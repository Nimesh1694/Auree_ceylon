<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Public\CheckoutController;
use App\Models\Order;
use App\Models\Product;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orderService
    ) {}

    /**
     * Display a listing of orders placed by this agent.
     */
    public function index(Request $request): Response
    {
        $agent = $request->user();

        $orders = Order::where('agent_id', $agent->id)
            ->with(['delivery', 'items'])
            ->latest('id')
            ->paginate(15);

        return Inertia::render('Agent/Orders/Index', [
            'orders' => $orders,
        ]);
    }

    /**
     * Show form for an agent to place an order on behalf of a customer.
     */
    public function create(): Response
    {
        $products = Product::where('is_active', true)
            ->where('stock_quantity', '>', 0)
            ->orderBy('name_en')
            ->get(['id', 'name_en', 'name_si', 'sku', 'price', 'stock_quantity']);

        return Inertia::render('Agent/Orders/Create', [
            'products' => $products,
            'districts' => CheckoutController::SRI_LANKA_DISTRICTS,
        ]);
    }

    /**
     * Store the order placed by the agent on behalf of a customer.
     */
    public function store(Request $request): RedirectResponse
    {
        $agent = $request->user();

        $validated = $request->validate([
            'customer_name' => 'required|string|min:2|max:255',
            'customer_phone' => 'required|string|min:9|max:20',
            'customer_email' => 'nullable|email|max:255',
            'shipping_address' => 'required|string|min:5|max:500',
            'district' => 'required|string|in:' . implode(',', CheckoutController::SRI_LANKA_DISTRICTS),
            'city' => 'required|string|min:2|max:100',
            'postal_code' => 'nullable|string|max:20',
            'notes' => 'nullable|string|max:500',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1|max:99',
        ]);

        // Build raw cart array [product_id => quantity]
        $rawCart = [];
        foreach ($validated['items'] as $item) {
            $pid = (int) $item['product_id'];
            $rawCart[$pid] = ($rawCart[$pid] ?? 0) + (int) $item['quantity'];
        }

        $order = $this->orderService->createOrder(
            customerData: $validated,
            rawCart: $rawCart,
            authenticatedUser: null,
            referralCode: $agent->referral_code, // Credited to agent referral
            agentId: $agent->id                 // Physically placed by agent
        );

        return redirect()->route('agent.orders.index')->with('success', "Order #{$order->order_number} placed successfully on behalf of {$validated['customer_name']}.");
    }
}

