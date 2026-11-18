<?php

namespace Tests\Feature\Agent;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\AgentAnnouncement;
use App\Models\AgentAnnouncementRead;
use App\Models\AgentProfile;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\AgentAnnouncementService;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AgentAnnouncementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    protected function createOwner(): User
    {
        return User::factory()->create([
            'role' => UserRole::OWNER->value,
            'status' => UserStatus::ACTIVE,
            'protected_authority' => 'business',
        ]);
    }

    protected function createSuperAdmin(): User
    {
        return User::factory()->create([
            'role' => UserRole::SUPER_ADMIN->value,
            'status' => UserStatus::ACTIVE,
            'protected_authority' => 'technical',
        ]);
    }

    protected function createAgentManager(): User
    {
        return User::factory()->create([
            'role' => UserRole::AGENT_MANAGER->value,
            'status' => UserStatus::ACTIVE,
        ]);
    }

    protected function createAgent(UserRole $role = UserRole::HIGH_LEVEL_AGENT, string $status = 'active', string $kycStatus = 'verified'): User
    {
        $user = User::factory()->create([
            'role' => $role->value,
            'status' => $status,
        ]);

        AgentProfile::create([
            'user_id' => $user->id,
            'business_name' => "{$user->name} Cosmetics",
            'nic' => '199012345678',
            'address' => '123 Galle Road, Colombo',
            'kyc_status' => $kycStatus,
            'wallet_balance' => 0.00,
            'reserved_balance' => 0.00,
        ]);

        return $user;
    }

    /*
    |--------------------------------------------------------------------------
    | 1. RBAC & Authorization Tests
    |--------------------------------------------------------------------------
    */

    public function test_owner_can_access_announcement_console_via_is_owner_bypass(): void
    {
        $owner = $this->createOwner();

        $response = $this->actingAs($owner)->get('/admin/agents/announcements');

        $response->assertOk();
    }

    public function test_super_admin_can_access_announcement_console_via_assigned_permissions(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $response = $this->actingAs($superAdmin)->get('/admin/agents/announcements');

        $response->assertOk();
    }

    public function test_agent_manager_can_access_announcement_console(): void
    {
        $agentManager = $this->createAgentManager();

        $response = $this->actingAs($agentManager)->get('/admin/agents/announcements');

        $response->assertOk();
    }

    public function test_hla_and_lla_receive_403_on_admin_announcement_console(): void
    {
        $hla = $this->createAgent(UserRole::HIGH_LEVEL_AGENT);
        $lla = $this->createAgent(UserRole::LOW_LEVEL_AGENT);

        $this->actingAs($hla)->get('/admin/agents/announcements')->assertForbidden();
        $this->actingAs($lla)->get('/admin/agents/announcements')->assertForbidden();
    }

    public function test_unauthenticated_user_redirected_to_login(): void
    {
        $this->get('/admin/agents/announcements')->assertRedirect('/login');
        $this->get('/agent/announcements')->assertRedirect('/login');
    }

    /*
    |--------------------------------------------------------------------------
    | 2. Lifecycle State Transitions & Attribution Tests
    |--------------------------------------------------------------------------
    */

    public function test_can_create_draft_announcement_with_truthful_attribution(): void
    {
        $agentManager = $this->createAgentManager();

        $response = $this->actingAs($agentManager)->post('/admin/agents/announcements', [
            'title_en' => 'Q4 Seasonal Campaign Guidelines',
            'title_si' => 'Q4 සෘතුමය ව්‍යාපාර මාර්ගෝපදේශ',
            'content_en' => 'All agents must adhere to standardized retail pricing.',
            'content_si' => 'සියලුම නියෝජිතයින් සම්මත සිල්ලර මිල ගණන් පිළිපැදිය යුතුය.',
            'target_audience' => 'all',
            'priority' => 'important',
            'is_pinned' => true,
            'pin_order' => 1,
            'publish_action' => 'draft',
        ]);

        $response->assertRedirect('/admin/agents/announcements?tab=draft');

        $announcement = AgentAnnouncement::first();
        $this->assertNotNull($announcement);
        $this->assertSame('draft', $announcement->status);
        $this->assertSame($agentManager->id, $announcement->created_by);
        $this->assertNull($announcement->scheduled_by);
        $this->assertNull($announcement->published_by);
        $this->assertNull($announcement->published_at);
        $this->assertTrue($announcement->is_pinned);

        // Verify audit log
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'agent_announcement_created',
            'user_id' => $agentManager->id,
            'auditable_type' => AgentAnnouncement::class,
            'auditable_id' => $announcement->id,
        ]);
    }

    public function test_draft_is_strictly_hidden_from_agent_feed(): void
    {
        $agentManager = $this->createAgentManager();
        $hla = $this->createAgent(UserRole::HIGH_LEVEL_AGENT);

        AgentAnnouncement::create([
            'title_en' => 'Confidential Internal Draft',
            'content_en' => 'Not yet ready for network release.',
            'target_audience' => 'all',
            'priority' => 'normal',
            'status' => 'draft',
            'created_by' => $agentManager->id,
        ]);

        $service = app(AgentAnnouncementService::class);
        $feed = $service->getFeedForAgent($hla);

        $this->assertCount(0, $feed->items());
    }

    public function test_can_manually_publish_immediately(): void
    {
        $agentManager = $this->createAgentManager();

        $announcement = AgentAnnouncement::create([
            'title_en' => 'Urgent Compliance Update',
            'content_en' => 'Please review updated terms.',
            'target_audience' => 'all',
            'priority' => 'urgent',
            'status' => 'draft',
            'created_by' => $agentManager->id,
        ]);

        $response = $this->actingAs($agentManager)->post("/admin/agents/announcements/{$announcement->id}/publish");
        $response->assertRedirect();

        $announcement->refresh();
        $this->assertSame('published', $announcement->status);
        $this->assertNotNull($announcement->published_at);
        $this->assertSame($agentManager->id, $announcement->published_by);
        $this->assertNull($announcement->scheduled_by);

        // Verify audit log
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'agent_announcement_published_manual',
            'user_id' => $agentManager->id,
            'auditable_type' => AgentAnnouncement::class,
            'auditable_id' => $announcement->id,
        ]);
    }

    public function test_scheduling_rescheduling_and_cancellation_flow(): void
    {
        $adminA = $this->createSuperAdmin();
        $agentMgrB = $this->createAgentManager();
        $agentMgrC = User::factory()->create([
            'role' => UserRole::AGENT_MANAGER->value,
            'status' => UserStatus::ACTIVE,
        ]);

        // Step 1: Admin A creates draft
        $announcement = AgentAnnouncement::create([
            'title_en' => 'Scheduled Promo Launch',
            'content_en' => 'Promotional pricing goes live on scheduled date.',
            'target_audience' => 'all',
            'priority' => 'normal',
            'status' => 'draft',
            'created_by' => $adminA->id,
        ]);

        // Step 2: Agent Manager B schedules it for tomorrow
        $scheduledTime = Carbon::now()->addDay();
        $this->actingAs($agentMgrB)->post("/admin/agents/announcements/{$announcement->id}/schedule", [
            'scheduled_at' => $scheduledTime->format('Y-m-d\TH:i'),
        ])->assertRedirect();

        $announcement->refresh();
        $this->assertSame('scheduled', $announcement->status);
        $this->assertSame($agentMgrB->id, $announcement->scheduled_by);
        $this->assertSame($adminA->id, $announcement->created_by);

        // Step 3: Agent Manager C reschedules it for 2 days later
        $rescheduledTime = Carbon::now()->addDays(2);
        $this->actingAs($agentMgrC)->post("/admin/agents/announcements/{$announcement->id}/schedule", [
            'scheduled_at' => $rescheduledTime->format('Y-m-d\TH:i'),
        ])->assertRedirect();

        $announcement->refresh();
        $this->assertSame('scheduled', $announcement->status);
        $this->assertSame($agentMgrC->id, $announcement->scheduled_by); // Authority transferred to C

        // Step 4: Cancel schedule back to draft
        $this->actingAs($agentMgrC)->post("/admin/agents/announcements/{$announcement->id}/cancel-schedule")
            ->assertRedirect();

        $announcement->refresh();
        $this->assertSame('draft', $announcement->status);
        $this->assertNull($announcement->scheduled_at);
        $this->assertNull($announcement->scheduled_by);
    }

    public function test_scheduler_command_publishes_due_announcements_with_system_actor(): void
    {
        $creator = $this->createSuperAdmin();
        $scheduler = $this->createAgentManager();

        // Due announcement (scheduled 10 minutes ago)
        $due = AgentAnnouncement::create([
            'title_en' => 'Automated Scheduled Announcement',
            'content_en' => 'This announcement was scheduled for automatic release.',
            'target_audience' => 'all',
            'priority' => 'normal',
            'status' => 'scheduled',
            'scheduled_at' => Carbon::now()->subMinutes(10),
            'created_by' => $creator->id,
            'scheduled_by' => $scheduler->id,
        ]);

        // Future announcement (scheduled for tomorrow - should NOT publish)
        $future = AgentAnnouncement::create([
            'title_en' => 'Future Announcement',
            'content_en' => 'Should remain scheduled.',
            'target_audience' => 'all',
            'priority' => 'normal',
            'status' => 'scheduled',
            'scheduled_at' => Carbon::now()->addDay(),
            'created_by' => $creator->id,
            'scheduled_by' => $scheduler->id,
        ]);

        // Run lifecycle artisan command
        $this->artisan('agents:process-announcement-lifecycle')
            ->assertSuccessful();

        $due->refresh();
        $this->assertSame('published', $due->status);
        $this->assertNotNull($due->published_at);
        $this->assertNull($due->published_by); // Preserves null published_by for system action
        $this->assertSame($scheduler->id, $due->scheduled_by); // Preserves scheduling authorizer

        $future->refresh();
        $this->assertSame('scheduled', $future->status);

        // Verify audit log has user_id = null and action = agent_announcement_published_scheduled
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'agent_announcement_published_scheduled',
            'user_id' => null,
            'auditable_type' => AgentAnnouncement::class,
            'auditable_id' => $due->id,
        ]);

        // Verify idempotency on second run
        $this->artisan('agents:process-announcement-lifecycle')
            ->expectsOutput('Announcement lifecycle processed: 0 published, 0 expired.')
            ->assertSuccessful();
    }

    public function test_scheduler_command_automatically_expires_overdue_published_announcements(): void
    {
        $creator = $this->createSuperAdmin();

        $expiredAnnouncement = AgentAnnouncement::create([
            'title_en' => 'Flash Sale Ending Soon',
            'content_en' => 'Discount applies until 1 hour ago.',
            'target_audience' => 'all',
            'priority' => 'normal',
            'status' => 'published',
            'published_at' => Carbon::now()->subDays(2),
            'expires_at' => Carbon::now()->subHour(),
            'created_by' => $creator->id,
            'published_by' => $creator->id,
        ]);

        $this->artisan('agents:process-announcement-lifecycle')
            ->assertSuccessful();

        $expiredAnnouncement->refresh();
        $this->assertSame('archived', $expiredAnnouncement->status);
        $this->assertSame('system_expiry', $expiredAnnouncement->archived_reason);
        $this->assertNull($expiredAnnouncement->archived_by); // System expiry sets archived_by = null

        // Verify audit log has user_id = null and action = agent_announcement_expired_system
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'agent_announcement_expired_system',
            'user_id' => null,
            'auditable_type' => AgentAnnouncement::class,
            'auditable_id' => $expiredAnnouncement->id,
        ]);
    }

    public function test_can_manually_archive_published_announcement(): void
    {
        $staff = $this->createAgentManager();

        $announcement = AgentAnnouncement::create([
            'title_en' => 'Old Campaign Notice',
            'content_en' => 'Archiving this announcement manually.',
            'target_audience' => 'all',
            'priority' => 'normal',
            'status' => 'published',
            'published_at' => Carbon::now()->subWeek(),
            'created_by' => $staff->id,
            'published_by' => $staff->id,
        ]);

        $this->actingAs($staff)->post("/admin/agents/announcements/{$announcement->id}/archive")
            ->assertRedirect();

        $announcement->refresh();
        $this->assertSame('archived', $announcement->status);
        $this->assertSame('manual', $announcement->archived_reason);
        $this->assertSame($staff->id, $announcement->archived_by);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'agent_announcement_archived',
            'user_id' => $staff->id,
            'auditable_type' => AgentAnnouncement::class,
            'auditable_id' => $announcement->id,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | 3. Audience Segregation & Eligibility Tests
    |--------------------------------------------------------------------------
    */

    public function test_audience_segregation_in_feed_and_read_action(): void
    {
        $staff = $this->createAgentManager();
        $hla = $this->createAgent(UserRole::HIGH_LEVEL_AGENT);
        $lla = $this->createAgent(UserRole::LOW_LEVEL_AGENT);

        $allAnnouncement = AgentAnnouncement::create([
            'title_en' => 'Notice for Everyone',
            'content_en' => 'Content for both tiers.',
            'target_audience' => 'all',
            'priority' => 'normal',
            'status' => 'published',
            'published_at' => now(),
            'created_by' => $staff->id,
        ]);

        $hlaAnnouncement = AgentAnnouncement::create([
            'title_en' => 'HLA Exclusive Leadership Update',
            'content_en' => 'Sensitive upline commission structure.',
            'target_audience' => 'high_level_agent',
            'priority' => 'important',
            'status' => 'published',
            'published_at' => now(),
            'created_by' => $staff->id,
        ]);

        $llaAnnouncement = AgentAnnouncement::create([
            'title_en' => 'LLA Exclusive Sales Target',
            'content_en' => 'Direct selling bonuses.',
            'target_audience' => 'low_level_agent',
            'priority' => 'normal',
            'status' => 'published',
            'published_at' => now(),
            'created_by' => $staff->id,
        ]);

        $service = app(AgentAnnouncementService::class);

        // HLA Feed checks
        $hlaFeed = $service->getFeedForAgent($hla)->items();
        $hlaIds = collect($hlaFeed)->pluck('id')->toArray();
        $this->assertContains($allAnnouncement->id, $hlaIds);
        $this->assertContains($hlaAnnouncement->id, $hlaIds);
        $this->assertNotContains($llaAnnouncement->id, $hlaIds);

        // LLA Feed checks
        $llaFeed = $service->getFeedForAgent($lla)->items();
        $llaIds = collect($llaFeed)->pluck('id')->toArray();
        $this->assertContains($allAnnouncement->id, $llaIds);
        $this->assertContains($llaAnnouncement->id, $llaIds);
        $this->assertNotContains($hlaAnnouncement->id, $llaIds);

        // IDOR Protection: LLA attempts to mark read on HLA announcement
        $response = $this->actingAs($lla)->postJson("/agent/announcements/{$hlaAnnouncement->id}/read");
        $response->assertForbidden();

        // HLA can mark read on HLA announcement
        $response = $this->actingAs($hla)->postJson("/agent/announcements/{$hlaAnnouncement->id}/read");
        $response->assertOk();
    }

    public function test_inactive_or_suspended_agent_cannot_access_announcements_or_mark_read(): void
    {
        $staff = $this->createAgentManager();
        $suspendedAgent = $this->createAgent(UserRole::HIGH_LEVEL_AGENT, 'suspended');

        $announcement = AgentAnnouncement::create([
            'title_en' => 'General Notice',
            'content_en' => 'Notice body.',
            'target_audience' => 'all',
            'priority' => 'normal',
            'status' => 'published',
            'published_at' => now(),
            'created_by' => $staff->id,
        ]);

        // Suspended agent is redirected to login by EnsureUserHasRole middleware
        $this->actingAs($suspendedAgent)->get('/agent/announcements')->assertRedirect('/login');

        // Service-level authoritative verification: suspended agent is strictly not eligible
        $this->assertFalse(app(AgentAnnouncementService::class)->isAgentEligible($suspendedAgent, $announcement));
    }

    /*
    |--------------------------------------------------------------------------
    | 4. Read Tracking, Badges & Analytics Tests
    |--------------------------------------------------------------------------
    */

    public function test_read_tracking_is_idempotent_and_unread_count_decrements(): void
    {
        $staff = $this->createAgentManager();
        $agent = $this->createAgent(UserRole::HIGH_LEVEL_AGENT);

        $announcement1 = AgentAnnouncement::create([
            'title_en' => 'Announcement 1',
            'content_en' => 'Body 1',
            'target_audience' => 'all',
            'priority' => 'normal',
            'status' => 'published',
            'published_at' => now(),
            'created_by' => $staff->id,
        ]);

        $announcement2 = AgentAnnouncement::create([
            'title_en' => 'Announcement 2',
            'content_en' => 'Body 2',
            'target_audience' => 'all',
            'priority' => 'normal',
            'status' => 'published',
            'published_at' => now(),
            'created_by' => $staff->id,
        ]);

        $service = app(AgentAnnouncementService::class);
        $this->assertSame(2, $service->getUnreadCountForAgent($agent));

        // Mark announcement 1 as read
        $response = $this->actingAs($agent)->postJson("/agent/announcements/{$announcement1->id}/read");
        $response->assertOk();
        $response->assertJson(['unread_count' => 1]);

        $this->assertSame(1, $service->getUnreadCountForAgent($agent));
        $this->assertDatabaseCount('agent_announcement_reads', 1);

        // Mark announcement 1 as read again (duplicate idempotency)
        $response2 = $this->actingAs($agent)->postJson("/agent/announcements/{$announcement1->id}/read");
        $response2->assertOk();
        $this->assertDatabaseCount('agent_announcement_reads', 1); // No duplicate row
    }

    public function test_urgent_modal_list_returns_until_acknowledged(): void
    {
        $staff = $this->createAgentManager();
        $agent = $this->createAgent(UserRole::HIGH_LEVEL_AGENT);

        $urgent = AgentAnnouncement::create([
            'title_en' => 'Critical Policy Change',
            'content_en' => 'You must acknowledge this immediately.',
            'target_audience' => 'all',
            'priority' => 'urgent',
            'status' => 'published',
            'published_at' => now(),
            'created_by' => $staff->id,
        ]);

        $service = app(AgentAnnouncementService::class);
        $urgentList = $service->getUnreadUrgentAnnouncements($agent);
        $this->assertCount(1, $urgentList);

        // Acknowledge read
        $service->recordRead($urgent, $agent);

        // Verify it disappears from urgent modal list permanently
        $this->assertCount(0, $service->getUnreadUrgentAnnouncements($agent));
    }

    public function test_read_analytics_computes_active_network_cohort_accurately(): void
    {
        $staff = $this->createAgentManager();

        // 3 active HLAs, 1 suspended HLA
        $hla1 = $this->createAgent(UserRole::HIGH_LEVEL_AGENT, 'active');
        $hla2 = $this->createAgent(UserRole::HIGH_LEVEL_AGENT, 'active');
        $hla3 = $this->createAgent(UserRole::HIGH_LEVEL_AGENT, 'active');
        $suspendedHla = $this->createAgent(UserRole::HIGH_LEVEL_AGENT, 'suspended');

        $announcement = AgentAnnouncement::create([
            'title_en' => 'Leadership Briefing',
            'content_en' => 'For HLA only.',
            'target_audience' => 'high_level_agent',
            'priority' => 'normal',
            'status' => 'published',
            'published_at' => now(),
            'created_by' => $staff->id,
        ]);

        $service = app(AgentAnnouncementService::class);

        // Initial analytics: 0 / 3 active agents (0%)
        $analytics0 = $service->getReadAnalytics($announcement);
        $this->assertSame(3, $analytics0['eligible_agents_count']);
        $this->assertSame(0, $analytics0['active_reads_count']);
        $this->assertSame(0.0, $analytics0['read_percentage']);

        // hla1 and hla2 read it
        $service->recordRead($announcement, $hla1);
        $service->recordRead($announcement, $hla2);

        // Analytics: 2 / 3 active agents (66.7%)
        $analytics1 = $service->getReadAnalytics($announcement);
        $this->assertSame(3, $analytics1['eligible_agents_count']);
        $this->assertSame(2, $analytics1['active_reads_count']);
        $this->assertSame(66.7, $analytics1['read_percentage']);
    }

    /*
    |--------------------------------------------------------------------------
    | 5. Immutability, Localization & Fallback Tests
    |--------------------------------------------------------------------------
    */

    public function test_target_audience_is_strictly_immutable_once_published(): void
    {
        $staff = $this->createAgentManager();

        $announcement = AgentAnnouncement::create([
            'title_en' => 'Fixed Target Broadcast',
            'content_en' => 'Audience cannot change once live.',
            'target_audience' => 'all',
            'priority' => 'normal',
            'status' => 'published',
            'published_at' => now(),
            'created_by' => $staff->id,
        ]);

        $service = app(AgentAnnouncementService::class);

        $this->expectException(ValidationException::class);
        $service->update($announcement, [
            'title_en' => 'Fixed Target Broadcast Updated',
            'content_en' => 'New content',
            'target_audience' => 'high_level_agent', // Mutating audience is blocked
        ], $staff);
    }

    public function test_bilingual_accessors_and_fallback_logic(): void
    {
        $staff = $this->createAgentManager();

        $bilingual = AgentAnnouncement::create([
            'title_en' => 'English Title',
            'title_si' => 'සිංහල මාතෘකාව',
            'content_en' => 'English Content',
            'content_si' => 'සිංහල අන්තර්ගතය',
            'target_audience' => 'all',
            'priority' => 'normal',
            'status' => 'published',
            'published_at' => now(),
            'created_by' => $staff->id,
        ]);

        $englishOnly = AgentAnnouncement::create([
            'title_en' => 'English Only Title',
            'title_si' => null,
            'content_en' => 'English Only Content',
            'content_si' => null,
            'target_audience' => 'all',
            'priority' => 'normal',
            'status' => 'published',
            'published_at' => now(),
            'created_by' => $staff->id,
        ]);

        // When locale is English ('en')
        app()->setLocale('en');
        $this->assertSame('English Title', $bilingual->localized_title);
        $this->assertSame('English Content', $bilingual->localized_content);
        $this->assertSame('English Only Title', $englishOnly->localized_title);

        // When locale is Sinhala ('si')
        app()->setLocale('si');
        $this->assertSame('සිංහල මාතෘකාව', $bilingual->localized_title);
        $this->assertSame('සිංහල අන්තර්ගතය', $bilingual->localized_content);
        // Fallback test: englishOnly returns English content when Sinhala is null
        $this->assertSame('English Only Title', $englishOnly->localized_title);
        $this->assertSame('English Only Content', $englishOnly->localized_content);

        // Restore locale
        app()->setLocale('en');
    }

    /*
    |--------------------------------------------------------------------------
    | 6. Validation, XSS & Security Edge Cases
    |--------------------------------------------------------------------------
    */

    public function test_scheduling_in_the_past_is_rejected(): void
    {
        $staff = $this->createAgentManager();
        $announcement = AgentAnnouncement::create([
            'title_en' => 'Past Promo',
            'content_en' => 'Cannot be scheduled in the past.',
            'target_audience' => 'all',
            'priority' => 'normal',
            'status' => 'draft',
            'created_by' => $staff->id,
        ]);

        $pastTime = Carbon::now()->subMinute();

        $this->actingAs($staff)->post("/admin/agents/announcements/{$announcement->id}/schedule", [
            'scheduled_at' => $pastTime->format('Y-m-d\TH:i'),
        ])->assertSessionHasErrors(['scheduled_at']);
    }

    public function test_cancelling_schedule_on_non_scheduled_announcement_is_rejected(): void
    {
        $staff = $this->createAgentManager();
        $draft = AgentAnnouncement::create([
            'title_en' => 'Draft Item',
            'content_en' => 'Still a draft.',
            'target_audience' => 'all',
            'priority' => 'normal',
            'status' => 'draft',
            'created_by' => $staff->id,
        ]);

        $service = app(AgentAnnouncementService::class);
        $this->expectException(ValidationException::class);
        $service->cancelSchedule($draft, $staff);
    }

    public function test_only_draft_announcements_can_be_deleted(): void
    {
        $staff = $this->createAgentManager();

        $draft = AgentAnnouncement::create([
            'title_en' => 'Draft to Delete',
            'content_en' => 'Will be deleted.',
            'target_audience' => 'all',
            'priority' => 'normal',
            'status' => 'draft',
            'created_by' => $staff->id,
        ]);

        $published = AgentAnnouncement::create([
            'title_en' => 'Live Broadcast',
            'content_en' => 'Cannot be deleted.',
            'target_audience' => 'all',
            'priority' => 'normal',
            'status' => 'published',
            'published_at' => now(),
            'created_by' => $staff->id,
        ]);

        $service = app(AgentAnnouncementService::class);

        // Deleting draft succeeds
        $this->assertTrue($service->deleteDraft($draft));
        $this->assertDatabaseMissing('agent_announcements', ['id' => $draft->id]);

        // Deleting published item throws ValidationException
        $this->expectException(ValidationException::class);
        $service->deleteDraft($published);
    }

    public function test_xss_payload_in_title_and_content_is_safely_persisted_without_execution(): void
    {
        $staff = $this->createAgentManager();
        $agent = $this->createAgent(UserRole::HIGH_LEVEL_AGENT);

        $xssTitle = "<script>alert('XSS-Title')</script>";
        $xssContent = "Hello <img src='x' onerror='alert(\"XSS-Img\")'> **Bold** [Link](javascript:alert(1))";

        $announcement = AgentAnnouncement::create([
            'title_en' => $xssTitle,
            'content_en' => $xssContent,
            'target_audience' => 'all',
            'priority' => 'normal',
            'status' => 'published',
            'published_at' => now(),
            'created_by' => $staff->id,
        ]);

        // Verify stored as raw literal string, not executed
        $this->assertSame($xssTitle, $announcement->title_en);
        $this->assertSame($xssContent, $announcement->content_en);

        // Verify agent feed retrieves it as text
        $service = app(AgentAnnouncementService::class);
        $feed = $service->getFeedForAgent($agent)->items();
        $this->assertSame($xssTitle, $feed[0]->localized_title);
        $this->assertSame($xssContent, $feed[0]->localized_content);
    }

    public function test_feed_ordering_respects_pinned_and_tie_breaker(): void
    {
        $staff = $this->createAgentManager();
        $agent = $this->createAgent(UserRole::HIGH_LEVEL_AGENT);

        // Normal 1
        $item1 = AgentAnnouncement::create([
            'title_en' => 'Normal 1',
            'content_en' => 'Content 1',
            'target_audience' => 'all',
            'priority' => 'normal',
            'status' => 'published',
            'is_pinned' => false,
            'published_at' => Carbon::now()->subMinutes(10),
            'created_by' => $staff->id,
        ]);

        // Pinned with pin_order 2
        $item2 = AgentAnnouncement::create([
            'title_en' => 'Pinned Order 2',
            'content_en' => 'Content 2',
            'target_audience' => 'all',
            'priority' => 'normal',
            'status' => 'published',
            'is_pinned' => true,
            'pin_order' => 2,
            'published_at' => Carbon::now()->subMinutes(5),
            'created_by' => $staff->id,
        ]);

        // Pinned with pin_order 1 (should be first)
        $item3 = AgentAnnouncement::create([
            'title_en' => 'Pinned Order 1',
            'content_en' => 'Content 3',
            'target_audience' => 'all',
            'priority' => 'urgent',
            'status' => 'published',
            'is_pinned' => true,
            'pin_order' => 1,
            'published_at' => Carbon::now()->subMinutes(20),
            'created_by' => $staff->id,
        ]);

        $service = app(AgentAnnouncementService::class);
        $feed = $service->getFeedForAgent($agent)->items();

        $this->assertSame($item3->id, $feed[0]->id); // Pinned order 1
        $this->assertSame($item2->id, $feed[1]->id); // Pinned order 2
        $this->assertSame($item1->id, $feed[2]->id); // Normal published
    }
}
