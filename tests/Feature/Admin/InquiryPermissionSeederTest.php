<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\InquiryPermissionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InquiryPermissionSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    /**
     * 1. Test seeder executes cleanly and creates inquiries.manage permission.
     */
    public function test_seeder_creates_inquiries_manage_permission_with_correct_attributes(): void
    {
        $this->seed(InquiryPermissionSeeder::class);

        $perm = Permission::where('name', 'inquiries.manage')->first();
        $this->assertNotNull($perm);
        $this->assertEquals('Manage Customer Inquiries', $perm->display_name);
        $this->assertEquals('support', $perm->module);
        $this->assertFalse((bool) $perm->is_financial_restricted);
    }

    /**
     * 2. Test seeder can run repeatedly without duplicate permissions or pivot entries.
     */
    public function test_seeder_is_idempotent_across_repeated_runs(): void
    {
        $this->seed(InquiryPermissionSeeder::class);
        $this->seed(InquiryPermissionSeeder::class);
        $this->seed(InquiryPermissionSeeder::class);

        $count = Permission::where('name', 'inquiries.manage')->count();
        $this->assertEquals(1, $count);

        $opsRole = Role::where('name', 'operations_manager')->first();
        $this->assertNotNull($opsRole);
        $assignedCount = $opsRole->permissions()->where('name', 'inquiries.manage')->count();
        $this->assertEquals(1, $assignedCount);
    }

    /**
     * 3. Test correct roles are granted inquiries.manage and unauthorized roles remain excluded.
     */
    public function test_correct_roles_granted_and_unauthorized_roles_excluded(): void
    {
        $this->seed(InquiryPermissionSeeder::class);

        // Super Admin has permission
        $superAdminUser = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN,
            'status' => UserStatus::ACTIVE,
        ]);
        $superAdminRole = Role::where('name', 'super_admin')->first();
        $superAdminUser->assignedRoles()->sync([$superAdminRole->id]);
        $this->assertTrue($superAdminUser->hasPermissionTo('inquiries.manage'));

        // Operations Manager has permission
        $opsUser = User::factory()->create([
            'role' => UserRole::OPERATIONS_MANAGER,
            'status' => UserStatus::ACTIVE,
        ]);
        $opsRole = Role::where('name', 'operations_manager')->first();
        $opsUser->assignedRoles()->sync([$opsRole->id]);
        $this->assertTrue($opsUser->hasPermissionTo('inquiries.manage'));

        // Owner has permission via wildcard
        $ownerUser = User::factory()->create([
            'role' => UserRole::OWNER,
            'status' => UserStatus::ACTIVE,
        ]);
        $this->assertTrue($ownerUser->hasPermissionTo('inquiries.manage'));

        // Accountant does NOT have permission
        $accountantUser = User::factory()->create([
            'role' => UserRole::ACCOUNTANT,
            'status' => UserStatus::ACTIVE,
        ]);
        $accountantRole = Role::where('name', 'accountant')->first();
        $accountantUser->assignedRoles()->sync([$accountantRole->id]);
        $this->assertFalse($accountantUser->hasPermissionTo('inquiries.manage'));

        // Customer does NOT have permission
        $customerUser = User::factory()->create([
            'role' => UserRole::CUSTOMER,
            'status' => UserStatus::ACTIVE,
        ]);
        $this->assertFalse($customerUser->hasPermissionTo('inquiries.manage'));

        // High-level Agent does NOT have permission
        $hlaUser = User::factory()->create([
            'role' => UserRole::HIGH_LEVEL_AGENT,
            'status' => UserStatus::ACTIVE,
        ]);
        $this->assertFalse($hlaUser->hasPermissionTo('inquiries.manage'));

        // Low-level Agent does NOT have permission
        $llaUser = User::factory()->create([
            'role' => UserRole::LOW_LEVEL_AGENT,
            'status' => UserStatus::ACTIVE,
        ]);
        $this->assertFalse($llaUser->hasPermissionTo('inquiries.manage'));
    }
}

