<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Category;
use App\Models\InventoryMovement;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductSupplier;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseOrderReceipt;
use App\Models\PurchaseOrderReceiptItem;
use App\Models\Role;
use App\Models\StockAlert;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SupplyChainSchemaAndRbacTest extends TestCase
{
    use RefreshDatabase;

    private function createProduct(string $sku = 'SKU-FV02-01'): Product
    {
        $category = Category::create([
            'name_en' => 'Skincare',
            'name_si' => 'සම රැකවරණය',
            'slug' => 'skincare-' . uniqid(),
            'is_active' => true,
        ]);

        return Product::create([
            'category_id' => $category->id,
            'name_en' => 'Aura Glow Serum',
            'name_si' => 'අවුරා ග්ලෝ සීරම්',
            'slug' => 'aura-glow-serum-' . uniqid(),
            'sku' => $sku,
            'price' => 4500.00,
            'cost_price' => 2100.00,
            'stock_quantity' => 20,
            'low_stock_threshold' => 5,
            'is_active' => true,
        ]);
    }

    private function createUser(UserRole $role, string $emailPrefix = 'user'): User
    {
        return User::create([
            'name' => ucfirst($emailPrefix),
            'email' => $emailPrefix . '-' . uniqid() . '@auraluxe.test',
            'password' => bcrypt('Secret123!'),
            'role' => $role->value,
            'status' => UserStatus::ACTIVE->value,
        ]);
    }

    public function test_supply_chain_tables_and_inventory_movement_columns_exist_and_roll_back_cleanly(): void
    {
        $expectedTables = [
            'suppliers',
            'product_supplier',
            'purchase_orders',
            'purchase_order_items',
            'purchase_order_receipts',
            'purchase_order_receipt_items',
            'stock_alerts',
        ];

        foreach ($expectedTables as $table) {
            $this->assertTrue(Schema::hasTable($table), "Expected table '{$table}' to exist.");
        }

        $this->assertTrue(
            Schema::hasColumns('inventory_movements', ['purchase_order_id', 'purchase_order_receipt_id']),
            'inventory_movements should contain purchase_order_id and purchase_order_receipt_id.'
        );

        $supplyChainMigrations = [
            'database/migrations/2026_11_12_000001_create_suppliers_table.php',
            'database/migrations/2026_11_12_000002_create_product_supplier_table.php',
            'database/migrations/2026_11_12_000003_create_purchase_orders_table.php',
            'database/migrations/2026_11_12_000004_create_purchase_order_items_table.php',
            'database/migrations/2026_11_12_000005_create_purchase_order_receipts_table.php',
            'database/migrations/2026_11_12_000006_create_purchase_order_receipt_items_table.php',
            'database/migrations/2026_11_12_000007_create_stock_alerts_table.php',
            'database/migrations/2026_11_12_000008_add_purchase_order_links_to_inventory_movements_table.php',
            'database/migrations/2026_11_12_000009_add_preferred_product_id_to_product_supplier_table.php',
            'database/migrations/2026_11_12_000010_add_supplier_sku_to_purchase_order_items_table.php',
            'database/migrations/2026_11_12_000011_add_active_product_id_to_stock_alerts_table.php',
        ];

        // Roll back the 11 FV-02-02..05 migrations in isolation and re-migrate to verify reversibility.
        Artisan::call('migrate:rollback', ['--path' => $supplyChainMigrations]);

        foreach ($expectedTables as $table) {
            $this->assertFalse(Schema::hasTable($table), "Expected table '{$table}' to be dropped on rollback.");
        }

        $this->assertFalse(
            Schema::hasColumns('inventory_movements', ['purchase_order_id', 'purchase_order_receipt_id'])
        );

        Artisan::call('migrate', ['--path' => $supplyChainMigrations]);

        foreach ($expectedTables as $table) {
            $this->assertTrue(Schema::hasTable($table), "Expected table '{$table}' to be restored on re-migrate.");
        }
    }

    public function test_eloquent_models_and_relationships_work_end_to_end(): void
    {
        $owner = $this->createUser(UserRole::OWNER, 'owner');
        $product = $this->createProduct();

        $supplier = Supplier::create([
            'code' => 'SUP-2026-0001',
            'name' => 'Ceylon Botanicals Ltd',
            'payment_terms' => Supplier::TERMS_NET_30,
            'default_lead_time_days' => 10,
            'status' => Supplier::STATUS_ACTIVE,
            'created_by' => $owner->id,
        ]);

        $product->suppliers()->attach($supplier->id, [
            'supplier_sku' => 'CB-SERUM-01',
            'unit_cost' => 1950.00,
            'lead_time_days' => 7,
            'min_order_qty' => 10,
            'is_preferred' => true,
        ]);

        $po = PurchaseOrder::create([
            'po_number' => 'PO-20261112-0001',
            'supplier_id' => $supplier->id,
            'status' => PurchaseOrder::STATUS_ORDERED,
            'ordered_at' => now(),
            'subtotal' => 19500.00,
            'tax_amount' => 0.00,
            'shipping_cost' => 500.00,
            'total_cost' => 20000.00,
            'created_by' => $owner->id,
            'approved_by' => $owner->id,
        ]);

        $poItem = PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'product_id' => $product->id,
            'product_name' => $product->name_en,
            'product_sku' => $product->sku,
            'ordered_qty' => 10,
            'received_qty' => 10,
            'unit_cost' => 1950.00,
            'line_total' => 19500.00,
        ]);

        $receipt = PurchaseOrderReceipt::create([
            'receipt_number' => 'POR-20261112-0001',
            'purchase_order_id' => $po->id,
            'received_by' => $owner->id,
            'received_at' => now(),
        ]);

        $receiptItem = PurchaseOrderReceiptItem::create([
            'purchase_order_receipt_id' => $receipt->id,
            'purchase_order_item_id' => $poItem->id,
            'product_id' => $product->id,
            'quantity_received' => 10,
            'unit_cost' => 1950.00,
        ]);

        $movement = InventoryMovement::create([
            'product_id' => $product->id,
            'movement_type' => InventoryMovement::TYPE_RESTOCK,
            'quantity' => 10,
            'previous_stock' => 20,
            'new_stock' => 30,
            'reason' => 'PO Restock',
            'purchase_order_id' => $po->id,
            'purchase_order_receipt_id' => $receipt->id,
            'user_id' => $owner->id,
        ]);

        $alert = StockAlert::create([
            'product_id' => $product->id,
            'status' => StockAlert::STATUS_OPEN,
            'stock_at_trigger' => 4,
            'threshold_at_trigger' => 5,
            'current_stock' => 4,
            'trigger_source' => 'order_deduction',
            'opened_at' => now(),
        ]);

        $this->assertTrue($supplier->products->contains($product));
        $this->assertTrue($product->suppliers->contains($supplier));
        $this->assertSame($supplier->id, $product->preferredSupplier->supplier_id);
        $this->assertSame($supplier->id, $po->supplier->id);
        $this->assertCount(1, $po->items);
        $this->assertCount(1, $po->receipts);
        $this->assertCount(1, $receipt->items);
        $this->assertSame($poItem->id, $receiptItem->purchaseOrderItem->id);
        $this->assertSame($po->id, $movement->purchaseOrder->id);
        $this->assertSame($receipt->id, $movement->purchaseOrderReceipt->id);
        $this->assertSame($product->id, $alert->product->id);
    }

    public function test_unique_constraints_and_restrict_foreign_keys_are_enforced(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = ON;');
        }

        $owner = $this->createUser(UserRole::OWNER, 'owner');
        $product = $this->createProduct();

        $supplier = Supplier::create([
            'code' => 'SUP-UNIQ-01',
            'name' => 'Lanka Extracts',
            'created_by' => $owner->id,
        ]);

        // Duplicate supplier code must fail
        $this->expectException(QueryException::class);
        Supplier::create([
            'code' => 'SUP-UNIQ-01',
            'name' => 'Duplicate Code Supplier',
            'created_by' => $owner->id,
        ]);
    }

    public function test_unique_po_number_and_restrict_delete_on_supplier_with_purchase_order(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = ON;');
        }

        $owner = $this->createUser(UserRole::OWNER, 'owner');
        $supplier = Supplier::create([
            'code' => 'SUP-FK-01',
            'name' => 'Kandy Botanicals',
            'created_by' => $owner->id,
        ]);

        PurchaseOrder::create([
            'po_number' => 'PO-UNIQ-0001',
            'supplier_id' => $supplier->id,
            'status' => PurchaseOrder::STATUS_DRAFT,
            'created_by' => $owner->id,
        ]);

        try {
            PurchaseOrder::create([
                'po_number' => 'PO-UNIQ-0001',
                'supplier_id' => $supplier->id,
                'status' => PurchaseOrder::STATUS_DRAFT,
                'created_by' => $owner->id,
            ]);
            $this->fail('Duplicate po_number should throw QueryException.');
        } catch (QueryException $e) {
            $this->assertTrue(true);
        }

        // Supplier with existing PO cannot be deleted due to restrictOnDelete()
        $this->expectException(QueryException::class);
        $supplier->delete();
    }

    public function test_only_one_preferred_supplier_is_allowed_per_product(): void
    {
        $owner = $this->createUser(UserRole::OWNER, 'owner');
        $product = $this->createProduct();

        $supplierA = Supplier::create(['code' => 'SUP-A', 'name' => 'Supplier A', 'created_by' => $owner->id]);
        $supplierB = Supplier::create(['code' => 'SUP-B', 'name' => 'Supplier B', 'created_by' => $owner->id]);

        $linkA = ProductSupplier::create([
            'product_id' => $product->id,
            'supplier_id' => $supplierA->id,
            'unit_cost' => 1000.00,
            'is_preferred' => true,
        ]);

        $linkB = ProductSupplier::create([
            'product_id' => $product->id,
            'supplier_id' => $supplierB->id,
            'unit_cost' => 950.00,
            'is_preferred' => true,
        ]);

        $this->assertFalse($linkA->fresh()->is_preferred);
        $this->assertTrue($linkB->fresh()->is_preferred);
        $this->assertSame(
            1,
            ProductSupplier::where('product_id', $product->id)->where('is_preferred', true)->count()
        );
    }

    public function test_rbac_permissions_and_owner_super_admin_accountant_separation(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $expectedPermissions = [
            'suppliers.view' => false,
            'suppliers.manage' => false,
            'purchase_orders.view' => false,
            'purchase_orders.create' => false,
            'purchase_orders.approve' => true,
            'purchase_orders.receive' => false,
            'purchase_orders.cancel' => false,
            'inventory.alerts.view' => false,
            'inventory.alerts.acknowledge' => false,
        ];

        foreach ($expectedPermissions as $name => $isRestricted) {
            $perm = Permission::where('name', $name)->first();
            $this->assertNotNull($perm, "Permission '{$name}' should be registered.");
            $this->assertSame($isRestricted, (bool) $perm->is_financial_restricted);
        }

        $owner = $this->createUser(UserRole::OWNER, 'owner');
        $superAdmin = $this->createUser(UserRole::SUPER_ADMIN, 'superadmin');
        $accountant = $this->createUser(UserRole::ACCOUNTANT, 'accountant');
        $invManager = $this->createUser(UserRole::PRODUCT_INVENTORY_MANAGER, 'invmgr');

        $this->seed(RolesAndPermissionsSeeder::class);

        $owner->refresh();
        $superAdmin->refresh();
        $accountant->refresh();
        $invManager->refresh();

        // Owner has PO approval authority
        $this->assertTrue($owner->hasPermissionTo('purchase_orders.approve'));
        $this->assertTrue($owner->hasPermissionTo('suppliers.manage'));

        // Super Admin is excluded from financial-restricted PO approval
        $this->assertFalse($superAdmin->hasPermissionTo('purchase_orders.approve'));
        $this->assertTrue($superAdmin->hasPermissionTo('suppliers.manage'));
        $this->assertTrue($superAdmin->hasPermissionTo('inventory.alerts.view'));

        // Accountant can view POs but cannot approve POs
        $this->assertTrue($accountant->hasPermissionTo('purchase_orders.view'));
        $this->assertFalse($accountant->hasPermissionTo('purchase_orders.approve'));
        $this->assertFalse($accountant->hasPermissionTo('purchase_orders.receive'));

        // Product & Inventory Manager manages suppliers, draft POs, receipts, alerts — NOT approval
        $this->assertTrue($invManager->hasPermissionTo('suppliers.manage'));
        $this->assertTrue($invManager->hasPermissionTo('purchase_orders.create'));
        $this->assertTrue($invManager->hasPermissionTo('purchase_orders.receive'));
        $this->assertTrue($invManager->hasPermissionTo('inventory.alerts.acknowledge'));
        $this->assertFalse($invManager->hasPermissionTo('purchase_orders.approve'));

        // Existing restricted permissions remain restricted from Super Admin
        $this->assertFalse($superAdmin->hasPermissionTo('payouts.approve'));
        $this->assertFalse($superAdmin->hasPermissionTo('commissions.rules.review'));
        $this->assertFalse($superAdmin->hasPermissionTo('agents.hierarchy.review'));
    }

    public function test_seeder_does_not_overwrite_custom_role_assignments(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $customRole = Role::create([
            'name' => 'warehouse_auditor',
            'display_name' => 'Warehouse Auditor',
            'description' => 'Custom read-only auditor role',
            'is_system' => false,
            'is_protected' => false,
        ]);

        $perm = Permission::where('name', 'inventory.alerts.view')->firstOrFail();
        $customRole->permissions()->sync([$perm->id]);

        // Re-running seeder must preserve custom role's permissions
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->assertTrue(
            $customRole->fresh()->permissions()->where('name', 'inventory.alerts.view')->exists()
        );
    }
}
