<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Category;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Permission;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseOrderReceipt;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use App\Services\PurchaseOrderService;
use App\Services\StockService;
use App\Services\SupplierService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PurchaseOrderManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $superAdmin;
    private User $accountant;
    private User $invManager;
    private Supplier $supplier;
    private Product $product;
    private PurchaseOrderService $poService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->owner = $this->makeUser(UserRole::OWNER, 'owner');
        $this->superAdmin = $this->makeUser(UserRole::SUPER_ADMIN, 'superadmin');
        $this->accountant = $this->makeUser(UserRole::ACCOUNTANT, 'accountant');
        $this->invManager = $this->makeUser(UserRole::PRODUCT_INVENTORY_MANAGER, 'invmgr');

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->owner->refresh();
        $this->superAdmin->refresh();
        $this->accountant->refresh();
        $this->invManager->refresh();

        $category = Category::create([
            'name_en' => 'Serums',
            'name_si' => 'සීරම්',
            'slug' => 'serums-' . uniqid(),
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'category_id' => $category->id,
            'name_en' => 'Radiant Vitamin C Serum',
            'name_si' => 'විටමින් C සීරම්',
            'slug' => 'vit-c-serum-' . uniqid(),
            'sku' => 'VCS-001',
            'price' => 5000.00,
            'cost_price' => 1000.00,
            'stock_quantity' => 10,
            'low_stock_threshold' => 5,
            'is_active' => true,
        ]);

        $supplierService = app(SupplierService::class);
        $this->supplier = $supplierService->createSupplier(['name' => 'Ceylon Actives Ltd'], $this->owner);
        $supplierService->linkProduct($this->supplier, $this->product, [
            'supplier_sku' => 'CA-VCS-01',
            'unit_cost' => 1300.00,
            'min_order_qty' => 5,
            'is_preferred' => true,
        ], $this->owner);

        $this->poService = app(PurchaseOrderService::class);
    }

    private function makeUser(UserRole $role, string $prefix): User
    {
        return User::create([
            'name' => ucfirst($prefix),
            'email' => "{$prefix}-" . uniqid() . '@auraluxe.test',
            'password' => bcrypt('Secret123!'),
            'role' => $role->value,
            'status' => UserStatus::ACTIVE->value,
            'two_factor_confirmed_at' => now(),
        ]);
    }

    public function test_po_creation_unique_numbering_and_validation_rules(): void
    {
        // Create draft PO via HTTP
        $response = $this->actingAs($this->invManager)->post('/admin/purchase-orders', [
            'supplier_id' => $this->supplier->id,
            'tax_amount' => '100.00',
            'shipping_cost' => '250.00',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'ordered_qty' => 10,
                    'unit_cost' => '1300.00',
                ],
            ],
        ]);

        $po = PurchaseOrder::firstOrFail();
        $response->assertRedirect(route('admin.purchase-orders.show', $po));
        $this->assertMatchesRegularExpression('/^PO-\d{4}-\d{4}$/', $po->po_number);
        $this->assertSame('13000.00', (string) $po->subtotal);
        $this->assertSame('13350.00', (string) $po->total_cost);
        $this->assertSame('CA-VCS-01', $po->items()->first()->supplier_sku);

        // Second PO gets sequential unique PO number
        $po2 = $this->poService->createDraft([
            'supplier_id' => $this->supplier->id,
            'items' => [['product_id' => $this->product->id, 'ordered_qty' => 5]],
        ], $this->invManager);
        $this->assertNotSame($po->po_number, $po2->po_number);

        // Inactive supplier rejected
        $inactiveSupplier = app(SupplierService::class)->createSupplier([
            'name' => 'Inactive Co',
            'status' => Supplier::STATUS_INACTIVE,
        ], $this->owner);

        $this->actingAs($this->invManager)->post('/admin/purchase-orders', [
            'supplier_id' => $inactiveSupplier->id,
            'items' => [['product_id' => $this->product->id, 'ordered_qty' => 5]],
        ])->assertSessionHasErrors('supplier_id');

        // Unlinked product rejected (no auto-linking)
        $unlinkedProduct = Product::create([
            'category_id' => $this->product->category_id,
            'name_en' => 'Unlinked Cream',
            'name_si' => 'ක්‍රීම්',
            'slug' => 'unlinked-' . uniqid(),
            'sku' => 'UNL-001',
            'price' => 2000.00,
            'stock_quantity' => 5,
            'is_active' => true,
        ]);

        $this->actingAs($this->invManager)->post('/admin/purchase-orders', [
            'supplier_id' => $this->supplier->id,
            'items' => [['product_id' => $unlinkedProduct->id, 'ordered_qty' => 5]],
        ])->assertSessionHasErrors('items.0.product_id');

        // Invalid quantities and negative unit costs rejected
        $this->actingAs($this->invManager)->post('/admin/purchase-orders', [
            'supplier_id' => $this->supplier->id,
            'items' => [['product_id' => $this->product->id, 'ordered_qty' => 0, 'unit_cost' => -5]],
        ])->assertSessionHasErrors(['items.0.ordered_qty', 'items.0.unit_cost']);
    }

    public function test_owner_only_approval_and_lifecycle_transitions(): void
    {
        $po = $this->poService->createDraft([
            'supplier_id' => $this->supplier->id,
            'items' => [['product_id' => $this->product->id, 'ordered_qty' => 10, 'unit_cost' => '1300.00']],
        ], $this->invManager);

        // Super Admin, Accountant, and Product & Inventory Manager CANNOT approve
        $this->actingAs($this->superAdmin)->post("/admin/purchase-orders/{$po->id}/approve")->assertForbidden();
        $this->actingAs($this->accountant)->post("/admin/purchase-orders/{$po->id}/approve")->assertForbidden();
        $this->actingAs($this->invManager)->post("/admin/purchase-orders/{$po->id}/approve")->assertForbidden();

        // Draft can be edited
        $this->actingAs($this->invManager)->put("/admin/purchase-orders/{$po->id}", [
            'supplier_id' => $this->supplier->id,
            'items' => [['product_id' => $this->product->id, 'ordered_qty' => 12, 'unit_cost' => '1250.00']],
        ])->assertRedirect();
        $this->assertSame('15000.00', (string) $po->fresh()->total_cost);

        // Owner approves PO -> ordered
        $this->actingAs($this->owner)->post("/admin/purchase-orders/{$po->id}/approve")->assertRedirect();
        $this->assertSame(PurchaseOrder::STATUS_ORDERED, $po->fresh()->status);

        // Duplicate approval rejected
        $this->actingAs($this->owner)
            ->post("/admin/purchase-orders/{$po->id}/approve")
            ->assertSessionHasErrors('status');

        // Editing ordered PO rejected
        $this->actingAs($this->invManager)->put("/admin/purchase-orders/{$po->id}", [
            'supplier_id' => $this->supplier->id,
            'items' => [['product_id' => $this->product->id, 'ordered_qty' => 5]],
        ])->assertSessionHasErrors('status');
    }

    public function test_partial_and_full_receiving_wac_calculation_and_historical_cogs_preservation(): void
    {
        // Create historical OrderItem snapshot at cost_price = 1000.00
        $historicalOrder = Order::create([
            'order_number' => 'ORD-HIST-001',
            'customer_name' => 'Test Customer',
            'customer_phone' => '0770000001',
            'shipping_address' => '123 Street',
            'district' => 'Colombo',
            'city' => 'Colombo',
            'subtotal' => 5000.00,
            'delivery_charge' => 350.00,
            'total_amount' => 5350.00,
            'payment_method' => 'cod',
            'status' => 'completed',
            'tracking_token' => 'tok-' . uniqid(),
            'placed_at' => now(),
        ]);

        $historicalOrderItem = OrderItem::create([
            'order_id' => $historicalOrder->id,
            'product_id' => $this->product->id,
            'product_name' => $this->product->name_en,
            'product_sku' => $this->product->sku,
            'unit_price' => 5000.00,
            'cost_price' => 1000.00,
            'quantity' => 1,
            'subtotal' => 5000.00,
        ]);

        // Product starts with stock=10, cost_price=1000.00.
        // Create & approve PO for 10 units @ 1400.00
        $po = $this->poService->createDraft([
            'supplier_id' => $this->supplier->id,
            'items' => [['product_id' => $this->product->id, 'ordered_qty' => 10, 'unit_cost' => '1400.00']],
        ], $this->invManager);
        $this->poService->approve($po, $this->owner);

        $poItem = $po->items()->firstOrFail();

        // Receipt 1 (Partial: 5 units):
        // WAC = ((10 * 1000.00) + (5 * 1400.00)) / (10 + 5) = 17000 / 15 = 1133.33
        $this->actingAs($this->invManager)->post("/admin/purchase-orders/{$po->id}/receive", [
            'notes' => 'Batch 1',
            'items' => [['purchase_order_item_id' => $poItem->id, 'quantity_received' => 5]],
        ])->assertRedirect();

        $po->refresh();
        $this->product->refresh();
        $this->assertSame(PurchaseOrder::STATUS_PARTIALLY_RECEIVED, $po->status);
        $this->assertSame(15, $this->product->stock_quantity);
        $this->assertSame('1133.33', (string) $this->product->cost_price);

        // Cancellation MUST be blocked after partial receipt
        $this->actingAs($this->invManager)->post("/admin/purchase-orders/{$po->id}/cancel", [
            'cancellation_reason' => 'Too late',
        ])->assertSessionHasErrors('status');

        // Over-receiving (6 units when 5 remaining) MUST fail
        $this->actingAs($this->invManager)->post("/admin/purchase-orders/{$po->id}/receive", [
            'items' => [['purchase_order_item_id' => $poItem->id, 'quantity_received' => 6]],
        ])->assertSessionHasErrors('items');

        // Receipt 2 (Remaining 5 units):
        // WAC = ((15 * 1133.33) + (5 * 1400.00)) / 20 = (16999.95 + 7000) / 20 = 1199.9975 -> 1200.00
        $this->actingAs($this->invManager)->post("/admin/purchase-orders/{$po->id}/receive", [
            'notes' => 'Batch 2',
            'items' => [['purchase_order_item_id' => $poItem->id, 'quantity_received' => 5]],
        ])->assertRedirect();

        $po->refresh();
        $this->product->refresh();
        $this->assertSame(PurchaseOrder::STATUS_RECEIVED, $po->status);
        $this->assertSame(20, $this->product->stock_quantity);
        $this->assertSame('1200.00', (string) $this->product->cost_price);

        // Duplicate receipt attempt after full receive MUST fail
        $this->actingAs($this->invManager)->post("/admin/purchase-orders/{$po->id}/receive", [
            'items' => [['purchase_order_item_id' => $poItem->id, 'quantity_received' => 1]],
        ])->assertSessionHasErrors('status');

        // Historical order_items.cost_price MUST remain 1000.00
        $this->assertSame('1000.00', (string) $historicalOrderItem->fresh()->cost_price);

        // Two InventoryMovements linked to PO & receipts
        $this->assertSame(2, InventoryMovement::where('purchase_order_id', $po->id)->count());

        // AuditLog events recorded
        $this->assertDatabaseHas('audit_logs', ['action' => 'purchase_order_created', 'auditable_id' => $po->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'purchase_order_approved', 'auditable_id' => $po->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'purchase_order_partially_received', 'auditable_id' => $po->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'purchase_order_received', 'auditable_id' => $po->id]);
    }

    public function test_cancellation_before_receiving_and_obs03_stock_service_validation(): void
    {
        $po = $this->poService->createDraft([
            'supplier_id' => $this->supplier->id,
            'items' => [['product_id' => $this->product->id, 'ordered_qty' => 5]],
        ], $this->invManager);
        $this->poService->approve($po, $this->owner);

        // Cancellation allowed when 0 received
        $this->actingAs($this->invManager)->post("/admin/purchase-orders/{$po->id}/cancel", [
            'cancellation_reason' => 'Supplier out of stock',
        ])->assertRedirect();
        $this->assertSame(PurchaseOrder::STATUS_CANCELLED, $po->fresh()->status);

        // OBS-03: StockService rejects PO without receipt
        $stockService = app(StockService::class);
        $po2 = $this->poService->createDraft([
            'supplier_id' => $this->supplier->id,
            'items' => [['product_id' => $this->product->id, 'ordered_qty' => 5]],
        ], $this->invManager);
        $this->poService->approve($po2, $this->owner);

        $threwMissingReceipt = false;
        try {
            $stockService->adjustStock($this->product, 5, InventoryMovement::TYPE_RESTOCK, 'Test', null, $this->owner, $po2, null);
        } catch (ValidationException) {
            $threwMissingReceipt = true;
        }
        $this->assertTrue($threwMissingReceipt);

        // OBS-03: StockService rejects receipt belonging to a different PO
        $po3 = $this->poService->createDraft([
            'supplier_id' => $this->supplier->id,
            'items' => [['product_id' => $this->product->id, 'ordered_qty' => 5]],
        ], $this->invManager);
        $this->poService->approve($po3, $this->owner);

        $foreignReceipt = PurchaseOrderReceipt::create([
            'receipt_number' => 'POR-FOREIGN-1',
            'purchase_order_id' => $po3->id,
            'received_by' => $this->invManager->id,
            'received_at' => now(),
        ]);

        $threwMismatch = false;
        try {
            $stockService->adjustStock($this->product, 5, InventoryMovement::TYPE_RESTOCK, 'Test', null, $this->owner, $po2, $foreignReceipt);
        } catch (ValidationException) {
            $threwMismatch = true;
        }
        $this->assertTrue($threwMismatch);
    }

    public function test_cost_confidentiality_for_po_viewer_without_cost_permission(): void
    {
        $po = $this->poService->createDraft([
            'supplier_id' => $this->supplier->id,
            'items' => [['product_id' => $this->product->id, 'ordered_qty' => 5, 'unit_cost' => '1300.00']],
        ], $this->invManager);

        $customRole = Role::create([
            'name' => 'po_viewer',
            'display_name' => 'PO Viewer',
            'is_system' => false,
            'is_protected' => false,
        ]);
        $viewPerm = Permission::where('name', 'purchase_orders.view')->firstOrFail();
        $customRole->permissions()->sync([$viewPerm->id]);

        $viewer = $this->makeUser(UserRole::OPERATIONS_MANAGER, 'poviewer');
        $viewer->update(['role_id' => $customRole->id]);
        $viewer->assignedRoles()->sync([$customRole->id]);

        $this->actingAs($viewer)
            ->get("/admin/purchase-orders/{$po->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/PurchaseOrders/Show')
                ->where('permissions.canViewCost', false)
                ->where('purchaseOrder.total_cost', null)
                ->where('purchaseOrder.items.0.unit_cost', null)
            );
    }

    public function test_fnd04_po_creator_without_cost_permission_cannot_view_or_overwrite_unit_cost(): void
    {
        $po = $this->poService->createDraft([
            'supplier_id' => $this->supplier->id,
            'tax_amount' => '150.00',
            'shipping_cost' => '300.00',
            'items' => [['product_id' => $this->product->id, 'ordered_qty' => 5, 'unit_cost' => '1450.00']],
        ], $this->owner, true);

        // Custom role with purchase_orders.create + purchase_orders.view ONLY (NO products.cost_price.view)
        $customRole = Role::create([
            'name' => 'po_creator_no_cost',
            'display_name' => 'PO Creator (No Cost)',
            'is_system' => false,
            'is_protected' => false,
        ]);
        $permIds = Permission::whereIn('name', ['purchase_orders.view', 'purchase_orders.create'])->pluck('id')->all();
        $customRole->permissions()->sync($permIds);

        $creator = $this->makeUser(UserRole::OPERATIONS_MANAGER, 'nocostcreator');
        $creator->update(['role_id' => $customRole->id]);
        $creator->assignedRoles()->sync([$customRole->id]);

        // 1. Edit page must NOT expose unit_cost, tax_amount, shipping_cost, or substitute 0.00
        $this->actingAs($creator)
            ->get("/admin/purchase-orders/{$po->id}/edit")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/PurchaseOrders/Edit')
                ->where('canViewCost', false)
                ->where('purchaseOrder.tax_amount', null)
                ->where('purchaseOrder.shipping_cost', null)
                ->where('purchaseOrder.items.0.unit_cost', null)
                ->where('suppliers.0.products.0.unit_cost', null)
            );

        // 2. Submitting an update (even with forged unit_cost/tax/shipping) preserves existing DB costs
        $this->actingAs($creator)
            ->put("/admin/purchase-orders/{$po->id}", [
                'supplier_id' => $this->supplier->id,
                'tax_amount' => '0.00',
                'shipping_cost' => '0.00',
                'notes' => 'Updated quantity only',
                'items' => [
                    [
                        'product_id' => $this->product->id,
                        'ordered_qty' => 8,
                        'unit_cost' => '0.00',
                    ],
                ],
            ])
            ->assertRedirect();

        $po->refresh();
        $this->assertSame('1450.00', (string) $po->items()->firstOrFail()->unit_cost);
        $this->assertSame('150.00', (string) $po->tax_amount);
        $this->assertSame('300.00', (string) $po->shipping_cost);
        // 8 * 1450.00 + 150.00 + 300.00 = 12050.00
        $this->assertSame('12050.00', (string) $po->total_cost);

        // 3. Authorized user (Product & Inventory Manager with products.cost_price.view) CAN update unit_cost normally
        $this->actingAs($this->invManager)
            ->put("/admin/purchase-orders/{$po->id}", [
                'supplier_id' => $this->supplier->id,
                'tax_amount' => '100.00',
                'shipping_cost' => '200.00',
                'items' => [
                    [
                        'product_id' => $this->product->id,
                        'ordered_qty' => 8,
                        'unit_cost' => '1500.00',
                    ],
                ],
            ])
            ->assertRedirect();

        $po->refresh();
        $this->assertSame('1500.00', (string) $po->items()->firstOrFail()->unit_cost);
        $this->assertSame('12300.00', (string) $po->total_cost);
    }

    public function test_fnd05_po_creation_retries_and_succeeds_on_po_number_collision(): void
    {
        $year = now()->format('Y');
        $collisionPoNumber = "PO-{$year}-0001";

        // Pre-create PO-YYYY-0001 in database
        $initialPo = $this->poService->createDraft([
            'supplier_id' => $this->supplier->id,
            'items' => [
                ['product_id' => $this->product->id, 'ordered_qty' => 5],
            ],
        ], $this->owner);

        $this->assertSame($collisionPoNumber, $initialPo->po_number);
        $this->assertSame(1, PurchaseOrder::count());

        // Subclass PurchaseOrderService so that the first call to generatePoNumber() returns PO-YYYY-0001
        // which collides with the existing record, and subsequent calls delegate to parent::generatePoNumber()
        $service = new class(app(StockService::class)) extends PurchaseOrderService {
            public int $generateCalls = 0;

            public function generatePoNumber(): string
            {
                $this->generateCalls++;
                if ($this->generateCalls === 1) {
                    $year = now()->format('Y');
                    return "PO-{$year}-0001";
                }

                return parent::generatePoNumber();
            }
        };

        $secondPo = $service->createDraft([
            'supplier_id' => $this->supplier->id,
            'items' => [
                ['product_id' => $this->product->id, 'ordered_qty' => 10],
            ],
        ], $this->owner);

        // Verify that generatePoNumber was called twice (1 collision + 1 retry)
        $this->assertSame(2, $service->generateCalls);
        $this->assertSame("PO-{$year}-0002", $secondPo->po_number);
        $this->assertSame(2, PurchaseOrder::count());
        $this->assertDatabaseHas('purchase_orders', ['id' => $secondPo->id, 'po_number' => "PO-{$year}-0002"]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'purchase_order_created', 'auditable_id' => $secondPo->id]);
    }

    public function test_fnd05_po_creation_exhausts_retries_and_throws_query_exception(): void
    {
        $year = now()->format('Y');
        $collisionPoNumber = "PO-{$year}-0001";

        // Pre-create PO-YYYY-0001
        $initialPo = $this->poService->createDraft([
            'supplier_id' => $this->supplier->id,
            'items' => [
                ['product_id' => $this->product->id, 'ordered_qty' => 5],
            ],
        ], $this->owner);

        $this->assertSame($collisionPoNumber, $initialPo->po_number);
        $initialItemCount = PurchaseOrderItem::count();
        $this->assertSame(1, PurchaseOrder::count());

        // Subclass PurchaseOrderService so that generatePoNumber() ALWAYS returns PO-YYYY-0001
        $service = new class(app(StockService::class)) extends PurchaseOrderService {
            public int $generateCalls = 0;

            public function generatePoNumber(): string
            {
                $this->generateCalls++;
                $year = now()->format('Y');
                return "PO-{$year}-0001";
            }
        };

        $caughtException = null;
        try {
            $service->createDraft([
                'supplier_id' => $this->supplier->id,
                'items' => [
                    ['product_id' => $this->product->id, 'ordered_qty' => 10],
                ],
            ], $this->owner);
        } catch (QueryException $e) {
            $caughtException = $e;
        }

        $this->assertNotNull($caughtException, 'Expected QueryException was not thrown.');
        $this->assertTrue($service->isPoNumberDuplicateException($caughtException));
        // Verify exactly 3 attempts were made before giving up
        $this->assertSame(3, $service->generateCalls);

        // Verify total rollback: exactly 1 PO remains, no orphan items created
        $this->assertSame(1, PurchaseOrder::count());
        $this->assertSame($initialItemCount, PurchaseOrderItem::count());
    }

    public function test_fnd05_unrelated_database_exceptions_are_not_retried(): void
    {
        $service = new class(app(StockService::class)) extends PurchaseOrderService {
            public int $generateCalls = 0;

            public function generatePoNumber(): string
            {
                $this->generateCalls++;
                return parent::generatePoNumber();
            }
        };

        // Create an unpersisted User with non-existent foreign key ID
        $ghostUser = new User();
        $ghostUser->id = 99999999;

        $caughtException = null;
        try {
            $service->createDraft([
                'supplier_id' => $this->supplier->id,
                'items' => [
                    ['product_id' => $this->product->id, 'ordered_qty' => 5],
                ],
            ], $ghostUser);
        } catch (QueryException $e) {
            $caughtException = $e;
        }

        $this->assertNotNull($caughtException, 'Expected foreign key QueryException was not thrown.');
        $this->assertFalse($service->isPoNumberDuplicateException($caughtException));
        // Must fail immediately on attempt 1 with NO retries
        $this->assertSame(1, $service->generateCalls);
    }
}

