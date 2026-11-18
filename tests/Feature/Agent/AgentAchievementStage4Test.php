<?php

namespace Tests\Feature\Agent;

use App\Models\AgentAchievement;
use App\Models\AgentAchievementAward;
use App\Models\AgentAchievementRequest;
use App\Models\AgentAchievementVersion;
use App\Models\AgentProfile;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use App\Notifications\AgentAchievementAwardedNotification;
use App\Notifications\AgentAchievementReinstatedNotification;
use App\Notifications\AgentAchievementRevokedNotification;
use App\Services\AgentAchievementService;
use Carbon\Carbon;
use Database\Seeders\AgentAchievementSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AgentAchievementStage4Test extends TestCase
{
    use RefreshDatabase;

    protected User $owner;
    protected User $superAdmin;
    protected User $agentManager;
    protected User $lla;
    protected AgentAchievement $discretionaryAchievement;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(AgentAchievementSeeder::class);

        // 1. Business Owner
        $this->owner = User::factory()->create([
            'role' => 'owner',
            'protected_authority' => 'business',
            'status' => 'active',
        ]);
        $ownerRole = Role::where('name', 'owner')->first();
        if ($ownerRole) {
            $this->owner->assignedRoles()->sync([$ownerRole->id]);
        }

        // 2. Super Admin (Technical Authority)
        $this->superAdmin = User::factory()->create([
            'role' => 'super_admin',
            'protected_authority' => 'technical',
            'status' => 'active',
        ]);
        $saRole = Role::where('name', 'super_admin')->first();
        if ($saRole) {
            $this->superAdmin->assignedRoles()->sync([$saRole->id]);
        }

        // 3. Agent Manager
        $this->agentManager = User::factory()->create([
            'role' => 'agent_manager',
            'status' => 'active',
        ]);
        $amRole = Role::where('name', 'agent_manager')->first();
        if ($amRole) {
            $this->agentManager->assignedRoles()->sync([$amRole->id]);
        }

        // 4. Eligible LLA
        $this->lla = User::factory()->create([
            'role' => 'low_level_agent',
            'status' => 'active',
        ]);
        AgentProfile::create([
            'user_id' => $this->lla->id,
            'kyc_status' => 'verified',
            'referral_code' => 'LLA_TEST_01',
        ]);

        $this->discretionaryAchievement = AgentAchievement::where('code', 'HONOR_CONVENTION_AWARD')->firstOrFail();
    }

    /**
     * 1. Test Agent Manager can submit nomination and sets 30-day expiration.
     */
    public function test_agent_manager_can_submit_nomination_with_expiration(): void
    {
        $service = app(AgentAchievementService::class);

        $request = $service->submitDiscretionaryRequest([
            'agent_id' => $this->lla->id,
            'achievement_id' => $this->discretionaryAchievement->id,
            'period_key' => '2026',
            'justification' => 'Exemplary customer satisfaction rating and consistent community mentorship.',
        ], $this->agentManager);

        $this->assertEquals('pending', $request->status);
        $this->assertEquals($this->lla->id, $request->agent_id);
        $this->assertEquals($this->agentManager->id, $request->requested_by);
        $this->assertNotNull($request->expires_at);
        $this->assertTrue(Carbon::now()->addDays(29)->lessThan($request->expires_at));

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'agent_achievement_request_submitted',
            'auditable_type' => AgentAchievementRequest::class,
            'auditable_id' => $request->id,
            'user_id' => $this->agentManager->id,
        ]);
    }

    /**
     * 2. Test Anti-Self-Nomination: User cannot nominate themselves.
     */
    public function test_user_cannot_nominate_themselves(): void
    {
        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage('Anti-Self-Approval');

        $service = app(AgentAchievementService::class);
        $service->submitDiscretionaryRequest([
            'agent_id' => $this->agentManager->id,
            'achievement_id' => $this->discretionaryAchievement->id,
            'justification' => 'Self-nomination justification attempt.',
        ], $this->agentManager);
    }

    /**
     * 3. Test Owner-only approval creates award bound to active definition version.
     */
    public function test_owner_approval_creates_award_bound_to_version(): void
    {
        Notification::fake();

        $service = app(AgentAchievementService::class);
        $nomination = $service->submitDiscretionaryRequest([
            'agent_id' => $this->lla->id,
            'achievement_id' => $this->discretionaryAchievement->id,
            'period_key' => '2026',
            'justification' => 'Outstanding field ethical leadership demonstrated throughout the year.',
        ], $this->agentManager);

        $award = $service->approveDiscretionaryRequest(
            $nomination->id,
            'Approved on executive council review.',
            $this->owner
        );

        $this->assertEquals('active', $award->status);
        $this->assertTrue($award->is_discretionary);
        $this->assertEquals($this->owner->id, $award->awarded_by);
        $this->assertEquals($nomination->id, $award->request_id);

        $nomination->refresh();
        $this->assertEquals('approved', $nomination->status);
        $this->assertEquals($this->owner->id, $nomination->decided_by);

        Notification::assertSentTo($this->lla, AgentAchievementAwardedNotification::class);
    }

    /**
     * 4. Test Anti-Self-Approval: Creator cannot approve their own nomination.
     */
    public function test_creator_cannot_approve_own_nomination(): void
    {
        $service = app(AgentAchievementService::class);
        $nomination = $service->submitDiscretionaryRequest([
            'agent_id' => $this->lla->id,
            'achievement_id' => $this->discretionaryAchievement->id,
            'justification' => 'Executive nomination by the Business Owner.',
        ], $this->owner);

        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage('Anti-Self-Approval');

        // Same owner attempting to approve their own submitted nomination
        $service->approveDiscretionaryRequest(
            $nomination->id,
            'Self approval attempt.',
            $this->owner
        );
    }

    /**
     * 5. Test Super Admin is denied from submitting nominations and approvals.
     */
    public function test_super_admin_is_denied_from_governance_actions(): void
    {
        $this->actingAs($this->superAdmin);

        // Attempting to submit nomination
        $response = $this->post(route('admin.agents.achievements.requests.store'), [
            'agent_id' => $this->lla->id,
            'achievement_id' => $this->discretionaryAchievement->id,
            'justification' => 'Super admin nomination attempt.',
        ]);
        $response->assertStatus(403);

        // Attempting to approve
        $service = app(AgentAchievementService::class);
        $nomination = $service->submitDiscretionaryRequest([
            'agent_id' => $this->lla->id,
            'achievement_id' => $this->discretionaryAchievement->id,
            'justification' => 'Valid nomination by Agent Manager.',
        ], $this->agentManager);

        $responseApprove = $this->post(route('admin.agents.achievements.requests.approve', $nomination->id), [
            'decision_notes' => 'Super admin approval attempt.',
        ]);
        $responseApprove->assertStatus(403);
    }

    /**
     * 6. Test Owner Rejection updates request status without creating awards.
     */
    public function test_owner_rejection_updates_status_without_award(): void
    {
        $service = app(AgentAchievementService::class);
        $nomination = $service->submitDiscretionaryRequest([
            'agent_id' => $this->lla->id,
            'achievement_id' => $this->discretionaryAchievement->id,
            'justification' => 'Nomination to be rejected on review.',
        ], $this->agentManager);

        $rejected = $service->rejectDiscretionaryRequest(
            $nomination->id,
            'Does not meet current year performance threshold.',
            $this->owner
        );

        $this->assertEquals('rejected', $rejected->status);
        $this->assertEquals($this->owner->id, $rejected->decided_by);
        $this->assertEquals(0, AgentAchievementAward::count());

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'agent_achievement_request_rejected',
            'auditable_type' => AgentAchievementRequest::class,
            'auditable_id' => $nomination->id,
        ]);
    }

    /**
     * 7. Test Direct Owner Grant without prior nomination.
     */
    public function test_owner_direct_discretionary_grant(): void
    {
        Notification::fake();

        $service = app(AgentAchievementService::class);
        $award = $service->directGrantDiscretionaryAward(
            $this->lla->id,
            $this->discretionaryAchievement->id,
            '2026',
            'Direct Owner grant during annual convention awards.',
            $this->owner
        );

        $this->assertEquals('active', $award->status);
        $this->assertNull($award->request_id);
        $this->assertEquals($this->owner->id, $award->awarded_by);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'agent_achievement_direct_granted',
            'auditable_type' => AgentAchievementAward::class,
            'auditable_id' => $award->id,
        ]);

        Notification::assertSentTo($this->lla, AgentAchievementAwardedNotification::class);
    }

    /**
     * 8. Test Two-Transaction Expiration Safety: Expired request commits 'expired' status and aborts approval.
     */
    public function test_expired_request_commits_expired_status_and_aborts_approval(): void
    {
        $service = app(AgentAchievementService::class);
        $nomination = $service->submitDiscretionaryRequest([
            'agent_id' => $this->lla->id,
            'achievement_id' => $this->discretionaryAchievement->id,
            'justification' => 'Nomination that will expire.',
        ], $this->agentManager);

        // Manually age the request past 30 days
        $nomination->update([
            'expires_at' => Carbon::now()->subDays(2),
        ]);

        try {
            $service->approveDiscretionaryRequest(
                $nomination->id,
                'Attempt to approve expired request.',
                $this->owner
            );
            $this->fail('Expected DomainException for expired request was not thrown.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('expired', $e->getMessage());
        }

        // Verify request status was permanently committed as expired
        $nomination->refresh();
        $this->assertEquals('expired', $nomination->status);
        $this->assertEquals(0, AgentAchievementAward::count());

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'agent_achievement_request_expired',
            'auditable_type' => AgentAchievementRequest::class,
            'auditable_id' => $nomination->id,
        ]);
    }

    /**
     * 9. Test Prune Expired Requests Artisan Command.
     */
    public function test_prune_expired_achievement_requests_command(): void
    {
        $service = app(AgentAchievementService::class);
        $req1 = $service->submitDiscretionaryRequest([
            'agent_id' => $this->lla->id,
            'achievement_id' => $this->discretionaryAchievement->id,
            'period_key' => 'p1',
            'justification' => 'Nomination that is overdue.',
        ], $this->agentManager);
        $req1->update(['expires_at' => Carbon::now()->subDay()]);

        $this->artisan('agent:prune-expired-achievement-requests')
            ->assertSuccessful();

        $req1->refresh();
        $this->assertEquals('expired', $req1->status);
    }

    /**
     * 10. Test Owner can Revoke Active or Corrected Awards, with mandatory reason.
     */
    public function test_owner_can_revoke_award_with_mandatory_reason(): void
    {
        Notification::fake();

        $service = app(AgentAchievementService::class);
        $award = $service->directGrantDiscretionaryAward(
            $this->lla->id,
            $this->discretionaryAchievement->id,
            '2026',
            'Direct grant to be revoked later.',
            $this->owner
        );

        $revoked = $service->revokeAward(
            $award->id,
            'Compliance audit found fraudulent orders associated with this nomination.',
            $this->owner
        );

        $this->assertEquals('revoked', $revoked->status);
        $this->assertEquals($this->owner->id, $revoked->revoked_by);
        $this->assertNotNull($revoked->revoked_at);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'agent_achievement_award_revoked',
            'auditable_type' => AgentAchievementAward::class,
            'auditable_id' => $award->id,
        ]);

        Notification::assertSentTo($this->lla, AgentAchievementRevokedNotification::class);
    }

    /**
     * 11. Test Owner can Reinstate Revoked Award, producing distinct notification.
     */
    public function test_owner_can_reinstate_revoked_award(): void
    {
        Notification::fake();

        $service = app(AgentAchievementService::class);
        $award = $service->directGrantDiscretionaryAward(
            $this->lla->id,
            $this->discretionaryAchievement->id,
            '2026',
            'Direct grant to test revoke and reinstatement.',
            $this->owner
        );

        $service->revokeAward(
            $award->id,
            'Temporary suspension pending internal compliance review.',
            $this->owner
        );

        $reinstated = $service->reinstateAward(
            $award->id,
            'Formal exoneration following internal compliance investigation.',
            $this->owner
        );

        $this->assertEquals('active', $reinstated->status);
        $this->assertNull($reinstated->revoked_at);
        $this->assertNull($reinstated->revocation_reason);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'agent_achievement_award_reinstated',
            'auditable_type' => AgentAchievementAward::class,
            'auditable_id' => $award->id,
        ]);

        Notification::assertSentTo($this->lla, AgentAchievementReinstatedNotification::class);
    }

    /**
     * 12. Test Concurrent Duplicate Request Protection via Virtual Generated Slot.
     */
    public function test_concurrent_duplicate_pending_request_is_prevented(): void
    {
        $service = app(AgentAchievementService::class);
        $service->submitDiscretionaryRequest([
            'agent_id' => $this->lla->id,
            'achievement_id' => $this->discretionaryAchievement->id,
            'period_key' => '2026',
            'justification' => 'First valid nomination for 2026 cycle.',
        ], $this->agentManager);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('already exists');

        // Second simultaneous nomination for same agent, achievement, and period
        $service->submitDiscretionaryRequest([
            'agent_id' => $this->lla->id,
            'achievement_id' => $this->discretionaryAchievement->id,
            'period_key' => '2026',
            'justification' => 'Duplicate nomination attempt for 2026 cycle.',
        ], $this->agentManager);
    }

    /**
     * 13. Test Expiry Boundary: Request at exact expires_at timestamp is treated as expired.
     */
    public function test_request_at_exact_expiry_boundary_is_treated_as_expired(): void
    {
        $service = app(AgentAchievementService::class);
        $nomination = $service->submitDiscretionaryRequest([
            'agent_id' => $this->lla->id,
            'achievement_id' => $this->discretionaryAchievement->id,
            'justification' => 'Nomination at exact expiry boundary.',
        ], $this->agentManager);

        // Freeze time to exact expires_at
        $exactExpiry = $nomination->expires_at;
        Carbon::setTestNow($exactExpiry);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('expired');

        try {
            $service->approveDiscretionaryRequest(
                $nomination->id,
                'Approval attempt at exact boundary.',
                $this->owner
            );
        } finally {
            Carbon::setTestNow(); // Reset test clock
        }
    }

    /**
     * 14. Test Expiration Crossing Race inside final locked approval transaction.
     */
    public function test_expiration_crossing_race_inside_locked_transaction(): void
    {
        $service = app(AgentAchievementService::class);
        $nomination = $service->submitDiscretionaryRequest([
            'agent_id' => $this->lla->id,
            'achievement_id' => $this->discretionaryAchievement->id,
            'justification' => 'Nomination crossing boundary.',
        ], $this->agentManager);

        // Simulate request expiring between Transaction 1 and Transaction 2
        $nomination->update(['expires_at' => Carbon::now()->addSeconds(1)]);

        // Advance time during approval execution
        Carbon::setTestNow(Carbon::now()->addSeconds(5));

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('expired');

        try {
            $service->approveDiscretionaryRequest(
                $nomination->id,
                'Approval attempt when crossing boundary.',
                $this->owner
            );
        } finally {
            Carbon::setTestNow();
        }

        // Assert persisted database state survived the exception and was not rolled back
        $this->assertDatabaseHas('agent_achievement_requests', [
            'id' => $nomination->id,
            'status' => 'expired',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'agent_achievement_request_expired',
            'auditable_type' => AgentAchievementRequest::class,
            'auditable_id' => $nomination->id,
        ]);

        $this->assertEquals(0, AgentAchievementAward::count());
    }

    /**
     * 18. Test Repeating Approval on Expired Request cannot turn it into approved.
     */
    public function test_repeating_approval_on_expired_request_cannot_turn_into_approved(): void
    {
        $service = app(AgentAchievementService::class);
        $nomination = $service->submitDiscretionaryRequest([
            'agent_id' => $this->lla->id,
            'achievement_id' => $this->discretionaryAchievement->id,
            'justification' => 'Nomination to expire.',
        ], $this->agentManager);

        $nomination->update(['expires_at' => Carbon::now()->subDay()]);

        try {
            $service->approveDiscretionaryRequest($nomination->id, 'First attempt', $this->owner);
        } catch (\DomainException $e) {
            // Expected
        }

        $this->assertDatabaseHas('agent_achievement_requests', [
            'id' => $nomination->id,
            'status' => 'expired',
        ]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('expired');

        // Second attempt on now-persisted expired request
        $service->approveDiscretionaryRequest($nomination->id, 'Second attempt', $this->owner);
    }

    /**
     * 19. Test Unrelated Exception during approval causes clean rollback.
     */
    public function test_unrelated_exception_during_approval_causes_rollback(): void
    {
        $service = app(AgentAchievementService::class);
        $nomination = $service->submitDiscretionaryRequest([
            'agent_id' => $this->lla->id,
            'achievement_id' => $this->discretionaryAchievement->id,
            'justification' => 'Valid nomination before failure.',
        ], $this->agentManager);

        // Deactivate agent right before approval to trigger DomainException
        $this->lla->update(['status' => 'suspended']);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('eligible');

        try {
            $service->approveDiscretionaryRequest($nomination->id, 'Attempt on suspended agent', $this->owner);
        } finally {
            $nomination->refresh();
            // Request remains pending (not approved, not awarded)
            $this->assertEquals('pending', $nomination->status);
            $this->assertEquals(0, AgentAchievementAward::count());
        }
    }

    /**
     * 15. Test Direct Grant strictly rejects previously revoked award slot.
     */
    public function test_direct_grant_strictly_rejects_revoked_award_slot(): void
    {
        $service = app(AgentAchievementService::class);
        $award = $service->directGrantDiscretionaryAward(
            $this->lla->id,
            $this->discretionaryAchievement->id,
            '2026',
            'Initial direct grant for revocation testing.',
            $this->owner
        );

        $service->revokeAward($award->id, 'Compliance violation identified.', $this->owner);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('revoked');

        // Direct grant attempt on revoked slot must fail closed
        $service->directGrantDiscretionaryAward(
            $this->lla->id,
            $this->discretionaryAchievement->id,
            '2026',
            'Direct grant bypass attempt on revoked slot.',
            $this->owner
        );
    }

    /**
     * 16. Test Notification Idempotency across multiple distinct revoke/reinstate cycles.
     */
    public function test_notification_idempotency_and_distinct_identities_across_cycles(): void
    {
        $service = app(AgentAchievementService::class);
        $award = $service->directGrantDiscretionaryAward(
            $this->lla->id,
            $this->discretionaryAchievement->id,
            '2026',
            'Initial grant for cycle verification.',
            $this->owner
        );

        // Cycle 1: Revoke
        $service->revokeAward($award->id, 'First revocation reason.', $this->owner);
        $notificationsRevoke1 = $this->lla->notifications()->where('type', 'App\Notifications\AgentAchievementRevokedNotification')->get();
        $this->assertCount(1, $notificationsRevoke1);

        // Cycle 1: Reinstate
        $service->reinstateAward($award->id, 'First reinstatement reason.', $this->owner);
        $notificationsReinstate1 = $this->lla->notifications()->where('type', 'App\Notifications\AgentAchievementReinstatedNotification')->get();
        $this->assertCount(1, $notificationsReinstate1);

        // Cycle 2: Revoke again
        $service->revokeAward($award->id, 'Second revocation reason.', $this->owner);
        $notificationsRevoke2 = $this->lla->notifications()->where('type', 'App\Notifications\AgentAchievementRevokedNotification')->get();
        $this->assertCount(2, $notificationsRevoke2);

        // Verify distinct notification IDs across cycles
        $this->assertNotEquals($notificationsRevoke1->first()->id, $notificationsRevoke2->last()->id);
    }

    /**
     * 17. Test Beneficiary Anti-Self-Approval: Beneficiary cannot approve their own award.
     */
    public function test_beneficiary_cannot_approve_their_own_award(): void
    {
        $service = app(AgentAchievementService::class);
        $nomination = $service->submitDiscretionaryRequest([
            'agent_id' => $this->lla->id,
            'achievement_id' => $this->discretionaryAchievement->id,
            'justification' => 'Nomination where beneficiary is LLA.',
        ], $this->agentManager);

        $this->expectException(AuthorizationException::class);

        // LLA attempting to approve their own award
        $service->approveDiscretionaryRequest(
            $nomination->id,
            'Beneficiary self-approval attempt.',
            $this->lla
        );
    }
}
