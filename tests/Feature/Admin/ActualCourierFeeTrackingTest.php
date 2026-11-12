<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Services\OrderStateMachineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActualCourierFeeTrackingTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $accountant;
    private User $operationsManager;
    private User $marketingManager;
    private User $customer;
    private User $agent;
    private Order $order;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::set('default_delivery_charge', 350.00, 'shipping', 'float');

        $this->owner = User::factory()->create(['role' => UserRole::OWNER]);
        $this->accountant = User::factory()->create(['role' => UserRole::ACCOUNTANT]);
        $this->operationsManager = User::factory()->create(['role' => UserRole::OPERATIONS_MANAGER]);
        $this->marketingManager = User::factory()->create(['role' => UserRole::MARKETING_MANAGER]);
        $this->customer = User::factory()->create(['role' => UserRole::CUSTOMER]);
        $this->agent = User::factory()->create(['role' => UserRole::HIGH_LEVEL_AGENT]);

        $this->product = Product::create([
            'name_en' => 'Rose Face Cream',
            'name_si' => 'ක්‍රීම්',
            'slug' => 'rose-face-cream',
            'sku' => 'AL-RFC-001',
            'price' => 2500.00,
            'cost_price' => 1200.00,
            'stock_quantity' => 40,
            'is_active' => true,
        ]);

        $this->order = Order::create([
            'order_number' => 'ORD-20261103-COURIER1',
            'customer_name' => 'Kusal Mendis',
            'customer_phone' => '0778899001',
            'shipping_address' => '78 Peradeniya Road',
            'district' => 'Kandy',
            'city' => 'Kandy',
            'subtotal' => 5000.00,
            'delivery_charge' => 350.00, // Customer delivery revenue
            'total_amount' => 5350.00,
            'payment_method' => 'cod',
            'status' => OrderStateMachineService::STATUS_PLACED,
            'tracking_token' => 'TRACK_TOKEN_COURIER_1',
            'placed_at' => now(),
        ]);
    }

    public function test_authorized_operations_manager_can_assign_courier_and_record_actual_courier_fee(): void
    {
        $response = $this->actingAs($this->operationsManager)
            ->post(route('admin.orders.courier', $this->order), [
                'courier_name' => 'Domex Logistics',
                'tracking_number' => 'DMX-99887766',
                'actual_courier_fee' => 380.00,
                'courier_invoice_reference' => 'INV-DMX-2026-NOV-01',
                'notes' => 'Parcel handed to Domex courier agent.',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('deliveries', [
            'order_id' => $this->order->id,
            'courier_name' => 'Domex Logistics',
            'tracking_number' => 'DMX-99887766',
            'actual_courier_fee' => 380.00,
            'courier_invoice_reference' => 'INV-DMX-2026-NOV-01',
            'courier_fee_recorded_by' => $this->operationsManager->id,
        ]);

        $delivery = $this->order->fresh()->delivery;
        $this->assertTrue($delivery->isFeeRecorded());
        $this->assertEquals(380.00, $delivery->getActualFee());
        $this->assertNotNull($delivery->courier_fee_recorded_at);
    }

    public function test_accountant_can_record_actual_courier_fee_independently(): void
    {
        // First courier is assigned with pending fee
        $this->order->delivery()->create([
            'courier_name' => 'Pronto Sri Lanka',
            'tracking_number' => 'PRN-12345',
            'status' => 'assigned',
            'actual_courier_fee' => null,
        ]);

        $response = $this->actingAs($this->accountant)
            ->post(route('admin.orders.courier-fee', $this->order), [
                'actual_courier_fee' => 410.50,
                'courier_invoice_reference' => 'INV-PRN-8812',
                'notes' => 'Weekly courier billing reconciliation verified.',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('deliveries', [
            'order_id' => $this->order->id,
            'actual_courier_fee' => 410.50,
            'courier_invoice_reference' => 'INV-PRN-8812',
            'courier_fee_recorded_by' => $this->accountant->id,
        ]);

        // Verify audit log entry
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'courier_fee_recorded',
            'auditable_type' => Order::class,
            'auditable_id' => $this->order->id,
            'user_id' => $this->accountant->id,
        ]);
    }

    public function test_customer_delivery_charge_and_actual_courier_fee_remain_strictly_separate(): void
    {
        $this->order->delivery()->create([
            'courier_name' => 'Certis Courier',
            'tracking_number' => 'CRT-554433',
            'status' => 'in_transit',
            'actual_courier_fee' => 450.00, // Actual expense paid to courier
            'courier_fee_recorded_by' => $this->owner->id,
            'courier_fee_recorded_at' => now(),
        ]);

        $freshOrder = $this->order->fresh();
        $delivery = $freshOrder->delivery;

        // Customer Delivery Revenue = 350.00
        $this->assertEquals(350.00, (float) $freshOrder->delivery_charge);

        // Actual Courier Logistics Expense = 450.00
        $this->assertEquals(450.00, (float) $delivery->actual_courier_fee);

        // They do not overwrite each other
        $this->assertNotEquals((float) $freshOrder->delivery_charge, (float) $delivery->actual_courier_fee);
    }

    public function test_unrecorded_courier_fee_remains_null_and_is_not_treated_as_zero(): void
    {
        $delivery = $this->order->delivery()->create([
            'courier_name' => 'Koombiyo Delivery',
            'tracking_number' => 'KMB-00192',
            'status' => 'assigned',
            'actual_courier_fee' => null, // Not yet invoiced
        ]);

        $this->assertNull($delivery->actual_courier_fee);
        $this->assertFalse($delivery->isFeeRecorded());
        $this->assertNull($delivery->getActualFee());
    }

    public function test_unauthorized_roles_and_customers_cannot_record_or_modify_courier_fee(): void
    {
        // Customer cannot access courier fee route
        $customerResponse = $this->actingAs($this->customer)
            ->post(route('admin.orders.courier-fee', $this->order), [
                'actual_courier_fee' => 100.00,
            ]);
        $customerResponse->assertForbidden();

        // Agent cannot access courier fee route
        $agentResponse = $this->actingAs($this->agent)
            ->post(route('admin.orders.courier-fee', $this->order), [
                'actual_courier_fee' => 100.00,
            ]);
        $agentResponse->assertForbidden();

        // Marketing Manager is unauthorized for logistics financial adjustments
        $marketingResponse = $this->actingAs($this->marketingManager)
            ->post(route('admin.orders.courier-fee', $this->order), [
                'actual_courier_fee' => 250.00,
            ]);
        $marketingResponse->assertForbidden();
    }

    public function test_actual_courier_fee_is_hidden_from_customer_tracking_and_agent_views(): void
    {
        $this->order->delivery()->create([
            'courier_name' => 'Domex',
            'tracking_number' => 'DMX-SECRET-1',
            'status' => 'delivered',
            'actual_courier_fee' => 420.00,
        ]);

        // 1. Serialization test (Model $hidden)
        $delivery = $this->order->fresh()->delivery;
        $array = $delivery->toArray();
        $json = json_encode($delivery);

        $this->assertArrayNotHasKey('actual_courier_fee', $array);
        $this->assertStringNotContainsString('"actual_courier_fee"', $json);

        // 2. Customer Order Tracking endpoint
        $trackResponse = $this->post(route('tracking.track'), [
            'order_number' => $this->order->order_number,
            'phone' => $this->order->customer_phone,
        ]);

        $trackResponse->assertOk();
        $orderData = $trackResponse->viewData('page')['props']['order'];
        $this->assertNotNull($orderData['delivery']);
        $this->assertArrayNotHasKey('actual_courier_fee', $orderData['delivery']);
    }
}

