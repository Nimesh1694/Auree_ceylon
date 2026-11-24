<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\CartService;
use App\Services\CustomerAddressService;
use App\Services\OrderInvoiceService;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CheckoutController extends Controller
{
    /**
     * Sri Lanka Districts for COD delivery.
     */
    public const SRI_LANKA_DISTRICTS = [
        'Ampara', 'Anuradhapura', 'Badulla', 'Batticaloa', 'Colombo',
        'Galle', 'Gampaha', 'Hambantota', 'Jaffna', 'Kalutara',
        'Kandy', 'Kegalle', 'Kilinochchi', 'Kurunegala', 'Mannar',
        'Matale', 'Matara', 'Monaragala', 'Mullaitivu', 'Nuwara Eliya',
        'Polonnaruwa', 'Puttalam', 'Ratnapura', 'Trincomalee', 'Vavuniya',
    ];

    /**
     * Display the checkout page.
     */
    public function index(Request $request, CartService $cartService): Response|RedirectResponse
    {
        $cart = $cartService->getDetails();

        if (empty($cart['items'])) {
            return redirect()->route('cart.index')->with('error', 'Your shopping cart is empty.');
        }

        $user = $request->user();
        $savedAddresses = $user
            ? $user->customerAddresses()->orderByDesc('is_default')->latest('id')->get()
            : [];

        return Inertia::render('Checkout/Index', [
            'cart' => $cart,
            'districts' => self::SRI_LANKA_DISTRICTS,
            'savedAddresses' => $savedAddresses,
            'prefill' => [
                'name' => $user?->name ?? '',
                'phone' => $user?->phone ?? '',
                'email' => $user?->email ?? '',
            ],
        ]);
    }

    /**
     * Process COD checkout and place the order.
     * Prevents duplicate orders and strictly validates on the server.
     */
    public function store(
        Request $request,
        CartService $cartService,
        OrderService $orderService,
        CustomerAddressService $addressService
    ): RedirectResponse
    {
        $validated = $request->validate([
            'customer_name' => 'required|string|min:2|max:255',
            'customer_phone' => 'required|string|min:9|max:20',
            'customer_email' => 'nullable|email|max:255',
            'shipping_address' => 'required|string|min:5|max:500',
            'district' => 'required|string|in:' . implode(',', self::SRI_LANKA_DISTRICTS),
            'city' => 'required|string|min:2|max:100',
            'postal_code' => 'nullable|string|max:20',
            'notes' => 'nullable|string|max:500',
            'save_address' => 'nullable|boolean',
        ]);

        $rawCart = $cartService->getRawCart();
        if (empty($rawCart)) {
            return redirect()->route('cart.index')->with('error', 'Your cart is empty.');
        }

        $user = $request->user();
        $referralCode = $request->session()->get('referral_code');
        $couponCode = $cartService->getAppliedCouponCode();

        $order = $orderService->createOrder(
            customerData: $validated,
            rawCart: $rawCart,
            authenticatedUser: $user,
            referralCode: $referralCode,
            agentId: null,
            couponCode: $couponCode
        );

        // Optional address saving for authenticated customer (Phase 5 Step 3 Phase D)
        // Uses centralized CustomerAddressService with user row-locking and capacity checks
        if ($user && !empty($validated['save_address'])) {
            try {
                $addressService->saveAddressFromCheckout($user, $validated);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning(
                    "Optional checkout address save failed for user #{$user->id}: " . $e->getMessage()
                );
            }
        }

        // Clear the shopping cart
        $cartService->clear();

        return redirect()->route('order.confirmation', [
            'orderNumber' => $order->order_number,
            'token' => $order->tracking_token,
        ])->with('success', 'Your order has been placed successfully!');
    }

    /**
     * Display order confirmation with security token verification.
     */
    public function confirmation(string $orderNumber, Request $request): Response|RedirectResponse
    {
        $token = $request->query('token');

        $order = Order::with(['items.product'])
            ->where('order_number', $orderNumber)
            ->where('tracking_token', $token)
            ->firstOrFail();

        return Inertia::render('Checkout/Confirmation', [
            'order' => $order,
        ]);
    }

    /**
     * Download the official receipt / invoice PDF for the confirmed order.
     */
    public function downloadReceipt(
        string $orderNumber,
        Request $request,
        OrderInvoiceService $invoiceService
    ): \Symfony\Component\HttpFoundation\Response {
        $token = $request->query('token');

        $query = Order::with(['items', 'delivery', 'codCollection'])
            ->where('order_number', $orderNumber);

        if ($token) {
            $query->where('tracking_token', $token);
        } elseif (auth()->check()) {
            $query->where('customer_id', auth()->id());
        } else {
            abort(403, 'Unauthorized access: tracking token required.');
        }

        $order = $query->firstOrFail();

        return $invoiceService->download($order);
    }

    /**
     * Show / stream the official receipt PDF inline in browser tab.
     */
    public function showReceipt(
        string $orderNumber,
        Request $request,
        OrderInvoiceService $invoiceService
    ): \Symfony\Component\HttpFoundation\Response {
        return $this->printReceipt($orderNumber, $request, $invoiceService);
    }

    /**
     * Stream the invoice PDF inline for direct browser viewing or printing.
     */
    public function printReceipt(
        string $orderNumber,
        Request $request,
        OrderInvoiceService $invoiceService
    ): \Symfony\Component\HttpFoundation\Response {
        $token = $request->query('token');

        $query = Order::with(['items', 'delivery', 'codCollection'])
            ->where('order_number', $orderNumber);

        if ($token) {
            $query->where('tracking_token', $token);
        } elseif (auth()->check()) {
            $query->where('customer_id', auth()->id());
        } else {
            abort(403, 'Unauthorized access: tracking token required.');
        }

        $order = $query->firstOrFail();

        return $invoiceService->stream($order);
    }

    /**
     * Apply coupon to current cart/checkout session.
     */
    public function applyCoupon(Request $request, CartService $cartService, \App\Services\CouponService $couponService): RedirectResponse
    {
        $validated = $request->validate([
            'coupon_code' => 'required|string|max:50',
        ]);

        $rawCart = $cartService->getRawCart();
        if (empty($rawCart)) {
            return back()->withErrors(['coupon' => 'Your cart is empty.']);
        }

        $cart = $cartService->getDetails();
        $cartItemsForCalc = [];
        foreach ($cart['items'] as $idx => $it) {
            $cartItemsForCalc[] = [
                'id' => $idx + 1,
                'product_id' => $it['product_id'],
                'category_id' => $it['category_id'] ?? null,
                'quantity' => $it['quantity'],
                'unit_price' => $it['unit_price'],
            ];
        }

        $referralCode = $request->session()->get('referral_code');
        $user = $request->user();

        try {
            $calc = $couponService->validateAndCalculate(
                couponOrCode: $validated['coupon_code'],
                cartItems: $cartItemsForCalc,
                customer: $user,
                customerEmail: $user?->email,
                customerPhone: $user?->phone,
                deliveryCharge: $cart['delivery_charge'] ?? 350.00,
                referralCode: $referralCode
            );

            $cartService->applyCoupon($calc['coupon']->code);

            return back()->with('success', "Coupon '{$calc['coupon']->code}' applied successfully!");
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors());
        }
    }

    /**
     * Remove applied coupon from checkout session.
     */
    public function removeCoupon(CartService $cartService): RedirectResponse
    {
        $cartService->removeCoupon();
        return back()->with('success', 'Coupon removed.');
    }
}

