<?php

namespace App\Services;

use App\Models\CommissionLedgerEntry;
use App\Models\User;
use App\Models\WalletTransaction;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class WalletService
{
    /**
     * Compute comprehensive financial wallet metrics for an agent.
     */
    public function getWalletSummary(User $agent): array
    {
        $profile = $agent->agentProfile;

        $grossBalance = (string) ($profile?->wallet_balance ?? '0.00');
        $reservedBalance = (string) ($profile?->reserved_balance ?? '0.00');
        $availableBalance = bcsub($grossBalance, $reservedBalance, 2);
        if (bccomp($availableBalance, '0', 2) < 0) {
            $availableBalance = '0.00';
        }

        $totalEarned = (string) ($profile?->total_earned ?? '0.00');
        $totalWithdrawn = (string) ($profile?->total_withdrawn ?? '0.00');

        $pendingCommissions = CommissionLedgerEntry::where('agent_id', $agent->id)
            ->pendingActive()
            ->sum('calculated_amount');

        $reversedCommissions = CommissionLedgerEntry::where('agent_id', $agent->id)
            ->where('commission_type', CommissionLedgerEntry::TYPE_REVERSAL)
            ->sum('calculated_amount');

        $bankConfigured = !empty($profile?->bank_name)
            && !empty($profile?->bank_account_number)
            && !empty($profile?->bank_account_name);

        return [
            'wallet_balance' => (float) $grossBalance,
            'reserved_balance' => (float) $reservedBalance,
            'available_balance' => (float) $availableBalance,
            'total_earned' => (float) $totalEarned,
            'total_withdrawn' => (float) $totalWithdrawn,
            'outstanding_clawback' => (float) ($profile?->outstanding_clawback ?? 0.00),
            'pending_commissions' => (float) $pendingCommissions,
            'reversed_commissions' => (float) $reversedCommissions,
            'kyc_status' => $profile?->kyc_status ?? 'pending',
            'bank_configured' => $bankConfigured,
            'bank_details' => [
                'bank_name' => $profile?->bank_name,
                'bank_branch' => $profile?->bank_branch,
                'bank_account_number' => $profile?->bank_account_number,
                'bank_account_name' => $profile?->bank_account_name,
            ],
        ];
    }

    /**
     * Generate an official monthly statement for an agent.
     */
    public function getStatement(User $agent, int $year, int $month): array
    {
        $periodStart = Carbon::create($year, $month, 1)->startOfMonth();
        $periodEnd = Carbon::create($year, $month, 1)->endOfMonth();

        // Prior transaction immediately before periodStart
        $lastPriorTx = WalletTransaction::where('agent_id', $agent->id)
            ->where('created_at', '<', $periodStart)
            ->latest('created_at')
            ->latest('id')
            ->first();

        $openingGrossBalance = $lastPriorTx ? (string) $lastPriorTx->balance_after : '0.00';
        $openingAvailableBalance = $lastPriorTx ? (string) $lastPriorTx->available_balance_after : $openingGrossBalance;
        $openingReservedBalance = bcsub($openingGrossBalance, $openingAvailableBalance, 2);
        if (bccomp($openingReservedBalance, '0.00', 2) < 0) {
            $openingReservedBalance = '0.00';
        }

        // Transactions within the period
        $transactions = WalletTransaction::where('agent_id', $agent->id)
            ->whereBetween('created_at', [$periodStart, $periodEnd])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        // Categorized totals using BCMath
        $approvedCredits = '0.00';
        $commissionReversals = '0.00';
        $newReservations = '0.00';
        $releasedReservations = '0.00';
        $completedPayouts = '0.00';

        foreach ($transactions as $tx) {
            $amtStr = number_format(abs((float) $tx->amount), 2, '.', '');
            switch ($tx->transaction_type) {
                case WalletTransaction::TYPE_COMMISSION_CREDIT:
                    $approvedCredits = bcadd($approvedCredits, $amtStr, 2);
                    break;
                case WalletTransaction::TYPE_COMMISSION_REVERSAL:
                    $commissionReversals = bcadd($commissionReversals, $amtStr, 2);
                    break;
                case WalletTransaction::TYPE_PAYOUT_RESERVED:
                    $newReservations = bcadd($newReservations, $amtStr, 2);
                    break;
                case WalletTransaction::TYPE_PAYOUT_RELEASED:
                    $releasedReservations = bcadd($releasedReservations, $amtStr, 2);
                    break;
                case WalletTransaction::TYPE_PAYOUT_COMPLETED:
                    $completedPayouts = bcadd($completedPayouts, $amtStr, 2);
                    break;
            }
        }

        // Exact mathematical reconciliation for closing gross balance:
        // Closing Gross = Opening Gross + Credits - Reversals - Completed Payouts
        $grossAdditions = bcadd($openingGrossBalance, $approvedCredits, 2);
        $grossDeductions = bcadd($commissionReversals, $completedPayouts, 2);
        $closingGrossBalance = bcsub($grossAdditions, $grossDeductions, 2);
        if (bccomp($closingGrossBalance, '0.00', 2) < 0) {
            $closingGrossBalance = '0.00';
        }

        // Closing reserved balance reconciliation:
        // Closing Reserved = Opening Reserved + New Reservations - Released Reservations - Completed Payouts
        $reservedAdditions = bcadd($openingReservedBalance, $newReservations, 2);
        $reservedDeductions = bcadd($releasedReservations, $completedPayouts, 2);
        $closingReservedBalance = bcsub($reservedAdditions, $reservedDeductions, 2);
        if (bccomp($closingReservedBalance, '0.00', 2) < 0) {
            $closingReservedBalance = '0.00';
        }

        // Closing available balance = Closing Gross - Closing Reserved
        $closingAvailableBalance = bcsub($closingGrossBalance, $closingReservedBalance, 2);
        if (bccomp($closingAvailableBalance, '0.00', 2) < 0) {
            $closingAvailableBalance = '0.00';
        }

        $profile = $agent->agentProfile;

        return [
            'agent' => [
                'id' => $agent->id,
                'name' => $agent->name,
                'email' => $agent->email,
                'phone' => $agent->phone,
                'referral_code' => $agent->referral_code,
                'business_name' => $profile?->business_name,
                'nic' => $profile?->nic,
                'bank_name' => $profile?->bank_name,
                'bank_account_number' => $profile?->bank_account_number,
                'bank_account_name' => $profile?->bank_account_name,
            ],
            'period' => [
                'year' => $year,
                'month' => $month,
                'month_name' => $periodStart->format('F Y'),
                'start_date' => $periodStart->toDateString(),
                'end_date' => $periodEnd->toDateString(),
            ],
            'opening_balance' => (float) $openingGrossBalance,
            'opening_gross_balance' => (float) $openingGrossBalance,
            'opening_reserved_balance' => (float) $openingReservedBalance,
            'opening_available_balance' => (float) $openingAvailableBalance,
            'total_credited' => (float) $approvedCredits,
            'total_reversed' => (float) $commissionReversals,
            'total_paid_out' => (float) $completedPayouts,
            'completed_payouts' => (float) $completedPayouts,
            'new_reservations' => (float) $newReservations,
            'released_reservations' => (float) $releasedReservations,
            'net_change_gross' => bcsub($closingGrossBalance, $openingGrossBalance, 2),
            'net_change_available' => bcsub($closingAvailableBalance, $openingAvailableBalance, 2),
            'closing_balance' => (float) $closingGrossBalance,
            'closing_gross_balance' => (float) $closingGrossBalance,
            'closing_reserved_balance' => (float) $closingReservedBalance,
            'closing_available_balance' => (float) $closingAvailableBalance,
            'summary' => [
                'opening_gross_balance' => (float) $openingGrossBalance,
                'opening_reserved_balance' => (float) $openingReservedBalance,
                'opening_available_balance' => (float) $openingAvailableBalance,
                'approved_credits' => (float) $approvedCredits,
                'commission_reversals' => (float) $commissionReversals,
                'payout_reservations' => (float) $newReservations,
                'payout_releases' => (float) $releasedReservations,
                'completed_payouts' => (float) $completedPayouts,
                'closing_gross_balance' => (float) $closingGrossBalance,
                'closing_reserved_balance' => (float) $closingReservedBalance,
                'closing_available_balance' => (float) $closingAvailableBalance,
            ],
            'transactions' => $transactions,
        ];
    }
}
