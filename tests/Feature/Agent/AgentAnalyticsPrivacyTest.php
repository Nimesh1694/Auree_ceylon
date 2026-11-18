<?php

namespace Tests\Feature\Agent;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\AgentProfile;
use App\Models\Order;
use App\Models\User;
use App\Services\AgentAnalyticsService;
use App\Services\PeriodBoundaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class AgentAnalyticsPrivacyTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;
    private User $adminManager;
    private AgentAnalyticsService $analyticsService;
    private PeriodBoundaryService $boundaryService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->analyticsService = app(AgentAnalyticsService::class);
        $this->boundaryService = app(PeriodBoundaryService::class);

        $this->agent = User::factory()->create([
            'role' => UserRole::HIGH_LEVEL_AGENT,
            'status' => UserStatus::ACTIVE,
            'referral_code' => 'PRIVACYAGT1',
        ]);
        AgentProfile::create([
            'user_id' => $this->agent->id,
            'nic' => '199112345678',
            'kyc_status' => 'verified',
            'wallet_balance' => 0,
            'total_earned' => 0,
            'total_withdrawn' => 0,
        ]);

        $this->adminManager = User::factory()->create([
            'status' => UserStatus::ACTIVE,
        ]);
        $this->adminManager->syncRole('agent_manager');

        // Create an order with highly sensitive customer PII
        Order::create([
            'order_number' => 'ORD-PII-SECRET-001',
            'agent_id' => $this->agent->id,
            'customer_name' => 'Secret Customer Name',
            'customer_phone' => '+94779998877',
            'shipping_address' => 'Private Apartment 4B, Colombo 03',
            'district' => 'Colombo',
            'city' => 'Colombo',
            'subtotal' => 15000.00,
            'discount_amount' => 0.00,
            'delivery_charge' => 350.00,
            'total_amount' => 15350.00,
            'payment_method' => 'cod',
            'status' => 'delivered',
            'tracking_token' => 'TOK-PII-001',
            'placed_at' => now(),
        ]);
    }

    public function test_agent_portal_analytics_contains_zero_customer_pii_in_props(): void
    {
        $response = $this->actingAs($this->agent)
            ->get('/agent/analytics');

        $response->assertOk();

        // Ensure sensitive customer details are NOT present in the response content
        $content = $response->getContent();
        $this->assertStringNotContainsString('Secret Customer Name', $content);
        $this->assertStringNotContainsString('+94779998877', $content);
        $this->assertStringNotContainsString('Private Apartment 4B', $content);
        $this->assertStringNotContainsString('ORD-PII-SECRET-001', $content);
    }

    public function test_admin_analytics_leaderboard_contains_zero_customer_pii(): void
    {
        $response = $this->actingAs($this->adminManager)
            ->get('/admin/agents/analytics');

        $response->assertOk();

        $content = $response->getContent();
        $this->assertStringNotContainsString('Secret Customer Name', $content);
        $this->assertStringNotContainsString('+94779998877', $content);
        $this->assertStringNotContainsString('Private Apartment 4B', $content);
    }

    public function test_csv_export_contains_zero_customer_pii(): void
    {
        $response = $this->actingAs($this->adminManager)
            ->get('/admin/agents/analytics/export?period=this_month');

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        // Read streamed CSV output from callback
        $callback = $response->baseResponse->getCallback();
        ob_start();
        $callback();
        $csvContent = ob_get_clean();

        $this->assertStringNotContainsString('Secret Customer Name', $csvContent);
        $this->assertStringNotContainsString('+94779998877', $csvContent);
        $this->assertStringNotContainsString('Private Apartment 4B', $csvContent);

        // Verify CSV header contains only aggregated analytics fields
        $this->assertStringContainsString('Agent ID', $csvContent);
        $this->assertStringContainsString('Gross Product Sales (LKR)', $csvContent);
        $this->assertStringContainsString('Net Product Sales (LKR)', $csvContent);
    }

    public function test_guest_orders_are_tracked_and_never_conflated_with_registered_customers(): void
    {
        $boundaries = $this->boundaryService->resolvePeriodUtcBoundaries('this_month');

        // Customer registered order (has customer_id)
        $regCustomer = User::factory()->create(['role' => 'customer']);
        Order::create([
            'order_number' => 'ORD-REG-CUST-1',
            'agent_id' => $this->agent->id,
            'customer_id' => $regCustomer->id,
            'customer_name' => 'Registered Customer',
            'customer_phone' => '0771231234',
            'shipping_address' => 'Colombo',
            'district' => 'Colombo',
            'city' => 'Colombo',
            'subtotal' => 5000.00,
            'delivery_charge' => 350.00,
            'total_amount' => 5350.00,
            'payment_method' => 'cod',
            'status' => 'delivered',
            'tracking_token' => 'TOK-REG-01',
            'placed_at' => now(),
        ]);

        // Guest order (customer_id is null)
        Order::create([
            'order_number' => 'ORD-GUEST-CUST-1',
            'agent_id' => $this->agent->id,
            'customer_id' => null,
            'customer_name' => 'Guest Customer',
            'customer_phone' => '0779879876',
            'shipping_address' => 'Kandy',
            'district' => 'Kandy',
            'city' => 'Kandy',
            'subtotal' => 4000.00,
            'delivery_charge' => 350.00,
            'total_amount' => 4350.00,
            'payment_method' => 'cod',
            'status' => 'delivered',
            'tracking_token' => 'TOK-GUEST-01',
            'placed_at' => now(),
        ]);

        $analytics = $this->analyticsService->getPersonalAnalytics($this->agent, $boundaries);

        $this->assertEquals(1, $analytics['metrics']['registered_customer_count']);
        // 2 guest orders: the first order in setUp (null customer_id) + this second guest order
        $this->assertEquals(2, $analytics['metrics']['guest_order_count']);
    }
}
