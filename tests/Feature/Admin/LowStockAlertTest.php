<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Category;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\StockAlert;
use App\Models\User;
use App\Notifications\LowStockAlertOpenedNotification;
use App\Notifications\LowStockDailyDigestNotification;
use App\Services\OrderService;
use App\Services\PurchaseOrderService;
use App\Services\StockService;
use App\Services\SupplierService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LowStockAlertTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $invManager;
    private User $marketingManager;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->owner = $this->makeUser(UserRole::OWNER, 'owner');
        $this->invManager = $this->makeUser(UserRole::PRODUCT_INVENTORY_MANAGER, 'invmgr');
        $this->marketingManager = $this->makeUser(UserRole::MARKETING_MANAGER, 'marketing');

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->owner->refresh();
        $this->invManager->refresh();
        $this->marketingManager->refresh();

        $category = Category::create([
            'name_en' => 'Cleansers',
            'name_si' => 'ක්ලෙන්සර්',
            'slug' => 'cleansers-' . uniqid(),
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'category_id' => $category->id,
            'name_en' => 'Gentle Rose Cleanser',
            'name_si' => 'රෝස් ක්ලෙන්සර්',
            'slug' => 'rose-cleanser-' . uniqid(),
            'sku' => 'GRC-001',
            'price' => 2800.00,
            'cost_price' => 1100.00,
            'stock_quantity' => 10,
            'low_stock_threshold' => 5,
            'is_active' => true,
        ]);
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

    public function test_stock_threshold_detection_deduplication_resolution_and_retrigger(): void
    {
        Notification::fake();
        $stockService = app(StockService::class);

        // 1. Stock stays above threshold (10 -> 6, threshold = 5) -> NO alert
        $stockService->adjustStock($this->product, -4, InventoryMovement::TYPE_ADJUSTMENT, 'Sample out', null, $this->invManager);
        $this->assertSame(0, StockAlert::count());
        Notification::assertNothingSent();

        // 2. Stock reaches exact threshold (6 -> 5) -> 1 OPEN alert + notification after commit
        $stockService->adjustStock($this->product, -1, InventoryMovement::TYPE_ADJUSTMENT, 'Reach threshold', null, $this->invManager);
        $this->assertSame(1, StockAlert::where('status', StockAlert::STATUS_OPEN)->count());
        Notification::assertSentTo($this->invManager, LowStockAlertOpenedNotification::class);
        Notification::assertSentTo($this->owner, LowStockAlertOpenedNotification::class);

        // 3. Repeated deduction below threshold (5 -> 2) -> NO duplicate alert, NO duplicate email
        Notification::fake();
        $stockService->adjustStock($this->product, -3, InventoryMovement::TYPE_DAMAGE_LOSS, 'Damaged', null, $this->invManager);
        $this->assertSame(1, StockAlert::where('product_id', $this->product->id)->count());
        $this->assertSame(2, StockAlert::first()->current_stock);
        Notification::assertNothingSent();

        // 4. Restock above threshold (2 -> 12) -> Alert auto-resolved, active_product_id cleared
        $stockService->adjustStock($this->product, 10, InventoryMovement::TYPE_RESTOCK, 'Restocked', null, $this->invManager);
        $resolved = StockAlert::firstOrFail();
        $this->assertSame(StockAlert::STATUS_RESOLVED, $resolved->status);
        $this->assertNull($resolved->active_product_id);
        $this->assertNotNull($resolved->resolved_at);

        // 5. Stock drops again (12 -> 3) -> New OPEN alert created while historical resolved alert preserved
        $stockService->adjustStock($this->product, -9, InventoryMovement::TYPE_ADJUSTMENT, 'Drop again', null, $this->invManager);
        $this->assertSame(2, StockAlert::where('product_id', $this->product->id)->count());
        $this->assertSame(1, StockAlert::where('product_id', $this->product->id)->where('status', StockAlert::STATUS_RESOLVED)->count());
        $this->assertSame(1, StockAlert::where('product_id', $this->product->id)->where('status', StockAlert::STATUS_OPEN)->count());

        // AuditLog events recorded
        $this->assertDatabaseHas('audit_logs', ['action' => 'stock_alert_opened']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'stock_alert_resolved']);
    }

    public function test_order_checkout_and_po_receiving_trigger_and_resolve_alerts(): void
    {
        Notification::fake();

        // Order checkout deducts 6 units (10 -> 4 <= 5) -> triggers OPEN alert
        $orderService = app(OrderService::class);
        $orderService->createOrder([
            'customer_name' => 'Nimali Silva',
            'customer_phone' => '0771112233',
            'shipping_address' => '45 Galle Road',
            'district' => 'Colombo',
            'city' => 'Colombo',
        ], [$this->product->id => 6]);

        $alert = StockAlert::where('product_id', $this->product->id)->firstOrFail();
        $this->assertSame(StockAlert::STATUS_OPEN, $alert->status);
        $this->assertSame(InventoryMovement::TYPE_ORDER_DEDUCTION, $alert->trigger_source);
        $this->assertSame(4, $alert->current_stock);

        // PO Receiving adds 10 units (4 -> 14 > 5) -> auto-resolves alert without breaking WAC
        $supplierService = app(SupplierService::class);
        $supplier = $supplierService->createSupplier(['name' => 'Rose Supplier'], $this->owner);
        $supplierService->linkProduct($supplier, $this->product, [
            'unit_cost' => 1200.00,
            'min_order_qty' => 1,
            'is_preferred' => true,
        ], $this->owner);

        $poService = app(PurchaseOrderService::class);
        $po = $poService->createDraft([
            'supplier_id' => $supplier->id,
            'items' => [['product_id' => $this->product->id, 'ordered_qty' => 10, 'unit_cost' => '1200.00']],
        ], $this->invManager);
        $poService->approve($po, $this->owner);
        $poService->receive($po, [['purchase_order_item_id' => $po->items()->first()->id, 'quantity_received' => 10]], null, $this->invManager);

        $this->assertSame(StockAlert::STATUS_RESOLVED, $alert->fresh()->status);
        $this->assertSame(14, $this->product->fresh()->stock_quantity);
    }

    public function test_database_level_unique_constraint_prevents_concurrent_duplicate_active_alerts(): void
    {
        StockAlert::create([
            'product_id' => $this->product->id,
            'status' => StockAlert::STATUS_OPEN,
            'stock_at_trigger' => 4,
            'threshold_at_trigger' => 5,
            'current_stock' => 4,
            'trigger_source' => 'order_deduction',
            'opened_at' => now(),
        ]);

        $this->expectException(QueryException::class);
        DB::table('stock_alerts')->insert([
            'product_id' => $this->product->id,
            'active_product_id' => $this->product->id,
            'status' => StockAlert::STATUS_OPEN,
            'stock_at_trigger' => 3,
            'threshold_at_trigger' => 5,
            'current_stock' => 3,
            'trigger_source' => 'order_deduction',
            'opened_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_rbac_acknowledgement_and_after_commit_rollback_safety(): void
    {
        Notification::fake();

        // Rolled-back transaction must NOT create alert or dispatch notification
        try {
            DB::transaction(function () {
                app(StockService::class)->adjustStock($this->product, -7, InventoryMovement::TYPE_ADJUSTMENT, 'Rollback test');
                throw new \RuntimeException('Simulated failure');
            });
        } catch (\RuntimeException) {
        }
        $this->assertSame(0, StockAlert::count());
        Notification::assertNothingSent();

        // Trigger real alert
        app(StockService::class)->adjustStock($this->product, -6, InventoryMovement::TYPE_ADJUSTMENT, 'Real low stock');
        $alert = StockAlert::firstOrFail();

        // Unauthorized role cannot view or acknowledge
        $this->actingAs($this->marketingManager)->get('/admin/inventory/alerts')->assertForbidden();
        $this->actingAs($this->marketingManager)->post("/admin/inventory/alerts/{$alert->id}/acknowledge")->assertForbidden();

        // Authorized user can view and acknowledge
        $this->actingAs($this->invManager)
            ->get('/admin/inventory/alerts?status=open')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Inventory/Alerts')
                ->has('alerts.data', 1)
            );

        $this->actingAs($this->invManager)
            ->post("/admin/inventory/alerts/{$alert->id}/acknowledge", [
                'acknowledgement_note' => 'PO raised',
            ])
            ->assertRedirect();

        $alert->refresh();
        $this->assertSame(StockAlert::STATUS_ACKNOWLEDGED, $alert->status);
        $this->assertSame($this->invManager->id, $alert->acknowledged_by);
        $this->assertSame('PO raised', $alert->acknowledgement_note);
        $this->assertDatabaseHas('audit_logs', ['action' => 'stock_alert_acknowledged', 'auditable_id' => $alert->id]);

        // Re-acknowledging an already-acknowledged alert is rejected
        $this->actingAs($this->invManager)
            ->post("/admin/inventory/alerts/{$alert->id}/acknowledge")
            ->assertSessionHasErrors('status');
    }

    public function test_daily_digest_command_sends_only_for_open_alerts(): void
    {
        Notification::fake();

        // No open alerts -> digest sends nothing
        Artisan::call('inventory:send-low-stock-digest');
        Notification::assertNothingSent();

        // Open alert -> digest sends LowStockDailyDigestNotification
        app(StockService::class)->adjustStock($this->product, -6, InventoryMovement::TYPE_ADJUSTMENT, 'Digest test');
        Notification::fake();

        Artisan::call('inventory:send-low-stock-digest');
        Notification::assertSentTo($this->invManager, LowStockDailyDigestNotification::class);
        Notification::assertSentTo($this->owner, LowStockDailyDigestNotification::class);
    }

    public function test_fnd08_restore_order_stock_resolves_open_alert_and_digest_excludes_acknowledged(): void
    {
        Notification::fake();

        $orderService = app(OrderService::class);
        $order = $orderService->createOrder([
            'customer_name' => 'Test Buyer',
            'customer_phone' => '0779998877',
            'shipping_address' => '10 Main St',
            'district' => 'Colombo',
            'city' => 'Colombo',
        ], [$this->product->id => 6]); // 10 -> 4 <= 5

        $alert = StockAlert::where('product_id', $this->product->id)->firstOrFail();
        $this->assertSame(StockAlert::STATUS_OPEN, $alert->status);

        // Acknowledge alert -> daily digest must exclude acknowledged alerts
        app(\App\Services\LowStockAlertService::class)->acknowledge($alert, 'Ordered restock', $this->invManager);
        Notification::fake();

        Artisan::call('inventory:send-low-stock-digest');
        Notification::assertNothingSent();

        // Restore order stock (4 -> 10 > 5) -> resolves acknowledged alert
        app(StockService::class)->restoreOrderStock($order, 'Order cancelled', InventoryMovement::TYPE_CANCELLATION_RESTOCK, $this->invManager);

        $alert->refresh();
        $this->assertSame(StockAlert::STATUS_RESOLVED, $alert->status);
        $this->assertNull($alert->active_product_id);
        $this->assertSame(10, $this->product->fresh()->stock_quantity);
    }
}

