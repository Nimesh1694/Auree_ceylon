<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\OrderInvoiceService;
use App\Services\OrderStateMachineService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    /**
     * Display the authenticated customer's paginated order history.
     * Enforces customer role scoping and server-side data isolation.
     */
    public function index(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if (!$user) {
            return redirect()->route('login');
        }

        // Server-side redirection for non-customer management or agent roles
        if ($user->isOwner() || $user->isManagement()) {
            return redirect()->route('admin.dashboard');
        }

        if ($user->isAgent()) {
            return redirect()->route('agent.dashboard');
        }

        // Scope queries strictly to the authenticated customer ID
        $ordersQuery = Order::where('customer_id', $user->id);

        // Search by order reference
        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $ordersQuery->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%");
            });
        }

        // Filter by status grouping or specific status
        $status = trim((string) $request->input('status', ''));
        if ($status !== '' && $status !== 'all') {
            if ($status === 'pending') {
                $ordersQuery->whereIn('status', ['placed', 'confirmed', 'packed', 'shipped']);
            } elseif ($status === 'delivered') {
                $ordersQuery->whereIn('status', ['delivered', 'cash_collected', 'completed']);
            } elseif ($status === 'cancelled') {
                $ordersQuery->whereIn('status', ['cancelled', 'refused', 'returned']);
            } else {
                $ordersQuery->where('status', $status);
            }
        }

        $orders = $ordersQuery
            ->with(['items'])
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        $transformedOrders = $orders->through(function (Order $order) {
            return [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'total_amount' => (float) $order->total_amount,
                'total_amount_formatted' => 'LKR ' . number_format((float) $order->total_amount, 2),
                'items_count' => $order->items->sum('quantity'),
                'first_item_name' => $order->items->first()?->product_name ?? 'Products',
                'placed_at' => $order->placed_at
                    ? $order->placed_at->format('M d, Y h:i A')
                    : $order->created_at->format('M d, Y h:i A'),
                'tracking_token' => $order->tracking_token,
            ];
        });

        return Inertia::render('Customer/Orders/Index', [
            'orders' => $transformedOrders,
            'filters' => [
                'search' => $search,
                'status' => $status ?: 'all',
            ],
            'customer' => [
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]);
    }

    /**
     * Display detailed information for a single customer order.
     * Strictly verifies customer ownership and returns 404 for unauthorized access.
     */
    public function show(Request $request, string $orderNumber): Response|RedirectResponse
    {
        $user = $request->user();

        if (!$user) {
            return redirect()->route('login');
        }

        // Server-side redirection for non-customer management or agent roles
        if ($user->isOwner() || $user->isManagement()) {
            return redirect()->route('admin.dashboard');
        }

        if ($user->isAgent()) {
            return redirect()->route('agent.dashboard');
        }

        // Strictly verify customer ownership using customer_id = Auth::id()
        $order = Order::with(['items', 'delivery', 'codCollection'])
            ->where('customer_id', $user->id)
            ->where('order_number', $orderNumber)
            ->firstOrFail();

        $delivery = $order->delivery;

        $paymentStatus = match ($order->status) {
            'cash_collected', 'completed' => 'Paid (Collected by Courier)',
            'delivered' => 'Delivered (Reconciliation in Progress)',
            'cancelled', 'refused', 'returned' => 'Cancelled / Not Collected',
            default => 'Payment on Delivery (Pending)',
        };

        $canCancel = in_array($order->status, [
            OrderStateMachineService::STATUS_PLACED,
            OrderStateMachineService::STATUS_CONFIRMED,
            OrderStateMachineService::STATUS_PACKED,
        ], true);

        return Inertia::render('Customer/Orders/Show', [
            'order' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'can_cancel' => $canCancel,
                'subtotal' => (float) $order->subtotal,
                'subtotal_formatted' => 'LKR ' . number_format((float) $order->subtotal, 2),
                'delivery_charge' => (float) $order->delivery_charge,
                'delivery_charge_formatted' => 'LKR ' . number_format((float) $order->delivery_charge, 2),
                'total_amount' => (float) $order->total_amount,
                'total_amount_formatted' => 'LKR ' . number_format((float) $order->total_amount, 2),
                'payment_method' => strtoupper((string) ($order->payment_method ?: 'cod')),
                'payment_status' => $paymentStatus,
                'placed_at' => $order->placed_at
                    ? $order->placed_at->format('M d, Y h:i A')
                    : $order->created_at->format('M d, Y h:i A'),
                'notes' => $order->notes,
                'tracking_token' => $order->tracking_token,
                'invoice_url' => route('customer.orders.invoice', $order->order_number),
                'shipping' => [
                    'customer_name' => $order->customer_name,
                    'customer_phone' => $order->customer_phone,
                    'customer_email' => $order->customer_email,
                    'shipping_address' => $order->shipping_address,
                    'district' => $order->district,
                    'city' => $order->city,
                    'postal_code' => $order->postal_code,
                ],
                'delivery' => $delivery ? [
                    'courier_name' => $delivery->courier_name,
                    'tracking_number' => $delivery->tracking_number,
                    'status' => $delivery->status,
                    'dispatched_at' => $delivery->dispatched_at ? $delivery->dispatched_at->format('M d, Y h:i A') : null,
                    'delivered_at' => $delivery->delivered_at ? $delivery->delivered_at->format('M d, Y h:i A') : null,
                    'notes' => $delivery->notes,
                ] : null,
                'items' => $order->items->map(function (OrderItem $item) {
                    return [
                        'id' => $item->id,
                        'product_id' => $item->product_id,
                        'product_name' => $item->product_name,
                        'product_sku' => $item->product_sku,
                        'unit_price' => (float) $item->unit_price,
                        'unit_price_formatted' => 'LKR ' . number_format((float) $item->unit_price, 2),
                        'quantity' => $item->quantity,
                        'subtotal' => (float) $item->subtotal,
                        'subtotal_formatted' => 'LKR ' . number_format((float) $item->subtotal, 2),
                    ];
                }),
            ],
        ]);
    }

    /**
     * Safely cancel an eligible order on customer request.
     * Enforces customer ownership, concurrency row locking, stock restoration, and commission reversal.
     */
    public function cancel(
        Request $request,
        string $orderNumber,
        OrderStateMachineService $stateMachine
    ): RedirectResponse {
        $user = $request->user();

        if (!$user) {
            return redirect()->route('login');
        }

        // Server-side redirection for non-customer management or agent roles
        if ($user->isOwner() || $user->isManagement()) {
            return redirect()->route('admin.dashboard');
        }

        if ($user->isAgent()) {
            return redirect()->route('agent.dashboard');
        }

        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        return DB::transaction(function () use ($user, $orderNumber, $validated, $stateMachine) {
            // Atomic row locking within transaction prevents duplicate concurrent cancellations
            $order = Order::where('customer_id', $user->id)
                ->where('order_number', $orderNumber)
                ->lockForUpdate()
                ->firstOrFail();

            if ($order->status === OrderStateMachineService::STATUS_CANCELLED) {
                return redirect()->route('customer.orders.show', $order->order_number)
                    ->with('error', "Order #{$order->order_number} is already cancelled.");
            }

            if (!in_array($order->status, [
                OrderStateMachineService::STATUS_PLACED,
                OrderStateMachineService::STATUS_CONFIRMED,
                OrderStateMachineService::STATUS_PACKED,
            ], true)) {
                return redirect()->route('customer.orders.show', $order->order_number)
                    ->with('error', "Order #{$order->order_number} cannot be cancelled because it is currently {$order->status}. Orders that are already dispatched, delivered, or completed cannot be cancelled online.");
            }

            $cancellationReason = 'Customer Cancelled: ' . trim($validated['reason']);

            $stateMachine->transition(
                order: $order,
                newStatus: OrderStateMachineService::STATUS_CANCELLED,
                reason: $cancellationReason,
                user: $user
            );

            return redirect()->route('customer.orders.show', $order->order_number)
                ->with('success', "Order #{$order->order_number} has been cancelled successfully. Any reserved inventory has been restored.");
        });
    }

    /**
     * Download customer commercial invoice or official payment receipt as PDF.
     * Strictly verifies customer ownership using customer_id = Auth::id() and returns 404 for unauthorized access.
     */
    public function downloadInvoice(
        Request $request,
        string $orderNumber,
        OrderInvoiceService $invoiceService
    ): \Symfony\Component\HttpFoundation\Response {
        $user = $request->user();

        if (!$user) {
            return redirect()->route('login');
        }

        // Strictly verify customer ownership using customer_id = Auth::id()
        $order = Order::with(['items', 'delivery', 'codCollection'])
            ->where('customer_id', $user->id)
            ->where('order_number', $orderNumber)
            ->firstOrFail();

        return $invoiceService->download($order);
    }
}

