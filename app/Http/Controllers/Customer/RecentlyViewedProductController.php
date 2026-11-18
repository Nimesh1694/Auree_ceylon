<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\MergeRecentlyViewedRequest;
use App\Services\RecentlyViewedProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecentlyViewedProductController extends Controller
{
    public function __construct(private RecentlyViewedProductService $service) {}

    /**
     * Retrieve the authenticated customer's recently viewed products list.
     */
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->hasRole('customer'), 403, 'Unauthorized.');

        $excludeId = $request->integer('exclude_product_id') ?: null;
        $items = $this->service->getForCustomer($request->user(), 20, $excludeId);

        return response()->json([
            'success' => true,
            'data'    => $items,
        ]);
    }

    /**
     * Record an authenticated view for a product.
     */
    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()?->hasRole('customer'), 403, 'Unauthorized.');

        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'min:1'],
        ]);

        $this->service->recordView($request->user(), (int) $validated['product_id']);

        return response()->json([
            'success' => true,
        ]);
    }

    /**
     * Merge guest viewing history into the authenticated customer account.
     */
    public function merge(MergeRecentlyViewedRequest $request): JsonResponse
    {
        $result = $this->service->mergeGuestHistory(
            $request->user(),
            $request->validated('idempotency_key'),
            $request->validated('items')
        );

        return response()->json($result, 200);
    }
}
