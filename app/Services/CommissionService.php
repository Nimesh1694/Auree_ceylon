<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\AuditLog;
use App\Models\CodCollection;
use App\Models\CommissionLedgerEntry;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CommissionService
{
    public function __construct(
        private readonly AgentHierarchyService $hierarchyService
    ) {}

    /**
     * Calculate and record pending commission ledger entries for an order.
     * Idempotent: will not create duplicate entries if already recorded.
     *
     * @return Collection<int, CommissionLedgerEntry>
     */
    public function calculateAndRecordPendingCommissions(Order $order): Collection
    {
        // 1. Idempotency Check: Prevent duplicate generation for the same order
        $existing = CommissionLedgerEntry::where('order_id', $order->id)
            ->where('commission_type', '!=', CommissionLedgerEntry::TYPE_REVERSAL)
            ->get();

        if ($existing->isNotEmpty()) {
            return $existing;
        }

        // 2. Attribution Check: Must have a valid referral code (Rule C)
        $referralCode = $order->referral_code ? strtoupper(trim($order->referral_code)) : null;
        if (!$referralCode) {
            return collect();
        }

        // 3. Selling Agent Verification: Must be an active agent (Rule D & E)
        $sellingAgent = User::where('referral_code', $referralCode)
            ->whereIn('role', [UserRole::HIGH_LEVEL_AGENT, UserRole::LOW_LEVEL_AGENT])
            ->where('status', UserStatus::ACTIVE)
            ->first();

        if (!$sellingAgent) {
            return collect();
        }

        // Management roles cannot receive per-sale commissions (Rule F)
        if ($sellingAgent->isManagement()) {
            return collect();
        }

        // 4. Commission Calculation Base: Product Subtotal ONLY (excludes delivery fees/courier costs)
        $baseAmount = (string) $order->subtotal;
        if (bccomp($baseAmount, '0', 2) <= 0) {
            return collect();
        }

        // 5. Configurable Commission Rates
        $directRate = (string) Setting::get('direct_agent_commission_percent', 25.0);
        $uplineRate = (string) Setting::get('upline_agent_commission_percent', 10.0);

        return DB::transaction(function () use ($order, $sellingAgent, $baseAmount, $directRate, $uplineRate) {
            // Pessimistically check if commissions for this order have already been recorded inside transaction
            $existing = CommissionLedgerEntry::where('order_id', $order->id)
                ->where('commission_type', '!=', CommissionLedgerEntry::TYPE_REVERSAL)
                ->lockForUpdate()
                ->get();

            if ($existing->isNotEmpty()) {
                return $existing;
            }

            $createdEntries = collect();

            if ($sellingAgent->isHighLevelAgent()) {
                // =============================================================
                // RULE A: High-Level Agent Direct Sale (25%)
                // =============================================================
                $directAmount = $this->calculateAmount($baseAmount, $directRate);

                $entry = CommissionLedgerEntry::create([
                    'entry_number' => CommissionLedgerEntry::generateEntryNumber(),
                    'order_id' => $order->id,
                    'agent_id' => $sellingAgent->id,
                    'selling_agent_id' => $sellingAgent->id,
                    'commission_type' => CommissionLedgerEntry::TYPE_DIRECT_HIGH_LEVEL,
                    'calculation_base' => 'order_subtotal',
                    'base_amount' => $baseAmount,
                    'rate_percentage' => $directRate,
                    'calculated_amount' => $directAmount,
                    'currency' => 'LKR',
                    'status' => CommissionLedgerEntry::STATUS_PENDING,
                    'rate_snapshot' => [
                        'tier' => 'high_level_direct',
                        'rate_percentage' => $directRate,
                        'base_amount' => $baseAmount,
                        'rule' => 'Rule A — High-level agent direct sale 25%',
                        'recorded_at' => now()->toIso8601String(),
                    ],
                ]);

                $createdEntries->push($entry);

            } elseif ($sellingAgent->isLowLevelAgent()) {
                // =============================================================
                // RULE B: Low-Level Agent Direct Sale (25%) + Upline Override (10%)
                // =============================================================
                $directAmount = $this->calculateAmount($baseAmount, $directRate);

                $directEntry = CommissionLedgerEntry::create([
                    'entry_number' => CommissionLedgerEntry::generateEntryNumber(),
                    'order_id' => $order->id,
                    'agent_id' => $sellingAgent->id,
                    'selling_agent_id' => $sellingAgent->id,
                    'commission_type' => CommissionLedgerEntry::TYPE_DIRECT_LOW_LEVEL,
                    'calculation_base' => 'order_subtotal',
                    'base_amount' => $baseAmount,
                    'rate_percentage' => $directRate,
                    'calculated_amount' => $directAmount,
                    'currency' => 'LKR',
                    'status' => CommissionLedgerEntry::STATUS_PENDING,
                    'rate_snapshot' => [
                        'tier' => 'low_level_direct',
                        'rate_percentage' => $directRate,
                        'base_amount' => $baseAmount,
                        'rule' => 'Rule B — Low-level agent direct sale 25%',
                        'recorded_at' => now()->toIso8601String(),
                    ],
                ]);

                $createdEntries->push($directEntry);

                // Find the direct High-level upline in the hierarchy closure
                $uplineChain = $this->hierarchyService->getUpline($sellingAgent);
                $highLevelUpline = $uplineChain->first(function ($ancestor) {
                    return $ancestor->role === UserRole::HIGH_LEVEL_AGENT && $ancestor->status === UserStatus::ACTIVE;
                });

                if ($highLevelUpline) {
                    $uplineAmount = $this->calculateAmount($baseAmount, $uplineRate);

                    $uplineEntry = CommissionLedgerEntry::create([
                        'entry_number' => CommissionLedgerEntry::generateEntryNumber(),
                        'order_id' => $order->id,
                        'agent_id' => $highLevelUpline->id,
                        'selling_agent_id' => $sellingAgent->id,
                        'commission_type' => CommissionLedgerEntry::TYPE_UPLINE_OVERRIDE,
                        'calculation_base' => 'order_subtotal',
                        'base_amount' => $baseAmount,
                        'rate_percentage' => $uplineRate,
                        'calculated_amount' => $uplineAmount,
                        'currency' => 'LKR',
                        'status' => CommissionLedgerEntry::STATUS_PENDING,
                        'rate_snapshot' => [
                            'tier' => 'high_level_upline_override',
                            'rate_percentage' => $uplineRate,
                            'base_amount' => $baseAmount,
                            'selling_agent_id' => $sellingAgent->id,
                            'selling_agent_name' => $sellingAgent->name,
                            'rule' => 'Rule B — Direct High-level upline 10% override',
                            'recorded_at' => now()->toIso8601String(),
                        ],
                    ]);

                    $createdEntries->push($uplineEntry);
                }
            }

            // Audit log creation
            AuditLog::log('commissions_generated', $order, null, [
                'order_id' => $order->id,
                'entries_count' => $createdEntries->count(),
                'total_commission' => $createdEntries->sum('calculated_amount'),
            ]);

            return $createdEntries;
        });
    }

    /**
     * Process and approve pending commissions whose order return window has elapsed
     * and whose COD collection has been confirmed/matched.
     * Atomic database status updates and row-locking protect against race conditions.
     *
     * @return int Count of approved commission entries
     */
    public function approveEligibleCommissions(): int
    {
        $returnDays = (int) Setting::get('commission_return_period_days', 7);
        $thresholdDate = now()->subDays($returnDays);

        $pendingEntries = CommissionLedgerEntry::pendingActive()
            ->with(['order.delivery', 'order.codCollection', 'agent.agentProfile'])
            ->get();

        $approvedCount = 0;

        foreach ($pendingEntries as $entry) {
            $order = $entry->order;
            if (!$order) {
                continue;
            }

            // Must be in delivered or completed state
            $isDelivered = in_array($order->status, [
                OrderStateMachineService::STATUS_DELIVERED,
                OrderStateMachineService::STATUS_CASH_COLLECTED,
                OrderStateMachineService::STATUS_COMPLETED,
            ], true);

            if (!$isDelivered) {
                continue;
            }

            // COD collection must be matched/verified
            $codCollection = $order->codCollection;
            $isCodSettled = $codCollection && $codCollection->reconciliation_status === CodCollection::STATUS_MATCHED;

            if (!$isCodSettled && $order->status !== OrderStateMachineService::STATUS_COMPLETED) {
                continue;
            }

            // Check that the return period window has expired
            $deliveryDate = $order->delivery?->delivered_at ?? $order->placed_at ?? $order->created_at;
            if ($deliveryDate > $thresholdDate) {
                continue; // Return window still active
            }

            // Approve commission entry transactionally with row-level lock and atomic conditional update (SEC-05-01)
            DB::transaction(function () use ($entry, $order, &$approvedCount) {
                // Lock the pending ledger row first to serialize against concurrent maturation or reversal workers
                $lockedEntry = CommissionLedgerEntry::where('id', $entry->id)
                    ->where('status', CommissionLedgerEntry::STATUS_PENDING)
                    ->lockForUpdate()
                    ->first();

                if (!$lockedEntry) {
                    return;
                }

                // Verify no compensating reversal exists while holding the exclusive row lock
                $hasReversal = CommissionLedgerEntry::where('original_entry_id', $lockedEntry->id)->exists();
                if ($hasReversal) {
                    return;
                }

                // Primary-key-only conditional update (avoids MySQL 1093 self-referential subquery in UPDATE)
                $affected = CommissionLedgerEntry::where('id', $lockedEntry->id)
                    ->where('status', CommissionLedgerEntry::STATUS_PENDING)
                    ->update([
                        'status' => CommissionLedgerEntry::STATUS_APPROVED,
                        'approved_at' => now(),
                    ]);

                // If another concurrent process (e.g. cron or concurrent admin) already updated this entry, abort
                if ($affected === 0) {
                    return;
                }

                // Lock beneficiary agent profile row and increment wallet balances atomically
                $agent = $entry->agent;
                if ($agent && $agent->agentProfile) {
                    $profile = $agent->agentProfile()->lockForUpdate()->first();
                    if ($profile) {
                        $calcAmt = number_format((float) $entry->calculated_amount, 2, '.', '');
                        $outstandingClawback = (string) ($profile->outstanding_clawback ?? '0.00');

                        // Amortize any outstanding clawback debt before increasing spendable wallet balance
                        if (bccomp($outstandingClawback, '0.00', 2) > 0) {
                            if (bccomp($calcAmt, $outstandingClawback, 2) >= 0) {
                                $absorbed = $outstandingClawback;
                                $netWalletCredit = bcsub($calcAmt, $outstandingClawback, 2);
                                $profile->outstanding_clawback = '0.00';
                            } else {
                                $absorbed = $calcAmt;
                                $netWalletCredit = '0.00';
                                $profile->outstanding_clawback = bcsub($outstandingClawback, $calcAmt, 2);
                            }
                        } else {
                            $absorbed = '0.00';
                            $netWalletCredit = $calcAmt;
                        }

                        $profile->wallet_balance = bcadd((string) $profile->wallet_balance, $netWalletCredit, 2);
                        $profile->total_earned = bcadd((string) $profile->total_earned, $calcAmt, 2);
                        $profile->save();

                        WalletTransaction::create([
                            'transaction_number' => WalletTransaction::generateTransactionNumber(),
                            'agent_id' => $agent->id,
                            'transaction_type' => WalletTransaction::TYPE_COMMISSION_CREDIT,
                            'amount' => $calcAmt,
                            'balance_after' => $profile->wallet_balance,
                            'available_balance_after' => $profile->available_balance,
                            'currency' => $entry->currency ?? 'LKR',
                            'source_type' => CommissionLedgerEntry::class,
                            'source_id' => $entry->id,
                            'description' => "Commission approved on Order #{$order->order_number}",
                            'metadata' => [
                                'order_id' => $order->id,
                                'order_number' => $order->order_number,
                                'entry_number' => $entry->entry_number,
                                'commission_type' => $entry->commission_type,
                                'absorbed_clawback' => $absorbed,
                                'net_wallet_credit' => $netWalletCredit,
                                'outstanding_clawback_after' => (string) $profile->outstanding_clawback,
                            ],
                        ]);
                    }
                }

                AuditLog::log('commission_approved', $entry, ['status' => 'pending'], ['status' => 'approved']);
                $approvedCount++;
            });
        }

        return $approvedCount;
    }

    /**
     * Reverse commissions when an order is cancelled, refused, or returned.
     * Creates an append-only reversal entry in the ledger and preserves original records.
     * Enforces atomic database row-locking and unique constraint verification (SEC-05-02).
     *
     * @return int Count of reversed entries
     */
    public function reverseOrderCommissions(Order $order, string $reason): int
    {
        $entries = CommissionLedgerEntry::where('order_id', $order->id)
            ->where('commission_type', '!=', CommissionLedgerEntry::TYPE_REVERSAL)
            ->whereIn('status', [CommissionLedgerEntry::STATUS_PENDING, CommissionLedgerEntry::STATUS_APPROVED])
            ->whereDoesntHave('reversals')
            ->with('agent.agentProfile')
            ->get();

        if ($entries->isEmpty()) {
            return 0;
        }

        $reversedCount = 0;

        DB::transaction(function () use ($entries, $order, $reason, &$reversedCount) {
            foreach ($entries as $originalEntry) {
                // Lock and verify original entry status atomically inside transaction
                $lockedEntry = CommissionLedgerEntry::where('id', $originalEntry->id)
                    ->whereIn('status', [CommissionLedgerEntry::STATUS_PENDING, CommissionLedgerEntry::STATUS_APPROVED])
                    ->whereDoesntHave('reversals')
                    ->lockForUpdate()
                    ->first();

                // If already reversed by another concurrent transaction, skip
                if (!$lockedEntry) {
                    continue;
                }

                // Verify that a reversal record does not already exist for this entry
                $alreadyReversed = CommissionLedgerEntry::where('original_entry_id', $originalEntry->id)->exists();
                if ($alreadyReversed) {
                    continue;
                }

                $wasApproved = $lockedEntry->status === CommissionLedgerEntry::STATUS_APPROVED;

                // Immutable Financial Ledger Rule: The original entry is NEVER updated or deleted.
                // It remains in its original state as an immutable historical record.
                // Reversals are represented solely by appending a new compensating negative entry.

                $absAmount = abs((float) $originalEntry->calculated_amount);
                $negativeAmount = bccomp((string) $absAmount, '0', 2) > 0 ? '-' . number_format($absAmount, 2, '.', '') : '0.00';

                $reversalEntry = CommissionLedgerEntry::create([
                    'entry_number' => CommissionLedgerEntry::generateEntryNumber(),
                    'order_id' => $order->id,
                    'agent_id' => $originalEntry->agent_id,
                    'selling_agent_id' => $originalEntry->selling_agent_id,
                    'commission_type' => CommissionLedgerEntry::TYPE_REVERSAL,
                    'calculation_base' => $originalEntry->calculation_base,
                    'base_amount' => $originalEntry->base_amount,
                    'rate_percentage' => $originalEntry->rate_percentage,
                    'calculated_amount' => $negativeAmount,
                    'currency' => $originalEntry->currency,
                    'status' => CommissionLedgerEntry::STATUS_REVERSED,
                    'original_entry_id' => $originalEntry->id,
                    'reversal_reason' => $reason,
                    'reversed_at' => now(),
                    'rate_snapshot' => [
                        'reversal_of' => $originalEntry->entry_number,
                        'reason' => $reason,
                        'reversal_timestamp' => now()->toIso8601String(),
                    ],
                ]);

                // If original was already approved, apply clawback against unreserved wallet funds first with row locking
                if ($wasApproved && $originalEntry->agent && $originalEntry->agent->agentProfile) {
                    $profile = $originalEntry->agent->agentProfile()->lockForUpdate()->first();
                    if ($profile) {
                        $calcAmt = number_format((float) $originalEntry->calculated_amount, 2, '.', '');
                        $grossWallet = (string) ($profile->wallet_balance ?? '0.00');
                        $reservedBalance = (string) ($profile->reserved_balance ?? '0.00');

                        // Available unreserved wallet funds (FIN-05-03: Protected Reserved Funds)
                        $unreserved = bcsub($grossWallet, $reservedBalance, 2);
                        if (bccomp($unreserved, '0.00', 2) < 0) {
                            $unreserved = '0.00';
                        }

                        // Apply clawback against unreserved wallet funds first; protect reserved funds
                        if (bccomp($calcAmt, $unreserved, 2) <= 0) {
                            $walletDeduction = $calcAmt;
                            $excessClawback = '0.00';
                        } else {
                            $walletDeduction = $unreserved;
                            $excessClawback = bcsub($calcAmt, $unreserved, 2);
                        }

                        // Deduct from wallet without invading protected reserved balance
                        $profile->wallet_balance = bcsub($grossWallet, $walletDeduction, 2);
                        if (bccomp((string) $profile->wallet_balance, '0.00', 2) < 0) {
                            $profile->wallet_balance = '0.00';
                        }

                        // Record excess clawback as identifiable financial obligation
                        if (bccomp($excessClawback, '0.00', 2) > 0) {
                            $profile->outstanding_clawback = bcadd(
                                (string) ($profile->outstanding_clawback ?? '0.00'),
                                $excessClawback,
                                2
                            );
                        }

                        // Deduct net cumulative lifetime earnings
                        $profile->total_earned = bcsub((string) $profile->total_earned, $calcAmt, 2);
                        $profile->save();

                        WalletTransaction::create([
                            'transaction_number' => WalletTransaction::generateTransactionNumber(),
                            'agent_id' => $originalEntry->agent_id,
                            'transaction_type' => WalletTransaction::TYPE_COMMISSION_REVERSAL,
                            'amount' => '-' . $calcAmt,
                            'balance_after' => $profile->wallet_balance,
                            'available_balance_after' => $profile->available_balance,
                            'currency' => $originalEntry->currency ?? 'LKR',
                            'source_type' => CommissionLedgerEntry::class,
                            'source_id' => $reversalEntry->id,
                            'description' => "Commission reversed on Order #{$order->order_number} ({$reason})",
                            'metadata' => [
                                'order_id' => $order->id,
                                'order_number' => $order->order_number,
                                'original_entry_number' => $originalEntry->entry_number,
                                'reversal_entry_number' => $reversalEntry->entry_number,
                                'reason' => $reason,
                                'wallet_deduction' => $walletDeduction,
                                'excess_clawback_added' => $excessClawback,
                                'outstanding_clawback_after' => (string) $profile->outstanding_clawback,
                            ],
                        ]);
                    }
                }

                AuditLog::log('commission_reversed', $reversalEntry, null, [
                    'order_id' => $order->id,
                    'original_entry_id' => $originalEntry->id,
                    'reason' => $reason,
                    'amount' => $negativeAmount,
                ]);

                $reversedCount++;
            }
        });

        return $reversedCount;
    }

    /**
     * Compute decimal-safe financial percentage amount with commercial half-up rounding to 2 decimal places (FIN-05-01).
     * Pure BCMath arithmetic; never casts to PHP floating-point numbers.
     *
     * Rounding policy:
     * - Multiplies base by rate with scale 6.
     * - Divides by 100 with scale 6.
     * - Adds 0.005 for positive numbers (or subtracts 0.005 for negative numbers) with scale 4 to achieve standard commercial half-up rounding.
     * - Truncates to 2 decimal places with scale 2 using bcdiv.
     *
     * Example: 10.07 * 25% = 2.5175 -> +0.005 = 2.5225 -> truncated to 2 decimals = "2.52"
     *
     * @param float|string $base
     * @param float|string $ratePercentage
     * @return string Exact two-decimal string representation (e.g. "2500.00")
     */
    public function calculateAmount(float|string $base, float|string $ratePercentage): string
    {
        $baseStr = (string) $base;
        $rateStr = (string) $ratePercentage;

        if (function_exists('bcmul') && function_exists('bcdiv') && function_exists('bcadd') && function_exists('bcsub')) {
            $product = bcmul($baseStr, $rateStr, 6);
            $rawAmount = bcdiv($product, '100', 6);

            // Commercial half-up rounding: add half a cent (0.005) for positive, subtract 0.005 for negative
            if (bccomp($rawAmount, '0', 6) >= 0) {
                $rounded = bcadd($rawAmount, '0.005', 4);
            } else {
                $rounded = bcsub($rawAmount, '0.005', 4);
            }

            // Truncate to exact 2 decimal places
            return bcdiv($rounded, '1', 2);
        }

        // Integer-cents fallback if BCMath functions are somehow not installed
        $baseCents = (int) round((float) $baseStr * 100);
        $rateScaled = (int) round((float) $rateStr * 100);
        $resultCents = (int) floor(($baseCents * $rateScaled + 5000) / 10000);
        return number_format($resultCents / 100, 2, '.', '');
    }
}
