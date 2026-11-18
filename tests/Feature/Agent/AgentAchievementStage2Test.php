<?php

namespace Tests\Feature\Agent;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\AgentAchievement;
use App\Models\AgentAchievementAward;
use App\Models\AgentAchievementEvaluationRun;
use App\Models\AgentAchievementVersion;
use App\Models\AgentProfile;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\User;
use App\Services\AgentAchievementService;
use App\Services\AgentAnalyticsService;
use Carbon\Carbon;
use Database\Seeders\AgentAchievementSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class AgentAchievementStage2Test extends TestCase
{
    use RefreshDatabase;

    private AgentAchievementService $achievementService;
    private User $owner;
    private User $superAdmin;
    private User $agentManager;
    private User $hla;
    private User $lla;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(AgentAchievementSeeder::class);

        $this->achievementService = app(AgentAchievementService::class);

        // 1. Business Owner
        $this->owner = User::factory()->create([
            'name' => 'Dilshan Ranasinghe',
            'email' => 'owner@auraluxe.lk',
            'role' => UserRole::OWNER,
            'status' => UserStatus::ACTIVE,
            'protected_authority' => 'business',
        ]);
        $this->owner->syncRole('owner');

        // 2. Super Admin
        $this->superAdmin = User::factory()->create([
            'name' => 'Naveen Jayawardena',
            'email' => 'superadmin@auraluxe.lk',
            'role' => UserRole::SUPER_ADMIN,
            'status' => UserStatus::ACTIVE,
            'protected_authority' => 'technical',
        ]);
        $this->superAdmin->syncRole('super_admin');

        // 3. Agent Manager (Staff)
        $this->agentManager = User::factory()->create([
            'name' => 'Kavinda Perera',
            'email' => 'agentmanager@auraluxe.lk',
            'role' => 'agent_manager',
            'status' => UserStatus::ACTIVE,
        ]);
        $this->agentManager->syncRole('agent_manager');

        // 4. HLA
        $this->hla = User::factory()->create([
            'name' => 'Dilini Senanayake (HLA)',
            'email' => 'hla@auraluxe.lk',
            'role' => UserRole::HIGH_LEVEL_AGENT,
            'status' => UserStatus::ACTIVE,
            'referral_code' => 'HLAMAIN01',
        ]);
        $this->hla->syncRole('high_level_agent');
        AgentProfile::create([
            'user_id' => $this->hla->id,
            'nic' => '198812345678',
            'kyc_status' => 'verified',
            'wallet_balance' => 0.00,
            'sales_tier' => 'bronze',
        ]);

        // 5. LLA
        $this->lla = User::factory()->create([
            'name' => 'Saman Kumara (LLA)',
            'email' => 'lla@auraluxe.lk',
            'role' => UserRole::LOW_LEVEL_AGENT,
            'status' => UserStatus::ACTIVE,
            'parent_id' => $this->hla->id,
            'referral_code' => 'LLAMAIN01',
        ]);
        $this->lla->syncRole('low_level_agent');
        AgentProfile::create([
            'user_id' => $this->lla->id,
            'nic' => '199512345678',
            'kyc_status' => 'verified',
            'wallet_balance' => 0.00,
            'sales_tier' => 'bronze',
        ]);
    }

    private function createOrderForAgent(
        User $agent,
        string|float $subtotal,
        string $status = 'delivered',
        string|float $discount = '0.00',
        ?string $orderNumber = null
    ): Order {
        static $orderSeq = 1;
        $orderNumber = $orderNumber ?: 'ORD-STAGE2-' . str_pad((string) $orderSeq++, 4, '0', STR_PAD_LEFT);
        $subtotalStr = AgentAnalyticsService::normalizeDecimalString($subtotal, false);
        $discountStr = AgentAnalyticsService::normalizeDecimalString($discount, true);
        $total = bcadd(bcsub($subtotalStr, $discountStr, 2), '350.00', 2);

        return Order::create([
            'order_number' => $orderNumber,
            'agent_id' => $agent->id,
            'customer_name' => 'Test Customer',
            'customer_phone' => '+94771234567',
            'shipping_address' => 'Colombo 07',
            'district' => 'Colombo',
            'city' => 'Colombo',
            'subtotal' => $subtotalStr,
            'discount_amount' => $discountStr,
            'delivery_charge' => '350.00',
            'total_amount' => $total,
            'payment_method' => 'cod',
            'status' => $status,
            'tracking_token' => 'TOK-' . $orderNumber,
            'placed_at' => now(),
        ]);
    }

    /**
     * 1. Test First Sale qualification succeeds with a delivered commercial order.
     */
    public function test_first_sale_qualification_succeeds_with_delivered_commercial_order(): void
    {
        $this->createOrderForAgent($this->lla, '15000.00', 'delivered');

        $result = $this->achievementService->evaluateCumulativeMilestonesForAgent($this->lla);

        $this->assertTrue($result['eligible']);
        $this->assertEquals(1, $result['awarded']);

        $award = AgentAchievementAward::where('agent_id', $this->lla->id)
            ->where('period_key', 'lifetime')
            ->first();

        $this->assertNotNull($award);
        $this->assertEquals(AgentAchievementService::CODE_FIRST_ORDER, $award->achievement->code);
        $this->assertEquals('active', $award->status);
        $this->assertEquals('1.00', $award->threshold_snapshot);
        $this->assertEquals('1.00', $award->achieved_value);

        // Verify audit log exists
        $audit = AuditLog::where('action', 'agent_achievement_awarded')
            ->where('auditable_id', $award->id)
            ->first();
        $this->assertNotNull($audit);
    }

    /**
     * 2. Test First Sale does NOT qualify on cancelled or returned orders.
     */
    public function test_first_sale_does_not_qualify_on_cancelled_or_returned_orders(): void
    {
        // Cancelled order
        $this->createOrderForAgent($this->lla, '15000.00', 'cancelled');

        $result = $this->achievementService->evaluateCumulativeMilestonesForAgent($this->lla);
        $this->assertEquals(0, $result['awarded']);
        $this->assertDatabaseMissing('agent_achievement_awards', ['agent_id' => $this->lla->id]);

        // Returned order (full return drops fulfilled orders to 0)
        $this->createOrderForAgent($this->lla, '20000.00', 'returned');
        $result2 = $this->achievementService->evaluateCumulativeMilestonesForAgent($this->lla);
        $this->assertEquals(0, $result2['awarded']);
        $this->assertDatabaseMissing('agent_achievement_awards', ['agent_id' => $this->lla->id]);
    }

    /**
     * 3. Test cumulative volume milestones at exact threshold boundaries.
     */
    public function test_cumulative_sales_volume_milestones_at_exact_threshold_boundaries(): void
    {
        // Subtotal: 99,999.99 -> Exactly 1 cent below 100k
        $order1 = $this->createOrderForAgent($this->lla, '99999.99', 'delivered');

        $result1 = $this->achievementService->evaluateCumulativeMilestonesForAgent($this->lla);
        // Only First Sale qualifies; 100k does NOT qualify
        $this->assertEquals(1, $result1['awarded']);
        $this->assertDatabaseHas('agent_achievement_awards', [
            'agent_id' => $this->lla->id,
            'achievement_id' => AgentAchievement::where('code', AgentAchievementService::CODE_FIRST_ORDER)->value('id'),
        ]);
        $this->assertDatabaseMissing('agent_achievement_awards', [
            'agent_id' => $this->lla->id,
            'achievement_id' => AgentAchievement::where('code', AgentAchievementService::CODE_100K)->value('id'),
        ]);

        // Add 0.01 -> Net sales now exactly 100,000.00
        $this->createOrderForAgent($this->lla, '0.01', 'delivered');
        $result2 = $this->achievementService->evaluateCumulativeMilestonesForAgent($this->lla);
        $this->assertEquals(1, $result2['awarded']); // 100k milestone awarded!
        $this->assertDatabaseHas('agent_achievement_awards', [
            'agent_id' => $this->lla->id,
            'achievement_id' => AgentAchievement::where('code', AgentAchievementService::CODE_100K)->value('id'),
            'status' => 'active',
            'achieved_value' => '100000.00',
        ]);

        // Add 400,000.00 -> Net sales now 500,000.00
        $this->createOrderForAgent($this->lla, '400000.00', 'delivered');
        $result3 = $this->achievementService->evaluateCumulativeMilestonesForAgent($this->lla);
        $this->assertEquals(1, $result3['awarded']); // 500k milestone awarded!
        $this->assertDatabaseHas('agent_achievement_awards', [
            'agent_id' => $this->lla->id,
            'achievement_id' => AgentAchievement::where('code', AgentAchievementService::CODE_500K)->value('id'),
            'status' => 'active',
            'achieved_value' => '500000.00',
        ]);

        // Add 500,000.00 -> Net sales now 1,000,000.00
        $this->createOrderForAgent($this->lla, '500000.00', 'delivered');
        $result4 = $this->achievementService->evaluateCumulativeMilestonesForAgent($this->lla);
        $this->assertEquals(1, $result4['awarded']); // 1M milestone awarded!
        $this->assertDatabaseHas('agent_achievement_awards', [
            'agent_id' => $this->lla->id,
            'achievement_id' => AgentAchievement::where('code', AgentAchievementService::CODE_1M)->value('id'),
            'status' => 'active',
            'achieved_value' => '1000000.00',
        ]);
    }

    /**
     * 4. Test ineligible agents are strictly rejected from receiving awards.
     */
    public function test_ineligible_agents_are_strictly_rejected(): void
    {
        // 1. Inactive agent
        $inactiveAgent = User::factory()->create([
            'role' => UserRole::LOW_LEVEL_AGENT,
            'status' => UserStatus::SUSPENDED,
        ]);
        AgentProfile::create([
            'user_id' => $inactiveAgent->id,
            'nic' => '199011112222',
            'kyc_status' => 'verified',
        ]);
        $this->createOrderForAgent($inactiveAgent, '150000.00', 'delivered');
        $res1 = $this->achievementService->evaluateCumulativeMilestonesForAgent($inactiveAgent);
        $this->assertFalse($res1['eligible']);
        $this->assertEquals(0, $res1['awarded']);

        // 2. Unverified KYC agent
        $unverifiedAgent = User::factory()->create([
            'role' => UserRole::LOW_LEVEL_AGENT,
            'status' => UserStatus::ACTIVE,
        ]);
        AgentProfile::create([
            'user_id' => $unverifiedAgent->id,
            'nic' => '199033334444',
            'kyc_status' => 'pending',
        ]);
        $this->createOrderForAgent($unverifiedAgent, '150000.00', 'delivered');
        $res2 = $this->achievementService->evaluateCumulativeMilestonesForAgent($unverifiedAgent);
        $this->assertFalse($res2['eligible']);
        $this->assertEquals(0, $res2['awarded']);

        // 3. Customer user
        $customer = User::factory()->create([
            'role' => UserRole::CUSTOMER,
            'status' => UserStatus::ACTIVE,
        ]);
        $res3 = $this->achievementService->evaluateCumulativeMilestonesForAgent($customer);
        $this->assertFalse($res3['eligible']);
        $this->assertEquals(0, $res3['awarded']);
    }

    /**
     * 5. Test refund induces award correction when sales fall below threshold.
     */
    public function test_refund_induces_award_correction_when_sales_fall_below_threshold(): void
    {
        // Qualify for 100k milestone with two orders: 60,000 + 45,000 = 105,000.00
        $order1 = $this->createOrderForAgent($this->lla, '60000.00', 'delivered');
        $order2 = $this->createOrderForAgent($this->lla, '45000.00', 'delivered');
        $this->achievementService->evaluateCumulativeMilestonesForAgent($this->lla);

        $award100k = AgentAchievementAward::where('agent_id', $this->lla->id)
            ->where('achievement_id', AgentAchievement::where('code', AgentAchievementService::CODE_100K)->value('id'))
            ->first();
        $this->assertNotNull($award100k);
        $this->assertEquals('active', $award100k->status);

        // Customer returns Order 2 ($order2 status becomes 'returned') -> Net commercial sales drops to 60,000.00
        $order2->update(['status' => 'returned']);

        $result = $this->achievementService->evaluateCumulativeMilestonesForAgent($this->lla);
        $this->assertEquals(1, $result['corrected']);

        $award100k->refresh();
        $this->assertEquals('corrected', $award100k->status);
        $this->assertEquals('60000.00', $award100k->achieved_value);
        $this->assertNotNull($award100k->revocation_reason);

        // Verify AuditLog for correction
        $audit = AuditLog::where('action', 'agent_achievement_award_corrected')
            ->where('auditable_id', $award100k->id)
            ->first();
        $this->assertNotNull($audit);
    }

    /**
     * 6. Test subsequent qualifying sales reactivate a corrected award.
     */
    public function test_subsequent_qualifying_sales_reactivate_corrected_award(): void
    {
        // Start qualified (60,000 + 45,000 = 105,000), then return order 2 (drops to 60,000) -> corrected
        $order1 = $this->createOrderForAgent($this->lla, '60000.00', 'delivered');
        $order2 = $this->createOrderForAgent($this->lla, '45000.00', 'delivered');
        $this->achievementService->evaluateCumulativeMilestonesForAgent($this->lla);

        $order2->update(['status' => 'returned']);
        $this->achievementService->evaluateCumulativeMilestonesForAgent($this->lla);

        $award100k = AgentAchievementAward::where('agent_id', $this->lla->id)
            ->where('achievement_id', AgentAchievement::where('code', AgentAchievementService::CODE_100K)->value('id'))
            ->first();
        $this->assertEquals('corrected', $award100k->status);

        // Subsequent commercial sale of 55,000.00 delivered -> Net sales now 60,000 + 55,000 = 115,000.00
        $this->createOrderForAgent($this->lla, '55000.00', 'delivered');

        $result = $this->achievementService->evaluateCumulativeMilestonesForAgent($this->lla);
        $this->assertEquals(1, $result['requalified']);

        $award100k->refresh();
        $this->assertEquals('active', $award100k->status);
        $this->assertEquals('115000.00', $award100k->achieved_value);
        $this->assertNull($award100k->revocation_reason);

        // Verify AuditLog for requalification
        $audit = AuditLog::where('action', 'agent_achievement_award_requalified')
            ->where('auditable_id', $award100k->id)
            ->first();
        $this->assertNotNull($audit);
    }

    /**
     * 7. Test automated evaluation never reinstates or modifies a revoked award.
     */
    public function test_automated_evaluation_never_reinstates_or_modifies_revoked_award(): void
    {
        $this->createOrderForAgent($this->lla, '150000.00', 'delivered');
        $this->achievementService->evaluateCumulativeMilestonesForAgent($this->lla);

        $award100k = AgentAchievementAward::where('agent_id', $this->lla->id)
            ->where('achievement_id', AgentAchievement::where('code', AgentAchievementService::CODE_100K)->value('id'))
            ->first();

        // Business Owner explicitly revokes the award
        $award100k->update([
            'status' => 'revoked',
            'revoked_at' => now(),
            'revoked_by' => $this->owner->id,
            'revocation_reason' => 'Ethical code of conduct breach.',
        ]);

        // Agent generates further sales (e.g. 500k)
        $this->createOrderForAgent($this->lla, '350000.00', 'delivered');

        // Evaluate again: automated engine must strictly preserve 'revoked'
        $result = $this->achievementService->evaluateCumulativeMilestonesForAgent($this->lla);
        $this->assertEquals(0, $result['requalified']);

        $award100k->refresh();
        $this->assertEquals('revoked', $award100k->status);
        $this->assertEquals('Ethical code of conduct breach.', $award100k->revocation_reason);
    }

    /**
     * 8. Test duplicate evaluation is completely idempotent with zero duplicated records.
     */
    public function test_duplicate_evaluation_is_completely_idempotent(): void
    {
        $this->createOrderForAgent($this->lla, '120000.00', 'delivered');

        $res1 = $this->achievementService->evaluateCumulativeMilestonesForAgent($this->lla);
        $this->assertEquals(2, $res1['awarded']); // First Order + 100k

        $countBefore = AgentAchievementAward::count();

        // Evaluate second and third times
        $res2 = $this->achievementService->evaluateCumulativeMilestonesForAgent($this->lla);
        $res3 = $this->achievementService->evaluateCumulativeMilestonesForAgent($this->lla);

        $this->assertEquals(0, $res2['awarded']);
        $this->assertEquals(0, $res3['awarded']);
        $this->assertEquals($countBefore, AgentAchievementAward::count());
    }

    /**
     * 9. Test historical version linkage: award references AgentAchievementVersion.
     */
    public function test_award_links_to_current_definition_version(): void
    {
        $this->createOrderForAgent($this->lla, '50000.00', 'delivered');
        $this->achievementService->evaluateCumulativeMilestonesForAgent($this->lla);

        $award = AgentAchievementAward::where('agent_id', $this->lla->id)->first();
        $this->assertNotNull($award);
        $this->assertNotNull($award->version);
        $this->assertEquals(1, $award->version->version);
        $this->assertEquals($award->achievement->id, $award->version->achievement_id);
    }

    /**
     * 10. Test backfill preview dry-run generates snapshot without writing awards.
     */
    public function test_backfill_preview_dry_run_generates_snapshot_without_writing_awards(): void
    {
        $this->createOrderForAgent($this->lla, '120000.00', 'delivered');
        $this->createOrderForAgent($this->hla, '600000.00', 'delivered');

        $awardsBefore = AgentAchievementAward::count();

        $run = $this->achievementService->previewHistoricalBackfill($this->superAdmin);

        $this->assertEquals('pending_owner_authorization', $run->status);
        $this->assertEquals('historical_backfill', $run->run_type);
        $this->assertGreaterThan(0, $run->awards_created);
        $this->assertNotEmpty($run->metadata['checksum']);
        $this->assertNotEmpty($run->metadata['preview_awards']);

        // Zero awards were written to the database!
        $this->assertEquals($awardsBefore, AgentAchievementAward::count());
    }

    /**
     * 11. Test backfill authorization requires Business Owner authority.
     */
    public function test_backfill_authorization_requires_business_owner_authority(): void
    {
        $run = $this->achievementService->previewHistoricalBackfill();

        // 1. Super Admin attempt is denied
        try {
            $this->achievementService->authorizeBackfillRun($run, $this->superAdmin);
            $this->fail("Super Admin should not be authorized to approve backfill.");
        } catch (AuthorizationException $e) {
            $this->assertStringContainsString("Business Owner", $e->getMessage());
        }

        // 2. Agent Manager attempt is denied
        try {
            $this->achievementService->authorizeBackfillRun($run, $this->agentManager);
            $this->fail("Agent Manager should not be authorized to approve backfill.");
        } catch (AuthorizationException $e) {
            $this->assertStringContainsString("Business Owner", $e->getMessage());
        }

        // 3. Business Owner succeeds
        $authorizedRun = $this->achievementService->authorizeBackfillRun($run, $this->owner);
        $this->assertEquals('authorized', $authorizedRun->status);
        $this->assertEquals($this->owner->id, $authorizedRun->metadata['authorized_by']);

        // Verify immutable AuditLog
        $audit = AuditLog::where('action', 'agent_achievement_backfill_authorized')
            ->where('auditable_id', $run->id)
            ->first();
        $this->assertNotNull($audit);
    }

    /**
     * 12. Test backfill execution is denied without Owner authorization.
     */
    public function test_backfill_execution_denied_without_owner_authorization(): void
    {
        $run = $this->achievementService->previewHistoricalBackfill();

        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage("not authorized for execution");

        $this->achievementService->executeBackfillRun($run);
    }

    /**
     * 13. Test backfill rejects expired preview window.
     */
    public function test_backfill_rejects_expired_preview_window(): void
    {
        $run = $this->achievementService->previewHistoricalBackfill();

        // Simulate 25 hours later (past 24h expiration)
        $meta = $run->metadata;
        $meta['expires_at'] = now()->subHour()->toIso8601String();
        $run->update(['metadata' => $meta]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage("Preview snapshot has expired");

        $this->achievementService->authorizeBackfillRun($run, $this->owner);
    }

    /**
     * 14. Test backfill rejects altered preview checksum.
     */
    public function test_backfill_rejects_altered_preview_checksum(): void
    {
        $run = $this->achievementService->previewHistoricalBackfill();

        // Tamper with preview awards data
        $meta = $run->metadata;
        $meta['preview_awards'][] = ['agent_id' => 999, 'tampered' => true];
        $run->update(['metadata' => $meta]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage("Security Violation: Preview snapshot checksum mismatch");

        $this->achievementService->authorizeBackfillRun($run, $this->owner);
    }

    /**
     * 15. Test backfill batch execution and safe resumption.
     */
    public function test_backfill_batch_execution_and_resumption(): void
    {
        $this->createOrderForAgent($this->hla, '600000.00', 'delivered');
        $this->createOrderForAgent($this->lla, '120000.00', 'delivered');

        $run = $this->achievementService->previewHistoricalBackfill();
        $this->achievementService->authorizeBackfillRun($run, $this->owner);

        // Simulate interruption after HLA: checkpoint last_processed_agent_id = HLA id (id 4)
        // Since HLA id < LLA id, resumption will process only remaining agents with id > HLA id (LLA)
        $meta = $run->metadata;
        $meta['last_processed_agent_id'] = $this->hla->id;
        $run->update(['metadata' => $meta]);

        // Execute: should process only LLA (id > HLA id)
        $result = $this->achievementService->executeBackfillRun($run, $this->owner, 10);

        $this->assertEquals('completed', $result['status']);
        $this->assertDatabaseHas('agent_achievement_awards', [
            'agent_id' => $this->lla->id,
            'status' => 'active',
        ]);
        // HLA was skipped due to checkpoint
        $this->assertDatabaseMissing('agent_achievement_awards', [
            'agent_id' => $this->hla->id,
        ]);
    }

    /**
     * 16. Test backfill Artisan command dry-run and execution guards.
     */
    public function test_backfill_artisan_command_dry_run_and_execution_guards(): void
    {
        // 1. Dry run succeeds with exit code 0
        $exitCode = Artisan::call('agent:backfill-achievements', ['--dry-run' => true]);
        $this->assertEquals(0, $exitCode);

        // 2. Execution with non-existent or unauthorized run ID fails with exit code 1
        $exitCode2 = Artisan::call('agent:backfill-achievements', ['--run-id' => '99999']);
        $this->assertEquals(1, $exitCode2);

        // 3. Execution on an authorized run succeeds
        $this->createOrderForAgent($this->lla, '100000.00', 'delivered');
        $run = $this->achievementService->previewHistoricalBackfill();
        $this->achievementService->authorizeBackfillRun($run, $this->owner);

        $exitCode3 = Artisan::call('agent:backfill-achievements', ['--run-id' => (string) $run->id]);
        $this->assertEquals(0, $exitCode3);

        $run->refresh();
        $this->assertEquals('completed', $run->status);
    }
}
