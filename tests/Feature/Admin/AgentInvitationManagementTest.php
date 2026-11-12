<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\AgentInvitation;
use App\Models\AgentProfile;
use App\Models\User;
use App\Services\AgentInvitationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentInvitationManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $superAdmin;
    private User $agentManager;
    private User $customer;
    private AgentInvitationService $invitationService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->invitationService = app(AgentInvitationService::class);

        $this->owner = User::factory()->create([
            'role' => UserRole::OWNER,
            'status' => UserStatus::ACTIVE,
        ]);

        $this->superAdmin = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN,
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
    }

    public function test_agent_manager_can_access_invitations_page(): void
    {
        $response = $this->actingAs($this->agentManager)->get(route('admin.agents.invitations.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Agents/Invitations')
            ->has('invitations')
            ->has('stats')
            ->has('canCreateHla')
            ->where('canCreateHla', true)
        );
    }

    public function test_owner_and_super_admin_can_access_invitations_page(): void
    {
        $responseOwner = $this->actingAs($this->owner)->get(route('admin.agents.invitations.index'));
        $responseOwner->assertStatus(200);

        $responseSuperAdmin = $this->actingAs($this->superAdmin)->get(route('admin.agents.invitations.index'));
        $responseSuperAdmin->assertStatus(200);
    }

    public function test_unauthorized_user_cannot_access_invitations_page(): void
    {
        $response = $this->actingAs($this->customer)->get(route('admin.agents.invitations.index'));
        $response->assertStatus(403);
    }

    public function test_agent_manager_can_generate_hla_invitation(): void
    {
        $payload = [
            'candidate_name' => 'Amal Perera',
            'candidate_email' => 'amal@example.lk',
            'candidate_phone' => '0771234567',
            'expiry_days' => 7,
        ];

        $response = $this->actingAs($this->agentManager)
            ->post(route('admin.agents.invitations.store'), $payload);

        $response->assertRedirect(route('admin.agents.invitations.index'));
        $response->assertSessionHas('success');
        $response->assertSessionHas('generated_link');
        $response->assertSessionHas('token');

        // Verify Database State
        $this->assertDatabaseHas('agent_invitations', [
            'invitation_type' => AgentInvitation::TYPE_HIGH_LEVEL_AGENT,
            'created_by' => $this->agentManager->id,
            'parent_agent_id' => null, // Strictly company direct; Agent Manager is NOT the parent
            'candidate_name' => 'Amal Perera',
            'candidate_email' => 'amal@example.lk',
            'candidate_phone' => '0771234567',
            'status' => AgentInvitation::STATUS_PENDING,
        ]);

        $invitation = AgentInvitation::where('candidate_email', 'amal@example.lk')->first();
        $this->assertNotNull($invitation);
        $this->assertTrue($invitation->expires_at->isFuture());
    }

    public function test_unauthorized_user_cannot_create_hla_invitation(): void
    {
        $response = $this->actingAs($this->customer)->post(route('admin.agents.invitations.store'), [
            'candidate_name' => 'Intruder Candidate',
        ]);

        $response->assertStatus(403);
    }

    public function test_invitations_can_be_filtered_by_status_and_type(): void
    {
        // 1. Pending HLA
        $resHla = $this->invitationService->createInvitation(
            creator: $this->agentManager,
            type: AgentInvitation::TYPE_HIGH_LEVEL_AGENT,
            candidateData: ['name' => 'Pending HLA Candidate']
        );

        // 2. Revoked HLA
        $resRevoked = $this->invitationService->createInvitation(
            creator: $this->agentManager,
            type: AgentInvitation::TYPE_HIGH_LEVEL_AGENT,
            candidateData: ['name' => 'Revoked Candidate']
        );
        $resRevoked['invitation']->update(['status' => AgentInvitation::STATUS_REVOKED]);

        // 3. Expired HLA
        $resExpired = $this->invitationService->createInvitation(
            creator: $this->agentManager,
            type: AgentInvitation::TYPE_HIGH_LEVEL_AGENT,
            candidateData: ['name' => 'Expired Candidate']
        );
        $resExpired['invitation']->update([
            'expires_at' => Carbon::now()->subDay(),
        ]);

        // Filter: Pending
        $responsePending = $this->actingAs($this->agentManager)->get(route('admin.agents.invitations.index', ['status' => 'pending']));
        $responsePending->assertStatus(200);
        $responsePending->assertInertia(fn ($page) => $page
            ->where('invitations.total', 1)
        );

        // Filter: Revoked
        $responseRevoked = $this->actingAs($this->agentManager)->get(route('admin.agents.invitations.index', ['status' => 'revoked']));
        $responseRevoked->assertStatus(200);
        $responseRevoked->assertInertia(fn ($page) => $page
            ->where('invitations.total', 1)
        );

        // Filter: Expired
        $responseExpired = $this->actingAs($this->agentManager)->get(route('admin.agents.invitations.index', ['status' => 'expired']));
        $responseExpired->assertStatus(200);
        $responseExpired->assertInertia(fn ($page) => $page
            ->where('invitations.total', 1)
        );
    }

    public function test_agent_manager_can_revoke_pending_invitation(): void
    {
        $result = $this->invitationService->createInvitation(
            creator: $this->agentManager,
            type: AgentInvitation::TYPE_HIGH_LEVEL_AGENT,
            candidateData: ['name' => 'To Be Revoked']
        );
        $invitation = $result['invitation'];

        $this->assertEquals(AgentInvitation::STATUS_PENDING, $invitation->status);

        $response = $this->actingAs($this->agentManager)
            ->post(route('admin.agents.invitations.revoke', $invitation->id));

        $response->assertSessionHas('success');
        $invitation->refresh();
        $this->assertEquals(AgentInvitation::STATUS_REVOKED, $invitation->status);
    }

    public function test_candidate_can_view_registration_form_with_valid_hla_token(): void
    {
        $result = $this->invitationService->createInvitation(
            creator: $this->agentManager,
            type: AgentInvitation::TYPE_HIGH_LEVEL_AGENT,
            candidateData: ['name' => 'Nimal Silva']
        );

        $token = $result['token'];

        $response = $this->get("/agent/register?token={$token}");
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Agent/Register')
            ->where('token', $token)
            ->where('invitation.type', AgentInvitation::TYPE_HIGH_LEVEL_AGENT)
            ->where('invitation.candidate_name', 'Nimal Silva')
            ->where('sponsorName', 'Aura Luxe Company (Executive Management)')
            ->where('isInvalidToken', false)
        );
    }

    public function test_revoked_invitation_shows_invalid_token_warning(): void
    {
        $result = $this->invitationService->createInvitation(
            creator: $this->agentManager,
            type: AgentInvitation::TYPE_HIGH_LEVEL_AGENT,
            candidateData: ['name' => 'Revoked Token Test']
        );
        $token = $result['token'];
        $result['invitation']->update(['status' => AgentInvitation::STATUS_REVOKED]);

        $response = $this->get("/agent/register?token={$token}");
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('isInvalidToken', true)
        );
    }

    public function test_candidate_can_register_via_hla_invitation_and_becomes_company_direct(): void
    {
        $result = $this->invitationService->createInvitation(
            creator: $this->agentManager,
            type: AgentInvitation::TYPE_HIGH_LEVEL_AGENT,
            candidateData: [
                'name' => 'Sunil Jayasuriya',
                'email' => 'sunil@example.lk',
                'phone' => '0779998877',
            ]
        );
        $token = $result['token'];
        $invitation = $result['invitation'];

        $registrationPayload = [
            'token' => $token,
            'name' => 'Sunil Jayasuriya',
            'email' => 'sunil@example.lk',
            'phone' => '0779998877',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'nic' => '199012345678',
            'address' => 'No 45, Flower Road, Colombo 07',
            'bank_name' => 'Commercial Bank',
            'bank_branch' => 'Kollupitiya',
            'bank_account_number' => '1234567890',
            'bank_account_name' => 'Sunil Jayasuriya',
        ];

        $response = $this->post('/agent/register', $registrationPayload);
        $response->assertRedirect(route('login'));
        $response->assertSessionHas('success');

        // Assert user created with high_level_agent role and parent_id = null
        $newUser = User::where('email', 'sunil@example.lk')->first();
        $this->assertNotNull($newUser);
        $this->assertEquals(UserRole::HIGH_LEVEL_AGENT, $newUser->role);
        $this->assertEquals(UserStatus::PENDING, $newUser->status);
        $this->assertNull($newUser->parent_id); // Direct company relationship; NOT under Agent Manager

        // Assert invitation marked accepted
        $invitation->refresh();
        $this->assertEquals(AgentInvitation::STATUS_ACCEPTED, $invitation->status);
        $this->assertEquals($newUser->id, $invitation->used_by);
        $this->assertNotNull($invitation->used_at);
    }

    public function test_accepted_invitation_cannot_be_reused(): void
    {
        $result = $this->invitationService->createInvitation(
            creator: $this->agentManager,
            type: AgentInvitation::TYPE_HIGH_LEVEL_AGENT,
            candidateData: ['name' => 'Single Use Test']
        );
        $token = $result['token'];

        // First registration
        $this->post('/agent/register', [
            'token' => $token,
            'name' => 'First Registrant',
            'email' => 'first@example.lk',
            'phone' => '0771112233',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'nic' => '199011112222',
            'address' => 'First Address',
            'bank_name' => 'Commercial Bank',
            'bank_branch' => 'Colombo',
            'bank_account_number' => '1111222233',
            'bank_account_name' => 'First Registrant',
        ])->assertRedirect(route('login'));

        // Second registration attempt using identical token
        $responseSecond = $this->post('/agent/register', [
            'token' => $token,
            'name' => 'Second Registrant',
            'email' => 'second@example.lk',
            'phone' => '0774445566',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'nic' => '199033334444',
            'address' => 'Second Address',
            'bank_name' => 'Commercial Bank',
            'bank_branch' => 'Colombo',
            'bank_account_number' => '3333444455',
            'bank_account_name' => 'Second Registrant',
        ]);

        $responseSecond->assertSessionHasErrors('token');
        $this->assertDatabaseMissing('users', ['email' => 'second@example.lk']);
    }

    public function test_hla_can_create_lla_invitation_with_themselves_as_parent(): void
    {
        $hla = User::factory()->create([
            'role' => UserRole::HIGH_LEVEL_AGENT,
            'status' => UserStatus::ACTIVE,
        ]);

        $result = $this->invitationService->createInvitation(
            creator: $hla,
            type: AgentInvitation::TYPE_LOW_LEVEL_AGENT,
            candidateData: ['name' => 'LLA Candidate']
        );

        $invitation = $result['invitation'];
        $this->assertEquals(AgentInvitation::TYPE_LOW_LEVEL_AGENT, $invitation->invitation_type);
        $this->assertEquals($hla->id, $invitation->parent_agent_id); // Low-Level Agent is bound to inviting HLA

        // Register candidate
        $this->post('/agent/register', [
            'token' => $result['token'],
            'name' => 'Downline Agent',
            'email' => 'downline@example.lk',
            'phone' => '0777778899',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'nic' => '199277788899',
            'address' => 'Kandy Road',
            'bank_name' => 'BOC',
            'bank_branch' => 'Kandy',
            'bank_account_number' => '9988776655',
            'bank_account_name' => 'Downline Agent',
        ])->assertRedirect(route('login'));

        $downlineUser = User::where('email', 'downline@example.lk')->first();
        $this->assertNotNull($downlineUser);
        $this->assertEquals(UserRole::LOW_LEVEL_AGENT, $downlineUser->role);
        $this->assertEquals($hla->id, $downlineUser->parent_id); // Bound to HLA sponsor
    }

    public function test_concurrent_revocation_race_condition_aborts_registration_atomically(): void
    {
        $result = $this->invitationService->createInvitation(
            creator: $this->agentManager,
            type: AgentInvitation::TYPE_HIGH_LEVEL_AGENT,
            candidateData: ['name' => 'Race Condition Candidate']
        );
        $token = $result['token'];
        $invitation = $result['invitation'];

        // Simulate manager revoking the invitation concurrently right as candidate submits
        $this->invitationService->revokeInvitation($invitation, $this->agentManager);
        $this->assertEquals(AgentInvitation::STATUS_REVOKED, $invitation->fresh()->status);

        // Candidate attempts to register with token that was revoked
        $response = $this->post('/agent/register', [
            'token' => $token,
            'name' => 'Raced Candidate',
            'email' => 'raced@example.lk',
            'phone' => '0770001122',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'nic' => '199500001111',
            'address' => 'Galle Road',
            'bank_name' => 'Commercial Bank',
            'bank_branch' => 'Colombo',
            'bank_account_number' => '1234123412',
            'bank_account_name' => 'Raced Candidate',
        ]);

        $response->assertSessionHasErrors('token');

        // Confirm database atomicity: No user created, no agent profile created
        $this->assertDatabaseMissing('users', ['email' => 'raced@example.lk']);
        $this->assertDatabaseMissing('agent_profiles', ['nic' => '199500001111']);
        $this->assertEquals(AgentInvitation::STATUS_REVOKED, $invitation->fresh()->status);
    }
}

