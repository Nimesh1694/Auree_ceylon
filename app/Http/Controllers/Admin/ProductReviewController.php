<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ModerateProductReviewRequest;
use App\Models\ProductReview;
use App\Services\ProductReviewService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductReviewController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected ProductReviewService $reviewService
    ) {}

    /**
     * Display a paginated listing of product reviews for administrative moderation.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', ProductReview::class);

        $query = ProductReview::query()
            ->with([
                'product:id,name_en,name_si,slug,rating_cache,reviews_count',
                'customer:id,name,email',
                'moderator:id,name',
            ]);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('search')) {
            $search = '%' . $request->input('search') . '%';
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', $search)
                    ->orWhere('comment', 'like', $search)
                    ->orWhereHas('product', function ($pq) use ($search) {
                        $pq->where('name_en', 'like', $search)
                            ->orWhere('sku', 'like', $search);
                    })
                    ->orWhereHas('customer', function ($cq) use ($search) {
                        $cq->where('name', 'like', $search)
                            ->orWhere('email', 'like', $search);
                    });
            });
        }

        $reviews = $query->latest()->paginate(15)->withQueryString();

        $counts = [
            'all' => ProductReview::count(),
            'pending' => ProductReview::pending()->count(),
            'approved' => ProductReview::approved()->count(),
            'rejected' => ProductReview::rejected()->count(),
        ];

        return Inertia::render('Admin/Marketing/Reviews/Index', [
            'reviews' => $reviews,
            'filters' => $request->only(['status', 'search']),
            'counts' => $counts,
        ]);
    }

    /**
     * Display detailed review information and verified purchaser context.
     */
    public function show(ProductReview $review): Response
    {
        $this->authorize('view', $review);

        $review->load([
            'product:id,name_en,name_si,slug,price,rating_cache,reviews_count',
            'customer:id,name,email',
            'order.items.product:id,name_en,sku',
            'moderator:id,name',
        ]);

        return Inertia::render('Admin/Marketing/Reviews/Show', [
            'review' => $review,
        ]);
    }

    /**
     * Approve a review.
     */
    public function approve(ModerateProductReviewRequest $request, ProductReview $review): RedirectResponse
    {
        $this->authorize('moderate', $review);

        $this->reviewService->approveReview($review, $request->user());

        return back()->with('success', 'Review approved successfully. Product rating cache updated.');
    }

    /**
     * Reject a review.
     */
    public function reject(ModerateProductReviewRequest $request, ProductReview $review): RedirectResponse
    {
        $this->authorize('moderate', $review);

        $this->reviewService->rejectReview(
            $review,
            $request->user(),
            (string) $request->input('rejection_reason')
        );

        return back()->with('success', 'Review rejected successfully.');
    }

    /**
     * Permanently delete a review (Owner Only).
     */
    public function destroy(Request $request, ProductReview $review): RedirectResponse
    {
        $this->authorize('delete', $review);

        $this->reviewService->deleteReviewPermanently($review, $request->user());

        return redirect()->route('admin.reviews.index')
            ->with('success', 'Review permanently deleted. Product rating cache updated.');
    }
}
