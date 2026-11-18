<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReceivePurchaseOrderRequest;
use App\Http\Requests\Admin\StorePurchaseOrderRequest;
use App\Http\Requests\Admin\UpdatePurchaseOrderRequest;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Services\PurchaseOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseOrderController extends Controller
{
    public function __construct(
        private readonly PurchaseOrderService $poService
    ) {}

    private function authorizePermission(?User $user, string $permission): void
    {
        if (!$user || (!$user->isOwner() && !$user->hasPermissionTo($permission))) {
            abort(403, 'Unauthorized access to purchase order management.');
        }
    }

    private function canViewCost(?User $user): bool
    {
        return (bool) ($user && ($user->isOwner() || $user->hasPermissionTo('products.cost_price.view')));
    }

    public function index(Request $request): Response
    {
        $user = $request->user();
        $this->authorizePermission($user, 'purchase_orders.view');

        $search = trim((string) $request->input('search', ''));
        $status = (string) $request->input('status', 'all');
        $canViewCost = $this->canViewCost($user);

        $query = PurchaseOrder::query()
            ->with('supplier:id,name,code')
            ->withCount('items');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('po_number', 'like', "%{$search}%")
                    ->orWhereHas('supplier', fn ($sq) => $sq->where('name', 'like', "%{$search}%"));
            });
        }

        if (in_array($status, [
            PurchaseOrder::STATUS_DRAFT,
            PurchaseOrder::STATUS_ORDERED,
            PurchaseOrder::STATUS_PARTIALLY_RECEIVED,
            PurchaseOrder::STATUS_RECEIVED,
            PurchaseOrder::STATUS_CANCELLED,
        ], true)) {
            $query->where('status', $status);
        }

        $purchaseOrders = $query->latest('id')->paginate(15)->withQueryString()->through(function (PurchaseOrder $po) use ($canViewCost) {
            return [
                'id' => $po->id,
                'po_number' => $po->po_number,
                'supplier_name' => $po->supplier?->name,
                'supplier_code' => $po->supplier?->code,
                'status' => $po->status,
                'items_count' => $po->items_count,
                'expected_delivery_date' => $po->expected_delivery_date?->toDateString(),
                'ordered_at' => $po->ordered_at?->toISOString(),
                'total_cost' => $canViewCost ? (string) $po->total_cost : null,
                'created_at' => $po->created_at?->toISOString(),
            ];
        });

        return Inertia::render('Admin/PurchaseOrders/Index', [
            'purchaseOrders' => $purchaseOrders,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
            'stats' => [
                'draft' => PurchaseOrder::where('status', PurchaseOrder::STATUS_DRAFT)->count(),
                'ordered' => PurchaseOrder::where('status', PurchaseOrder::STATUS_ORDERED)->count(),
                'partially_received' => PurchaseOrder::where('status', PurchaseOrder::STATUS_PARTIALLY_RECEIVED)->count(),
                'received' => PurchaseOrder::where('status', PurchaseOrder::STATUS_RECEIVED)->count(),
            ],
            'canCreate' => $user->isOwner() || $user->hasPermissionTo('purchase_orders.create'),
            'canViewCost' => $canViewCost,
        ]);
    }

    public function create(Request $request): Response
    {
        $user = $request->user();
        $this->authorizePermission($user, 'purchase_orders.create');

        return Inertia::render('Admin/PurchaseOrders/Create', [
            'suppliers' => $this->getActiveSuppliersWithProducts($this->canViewCost($user)),
            'canViewCost' => $this->canViewCost($user),
        ]);
    }

    public function store(StorePurchaseOrderRequest $request): RedirectResponse
    {
        $po = $this->poService->createDraft(
            $request->validated(),
            $request->user(),
            $this->canViewCost($request->user())
        );

        return redirect()
            ->route('admin.purchase-orders.show', $po)
            ->with('success', "Purchase Order {$po->po_number} created as Draft.");
    }

    public function show(Request $request, PurchaseOrder $purchaseOrder): Response
    {
        $user = $request->user();
        $this->authorizePermission($user, 'purchase_orders.view');

        $canViewCost = $this->canViewCost($user);

        $purchaseOrder->load([
            'supplier:id,name,code,payment_terms,default_lead_time_days,status',
            'creator:id,name',
            'approver:id,name',
            'canceller:id,name',
            'items.product:id,name_en,sku,stock_quantity',
            'receipts' => fn ($q) => $q->latest('id')->with(['receiver:id,name', 'items.purchaseOrderItem:id,product_name,product_sku']),
            'inventoryMovements' => fn ($q) => $q->latest('id')->with(['product:id,name_en,sku', 'user:id,name']),
        ]);

        return Inertia::render('Admin/PurchaseOrders/Show', [
            'purchaseOrder' => [
                'id' => $purchaseOrder->id,
                'po_number' => $purchaseOrder->po_number,
                'status' => $purchaseOrder->status,
                'supplier' => $purchaseOrder->supplier,
                'expected_delivery_date' => $purchaseOrder->expected_delivery_date?->toDateString(),
                'ordered_at' => $purchaseOrder->ordered_at?->toISOString(),
                'received_at' => $purchaseOrder->received_at?->toISOString(),
                'cancelled_at' => $purchaseOrder->cancelled_at?->toISOString(),
                'cancellation_reason' => $purchaseOrder->cancellation_reason,
                'notes' => $purchaseOrder->notes,
                'subtotal' => $canViewCost ? (string) $purchaseOrder->subtotal : null,
                'tax_amount' => $canViewCost ? (string) $purchaseOrder->tax_amount : null,
                'shipping_cost' => $canViewCost ? (string) $purchaseOrder->shipping_cost : null,
                'total_cost' => $canViewCost ? (string) $purchaseOrder->total_cost : null,
                'creator_name' => $purchaseOrder->creator?->name,
                'approver_name' => $purchaseOrder->approver?->name,
                'canceller_name' => $purchaseOrder->canceller?->name,
                'items' => $purchaseOrder->items->map(fn ($item) => [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'product_name' => $item->product_name,
                    'product_sku' => $item->product_sku,
                    'supplier_sku' => $item->supplier_sku,
                    'ordered_qty' => $item->ordered_qty,
                    'received_qty' => $item->received_qty,
                    'remaining_qty' => max(0, $item->ordered_qty - $item->received_qty),
                    'unit_cost' => $canViewCost ? (string) $item->unit_cost : null,
                    'line_total' => $canViewCost ? (string) $item->line_total : null,
                    'current_stock' => $item->product?->stock_quantity,
                ])->values(),
                'receipts' => $purchaseOrder->receipts->map(fn ($r) => [
                    'id' => $r->id,
                    'receipt_number' => $r->receipt_number,
                    'received_by_name' => $r->receiver?->name,
                    'received_at' => $r->received_at?->toISOString(),
                    'notes' => $r->notes,
                    'items' => $r->items->map(fn ($ri) => [
                        'id' => $ri->id,
                        'product_name' => $ri->purchaseOrderItem?->product_name,
                        'product_sku' => $ri->purchaseOrderItem?->product_sku,
                        'quantity_received' => $ri->quantity_received,
                        'unit_cost' => $canViewCost ? (string) $ri->unit_cost : null,
                    ])->values(),
                ])->values(),
                'inventory_movements' => $purchaseOrder->inventoryMovements->map(fn ($m) => [
                    'id' => $m->id,
                    'product_name' => $m->product?->name_en,
                    'quantity' => $m->quantity,
                    'previous_stock' => $m->previous_stock,
                    'new_stock' => $m->new_stock,
                    'reason' => $m->reason,
                    'actor_name' => $m->user?->name,
                    'created_at' => $m->created_at?->toISOString(),
                ])->values(),
            ],
            'permissions' => [
                'canEdit' => $purchaseOrder->status === PurchaseOrder::STATUS_DRAFT && ($user->isOwner() || $user->hasPermissionTo('purchase_orders.create')),
                'canApprove' => $user->isOwner() && $purchaseOrder->status === PurchaseOrder::STATUS_DRAFT,
                'canReceive' => in_array($purchaseOrder->status, [PurchaseOrder::STATUS_ORDERED, PurchaseOrder::STATUS_PARTIALLY_RECEIVED], true)
                    && ($user->isOwner() || $user->hasPermissionTo('purchase_orders.receive')),
                'canCancel' => in_array($purchaseOrder->status, [PurchaseOrder::STATUS_DRAFT, PurchaseOrder::STATUS_ORDERED], true)
                    && $purchaseOrder->items->sum('received_qty') === 0
                    && ($user->isOwner() || $user->hasPermissionTo('purchase_orders.cancel')),
                'canViewCost' => $canViewCost,
            ],
        ]);
    }

    public function edit(Request $request, PurchaseOrder $purchaseOrder): Response|RedirectResponse
    {
        $user = $request->user();
        $this->authorizePermission($user, 'purchase_orders.create');

        if ($purchaseOrder->status !== PurchaseOrder::STATUS_DRAFT) {
            return redirect()
                ->route('admin.purchase-orders.show', $purchaseOrder)
                ->with('error', 'Only draft purchase orders can be edited.');
        }

        $purchaseOrder->load('items');
        $canViewCost = $this->canViewCost($user);

        return Inertia::render('Admin/PurchaseOrders/Edit', [
            'purchaseOrder' => [
                'id' => $purchaseOrder->id,
                'po_number' => $purchaseOrder->po_number,
                'supplier_id' => $purchaseOrder->supplier_id,
                'expected_delivery_date' => $purchaseOrder->expected_delivery_date?->toDateString() ?? '',
                'tax_amount' => $canViewCost ? (string) $purchaseOrder->tax_amount : null,
                'shipping_cost' => $canViewCost ? (string) $purchaseOrder->shipping_cost : null,
                'notes' => $purchaseOrder->notes ?? '',
                'items' => $purchaseOrder->items->map(fn ($item) => [
                    'product_id' => $item->product_id,
                    'ordered_qty' => $item->ordered_qty,
                    'unit_cost' => $canViewCost ? (string) $item->unit_cost : null,
                ])->values(),
            ],
            'suppliers' => $this->getActiveSuppliersWithProducts($canViewCost),
            'canViewCost' => $canViewCost,
        ]);
    }

    public function update(UpdatePurchaseOrderRequest $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $updated = $this->poService->updateDraft(
            $purchaseOrder,
            $request->validated(),
            $request->user(),
            $this->canViewCost($request->user())
        );

        return redirect()
            ->route('admin.purchase-orders.show', $updated)
            ->with('success', "Purchase Order {$updated->po_number} updated.");
    }

    public function approve(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $user = $request->user();
        if (!$user || !$user->isOwner() || !$user->hasPermissionTo('purchase_orders.approve')) {
            abort(403, 'Only the Business Owner may approve purchase orders.');
        }

        $approved = $this->poService->approve($purchaseOrder, $user);

        return back()->with('success', "Purchase Order {$approved->po_number} approved and marked Ordered.");
    }

    public function receive(ReceivePurchaseOrderRequest $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $validated = $request->validated();

        $receipt = $this->poService->receive(
            $purchaseOrder,
            $validated['items'],
            $validated['notes'] ?? null,
            $request->user()
        );

        return back()->with('success', "Receipt {$receipt->receipt_number} recorded and inventory updated.");
    }

    public function cancel(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorizePermission($request->user(), 'purchase_orders.cancel');

        $validated = $request->validate([
            'cancellation_reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        $cancelled = $this->poService->cancel(
            $purchaseOrder,
            $validated['cancellation_reason'],
            $request->user()
        );

        return back()->with('success', "Purchase Order {$cancelled->po_number} cancelled.");
    }

    private function getActiveSuppliersWithProducts(bool $canViewCost): array
    {
        return Supplier::where('status', Supplier::STATUS_ACTIVE)
            ->orderBy('name')
            ->with(['productSuppliers' => function ($q) {
                $q->whereHas('product', fn ($pq) => $pq->where('is_active', true))
                    ->with('product:id,name_en,sku');
            }])
            ->get()
            ->map(function (Supplier $supplier) use ($canViewCost) {
                return [
                    'id' => $supplier->id,
                    'code' => $supplier->code,
                    'name' => $supplier->name,
                    'default_lead_time_days' => $supplier->default_lead_time_days,
                    'products' => $supplier->productSuppliers->map(fn ($link) => [
                        'product_id' => $link->product_id,
                        'name' => $link->product?->name_en,
                        'sku' => $link->product?->sku,
                        'supplier_sku' => $link->supplier_sku,
                        'unit_cost' => $canViewCost ? (string) $link->unit_cost : null,
                        'min_order_qty' => $link->min_order_qty,
                        'is_preferred' => (bool) $link->is_preferred,
                    ])->values()->all(),
                ];
            })
            ->all();
    }
}

