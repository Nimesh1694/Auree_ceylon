<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\CodCollection;
use App\Models\Courier;
use App\Models\Delivery;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Setting;
use App\Models\User;
use App\Services\OrderStateMachineService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class Phase3SecurityAuditTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $marketingManager;
    private User $itManager;
    private User $accountant;
    private User $agent;
    private User $customer;
    private Product $product;
    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::set('default_delivery_charge', 350.00, 'shipping', 'float');

        $this->owner = User::factory()->create(['role' => UserRole::OWNER]);
        $this->marketingManager = User::factory()->create(['role' => UserRole::MARKETING_MANAGER]);
        $this->itManager = User::factory()->create(['role' => UserRole::IT_MANAGER]);
        $this->accountant = User::factory()->create(['role' => UserRole::ACCOUNTANT]);
        $this->agent = User::factory()->create(['role' => UserRole::LOW_LEVEL_AGENT]);
        $this->customer = User::factory()->create(['role' => UserRole::CUSTOMER]);

        $this->category = Category::create([
            'name_en' => 'Night Serums',
            'name_si' => 'රාත්‍රී සීරම්',
            'slug' => 'night-serums',
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'category_id' => $this->category->id,
            'name_en' => 'Orchid Night Elixir',
            'name_si' => 'එලික්සර්',
            'slug' => 'orchid-night-elixir',
            'sku' => 'AUDIT-ORC-001',
            'price' => 4500.00,
            'cost_price' => 1500.00,
            'stock_quantity' => 20,
            'low_stock_threshold' => 5,
            'is_active' => true,
        ]);
    }

    /**
     * Audit Test 1: Agent and Customer are strictly forbidden from all admin routes.
     */
    public function test_customer_and_agent_blocked_from_all_admin_endpoints(): void
    {
        $adminEndpoints = [
            '/admin/dashboard',
            '/admin/categories',
            '/admin/products',
            '/admin/inventory',
            '/admin/inventory/movements',
            '/admin/orders',
            '/admin/couriers',
        ];

        foreach ($adminEndpoints as $url) {
            $this->actingAs($this->customer)->get($url)->assertStatus(403);
            $this->actingAs($this->agent)->get($url)->assertStatus(403);
        }
    }

    /**
     * Audit Test 2: Marketing Manager cannot view or modify cost price.
     */
    public function test_marketing_manager_cannot_read_or_overwrite_cost_price(): void
    {
        // 1. Reading product index: cost_price is masked as null
        $response = $this->actingAs($this->marketingManager)->get('/admin/products');
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->where('canViewCostPrice', false)
                ->where('products.data.0.cost_price', null)
        );

        // 2. Editing product: cost_price is masked
        $responseEdit = $this->actingAs($this->marketingManager)->get("/admin/products/{$this->product->id}/edit");
        $responseEdit->assertStatus(200);
        $responseEdit->assertInertia(fn ($page) =>
            $page->where('canViewCostPrice', false)
                ->where('product.cost_price', null)
        );

        // 3. Updating product with tampered cost_price: server ignores and preserves true cost price
        $this->actingAs($this->marketingManager)->put("/admin/products/{$this->product->id}", [
            'name_en' => 'Orchid Night Elixir Updated',
            'name_si' => 'එලික්සර්',
            'price' => 4500.00,
            'cost_price' => 1.00, // Tampered cost price
            'low_stock_threshold' => 5,
            'is_active' => true,
        ]);

        $this->assertEquals('1500.00', (string) $this->product->fresh()->cost_price); // Untouched!
    }

    /**
     * Audit Test 3: Accountant cannot adjust inventory or manage couriers.
     */
    public function test_accountant_forbidden_from_inventory_adjustments_and_courier_management(): void
    {
        // Inventory adjustment blocked
        $this->actingAs($this->accountant)->post('/admin/inventory/adjust', [
            'product_id' => $this->product->id,
            'quantity_delta' => 10,
            'movement_type' => 'restock',
            'reason' => 'Unauthorized attempt',
        ])->assertStatus(403);

        // Courier creation blocked
        $this->actingAs($this->accountant)->post('/admin/couriers', [
            'name' => 'Fake Courier',
            'code' => 'fake_courier',
        ])->assertStatus(403);
    }

    /**
     * Audit Test 4: Inventory adjustments strictly enforce non-negative stock and record audit ledger.
     */
    public function test_inventory_ledger_maintains_exact_balances_and_prevents_negative_stock(): void
    {
        $stockService = app(StockService::class);

        // Valid adjustment: +30
        $movement = $stockService->adjustStock(
            product: $this->product,
            quantityDelta: 30,
            movementType: InventoryMovement::TYPE_RESTOCK,
            reason: 'Audit restock test',
            user: $this->owner
        );

        $this->assertEquals(20, $movement->previous_stock);
        $this->assertEquals(50, $movement->new_stock);
        $this->assertEquals(50, $this->product->fresh()->stock_quantity);

        // Attempt invalid deduction: -100 (available is 50)
        try {
            $stockService->adjustStock(
                product: $this->product,
                quantityDelta: -100,
                movementType: InventoryMovement::TYPE_ADJUSTMENT,
                reason: 'Excessive deduction',
                user: $this->owner
            );
            $this->fail('Expected ValidationException was not thrown for negative stock.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('stock', $e->errors());
            $this->assertEquals(50, $this->product->fresh()->stock_quantity); // Stock preserved
        }
    }

    /**
     * Audit Test 5: Order state machine blocks invalid transition jumps.
     */
    public function test_state_machine_blocks_illegal_stage_skips(): void
    {
        $stateMachine = app(OrderStateMachineService::class);

        $order = Order::create([
            'order_number' => 'ORD-AUDIT-001',
            'customer_name' => 'Kamal Silva',
            'customer_phone' => '0770001122',
            'shipping_address' => 'Colombo 03',
            'district' => 'Colombo',
            'city' => 'Colombo',
            'subtotal' => 4500.00,
            'delivery_charge' => 350.00,
            'total_amount' => 4850.00,
            'payment_method' => 'cod',
            'status' => 'placed',
            'tracking_token' => 'AUDIT_TOKEN_01',
            'placed_at' => now(),
        ]);

        // Attempting to skip placed -> shipped
        $this->assertFalse($stateMachine->canTransition('placed', 'shipped'));
        $this->assertFalse($stateMachine->canTransition('placed', 'completed'));
        $this->assertFalse($stateMachine->canTransition('packed', 'delivered'));

        // Attempting to execute illegal transition throws ValidationException
        $this->expectException(ValidationException::class);
        $stateMachine->transition($order, 'shipped');
    }

    /**
     * Audit Test 6: Shipping requires courier name and tracking number.
     */
    public function test_marking_as_shipped_requires_courier_and_tracking_number(): void
    {
        $stateMachine = app(OrderStateMachineService::class);

        $order = Order::create([
            'order_number' => 'ORD-AUDIT-002',
            'customer_name' => 'Nimali Fernando',
            'customer_phone' => '0770002233',
            'shipping_address' => 'Galle Road',
            'district' => 'Colombo',
            'city' => 'Dehiwala',
            'subtotal' => 4500.00,
            'delivery_charge' => 350.00,
            'total_amount' => 4850.00,
            'payment_method' => 'cod',
            'status' => 'packed',
            'tracking_token' => 'AUDIT_TOKEN_02',
            'placed_at' => now(),
        ]);

        Delivery::create([
            'order_id' => $order->id,
            'status' => 'pending',
        ]);

        // Transition without courier details must fail
        $this->expectException(ValidationException::class);
        $stateMachine->transition($order, 'shipped', null, $this->owner, [
            'courier_name' => '',
            'tracking_number' => '',
        ]);
    }

    /**
     * Audit Test 7: Cash collected strictly requires verified non-zero collection record.
     */
    public function test_cash_collected_blocked_without_reconciled_cod_record(): void
    {
        $stateMachine = app(OrderStateMachineService::class);

        $order = Order::create([
            'order_number' => 'ORD-AUDIT-003',
            'customer_name' => 'Pradeep Kumara',
            'customer_phone' => '0770003344',
            'shipping_address' => 'Kandy Road',
            'district' => 'Gampaha',
            'city' => 'Kadawatha',
            'subtotal' => 4500.00,
            'delivery_charge' => 350.00,
            'total_amount' => 4850.00,
            'payment_method' => 'cod',
            'status' => 'delivered',
            'tracking_token' => 'AUDIT_TOKEN_03',
            'placed_at' => now(),
        ]);

        Delivery::create([
            'order_id' => $order->id,
            'courier_name' => 'Domex',
            'tracking_number' => 'DMX-998877',
            'status' => 'delivered',
        ]);

        // Pending un-reconciled COD record
        CodCollection::create([
            'order_id' => $order->id,
            'expected_amount' => 4850.00,
            'collected_amount' => 0.00,
            'reconciliation_status' => CodCollection::STATUS_PENDING,
        ]);

        // Must throw ValidationException because reconciliation status is still pending
        $this->expectException(ValidationException::class);
        $stateMachine->transition($order, 'cash_collected', null, $this->accountant);
    }

    /**
     * Audit Test 8: Double stock restoration prevention on cancellation.
     */
    public function test_double_stock_restoration_is_strictly_prevented(): void
    {
        $stockService = app(StockService::class);

        $order = Order::create([
            'order_number' => 'ORD-AUDIT-004',
            'customer_name' => 'Anura Banda',
            'customer_phone' => '0770004455',
            'shipping_address' => 'Main St',
            'district' => 'Matara',
            'city' => 'Matara',
            'subtotal' => 4500.00,
            'delivery_charge' => 350.00,
            'total_amount' => 4850.00,
            'payment_method' => 'cod',
            'status' => 'placed',
            'tracking_token' => 'AUDIT_TOKEN_04',
            'placed_at' => now(),
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_name' => 'Orchid Night Elixir',
            'product_sku' => 'AUDIT-ORC-001',
            'unit_price' => 4500.00,
            'quantity' => 3,
            'subtotal' => 13500.00,
        ]);

        $initialStock = $this->product->stock_quantity; // 20

        // 1st restoration succeeds (+3)
        $stockService->restoreOrderStock($order, 'Order cancelled by customer', InventoryMovement::TYPE_CANCELLATION_RESTOCK, $this->owner);
        $this->assertEquals($initialStock + 3, $this->product->fresh()->stock_quantity);

        // 2nd restoration must fail with ValidationException
        $this->expectException(ValidationException::class);
        $stockService->restoreOrderStock($order, 'Second cancellation attempt', InventoryMovement::TYPE_CANCELLATION_RESTOCK, $this->owner);
    }
}

