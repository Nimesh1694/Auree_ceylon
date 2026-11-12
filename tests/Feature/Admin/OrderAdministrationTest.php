<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\CodCollection;
use App\Models\Courier;
use App\Models\Delivery;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Services\OrderService;
use App\Services\OrderStateMachineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OrderAdministrationTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $accountant;
    private User $customer;
    private Product $product;
    private Order $order;
    private OrderStateMachineService $stateMachine;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::set('default_delivery_charge', 350.00, 'shipping', 'float');

        $this->owner = User::factory()->create(['role' => UserRole::OWNER]);
        $this->accountant = User::factory()->create(['role' => UserRole::ACCOUNTANT]);
        $this->customer = User::factory()->create(['role' => UserRole::CUSTOMER]);

        $this->product = Product::create([
            'name_en' => 'Hibiscus Face Toner',
            'name_si' => 'ටෝනර්',
            'slug' => 'hibiscus-face-toner',
            'sku' => 'AL-HIB-001',
            'price' => 2000.00,
            'stock_quantity' => 20,
            'is_active' => true,
        ]);

        $this->order = Order::create([
            'order_number' => 'ORD-20261102-ADMIN1',
            'customer_name' => 'Senaka Perera',
            'customer_phone' => '0771122334',
            'shipping_address' => 'No 45, Temple Road',
            'district' => 'Colombo',
            'city' => 'Nugegoda',
            'subtotal' => 2000.00,
            'delivery_charge' => 350.00,
            'total_amount' => 2350.00,
            'payment_method' => 'cod',
            'status' => OrderStateMachineService::STATUS_PLACED,
            'tracking_token' => 'TOKEN_ADMIN_123',
            'placed_at' => now(),
        ]);

        OrderItem::create([
            'order_id' => $this->order->id,
            'product_id' => $this->product->id,
            'product_name' => 'Hibiscus Face Toner',
            'product_sku' => 'AL-HIB-001',
            'unit_price' => 2000.00,
            'quantity' => 2,
            'subtotal' => 4000.00,
        ]);

        Delivery::create([
            'order_id' => $this->order->id,
            'status' => 'pending',
        ]);

        CodCollection::create([
            'order_id' => $this->order->id,
            'expected_amount' => 2350.00,
            'collected_amount' => 0.00,
            'reconciliation_status' => CodCollection::STATUS_PENDING,
        ]);

        $this->stateMachine = app(OrderStateMachineService::class);
    }

    public function test_strict_order_lifecycle_from_placed_to_completed(): void
    {
        // 1. placed -> confirmed
        $this->actingAs($this->owner)->post("/admin/orders/{$this->order->id}/status", [
            'status' => 'confirmed',
        ])->assertSessionHas('success');
        $this->assertEquals('confirmed', $this->order->fresh()->status);

        // 2. confirmed -> packed
        $this->actingAs($this->owner)->post("/admin/orders/{$this->order->id}/status", [
            'status' => 'packed',
        ])->assertSessionHas('success');
        $this->assertEquals('packed', $this->order->fresh()->status);

        // 3. packed -> shipped (requires courier & tracking number)
        $this->actingAs($this->owner)->post("/admin/orders/{$this->order->id}/status", [
            'status' => 'shipped',
            'courier_name' => 'Domex Courier',
            'tracking_number' => 'DMX-12345678',
        ])->assertSessionHas('success');
        $this->assertEquals('shipped', $this->order->fresh()->status);
        $this->assertEquals('in_transit', $this->order->delivery->fresh()->status);

        // 4. shipped -> delivered
        $this->actingAs($this->owner)->post("/admin/orders/{$this->order->id}/status", [
            'status' => 'delivered',
        ])->assertSessionHas('success');
        $this->assertEquals('delivered', $this->order->fresh()->status);
        $this->assertEquals('delivered', $this->order->delivery->fresh()->status);

        // 5. Attempting to mark as cash_collected before reconciling fails!
        $this->expectException(ValidationException::class);
        $this->stateMachine->transition($this->order->fresh(), 'cash_collected');

        // 6. Record and reconcile physical COD cash
        $this->actingAs($this->accountant)->post("/admin/orders/{$this->order->id}/reconcile-cod", [
            'collected_amount' => 2350.00,
            'courier_reference' => 'REMIT-DMX-991',
        ])->assertSessionHas('success');

        $this->assertEquals(
            CodCollection::STATUS_MATCHED,
            $this->order->codCollection->fresh()->reconciliation_status
        );

        // 7. delivered -> cash_collected succeeds now!
        $this->actingAs($this->accountant)->post("/admin/orders/{$this->order->id}/status", [
            'status' => 'cash_collected',
        ])->assertSessionHas('success');
        $this->assertEquals('cash_collected', $this->order->fresh()->status);

        // 8. cash_collected -> completed
        $this->actingAs($this->owner)->post("/admin/orders/{$this->order->id}/status", [
            'status' => 'completed',
        ])->assertSessionHas('success');
        $this->assertEquals('completed', $this->order->fresh()->status);
    }

    public function test_invalid_status_transition_is_rejected(): void
    {
        // Cannot jump directly from placed to shipped
        $this->expectException(ValidationException::class);
        $this->stateMachine->transition($this->order, 'shipped');
    }

    public function test_order_cancellation_restores_stock_and_prevents_duplicate_restoration(): void
    {
        $initialStock = $this->product->stock_quantity; // 20

        // Cancel order with quantity = 2
        $this->actingAs($this->owner)->post("/admin/orders/{$this->order->id}/status", [
            'status' => 'cancelled',
            'reason' => 'Customer changed mind',
        ]);

        $this->assertEquals('cancelled', $this->order->fresh()->status);

        // Stock restored from 20 to 22
        $this->assertEquals($initialStock + 2, $this->product->fresh()->stock_quantity);

        // Ledger record created
        $this->assertDatabaseHas('inventory_movements', [
            'order_id' => $this->order->id,
            'movement_type' => InventoryMovement::TYPE_CANCELLATION_RESTOCK,
            'quantity' => 2,
        ]);

        // Attempting to cancel or restore stock a second time must fail!
        $this->expectException(ValidationException::class);
        $this->stateMachine->transition($this->order->fresh(), 'cancelled', 'Double cancel');
    }

    public function test_refused_delivery_restores_stock_and_marks_delivery_returned(): void
    {
        // Transition order to shipped first
        $this->order->update(['status' => 'shipped']);
        $this->order->delivery->update(['status' => 'in_transit']);

        $initialStock = $this->product->stock_quantity;

        // Customer refused parcel at door
        $this->actingAs($this->owner)->post("/admin/orders/{$this->order->id}/status", [
            'status' => 'refused',
            'reason' => 'Customer unavailable at delivery address',
        ]);

        $this->assertEquals('refused', $this->order->fresh()->status);
        $this->assertEquals('returned', $this->order->delivery->fresh()->status);
        $this->assertEquals($initialStock + 2, $this->product->fresh()->stock_quantity); // Restored!
    }

    public function test_cod_partial_mismatched_reconciliation(): void
    {
        // Expected amount is 2350.00. Courier remits only 2000.00.
        $this->actingAs($this->accountant)->post("/admin/orders/{$this->order->id}/reconcile-cod", [
            'collected_amount' => 2000.00,
            'courier_reference' => 'REMIT-PARTIAL-01',
            'notes' => 'Customer requested LKR 350 return adjustment',
        ]);

        $cod = $this->order->codCollection->fresh();
        $this->assertEquals(CodCollection::STATUS_MISMATCHED_UNDERPAID, $cod->reconciliation_status);
        $this->assertEquals('2000.00', (string) $cod->collected_amount);
    }

    public function test_phone_number_normalization_on_checkout(): void
    {
        $normalized1 = OrderService::normalizePhoneNumber('+94 77 123 4567');
        $this->assertEquals('0771234567', $normalized1);

        $normalized2 = OrderService::normalizePhoneNumber('77-123-4567');
        $this->assertEquals('0771234567', $normalized2);

        $normalized3 = OrderService::normalizePhoneNumber('0771234567');
        $this->assertEquals('0771234567', $normalized3);
    }
}

