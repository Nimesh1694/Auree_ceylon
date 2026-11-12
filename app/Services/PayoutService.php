<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\PayoutRequest;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Notifications\PayoutStatusNotification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PayoutService
{
    /**
     * Submit a new commission payout request on behalf of an eligible agent.
     * Atomically reserves requested funds from available balance.
     */
    public function createPayoutRequest(User $agent, string|float $amount, ?string $agentNotes = null): PayoutRequest
    {
        $amountStr = number_format((float) $amount, 2, '.', '');
        if (bccomp($amountStr, '0.00', 2) <= 0) {
            throw new InvalidArgumentException('Payout amount must be greater than zero.');
        }

        $profile = $agent->agentProfile;
        if (!$profile) {
            throw new InvalidArgumentException('Agent profile not found.');
        }

        // Validate complete bank information
        if (empty($profile->bank_name) || empty($profile->bank_account_number) || empty($profile->bank_account_name)) {
            throw new InvalidArgumentException('Please complete and verify your bank account details in your profile before requesting a payout.');
        }

        // Validate KYC verification status
        if (!in_array($profile->kyc_status, ['verified', 'approved'], true)) {
            throw new InvalidArgumentException('Your KYC verification must be approved before requesting commission payouts.');
        }

        return DB::transaction(function () use ($agent, $profile, $amountStr, $agentNotes) {
            // Pessimistic lock on agent profile to prevent race conditions on available balance
            $lockedProfile = $agent->agentProfile()->lockForUpdate()->first();

            $grossBalance = (string) $lockedProfile->wallet_balance;
            $reservedBalance = (string) ($lockedProfile->reserved_balance ?? '0.00');
            $availableBalance = bcsub($grossBalance, $reservedBalance, 2);

            if (bccomp($amountStr, $availableBalance, 2) > 0) {
                throw new InvalidArgumentException("Insufficient available balance. You have LKR {$availableBalance} available for payout.");
            }

            // Atomically increment reserved balance using BCMath
            $lockedProfile->reserved_balance = bcadd((string) ($lockedProfile->reserved_balance ?? '0.00'), $amountStr, 2);
            $lockedProfile->save();

            // Capture immutable bank snapshot at submission time
            $bankSnapshot = [
                'bank_name' => $lockedProfile->bank_name,
                'bank_branch' => $lockedProfile->bank_branch,
                'bank_account_number' => $lockedProfile->bank_account_number,
                'bank_account_name' => $lockedProfile->bank_account_name,
                'nic' => $lockedProfile->nic,
                'business_name' => $lockedProfile->business_name,
                'agent_name' => $agent->name,
                'agent_email' => $agent->email,
                'captured_at' => now()->toIso8601String(),
            ];

            // Create Payout Request
            $payout = PayoutRequest::create([
                'reference_number' => PayoutRequest::generateReferenceNumber(),
                'agent_id' => $agent->id,
                'amount' => $amountStr,
                'currency' => 'LKR',
                'status' => PayoutRequest::STATUS_PENDING,
                'bank_name' => $lockedProfile->bank_name,
                'bank_branch' => $lockedProfile->bank_branch,
                'bank_account_number' => $lockedProfile->bank_account_number,
                'bank_account_name' => $lockedProfile->bank_account_name,
                'bank_snapshot' => $bankSnapshot,
                'agent_notes' => $agentNotes,
            ]);

            // Append wallet ledger record for payout reservation
            // Note (FIN-06-01): balance_after represents Gross wallet balance (untouched by reservation)
            // available_balance_after tracks the reduced spendable/withdrawable balance
            WalletTransaction::create([
                'transaction_number' => WalletTransaction::generateTransactionNumber(),
                'agent_id' => $agent->id,
                'transaction_type' => WalletTransaction::TYPE_PAYOUT_RESERVED,
                'amount' => '-' . $amountStr,
                'balance_after' => $lockedProfile->wallet_balance,
                'available_balance_after' => $lockedProfile->available_balance,
                'currency' => 'LKR',
                'source_type' => PayoutRequest::class,
                'source_id' => $payout->id,
                'description' => "Payout requested #{$payout->reference_number} (funds reserved)",
                'metadata' => [
                    'payout_reference' => $payout->reference_number,
                    'amount' => $amountStr,
                    'gross_balance' => (string) $lockedProfile->wallet_balance,
                    'available_balance_after' => (string) $lockedProfile->available_balance,
                ],
            ]);

            AuditLog::log('payout_requested', $payout, null, [
                'amount' => $amountStr,
                'agent_id' => $agent->id,
                'reference' => $payout->reference_number,
            ]);

            $agent->notify(new PayoutStatusNotification($payout, 'requested'));

            return $payout;
        });
    }

    /**
     * Approve a pending payout request (Executive Owner ONLY).
     */
    public function approvePayoutRequest(PayoutRequest $payoutRequest, User $owner, ?string $notes = null): PayoutRequest
    {
        if (!$owner->isOwner()) {
            throw new AuthorizationException('Only the executive company Owner can approve commission payout requests.');
        }

        return DB::transaction(function () use ($payoutRequest, $owner, $notes) {
            $lockedPayout = PayoutRequest::where('id', $payoutRequest->id)
                ->where('status', PayoutRequest::STATUS_PENDING)
                ->lockForUpdate()
                ->first();

            if (!$lockedPayout) {
                throw new InvalidArgumentException('Payout request is not in a pending state or has already been reviewed.');
            }

            // Aggregate Reservation Invariant Guard: Cannot approve if an aggregate deficit exists
            $profile = $lockedPayout->agent->agentProfile()->lockForUpdate()->first();
            $currentWallet = (string) ($profile?->wallet_balance ?? '0.00');
            $currentReserved = (string) ($profile?->reserved_balance ?? '0.00');

            if (bccomp($currentWallet, $currentReserved, 2) < 0) {
                $deficit = bcsub($currentReserved, $currentWallet, 2);
                throw new InvalidArgumentException(
                    "Cannot approve payout request #{$lockedPayout->reference_number}: Agent has an aggregate reservation deficit of LKR {$deficit} (Wallet: LKR {$currentWallet}, Total Reserved: LKR {$currentReserved}). Please reject this payout request."
                );
            }

            $lockedPayout->update([
                'status' => PayoutRequest::STATUS_APPROVED,
                'reviewed_by' => $owner->id,
                'reviewed_at' => now(),
                'review_notes' => $notes,
            ]);

            AuditLog::log('payout_approved', $lockedPayout, ['status' => 'pending'], ['status' => 'approved']);

            $lockedPayout->agent->notify(new PayoutStatusNotification($lockedPayout, 'approved'));

            return $lockedPayout;
        });
    }

    /**
     * Reject a payout request (Executive Owner ONLY).
     * Atomically releases reserved funds back to available balance.
     */
    public function rejectPayoutRequest(PayoutRequest $payoutRequest, User $owner, string $reason): PayoutRequest
    {
        if (!$owner->isOwner()) {
            throw new AuthorizationException('Only the executive company Owner can reject commission payout requests.');
        }

        $reason = trim($reason);
        if (empty($reason)) {
            throw new InvalidArgumentException('A clear justification reason is required when rejecting a payout request.');
        }

        return DB::transaction(function () use ($payoutRequest, $owner, $reason) {
            $lockedPayout = PayoutRequest::where('id', $payoutRequest->id)
                ->whereIn('status', [PayoutRequest::STATUS_PENDING, PayoutRequest::STATUS_APPROVED, PayoutRequest::STATUS_PROCESSING])
                ->lockForUpdate()
                ->first();

            if (!$lockedPayout) {
                throw new InvalidArgumentException('Payout request cannot be rejected from its current status.');
            }

            $priorStatus = $lockedPayout->status;
            $amountStr = number_format((float) $lockedPayout->amount, 2, '.', '');

            $lockedPayout->update([
                'status' => PayoutRequest::STATUS_REJECTED,
                'reviewed_by' => $owner->id,
                'reviewed_at' => now(),
                'rejection_reason' => $reason,
            ]);

            // Release reserved balance back to agent profile using BCMath without float casting
            $profile = $lockedPayout->agent->agentProfile()->lockForUpdate()->first();
            if ($profile) {
                $profile->reserved_balance = bcsub((string) ($profile->reserved_balance ?? '0.00'), $amountStr, 2);
                if (bccomp((string) $profile->reserved_balance, '0.00', 2) < 0) {
                    $profile->reserved_balance = '0.00';
                }

                // Symmetrical Absorption: If an outstanding clawback was shielded by this reservation, absorb it now
                $outstandingClawback = (string) ($profile->outstanding_clawback ?? '0.00');
                if (bccomp($outstandingClawback, '0.00', 2) > 0) {
                    if (bccomp($amountStr, $outstandingClawback, 2) >= 0) {
                        $absorbed = $outstandingClawback;
                        $profile->outstanding_clawback = '0.00';
                    } else {
                        $absorbed = $amountStr;
                        $profile->outstanding_clawback = bcsub($outstandingClawback, $amountStr, 2);
                    }
                    $profile->wallet_balance = bcsub((string) $profile->wallet_balance, $absorbed, 2);
                    if (bccomp((string) $profile->wallet_balance, '0.00', 2) < 0) {
                        $profile->wallet_balance = '0.00';
                    }
                }
                $profile->save();
            }

            // Append wallet ledger record for released reservation
            WalletTransaction::create([
                'transaction_number' => WalletTransaction::generateTransactionNumber(),
                'agent_id' => $lockedPayout->agent_id,
                'transaction_type' => WalletTransaction::TYPE_PAYOUT_RELEASED,
                'amount' => $amountStr,
                'balance_after' => $profile->wallet_balance,
                'available_balance_after' => $profile->available_balance,
                'currency' => 'LKR',
                'source_type' => PayoutRequest::class,
                'source_id' => $lockedPayout->id,
                'description' => "Payout #{$lockedPayout->reference_number} rejected (funds released back to available balance)",
                'metadata' => [
                    'payout_reference' => $lockedPayout->reference_number,
                    'reason' => $reason,
                ],
            ]);

            AuditLog::log('payout_rejected', $lockedPayout, ['status' => $priorStatus], [
                'status' => 'rejected',
                'reason' => $reason,
            ]);

            $lockedPayout->agent->notify(new PayoutStatusNotification($lockedPayout, 'rejected', $reason));

            return $lockedPayout;
        });
    }

    /**
     * Cancel a pending payout request by the requesting agent.
     * Releases reserved balance back to available funds.
     */
    public function cancelPayoutRequest(PayoutRequest $payoutRequest, User $agent): PayoutRequest
    {
        if ($payoutRequest->agent_id !== $agent->id) {
            throw new AuthorizationException('You can only cancel your own payout requests.');
        }

        return DB::transaction(function () use ($payoutRequest, $agent) {
            $lockedPayout = PayoutRequest::where('id', $payoutRequest->id)
                ->where('agent_id', $agent->id)
                ->where('status', PayoutRequest::STATUS_PENDING)
                ->lockForUpdate()
                ->first();

            if (!$lockedPayout) {
                throw new InvalidArgumentException('Only pending payout requests can be cancelled.');
            }

            $amountStr = number_format((float) $lockedPayout->amount, 2, '.', '');

            $lockedPayout->update([
                'status' => PayoutRequest::STATUS_CANCELLED,
            ]);

            // Release reserved funds using BCMath without float casting
            $profile = $agent->agentProfile()->lockForUpdate()->first();
            if ($profile) {
                $profile->reserved_balance = bcsub((string) ($profile->reserved_balance ?? '0.00'), $amountStr, 2);
                if (bccomp((string) $profile->reserved_balance, '0.00', 2) < 0) {
                    $profile->reserved_balance = '0.00';
                }

                // Symmetrical Absorption: If an outstanding clawback was shielded by this reservation, absorb it now
                $outstandingClawback = (string) ($profile->outstanding_clawback ?? '0.00');
                if (bccomp($outstandingClawback, '0.00', 2) > 0) {
                    if (bccomp($amountStr, $outstandingClawback, 2) >= 0) {
                        $absorbed = $outstandingClawback;
                        $profile->outstanding_clawback = '0.00';
                    } else {
                        $absorbed = $amountStr;
                        $profile->outstanding_clawback = bcsub($outstandingClawback, $amountStr, 2);
                    }
                    $profile->wallet_balance = bcsub((string) $profile->wallet_balance, $absorbed, 2);
                    if (bccomp((string) $profile->wallet_balance, '0.00', 2) < 0) {
                        $profile->wallet_balance = '0.00';
                    }
                }
                $profile->save();
            }

            // Append wallet ledger record
            WalletTransaction::create([
                'transaction_number' => WalletTransaction::generateTransactionNumber(),
                'agent_id' => $agent->id,
                'transaction_type' => WalletTransaction::TYPE_PAYOUT_RELEASED,
                'amount' => $amountStr,
                'balance_after' => $profile->wallet_balance,
                'available_balance_after' => $profile->available_balance,
                'currency' => 'LKR',
                'source_type' => PayoutRequest::class,
                'source_id' => $lockedPayout->id,
                'description' => "Payout #{$lockedPayout->reference_number} cancelled by agent (funds released)",
                'metadata' => [
                    'payout_reference' => $lockedPayout->reference_number,
                ],
            ]);

            AuditLog::log('payout_cancelled', $lockedPayout, ['status' => 'pending'], ['status' => 'cancelled']);

            $agent->notify(new PayoutStatusNotification($lockedPayout, 'cancelled'));

            return $lockedPayout;
        });
    }

    /**
     * Mark an approved payout as processing (bank batch preparation).
     */
    public function markProcessing(PayoutRequest $payoutRequest, User $manager): PayoutRequest
    {
        return DB::transaction(function () use ($payoutRequest, $manager) {
            $lockedPayout = PayoutRequest::where('id', $payoutRequest->id)
                ->where('status', PayoutRequest::STATUS_APPROVED)
                ->lockForUpdate()
                ->first();

            if (!$lockedPayout) {
                throw new InvalidArgumentException('Only approved payout requests can be moved to processing.');
            }

            // Aggregate Reservation Invariant Guard: Cannot mark processing if an aggregate deficit exists
            $profile = $lockedPayout->agent->agentProfile()->lockForUpdate()->first();
            $currentWallet = (string) ($profile?->wallet_balance ?? '0.00');
            $currentReserved = (string) ($profile?->reserved_balance ?? '0.00');

            if (bccomp($currentWallet, $currentReserved, 2) < 0) {
                $deficit = bcsub($currentReserved, $currentWallet, 2);
                throw new InvalidArgumentException(
                    "Cannot move payout #{$lockedPayout->reference_number} to processing: Agent has an aggregate reservation deficit of LKR {$deficit} (Wallet: LKR {$currentWallet}, Total Reserved: LKR {$currentReserved}). Please resolve or reject this payout request."
                );
            }

            $lockedPayout->update([
                'status' => PayoutRequest::STATUS_PROCESSING,
            ]);

            AuditLog::log('payout_processing', $lockedPayout, ['status' => 'approved'], ['status' => 'processing']);

            $lockedPayout->agent->notify(new PayoutStatusNotification($lockedPayout, 'processing'));

            return $lockedPayout;
        });
    }

    /**
     * Mark an approved or processing payout as failed (e.g. rejected by bank clearing).
     * Atomically releases reserved funds back to available balance.
     * Permitted roles: Owner, Accountant. (OPS-06-01)
     */
    public function failPayoutRequest(PayoutRequest $payoutRequest, User $manager, string $failureReason): PayoutRequest
    {
        if (!$manager->isOwner() && $manager->role !== \App\Enums\UserRole::ACCOUNTANT) {
            throw new AuthorizationException('Only the Owner or Accountant can record a payout failure.');
        }

        $reason = trim($failureReason);
        if (empty($reason)) {
            throw new InvalidArgumentException('A reason explaining the bank transfer failure is strictly required.');
        }

        return DB::transaction(function () use ($payoutRequest, $manager, $reason) {
            $lockedPayout = PayoutRequest::where('id', $payoutRequest->id)
                ->whereIn('status', [PayoutRequest::STATUS_APPROVED, PayoutRequest::STATUS_PROCESSING])
                ->lockForUpdate()
                ->first();

            if (!$lockedPayout) {
                throw new InvalidArgumentException('Only approved or processing payout requests can be marked as failed.');
            }

            $priorStatus = $lockedPayout->status;
            $amountStr = number_format((float) $lockedPayout->amount, 2, '.', '');

            $lockedPayout->update([
                'status' => PayoutRequest::STATUS_FAILED,
                'failed_by' => $manager->id,
                'failed_at' => now(),
                'failure_reason' => $reason,
            ]);

            // Release reserved funds back to available balance exactly once
            $profile = $lockedPayout->agent->agentProfile()->lockForUpdate()->first();
            if ($profile) {
                $profile->reserved_balance = bcsub((string) ($profile->reserved_balance ?? '0.00'), $amountStr, 2);
                if (bccomp((string) $profile->reserved_balance, '0.00', 2) < 0) {
                    $profile->reserved_balance = '0.00';
                }

                // Symmetrical Absorption: If an outstanding clawback was shielded by this reservation, absorb it now
                $outstandingClawback = (string) ($profile->outstanding_clawback ?? '0.00');
                if (bccomp($outstandingClawback, '0.00', 2) > 0) {
                    if (bccomp($amountStr, $outstandingClawback, 2) >= 0) {
                        $absorbed = $outstandingClawback;
                        $profile->outstanding_clawback = '0.00';
                    } else {
                        $absorbed = $amountStr;
                        $profile->outstanding_clawback = bcsub($outstandingClawback, $amountStr, 2);
                    }
                    $profile->wallet_balance = bcsub((string) $profile->wallet_balance, $absorbed, 2);
                    if (bccomp((string) $profile->wallet_balance, '0.00', 2) < 0) {
                        $profile->wallet_balance = '0.00';
                    }
                }
                $profile->save();
            }

            // Append wallet ledger record for released reservation
            WalletTransaction::create([
                'transaction_number' => WalletTransaction::generateTransactionNumber(),
                'agent_id' => $lockedPayout->agent_id,
                'transaction_type' => WalletTransaction::TYPE_PAYOUT_RELEASED,
                'amount' => $amountStr,
                'balance_after' => $profile->wallet_balance,
                'available_balance_after' => $profile->available_balance,
                'currency' => 'LKR',
                'source_type' => PayoutRequest::class,
                'source_id' => $lockedPayout->id,
                'description' => "Payout #{$lockedPayout->reference_number} failed by bank ({$reason}) - funds released",
                'metadata' => [
                    'payout_reference' => $lockedPayout->reference_number,
                    'failed_by' => $manager->id,
                    'reason' => $reason,
                ],
            ]);

            AuditLog::log('payout_failed', $lockedPayout, ['status' => $priorStatus], [
                'status' => 'failed',
                'reason' => $reason,
                'failed_by' => $manager->id,
            ]);

            $lockedPayout->agent->notify(new PayoutStatusNotification($lockedPayout, 'failed', $reason));

            return $lockedPayout;
        });
    }

    /**
     * Record bank transfer execution and complete the payout.
     * Atomically deducts gross wallet balance and releases reserved balance.
     */
    public function recordPayment(PayoutRequest $payoutRequest, User $manager, array $data): PayoutRequest
    {
        $transferRef = strtoupper(trim($data['bank_transfer_reference'] ?? ''));
        if (empty($transferRef)) {
            throw new InvalidArgumentException('A bank transfer reference number is strictly required.');
        }

        return DB::transaction(function () use ($payoutRequest, $manager, $transferRef, $data) {
            $lockedPayout = PayoutRequest::where('id', $payoutRequest->id)
                ->whereIn('status', [PayoutRequest::STATUS_APPROVED, PayoutRequest::STATUS_PROCESSING])
                ->lockForUpdate()
                ->first();

            if (!$lockedPayout) {
                throw new InvalidArgumentException('Payout request is not eligible for payment completion.');
            }

            // Verify uniqueness of transfer reference inside the locked transaction
            $duplicateRef = PayoutRequest::where('bank_transfer_reference', $transferRef)
                ->where('id', '!=', $payoutRequest->id)
                ->exists();
            if ($duplicateRef) {
                throw new InvalidArgumentException("Bank transfer reference '{$transferRef}' has already been recorded for another payout.");
            }

            $amountStr = number_format((float) $lockedPayout->amount, 2, '.', '');

            // Update PayoutRequest to paid with unique exception handling (SEC-06-01)
            try {
                $lockedPayout->update([
                    'status' => PayoutRequest::STATUS_PAID,
                    'paid_by' => $manager->id,
                    'paid_at' => now(),
                    'bank_transfer_reference' => $transferRef,
                    'bank_transfer_receipt_path' => $data['bank_transfer_receipt_path'] ?? null,
                    'bank_transfer_notes' => $data['bank_transfer_notes'] ?? null,
                ]);
            } catch (\Illuminate\Database\QueryException $e) {
                if (str_contains($e->getMessage(), 'UNIQUE') || str_contains($e->getMessage(), 'Duplicate') || $e->getCode() == 23000) {
                    throw new InvalidArgumentException("Bank transfer reference '{$transferRef}' has already been recorded for another payout.");
                }
                throw $e;
            }

            // Atomically adjust agent profile using BCMath without float casting (FIN-06-02)
            $profile = $lockedPayout->agent->agentProfile()->lockForUpdate()->first();
            if ($profile) {
                $currentWallet = (string) ($profile->wallet_balance ?? '0.00');

                // Settlement Non-Negativity Guard: Strict verification that wallet balance covers payout
                if (bccomp($currentWallet, $amountStr, 2) < 0) {
                    throw new InvalidArgumentException(
                        "Payout settlement blocked: Agent wallet balance (LKR {$currentWallet}) is insufficient to settle requested payout (LKR {$amountStr})."
                    );
                }

                $profile->wallet_balance = bcsub($currentWallet, $amountStr, 2);
                $profile->reserved_balance = bcsub((string) $profile->reserved_balance, $amountStr, 2);
                if (bccomp((string) $profile->reserved_balance, '0.00', 2) < 0) {
                    $profile->reserved_balance = '0.00';
                }
                $profile->total_withdrawn = bcadd((string) ($profile->total_withdrawn ?? '0.00'), $amountStr, 2);
                $profile->save();
            }

            // Append completed payout wallet transaction record
            WalletTransaction::create([
                'transaction_number' => WalletTransaction::generateTransactionNumber(),
                'agent_id' => $lockedPayout->agent_id,
                'transaction_type' => WalletTransaction::TYPE_PAYOUT_COMPLETED,
                'amount' => '-' . $amountStr,
                'balance_after' => $profile->wallet_balance,
                'available_balance_after' => $profile->available_balance,
                'currency' => 'LKR',
                'source_type' => PayoutRequest::class,
                'source_id' => $lockedPayout->id,
                'description' => "Payout completed #{$lockedPayout->reference_number} (Ref: {$transferRef})",
                'metadata' => [
                    'payout_reference' => $lockedPayout->reference_number,
                    'bank_transfer_reference' => $transferRef,
                    'paid_by' => $manager->id,
                    'paid_at' => now()->toIso8601String(),
                ],
            ]);

            AuditLog::log('payout_paid', $lockedPayout, null, [
                'payout_reference' => $lockedPayout->reference_number,
                'amount' => $amountStr,
                'bank_transfer_reference' => $transferRef,
                'paid_by' => $manager->id,
            ]);

            $lockedPayout->agent->notify(new PayoutStatusNotification($lockedPayout, 'paid'));

            return $lockedPayout;
        });
    }
}
