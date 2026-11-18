<?php

namespace App\Policies;

use App\Models\AgentAchievement;
use App\Models\AgentAchievementAward;
use App\Models\AgentAchievementRequest;
use App\Models\User;

class AgentAchievementPolicy
{
    /**
     * Determine whether the user can view any achievements.
     */
    public function viewAny(User $actor): bool
    {
        return $actor->isOwner() || $actor->hasPermissionTo('agents.achievements.view');
    }

    /**
     * Determine whether the user can view a specific achievement definition.
     */
    public function view(User $actor, AgentAchievement $achievement): bool
    {
        return $actor->isOwner() || $actor->hasPermissionTo('agents.achievements.view');
    }

    /**
     * Determine whether the user can configure technical achievement catalog parameters.
     * Technical config only — cannot publish business definitions.
     */
    public function manageTechnical(User $actor): bool
    {
        return $actor->isOwner() || $actor->hasPermissionTo('agents.achievements.manage');
    }

    /**
     * Determine whether the user can approve and publish business achievement definitions.
     * Business Owner exclusive authority.
     */
    public function publish(User $actor): bool
    {
        return $actor->isOwner() && $actor->hasPermissionTo('agents.achievements.publish');
    }

    /**
     * Determine whether the user can submit discretionary nominations.
     * Operational management (Agent Manager) and Owner only. Super Admin is denied.
     */
    public function request(User $actor): bool
    {
        if ($actor->isTechnicalAuthority() && !$actor->isOwner()) {
            return false;
        }

        return $actor->hasPermissionTo('agents.achievements.request');
    }

    /**
     * Determine whether the user can approve a discretionary request.
     * Business Owner exclusive authority.
     * Anti-Self-Approval: Creator cannot approve own request, beneficiary cannot approve own award.
     */
    public function approveRequest(User $actor, AgentAchievementRequest $request): bool
    {
        if (!$actor->isBusinessAuthority() || !$actor->hasPermissionTo('agents.achievements.award')) {
            return false;
        }

        if ($actor->id === $request->requested_by) {
            return false;
        }

        if ($actor->id === $request->agent_id) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can reject a discretionary request.
     * Business Owner exclusive authority.
     * Creator cannot reject own request (must be independent approver).
     */
    public function rejectRequest(User $actor, AgentAchievementRequest $request): bool
    {
        if (!$actor->isBusinessAuthority() || !$actor->hasPermissionTo('agents.achievements.award')) {
            return false;
        }

        if ($actor->id === $request->requested_by) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can grant a discretionary achievement directly.
     * Business Owner exclusive authority. Beneficiary cannot grant to self.
     */
    public function directGrant(User $actor, ?User $beneficiary = null): bool
    {
        if (!$actor->isBusinessAuthority() || !$actor->hasPermissionTo('agents.achievements.award')) {
            return false;
        }

        if ($beneficiary && $actor->id === $beneficiary->id) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can approve and issue discretionary awards.
     * Business Owner exclusive authority.
     */
    public function award(User $actor): bool
    {
        return $actor->isOwner() && $actor->hasPermissionTo('agents.achievements.award');
    }

    /**
     * Determine whether the user can revoke awards.
     * Business Owner exclusive authority.
     */
    public function revoke(User $actor, mixed $award = null): bool
    {
        if (!$actor->isOwner() || !$actor->hasPermissionTo('agents.achievements.revoke')) {
            return false;
        }

        if ($award instanceof AgentAchievementAward && !in_array($award->status, ['active', 'corrected'], true)) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can reinstate revoked awards.
     * Business Owner exclusive authority.
     */
    public function reinstate(User $actor, mixed $award = null): bool
    {
        if (!$actor->isOwner() || !$actor->hasPermissionTo('agents.achievements.revoke')) {
            return false;
        }

        if ($award instanceof AgentAchievementAward && $award->status !== 'revoked') {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can export achievement data.
     */
    public function export(User $actor): bool
    {
        return $actor->isOwner() || $actor->hasPermissionTo('agents.achievements.export');
    }
}
