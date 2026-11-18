<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\User;
use Carbon\Carbon;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class ProductReviewService
{
    /**
     * Submit a customer review for a verified purchased and delivered product.
     *
     * @throws ValidationException
     * @throws QueryException
     */
    public function submitReview(User $customer, array $data): ProductReview
    {
        $productId = (int) ($data['product_id'] ?? 0);
        $orderId = (int) ($data['order_id'] ?? 0);

        // 1. Order ownership verification
        $order = Order::find($orderId);
        if (! $order || (int) $order->customer_id !== (int) $customer->id) {
            throw ValidationException::withMessages([
                'order_id' => ['The specified order does not belong to your account.'],
            ]);
        }

        // 2. Delivered status verification
        if ($order->status !== 'delivered') {
            throw ValidationException::withMessages([
                'order_id' => ['Only delivered orders are eligible for product reviews.'],
            ]);
        }

        // 3. Product line-item containment
        $productInOrder = OrderItem::where('order_id', $order->id)
            ->where('product_id', $productId)
            ->exists();

        if (! $productInOrder) {
            throw ValidationException::withMessages([
                'product_id' => ['The specified product was not purchased in this order.'],
            ]);
        }

        // 4. Application-level deduplication
        $alreadyReviewed = ProductReview::where('customer_id', $customer->id)
            ->where('product_id', $productId)
            ->exists();

        if ($alreadyReviewed) {
            throw ValidationException::withMessages([
                'review' => ['You have already submitted a review for this product.'],
            ]);
        }

        // 5. Transactional persistence with exact unique constraint error handling
        try {
            return DB::transaction(function () use ($customer, $data, $productId, $orderId) {
                return ProductReview::create([
                    'product_id' => $productId,
                    'customer_id' => $customer->id,
                    'order_id' => $orderId,
                    'rating' => (int) $data['rating'],
                    'title' => (string) $data['title'],
                    'comment' => (string) $data['comment'],
                    'status' => 'pending',
                    'moderated_by' => null,
                    'moderated_at' => null,
                    'rejection_reason' => null,
                ]);
            });
        } catch (QueryException $e) {
            if ($this->isDuplicateReviewConstraintViolation($e)) {
                throw ValidationException::withMessages([
                    'review' => ['You have already submitted a review for this product.'],
                ]);
            }

            // Re-throw foreign key violations or unrelated database errors
            throw $e;
        }
    }

    /**
     * Approve a review, persist the state, and atomically synchronize the product rating cache.
     *
     * @throws AuthorizationException
     * @throws DomainException
     */
    public function approveReview(ProductReview $review, User $actor): void
    {
        if (! $actor->can('reviews.moderate')) {
            throw new AuthorizationException('Unauthorized to moderate reviews.');
        }

        DB::transaction(function () use ($review, $actor) {
            // Lock Product first, then ProductReview (hierarchical locking order)
            $product = Product::where('id', $review->product_id)->lockForUpdate()->firstOrFail();
            $lockedReview = ProductReview::where('id', $review->id)->lockForUpdate()->firstOrFail();

            // Verify relationship integrity
            if ((int) $lockedReview->product_id !== (int) $product->id) {
                throw new DomainException('ProductReview product_id does not match the locked Product id.');
            }

            // Persist review mutation first
            $lockedReview->status = 'approved';
            $lockedReview->moderated_by = $actor->id;
            $lockedReview->moderated_at = Carbon::now();
            $lockedReview->rejection_reason = null;
            $lockedReview->save();

            // Execute aggregate query post-mutation and sync product cache
            $this->syncProductRatingCache($product);
        });
    }

    /**
     * Reject a review, persist the state, and atomically synchronize the product rating cache.
     *
     * @throws AuthorizationException
     * @throws InvalidArgumentException
     * @throws DomainException
     */
    public function rejectReview(ProductReview $review, User $actor, string $reason): void
    {
        $trimmedReason = trim($reason);
        if (mb_strlen($trimmedReason) < 5 || mb_strlen($trimmedReason) > 255) {
            throw new InvalidArgumentException('Rejection reason must be between 5 and 255 characters.');
        }

        if (! $actor->can('reviews.moderate')) {
            throw new AuthorizationException('Unauthorized to moderate reviews.');
        }

        DB::transaction(function () use ($review, $actor, $trimmedReason) {
            // Lock Product first, then ProductReview (hierarchical locking order)
            $product = Product::where('id', $review->product_id)->lockForUpdate()->firstOrFail();
            $lockedReview = ProductReview::where('id', $review->id)->lockForUpdate()->firstOrFail();

            // Verify relationship integrity
            if ((int) $lockedReview->product_id !== (int) $product->id) {
                throw new DomainException('ProductReview product_id does not match the locked Product id.');
            }

            // Persist review mutation first
            $lockedReview->status = 'rejected';
            $lockedReview->moderated_by = $actor->id;
            $lockedReview->moderated_at = Carbon::now();
            $lockedReview->rejection_reason = $trimmedReason;
            $lockedReview->save();

            // Execute aggregate query post-mutation and sync product cache
            $this->syncProductRatingCache($product);
        });
    }

    /**
     * Permanently delete a review (Owner only) and atomically synchronize the product rating cache.
     *
     * @throws AuthorizationException
     * @throws DomainException
     */
    public function deleteReviewPermanently(ProductReview $review, User $actor): void
    {
        // Strict Owner-only authorization: no alternative permissions allowed
        if (! $actor->hasRole('owner')) {
            throw new AuthorizationException(
                'Only the Project Owner is authorized to permanently delete product reviews.'
            );
        }

        DB::transaction(function () use ($review) {
            // Lock Product first, then ProductReview (hierarchical locking order)
            $product = Product::where('id', $review->product_id)->lockForUpdate()->firstOrFail();
            $lockedReview = ProductReview::where('id', $review->id)->lockForUpdate()->firstOrFail();

            // Verify relationship integrity
            if ((int) $lockedReview->product_id !== (int) $product->id) {
                throw new DomainException('ProductReview product_id does not match the locked Product id.');
            }

            // Persist deletion first
            $lockedReview->delete();

            // Execute aggregate query post-deletion and sync product cache
            $this->syncProductRatingCache($product);
        });
    }

    /**
     * Atomically recalculate and synchronize a product's rating cache and review count.
     */
    protected function syncProductRatingCache(Product $product): void
    {
        $stats = ProductReview::where('product_id', $product->id)
            ->where('status', 'approved')
            ->selectRaw('COUNT(*) as total_count, COALESCE(ROUND(AVG(rating), 2), 0.00) as avg_rating')
            ->first();

        $reviewsCount = (int) ($stats->total_count ?? 0);
        $ratingCache = $reviewsCount > 0 ? number_format((float) $stats->avg_rating, 2, '.', '') : '0.00';

        $product->reviews_count = $reviewsCount;
        $product->rating_cache = $ratingCache;
        $product->save();
    }

    /**
     * Inspect exception to determine if it is specifically a uk_pr_customer_product constraint violation.
     */
    protected function isDuplicateReviewConstraintViolation(QueryException $e): bool
    {
        $message = $e->getMessage();
        $sqlState = (string) $e->getCode();

        if ($sqlState !== '23000') {
            return false;
        }

        return str_contains($message, 'uk_pr_customer_product')
            || (str_contains($message, 'UNIQUE constraint failed')
                && str_contains($message, 'product_reviews.customer_id')
                && str_contains($message, 'product_reviews.product_id'));
    }
}

