<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\AgentProfile;
use App\Models\HierarchyChangeRequest;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AgentManagerDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;
    protected User $agentManager;
    protected User $hla;
    protected User $lla;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->owner = User::where('role', UserRole::OWNER)->first()
            ?? User::factory()->create([
                'name' => 'Business Owner',
                'email' => 'owner@auraluxe.lk',
                'role' => UserRole::OWNER,
                'protected_authority' => 'business',
                'status' => UserStatus::ACTIVE,
            ]);

        $agentManagerRole = Role::where('name', 'agent_manager')->first();
        $this->agentManager = User::factory()->create([
            'name' => 'Sanduni Agent Manager',
            'email' => 'agentmanager@auraluxe.lk',
            'role' => UserRole::AGENT_MANAGER,
            'role_id' => $agentManagerRole?->id,
            'status' => UserStatus::ACTIVE,
        ]);

        $this->hla = User::factory()->create([
            'name' => 'Kasun HLA',
            'role' => UserRole::HIGH_LEVEL_AGENT,
            'status' => UserStatus::ACTIVE,
            'referral_code' => 'HLA-001',
        ]);
        AgentProfile::create([
            'user_id' => $this->hla->id,
            'nic' => '199012345678',
            'kyc_status' => 'verified',
            'bank_name' => 'Commercial Bank',
            'bank_account_number' => '8829102938',
            'bank_account_name' => 'Kasun HLA',
        ]);

        $this->lla = User::factory()->create([
            'name' => 'Dilini LLA',
            'role' => UserRole::LOW_LEVEL_AGENT,
            'status' => UserStatus::PENDING,
            'parent_id' => $this->hla->id,
            'referral_code' => 'LLA-001',
        ]);
        AgentProfile::create([
            'user_id' => $this->lla->id,
            'nic' => '199587654321',
            'kyc_status' => 'pending',
            'bank_name' => 'Bank of Ceylon',
            'bank_account_number' => '1234567890',
            'bank_account_name' => 'Dilini LLA',
        ]);
    }

    /**
     * 1. Agent Manager lands on dedicated Agent Operations Dashboard by default.
     */
    public function test_agent_manager_lands_on_agent_dashboard(): void
    {
        $response = $this->actingAs($this->agentManager)->get('/admin/dashboard');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/AgentManager/Dashboard')
            ->has('metrics')
            ->where('metrics.total_agents', 2)
            ->where('metrics.hla_count', 1)
            ->where('metrics.lla_count', 1)
            ->where('metrics.active_agents', 1)
            ->where('metrics.pending_agents', 1)
            ->where('metrics.verified_kyc', 1)
            ->where('metrics.pending_kyc', 1)
            ->has('registrationTrend')
            ->has('recentRegistrations')
            ->has('pendingQueues')
            ->has('topPerformers')
            ->where('isOwner', false)
            ->missing('executiveKpis')
            ->missing('inventoryOverview')
        );
    }

    /**
     * 2. Owner can switch to view agent network dashboard via query parameter.
     */
    public function test_owner_can_view_agent_network_dashboard_via_view_parameter(): void
    {
        $response = $this->actingAs($this->owner)->get('/admin/dashboard?view=agent_network');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/AgentManager/Dashboard')
            ->where('isOwner', true)
            ->has('metrics')
        );
    }

    /**
     * 3. Non-owner Agent Manager cannot view executive commercial dashboard.
     */
    public function test_agent_manager_cannot_view_executive_commercial_dashboard(): void
    {
        $response = $this->actingAs($this->agentManager)->get('/admin/dashboard?view=commercial');

        $response->assertStatus(200);
        // Agent Manager is barred from commercial dashboard and served their operational console
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/AgentManager/Dashboard')
            ->missing('executiveKpis')
        );
    }

    /**
     * 4. Agent Manager cannot access Owner-only financial contribution report.
     */
    public function test_agent_manager_cannot_access_owner_financial_contribution(): void
    {
        $response = $this->actingAs($this->agentManager)->get('/admin/finance/contribution');

        $response->assertStatus(403);
    }

    /**
     * 5. Agent Manager cannot approve payouts.
     */
    public function test_agent_manager_cannot_approve_payouts(): void
    {
        $payout = DB::table('payout_requests')->insertGetId([
            'reference_number' => 'PAY-TEST-001',
            'agent_id' => $this->hla->id,
            'amount' => 5000.00,
            'status' => 'pending',
            'bank_name' => 'Commercial Bank',
            'bank_branch' => 'Colombo',
            'bank_account_number' => '12345678',
            'bank_account_name' => 'Kasun HLA',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->agentManager)->post("/admin/payouts/{$payout}/approve", [
            'review_notes' => 'Unauthorized attempt by Agent Manager',
        ]);

        $response->assertStatus(403);
    }
}

