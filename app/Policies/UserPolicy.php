<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Determine whether the user can view any users.
     */
    public function viewAny(User $actor): bool
    {
        return $actor->isOwner() || $actor->isTechnicalAuthority() || $actor->hasPermissionTo('users.view');
    }

    /**
     * Determine whether the user can view the specific user.
     */
    public function view(User $actor, User $target): bool
    {
        return $actor->isOwner()
            || $actor->isTechnicalAuthority()
            || $actor->id === $target->id
            || $actor->hasPermissionTo('users.view');
    }

    /**
     * Determine whether the user can create staff or users.
     */
    public function create(User $actor): bool
    {
        return $actor->isTechnicalAuthority() || $actor->isOwner() || $actor->hasPermissionTo('users.create');
    }

    /**
     * Determine whether the user can update the target user.
     * Dual protected account architecture:
     * - Business Owner account can only be edited by the Owner themselves.
     * - Super Admin account can only be edited by the Super Admin themselves.
     */
    public function update(User $actor, User $target): bool
    {
        if ($target->isBusinessAuthority()) {
            return $actor->id === $target->id;
        }

        if ($target->isTechnicalAuthority()) {
            return $actor->id === $target->id;
        }

        return $actor->isTechnicalAuthority() || $actor->isOwner() || $actor->hasPermissionTo('users.update');
    }

    /**
     * Determine whether the user can delete the target user.
     * Neither the Business Owner nor the Super Admin can be deleted.
     */
    public function delete(User $actor, User $target): bool
    {
        if ($target->isBusinessAuthority() || $target->isTechnicalAuthority()) {
            return false;
        }

        return $actor->isTechnicalAuthority() || $actor->isOwner() || $actor->hasPermissionTo('users.suspend');
    }

    /**
     * Determine whether the user can suspend/deactivate the target user.
     * Neither the Business Owner nor the Super Admin can be suspended.
     */
    public function suspend(User $actor, User $target): bool
    {
        if ($target->isBusinessAuthority() || $target->isTechnicalAuthority()) {
            return false;
        }

        return $actor->isTechnicalAuthority() || $actor->isOwner() || $actor->hasPermissionTo('users.suspend');
    }

    /**
     * Determine whether the user can terminate active sessions for the target user.
     * - Owner cannot terminate Super Admin sessions.
     * - Super Admin cannot terminate Owner sessions.
     */
    public function killSessions(User $actor, User $target): bool
    {
        if ($target->isBusinessAuthority() && $actor->id !== $target->id) {
            return false;
        }

        if ($target->isTechnicalAuthority() && $actor->id !== $target->id) {
            return false;
        }

        return $actor->isTechnicalAuthority() || $actor->isOwner() || $actor->hasPermissionTo('users.sessions.kill');
    }

    /**
     * Determine whether the user can reset MFA for the target user.
     * - Owner cannot reset Super Admin MFA.
     * - Super Admin cannot reset Owner MFA.
     */
    public function resetMfa(User $actor, User $target): bool
    {
        if ($target->isBusinessAuthority() && $actor->id !== $target->id) {
            return false;
        }

        if ($target->isTechnicalAuthority() && $actor->id !== $target->id) {
            return false;
        }

        return $actor->isTechnicalAuthority() || $actor->isOwner() || $actor->hasPermissionTo('users.mfa.reset');
    }
}
