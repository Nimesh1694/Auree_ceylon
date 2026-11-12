<?php

namespace Tests\Feature\Agent;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\AgentProfile;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderStateMachineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class AgentOrderPlacementTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->agent = User::factory()->create([
            'role' => UserRole::LOW_LEVEL_AGENT,
            'status' => UserStatus::ACTIVE,
            'referral_code' => 'AGT_SALES_99',
        ]);

        AgentProfile::create([
            'user_id' => $this->agent->id,
            'nic' => '199511112222',
            'kyc_status' => 'verified',
            'wallet_balance' => 0,
            'total_earned' => 0,
            'total_withdrawn' => 0,
        ]);

        $category = Category::create([
            'name_en' => 'Skincare',
            'name_si' => 'සම රැකවරණය',
            'slug' => 'skincare',
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'category_id' => $category->id,
            'name_en' => 'Botanical Hydrating Serum',
            'name_si' => 'ශාකසාර සේරම්',
            'slug' => 'botanical-hydrating-serum',
            'sku' => 'SKIN-SERUM-01',
            'price' => 4500.00,
            'stock_quantity' => 20,
            'low_stock_threshold' => 5,
            'is_active' => true,
            'is_featured' => true,
            'is_best_seller' => true,
            'is_new_arrival' => false,
        ]);
    }

    public function test_agent_can_view_order_placement_form(): void
    {
        $response = $this->actingAs($this->agent)->get(route('agent.orders.create'));
        $response->assertStatus(200);

        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Agent/Orders/Create')
            ->has('products', 1)
            ->has('districts')
        );
    }

    public function test_agent_can_place_cod_order_on_behalf_of_customer(): void
    {
        $orderData = [
            'customer_name' => 'Nimal Perera',
            'customer_phone' => '0773334444',
            'customer_email' => 'nimal@example.com',
            'shipping_address' => 'No 78, Kandy Road, Kiribathgoda',
            'district' => 'Gampaha',
            'city' => 'Kiribathgoda',
            'postal_code' => '11600',
            'notes' => 'Deliver before 5 PM',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 2,
                ],
            ],
        ];

        $response = $this->actingAs($this->agent)->post(route('agent.orders.store'), $orderData);
        $response->assertRedirect(route('agent.orders.index'));
        $response->assertSessionHas('success');

        // Check order attribution
        $this->assertDatabaseHas('orders', [
            'agent_id' => $this->agent->id,
            'referral_code' => $this->agent->referral_code,
            'customer_name' => 'Nimal Perera',
            'customer_phone' => '0773334444',
            'subtotal' => 9000.00, // 4500 * 2
            'total_amount' => 9350.00, // 9000 + 350 COD delivery
            'payment_method' => 'cod',
            'status' => OrderStateMachineService::STATUS_PLACED,
        ]);

        // Check inventory decrement
        $this->product->refresh();
        $this->assertEquals(18, $this->product->stock_quantity);

        // Check inventory movement
        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $this->product->id,
            'movement_type' => 'order_deduction',
            'quantity' => -2,
            'previous_stock' => 20,
            'new_stock' => 18,
        ]);
    }

    public function test_agent_orders_index_only_lists_orders_placed_by_the_agent(): void
    {
        // Order placed by this agent
        $ownOrder = Order::create([
            'order_number' => 'ORD-OWN-1',
            'agent_id' => $this->agent->id,
            'referral_code' => $this->agent->referral_code,
            'customer_name' => 'Customer A',
            'customer_phone' => '0771112222',
            'shipping_address' => 'Street A',
            'district' => 'Colombo',
            'city' => 'Colombo',
            'subtotal' => 4500,
            'delivery_charge' => 350,
            'total_amount' => 4850,
            'payment_method' => 'cod',
            'status' => 'placed',
            'tracking_token' => 'TOKEN_OWN_1',
            'placed_at' => now(),
        ]);

        // Order placed by another agent
        $otherAgent = User::factory()->create([
            'role' => UserRole::LOW_LEVEL_AGENT,
            'status' => UserStatus::ACTIVE,
        ]);
        $otherOrder = Order::create([
            'order_number' => 'ORD-OTHER-1',
            'agent_id' => $otherAgent->id,
            'referral_code' => $otherAgent->referral_code,
            'customer_name' => 'Customer B',
            'customer_phone' => '0772223333',
            'shipping_address' => 'Street B',
            'district' => 'Kandy',
            'city' => 'Kandy',
            'subtotal' => 4500,
            'delivery_charge' => 350,
            'total_amount' => 4850,
            'payment_method' => 'cod',
            'status' => 'placed',
            'tracking_token' => 'TOKEN_OTHER_1',
            'placed_at' => now(),
        ]);

        $response = $this->actingAs($this->agent)->get(route('agent.orders.index'));
        $response->assertStatus(200);

        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Agent/Orders/Index')
            ->where('orders.total', 1)
            ->where('orders.data.0.id', $ownOrder->id)
        );
    }
}
