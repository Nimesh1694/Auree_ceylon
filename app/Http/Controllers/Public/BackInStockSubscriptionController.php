<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Public\StoreBackInStockSubscriptionRequest;
use App\Models\BackInStockSubscription;
use App\Models\Product;
use App\Services\BackInStockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BackInStockSubscriptionController extends Controller
{
    public function __construct(
        protected BackInStockService $service
    ) {}

    /**
     * Store a new back-in-stock alert subscription (Authenticated or Guest).
     */
    public function store(StoreBackInStockSubscriptionRequest $request): JsonResponse|RedirectResponse
    {
        $productId = (int) $request->input('product_id');
        $product = Product::find($productId);

        if (!$product || !$product->is_active || $product->stock_quantity > 0) {
            $msg = 'Back-in-stock alerts are only available for active, out-of-stock products.';
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'status' => 'ineligible_product',
                    'message' => $msg,
                ], 422);
            }

            return back()->with('error', $msg);
        }

        $user = $request->user();
        $email = ($user && $user->email_verified_at) ? $user->email : (string) $request->input('email');
        $locale = $request->input('locale', app()->getLocale());

        $result = $this->service->subscribe($product, $email, $user, $locale);

        if ($request->wantsJson()) {
            return response()->json($result, $result['success'] ? 200 : 422);
        }

        if ($result['success']) {
            return back()->with('success', $result['message']);
        }

        return back()->with('error', $result['message']);
    }

    /**
     * Show guest verification confirmation page (GET - Safe anti-scanner view).
     * Does NOT mutate subscription state.
     */
    public function showVerify(Request $request): Response
    {
        $rawToken = (string) $request->query('token', '');
        if (empty($rawToken)) {
            return Inertia::render('BackInStock/Verify', [
                'status' => 'invalid_token',
                'message' => 'No verification token was provided.',
            ]);
        }

        $tokenHash = hash('sha256', trim($rawToken));

        /** @var BackInStockSubscription|null $subscription */
        $subscription = BackInStockSubscription::with('product')
            ->where('verification_token_hash', $tokenHash)
            ->first();

        if (!$subscription) {
            return Inertia::render('BackInStock/Verify', [
                'status' => 'invalid_token',
                'message' => 'The verification link is invalid or has already been used.',
            ]);
        }

        if ($subscription->verification_expires_at && $subscription->verification_expires_at->isPast()) {
            return Inertia::render('BackInStock/Verify', [
                'status' => 'token_expired',
                'message' => 'This verification link has expired. Please subscribe again from the product page.',
            ]);
        }

        if ($subscription->status === BackInStockSubscription::STATUS_PENDING && $subscription->is_active) {
            return Inertia::render('BackInStock/Verify', [
                'status' => 'already_verified',
                'message' => 'Your email alert is already confirmed and active.',
                'product' => $subscription->product,
            ]);
        }

        return Inertia::render('BackInStock/Verify', [
            'status' => 'confirm_prompt',
            'product' => $subscription->product,
            'token' => $rawToken,
        ]);
    }

    /**
     * Execute guest verification (POST - State mutation).
     */
    public function verify(Request $request): Response|RedirectResponse|JsonResponse
    {
        $rawToken = (string) $request->input('token', '');
        if (empty($rawToken)) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'error' => 'invalid_token'], 422);
            }
            return back()->with('error', 'Invalid verification token.');
        }

        $result = $this->service->verifyGuestSubscription($rawToken);

        if ($request->wantsJson()) {
            return response()->json($result, $result['success'] ? 200 : 422);
        }

        return Inertia::render('BackInStock/Verify', [
            'status' => $result['success'] ? 'verified_success' : ($result['error'] ?? 'invalid_token'),
            'message' => $result['message'],
            'product' => isset($result['subscription']) ? $result['subscription']->product : null,
        ]);
    }

    /**
     * Show unsubscribe confirmation page (GET - Safe anti-scanner view).
     * Validates HMAC signed URL signature without mutating subscription state.
     */
    public function showUnsubscribe(Request $request, BackInStockSubscription $subscription): Response
    {
        if (!$request->hasValidSignature()) {
            return Inertia::render('BackInStock/Unsubscribe', [
                'status' => 'invalid_signature',
                'message' => 'This unsubscribe link is invalid or has expired.',
            ]);
        }

        $subscription->load('product');

        if ($subscription->status === BackInStockSubscription::STATUS_UNSUBSCRIBED) {
            return Inertia::render('BackInStock/Unsubscribe', [
                'status' => 'already_unsubscribed',
                'message' => 'You are already unsubscribed from alerts for this item.',
                'product' => $subscription->product,
                'subscriptionId' => $subscription->id,
            ]);
        }

        return Inertia::render('BackInStock/Unsubscribe', [
            'status' => 'confirm_prompt',
            'product' => $subscription->product,
            'subscriptionId' => $subscription->id,
        ]);
    }

    /**
     * Execute unsubscribe mutation (POST - State mutation).
     */
    public function unsubscribe(Request $request, BackInStockSubscription $subscription): Response|RedirectResponse|JsonResponse
    {
        if (!$request->hasValidSignature()) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'error' => 'invalid_signature'], 403);
            }
            return Inertia::render('BackInStock/Unsubscribe', [
                'status' => 'invalid_signature',
                'message' => 'This unsubscribe link is invalid or has expired.',
            ]);
        }

        $result = $this->service->unsubscribe($subscription);

        if ($request->wantsJson()) {
            return response()->json($result, 200);
        }

        $subscription->load('product');

        return Inertia::render('BackInStock/Unsubscribe', [
            'status' => 'unsubscribed_success',
            'message' => $result['message'],
            'product' => $subscription->product,
            'subscriptionId' => $subscription->id,
        ]);
    }
}
