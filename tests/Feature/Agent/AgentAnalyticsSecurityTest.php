<?php

namespace Tests\Feature\Agent;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\AgentProfile;
use App\Models\User;
use App\Services\AgentHierarchyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentAnalyticsSecurityTest extends TestCase
{
    use RefreshDatabase;

    private User $hla;
    private User $lla;
    private User $adminManager;
    private User $owner;
    private User $customer;
    private AgentHierarchyService $hierarchyService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->hierarchyService = app(AgentHierarchyService::class);

        // High-Level Agent (HLA)
        $this->hla = User::factory()->create([
            'role' => UserRole::HIGH_LEVEL_AGENT,
            'status' => UserStatus::ACTIVE,
            'referral_code' => 'HLASEC001',
        ]);
        AgentProfile::create([
            'user_id' => $this->hla->id,
            'nic' => '199011112222',
            'kyc_status' => 'verified',
            'wallet_balance' => 0,
            'total_earned' => 0,
            'total_withdrawn' => 0,
        ]);
        $this->hierarchyService->attachAgent($this->hla, null);

        // Low-Level Agent (LLA) sponsored by HLA
        $this->lla = User::factory()->create([
            'role' => UserRole::LOW_LEVEL_AGENT,
            'status' => UserStatus::ACTIVE,
            'referral_code' => 'LLASEC002',
        ]);
        AgentProfile::create([
            'user_id' => $this->lla->id,
            'nic' => '199222223333',
            'kyc_status' => 'verified',
            'wallet_balance' => 0,
            'total_earned' => 0,
            'total_withdrawn' => 0,
        ]);
        $this->hierarchyService->attachAgent($this->lla, $this->hla);

        // Admin Agent Manager
        $this->adminManager = User::factory()->create([
            'status' => UserStatus::ACTIVE,
        ]);
        $this->adminManager->syncRole('agent_manager');

        // Business Owner
        $this->owner = User::factory()->create([
            'role' => UserRole::OWNER,
            'status' => UserStatus::ACTIVE,
            'protected_authority' => 'business',
        ]);

        // Customer
        $this->customer = User::factory()->create([
            'role' => 'customer',
            'status' => UserStatus::ACTIVE,
        ]);
    }

    public function test_unauthenticated_requests_are_redirected_to_login(): void
    {
        $this->get('/agent/analytics')->assertRedirect('/login');
        $this->get('/admin/agents/analytics')->assertRedirect('/login');
    }

    public function test_customer_cannot_access_agent_or_admin_analytics(): void
    {
        $this->actingAs($this->customer)->get('/agent/analytics')->assertForbidden();
        $this->actingAs($this->customer)->get('/admin/agents/analytics')->assertForbidden();
    }

    public function test_lla_cannot_access_team_downline_export(): void
    {
        // LLA has personal analytics access
        $this->actingAs($this->lla)->get('/agent/analytics')->assertOk();

        // But LLA is strictly forbidden from team export
        $response = $this->actingAs($this->lla)->get('/agent/analytics/export');
        $response->assertForbidden();
    }

    public function test_hla_can_access_team_downline_export(): void
    {
        $response = $this->actingAs($this->hla)->get('/agent/analytics/export?period=this_month');
        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_admin_manager_and_owner_can_access_admin_analytics(): void
    {
        $this->actingAs($this->adminManager)->get('/admin/agents/analytics')->assertOk();
        $this->actingAs($this->owner)->get('/admin/agents/analytics')->assertOk();

        // Show single agent
        $this->actingAs($this->adminManager)->get("/admin/agents/analytics/{$this->hla->id}")->assertOk();
        $this->actingAs($this->owner)->get("/admin/agents/analytics/{$this->lla->id}")->assertOk();
    }

    public function test_unauthorized_management_staff_without_explicit_permission_is_forbidden(): void
    {
        $marketingManager = User::factory()->create([
            'status' => UserStatus::ACTIVE,
        ]);
        $marketingManager->syncRole('marketing_manager');

        $response = $this->actingAs($marketingManager)->get('/admin/agents/analytics');
        $response->assertForbidden();
    }
}
