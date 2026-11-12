<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\AgentInvitation;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class AgentInvitationService
{
    /**
     * Create a secure, single-use agent invitation link.
     *
     * Strict Creator Rules:
     * - Agent Manager (or Owner/Super Admin) -> ONLY High-Level Agent invitations (parent = NULL).
     * - Active High-Level Agent -> ONLY Low-Level Agent invitations (parent = HLA creator).
     * - Low-Level Agents cannot recruit or create invitations.
     */
    public function createInvitation(User $creator, string $type, array $candidateData = [], int $expiryDays = 7): array
    {
        if ($type === AgentInvitation::TYPE_HIGH_LEVEL_AGENT) {
            $isAuthorizedStaff = $creator->isOwner()
                || $creator->isSuperAdmin()
                || $creator->isAgentManager()
                || $creator->hasPermissionTo('agents.invitations.create_hla');

            if (!$isAuthorizedStaff) {
                throw new AuthorizationException('Security Violation: Only an authorized Agent Manager or Executive can create High-Level Agent invitations.');
            }

            $parentAgentId = null; // High-level agents are direct under Company
        } elseif ($type === AgentInvitation::TYPE_LOW_LEVEL_AGENT) {
            $isAuthorizedHLA = $creator->isHighLevelAgent() && $creator->isActive();

            if (!$isAuthorizedHLA) {
                throw new AuthorizationException('Security Violation: Only an active High-Level Agent can invite Low-Level Agents to their network.');
            }

            $parentAgentId = $creator->id; // Low-level agent parent is strictly the inviting HLA
        } else {
            throw new InvalidArgumentException("Invalid invitation type '{$type}'. Must be high_level_agent or low_level_agent.");
        }

        // Generate 64-character high-entropy cryptographic token
        $plainToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $plainToken);

        $invitation = DB::transaction(function () use ($creator, $type, $parentAgentId, $candidateData, $tokenHash, $expiryDays) {
            return AgentInvitation::create([
                'token_hash' => $tokenHash,
                'invitation_type' => $type,
                'created_by' => $creator->id,
                'parent_agent_id' => $parentAgentId,
                'candidate_name' => $candidateData['candidate_name'] ?? $candidateData['name'] ?? null,
                'candidate_email' => $candidateData['candidate_email'] ?? $candidateData['email'] ?? null,
                'candidate_phone' => $candidateData['candidate_phone'] ?? $candidateData['phone'] ?? null,
                'status' => AgentInvitation::STATUS_PENDING,
                'expires_at' => now()->addDays($expiryDays),
            ]);
        });

        AuditLog::log('agent_invitation_created', $invitation, null, [
            'type' => $type,
            'creator_id' => $creator->id,
            'creator_name' => $creator->name,
            'parent_agent_id' => $parentAgentId,
            'expires_at' => $invitation->expires_at->toIso8601String(),
        ]);

        return [
            'invitation' => $invitation,
            'token' => $plainToken,
            'registration_url' => url("/agent/register?token={$plainToken}"),
        ];
    }

    /**
     * Resolve and validate an invitation by its plain token.
     */
    public function findValidInvitation(string $plainToken): ?AgentInvitation
    {
        if (empty($plainToken) || strlen($plainToken) !== 64) {
            return null;
        }

        $tokenHash = hash('sha256', $plainToken);

        return AgentInvitation::with(['creator', 'parentAgent'])
            ->where('token_hash', $tokenHash)
            ->where('status', AgentInvitation::STATUS_PENDING)
            ->where('expires_at', '>', now())
            ->first();
    }

    /**
     * Mark an invitation as accepted upon successful agent registration.
     */
    public function acceptInvitation(AgentInvitation $invitation, User $registeredUser): void
    {
        $lockedInvitation = AgentInvitation::where('id', $invitation->id)
            ->lockForUpdate()
            ->first();

        if (!$lockedInvitation || !$lockedInvitation->isValid()) {
            throw ValidationException::withMessages([
                'invitation' => 'This invitation link has expired, was already used, or is no longer valid.',
            ]);
        }

        $lockedInvitation->update([
            'status' => AgentInvitation::STATUS_ACCEPTED,
            'used_by' => $registeredUser->id,
            'used_at' => now(),
        ]);

        AuditLog::log('agent_invitation_accepted', $lockedInvitation, null, [
            'new_user_id' => $registeredUser->id,
            'new_user_email' => $registeredUser->email,
            'invitation_type' => $lockedInvitation->invitation_type,
        ]);
    }

    /**
     * Revoke an active invitation so it cannot be used.
     */
    public function revokeInvitation(AgentInvitation $invitation, User $actor): void
    {
        $canRevoke = $actor->isOwner()
            || $actor->isSuperAdmin()
            || $actor->id === $invitation->created_by
            || ($actor->isAgentManager() && $invitation->invitation_type === AgentInvitation::TYPE_HIGH_LEVEL_AGENT);

        if (!$canRevoke) {
            throw new AuthorizationException('Unauthorized to revoke this invitation.');
        }

        if ($invitation->status !== AgentInvitation::STATUS_PENDING) {
            throw new InvalidArgumentException('Only pending invitations can be revoked.');
        }

        $invitation->update(['status' => AgentInvitation::STATUS_REVOKED]);

        AuditLog::log('agent_invitation_revoked', $invitation, null, [
            'revoked_by' => $actor->name,
            'invitation_type' => $invitation->invitation_type,
        ]);
    }
}

