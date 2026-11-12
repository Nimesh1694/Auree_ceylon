<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\AgentProfile;
use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentKycQueueTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $superAdmin;
    private User $itManager;
    private User $accountant;
    private User $marketingManager;
    private User $agentManager;
    private User $customer;
    private User $agent;
    private AgentProfile $agentProfile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->owner = User::factory()->create([
            'role' => UserRole::OWNER,
            'status' => UserStatus::ACTIVE,
        ]);

        $this->superAdmin = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN,
            'status' => UserStatus::ACTIVE,
        ]);

        $this->itManager = User::factory()->create([
            'role' => UserRole::IT_MANAGER,
            'status' => UserStatus::ACTIVE,
        ]);

        $this->accountant = User::factory()->create([
            'role' => UserRole::ACCOUNTANT,
            'status' => UserStatus::ACTIVE,
        ]);

        $this->marketingManager = User::factory()->create([
            'role' => UserRole::MARKETING_MANAGER,
            'status' => UserStatus::ACTIVE,
        ]);

        $this->agentManager = User::factory()->create([
            'role' => UserRole::AGENT_MANAGER,
            'status' => UserStatus::ACTIVE,
        ]);

        $this->customer = User::factory()->create([
            'role' => UserRole::CUSTOMER,
            'status' => UserStatus::ACTIVE,
        ]);

        $this->agent = User::factory()->create([
            'name' => 'Dilshan Test Agent',
            'email' => 'dilshan.agent@example.lk',
            'phone' => '0771239999',
            'referral_code' => 'AGT-DILSHAN',
            'role' => UserRole::HIGH_LEVEL_AGENT,
            'status' => UserStatus::ACTIVE,
        ]);

        $this->agentProfile = AgentProfile::create([
            'user_id' => $this->agent->id,
            'nic' => '199012345678',
            'address' => 'No 12, Galle Road, Colombo',
            'bank_name' => 'Commercial Bank',
            'bank_branch' => 'Bambalapitiya',
            'bank_account_number' => '8888777766',
            'bank_account_name' => 'Dilshan Test Agent',
            'kyc_status' => 'pending',
            'wallet_balance' => 0.00,
            'total_earned' => 0.00,
            'total_withdrawn' => 0.00,
        ]);
    }

    public function test_agent_manager_can_access_kyc_queue(): void
    {
        $response = $this->actingAs($this->agentManager)->get(route('admin.agents.kyc.queue'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Agents/KycQueue')
            ->has('agents')
            ->has('filters')
            ->has('stats')
            ->where('canManageKyc', true)
            ->where('canUnmaskPii', true)
        );
    }

    public function test_owner_and_super_admin_can_access_kyc_queue(): void
    {
        $resOwner = $this->actingAs($this->owner)->get(route('admin.agents.kyc.queue'));
        $resOwner->assertStatus(200);

        $resSuperAdmin = $this->actingAs($this->superAdmin)->get(route('admin.agents.kyc.queue'));
        $resSuperAdmin->assertStatus(200);
    }

    public function test_unauthorized_customer_cannot_access_kyc_queue(): void
    {
        $response = $this->actingAs($this->customer)->get(route('admin.agents.kyc.queue'));
        $response->assertStatus(403);
    }

    public function test_kyc_queue_filters_by_pending_verified_and_rejected(): void
    {
        // Verified agent
        $verifiedUser = User::factory()->create([
            'name' => 'Verified Agent',
            'role' => UserRole::HIGH_LEVEL_AGENT,
            'status' => UserStatus::ACTIVE,
        ]);
        AgentProfile::create([
            'user_id' => $verifiedUser->id,
            'nic' => '199122223333',
            'kyc_status' => 'verified',
            'wallet_balance' => 0,
            'total_earned' => 0,
            'total_withdrawn' => 0,
        ]);

        // Rejected agent
        $rejectedUser = User::factory()->create([
            'name' => 'Rejected Agent',
            'role' => UserRole::LOW_LEVEL_AGENT,
            'status' => UserStatus::ACTIVE,
        ]);
        AgentProfile::create([
            'user_id' => $rejectedUser->id,
            'nic' => '199233334444',
            'kyc_status' => 'rejected',
            'kyc_notes' => 'Blurry NIC photo',
            'wallet_balance' => 0,
            'total_earned' => 0,
            'total_withdrawn' => 0,
        ]);

        // 1. Pending (Default)
        $resPending = $this->actingAs($this->agentManager)->get(route('admin.agents.kyc.queue', ['status' => 'pending']));
        $resPending->assertStatus(200);
        $resPending->assertInertia(fn ($page) => $page
            ->where('agents.total', 1)
            ->where('agents.data.0.id', $this->agent->id)
        );

        // 2. Verified
        $resVerified = $this->actingAs($this->agentManager)->get(route('admin.agents.kyc.queue', ['status' => 'verified']));
        $resVerified->assertStatus(200);
        $resVerified->assertInertia(fn ($page) => $page
            ->where('agents.total', 1)
            ->where('agents.data.0.id', $verifiedUser->id)
        );

        // 3. Rejected
        $resRejected = $this->actingAs($this->agentManager)->get(route('admin.agents.kyc.queue', ['status' => 'rejected']));
        $resRejected->assertStatus(200);
        $resRejected->assertInertia(fn ($page) => $page
            ->where('agents.total', 1)
            ->where('agents.data.0.id', $rejectedUser->id)
        );

        // 4. All
        $resAll = $this->actingAs($this->agentManager)->get(route('admin.agents.kyc.queue', ['status' => 'all']));
        $resAll->assertStatus(200);
        $resAll->assertInertia(fn ($page) => $page
            ->where('agents.total', 3)
        );
    }

    public function test_kyc_queue_filters_by_tier_role(): void
    {
        // Low Level Agent
        $llaUser = User::factory()->create([
            'name' => 'LLA Agent',
            'role' => UserRole::LOW_LEVEL_AGENT,
            'status' => UserStatus::ACTIVE,
        ]);
        AgentProfile::create([
            'user_id' => $llaUser->id,
            'nic' => '199344445555',
            'kyc_status' => 'pending',
            'wallet_balance' => 0,
            'total_earned' => 0,
            'total_withdrawn' => 0,
        ]);

        // Filter: High-Level Agent
        $resHla = $this->actingAs($this->agentManager)->get(route('admin.agents.kyc.queue', [
            'status' => 'pending',
            'role' => 'high_level_agent',
        ]));
        $resHla->assertStatus(200);
        $resHla->assertInertia(fn ($page) => $page
            ->where('agents.total', 1)
            ->where('agents.data.0.id', $this->agent->id)
        );

        // Filter: Low-Level Agent
        $resLla = $this->actingAs($this->agentManager)->get(route('admin.agents.kyc.queue', [
            'status' => 'pending',
            'role' => 'low_level_agent',
        ]));
        $resLla->assertStatus(200);
        $resLla->assertInertia(fn ($page) => $page
            ->where('agents.total', 1)
            ->where('agents.data.0.id', $llaUser->id)
        );
    }

    public function test_nic_and_bank_account_remain_masked_by_default_in_kyc_queue_props(): void
    {
        $response = $this->actingAs($this->agentManager)->get(route('admin.agents.kyc.queue'));

        $response->assertStatus(200);

        // Assert sensitive PII values are masked in Inertia props
        $response->assertInertia(function ($page) {
            $agentData = $page->toArray()['props']['agents']['data'][0];
            $profile = $agentData['agent_profile'];

            // NIC must not expose unmasked value
            $this->assertNotEquals('199012345678', $profile['nic']);
            $this->assertStringContainsString('*', $profile['nic']);
            $this->assertStringEndsWith('5678', $profile['nic']);

            // Bank Account Number must not expose unmasked value
            $this->assertNotEquals('8888777766', $profile['bank_account_number']);
            $this->assertStringContainsString('*', $profile['bank_account_number']);
            $this->assertStringEndsWith('7766', $profile['bank_account_number']);
        });
    }

    public function test_owner_can_unmask_pii_and_access_kyc_documents(): void
    {
        // Owner unmask PII
        $response = $this->actingAs($this->owner)->postJson(
            route('admin.agents.unmask-pii', $this->agent->id),
            ['reason' => 'Executive compliance oversight']
        );

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'nic' => '199012345678',
            'bank_account_number' => '8888777766',
        ]);

        // Owner access document endpoint
        $docResponse = $this->actingAs($this->owner)->get(
            route('admin.agents.kyc-document', $this->agent->id)
        );
        $docResponse->assertStatus(200);
    }

    public function test_authorized_agent_manager_can_unmask_pii_and_creates_audit_log(): void
    {
        $response = $this->actingAs($this->agentManager)->postJson(
            route('admin.agents.unmask-pii', $this->agent->id),
            ['reason' => 'Verifying NIC against national database']
        );

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'agent_id' => $this->agent->id,
            'nic' => '199012345678',
            'bank_account_number' => '8888777766',
            'bank_name' => 'Commercial Bank',
            'bank_branch' => 'Bambalapitiya',
        ]);

        // Verify audit log creation
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'agent_pii_unmasked',
            'auditable_type' => User::class,
            'auditable_id' => $this->agent->id,
            'user_id' => $this->agentManager->id,
        ]);

        $audit = AuditLog::where('action', 'agent_pii_unmasked')->latest('id')->first();
        $this->assertNotNull($audit);
        $this->assertEquals('Verifying NIC against national database', $audit->new_values['reason']);
        $this->assertEquals($this->agentManager->name, $audit->new_values['actor_name']);
    }

    public function test_super_admin_without_permission_cannot_unmask_pii(): void
    {
        // Detach agents.kyc.verify from super_admin to test technical staff without explicit KYC permission
        $perm = Permission::where('name', 'agents.kyc.verify')->first();
        $superAdminRole = Role::where('name', 'super_admin')->first();
        if ($superAdminRole && $perm) {
            $superAdminRole->permissions()->detach($perm->id);
        }
        $this->superAdmin->unsetRelation('roleModel');

        // Super Admin without explicit agents.kyc.verify receives 403
        $response = $this->actingAs($this->superAdmin)->postJson(
            route('admin.agents.unmask-pii', $this->agent->id),
            ['reason' => 'Technical test']
        );

        $response->assertStatus(403);
        $response->assertJson(['error' => 'Security Violation: Unauthorized attempt to unmask sensitive personal identity data.']);

        $docResponse = $this->actingAs($this->superAdmin)->get(
            route('admin.agents.kyc-document', $this->agent->id)
        );
        $docResponse->assertStatus(403);
    }

    public function test_it_manager_without_permission_cannot_unmask_pii(): void
    {
        $itRole = Role::firstOrCreate(['name' => 'it_manager'], [
            'display_name' => 'IT Manager',
            'description' => 'IT Technical Manager',
            'is_system' => false,
            'is_protected' => false,
        ]);
        $this->itManager->update(['role_id' => $itRole->id]);
        $perm = Permission::where('name', 'agents.kyc.verify')->first();
        if ($perm) {
            $itRole->permissions()->detach($perm->id);
        }
        $this->itManager->unsetRelation('roleModel');

        $response = $this->actingAs($this->itManager)->postJson(
            route('admin.agents.unmask-pii', $this->agent->id),
            ['reason' => 'IT inspection']
        );

        $response->assertStatus(403);

        $docResponse = $this->actingAs($this->itManager)->get(
            route('admin.agents.kyc-document', $this->agent->id)
        );
        $docResponse->assertStatus(403);
    }

    public function test_accountant_without_permission_cannot_unmask_pii(): void
    {
        // Detach agents.kyc.verify from accountant to test accountant without explicit KYC permission
        $perm = Permission::where('name', 'agents.kyc.verify')->first();
        $accountantRole = Role::where('name', 'accountant')->first();
        if ($accountantRole && $perm) {
            $accountantRole->permissions()->detach($perm->id);
        }
        $this->accountant->unsetRelation('roleModel');

        $response = $this->actingAs($this->accountant)->postJson(
            route('admin.agents.unmask-pii', $this->agent->id),
            ['reason' => 'Accounting review']
        );

        $response->assertStatus(403);

        $docResponse = $this->actingAs($this->accountant)->get(
            route('admin.agents.kyc-document', $this->agent->id)
        );
        $docResponse->assertStatus(403);
    }

    public function test_super_admin_with_explicit_permission_can_unmask_pii(): void
    {
        // Explicitly grant agents.kyc.verify to Super Admin role
        $perm = Permission::where('name', 'agents.kyc.verify')->first();
        $superAdminRole = Role::where('name', 'super_admin')->first();
        $superAdminRole->permissions()->syncWithoutDetaching([$perm->id]);
        $this->superAdmin->unsetRelation('roleModel');

        $response = $this->actingAs($this->superAdmin)->postJson(
            route('admin.agents.unmask-pii', $this->agent->id),
            ['reason' => 'Technical compliance investigation']
        );

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'nic' => '199012345678',
        ]);
    }

    public function test_it_manager_with_explicit_permission_can_unmask_pii(): void
    {
        $itRole = Role::firstOrCreate(['name' => 'it_manager'], [
            'display_name' => 'IT Manager',
            'description' => 'IT Technical Manager',
            'is_system' => false,
            'is_protected' => false,
        ]);
        $this->itManager->update(['role_id' => $itRole->id]);
        $perm = Permission::where('name', 'agents.kyc.verify')->first();
        $itRole->permissions()->syncWithoutDetaching([$perm->id]);
        $this->itManager->unsetRelation('roleModel');

        $response = $this->actingAs($this->itManager)->postJson(
            route('admin.agents.unmask-pii', $this->agent->id),
            ['reason' => 'IT audit investigation']
        );

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'nic' => '199012345678',
        ]);
    }

    public function test_accountant_with_explicit_permission_can_unmask_pii(): void
    {
        $perm = Permission::where('name', 'agents.kyc.verify')->first();
        $accountantRole = Role::where('name', 'accountant')->first();
        $accountantRole->permissions()->syncWithoutDetaching([$perm->id]);
        $this->accountant->unsetRelation('roleModel');

        $response = $this->actingAs($this->accountant)->postJson(
            route('admin.agents.unmask-pii', $this->agent->id),
            ['reason' => 'Bank detail reconciliation']
        );

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'nic' => '199012345678',
            'bank_account_number' => '8888777766',
        ]);
    }

    public function test_marketing_manager_without_permission_cannot_unmask_pii(): void
    {
        $response = $this->actingAs($this->marketingManager)->postJson(
            route('admin.agents.unmask-pii', $this->agent->id),
            ['reason' => 'Marketing inquiry']
        );

        $response->assertStatus(403);
    }

    public function test_unauthorized_user_cannot_unmask_pii(): void
    {
        $response = $this->actingAs($this->customer)->postJson(
            route('admin.agents.unmask-pii', $this->agent->id),
            ['reason' => 'Intruder request']
        );

        $response->assertStatus(403);

        $this->assertDatabaseMissing('audit_logs', [
            'action' => 'agent_pii_unmasked',
            'user_id' => $this->customer->id,
        ]);
    }

    public function test_agent_manager_can_verify_agent_kyc(): void
    {
        $response = $this->actingAs($this->agentManager)->post(
            route('admin.agents.kyc.update', $this->agent->id),
            [
                'kyc_status' => 'verified',
                'kyc_notes' => 'NIC and Bank details match official records.',
            ]
        );

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->agentProfile->refresh();
        $this->assertEquals('verified', $this->agentProfile->kyc_status);
        $this->assertEquals('NIC and Bank details match official records.', $this->agentProfile->kyc_notes);

        // Audit log created
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'agent_kyc_updated',
            'auditable_type' => User::class,
            'auditable_id' => $this->agent->id,
            'user_id' => $this->agentManager->id,
        ]);
    }

    public function test_rejection_strictly_requires_mandatory_reason(): void
    {
        // Attempt rejection without a reason
        $response = $this->actingAs($this->agentManager)->post(
            route('admin.agents.kyc.update', $this->agent->id),
            [
                'kyc_status' => 'rejected',
                'kyc_notes' => '',
            ]
        );

        $response->assertSessionHasErrors('kyc_notes');

        $this->agentProfile->refresh();
        $this->assertEquals('pending', $this->agentProfile->kyc_status);
    }

    public function test_agent_manager_can_reject_kyc_with_valid_reason(): void
    {
        $response = $this->actingAs($this->agentManager)->post(
            route('admin.agents.kyc.update', $this->agent->id),
            [
                'kyc_status' => 'rejected',
                'kyc_notes' => 'Bank account holder name differs from registered agent name.',
            ]
        );

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->agentProfile->refresh();
        $this->assertEquals('rejected', $this->agentProfile->kyc_status);
        $this->assertEquals('Bank account holder name differs from registered agent name.', $this->agentProfile->kyc_notes);
    }

    public function test_unauthorized_customer_cannot_verify_or_reject_kyc(): void
    {
        $response = $this->actingAs($this->customer)->post(
            route('admin.agents.kyc.update', $this->agent->id),
            [
                'kyc_status' => 'verified',
            ]
        );

        $response->assertStatus(403);
    }

    public function test_unauthorized_customer_cannot_access_kyc_document(): void
    {
        $response = $this->actingAs($this->customer)->get(
            route('admin.agents.kyc-document', $this->agent->id)
        );

        $response->assertStatus(403);
    }

    public function test_authorized_user_accesses_kyc_document_endpoint_safely(): void
    {
        $response = $this->actingAs($this->agentManager)->get(
            route('admin.agents.kyc-document', $this->agent->id)
        );

        $response->assertStatus(200);
        $response->assertJson([
            'has_document' => false,
            'nic_recorded' => true,
        ]);
    }
}
