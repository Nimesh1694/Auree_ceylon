<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SuperAdminProtectionTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;
    protected User $superAdmin;
    protected Role $superAdminRole;
    protected Role $ownerRole;

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

        $this->ownerRole = Role::where('name', 'owner')->first();
        $this->superAdminRole = Role::where('name', 'super_admin')->first();
    }

    /**
     * 1. Owner CANNOT suspend or deactivate the Super Admin account.
     */
    public function test_owner_cannot_suspend_super_admin(): void
    {
        $response = $this->actingAs($this->owner)->post(route('admin.staff.status', $this->superAdmin->id));
        $response->assertStatus(403);

        $this->assertEquals(UserStatus::ACTIVE, $this->superAdmin->fresh()->status);
    }

    /**
     * 2. Owner CANNOT delete the Super Admin account via Eloquent hook.
     */
    public function test_super_admin_cannot_be_deleted_via_model_hook(): void
    {
        $this->actingAs($this->owner);

        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);
        $this->superAdmin->delete();
    }

    /**
     * 3. Owner CANNOT change the Super Admin role or demote them.
     */
    public function test_owner_cannot_change_super_admin_role(): void
    {
        $marketingRole = Role::where('name', 'marketing_manager')->first();

        $response = $this->actingAs($this->owner)->put(route('admin.staff.update', $this->superAdmin->id), [
            'name' => 'Altered Admin',
            'email' => $this->superAdmin->email,
            'phone' => '0771234567',
            'role_id' => $marketingRole->id,
        ]);

        $response->assertStatus(403);
        $this->assertEquals('super_admin', $this->superAdmin->fresh()->getRoleValue());
    }

    /**
     * 4. Owner CANNOT edit Super Admin details or password.
     */
    public function test_owner_cannot_edit_super_admin_details_or_password(): void
    {
        $originalHash = $this->superAdmin->password;

        $response = $this->actingAs($this->owner)->put(route('admin.staff.update', $this->superAdmin->id), [
            'name' => 'Hijacked Admin',
            'email' => 'hijacked@auraluxe.lk',
            'phone' => '0779999999',
            'role_id' => $this->superAdminRole->id,
            'password' => 'NewCompromisedPass123!',
        ]);

        $response->assertStatus(403);
        $fresh = $this->superAdmin->fresh();
        $this->assertEquals($this->superAdmin->name, $fresh->name);
        $this->assertEquals($this->superAdmin->email, $fresh->email);
        $this->assertEquals($originalHash, $fresh->password);
    }

    /**
     * 5. Owner CANNOT reset Super Admin MFA secret.
     */
    public function test_owner_cannot_reset_super_admin_mfa(): void
    {
        $this->superAdmin->update([
            'two_factor_secret' => 'encrypted_secret_sample',
            'two_factor_confirmed_at' => now(),
        ]);

        $response = $this->actingAs($this->owner)->post(route('admin.staff.reset-mfa', $this->superAdmin->id));
        $response->assertStatus(403);

        $this->assertNotNull($this->superAdmin->fresh()->two_factor_secret);
    }

    /**
     * 6. Owner CANNOT terminate Super Admin active sessions.
     */
    public function test_owner_cannot_terminate_super_admin_sessions(): void
    {
        DB::table('sessions')->insert([
            'id' => 'super_admin_active_session_1',
            'user_id' => $this->superAdmin->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'AdminBrowser/1.0',
            'payload' => 'dummy',
            'last_activity' => time(),
        ]);

        $response = $this->actingAs($this->owner)->post(route('admin.staff.sessions', $this->superAdmin->id));
        $response->assertStatus(403);

        $this->assertDatabaseHas('sessions', ['id' => 'super_admin_active_session_1']);
    }

    /**
     * 7. Owner CANNOT modify Super Admin protected role permissions.
     */
    public function test_owner_cannot_modify_super_admin_role_permissions(): void
    {
        $response = $this->actingAs($this->owner)->put(route('admin.roles.update', $this->superAdminRole->id), [
            'display_name' => 'Weakened Super Admin',
            'permissions' => [],
        ]);

        $response->assertStatus(403);
    }

    /**
     * 8. Super Admin CANNOT suspend or deactivate the Owner account.
     */
    public function test_super_admin_cannot_suspend_owner(): void
    {
        $response = $this->actingAs($this->superAdmin)->post(route('admin.staff.status', $this->owner->id));
        $response->assertStatus(403);

        $this->assertEquals(UserStatus::ACTIVE, $this->owner->fresh()->status);
    }

    /**
     * 9. Super Admin CANNOT delete the Owner account via model hook.
     */
    public function test_owner_cannot_be_deleted_via_model_hook(): void
    {
        $this->actingAs($this->superAdmin);

        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);
        $this->owner->delete();
    }

    /**
     * 10. Super Admin CANNOT change the Owner role or demote them.
     */
    public function test_super_admin_cannot_change_owner_role(): void
    {
        $marketingRole = Role::where('name', 'marketing_manager')->first();

        $response = $this->actingAs($this->superAdmin)->put(route('admin.staff.update', $this->owner->id), [
            'name' => 'Dilshan Demoted',
            'email' => $this->owner->email,
            'phone' => '0771234567',
            'role_id' => $marketingRole->id,
        ]);

        $response->assertStatus(403);
        $this->assertEquals('owner', $this->owner->fresh()->getRoleValue());
    }

    /**
     * 11. Super Admin CANNOT reset Owner password or MFA.
     */
    public function test_super_admin_cannot_reset_owner_mfa_or_password(): void
    {
        $this->owner->update([
            'two_factor_secret' => 'owner_secret_123',
            'two_factor_confirmed_at' => now(),
        ]);

        $response = $this->actingAs($this->superAdmin)->post(route('admin.staff.reset-mfa', $this->owner->id));
        $response->assertStatus(403);

        $this->assertNotNull($this->owner->fresh()->two_factor_secret);
    }

    /**
     * 12. Super Admin CANNOT terminate Owner sessions.
     */
    public function test_super_admin_cannot_terminate_owner_sessions(): void
    {
        DB::table('sessions')->insert([
            'id' => 'owner_active_session_1',
            'user_id' => $this->owner->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'OwnerBrowser/1.0',
            'payload' => 'dummy',
            'last_activity' => time(),
        ]);

        $response = $this->actingAs($this->superAdmin)->post(route('admin.staff.sessions', $this->owner->id));
        $response->assertStatus(403);

        $this->assertDatabaseHas('sessions', ['id' => 'owner_active_session_1']);
    }

    /**
     * 13. Super Admin CANNOT approve payouts directly (financial authority reserved for Owner).
     */
    public function test_super_admin_cannot_approve_payouts(): void
    {
        $agent = User::factory()->create(['role' => UserRole::LOW_LEVEL_AGENT]);
        $payout = \App\Models\PayoutRequest::create([
            'reference_number' => 'PO-TEST-BOUNDARY-1',
            'agent_id' => $agent->id,
            'amount' => 5000.00,
            'currency' => 'LKR',
            'status' => 'pending',
            'bank_name' => 'Commercial Bank',
            'bank_branch' => 'Colombo',
            'bank_account_number' => '1234567890',
            'bank_account_name' => 'Agent Name',
        ]);

        $response = $this->actingAs($this->superAdmin)->post(route('admin.payouts.approve', $payout->id));
        $response->assertStatus(403);
    }

    /**
     * 14. Super Admin CAN manage ordinary staff accounts, terminate their sessions, and reset MFA.
     */
    public function test_super_admin_can_manage_ordinary_staff(): void
    {
        $marketingRole = Role::where('name', 'marketing_manager')->first();
        $staff = User::factory()->create([
            'role' => UserRole::MARKETING_MANAGER,
            'role_id' => $marketingRole->id,
            'status' => UserStatus::ACTIVE,
            'two_factor_secret' => 'staff_secret',
            'two_factor_confirmed_at' => now(),
        ]);

        DB::table('sessions')->insert([
            'id' => 'staff_session_1',
            'user_id' => $staff->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'StaffBrowser/1.0',
            'payload' => 'dummy',
            'last_activity' => time(),
        ]);

        // Terminate session
        $responseKill = $this->actingAs($this->superAdmin)->post(route('admin.staff.sessions', $staff->id));
        $responseKill->assertRedirect();
        $this->assertDatabaseMissing('sessions', ['id' => 'staff_session_1']);

        // Reset MFA
        $responseMfa = $this->actingAs($this->superAdmin)->post(route('admin.staff.reset-mfa', $staff->id));
        $responseMfa->assertRedirect();
        $this->assertNull($staff->fresh()->two_factor_secret);

        // Suspend staff
        $responseSuspend = $this->actingAs($this->superAdmin)->post(route('admin.staff.status', $staff->id));
        $responseSuspend->assertRedirect();
        $this->assertEquals(UserStatus::SUSPENDED, $staff->fresh()->status);
    }

    /**
     * 15. Emergency Recovery Command restores Super Admin credentials, purges sessions, and writes audit record.
     */
    public function test_emergency_recovery_cli_command_resets_super_admin(): void
    {
        $this->superAdmin->update([
            'two_factor_secret' => 'old_secret',
            'two_factor_confirmed_at' => now(),
            'status' => UserStatus::ACTIVE,
        ]);

        DB::table('sessions')->insert([
            'id' => 'compromised_session_xyz',
            'user_id' => $this->superAdmin->id,
            'ip_address' => '192.168.1.1',
            'user_agent' => 'CompromisedClient',
            'payload' => 'dummy',
            'last_activity' => time(),
        ]);

        $this->artisan('admin:recover-super-admin', [
            'email' => $this->superAdmin->email,
            '--force' => true,
            '--reason' => 'Compromised credentials rotation by IT Infrastructure team',
        ])->assertExitCode(0);

        $fresh = $this->superAdmin->fresh();
        $this->assertNull($fresh->two_factor_secret);
        $this->assertNull($fresh->two_factor_confirmed_at);
        $this->assertEquals(UserStatus::ACTIVE, $fresh->status);

        // Verify session was terminated
        $this->assertDatabaseMissing('sessions', ['id' => 'compromised_session_xyz']);

        // Verify audit log record exists
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'super_admin_emergency_recovery',
            'auditable_type' => User::class,
            'auditable_id' => $this->superAdmin->id,
        ]);
    }
}
