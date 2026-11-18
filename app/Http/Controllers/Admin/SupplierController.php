<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSupplierRequest;
use App\Http\Requests\Admin\UpdateSupplierRequest;
use App\Http\Requests\Admin\UpsertSupplierProductRequest;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Services\SupplierService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupplierController extends Controller
{
    public function __construct(
        private readonly SupplierService $supplierService
    ) {}

    private function authorizePermission(?User $user, string $permission): void
    {
        if (!$user || (!$user->isOwner() && !$user->hasPermissionTo($permission))) {
            abort(403, 'Unauthorized access to supplier management.');
        }
    }

    private function canViewCost(?User $user): bool
    {
        return (bool) ($user && ($user->isOwner() || $user->hasPermissionTo('products.cost_price.view')));
    }

    public function index(Request $request): Response
    {
        $user = $request->user();
        $this->authorizePermission($user, 'suppliers.view');

        $search = trim((string) $request->input('search', ''));
        $status = (string) $request->input('status', 'all');

        $query = Supplier::query()
            ->withCount(['products', 'purchaseOrders']);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('contact_person', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if (in_array($status, [Supplier::STATUS_ACTIVE, Supplier::STATUS_INACTIVE], true)) {
            $query->where('status', $status);
        }

        $suppliers = $query->orderBy('name')->paginate(15)->withQueryString();

        return Inertia::render('Admin/Suppliers/Index', [
            'suppliers' => $suppliers,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
            'stats' => [
                'total' => Supplier::count(),
                'active' => Supplier::where('status', Supplier::STATUS_ACTIVE)->count(),
                'inactive' => Supplier::where('status', Supplier::STATUS_INACTIVE)->count(),
            ],
            'canManage' => $user->isOwner() || $user->hasPermissionTo('suppliers.manage'),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorizePermission($request->user(), 'suppliers.manage');

        return Inertia::render('Admin/Suppliers/Create');
    }

    public function store(StoreSupplierRequest $request): RedirectResponse
    {
        $supplier = $this->supplierService->createSupplier(
            $request->validated(),
            $request->user()
        );

        return redirect()
            ->route('admin.suppliers.show', $supplier)
            ->with('success', "Supplier '{$supplier->name}' ({$supplier->code}) registered successfully.");
    }

    public function show(Request $request, Supplier $supplier): Response
    {
        $user = $request->user();
        $this->authorizePermission($user, 'suppliers.view');

        $canManage = $user->isOwner() || $user->hasPermissionTo('suppliers.manage');
        $canViewCost = $this->canViewCost($user);

        $supplier->load([
            'creator:id,name',
            'productSuppliers.product:id,name_en,sku,is_active,stock_quantity',
        ]);

        $linkedProducts = $supplier->productSuppliers->map(function ($link) use ($canViewCost) {
            return [
                'id' => $link->id,
                'product_id' => $link->product_id,
                'product_name' => $link->product?->name_en,
                'product_sku' => $link->product?->sku,
                'product_is_active' => (bool) ($link->product?->is_active ?? false),
                'product_stock' => (int) ($link->product?->stock_quantity ?? 0),
                'supplier_sku' => $link->supplier_sku,
                'unit_cost' => $canViewCost ? (string) $link->unit_cost : null,
                'lead_time_days' => $link->lead_time_days,
                'min_order_qty' => $link->min_order_qty,
                'is_preferred' => (bool) $link->is_preferred,
                'last_purchased_at' => $link->last_purchased_at?->toISOString(),
            ];
        })->values();

        $linkedProductIds = $supplier->productSuppliers->pluck('product_id')->all();

        $availableProducts = $canManage
            ? Product::where('is_active', true)
                ->whereNotIn('id', $linkedProductIds)
                ->orderBy('name_en')
                ->get(['id', 'name_en', 'sku'])
            : collect();

        $purchaseOrders = $supplier->purchaseOrders()
            ->latest('id')
            ->take(10)
            ->get()
            ->map(function ($po) use ($canViewCost) {
                return [
                    'id' => $po->id,
                    'po_number' => $po->po_number,
                    'status' => $po->status,
                    'ordered_at' => $po->ordered_at?->toISOString(),
                    'expected_delivery_date' => $po->expected_delivery_date?->toDateString(),
                    'total_cost' => $canViewCost ? (string) $po->total_cost : null,
                ];
            });

        return Inertia::render('Admin/Suppliers/Show', [
            'supplier' => $supplier->only([
                'id', 'code', 'name', 'contact_person', 'email', 'phone',
                'address', 'city', 'country', 'tax_id', 'payment_terms',
                'default_lead_time_days', 'status', 'notes', 'created_at', 'updated_at',
            ]) + ['creator_name' => $supplier->creator?->name],
            'linkedProducts' => $linkedProducts,
            'availableProducts' => $availableProducts,
            'purchaseOrders' => $purchaseOrders,
            'canManage' => $canManage,
            'canViewCost' => $canViewCost,
        ]);
    }

    public function edit(Request $request, Supplier $supplier): Response
    {
        $this->authorizePermission($request->user(), 'suppliers.manage');

        return Inertia::render('Admin/Suppliers/Edit', [
            'supplier' => $supplier->only([
                'id', 'code', 'name', 'contact_person', 'email', 'phone',
                'address', 'city', 'country', 'tax_id', 'payment_terms',
                'default_lead_time_days', 'status', 'notes',
            ]),
        ]);
    }

    public function update(UpdateSupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $updated = $this->supplierService->updateSupplier(
            $supplier,
            $request->validated(),
            $request->user()
        );

        return redirect()
            ->route('admin.suppliers.show', $updated)
            ->with('success', "Supplier '{$updated->name}' updated successfully.");
    }

    public function toggleStatus(Request $request, Supplier $supplier): RedirectResponse
    {
        $this->authorizePermission($request->user(), 'suppliers.manage');

        $updated = $this->supplierService->toggleStatus($supplier, $request->user());

        return back()->with('success', "Supplier '{$updated->name}' is now {$updated->status}.");
    }

    public function linkProduct(UpsertSupplierProductRequest $request, Supplier $supplier): RedirectResponse
    {
        $validated = $request->validated();
        $product = Product::findOrFail($validated['product_id']);

        $this->supplierService->linkProduct($supplier, $product, $validated, $request->user());

        return back()->with('success', "Linked '{$product->name_en}' to {$supplier->name}.");
    }

    public function updateProduct(UpsertSupplierProductRequest $request, Supplier $supplier, Product $product): RedirectResponse
    {
        $this->supplierService->updateProductLink($supplier, $product, $request->validated(), $request->user());

        return back()->with('success', "Updated supplier terms for '{$product->name_en}'.");
    }

    public function setPreferredProduct(Request $request, Supplier $supplier, Product $product): RedirectResponse
    {
        $this->authorizePermission($request->user(), 'suppliers.manage');

        $this->supplierService->setPreferredSupplier($supplier, $product, $request->user());

        return back()->with('success', "{$supplier->name} is now the preferred supplier for '{$product->name_en}'.");
    }

    public function unlinkProduct(Request $request, Supplier $supplier, Product $product): RedirectResponse
    {
        $this->authorizePermission($request->user(), 'suppliers.manage');

        $this->supplierService->unlinkProduct($supplier, $product, $request->user());

        return back()->with('success', "Removed '{$product->name_en}' from {$supplier->name}.");
    }
}

