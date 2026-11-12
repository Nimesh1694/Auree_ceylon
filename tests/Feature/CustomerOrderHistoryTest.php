<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerOrderHistoryTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;
    private User $otherCustomer;
    private User $owner;
    private User $agent;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = User::factory()->create([
            'role' => UserRole::CUSTOMER,
            'status' => UserStatus::ACTIVE,
            'name' => 'Nadeesha Fernando',
            'email' => 'nadeesha@example.com',
            'phone' => '0771234567',
        ]);

        $this->otherCustomer = User::factory()->create([
            'role' => UserRole::CUSTOMER,
            'status' => UserStatus::ACTIVE,
            'name' => 'Ruwan Jayasinghe',
            'email' => 'ruwan@example.com',
            'phone' => '0719876543',
        ]);

        $this->owner = User::factory()->create([
            'role' => UserRole::OWNER,
            'status' => UserStatus::ACTIVE,
            'email' => 'owner@auraluxe.lk',
            'two_factor_confirmed_at' => now(),
        ]);

        $this->agent = User::factory()->create([
            'role' => UserRole::HIGH_LEVEL_AGENT,
            'status' => UserStatus::ACTIVE,
            'email' => 'agent@auraluxe.lk',
        ]);

        $this->product = Product::create([
            'name_en' => 'Rose Water Mist',
            'name_si' => 'රෝස වතුර මීදුම',
            'slug' => 'rose-water-mist',
            'price' => 2500.00,
            'stock_quantity' => 50,
            'is_active' => true,
        ]);
    }

    private function createCustomerOrder(User $user, array $overrides = []): Order
    {
        static $counter = 100;
        $counter++;

        $orderNumber = $overrides['order_number'] ?? "ORD-20261109-{$counter}";
        $status = $overrides['status'] ?? 'placed';

        $order = Order::create(array_merge([
            'order_number' => $orderNumber,
            'customer_id' => $user->id,
            'customer_name' => $user->name,
            'customer_phone' => $user->phone ?? '0771234567',
            'customer_email' => $user->email,
            'shipping_address' => 'No 45, Temple Road',
            'district' => 'Colombo',
            'city' => 'Colombo 07',
            'postal_code' => '00700',
            'subtotal' => 2500.00,
            'delivery_charge' => 350.00,
            'total_amount' => 2850.00,
            'payment_method' => 'cod',
            'status' => $status,
            'tracking_token' => "TOKEN_{$counter}_" . uniqid(),
            'notes' => 'Ring bell twice',
            'placed_at' => now()->subMinutes($counter),
        ], $overrides));

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_name' => $this->product->name_en,
            'product_sku' => 'SKU-ROSE-MIST',
            'unit_price' => 2500.00,
            'cost_price' => 1200.00,
            'quantity' => 1,
            'subtotal' => 2500.00,
        ]);

        return $order;
    }

    public function test_guest_cannot_access_customer_order_history(): void
    {
        $response = $this->get(route('customer.orders.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_customer_can_view_their_own_order_history(): void
    {
        $this->createCustomerOrder($this->customer, ['order_number' => 'ORD-MY-001']);

        $response = $this->actingAs($this->customer)->get(route('customer.orders.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Customer/Orders/Index')
                ->has('orders.data', 1)
                ->where('orders.data.0.order_number', 'ORD-MY-001')
        );
    }

    public function test_customer_cannot_see_orders_belonging_to_another_customer(): void
    {
        $this->createCustomerOrder($this->customer, ['order_number' => 'ORD-CUST-A-01']);
        $this->createCustomerOrder($this->otherCustomer, ['order_number' => 'ORD-CUST-B-99']);

        $response = $this->actingAs($this->customer)->get(route('customer.orders.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Customer/Orders/Index')
                ->has('orders.data', 1)
                ->where('orders.data.0.order_number', 'ORD-CUST-A-01')
        );
    }

    public function test_pagination_works_correctly_on_order_history(): void
    {
        // Create 15 orders for customer
        for ($i = 1; $i <= 15; $i++) {
            $this->createCustomerOrder($this->customer, [
                'order_number' => sprintf('ORD-PAGE-%03d', $i),
                'placed_at' => now()->subMinutes(100 - $i),
            ]);
        }

        // Page 1 should contain 10 orders
        $response1 = $this->actingAs($this->customer)->get(route('customer.orders.index'));
        $response1->assertStatus(200);
        $response1->assertInertia(fn ($page) =>
            $page->component('Customer/Orders/Index')
                ->where('orders.total', 15)
                ->where('orders.per_page', 10)
                ->where('orders.current_page', 1)
                ->has('orders.data', 10)
        );

        // Page 2 should contain 5 orders
        $response2 = $this->actingAs($this->customer)->get(route('customer.orders.index', ['page' => 2]));
        $response2->assertStatus(200);
        $response2->assertInertia(fn ($page) =>
            $page->component('Customer/Orders/Index')
                ->where('orders.total', 15)
                ->where('orders.current_page', 2)
                ->has('orders.data', 5)
        );
    }

    public function test_search_by_order_reference_filters_correctly(): void
    {
        $this->createCustomerOrder($this->customer, ['order_number' => 'ORD-ALPHA-100']);
        $this->createCustomerOrder($this->customer, ['order_number' => 'ORD-BETA-200']);

        $response = $this->actingAs($this->customer)->get(route('customer.orders.index', ['search' => 'ALPHA']));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Customer/Orders/Index')
                ->has('orders.data', 1)
                ->where('orders.data.0.order_number', 'ORD-ALPHA-100')
                ->where('filters.search', 'ALPHA')
        );
    }

    public function test_status_filtering_works_for_pending_delivered_and_cancelled(): void
    {
        $this->createCustomerOrder($this->customer, ['order_number' => 'ORD-PEND-01', 'status' => 'placed']);
        $this->createCustomerOrder($this->customer, ['order_number' => 'ORD-DELV-02', 'status' => 'delivered']);
        $this->createCustomerOrder($this->customer, ['order_number' => 'ORD-CANC-03', 'status' => 'cancelled']);

        // Filter: pending
        $responsePending = $this->actingAs($this->customer)->get(route('customer.orders.index', ['status' => 'pending']));
        $responsePending->assertStatus(200);
        $responsePending->assertInertia(fn ($page) =>
            $page->component('Customer/Orders/Index')
                ->has('orders.data', 1)
                ->where('orders.data.0.order_number', 'ORD-PEND-01')
        );

        // Filter: delivered
        $responseDelivered = $this->actingAs($this->customer)->get(route('customer.orders.index', ['status' => 'delivered']));
        $responseDelivered->assertStatus(200);
        $responseDelivered->assertInertia(fn ($page) =>
            $page->component('Customer/Orders/Index')
                ->has('orders.data', 1)
                ->where('orders.data.0.order_number', 'ORD-DELV-02')
        );

        // Filter: cancelled
        $responseCancelled = $this->actingAs($this->customer)->get(route('customer.orders.index', ['status' => 'cancelled']));
        $responseCancelled->assertStatus(200);
        $responseCancelled->assertInertia(fn ($page) =>
            $page->component('Customer/Orders/Index')
                ->has('orders.data', 1)
                ->where('orders.data.0.order_number', 'ORD-CANC-03')
        );
    }

    public function test_customer_can_view_their_own_order_details(): void
    {
        $order = $this->createCustomerOrder($this->customer, ['order_number' => 'ORD-DETAIL-777']);

        Delivery::create([
            'order_id' => $order->id,
            'courier_name' => 'Pronto Courier Services',
            'tracking_number' => 'PRONTO-987654321',
            'status' => 'in_transit',
            'actual_courier_fee' => 450.00,
            'dispatched_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($this->customer)->get(route('customer.orders.show', 'ORD-DETAIL-777'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Customer/Orders/Show')
                ->where('order.order_number', 'ORD-DETAIL-777')
                ->where('order.shipping.customer_name', 'Nadeesha Fernando')
                ->where('order.delivery.courier_name', 'Pronto Courier Services')
                ->where('order.delivery.tracking_number', 'PRONTO-987654321')
                ->has('order.items', 1)
                ->where('order.items.0.product_name', 'Rose Water Mist')
        );
    }

    public function test_another_customers_order_returns_404(): void
    {
        $order = $this->createCustomerOrder($this->otherCustomer, ['order_number' => 'ORD-SECRET-999']);

        $response = $this->actingAs($this->customer)->get(route('customer.orders.show', 'ORD-SECRET-999'));

        $response->assertStatus(404);
    }

    public function test_management_and_agent_accounts_cannot_access_customer_order_pages(): void
    {
        // Owner redirected from index & show
        $order = $this->createCustomerOrder($this->customer, ['order_number' => 'ORD-MGMT-TEST']);

        $responseOwnerIndex = $this->actingAs($this->owner)->get(route('customer.orders.index'));
        $responseOwnerIndex->assertRedirect(route('admin.dashboard'));

        $responseOwnerShow = $this->actingAs($this->owner)->get(route('customer.orders.show', 'ORD-MGMT-TEST'));
        $responseOwnerShow->assertRedirect(route('admin.dashboard'));

        // Agent redirected from index & show
        $responseAgentIndex = $this->actingAs($this->agent)->get(route('customer.orders.index'));
        $responseAgentIndex->assertRedirect(route('agent.dashboard'));

        $responseAgentShow = $this->actingAs($this->agent)->get(route('customer.orders.show', 'ORD-MGMT-TEST'));
        $responseAgentShow->assertRedirect(route('agent.dashboard'));
    }

    public function test_internal_cost_and_courier_expense_fields_are_not_exposed_in_details(): void
    {
        $order = $this->createCustomerOrder($this->customer, ['order_number' => 'ORD-LEAK-TEST']);

        Delivery::create([
            'order_id' => $order->id,
            'courier_name' => 'Koombiyo Delivery',
            'tracking_number' => 'KB-123456',
            'status' => 'delivered',
            'actual_courier_fee' => 520.00,
        ]);

        $response = $this->actingAs($this->customer)->get(route('customer.orders.show', 'ORD-LEAK-TEST'));

        $response->assertStatus(200);
        $response->assertInertia(function ($page) {
            $orderData = $page->toArray()['props']['order'];

            // Item-level cost price check
            foreach ($orderData['items'] as $item) {
                $this->assertArrayNotHasKey('cost_price', $item);
            }

            // Delivery-level actual courier fee check
            $this->assertNotNull($orderData['delivery']);
            $this->assertArrayNotHasKey('actual_courier_fee', $orderData['delivery']);

            // Top-level internal checks
            $this->assertArrayNotHasKey('agent_id', $orderData);
            $this->assertArrayNotHasKey('commissions', $orderData);
        });
    }

    public function test_missing_optional_delivery_and_notes_are_handled_safely(): void
    {
        $order = $this->createCustomerOrder($this->customer, [
            'order_number' => 'ORD-NULL-TEST',
            'notes' => null,
        ]);

        $response = $this->actingAs($this->customer)->get(route('customer.orders.show', 'ORD-NULL-TEST'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Customer/Orders/Show')
                ->where('order.order_number', 'ORD-NULL-TEST')
                ->where('order.delivery', null)
                ->where('order.notes', null)
        );
    }

    public function test_existing_phase_a_customer_portal_dashboard_tests_continue_to_pass(): void
    {
        $order = $this->createCustomerOrder($this->customer, ['order_number' => 'ORD-DASH-RECHECK']);

        $response = $this->actingAs($this->customer)->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Customer/Dashboard')
                ->where('statistics.total_orders', 1)
                ->has('recentOrders', 1)
                ->where('recentOrders.0.order_number', 'ORD-DASH-RECHECK')
        );
    }
}

