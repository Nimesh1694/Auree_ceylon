<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Courier;
use App\Models\Order;
use App\Services\OrderInvoiceService;
use App\Services\OrderStateMachineService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    /**
     * Display a listing of orders.
     */
    public function index(Request $request): Response
    {
        $query = Order::with(['delivery', 'codCollection']);

        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('customer_phone', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('district')) {
            $query->where('district', $request->input('district'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('placed_at', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('placed_at', '<=', $request->input('date_to'));
        }

        $orders = $query->latest('id')->paginate(15)->withQueryString();

        $user = $request->user();
        if ($user && ($user->isOwner() || $user->isManagement())) {
            $orders->getCollection()->each(function ($order) {
                $order->delivery?->makeVisible(['actual_courier_fee']);
            });
        }

        return Inertia::render('Admin/Orders/Index', [
            'orders' => $orders,
            'filters' => $request->only(['search', 'status', 'district', 'date_from', 'date_to']),
        ]);
    }

    /**
     * Display the specified order details.
     */
    public function show(Order $order): Response
    {
        $order->load(['items.product', 'delivery.recordedBy', 'codCollection.reconciledBy', 'customer', 'agent']);

        // Explicitly unhide actual_courier_fee only for authorized management roles
        $user = auth()->user();
        if ($user && ($user->isOwner() || $user->isManagement())) {
            $order->delivery?->makeVisible(['actual_courier_fee']);
        }

        $auditLogs = AuditLog::where(function ($q) use ($order) {
            $q->where('auditable_type', Order::class)
                ->where('auditable_id', $order->id);
        })->orWhere(function ($q) use ($order) {
            $q->where('action', 'like', '%order%')
                ->where('new_values->order_number', $order->order_number);
        })->latest('id')->get();

        $couriers = Courier::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']);

        return Inertia::render('Admin/Orders/Show', [
            'order' => $order,
            'auditLogs' => $auditLogs,
            'couriers' => $couriers,
        ]);
    }

    /**
     * Transition order status via the strict state machine.
     */
    public function updateStatus(
        Request $request,
        Order $order,
        OrderStateMachineService $stateMachine
    ): RedirectResponse {
        $validated = $request->validate([
            'status' => 'required|string',
            'reason' => 'nullable|string|max:500',
            'courier_name' => 'nullable|string|max:100',
            'tracking_number' => 'nullable|string|max:100',
            'delivery_notes' => 'nullable|string|max:500',
        ]);

        $stateMachine->transition(
            order: $order,
            newStatus: $validated['status'],
            reason: $validated['reason'] ?? null,
            user: $request->user(),
            extraData: [
                'courier_name' => $validated['courier_name'] ?? null,
                'tracking_number' => $validated['tracking_number'] ?? null,
                'delivery_notes' => $validated['delivery_notes'] ?? null,
            ]
        );

        return back()->with('success', "Order #{$order->order_number} status updated to {$validated['status']}.");
    }

    /**
     * Assign courier and tracking details to the order delivery.
     */
    public function assignCourier(Request $request, Order $order): RedirectResponse
    {
        $validated = $request->validate([
            'courier_name' => 'required|string|max:100',
            'tracking_number' => 'required|string|max:100',
            'actual_courier_fee' => 'nullable|numeric|min:0|max:100000',
            'courier_invoice_reference' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:500',
        ]);

        $deliveryData = [
            'courier_name' => $validated['courier_name'],
            'tracking_number' => $validated['tracking_number'],
            'notes' => $validated['notes'] ?? null,
            'status' => 'assigned',
        ];

        if (array_key_exists('actual_courier_fee', $validated) && $validated['actual_courier_fee'] !== null && $validated['actual_courier_fee'] !== '') {
            $deliveryData['actual_courier_fee'] = round((float) $validated['actual_courier_fee'], 2);
            $deliveryData['courier_fee_recorded_by'] = $request->user()?->id;
            $deliveryData['courier_fee_recorded_at'] = now();
        }

        if (!empty($validated['courier_invoice_reference'])) {
            $deliveryData['courier_invoice_reference'] = $validated['courier_invoice_reference'];
        }

        $order->delivery()->updateOrCreate(
            ['order_id' => $order->id],
            $deliveryData
        );

        AuditLog::log('courier_assigned', $order, null, [
            'courier_name' => $validated['courier_name'],
            'tracking_number' => $validated['tracking_number'],
            'actual_courier_fee' => $deliveryData['actual_courier_fee'] ?? null,
        ]);

        return back()->with('success', 'Courier information assigned successfully.');
    }

    /**
     * Record or update actual courier expense for the order delivery.
     * Strictly restricted to authorized operations/financial roles.
     */
    public function updateCourierFee(Request $request, Order $order): RedirectResponse
    {
        $user = $request->user();

        // Server-side authorization check
        if (!$user || !($user->isOwner() || $user->isTechnicalAuthority() || $user->isOperationsManager() || in_array($user->getRoleValue(), ['accountant', 'operations_manager', 'owner', 'super_admin', 'it_manager'], true) || $user->hasPermissionTo('orders.courier.assign') || $user->hasPermissionTo('orders.cod.reconcile'))) {
            abort(403, 'Unauthorized to record or modify actual courier expenses.');
        }

        $validated = $request->validate([
            'actual_courier_fee' => 'required|numeric|min:0|max:100000',
            'courier_invoice_reference' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:500',
        ]);

        $delivery = $order->delivery;
        if (!$delivery) {
            $delivery = $order->delivery()->create([
                'status' => 'pending',
            ]);
        }

        $previousFee = $delivery->actual_courier_fee !== null ? (float) $delivery->actual_courier_fee : null;
        $newFee = round((float) $validated['actual_courier_fee'], 2);

        $updateData = [
            'actual_courier_fee' => $newFee,
            'courier_fee_recorded_by' => $user->id,
            'courier_fee_recorded_at' => now(),
        ];

        if (array_key_exists('courier_invoice_reference', $validated)) {
            $updateData['courier_invoice_reference'] = $validated['courier_invoice_reference'];
        }

        if (!empty($validated['notes'])) {
            $updateData['notes'] = $delivery->notes ? ($delivery->notes . ' | ' . $validated['notes']) : $validated['notes'];
        }

        $delivery->update($updateData);

        AuditLog::log('courier_fee_recorded', $order, ['actual_courier_fee' => $previousFee], [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'previous_courier_fee' => $previousFee,
            'actual_courier_fee' => $newFee,
            'customer_delivery_charge' => (float) $order->delivery_charge,
            'courier_invoice_reference' => $validated['courier_invoice_reference'] ?? null,
            'courier_name' => $delivery->courier_name,
        ]);

        return back()->with('success', 'Actual courier expense updated successfully.');
    }

    /**
     * Record COD cash collection & reconciliation.
     */
    public function reconcileCod(
        Request $request,
        Order $order,
        OrderStateMachineService $stateMachine
    ): RedirectResponse {
        $validated = $request->validate([
            'collected_amount' => 'required|numeric|min:0',
            'courier_reference' => 'nullable|string|max:100',
            'courier_name' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:500',
        ]);

        $cod = $stateMachine->recordCodCollection(
            order: $order,
            collectedAmount: (float) $validated['collected_amount'],
            courierReference: $validated['courier_reference'] ?? null,
            courierName: $validated['courier_name'] ?? null,
            notes: $validated['notes'] ?? null,
            user: $request->user()
        );

        return back()->with('success', "COD collection recorded. Status: {$cod->reconciliation_status}.");
    }

    /**
     * Download order commercial invoice or official payment receipt as PDF for management staff.
     * Enforces 'orders.view' permission and creates an audit log entry.
     */
    public function downloadInvoice(
        Request $request,
        Order $order,
        OrderInvoiceService $invoiceService
    ): \Symfony\Component\HttpFoundation\Response {
        $user = $request->user();

        if (!$user || (!$user->isOwner() && !$user->hasPermissionTo('orders.view'))) {
            abort(403, 'Unauthorized access: You do not possess the required permission.');
        }

        $order->load(['items', 'delivery', 'codCollection']);

        // Log audit event for invoice download
        AuditLog::log('order.invoice_downloaded', $order, null, [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'downloaded_by' => $user->id,
            'downloaded_by_email' => $user->email,
            'document_title' => $invoiceService->resolveDocumentMeta($order)['document_title'],
        ]);

        return $invoiceService->download($order);
    }
}

