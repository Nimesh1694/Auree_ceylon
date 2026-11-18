<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Public\StoreProductReviewRequest;
use App\Models\Product;
use App\Models\ProductReview;
use App\Services\ProductReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class ProductReviewController extends Controller
{
    public function __construct(
        protected ProductReviewService $reviewService
    ) {}

    /**
     * Store a newly created product review by a verified purchaser.
     */
    public function store(StoreProductReviewRequest $request, Product $product): JsonResponse|RedirectResponse
    {
        $payload = array_merge($request->validated(), [
            'product_id' => $product->id,
        ]);

        $review = $this->reviewService->submitReview($request->user(), $payload);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Thank you! Your review has been submitted for moderation.',
                'review' => $review,
            ], 201);
        }

        return back()->with('success', 'Thank you! Your review has been submitted for moderation.');
    }

    /**
     * Display a listing of approved reviews for the product.
     */
    public function index(Product $product): JsonResponse
    {
        $reviews = ProductReview::where('product_id', $product->id)
            ->approved()
            ->with(['customer:id,name'])
            ->latest()
            ->paginate(10);

        return response()->json([
            'rating_cache' => (string) $product->rating_cache,
            'reviews_count' => (int) $product->reviews_count,
            'reviews' => $reviews,
        ]);
    }
}

