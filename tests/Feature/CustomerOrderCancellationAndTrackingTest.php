<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\AgentProfile;
use App\Models\CommissionLedgerEntry;
use App\Models\Delivery;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Services\AgentHierarchyService;
use App\Services\CommissionService;
use App\Services\OrderStateMachineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerOrderCancellationAndTrackingTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;
    private User $otherCustomer;
    private User $agent;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = User::factory()->create([
            'role' => UserRole::CUSTOMER,
            'status' => UserStatus::ACTIVE,
            'name' => 'Dilshan Perera',
            'email' => 'dilshan@example.com',
            'phone' => '0771122334',
        ]);

        $this->otherCustomer = User::factory()->create([
            'role' => UserRole::CUSTOMER,
            'status' => UserStatus::ACTIVE,
            'name' => 'Kasun Chamara',
            'email' => 'kasun@example.com',
            'phone' => '0779988776',
        ]);

        Setting::set('direct_agent_commission_percent', 25.0, 'commission', 'float');
        Setting::set('upline_agent_commission_percent', 10.0, 'commission', 'float');
        Setting::set('max_commission_percent_per_order', 35.0, 'commission', 'float');

        $this->agent = User::factory()->create([
            'role' => UserRole::HIGH_LEVEL_AGENT,
            'status' => UserStatus::ACTIVE,
            'email' => 'agent@auraluxe.lk',
            'referral_code' => 'HIGH-AGT-99',
        ]);

        AgentProfile::create([
            'user_id' => $this->agent->id,
            'wallet_balance' => 0,
            'total_earned' => 0,
            'total_withdrawn' => 0,
            'kyc_status' => 'approved',
        ]);
        app(AgentHierarchyService::class)->attachAgent($this->agent, null);

        $this->product = Product::create([
            'name_en' => 'Gold Serum 30ml',
            'name_si' => 'රන් සීරම් 30මිලී',
            'slug' => 'gold-serum-30ml',
            'price' => 4000.00,
            'stock_quantity' => 20,
            'is_active' => true,
        ]);
    }

    private function createOrderForCustomer(User $user, array $overrides = []): Order
    {
        static $orderId = 200;
        $orderId++;

        $orderNumber = $overrides['order_number'] ?? "ORD-CANCEL-{$orderId}";
        $status = $overrides['status'] ?? 'placed';
        $quantity = $overrides['quantity'] ?? 2;

        $order = Order::create(array_merge([
            'order_number' => $orderNumber,
            'customer_id' => $user->id,
            'customer_name' => $user->name,
            'customer_phone' => $user->phone ?? '0771122334',
            'customer_email' => $user->email,
            'shipping_address' => 'No 77, Havelock Road',
            'district' => 'Colombo',
            'city' => 'Colombo 05',
            'postal_code' => '00500',
            'subtotal' => 4000.00 * $quantity,
            'delivery_charge' => 350.00,
            'total_amount' => (4000.00 * $quantity) + 350.00,
            'payment_method' => 'cod',
            'status' => $status,
            'agent_id' => $overrides['agent_id'] ?? null,
            'tracking_token' => "TOKEN_{$orderId}_" . uniqid(),
            'notes' => 'Handle with care',
            'placed_at' => now(),
        ], $overrides));

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_name' => $this->product->name_en,
            'product_sku' => 'SKU-GOLD-30',
            'unit_price' => 4000.00,
            'cost_price' => 2000.00,
            'quantity' => $quantity,
            'subtotal' => 4000.00 * $quantity,
        ]);

        return $order;
    }

    public function test_customer_can_track_their_own_order_via_details_and_tracker(): void
    {
        $order = $this->createOrderForCustomer($this->customer, ['order_number' => 'ORD-TRK-001', 'status' => 'confirmed']);

        Delivery::create([
            'order_id' => $order->id,
            'courier_name' => 'Fardar Express',
            'tracking_number' => 'FARDAR-112233',
            'status' => 'pending',
        ]);

        // 1. Via customer order details page
        $responseShow = $this->actingAs($this->customer)->get(route('customer.orders.show', 'ORD-TRK-001'));
        $responseShow->assertStatus(200);
        $responseShow->assertInertia(fn ($page) =>
            $page->component('Customer/Orders/Show')
                ->where('order.order_number', 'ORD-TRK-001')
                ->where('order.status', 'confirmed')
                ->where('order.delivery.courier_name', 'Fardar Express')
                ->where('order.delivery.tracking_number', 'FARDAR-112233')
        );

        // 2. Via public tracker route with preloaded owned order
        $responseTracker = $this->actingAs($this->customer)->get('/track?order_number=ORD-TRK-001');
        $responseTracker->assertStatus(200);
        $responseTracker->assertInertia(fn ($page) =>
            $page->component('Tracking/Index')
                ->where('searched', true)
                ->where('order.order_number', 'ORD-TRK-001')
        );
    }

    public function test_customer_cannot_track_another_customers_order_without_phone(): void
    {
        $order = $this->createOrderForCustomer($this->otherCustomer, ['order_number' => 'ORD-OTHER-999']);

        // Direct show returns 404
        $responseShow = $this->actingAs($this->customer)->get(route('customer.orders.show', 'ORD-OTHER-999'));
        $responseShow->assertStatus(404);

        // Tracker does not pre-populate other customer's order
        $responseTracker = $this->actingAs($this->customer)->get('/track?order_number=ORD-OTHER-999');
        $responseTracker->assertStatus(200);
        $responseTracker->assertInertia(fn ($page) =>
            $page->component('Tracking/Index')
                ->where('searched', false)
                ->where('order', null)
        );
    }

    public function test_guest_tracking_requires_phone_match(): void
    {
        $order = $this->createOrderForCustomer($this->customer, ['order_number' => 'ORD-GUEST-TRK']);

        // Mismatched phone fails
        $responseFail = $this->post('/track', [
            'order_number' => 'ORD-GUEST-TRK',
            'phone' => '0770000000',
        ]);
        $responseFail->assertStatus(200);
        $responseFail->assertInertia(fn ($page) =>
            $page->component('Tracking/Index')
                ->where('searched', true)
                ->where('order', null)
        );

        // Correct phone succeeds
        $responseSuccess = $this->post('/track', [
            'order_number' => 'ORD-GUEST-TRK',
            'phone' => $this->customer->phone,
        ]);
        $responseSuccess->assertStatus(200);
        $responseSuccess->assertInertia(fn ($page) =>
            $page->component('Tracking/Index')
                ->where('searched', true)
                ->where('order.order_number', 'ORD-GUEST-TRK')
        );
    }

    public function test_eligible_customer_can_cancel_placed_order_restoring_stock(): void
    {
        $initialStock = $this->product->stock_quantity; // 20
        $order = $this->createOrderForCustomer($this->customer, [
            'order_number' => 'ORD-CANCEL-ME-01',
            'status' => 'placed',
            'quantity' => 3,
        ]);

        $response = $this->actingAs($this->customer)->post(route('customer.orders.cancel', 'ORD-CANCEL-ME-01'), [
            'reason' => 'Changed my mind',
        ]);

        $response->assertRedirect(route('customer.orders.show', 'ORD-CANCEL-ME-01'));
        $response->assertSessionHas('success');

        $this->assertEquals('cancelled', $order->fresh()->status);

        // Stock restored from 20 to 23
        $this->assertEquals($initialStock + 3, $this->product->fresh()->stock_quantity);

        // Inventory movement logged
        $this->assertDatabaseHas('inventory_movements', [
            'order_id' => $order->id,
            'movement_type' => InventoryMovement::TYPE_CANCELLATION_RESTOCK,
            'quantity' => 3,
        ]);
    }

    public function test_eligible_customer_can_cancel_confirmed_or_packed_order(): void
    {
        $orderConfirmed = $this->createOrderForCustomer($this->customer, [
            'order_number' => 'ORD-CONFIRMED-CAN',
            'status' => 'confirmed',
            'quantity' => 1,
        ]);

        $response1 = $this->actingAs($this->customer)->post(route('customer.orders.cancel', 'ORD-CONFIRMED-CAN'), [
            'reason' => 'Ordered by mistake',
        ]);
        $response1->assertRedirect();
        $this->assertEquals('cancelled', $orderConfirmed->fresh()->status);

        $orderPacked = $this->createOrderForCustomer($this->customer, [
            'order_number' => 'ORD-PACKED-CAN',
            'status' => 'packed',
            'quantity' => 1,
        ]);

        $response2 = $this->actingAs($this->customer)->post(route('customer.orders.cancel', 'ORD-PACKED-CAN'), [
            'reason' => 'Need to change items',
        ]);
        $response2->assertRedirect();
        $this->assertEquals('cancelled', $orderPacked->fresh()->status);
    }

    public function test_cancellation_reverses_agent_commissions_safely(): void
    {
        $order = $this->createOrderForCustomer($this->customer, [
            'order_number' => 'ORD-COMM-REVERSE',
            'status' => 'placed',
            'agent_id' => $this->agent->id,
            'referral_code' => $this->agent->referral_code,
            'quantity' => 1,
        ]);

        // Calculate and record real pending commission
        app(CommissionService::class)->calculateAndRecordPendingCommissions($order);

        $this->assertEquals(1, CommissionLedgerEntry::where('order_id', $order->id)->where('commission_type', '!=', CommissionLedgerEntry::TYPE_REVERSAL)->count());

        $this->actingAs($this->customer)->post(route('customer.orders.cancel', 'ORD-COMM-REVERSE'), [
            'reason' => 'Found alternative',
        ]);

        $this->assertEquals('cancelled', $order->fresh()->status);

        // Compensating reversal entry created
        $this->assertDatabaseHas('commission_ledger_entries', [
            'order_id' => $order->id,
            'commission_type' => CommissionLedgerEntry::TYPE_REVERSAL,
            'status' => CommissionLedgerEntry::STATUS_REVERSED,
        ]);
    }

    public function test_ineligible_order_cancellation_is_rejected(): void
    {
        $ineligibleStatuses = ['shipped', 'delivered', 'cash_collected', 'completed', 'refused', 'returned'];

        foreach ($ineligibleStatuses as $idx => $status) {
            $order = $this->createOrderForCustomer($this->customer, [
                'order_number' => "ORD-NO-CANCEL-{$idx}",
                'status' => $status,
            ]);

            $response = $this->actingAs($this->customer)->post(route('customer.orders.cancel', $order->order_number), [
                'reason' => 'Want to cancel anyway',
            ]);

            $response->assertRedirect(route('customer.orders.show', $order->order_number));
            $response->assertSessionHas('error');
            $this->assertEquals($status, $order->fresh()->status);
        }
    }

    public function test_customer_cannot_cancel_another_customers_order(): void
    {
        $order = $this->createOrderForCustomer($this->otherCustomer, [
            'order_number' => 'ORD-STEAL-CANCEL',
            'status' => 'placed',
        ]);

        $response = $this->actingAs($this->customer)->post(route('customer.orders.cancel', 'ORD-STEAL-CANCEL'), [
            'reason' => 'Malicious cancel',
        ]);

        $response->assertStatus(404);
        $this->assertEquals('placed', $order->fresh()->status);
    }

    public function test_duplicate_cancellation_is_safely_rejected(): void
    {
        $order = $this->createOrderForCustomer($this->customer, [
            'order_number' => 'ORD-DOUBLE-CAN',
            'status' => 'placed',
            'quantity' => 1,
        ]);

        // First cancel succeeds
        $res1 = $this->actingAs($this->customer)->post(route('customer.orders.cancel', 'ORD-DOUBLE-CAN'), [
            'reason' => 'First cancel',
        ]);
        $res1->assertSessionHas('success');
        $this->assertEquals('cancelled', $order->fresh()->status);

        $stockAfterFirstCancel = $this->product->fresh()->stock_quantity;

        // Second cancel attempt returns error and does NOT double-restore stock
        $res2 = $this->actingAs($this->customer)->post(route('customer.orders.cancel', 'ORD-DOUBLE-CAN'), [
            'reason' => 'Second duplicate cancel',
        ]);
        $res2->assertSessionHas('error');
        $this->assertEquals($stockAfterFirstCancel, $this->product->fresh()->stock_quantity);
    }

    public function test_empty_cancellation_reason_is_rejected(): void
    {
        $order = $this->createOrderForCustomer($this->customer, [
            'order_number' => 'ORD-EMPTY-REASON',
            'status' => 'placed',
        ]);

        $response = $this->actingAs($this->customer)->post(route('customer.orders.cancel', 'ORD-EMPTY-REASON'), [
            'reason' => '',
        ]);

        $response->assertSessionHasErrors(['reason']);
        $this->assertEquals('placed', $order->fresh()->status);
    }

    public function test_terminated_statuses_display_correctly_without_false_completion(): void
    {
        $order = $this->createOrderForCustomer($this->customer, [
            'order_number' => 'ORD-TERM-SHOW',
            'status' => 'cancelled',
        ]);

        $response = $this->actingAs($this->customer)->get(route('customer.orders.show', 'ORD-TERM-SHOW'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Customer/Orders/Show')
                ->where('order.status', 'cancelled')
                ->where('order.can_cancel', false)
                ->where('order.payment_status', 'Cancelled / Not Collected')
        );
    }
}
