<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class InquiryPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds idempotently.
     */
    public function run(): void
    {
        // 1. Create or retrieve the inquiries.manage permission
        $permission = Permission::firstOrCreate(
            ['name' => 'inquiries.manage'],
            [
                'display_name' => 'Manage Customer Inquiries',
                'module' => 'support',
                'description' => 'View, triage, assign, resolve, and add internal notes to customer inquiries',
                'is_financial_restricted' => false,
            ]
        );

        // 2. Attach to Super Admin (Technical Administrator)
        $superAdminRole = Role::where('name', 'super_admin')->first();
        if ($superAdminRole) {
            $superAdminRole->permissions()->syncWithoutDetaching([$permission->id]);
        }

        // 3. Attach to Operations Manager (Operational Support Lead)
        $opsRole = Role::where('name', 'operations_manager')->first();
        if ($opsRole) {
            $opsRole->permissions()->syncWithoutDetaching([$permission->id]);
        }

        // 4. Attach to Owner role (Preserves database-level consistency alongside Owner wildcard bypass)
        $ownerRole = Role::where('name', 'owner')->first();
        if ($ownerRole) {
            $ownerRole->permissions()->syncWithoutDetaching([$permission->id]);
        }
    }
}

