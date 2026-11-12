<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TechnicalConsoleTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;
    protected User $superAdmin;
    protected User $agent;
    protected User $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->owner = User::where('role', UserRole::OWNER)->first()
            ?? User::factory()->create([
                'name' => 'Dilshan Business Owner',
                'email' => 'owner@auraluxe.lk',
                'role' => UserRole::OWNER,
                'protected_authority' => 'business',
                'status' => UserStatus::ACTIVE,
            ]);

        $this->superAdmin = User::where('role', UserRole::SUPER_ADMIN)->first()
            ?? User::factory()->create([
                'name' => 'Naveen Super Admin',
                'email' => 'it@auraluxe.lk',
                'role' => UserRole::SUPER_ADMIN,
                'protected_authority' => 'technical',
                'status' => UserStatus::ACTIVE,
            ]);

        $this->agent = User::factory()->create([
            'role' => UserRole::HIGH_LEVEL_AGENT,
            'status' => UserStatus::ACTIVE,
        ]);

        $this->customer = User::factory()->create([
            'role' => UserRole::CUSTOMER,
            'status' => UserStatus::ACTIVE,
        ]);
    }

    /**
     * 1. Super Admin lands on Technical Console by default.
     */
    public function test_super_admin_renders_technical_console_by_default(): void
    {
        $response = $this->actingAs($this->superAdmin)->get(route('admin.dashboard'));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/TechnicalDashboard')
            ->has('staffMetrics')
            ->has('sessionTelemetry')
            ->has('securityEvents')
            ->has('runtime')
            ->has('storageMetrics')
            ->has('accessControl')
            ->where('isSuperAdmin', true)
        );
    }

    /**
     * 2. Owner lands on Executive Commercial Dashboard by default.
     */
    public function test_owner_renders_executive_dashboard_by_default(): void
    {
        $response = $this->actingAs($this->owner)->get(route('admin.dashboard'));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Dashboard')
            ->has('stats')
            ->has('executiveMetrics')
            ->has('financialOverview')
            ->has('orderPipeline')
        );
    }

    /**
     * 3. Owner can explicitly switch to Technical Console via query param ?view=technical.
     */
    public function test_owner_can_switch_to_technical_console(): void
    {
        $response = $this->actingAs($this->owner)->get(route('admin.dashboard', ['view' => 'technical']));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/TechnicalDashboard')
            ->where('isOwner', true)
        );
    }

    /**
     * 4. Super Admin can switch to Commercial Overview via query param ?view=commercial.
     */
    public function test_super_admin_can_switch_to_commercial_overview(): void
    {
        $response = $this->actingAs($this->superAdmin)->get(route('admin.dashboard', ['view' => 'commercial']));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Dashboard')
        );
    }

    /**
     * 5. Super Admin can clear application cache and audit record is created.
     */
    public function test_super_admin_can_clear_cache(): void
    {
        $response = $this->actingAs($this->superAdmin)->post(route('admin.system.cache.clear'));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'application_cache_cleared',
        ]);
    }

    /**
     * 6. Owner can clear application cache.
     */
    public function test_owner_can_clear_cache(): void
    {
        $response = $this->actingAs($this->owner)->post(route('admin.system.cache.clear'));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'application_cache_cleared',
        ]);
    }

    /**
     * 7. Unauthorized roles (Agent, Customer) cannot clear application cache.
     */
    public function test_unauthorized_roles_cannot_clear_cache(): void
    {
        $responseAgent = $this->actingAs($this->agent)->post(route('admin.system.cache.clear'));
        $responseAgent->assertStatus(403);

        $responseCustomer = $this->actingAs($this->customer)->post(route('admin.system.cache.clear'));
        $responseCustomer->assertStatus(403);
    }

    /**
     * 8. Active sessions query returns accurate telemetry.
     */
    public function test_active_sessions_telemetry_reflects_database_sessions(): void
    {
        DB::table('sessions')->insert([
            'id' => 'sample_telemetry_sess_1',
            'user_id' => $this->superAdmin->id,
            'ip_address' => '192.168.1.100',
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0',
            'payload' => 'sample',
            'last_activity' => time() - 300,
        ]);

        $response = $this->actingAs($this->superAdmin)->get(route('admin.dashboard'));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/TechnicalDashboard')
            ->where('sessionTelemetry.active_sessions', fn ($count) => $count >= 1)
        );
    }

    /**
     * 9. Staff directory page loads for Super Admin with staff metrics.
     */
    public function test_staff_directory_loads_with_full_props(): void
    {
        $response = $this->actingAs($this->superAdmin)->get(route('admin.staff.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Staff/Index')
            ->has('staff')
            ->has('availableRoles')
            ->where('isTechnicalAuthority', true)
        );
    }

    /**
     * 10. Roles matrix page loads for Super Admin with permission groups.
     */
    public function test_roles_matrix_loads_with_full_props(): void
    {
        $response = $this->actingAs($this->superAdmin)->get(route('admin.roles.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Roles/Index')
            ->has('roles')
            ->has('permissions')
            ->has('permissionsByModule')
            ->has('pendingRequestsCount')
        );
    }
}

