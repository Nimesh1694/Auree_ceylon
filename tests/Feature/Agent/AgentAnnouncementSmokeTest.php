<?php

namespace Tests\Feature\Agent;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\AgentAnnouncement;
use App\Models\AgentProfile;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AgentAnnouncementSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected User $manager;
    protected User $hla;
    protected User $lla;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        // 1. Manager
        $this->manager = User::factory()->create([
            'email' => 'manager_smoke@auraluxe.lk',
            'role' => UserRole::AGENT_MANAGER->value,
            'status' => UserStatus::ACTIVE,
        ]);
        $agentMgrRole = Role::where('name', UserRole::AGENT_MANAGER->value)->first();
        if ($agentMgrRole) {
            $this->manager->update(['role_id' => $agentMgrRole->id]);
            $this->manager->assignedRoles()->syncWithoutDetaching([$agentMgrRole->id]);
        }

        // 2. HLA Agent
        $this->hla = User::factory()->create([
            'email' => 'hla_smoke@auraluxe.lk',
            'role' => UserRole::HIGH_LEVEL_AGENT->value,
            'status' => UserStatus::ACTIVE,
        ]);
        AgentProfile::create([
            'user_id' => $this->hla->id,
            'business_name' => 'Kasun Luxe Cosmetics',
            'nic' => '199212345678',
            'address' => '45 Kandy Road, Colombo',
            'kyc_status' => 'verified',
            'wallet_balance' => 25000.00,
            'reserved_balance' => 0.00,
        ]);

        // 3. LLA Agent
        $this->lla = User::factory()->create([
            'email' => 'lla_smoke@auraluxe.lk',
            'role' => UserRole::LOW_LEVEL_AGENT->value,
            'status' => UserStatus::ACTIVE,
        ]);
        AgentProfile::create([
            'user_id' => $this->lla->id,
            'business_name' => 'Amali Glow Care',
            'nic' => '199587654321',
            'address' => '78 High Level Road, Maharagama',
            'kyc_status' => 'verified',
            'wallet_balance' => 8500.00,
            'reserved_balance' => 0.00,
        ]);
    }

    public function test_admin_console_index_renders_inertia_component_with_counts(): void
    {
        AgentAnnouncement::create([
            'title_en' => 'Existing Notice',
            'content_en' => 'Notice body',
            'target_audience' => 'all',
            'priority' => 'normal',
            'status' => 'published',
            'published_at' => now(),
            'created_by' => $this->manager->id,
            'published_by' => $this->manager->id,
        ]);

        $response = $this->actingAs($this->manager)->get('/admin/agents/announcements');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Agents/Announcements/Index')
            ->has('announcements.data', 1)
            ->has('counts')
            ->where('counts.published', 1)
        );
    }

    public function test_admin_create_page_renders_with_inertia_component(): void
    {
        $response = $this->actingAs($this->manager)->get('/admin/agents/announcements/create');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Agents/Announcements/Create')
        );
    }

    public function test_admin_can_create_draft_and_publish_immediately(): void
    {
        // 1. Create Draft
        $createResponse = $this->actingAs($this->manager)->post('/admin/agents/announcements', [
            'title_en' => 'Smoke Draft Broadcast',
            'title_si' => 'දුම් කෙටුම්පත් විකාශනය',
            'content_en' => 'Testing draft creation workflow.',
            'content_si' => 'කෙටුම්පත් නිර්මාණය පරීක්ෂා කිරීම.',
            'target_audience' => 'all',
            'priority' => 'normal',
            'is_pinned' => false,
            'publish_action' => 'draft',
        ]);

        $createResponse->assertRedirect('/admin/agents/announcements?tab=draft');
        $draft = AgentAnnouncement::where('title_en', 'Smoke Draft Broadcast')->first();
        $this->assertNotNull($draft);
        $this->assertSame('draft', $draft->status);

        // 2. Publish Draft
        $publishResponse = $this->actingAs($this->manager)->post("/admin/agents/announcements/{$draft->id}/publish");
        $publishResponse->assertRedirect();

        $draft->refresh();
        $this->assertSame('published', $draft->status);
        $this->assertSame($this->manager->id, $draft->published_by);
    }

    public function test_admin_read_analytics_endpoint_returns_json_metrics(): void
    {
        $announcement = AgentAnnouncement::create([
            'title_en' => 'Broadcast for Analytics',
            'content_en' => 'Measuring audience reach.',
            'target_audience' => 'all',
            'priority' => 'normal',
            'status' => 'published',
            'published_at' => now(),
            'created_by' => $this->manager->id,
            'published_by' => $this->manager->id,
        ]);

        $response = $this->actingAs($this->manager)
            ->getJson("/admin/agents/announcements/{$announcement->id}/reads");

        $response->assertOk();
        $response->assertJsonStructure([
            'analytics' => [
                'eligible_agents_count',
                'active_reads_count',
                'read_percentage',
                'total_all_time_reads',
            ],
            'reads',
        ]);
    }

    public function test_hla_agent_feed_renders_urgent_modal_payload_and_exclusive_notices(): void
    {
        $urgentNotice = AgentAnnouncement::create([
            'title_en' => 'URGENT: Warehouse Maintenance',
            'title_si' => 'හදිසි: ගබඩා නඩත්තුව',
            'content_en' => 'Warehouse closed tomorrow.',
            'content_si' => 'හෙට ගබඩාව වසා ඇත.',
            'target_audience' => 'all',
            'priority' => 'urgent',
            'status' => 'published',
            'published_at' => now(),
            'created_by' => $this->manager->id,
            'published_by' => $this->manager->id,
        ]);

        $hlaNotice = AgentAnnouncement::create([
            'title_en' => 'HLA Exclusive Leadership Notice',
            'content_en' => 'For HLA eyes only.',
            'target_audience' => 'high_level_agent',
            'priority' => 'important',
            'status' => 'published',
            'published_at' => now(),
            'created_by' => $this->manager->id,
            'published_by' => $this->manager->id,
        ]);

        $response = $this->actingAs($this->hla)->get('/agent/announcements');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Agent/Announcements/Index')
            ->has('announcements.data', 2)
            ->has('urgentAnnouncements', 1)
            ->where('urgentAnnouncements.0.id', $urgentNotice->id)
            ->where('unreadCount', 2)
        );
    }

    public function test_lla_agent_feed_strictly_omits_hla_exclusive_notice(): void
    {
        AgentAnnouncement::create([
            'title_en' => 'General Storefront Notice',
            'content_en' => 'Notice for everyone.',
            'target_audience' => 'all',
            'priority' => 'normal',
            'status' => 'published',
            'published_at' => now(),
            'created_by' => $this->manager->id,
            'published_by' => $this->manager->id,
        ]);

        AgentAnnouncement::create([
            'title_en' => 'HLA Exclusive Leadership Notice',
            'content_en' => 'For HLA eyes only.',
            'target_audience' => 'high_level_agent',
            'priority' => 'important',
            'status' => 'published',
            'published_at' => now(),
            'created_by' => $this->manager->id,
            'published_by' => $this->manager->id,
        ]);

        $response = $this->actingAs($this->lla)->get('/agent/announcements');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Agent/Announcements/Index')
            ->has('announcements.data', 1) // Only 1 notice, NOT the HLA exclusive notice
            ->where('announcements.data.0.title_en', 'General Storefront Notice')
        );
    }

    public function test_mark_as_read_decrements_unread_count_and_persists_row(): void
    {
        $announcement = AgentAnnouncement::create([
            'title_en' => 'Notice to be Read',
            'content_en' => 'Mark as read test.',
            'target_audience' => 'all',
            'priority' => 'normal',
            'status' => 'published',
            'published_at' => now(),
            'created_by' => $this->manager->id,
            'published_by' => $this->manager->id,
        ]);

        // Before mark-read: unread count is 1
        $response = $this->actingAs($this->hla)->get('/agent/announcements');
        $response->assertInertia(fn (Assert $page) => $page->where('unreadCount', 1));

        // Mark as read
        $markResponse = $this->actingAs($this->hla)
            ->postJson("/agent/announcements/{$announcement->id}/read");

        $markResponse->assertOk();
        $markResponse->assertJson(['unread_count' => 0]);

        // After mark-read: unread count is 0, is_read is true
        $responseAfter = $this->actingAs($this->hla)->get('/agent/announcements');
        $responseAfter->assertInertia(fn (Assert $page) => $page
            ->where('unreadCount', 0)
            ->where('announcements.data.0.is_read', true)
        );
    }
}
