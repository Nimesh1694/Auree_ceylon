<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Display the authenticated customer's dashboard.
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
        $customerId = $user->id;

        $ordersQuery = Order::where('customer_id', $customerId);

        // Calculate statistics based on actual customer records
        $totalOrders = (clone $ordersQuery)->count();

        $pendingOrders = (clone $ordersQuery)
            ->whereIn('status', ['placed', 'confirmed', 'packed', 'shipped'])
            ->count();

        $completedOrders = (clone $ordersQuery)
            ->whereIn('status', ['delivered', 'cash_collected', 'completed'])
            ->count();

        $cancelledOrders = (clone $ordersQuery)
            ->whereIn('status', ['cancelled', 'refused', 'returned'])
            ->count();

        $totalSpend = (float) (clone $ordersQuery)
            ->whereIn('status', ['delivered', 'cash_collected', 'completed'])
            ->sum('total_amount');

        // Fetch recent orders (latest 5)
        $recentOrders = (clone $ordersQuery)
            ->with(['items'])
            ->latest('id')
            ->take(5)
            ->get()
            ->map(function (Order $order) {
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

        return Inertia::render('Customer/Dashboard', [
            'customer' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'joined_date' => $user->created_at->format('F Y'),
            ],
            'statistics' => [
                'total_orders' => $totalOrders,
                'pending_orders' => $pendingOrders,
                'completed_orders' => $completedOrders,
                'cancelled_orders' => $cancelledOrders,
                'total_spend' => $totalSpend,
                'total_spend_formatted' => 'LKR ' . number_format($totalSpend, 2),
            ],
            'recentOrders' => $recentOrders,
        ]);
    }
}

