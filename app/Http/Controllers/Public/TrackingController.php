<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TrackingController extends Controller
{
    /**
     * Display order tracking form.
     * If an authenticated customer provides an order number they own, pre-load their tracking details.
     */
    public function index(Request $request): Response
    {
        $orderNumber = trim((string) $request->query('order_number', ''));
        $user = $request->user();

        if ($user && $user->isCustomer() && $orderNumber !== '') {
            $order = Order::with(['items.product', 'delivery'])
                ->where('customer_id', $user->id)
                ->where('order_number', $orderNumber)
                ->first();

            if ($order) {
                return Inertia::render('Tracking/Index', [
                    'searched' => true,
                    'order' => $order,
                    'searchedOrderNumber' => $order->order_number,
                    'searchedPhone' => $order->customer_phone,
                ]);
            }
        }

        return Inertia::render('Tracking/Index', [
            'searched' => false,
            'order' => null,
            'searchedOrderNumber' => $orderNumber,
            'searchedPhone' => '',
        ]);
    }

    /**
     * Look up and display order status securely.
     * Prevents sequential ID guessing attacks by requiring order number + phone match.
     */
    public function track(Request $request, OrderService $orderService): Response
    {
        $validated = $request->validate([
            'order_number' => 'required|string|max:50',
            'phone' => 'required|string|max:25',
        ]);

        $order = $orderService->trackOrder(
            orderNumber: $validated['order_number'],
            phone: $validated['phone']
        );

        return Inertia::render('Tracking/Index', [
            'searched' => true,
            'order' => $order,
            'searchedOrderNumber' => $validated['order_number'],
            'searchedPhone' => $validated['phone'],
        ]);
    }
}

