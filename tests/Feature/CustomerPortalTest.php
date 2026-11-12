<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerPortalTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;
    private User $otherCustomer;
    private User $owner;
    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = User::factory()->create([
            'role' => UserRole::CUSTOMER,
            'status' => UserStatus::ACTIVE,
            'name' => 'Dilhara Perera',
            'email' => 'dilhara@example.com',
            'phone' => '0771234567',
        ]);

        $this->otherCustomer = User::factory()->create([
            'role' => UserRole::CUSTOMER,
            'status' => UserStatus::ACTIVE,
            'name' => 'Chamara Silva',
            'email' => 'chamara@example.com',
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
    }

    public function test_guest_users_are_redirected_to_login_when_accessing_dashboard(): void
    {
        $response = $this->get(route('dashboard'));

        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_customer_can_access_dashboard_and_see_customer_inertia_page(): void
    {
        $response = $this->actingAs($this->customer)->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Customer/Dashboard')
                ->where('customer.name', 'Dilhara Perera')
                ->where('customer.email', 'dilhara@example.com')
                ->where('customer.phone', '0771234567')
                ->has('statistics')
                ->has('recentOrders')
        );
    }

    public function test_management_staff_are_redirected_from_customer_dashboard_to_admin_dashboard(): void
    {
        $response = $this->actingAs($this->owner)->get(route('dashboard'));

        $response->assertRedirect(route('admin.dashboard'));
    }

    public function test_agents_are_redirected_from_customer_dashboard_to_agent_dashboard(): void
    {
        $response = $this->actingAs($this->agent)->get(route('dashboard'));

        $response->assertRedirect(route('agent.dashboard'));
    }

    public function test_customer_dashboard_displays_strictly_authenticated_customer_orders_and_statistics(): void
    {
        $product = Product::create([
            'name_en' => 'Rosewater Mist',
            'name_si' => 'රෝස මීදුම',
            'slug' => 'rosewater-mist',
            'price' => 2000.00,
            'stock_quantity' => 25,
            'is_active' => true,
        ]);

        // Customer A: 1 Placed, 1 Delivered, 1 Cancelled
        $order1 = Order::create([
            'order_number' => 'ORD-CUST-A-001',
            'customer_id' => $this->customer->id,
            'customer_name' => $this->customer->name,
            'customer_phone' => $this->customer->phone,
            'shipping_address' => 'No 12, Galle Road',
            'district' => 'Colombo',
            'city' => 'Colombo 03',
            'subtotal' => 2000.00,
            'delivery_charge' => 350.00,
            'total_amount' => 2350.00,
            'payment_method' => 'cod',
            'status' => 'placed',
            'tracking_token' => 'TOKEN_A_1',
            'placed_at' => now()->subDays(3),
        ]);

        OrderItem::create([
            'order_id' => $order1->id,
            'product_id' => $product->id,
            'product_name' => $product->name_en,
            'unit_price' => 2000.00,
            'quantity' => 1,
            'subtotal' => 2000.00,
        ]);

        $order2 = Order::create([
            'order_number' => 'ORD-CUST-A-002',
            'customer_id' => $this->customer->id,
            'customer_name' => $this->customer->name,
            'customer_phone' => $this->customer->phone,
            'shipping_address' => 'No 12, Galle Road',
            'district' => 'Colombo',
            'city' => 'Colombo 03',
            'subtotal' => 4000.00,
            'delivery_charge' => 350.00,
            'total_amount' => 4350.00,
            'payment_method' => 'bank_transfer',
            'status' => 'delivered',
            'tracking_token' => 'TOKEN_A_2',
            'placed_at' => now()->subDays(2),
        ]);

        OrderItem::create([
            'order_id' => $order2->id,
            'product_id' => $product->id,
            'product_name' => $product->name_en,
            'unit_price' => 2000.00,
            'quantity' => 2,
            'subtotal' => 4000.00,
        ]);

        $order3 = Order::create([
            'order_number' => 'ORD-CUST-A-003',
            'customer_id' => $this->customer->id,
            'customer_name' => $this->customer->name,
            'customer_phone' => $this->customer->phone,
            'shipping_address' => 'No 12, Galle Road',
            'district' => 'Colombo',
            'city' => 'Colombo 03',
            'subtotal' => 2000.00,
            'delivery_charge' => 350.00,
            'total_amount' => 2350.00,
            'payment_method' => 'cod',
            'status' => 'cancelled',
            'tracking_token' => 'TOKEN_A_3',
            'placed_at' => now()->subDay(),
        ]);

        // Customer B: 2 Orders (should NOT appear for Customer A)
        Order::create([
            'order_number' => 'ORD-CUST-B-999',
            'customer_id' => $this->otherCustomer->id,
            'customer_name' => $this->otherCustomer->name,
            'customer_phone' => $this->otherCustomer->phone,
            'shipping_address' => 'No 88, Kandy Road',
            'district' => 'Kandy',
            'city' => 'Peradeniya',
            'subtotal' => 10000.00,
            'delivery_charge' => 450.00,
            'total_amount' => 10450.00,
            'payment_method' => 'cod',
            'status' => 'delivered',
            'tracking_token' => 'TOKEN_B_1',
            'placed_at' => now(),
        ]);

        $response = $this->actingAs($this->customer)->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Customer/Dashboard')
                ->where('statistics.total_orders', 3)
                ->where('statistics.pending_orders', 1)
                ->where('statistics.completed_orders', 1)
                ->where('statistics.cancelled_orders', 1)
                ->where('statistics.total_spend', fn ($spend) => (float) $spend == 4350.0)
                ->has('recentOrders', 3)
                ->where('recentOrders.0.order_number', 'ORD-CUST-A-003')
                ->where('recentOrders.1.order_number', 'ORD-CUST-A-002')
                ->where('recentOrders.2.order_number', 'ORD-CUST-A-001')
        );
    }

    public function test_customer_dashboard_displays_empty_state_when_customer_has_zero_orders(): void
    {
        $response = $this->actingAs($this->customer)->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Customer/Dashboard')
                ->where('statistics.total_orders', 0)
                ->where('statistics.pending_orders', 0)
                ->where('statistics.completed_orders', 0)
                ->where('statistics.cancelled_orders', 0)
                ->where('statistics.total_spend', fn ($spend) => (float) $spend == 0.0)
                ->has('recentOrders', 0)
        );
    }

    public function test_customer_dashboard_does_not_leak_unauthorized_fields_in_props(): void
    {
        $response = $this->actingAs($this->customer)->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertInertia(function ($page) {
            $customerData = $page->toArray()['props']['customer'];

            $this->assertArrayNotHasKey('password', $customerData);
            $this->assertArrayNotHasKey('two_factor_secret', $customerData);
            $this->assertArrayNotHasKey('two_factor_recovery_codes', $customerData);
            $this->assertArrayNotHasKey('remember_token', $customerData);
        });
    }
}
