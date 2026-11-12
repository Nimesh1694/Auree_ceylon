<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\AgentProfile;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\User;
use App\Models\UserClosure;
use App\Services\AgentHierarchyService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentPerformanceAndReportsTest extends TestCase
{
    use RefreshDatabase;

    private AgentHierarchyService $hierarchyService;

    private User $owner;
    private User $superAdmin;
    private User $agentManager;
    private User $accountant;
    private User $customer;

    private User $hla;
    private User $lla;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->hierarchyService = app(AgentHierarchyService::class);

        $this->owner = User::factory()->create([
            'name' => 'Owner Dilshan',
            'role' => UserRole::OWNER,
            'status' => UserStatus::ACTIVE,
            'protected_authority' => 'business',
        ]);

        $this->superAdmin = User::factory()->create([
            'name' => 'Super Admin Naveen',
            'role' => UserRole::SUPER_ADMIN,
            'status' => UserStatus::ACTIVE,
            'protected_authority' => 'technical',
        ]);

        $this->agentManager = User::factory()->create([
            'name' => 'Agent Manager User',
            'role' => UserRole::AGENT_MANAGER,
            'status' => UserStatus::ACTIVE,
        ]);

        $this->accountant = User::factory()->create([
            'name' => 'Accountant User',
            'role' => UserRole::ACCOUNTANT,
            'status' => UserStatus::ACTIVE,
        ]);

        $this->customer = User::factory()->create([
            'name' => 'Customer User',
            'role' => UserRole::CUSTOMER,
            'status' => UserStatus::ACTIVE,
        ]);

        // Agents
        $this->hla = User::factory()->create([
            'name' => 'Top HLA Leader',
            'role' => UserRole::HIGH_LEVEL_AGENT,
            'status' => UserStatus::ACTIVE,
            'parent_id' => null,
            'referral_code' => 'HLA-TOP',
        ]);
        AgentProfile::create([
            'user_id' => $this->hla->id,
            'nic' => '199012345678',
            'bank_name' => 'Commercial Bank',
            'bank_branch' => 'Colombo',
            'bank_account_number' => '1234567890',
            'bank_account_name' => 'Top HLA Leader',
        ]);
        $this->hierarchyService->attachAgent($this->hla, null);

        $this->lla = User::factory()->create([
            'name' => 'Active LLA Seller',
            'role' => UserRole::LOW_LEVEL_AGENT,
            'status' => UserStatus::ACTIVE,
            'parent_id' => $this->hla->id,
            'referral_code' => 'LLA-ACTIVE',
        ]);
        AgentProfile::create([
            'user_id' => $this->lla->id,
            'nic' => '199512345678',
            'bank_name' => 'Bank of Ceylon',
            'bank_branch' => 'Kandy',
            'bank_account_number' => '9876543210',
            'bank_account_name' => 'Active LLA Seller',
        ]);
        $this->hierarchyService->attachAgent($this->lla, $this->hla);

        // Create completed order for LLA (Rs. 15,000)
        Order::create([
            'order_number' => 'ORD-1001',
            'customer_id' => $this->customer->id,
            'customer_name' => 'Customer User',
            'customer_email' => 'customer@example.lk',
            'customer_phone' => '0771234567',
            'shipping_address' => '456 Test Street',
            'agent_id' => $this->lla->id,
            'referral_code' => $this->lla->referral_code,
            'subtotal' => '15000.00',
            'delivery_charge' => '350.00',
            'total_amount' => '15350.00',
            'currency' => 'LKR',
            'payment_method' => 'cod',
            'status' => 'delivered',
            'district' => 'Colombo',
            'city' => 'Colombo',
            'tracking_token' => 'TRK-1001',
        ]);

        // Create completed order for HLA (Rs. 5,000)
        Order::create([
            'order_number' => 'ORD-1002',
            'customer_id' => $this->customer->id,
            'customer_name' => 'Customer User',
            'customer_email' => 'customer@example.lk',
            'customer_phone' => '0771234567',
            'shipping_address' => '456 Test Street',
            'agent_id' => $this->hla->id,
            'referral_code' => $this->hla->referral_code,
            'subtotal' => '5000.00',
            'delivery_charge' => '350.00',
            'total_amount' => '5350.00',
            'currency' => 'LKR',
            'payment_method' => 'cod',
            'status' => 'completed',
            'district' => 'Colombo',
            'city' => 'Colombo',
            'tracking_token' => 'TRK-1002',
        ]);
    }

    public function test_agent_manager_and_owner_can_access_sales_leaderboard(): void
    {
        $response = $this->actingAs($this->agentManager)->get(route('admin.agents.performance'));
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Agents/Performance')
            ->has('leaderboard', 2)
            ->where('leaderboard.0.name', 'Active LLA Seller') // Rs. 15,000 rank 1
            ->where('leaderboard.1.name', 'Top HLA Leader')    // Rs. 5,000 rank 2
            ->has('metrics')
        );

        $this->actingAs($this->owner)->get(route('admin.agents.performance'))->assertOk();
    }

    public function test_sales_leaderboard_filters_by_role_and_period(): void
    {
        // Filter by High-Level Agent only
        $response = $this->actingAs($this->agentManager)->get(route('admin.agents.performance', ['role' => 'high_level_agent']));
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('leaderboard', 1)
            ->where('leaderboard.0.name', 'Top HLA Leader')
        );
    }

    public function test_activity_audit_trail_renders_and_sanitizes_pii(): void
    {
        // Log an agent action with sensitive fields
        AuditLog::log('agent_registered', $this->lla, null, [
            'name' => 'Active LLA Seller',
            'nic' => '199512345678',
            'bank_account_number' => '9876543210',
        ]);

        $response = $this->actingAs($this->agentManager)->get(route('admin.agents.activity'));
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Agents/Activity')
            ->has('logs.data')
            ->has('availableActions')
        );

        $content = $response->getContent();
        // Sensitive fields in payload must be masked to ********
        $this->assertStringNotContainsString('199512345678', $content);
        $this->assertStringNotContainsString('9876543210', $content);
    }

    public function test_export_center_renders_and_streams_csv_reports(): void
    {
        // 1. Export center page renders
        $response = $this->actingAs($this->agentManager)->get(route('admin.agents.reports'));
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Agents/Reports')
            ->has('stats')
        );

        // 2. Stream Directory CSV
        $dirExport = $this->actingAs($this->agentManager)->get(route('admin.agents.reports.export', ['type' => 'directory']));
        $dirExport->assertOk();
        $dirExport->assertHeader('content-type', 'text/csv; charset=UTF-8');

        // 3. Stream Performance CSV
        $perfExport = $this->actingAs($this->agentManager)->get(route('admin.agents.reports.export', ['type' => 'performance']));
        $perfExport->assertOk();
        $perfExport->assertHeader('content-type', 'text/csv; charset=UTF-8');

        // 4. Stream Hierarchy CSV
        $hierExport = $this->actingAs($this->agentManager)->get(route('admin.agents.reports.export', ['type' => 'hierarchy']));
        $hierExport->assertOk();
        $hierExport->assertHeader('content-type', 'text/csv; charset=UTF-8');

        // 5. Stream KYC CSV
        $kycExport = $this->actingAs($this->agentManager)->get(route('admin.agents.reports.export', ['type' => 'kyc']));
        $kycExport->assertOk();
        $kycExport->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_unauthorized_roles_receive_403_on_performance_and_reports(): void
    {
        $unauthorized = [$this->customer, $this->accountant];

        foreach ($unauthorized as $user) {
            $this->actingAs($user)->get(route('admin.agents.performance'))->assertForbidden();
            $this->actingAs($user)->get(route('admin.agents.activity'))->assertForbidden();
            $this->actingAs($user)->get(route('admin.agents.reports'))->assertForbidden();
            $this->actingAs($user)->get(route('admin.agents.reports.export', ['type' => 'directory']))->assertForbidden();
        }
    }
}

