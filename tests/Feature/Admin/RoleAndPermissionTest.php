<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RolePermissionRequest;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RoleAndPermissionTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $superAdmin;
    private User $accountant;
    private User $marketingManager;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles and permissions
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->owner = User::where('role', 'owner')->first()
            ?? User::factory()->create(['role' => UserRole::OWNER, 'status' => UserStatus::ACTIVE]);

        $this->superAdmin = User::where('role', 'super_admin')->first()
            ?? User::factory()->create(['role' => UserRole::SUPER_ADMIN, 'status' => UserStatus::ACTIVE]);

        $this->accountant = User::where('role', 'accountant')->first()
            ?? User::factory()->create(['role' => UserRole::ACCOUNTANT, 'status' => UserStatus::ACTIVE]);

        $this->marketingManager = User::where('role', 'marketing_manager')->first()
            ?? User::factory()->create(['role' => UserRole::MARKETING_MANAGER, 'status' => UserStatus::ACTIVE]);
    }

    public function test_owner_has_universal_permissions_by_default(): void
    {
        $this->assertTrue($this->owner->isOwner());
        $this->assertTrue($this->owner->hasPermissionTo('payouts.approve'));
        $this->assertTrue($this->owner->hasPermissionTo('users.create'));
        $this->assertTrue($this->owner->hasPermissionTo('system.maintenance.manage'));
    }

    public function test_owner_account_cannot_be_deleted_via_model_hook(): void
    {
        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);
        $this->owner->delete();
    }

    public function test_super_admin_cannot_edit_owner_account(): void
    {
        $ownerRoleId = Role::where('name', 'owner')->value('id');

        $response = $this->actingAs($this->superAdmin)->put(route('admin.staff.update', $this->owner->id), [
            'name' => 'Compromised Name',
            'email' => 'compromised@auraluxe.lk',
            'phone' => '0779999999',
            'role_id' => $ownerRoleId,
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseHas('users', [
            'id' => $this->owner->id,
            'email' => $this->owner->email,
        ]);
    }

    public function test_super_admin_cannot_suspend_owner_account(): void
    {
        $response = $this->actingAs($this->superAdmin)->post(route('admin.staff.status', $this->owner->id));
        $response->assertStatus(403);

        $this->assertEquals(UserStatus::ACTIVE, $this->owner->fresh()->status);
    }

    public function test_owner_role_is_protected_from_modification(): void
    {
        $ownerRole = Role::where('name', 'owner')->first();

        // Attempt to update
        $response = $this->actingAs($this->superAdmin)->put(route('admin.roles.update', $ownerRole->id), [
            'display_name' => 'Demoted Owner',
            'permissions' => [],
        ]);
        $response->assertStatus(403);

        // Attempt to delete
        $delResponse = $this->actingAs($this->superAdmin)->delete(route('admin.roles.destroy', $ownerRole->id));
        $delResponse->assertStatus(403);
    }

    public function test_super_admin_can_create_custom_role_with_operational_permissions(): void
    {
        $orderPerms = Permission::whereIn('name', ['orders.view', 'orders.status.update'])->pluck('id')->all();

        $response = $this->actingAs($this->superAdmin)->post(route('admin.roles.store'), [
            'name' => 'order_specialist',
            'display_name' => 'Order Specialist',
            'description' => 'Handles day-to-day order tracking',
            'permissions' => $orderPerms,
        ]);

        $response->assertRedirect(route('admin.roles.index'));
        $this->assertDatabaseHas('roles', [
            'name' => 'order_specialist',
            'display_name' => 'Order Specialist',
        ]);

        $role = Role::where('name', 'order_specialist')->first();
        $this->assertTrue($role->hasPermissionTo('orders.view'));
        $this->assertTrue($role->hasPermissionTo('orders.status.update'));
        $this->assertFalse($role->hasPermissionTo('payouts.approve'));
    }

    public function test_super_admin_cannot_directly_grant_restricted_financial_permissions(): void
    {
        $restrictedPerm = Permission::where('name', 'payouts.approve')->first();
        $ordersViewPerm = Permission::where('name', 'orders.view')->first();

        $response = $this->actingAs($this->superAdmin)->post(route('admin.roles.store'), [
            'name' => 'attempted_finance_role',
            'display_name' => 'Attempted Finance Role',
            'permissions' => [$ordersViewPerm->id, $restrictedPerm->id],
        ]);

        $response->assertRedirect(route('admin.roles.index'));

        $role = Role::where('name', 'attempted_finance_role')->first();
        $this->assertNotNull($role);

        // Operational permission granted
        $this->assertTrue($role->hasPermissionTo('orders.view'));

        // Restricted financial permission is NOT directly granted!
        $this->assertFalse($role->hasPermissionTo('payouts.approve'));

        // Instead, a Permission Change Request was created for Owner review
        $this->assertDatabaseHas('role_permission_requests', [
            'role_id' => $role->id,
            'permission_id' => $restrictedPerm->id,
            'status' => 'pending',
            'requested_by' => $this->superAdmin->id,
        ]);
    }

    public function test_super_admin_cannot_approve_their_own_permission_request(): void
    {
        $role = Role::create([
            'name' => 'custom_payout_role',
            'display_name' => 'Custom Payout Role',
            'is_system' => false,
        ]);

        $perm = Permission::where('name', 'payouts.approve')->first();

        $permReq = RolePermissionRequest::create([
            'role_id' => $role->id,
            'permission_id' => $perm->id,
            'requested_by' => $this->superAdmin->id,
            'status' => 'pending',
            'reason' => 'Need payout authority',
        ]);

        // Super Admin attempts self-approval -> 403 Forbidden
        $response = $this->actingAs($this->superAdmin)->post(
            route('admin.roles.permission-requests.approve', $permReq->id)
        );

        $response->assertStatus(403);
        $this->assertEquals('pending', $permReq->fresh()->status);
        $this->assertFalse($role->fresh()->hasPermissionTo('payouts.approve'));
    }

    public function test_owner_can_approve_permission_request_and_attach_to_role(): void
    {
        $role = Role::create([
            'name' => 'custom_authorized_role',
            'display_name' => 'Custom Authorized Role',
            'is_system' => false,
        ]);

        $perm = Permission::where('name', 'payouts.approve')->first();

        $permReq = RolePermissionRequest::create([
            'role_id' => $role->id,
            'permission_id' => $perm->id,
            'requested_by' => $this->superAdmin->id,
            'status' => 'pending',
            'reason' => 'Authorized by owner meeting',
        ]);

        $response = $this->actingAs($this->owner)->post(
            route('admin.roles.permission-requests.approve', $permReq->id),
            ['review_notes' => 'Approved for senior staff']
        );

        $response->assertRedirect();
        $this->assertEquals('approved', $permReq->fresh()->status);
        $this->assertEquals($this->owner->id, $permReq->fresh()->reviewed_by);

        // Permission is now officially attached to the role
        $this->assertTrue($role->fresh()->hasPermissionTo('payouts.approve'));
    }

    public function test_owner_can_reject_permission_request(): void
    {
        $role = Role::create([
            'name' => 'custom_rejected_role',
            'display_name' => 'Custom Rejected Role',
            'is_system' => false,
        ]);

        $perm = Permission::where('name', 'commissions.rules.review')->first();

        $permReq = RolePermissionRequest::create([
            'role_id' => $role->id,
            'permission_id' => $perm->id,
            'requested_by' => $this->superAdmin->id,
            'status' => 'pending',
            'reason' => 'Attempting to change rules',
        ]);

        $response = $this->actingAs($this->owner)->post(
            route('admin.roles.permission-requests.reject', $permReq->id),
            ['review_notes' => 'Rejected: Commission rules can only be modified by Owner.']
        );

        $response->assertRedirect();
        $this->assertEquals('rejected', $permReq->fresh()->status);
        $this->assertFalse($role->fresh()->hasPermissionTo('commissions.rules.review'));
    }

    public function test_custom_role_user_can_access_permitted_routes_and_blocked_from_others(): void
    {
        $orderRole = Role::create([
            'name' => 'warehouse_dispatcher',
            'display_name' => 'Warehouse Dispatcher',
            'is_system' => false,
        ]);

        $ordersPerm = Permission::where('name', 'orders.view')->first();
        $orderRole->permissions()->sync([$ordersPerm->id]);

        $staffUser = User::factory()->create([
            'role' => 'warehouse_dispatcher',
            'role_id' => $orderRole->id,
            'status' => UserStatus::ACTIVE,
        ]);
        $staffUser->assignedRoles()->sync([$orderRole->id]);

        // Permitted: can view orders
        $this->actingAs($staffUser)->get(route('admin.orders.index'))->assertStatus(200);

        // Blocked: cannot create products
        $this->actingAs($staffUser)->get(route('admin.products.create'))->assertStatus(403);

        // Blocked: cannot manage staff
        $this->actingAs($staffUser)->get(route('admin.staff.index'))->assertStatus(403);
    }

    public function test_super_admin_cannot_approve_payouts_directly(): void
    {
        $agent = User::factory()->create(['role' => UserRole::LOW_LEVEL_AGENT]);
        $payout = \App\Models\PayoutRequest::create([
            'reference_number' => 'PO-TEST-999',
            'agent_id' => $agent->id,
            'amount' => 1000.00,
            'currency' => 'LKR',
            'status' => 'pending',
            'bank_name' => 'Commercial Bank',
            'bank_branch' => 'Colombo',
            'bank_account_number' => '1234567890',
            'bank_account_name' => 'Agent Name',
        ]);

        // Route guarded by role:owner
        $response = $this->actingAs($this->superAdmin)->post(route('admin.payouts.approve', $payout->id));
        $response->assertStatus(403);
    }

    public function test_super_admin_can_terminate_staff_sessions(): void
    {
        $staffUser = User::factory()->create(['status' => UserStatus::ACTIVE]);

        DB::table('sessions')->insert([
            'id' => 'stale_session_123',
            'user_id' => $staffUser->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0',
            'payload' => 'dummy',
            'last_activity' => time(),
        ]);

        $this->assertEquals(1, DB::table('sessions')->where('user_id', $staffUser->id)->count());

        $response = $this->actingAs($this->superAdmin)->post(route('admin.staff.sessions', $staffUser->id));
        $response->assertRedirect();

        $this->assertEquals(0, DB::table('sessions')->where('user_id', $staffUser->id)->count());
    }

    public function test_super_admin_can_reset_staff_mfa(): void
    {
        $staffUser = User::factory()->create([
            'two_factor_secret' => 'encrypted_secret',
            'two_factor_confirmed_at' => now(),
            'status' => UserStatus::ACTIVE,
        ]);

        $this->assertTrue($staffUser->hasTwoFactorEnabled());

        $response = $this->actingAs($this->superAdmin)->post(route('admin.staff.reset-mfa', $staffUser->id));
        $response->assertRedirect();

        $fresh = $staffUser->fresh();
        $this->assertNull($fresh->two_factor_secret);
        $this->assertNull($fresh->two_factor_confirmed_at);
        $this->assertFalse($fresh->hasTwoFactorEnabled());
    }

    public function test_legacy_it_manager_role_alias_continues_working(): void
    {
        $itUser = User::factory()->create([
            'role' => UserRole::IT_MANAGER,
            'status' => UserStatus::ACTIVE,
        ]);

        $this->assertTrue($itUser->isManagement());
        $this->assertTrue($itUser->isSuperAdmin());

        // Access route expecting management
        $this->actingAs($itUser)->get(route('admin.dashboard'))->assertStatus(200);
    }
}
