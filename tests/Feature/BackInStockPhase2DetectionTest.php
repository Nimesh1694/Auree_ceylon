<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\RestockEvent;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BackInStockPhase2DetectionTest extends TestCase
{
    use RefreshDatabase;

    protected StockService $stockService;
    protected User $adminUser;
    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stockService = app(StockService::class);

        $this->adminUser = User::factory()->create([
            'role' => 'admin',
        ]);

        $this->category = Category::create([
            'name_en' => 'Skincare',
            'name_si' => 'සම රැකවරණය',
            'slug' => 'skincare',
            'is_active' => true,
        ]);
    }

    protected function createProduct(array $overrides = []): Product
    {
        return Product::create(array_merge([
            'category_id' => $this->category->id,
            'name_en' => 'Radiance Serum',
            'name_si' => 'රේඩියන්ස් සීරම්',
            'slug' => 'radiance-serum-' . Str::random(8),
            'sku' => 'SERUM-' . strtoupper(Str::random(6)),
            'price' => 3500.00,
            'cost_price' => 2000.00,
            'stock_quantity' => 0,
            'low_stock_threshold' => 5,
            'is_active' => true,
        ], $overrides));
    }

    public function test_restock_event_created_on_transition_from_zero_to_positive(): void
    {
        $product = $this->createProduct(['stock_quantity' => 0]);

        $movement = $this->stockService->adjustStock(
            $product,
            10,
            InventoryMovement::TYPE_RESTOCK,
            'Received supplier shipment',
            null,
            $this->adminUser
        );

        $this->assertInstanceOf(InventoryMovement::class, $movement);
        $this->assertEquals(10, $product->fresh()->stock_quantity);

        $event = RestockEvent::where('product_id', $product->id)->first();
        $this->assertNotNull($event);
        $this->assertTrue(Str::isUuid($event->id));
        $this->assertEquals(0, $event->previous_stock);
        $this->assertEquals(10, $event->new_stock);
        $this->assertEquals(10, $event->restocked_quantity);
        $this->assertEquals(20, $event->batch_limit); // 10 * 2.0 = 20
        $this->assertEquals(0, $event->claimed_count);
        $this->assertEquals(RestockEvent::STATUS_PENDING_CLAIMS, $event->status);
    }

    public function test_restock_event_created_on_transition_from_negative_to_positive(): void
    {
        $product = $this->createProduct(['stock_quantity' => -3]);

        $this->stockService->adjustStock(
            $product,
            8,
            InventoryMovement::TYPE_RESTOCK,
            'Inventory reconciliation after physical audit',
            null,
            $this->adminUser
        );

        $this->assertEquals(5, $product->fresh()->stock_quantity);

        $event = RestockEvent::where('product_id', $product->id)->first();
        $this->assertNotNull($event);
        $this->assertEquals(-3, $event->previous_stock);
        $this->assertEquals(5, $event->new_stock);
        $this->assertEquals(8, $event->restocked_quantity);
        $this->assertEquals(10, $event->batch_limit); // 5 * 2.0 = 10
        $this->assertEquals(RestockEvent::STATUS_PENDING_CLAIMS, $event->status);
    }

    public function test_disqualified_when_product_already_in_stock(): void
    {
        $product = $this->createProduct(['stock_quantity' => 5]);

        $this->stockService->adjustStock(
            $product,
            10,
            InventoryMovement::TYPE_RESTOCK,
            'Additional stock received',
            null,
            $this->adminUser
        );

        $this->assertEquals(15, $product->fresh()->stock_quantity);

        // Disqualified: previous_stock was 5 (> 0)
        $this->assertEquals(0, RestockEvent::where('product_id', $product->id)->count());
    }

    public function test_disqualified_when_stock_remains_out_of_stock(): void
    {
        $product = $this->createProduct(['stock_quantity' => -2]);

        // Adjustment from -2 to 0 (reaches zero, still out of stock)
        $this->stockService->adjustStock(
            $product,
            2,
            InventoryMovement::TYPE_RESTOCK,
            'Reconciled to zero',
            null,
            $this->adminUser
        );

        $this->assertEquals(0, $product->fresh()->stock_quantity);
        $this->assertEquals(0, RestockEvent::where('product_id', $product->id)->count());

        // Direct service evaluation where stock remains negative
        $event = app(\App\Services\BackInStockService::class)->recordRestockEventIfQualifying($product, -5, -2, 3);
        $this->assertNull($event);
    }

    public function test_disqualified_when_product_is_inactive(): void
    {
        $product = $this->createProduct([
            'stock_quantity' => 0,
            'is_active' => false, // Inactive / Draft
        ]);

        $this->stockService->adjustStock(
            $product,
            20,
            InventoryMovement::TYPE_RESTOCK,
            'Stocking draft item',
            null,
            $this->adminUser
        );

        $this->assertEquals(20, $product->fresh()->stock_quantity);

        // Disqualified: product is_active is false
        $this->assertEquals(0, RestockEvent::where('product_id', $product->id)->count());
    }

    public function test_duplicate_prevention_coalesces_into_existing_pending_event(): void
    {
        $product = $this->createProduct(['stock_quantity' => 0]);

        // Adjustment 1: 0 to 5 -> Creates Event 1
        $this->stockService->adjustStock(
            $product,
            5,
            InventoryMovement::TYPE_RESTOCK,
            'Batch 1 delivery',
            null,
            $this->adminUser
        );

        $this->assertEquals(1, RestockEvent::where('product_id', $product->id)->count());
        $initialEvent = RestockEvent::where('product_id', $product->id)->first();
        $this->assertEquals(5, $initialEvent->new_stock);
        $this->assertEquals(5, $initialEvent->restocked_quantity);
        $this->assertEquals(10, $initialEvent->batch_limit);

        // Simulate stock brought back to 0 before claims processed
        DB::table('products')->where('id', $product->id)->update(['stock_quantity' => 0]);
        $product->refresh();

        // Adjustment 2: 0 to 15 -> Coalesces into existing pending event
        $this->stockService->adjustStock(
            $product,
            15,
            InventoryMovement::TYPE_RESTOCK,
            'Batch 2 delivery while batch 1 pending',
            null,
            $this->adminUser
        );

        // Exactly ONE event exists in DB
        $this->assertEquals(1, RestockEvent::where('product_id', $product->id)->count());
        $coalescedEvent = RestockEvent::where('product_id', $product->id)->first();
        $this->assertEquals($initialEvent->id, $coalescedEvent->id);
        $this->assertEquals(15, $coalescedEvent->new_stock);
        $this->assertEquals(20, $coalescedEvent->restocked_quantity); // 5 + 15
        $this->assertEquals(30, $coalescedEvent->batch_limit); // 15 * 2 = 30
        $this->assertEquals(RestockEvent::STATUS_PENDING_CLAIMS, $coalescedEvent->status);
    }

    public function test_duplicate_prevention_preserves_processing_event(): void
    {
        $product = $this->createProduct(['stock_quantity' => 0]);

        $this->stockService->adjustStock(
            $product,
            10,
            InventoryMovement::TYPE_RESTOCK,
            'Initial restock',
            null,
            $this->adminUser
        );

        $event = RestockEvent::where('product_id', $product->id)->first();
        $event->status = RestockEvent::STATUS_PROCESSING;
        $event->claimed_count = 5;
        $event->save();

        // Simulate stock brought back to 0 and restocked again while processing
        DB::table('products')->where('id', $product->id)->update(['stock_quantity' => 0]);
        $product->refresh();

        $this->stockService->adjustStock(
            $product,
            10,
            InventoryMovement::TYPE_RESTOCK,
            'Restock while processing',
            null,
            $this->adminUser
        );

        // Still exactly ONE event; status remains processing
        $this->assertEquals(1, RestockEvent::where('product_id', $product->id)->count());
        $freshEvent = RestockEvent::where('product_id', $product->id)->first();
        $this->assertEquals($event->id, $freshEvent->id);
        $this->assertEquals(RestockEvent::STATUS_PROCESSING, $freshEvent->status);
    }

    public function test_transaction_rollback_reverts_restock_event_creation(): void
    {
        $product = $this->createProduct(['stock_quantity' => 0]);

        try {
            DB::transaction(function () use ($product) {
                $this->stockService->adjustStock(
                    $product,
                    10,
                    InventoryMovement::TYPE_RESTOCK,
                    'Doomed transaction',
                    null,
                    $this->adminUser
                );

                // Simulate unexpected failure after adjustStock inside same transaction
                throw new \RuntimeException('Simulated subsequent step failure');
            });
        } catch (\RuntimeException $e) {
            // Caught expected failure
        }

        // Product stock remains 0
        $this->assertEquals(0, $product->fresh()->stock_quantity);

        // Restock event must be rolled back completely
        $this->assertEquals(0, RestockEvent::where('product_id', $product->id)->count());
        $this->assertEquals(0, InventoryMovement::where('product_id', $product->id)->count());
    }

    public function test_order_cancellation_restore_stock_triggers_restock_event(): void
    {
        $product = $this->createProduct(['stock_quantity' => 0]);

        $order = Order::create([
            'order_number' => 'ORD-' . strtoupper(Str::random(10)),
            'customer_id' => $this->adminUser->id,
            'customer_name' => 'Jane Doe',
            'customer_phone' => '0771234567',
            'shipping_address' => '456 High St, Kandy',
            'district' => 'Kandy',
            'city' => 'Kandy',
            'subtotal' => 7000.00,
            'delivery_charge' => 350.00,
            'total_amount' => 7350.00,
            'tracking_token' => Str::random(64),
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name_en,
            'price' => 3500.00,
            'unit_price' => 3500.00,
            'quantity' => 4,
            'subtotal' => 14000.00,
        ]);

        $movements = $this->stockService->restoreOrderStock(
            $order,
            'Customer cancelled before shipment',
            InventoryMovement::TYPE_CANCELLATION_RESTOCK,
            $this->adminUser
        );

        $this->assertCount(1, $movements);
        $this->assertEquals(4, $product->fresh()->stock_quantity);

        $event = RestockEvent::where('product_id', $product->id)->first();
        $this->assertNotNull($event);
        $this->assertEquals(0, $event->previous_stock);
        $this->assertEquals(4, $event->new_stock);
        $this->assertEquals(4, $event->restocked_quantity);
        $this->assertEquals(8, $event->batch_limit);
        $this->assertEquals(RestockEvent::STATUS_PENDING_CLAIMS, $event->status);
    }

    public function test_duplicate_order_restoration_is_prevented_and_creates_no_duplicate_events(): void
    {
        $product = $this->createProduct(['stock_quantity' => 0]);

        $order = Order::create([
            'order_number' => 'ORD-' . strtoupper(Str::random(10)),
            'customer_id' => $this->adminUser->id,
            'customer_name' => 'Jane Doe',
            'customer_phone' => '0771234567',
            'shipping_address' => '456 High St, Kandy',
            'district' => 'Kandy',
            'city' => 'Kandy',
            'subtotal' => 3500.00,
            'delivery_charge' => 350.00,
            'total_amount' => 3850.00,
            'tracking_token' => Str::random(64),
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name_en,
            'price' => 3500.00,
            'unit_price' => 3500.00,
            'quantity' => 2,
            'subtotal' => 7000.00,
        ]);

        // First call succeeds
        $this->stockService->restoreOrderStock($order, 'First cancellation');
        $this->assertEquals(1, RestockEvent::where('product_id', $product->id)->count());

        // Second call throws ValidationException
        $this->expectException(ValidationException::class);
        $this->stockService->restoreOrderStock($order, 'Duplicate cancellation attempt');
    }

    public function test_audit_log_created_for_restock_event(): void
    {
        $product = $this->createProduct(['stock_quantity' => 0]);

        $this->actingAs($this->adminUser);

        $this->stockService->adjustStock(
            $product,
            12,
            InventoryMovement::TYPE_RESTOCK,
            'Audited restock',
            null,
            $this->adminUser
        );

        $event = RestockEvent::where('product_id', $product->id)->first();
        $this->assertNotNull($event);

        $log = AuditLog::where('action', 'restock_event_created')
            ->where('auditable_id', $product->id)
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals($this->adminUser->id, $log->user_id);
        $this->assertEquals($event->id, $log->new_values['restock_event_id']);
        $this->assertEquals(0, $log->new_values['previous_stock']);
        $this->assertEquals(12, $log->new_values['new_stock']);
        $this->assertEquals(12, $log->new_values['restocked_quantity']);
        $this->assertEquals(24, $log->new_values['batch_limit']);
    }
}
