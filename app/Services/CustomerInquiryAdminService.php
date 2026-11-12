<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\AuditLog;
use App\Models\CustomerInquiry;
use App\Models\CustomerInquiryNote;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CustomerInquiryAdminService
{
    /**
     * Strict whitelist of permitted state transitions.
     */
    public const ALLOWED_TRANSITIONS = [
        CustomerInquiry::STATUS_NEW => [
            CustomerInquiry::STATUS_IN_PROGRESS,
            CustomerInquiry::STATUS_CLOSED,
        ],
        CustomerInquiry::STATUS_IN_PROGRESS => [
            CustomerInquiry::STATUS_WAITING_CUSTOMER,
            CustomerInquiry::STATUS_RESOLVED,
            CustomerInquiry::STATUS_CLOSED,
        ],
        CustomerInquiry::STATUS_WAITING_CUSTOMER => [
            CustomerInquiry::STATUS_IN_PROGRESS,
            CustomerInquiry::STATUS_RESOLVED,
            CustomerInquiry::STATUS_CLOSED,
        ],
        CustomerInquiry::STATUS_RESOLVED => [
            CustomerInquiry::STATUS_CLOSED,
            CustomerInquiry::STATUS_IN_PROGRESS, // Reopening
        ],
        CustomerInquiry::STATUS_CLOSED => [
            CustomerInquiry::STATUS_IN_PROGRESS, // Reopening
        ],
    ];

    /**
     * Update the status of an inquiry following state machine rules.
     *
     * @throws ValidationException
     */
    public function updateStatus(CustomerInquiry $inquiry, string $newStatus, User $actor, ?string $reason = null): CustomerInquiry
    {
        $currentStatus = $inquiry->status;

        // 1. Idempotent check: If already in target status, return early without DB write or audit log
        if ($currentStatus === $newStatus) {
            return $inquiry;
        }

        // 2. Validate transition whitelist
        $allowedTargets = self::ALLOWED_TRANSITIONS[$currentStatus] ?? [];
        if (! in_array($newStatus, $allowedTargets, true)) {
            throw ValidationException::withMessages([
                'status' => "Transition from '{$currentStatus}' to '{$newStatus}' is not permitted.",
            ]);
        }

        $isReopeningClosed = ($currentStatus === CustomerInquiry::STATUS_CLOSED && $newStatus === CustomerInquiry::STATUS_IN_PROGRESS);
        $isReopeningResolved = ($currentStatus === CustomerInquiry::STATUS_RESOLVED && $newStatus === CustomerInquiry::STATUS_IN_PROGRESS);

        // 3. Reopening Closed: Strictly mandatory non-empty internal note
        $trimmedReason = is_string($reason) ? trim($reason) : '';
        if ($isReopeningClosed && $trimmedReason === '') {
            throw ValidationException::withMessages([
                'reason' => 'An internal note explaining why this closed inquiry is being reopened is strictly mandatory.',
            ]);
        }

        // 4. Execute atomic state transition, note creation, and audit logging
        return DB::transaction(function () use ($inquiry, $currentStatus, $newStatus, $actor, $trimmedReason, $isReopeningClosed, $isReopeningResolved) {
            $createdNote = null;

            // Persist internal note if mandatory (closed reopening) or optionally provided (resolved reopening or other)
            if ($isReopeningClosed) {
                $createdNote = CustomerInquiryNote::create([
                    'customer_inquiry_id' => $inquiry->id,
                    'user_id' => $actor->id,
                    'note' => "[REOPENED] {$trimmedReason}",
                ]);
            } elseif ($trimmedReason !== '') {
                $createdNote = CustomerInquiryNote::create([
                    'customer_inquiry_id' => $inquiry->id,
                    'user_id' => $actor->id,
                    'note' => $isReopeningResolved ? "[REOPENED] {$trimmedReason}" : $trimmedReason,
                ]);
            }

            // Calculate resolution metadata
            $updates = [
                'status' => $newStatus,
            ];

            if ($newStatus === CustomerInquiry::STATUS_RESOLVED) {
                $updates['resolved_at'] = now();
                $updates['resolved_by'] = $actor->id;
            } elseif ($newStatus === CustomerInquiry::STATUS_CLOSED) {
                // Populate resolution metadata only if not already present
                if (is_null($inquiry->resolved_at)) {
                    $updates['resolved_at'] = now();
                    $updates['resolved_by'] = $actor->id;
                }
            } elseif ($isReopeningClosed || $isReopeningResolved) {
                // Reopening: clear resolution metadata
                $updates['resolved_at'] = null;
                $updates['resolved_by'] = null;
            }

            $inquiry->update($updates);

            // Emit sanitized audit log (do not expose confidential raw note body)
            AuditLog::log('inquiry_status_updated', $inquiry, [
                'status' => $currentStatus,
            ], [
                'status' => $newStatus,
                'actor_id' => $actor->id,
                'actor_name' => $actor->name,
                'reopened' => ($isReopeningClosed || $isReopeningResolved),
                'note_id' => $createdNote?->id,
                'note_length' => $createdNote ? strlen($createdNote->note) : 0,
            ]);

            return $inquiry->fresh(['assignedStaff', 'resolvedBy']);
        });
    }

    /**
     * Update the priority of an inquiry.
     *
     * @throws ValidationException
     */
    public function updatePriority(CustomerInquiry $inquiry, string $newPriority, User $actor): CustomerInquiry
    {
        if (! in_array($newPriority, CustomerInquiry::getPriorities(), true)) {
            throw ValidationException::withMessages([
                'priority' => "Invalid priority value '{$newPriority}'.",
            ]);
        }

        $oldPriority = $inquiry->priority;
        if ($oldPriority === $newPriority) {
            return $inquiry;
        }

        return DB::transaction(function () use ($inquiry, $oldPriority, $newPriority, $actor) {
            $inquiry->update([
                'priority' => $newPriority,
            ]);

            AuditLog::log('inquiry_priority_updated', $inquiry, [
                'priority' => $oldPriority,
            ], [
                'priority' => $newPriority,
                'actor_id' => $actor->id,
                'actor_name' => $actor->name,
            ]);

            return $inquiry;
        });
    }

    /**
     * Check if a given user is eligible to be assigned customer inquiries.
     */
    public function isStaffEligible(?User $user): bool
    {
        if ($user === null) {
            return true; // null represents unassignment, which is valid
        }

        // Must have an active account
        $statusValue = $user->status instanceof UserStatus ? $user->status->value : (string) $user->status;
        if ($statusValue !== 'active') {
            return false;
        }

        // Strictly reject customers and external network agents
        if ($user->isCustomer() || $user->isAgent()) {
            return false;
        }

        // Business Owner and Super Admin are always eligible
        if ($user->isOwner() || $user->isSuperAdmin()) {
            return true;
        }

        // Operations Manager is the primary operational inquiry handler
        if ($user->role === UserRole::OPERATIONS_MANAGER || $user->getRoleValue() === 'operations_manager') {
            return true;
        }

        // Custom management roles explicitly granted inquiries.manage permission
        if ($user->isManagement() && $user->hasPermissionTo('inquiries.manage')) {
            return true;
        }

        return false;
    }

    /**
     * Assign or unassign an inquiry to/from a staff member.
     *
     * @throws ValidationException
     */
    public function assignStaff(CustomerInquiry $inquiry, ?int $staffUserId, User $actor): CustomerInquiry
    {
        $targetUser = null;

        if ($staffUserId !== null) {
            $targetUser = User::find($staffUserId);

            if (! $targetUser || ! $this->isStaffEligible($targetUser)) {
                throw ValidationException::withMessages([
                    'staff_user_id' => 'The selected user is not eligible to be assigned customer inquiries.',
                ]);
            }
        }

        $oldAssignedId = $inquiry->assigned_to;
        if ($oldAssignedId === $staffUserId) {
            return $inquiry;
        }

        return DB::transaction(function () use ($inquiry, $oldAssignedId, $staffUserId, $targetUser, $actor) {
            $inquiry->update([
                'assigned_to' => $staffUserId,
            ]);

            AuditLog::log('inquiry_assigned', $inquiry, [
                'assigned_to' => $oldAssignedId,
            ], [
                'assigned_to' => $staffUserId,
                'assigned_staff_name' => $targetUser?->name,
                'action' => $staffUserId === null ? 'unassigned' : 'assigned',
                'actor_id' => $actor->id,
                'actor_name' => $actor->name,
            ]);

            return $inquiry->fresh(['assignedStaff']);
        });
    }

    /**
     * Append an immutable internal staff note to an inquiry.
     *
     * @throws ValidationException
     */
    public function addNote(CustomerInquiry $inquiry, string $noteContent, User $author): CustomerInquiryNote
    {
        $trimmedContent = trim($noteContent);

        if ($trimmedContent === '') {
            throw ValidationException::withMessages([
                'note' => 'Internal note body cannot be empty.',
            ]);
        }

        return DB::transaction(function () use ($inquiry, $trimmedContent, $author) {
            $note = CustomerInquiryNote::create([
                'customer_inquiry_id' => $inquiry->id,
                'user_id' => $author->id,
                'note' => $trimmedContent,
            ]);

            AuditLog::log('inquiry_note_added', $inquiry, null, [
                'note_id' => $note->id,
                'note_length' => strlen($note->note),
                'author_id' => $author->id,
                'author_name' => $author->name,
            ]);

            return $note;
        });
    }
}

