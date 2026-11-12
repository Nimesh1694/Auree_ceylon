<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Define all 42 Granular Permissions across 9 Modules
        $permissions = [
            // User Management (users)
            ['name' => 'users.view', 'display_name' => 'View Staff & Users Directory', 'module' => 'users', 'description' => 'View staff, agent, and customer directories', 'is_financial_restricted' => false],
            ['name' => 'users.create', 'display_name' => 'Create Staff Accounts', 'module' => 'users', 'description' => 'Create new management staff accounts', 'is_financial_restricted' => false],
            ['name' => 'users.update', 'display_name' => 'Update Staff Accounts', 'module' => 'users', 'description' => 'Edit profile and contact details of staff', 'is_financial_restricted' => false],
            ['name' => 'users.suspend', 'display_name' => 'Suspend/Deactivate Staff', 'module' => 'users', 'description' => 'Toggle active/suspended status of accounts', 'is_financial_restricted' => false],
            ['name' => 'users.sessions.kill', 'display_name' => 'Terminate User Sessions', 'module' => 'users', 'description' => 'Force logout user from all active sessions', 'is_financial_restricted' => false],
            ['name' => 'users.mfa.reset', 'display_name' => 'Reset Two-Factor Authentication', 'module' => 'users', 'description' => 'Reset TOTP 2FA secret for locked-out staff', 'is_financial_restricted' => false],

            // Role & Permission Management (roles)
            ['name' => 'roles.view', 'display_name' => 'View Roles & Permissions', 'module' => 'roles', 'description' => 'View custom roles and assigned capabilities', 'is_financial_restricted' => false],
            ['name' => 'roles.create', 'display_name' => 'Create Custom Staff Roles', 'module' => 'roles', 'description' => 'Create dynamic roles with custom permissions', 'is_financial_restricted' => false],
            ['name' => 'roles.update', 'display_name' => 'Update Custom Roles', 'module' => 'roles', 'description' => 'Modify custom role details and granted permissions', 'is_financial_restricted' => false],
            ['name' => 'roles.delete', 'display_name' => 'Delete Custom Roles', 'module' => 'roles', 'description' => 'Remove custom roles with no assigned users', 'is_financial_restricted' => false],
            ['name' => 'roles.assign', 'display_name' => 'Assign Roles to Staff', 'module' => 'roles', 'description' => 'Assign or reassign roles to staff accounts', 'is_financial_restricted' => false],

            // Catalog Management (catalog)
            ['name' => 'products.view', 'display_name' => 'View Products Catalog', 'module' => 'catalog', 'description' => 'View products in administrative catalog', 'is_financial_restricted' => false],
            ['name' => 'products.create', 'display_name' => 'Create Products', 'module' => 'catalog', 'description' => 'Add new products to store catalog', 'is_financial_restricted' => false],
            ['name' => 'products.update', 'display_name' => 'Update Products', 'module' => 'catalog', 'description' => 'Edit product pricing, descriptions, and media', 'is_financial_restricted' => false],
            ['name' => 'products.delete', 'display_name' => 'Delete/Archive Products', 'module' => 'catalog', 'description' => 'Archive or delete products from catalog', 'is_financial_restricted' => false],
            ['name' => 'products.cost_price.view', 'display_name' => 'View Supplier Cost Prices', 'module' => 'catalog', 'description' => 'View confidential product manufacturing cost prices', 'is_financial_restricted' => false],
            ['name' => 'categories.manage', 'display_name' => 'Manage Categories', 'module' => 'catalog', 'description' => 'Create, edit, and organize product categories', 'is_financial_restricted' => false],

            // Inventory & Logistics (inventory)
            ['name' => 'inventory.view', 'display_name' => 'View Inventory & Stock Levels', 'module' => 'inventory', 'description' => 'View current physical stock and low-stock alerts', 'is_financial_restricted' => false],
            ['name' => 'inventory.adjust', 'display_name' => 'Perform Stock Adjustments', 'module' => 'inventory', 'description' => 'Restock, deduct, or adjust physical inventory', 'is_financial_restricted' => false],
            ['name' => 'inventory.movements.view', 'display_name' => 'Audit Inventory Movements', 'module' => 'inventory', 'description' => 'View immutable stock movement ledger records', 'is_financial_restricted' => false],
            ['name' => 'couriers.manage', 'display_name' => 'Manage Courier Partners', 'module' => 'inventory', 'description' => 'Create and configure delivery courier profiles', 'is_financial_restricted' => false],

            // Order Administration (orders)
            ['name' => 'orders.view', 'display_name' => 'View Customer Orders', 'module' => 'orders', 'description' => 'View orders, customer addresses, and order items', 'is_financial_restricted' => false],
            ['name' => 'orders.status.update', 'display_name' => 'Update Order Lifecycle Status', 'module' => 'orders', 'description' => 'Transition orders from Placed to Delivered/Completed', 'is_financial_restricted' => false],
            ['name' => 'orders.courier.assign', 'display_name' => 'Assign Couriers & Waybills', 'module' => 'orders', 'description' => 'Assign courier partners and tracking waybills', 'is_financial_restricted' => false],
            ['name' => 'orders.cod.reconcile', 'display_name' => 'Reconcile COD Remittances', 'module' => 'orders', 'description' => 'Reconcile cash collected by courier partners', 'is_financial_restricted' => false],
            ['name' => 'orders.packing.manage', 'display_name' => 'Manage Order Packing Queue', 'module' => 'orders', 'description' => 'Process order packaging and dispatch readiness', 'is_financial_restricted' => false],
            ['name' => 'orders.returns.manage', 'display_name' => 'Manage Returns & Refusals', 'module' => 'orders', 'description' => 'Process returned parcels and delivery rejections', 'is_financial_restricted' => false],

            // Agent Network Administration (agents)
            ['name' => 'agents.view', 'display_name' => 'View Agent Directory', 'module' => 'agents', 'description' => 'View agent applications and network hierarchy', 'is_financial_restricted' => false],
            ['name' => 'agents.kyc.verify', 'display_name' => 'Verify Agent KYC Documents', 'module' => 'agents', 'description' => 'Inspect NIC and verify or reject agent KYC', 'is_financial_restricted' => false],
            ['name' => 'agents.status.update', 'display_name' => 'Approve/Reject Agent Applications', 'module' => 'agents', 'description' => 'Approve or reject pending agent applicants', 'is_financial_restricted' => false],
            ['name' => 'agents.invitations.create_hla', 'display_name' => 'Create High-Level Agent Invitations', 'module' => 'agents', 'description' => 'Generate secure single-use invitation links for HLA candidates', 'is_financial_restricted' => false],
            ['name' => 'agents.invitations.view', 'display_name' => 'View Agent Invitations Log', 'module' => 'agents', 'description' => 'Inspect invitation links, statuses, and registration timestamps', 'is_financial_restricted' => false],
            ['name' => 'agents.applications.review', 'display_name' => 'Review Agent Registration Queue', 'module' => 'agents', 'description' => 'Evaluate pending registration applications for HLA and LLA', 'is_financial_restricted' => false],
            ['name' => 'agents.support.manage', 'display_name' => 'Manage Agent Network Support', 'module' => 'agents', 'description' => 'Handle inquiries, performance metrics, and complaints from agents', 'is_financial_restricted' => false],
            ['name' => 'agents.hierarchy.request', 'display_name' => 'Request Agent Hierarchy Move', 'module' => 'agents', 'description' => 'Submit request to move agent under new parent', 'is_financial_restricted' => false],
            ['name' => 'agents.hierarchy.review', 'display_name' => 'Approve Agent Hierarchy Move', 'module' => 'agents', 'description' => 'Exclusively approve agent sponsor changes (Owner Only)', 'is_financial_restricted' => true],
            ['name' => 'agents.hierarchy.integrity', 'display_name' => 'Audit Hierarchy Network Integrity', 'module' => 'agents', 'description' => 'Inspect and audit agent hierarchy closure integrity and graph consistency', 'is_financial_restricted' => false],

            // Commission Governance (commissions)
            ['name' => 'commissions.ledger.view', 'display_name' => 'View Commission Ledger', 'module' => 'commissions', 'description' => 'Inspect company-wide commission transactions', 'is_financial_restricted' => false],
            ['name' => 'commissions.process', 'display_name' => 'Process Eligible Commissions', 'module' => 'commissions', 'description' => 'Batch approve eligible commissions past return window', 'is_financial_restricted' => false],
            ['name' => 'commissions.rules.request', 'display_name' => 'Request Commission Rule Change', 'module' => 'commissions', 'description' => 'Propose modifications to commission percentages', 'is_financial_restricted' => false],
            ['name' => 'commissions.rules.review', 'display_name' => 'Approve Commission Rule Changes', 'module' => 'commissions', 'description' => 'Exclusively approve commission percentage rates (Owner Only)', 'is_financial_restricted' => true],

            // Finance & Payouts (finance)
            ['name' => 'financial.view', 'display_name' => 'View Financial Statistics', 'module' => 'finance', 'description' => 'Read-only access to turnover and revenue metrics', 'is_financial_restricted' => false],
            ['name' => 'payouts.view', 'display_name' => 'View Payout Requests Queue', 'module' => 'finance', 'description' => 'Inspect agent wallet withdrawal requests', 'is_financial_restricted' => false],
            ['name' => 'payouts.process', 'display_name' => 'Mark Payouts In-Process', 'module' => 'finance', 'description' => 'Move approved payout requests to processing state', 'is_financial_restricted' => false],
            ['name' => 'payouts.record_payment', 'display_name' => 'Record Bank Transfer Payments', 'module' => 'finance', 'description' => 'Upload bank receipts and record reference numbers', 'is_financial_restricted' => false],
            ['name' => 'payouts.approve', 'display_name' => 'Approve Wallet Payout Requests', 'module' => 'finance', 'description' => 'Authorize release of funds for wallet payout (Owner Only)', 'is_financial_restricted' => true],
            ['name' => 'payouts.reject', 'display_name' => 'Reject Wallet Payout Requests', 'module' => 'finance', 'description' => 'Reject payout requests and return funds to wallet (Owner Only)', 'is_financial_restricted' => true],

            // Technical System Administration (system)
            ['name' => 'system.settings.view', 'display_name' => 'View System Settings', 'module' => 'system', 'description' => 'View application configurations and environment info', 'is_financial_restricted' => false],
            ['name' => 'system.settings.manage', 'display_name' => 'Manage System Settings', 'module' => 'system', 'description' => 'Update business phone, email, and shipping fees', 'is_financial_restricted' => false],
            ['name' => 'system.health.view', 'display_name' => 'Monitor System Health & Runtime', 'module' => 'system', 'description' => 'Inspect database, cache, queues, and server metrics', 'is_financial_restricted' => false],
            ['name' => 'system.logs.view', 'display_name' => 'View System Audit Logs', 'module' => 'system', 'description' => 'Inspect security events, logins, and audit trail', 'is_financial_restricted' => false],
            ['name' => 'system.maintenance.manage', 'display_name' => 'Manage Maintenance Mode', 'module' => 'system', 'description' => 'Toggle application maintenance mode with bypass keys', 'is_financial_restricted' => false],
        ];

        foreach ($permissions as $p) {
            Permission::updateOrCreate(['name' => $p['name']], $p);
        }

        // 2. Define System Roles
        $ownerRole = Role::updateOrCreate(['name' => 'owner'], [
            'display_name' => 'Business Owner',
            'description' => 'Highest business and financial authority with complete access and protected status.',
            'is_system' => true,
            'is_protected' => true,
        ]);

        $superAdminRole = Role::updateOrCreate(['name' => 'super_admin'], [
            'display_name' => 'Super Admin (IT Administrator)',
            'description' => 'Complete technical and administrative authority. Manages users, roles, permissions, and security. Restricted from financial approvals.',
            'is_system' => true,
            'is_protected' => true,
        ]);

        $marketingRole = Role::updateOrCreate(['name' => 'marketing_manager'], [
            'display_name' => 'Marketing Manager',
            'description' => 'Manages storefront catalog, categories, and promotions. Cost prices and sensitive financial PII are masked.',
            'is_system' => true,
            'is_protected' => false,
        ]);

        $accountantRole = Role::updateOrCreate(['name' => 'accountant'], [
            'display_name' => 'Accountant',
            'description' => 'Manages financial records, commission ledger, bank remittances, and payout disbursement.',
            'is_system' => true,
            'is_protected' => false,
        ]);

        $prodInvRole = Role::updateOrCreate(['name' => 'product_inventory_manager'], [
            'display_name' => 'Product & Inventory Manager',
            'description' => 'Combines product catalog administration and physical warehouse stock management.',
            'is_system' => true,
            'is_protected' => false,
        ]);

        $opsRole = Role::updateOrCreate(['name' => 'operations_manager'], [
            'display_name' => 'Operations Manager',
            'description' => 'Manages order processing, packing, shipping, couriers, returns, and delivery tracking.',
            'is_system' => true,
            'is_protected' => false,
        ]);

        $agentMgrRole = Role::updateOrCreate(['name' => 'agent_manager'], [
            'display_name' => 'Agent Manager (Staff)',
            'description' => 'Company staff role managing agent recruitment, HLA invitations, applications verification, and agent network monitoring.',
            'is_system' => true,
            'is_protected' => false,
        ]);

        $highAgentRole = Role::updateOrCreate(['name' => 'high_level_agent'], [
            'display_name' => 'High-level Agent',
            'description' => 'Direct and upline selling agent in the referral network.',
            'is_system' => true,
            'is_protected' => false,
        ]);

        $lowAgentRole = Role::updateOrCreate(['name' => 'low_level_agent'], [
            'display_name' => 'Low-level Agent',
            'description' => 'Direct selling agent in the referral network.',
            'is_system' => true,
            'is_protected' => false,
        ]);

        $customerRole = Role::updateOrCreate(['name' => 'customer'], [
            'display_name' => 'Customer',
            'description' => 'Storefront consumer purchasing cosmetic products with Cash on Delivery.',
            'is_system' => true,
            'is_protected' => false,
        ]);

        // 3. Assign Permissions to System Roles

        // Owner gets ALL 42 permissions
        $allPermissionIds = Permission::pluck('id')->all();
        $ownerRole->permissions()->sync($allPermissionIds);

        // Super Admin gets all technical, user, role, catalog, inventory, order, and agent permissions
        // EXCEPT the 4 restricted financial/governance review permissions
        $superAdminPermissionIds = Permission::where('is_financial_restricted', false)->pluck('id')->all();
        $superAdminRole->permissions()->sync($superAdminPermissionIds);

        // Marketing Manager permissions
        $marketingPermissionIds = Permission::whereIn('name', [
            'products.view',
            'products.create',
            'products.update',
            'categories.manage',
            'orders.view',
            'agents.view',
        ])->pluck('id')->all();
        $marketingRole->permissions()->sync($marketingPermissionIds);

        // Accountant permissions
        $accountantPermissionIds = Permission::whereIn('name', [
            'orders.view',
            'orders.status.update',
            'orders.cod.reconcile',
            'inventory.movements.view',
            'products.cost_price.view',
            'agents.view',
            'agents.kyc.verify',
            'commissions.ledger.view',
            'commissions.process',
            'financial.view',
            'payouts.view',
            'payouts.process',
            'payouts.record_payment',
        ])->pluck('id')->all();
        $accountantRole->permissions()->sync($accountantPermissionIds);

        // Product & Inventory Manager permissions
        $prodInvPermissionIds = Permission::whereIn('name', [
            'products.view',
            'products.create',
            'products.update',
            'products.delete',
            'products.cost_price.view',
            'categories.manage',
            'inventory.view',
            'inventory.adjust',
            'inventory.movements.view',
            'couriers.manage',
        ])->pluck('id')->all();
        $prodInvRole->permissions()->sync($prodInvPermissionIds);

        // Operations Manager permissions
        $opsPermissionIds = Permission::whereIn('name', [
            'orders.view',
            'orders.status.update',
            'orders.courier.assign',
            'orders.cod.reconcile',
            'orders.packing.manage',
            'orders.returns.manage',
            'couriers.manage',
            'inventory.view',
        ])->pluck('id')->all();
        $opsRole->permissions()->sync($opsPermissionIds);

        // Agent Manager permissions (Staff)
        $agentMgrPermissionIds = Permission::whereIn('name', [
            'agents.view',
            'agents.kyc.verify',
            'agents.status.update',
            'agents.hierarchy.request',
            'agents.invitations.create_hla',
            'agents.invitations.view',
            'agents.applications.review',
            'agents.support.manage',
        ])->pluck('id')->all();
        $agentMgrRole->permissions()->sync($agentMgrPermissionIds);

        // 4. Decision 1 Migration: Update existing IT Manager account to Super Admin
        User::where('role', 'it_manager')->update(['role' => 'super_admin', 'protected_authority' => 'technical']);
        User::where('email', 'it@auraluxe.lk')->update([
            'name' => 'Naveen Jayawardena (Super Admin)',
            'role' => 'super_admin',
            'protected_authority' => 'technical',
        ]);
        User::where('role', 'owner')->update(['protected_authority' => 'business']);

        // 5. Synchronize all existing users' role_id with roles.name
        $roleMap = Role::pluck('id', 'name')->all();
        foreach (User::all() as $user) {
            $userRoleStr = $user->role instanceof UserRole ? $user->role->value : (string) $user->role;
            if (isset($roleMap[$userRoleStr])) {
                $roleId = $roleMap[$userRoleStr];
                $user->update(['role_id' => $roleId]);
                $user->assignedRoles()->syncWithoutDetaching([$roleId]);
            }
        }
    }
}

