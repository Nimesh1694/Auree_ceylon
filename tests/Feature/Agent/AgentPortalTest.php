<?php

namespace Tests\Feature\Agent;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\AgentProfile;
use App\Models\Order;
use App\Models\User;
use App\Services\AgentHierarchyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class AgentPortalTest extends TestCase
{
    use RefreshDatabase;

    private AgentHierarchyService $hierarchyService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hierarchyService = app(AgentHierarchyService::class);
    }

    public function test_active_agent_can_view_dashboard_with_independent_metrics(): void
    {
        $agent = User::factory()->create([
            'role' => UserRole::HIGH_LEVEL_AGENT,
            'status' => UserStatus::ACTIVE,
            'referral_code' => 'AGENT100',
        ]);
        AgentProfile::create([
            'user_id' => $agent->id,
            'nic' => '199012345678',
            'kyc_status' => 'verified',
            'wallet_balance' => 0,
            'total_earned' => 0,
            'total_withdrawn' => 0,
        ]);
        $this->hierarchyService->attachAgent($agent, null);

        // Own placed order
        Order::create([
            'order_number' => 'ORD-TEST-001',
            'agent_id' => $agent->id,
            'referral_code' => $agent->referral_code,
            'customer_name' => 'Cust 1',
            'customer_phone' => '0771234567',
            'shipping_address' => 'Addr 1',
            'district' => 'Colombo',
            'city' => 'Colombo',
            'subtotal' => 5000,
            'delivery_charge' => 350,
            'total_amount' => 5350,
            'payment_method' => 'cod',
            'status' => 'delivered',
            'tracking_token' => 'TOKEN_TEST_001',
            'placed_at' => now(),
        ]);

        // Customer referral order (not placed by agent)
        Order::create([
            'order_number' => 'ORD-TEST-002',
            'agent_id' => null,
            'referral_code' => $agent->referral_code,
            'customer_name' => 'Cust 2',
            'customer_phone' => '0771234568',
            'shipping_address' => 'Addr 2',
            'district' => 'Colombo',
            'city' => 'Colombo',
            'subtotal' => 3000,
            'delivery_charge' => 350,
            'total_amount' => 3350,
            'payment_method' => 'cod',
            'status' => 'confirmed',
            'tracking_token' => 'TOKEN_TEST_002',
            'placed_at' => now(),
        ]);

        $response = $this->actingAs($agent)->get(route('agent.dashboard'));
        $response->assertStatus(200);

        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Agent/Dashboard')
            ->where('stats.own_orders_count', 1)
            ->where('stats.referral_orders_count', 1)
            ->where('stats.kyc_status', 'verified')
        );
    }

    public function test_agent_can_update_permitted_profile_and_bank_details(): void
    {
        $agent = User::factory()->create([
            'role' => UserRole::LOW_LEVEL_AGENT,
            'status' => UserStatus::ACTIVE,
            'name' => 'Original Name',
            'phone' => '0771112233',
        ]);
        $profile = AgentProfile::create([
            'user_id' => $agent->id,
            'nic' => '199511112222',
            'address' => 'Old Address',
            'kyc_status' => 'pending',
            'wallet_balance' => 0,
            'total_earned' => 0,
            'total_withdrawn' => 0,
        ]);

        $response = $this->actingAs($agent)->put(route('agent.profile.update'), [
            'name' => 'Updated Agent Name',
            'phone' => '0779998877',
            'business_name' => 'Aura Salon',
            'address' => 'New Saloon Address, Kandy',
            'bank_name' => 'Sampath Bank',
            'bank_branch' => 'Kandy City',
            'bank_account_number' => '5556667778',
            'bank_account_name' => 'Updated Agent Name',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'id' => $agent->id,
            'name' => 'Updated Agent Name',
            'phone' => '0779998877',
        ]);

        $this->assertDatabaseHas('agent_profiles', [
            'user_id' => $agent->id,
            'business_name' => 'Aura Salon',
            'address' => 'New Saloon Address, Kandy',
            'bank_name' => 'Sampath Bank',
            'bank_account_number' => '5556667778',
            'kyc_status' => 'pending', // Remains unchanged
        ]);
    }

    public function test_agent_cannot_self_modify_role_or_kyc_status_via_profile_update(): void
    {
        $agent = User::factory()->create([
            'role' => UserRole::LOW_LEVEL_AGENT,
            'status' => UserStatus::ACTIVE,
        ]);
        AgentProfile::create([
            'user_id' => $agent->id,
            'nic' => '199511112222',
            'kyc_status' => 'pending',
            'wallet_balance' => 0,
            'total_earned' => 0,
            'total_withdrawn' => 0,
        ]);

        $this->actingAs($agent)->put(route('agent.profile.update'), [
            'name' => $agent->name,
            'phone' => '0772223344',
            'address' => 'Some address',
            'bank_name' => 'HNB',
            'bank_branch' => 'Colombo',
            'bank_account_number' => '1122334455',
            'bank_account_name' => 'Agent Name',
            'role' => 'high_level_agent', // Malicious attempt
            'kyc_status' => 'verified',    // Malicious attempt
            'wallet_balance' => 999999,    // Malicious attempt
        ]);

        $agent->refresh();
        $profile = $agent->agentProfile;

        $this->assertEquals(UserRole::LOW_LEVEL_AGENT, $agent->role);
        $this->assertEquals('pending', $profile->kyc_status);
        $this->assertEquals(0, (float) $profile->wallet_balance);
    }

    public function test_team_network_view_enforces_downline_privacy_boundaries(): void
    {
        $sponsor = User::factory()->create([
            'role' => UserRole::HIGH_LEVEL_AGENT,
            'status' => UserStatus::ACTIVE,
            'referral_code' => 'SPONSOR1',
        ]);
        $downlineAgent = User::factory()->create([
            'role' => UserRole::LOW_LEVEL_AGENT,
            'status' => UserStatus::ACTIVE,
            'parent_id' => $sponsor->id,
            'referral_code' => 'DOWNLINE1',
        ]);
        AgentProfile::create([
            'user_id' => $downlineAgent->id,
            'nic' => '199899998888',
            'bank_name' => 'Secret Bank',
            'bank_account_number' => '999888777666',
            'wallet_balance' => 45000,
            'total_earned' => 50000,
            'total_withdrawn' => 5000,
            'kyc_status' => 'verified',
        ]);

        $this->hierarchyService->attachAgent($sponsor, null);
        $this->hierarchyService->attachAgent($downlineAgent, $sponsor);

        $response = $this->actingAs($sponsor)->get(route('agent.team.index'));
        $response->assertStatus(200);

        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Agent/Team')
            ->where('directCount', 1)
            ->where('totalCount', 1)
            ->has('downline', 1, fn (AssertableInertia $downline) => $downline
                ->where('id', $downlineAgent->id)
                ->where('name', $downlineAgent->name)
                ->where('referral_code', 'DOWNLINE1')
                ->where('depth', 1)
                ->missing('nic')
                ->missing('bank_account_number')
                ->missing('wallet_balance')
                ->etc()
            )
        );
    }
}
