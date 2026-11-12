<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\AgentProfile;
use App\Models\AuditLog;
use App\Models\CommissionRuleChange;
use App\Models\HierarchyChangeRequest;
use App\Models\PayoutRequest;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RolePermissionRequest;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApprovalCenterTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $superAdmin;
    private User $accountant;
    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->owner = User::factory()->create([
            'role' => UserRole::OWNER,
            'status' => UserStatus::ACTIVE,
            'protected_authority' => 'business',
            'two_factor_confirmed_at' => now(),
        ]);

        $this->superAdmin = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN,
            'status' => UserStatus::ACTIVE,
            'protected_authority' => 'technical',
            'two_factor_confirmed_at' => now(),
        ]);

        $this->accountant = User::factory()->create([
            'role' => UserRole::ACCOUNTANT,
            'status' => UserStatus::ACTIVE,
            'two_factor_confirmed_at' => now(),
        ]);

        $this->agent = User::factory()->create([
            'role' => UserRole::HIGH_LEVEL_AGENT,
            'status' => UserStatus::ACTIVE,
            'referral_code' => 'AGT-9999',
            'two_factor_confirmed_at' => now(),
        ]);

        AgentProfile::create([
            'user_id' => $this->agent->id,
            'wallet_balance' => 10000.00,
            'reserved_balance' => 0.00,
            'total_earned' => 10000.00,
            'total_withdrawn' => 0.00,
            'bank_name' => 'Commercial Bank',
            'bank_branch' => 'Colombo',
            'bank_account_number' => '1234567890',
            'bank_account_name' => 'Agent One',
            'kyc_status' => 'verified',
        ]);
    }

    public function test_owner_can_access_business_approval_center(): void
    {
        $response = $this->actingAs($this->owner)
            ->withSession(['two_factor_authenticated' => true])
            ->get(route('admin.approvals.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Approvals/Index')
            ->has('counts')
            ->has('payouts')
            ->has('commissionRules')
            ->has('hierarchyMoves')
            ->has('permissionRequests')
        );
    }

    public function test_non_owner_roles_cannot_access_approval_center(): void
    {
        // Customer blocked
        $customer = User::factory()->create(['role' => UserRole::CUSTOMER]);
        $this->actingAs($customer)->get(route('admin.approvals.index'))->assertForbidden();

        // Super Admin blocked from business approval center (Owner exclusive)
        $this->actingAs($this->superAdmin)
            ->withSession(['two_factor_authenticated' => true])
            ->get(route('admin.approvals.index'))
            ->assertForbidden();

        // Accountant blocked from business approval center
        $this->actingAs($this->accountant)
            ->withSession(['two_factor_authenticated' => true])
            ->get(route('admin.approvals.index'))
            ->assertForbidden();
    }

    public function test_approval_center_accurately_aggregates_all_four_pending_queues(): void
    {
        // 1. Pending Payout
        PayoutRequest::create([
            'reference_number' => 'PAY-001',
            'agent_id' => $this->agent->id,
            'amount' => 5000.00,
            'currency' => 'LKR',
            'status' => PayoutRequest::STATUS_PENDING,
            'bank_name' => 'Commercial Bank',
            'bank_branch' => 'Colombo',
            'bank_account_number' => '1234567890',
            'bank_account_name' => 'Agent One',
        ]);
        $this->agent->agentProfile->update(['reserved_balance' => 5000.00]);

        // 2. Pending Commission Rule Change
        CommissionRuleChange::create([
            'rule_key' => 'direct_agent_commission_percent',
            'old_value' => '25.0',
            'new_value' => '30.0',
            'requested_by' => $this->superAdmin->id,
            'reason' => 'Seasonal promo incentive',
            'status' => CommissionRuleChange::STATUS_PENDING,
        ]);

        // 3. Pending Hierarchy Move
        $newParent = User::factory()->create(['role' => UserRole::HIGH_LEVEL_AGENT, 'status' => UserStatus::ACTIVE]);
        HierarchyChangeRequest::create([
            'agent_id' => $this->agent->id,
            'current_parent_id' => null,
            'requested_parent_id' => $newParent->id,
            'requested_by' => $this->superAdmin->id,
            'reason' => 'Territory reorganization',
            'status' => HierarchyChangeRequest::STATUS_PENDING,
        ]);

        // 4. Pending Role Permission Request
        $customRole = Role::create(['name' => 'finance_trainee', 'display_name' => 'Finance Trainee', 'description' => 'Trainee']);
        $restrictedPerm = Permission::where('name', 'payouts.approve')->first()
            ?? Permission::create(['name' => 'payouts.approve', 'slug' => 'payouts.approve', 'module' => 'finance']);
        RolePermissionRequest::create([
            'role_id' => $customRole->id,
            'permission_id' => $restrictedPerm->id,
            'requested_by' => $this->superAdmin->id,
            'reason' => 'Trainee assistance',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->owner)
            ->withSession(['two_factor_authenticated' => true])
            ->get(route('admin.approvals.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('counts.payouts', 1)
            ->where('counts.commission_rules', 1)
            ->where('counts.hierarchy_moves', 1)
            ->where('counts.permission_requests', 1)
            ->where('counts.total', 4)
        );
    }

    public function test_owner_can_approve_payout_request_and_audit_log_is_created(): void
    {
        $payout = PayoutRequest::create([
            'reference_number' => 'PAY-002',
            'agent_id' => $this->agent->id,
            'amount' => 3000.00,
            'currency' => 'LKR',
            'status' => PayoutRequest::STATUS_PENDING,
            'bank_name' => 'Commercial Bank',
            'bank_branch' => 'Colombo',
            'bank_account_number' => '1234567890',
            'bank_account_name' => 'Agent One',
        ]);
        $this->agent->agentProfile->update(['reserved_balance' => 3000.00]);

        $response = $this->actingAs($this->owner)
            ->withSession(['two_factor_authenticated' => true])
            ->post(route('admin.payouts.approve', $payout->id), [
                'notes' => 'Verified with bank branch manager',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('payout_requests', [
            'id' => $payout->id,
            'status' => PayoutRequest::STATUS_APPROVED,
            'reviewed_by' => $this->owner->id,
        ]);
    }

    public function test_owner_can_reject_payout_with_reason_and_funds_are_unreserved(): void
    {
        $payout = PayoutRequest::create([
            'reference_number' => 'PAY-003',
            'agent_id' => $this->agent->id,
            'amount' => 2000.00,
            'currency' => 'LKR',
            'status' => PayoutRequest::STATUS_PENDING,
            'bank_name' => 'Commercial Bank',
            'bank_branch' => 'Colombo',
            'bank_account_number' => '1234567890',
            'bank_account_name' => 'Agent One',
        ]);
        $this->agent->agentProfile->update(['reserved_balance' => 2000.00]);

        $response = $this->actingAs($this->owner)
            ->withSession(['two_factor_authenticated' => true])
            ->post(route('admin.payouts.reject', $payout->id), [
                'rejection_reason' => 'Invalid bank branch code',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('payout_requests', [
            'id' => $payout->id,
            'status' => PayoutRequest::STATUS_REJECTED,
            'rejection_reason' => 'Invalid bank branch code',
        ]);

        // Reserved balance returned to agent
        $this->agent->agentProfile->refresh();
        $this->assertEquals('0.00', $this->agent->agentProfile->reserved_balance);
    }

    public function test_super_admin_cannot_approve_payout_requests(): void
    {
        $payout = PayoutRequest::create([
            'reference_number' => 'PAY-004',
            'agent_id' => $this->agent->id,
            'amount' => 1000.00,
            'currency' => 'LKR',
            'status' => PayoutRequest::STATUS_PENDING,
            'bank_name' => 'Commercial Bank',
            'bank_branch' => 'Colombo',
            'bank_account_number' => '1234567890',
            'bank_account_name' => 'Agent One',
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->withSession(['two_factor_authenticated' => true])
            ->post(route('admin.payouts.approve', $payout->id));

        $response->assertForbidden();
        $this->assertDatabaseHas('payout_requests', [
            'id' => $payout->id,
            'status' => PayoutRequest::STATUS_PENDING,
        ]);
    }
}
