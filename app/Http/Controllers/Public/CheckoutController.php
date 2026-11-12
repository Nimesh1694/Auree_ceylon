<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\CartService;
use App\Services\CustomerAddressService;
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

        $order = $orderService->createOrder(
            customerData: $validated,
            rawCart: $rawCart,
            authenticatedUser: $user,
            referralCode: $referralCode,
            agentId: null
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
}

