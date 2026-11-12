<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;

class RolePolicy
{
    /**
     * Determine whether the user can view any roles.
     */
    public function viewAny(User $actor): bool
    {
        return $actor->isOwner() || $actor->hasPermissionTo('roles.view');
    }

    /**
     * Determine whether the user can create custom roles.
     */
    public function create(User $actor): bool
    {
        return $actor->isOwner() || $actor->hasPermissionTo('roles.create');
    }

    /**
     * Determine whether the user can update the role.
     * Protected roles (Owner) cannot be updated.
     */
    public function update(User $actor, Role $role): bool
    {
        if ($role->is_protected) {
            return false;
        }

        return $actor->isOwner() || $actor->hasPermissionTo('roles.update');
    }

    /**
     * Determine whether the user can delete the role.
     * System and protected roles cannot be deleted. Roles with assigned users cannot be deleted.
     */
    public function delete(User $actor, Role $role): bool
    {
        if ($role->is_system || $role->is_protected) {
            return false;
        }

        if ($role->users()->count() > 0 || $role->assignedUsers()->count() > 0) {
            return false;
        }

        return $actor->isOwner() || $actor->hasPermissionTo('roles.delete');
    }

    /**
     * Determine whether the user can assign roles to staff.
     */
    public function assign(User $actor): bool
    {
        return $actor->isOwner() || $actor->hasPermissionTo('roles.assign');
    }
}

