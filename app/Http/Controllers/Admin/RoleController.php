<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RolePermissionRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class RoleController extends Controller
{
    /**
     * Display a listing of roles with permission matrix and counts.
     */
    public function index(Request $request): Response
    {
        $roles = Role::with(['permissions', 'creator:id,name'])
            ->withCount(['users', 'assignedUsers'])
            ->orderBy('is_protected', 'desc')
            ->orderBy('is_system', 'desc')
            ->orderBy('name')
            ->get();

        $permissions = Permission::orderBy('module')
            ->orderBy('is_financial_restricted')
            ->orderBy('name')
            ->get();

        $permissionsByModule = $permissions->groupBy('module');

        $pendingRequestsCount = RolePermissionRequest::where('status', 'pending')->count();

        return Inertia::render('Admin/Roles/Index', [
            'roles' => $roles,
            'permissions' => $permissions,
            'permissionsByModule' => $permissionsByModule,
            'pendingRequestsCount' => $pendingRequestsCount,
            'canManageRoles' => $request->user()->can('create', Role::class),
        ]);
    }

    /**
     * Store a newly created custom role.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (!$user->isOwner() && !$user->hasPermissionTo('roles.create')) {
            abort(403, 'Unauthorized to create custom roles.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50', 'alpha_dash', 'unique:roles,name'],
            'display_name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
        ]);

        $requestedPermIds = $validated['permissions'] ?? [];
        $restrictedPerms = Permission::whereIn('id', $requestedPermIds)
            ->where('is_financial_restricted', true)
            ->get();

        // Separate non-restricted permissions from restricted financial ones
        $allowedPermIds = Permission::whereIn('id', $requestedPermIds)
            ->where('is_financial_restricted', false)
            ->pluck('id')
            ->all();

        // If actor is Owner, they can directly grant restricted permissions
        if ($user->isOwner()) {
            $allowedPermIds = $requestedPermIds;
        }

        $role = Role::create([
            'name' => Str::slug($validated['name'], '_'),
            'display_name' => $validated['display_name'],
            'description' => $validated['description'] ?? null,
            'is_system' => false,
            'is_protected' => false,
            'created_by' => $user->id,
        ]);

        if (!empty($allowedPermIds)) {
            $role->permissions()->sync($allowedPermIds);
        }

        AuditLog::log('role_created', $role, null, [
            'name' => $role->name,
            'display_name' => $role->display_name,
            'permissions_count' => count($allowedPermIds),
        ]);

        // If Super Admin requested restricted permissions, create Permission Change Requests
        if (!$user->isOwner() && $restrictedPerms->isNotEmpty()) {
            foreach ($restrictedPerms as $perm) {
                RolePermissionRequest::create([
                    'role_id' => $role->id,
                    'permission_id' => $perm->id,
                    'requested_by' => $user->id,
                    'status' => 'pending',
                    'reason' => 'Requested upon initial role creation by ' . $user->name,
                ]);
            }

            return redirect()->route('admin.roles.index')->with(
                'success',
                "Custom role '{$role->display_name}' created. Restricted financial permissions were submitted to the Owner for approval."
            );
        }

        return redirect()->route('admin.roles.index')->with(
            'success',
            "Custom role '{$role->display_name}' created successfully."
        );
    }

    /**
     * Update an existing custom or system role.
     */
    public function update(Request $request, Role $role): RedirectResponse
    {
        $user = $request->user();

        // Protection: Protected system roles are immutable
        if ($role->is_protected || in_array($role->name, ['owner', 'super_admin'], true)) {
            abort(403, 'Protected system roles (Business Owner and Super Admin) are hardcoded by system policy and cannot be altered.');
        }

        if (!$user->isOwner() && !$user->hasPermissionTo('roles.update')) {
            abort(403, 'Unauthorized to update roles.');
        }

        $validated = $request->validate([
            'display_name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
        ]);

        $role->update([
            'display_name' => $validated['display_name'],
            'description' => $validated['description'] ?? $role->description,
        ]);

        $requestedPermIds = $validated['permissions'] ?? [];

        // Check for restricted permissions
        $restrictedPerms = Permission::whereIn('id', $requestedPermIds)
            ->where('is_financial_restricted', true)
            ->get();

        $allowedPermIds = Permission::whereIn('id', $requestedPermIds)
            ->where('is_financial_restricted', false)
            ->pluck('id')
            ->all();

        $oldPermIds = $role->permissions()->pluck('permissions.id')->all();

        if ($user->isOwner()) {
            // Owner can grant all permissions directly
            $role->permissions()->sync($requestedPermIds);
        } else {
            // Keep any already-granted restricted permissions that the role previously had
            $existingRestricted = $role->permissions()
                ->where('is_financial_restricted', true)
                ->pluck('permissions.id')
                ->all();

            $mergedPermIds = array_unique(array_merge($allowedPermIds, $existingRestricted));
            $role->permissions()->sync($mergedPermIds);

            // Create permission change requests for newly requested restricted permissions
            $newRestricted = $restrictedPerms->whereNotIn('id', $existingRestricted);
            foreach ($newRestricted as $perm) {
                RolePermissionRequest::firstOrCreate([
                    'role_id' => $role->id,
                    'permission_id' => $perm->id,
                    'status' => 'pending',
                ], [
                    'requested_by' => $user->id,
                    'reason' => 'Requested role permission update by ' . $user->name,
                ]);
            }
        }

        AuditLog::log('role_updated', $role, [
            'permissions' => $oldPermIds,
        ], [
            'permissions' => $role->permissions()->pluck('permissions.id')->all(),
        ]);

        if (!$user->isOwner() && isset($newRestricted) && $newRestricted->isNotEmpty()) {
            return redirect()->route('admin.roles.index')->with(
                'success',
                "Role '{$role->display_name}' updated. " . $newRestricted->count() . " restricted financial permission(s) submitted to the Owner for approval."
            );
        }

        return redirect()->route('admin.roles.index')->with(
            'success',
            "Role '{$role->display_name}' permissions updated successfully."
        );
    }

    /**
     * Remove the specified custom role.
     */
    public function destroy(Request $request, Role $role): RedirectResponse
    {
        $user = $request->user();

        if ($role->is_system || $role->is_protected || in_array($role->name, ['owner', 'super_admin'], true)) {
            abort(403, 'System and protected roles cannot be deleted.');
        }

        if (!$user->isOwner() && !$user->hasPermissionTo('roles.delete')) {
            abort(403, 'Unauthorized to delete roles.');
        }

        if ($role->users()->count() > 0 || $role->assignedUsers()->count() > 0) {
            return redirect()->route('admin.roles.index')->with(
                'error',
                "Cannot delete role '{$role->display_name}' because staff members are currently assigned to it."
            );
        }

        AuditLog::log('role_deleted', $role, [
            'name' => $role->name,
            'display_name' => $role->display_name,
        ], null);

        $role->delete();

        return redirect()->route('admin.roles.index')->with(
            'success',
            "Custom role '{$role->display_name}' deleted successfully."
        );
    }
}

