<?php

namespace App\Services;

use App\Models\AgentSalesTarget;
use App\Models\CommissionLedgerEntry;
use App\Models\Order;
use App\Models\User;
use App\Models\UserClosure;
use Carbon\Carbon;
use DomainException;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AgentAnalyticsService
{
    public function __construct(
        protected AgentHierarchyService $hierarchyService,
        protected PeriodBoundaryService $boundaryService
    ) {}

    // =========================================================================
    // 1. STRICT MONETARY NORMALIZATION & CONVERSION (REVISION 08)
    // =========================================================================

    /**
     * Normalize and validate a monetary decimal value strictly as a string.
     * Enforces at most 2 decimal places.
     * Empty strings and whitespace are strictly rejected.
     * Null represents zero only when explicitly permitted ($allowNullZero = true).
     * Never casts to PHP float.
     *
     * @param string|int|null $value Raw value to normalize
     * @param bool $allowNullZero Whether null is permitted to resolve to '0.00'
     * @return string Strict 2-decimal string (e.g., "10000.50", "0.10", "0.00")
     * @throws InvalidArgumentException If value is null (when not allowed), empty string, whitespace, malformed, or exceeds 2 decimal places
     */
    public static function normalizeDecimalString(string|int|null $value, bool $allowNullZero = false): string
    {
        // 1. Explicit Null Evaluation
        if ($value === null) {
            if ($allowNullZero) {
                return '0.00';
            }
            throw new InvalidArgumentException('Monetary value cannot be null.');
        }

        $str = trim((string) $value);

        // 2. Strict Empty-String & Whitespace Rejection
        if ($str === '') {
            throw new InvalidArgumentException('Monetary value cannot be an empty string or whitespace.');
        }

        // 3. Strict 2-Decimal Regex Validation
        if (!preg_match('/^-?\d+(\.\d{1,2})?$/', $str)) {
            throw new InvalidArgumentException("Invalid monetary decimal string: '{$str}'. Must be a valid numeric amount with at most 2 decimal places.");
        }

        // 4. Exact Base-10 String Normalization via BCMath
        return bcadd($str, '0.00', 2);
    }

    /**
     * Convert an integer cents string into a presentation decimal LKR string.
     *
     * @param string $centsStr
     * @return string 2-decimal LKR string (e.g., "9500.25")
     * @throws InvalidArgumentException If input is not a valid integer string
     */
    public static function centsToLkr(string $centsStr): string
    {
        $clean = trim($centsStr);

        if (!preg_match('/^-?\d+$/', $clean)) {
            throw new InvalidArgumentException("Invalid cents string: '{$centsStr}'. Cents must be an integer string without decimals.");
        }

        return bcdiv($clean, '100', 2);
    }

    /**
     * Format an exact decimal string as LKR currency with digit grouping.
     * Pure string operations: zero float conversions, zero integer casting.
     *
     * @param string|int|null $amount
     * @return string Formatted string e.g. "LKR 1,234,567.89"
     */
    public static function formatExactLkr(string|int|null $amount): string
    {
        $normalized = self::normalizeDecimalString($amount, false);

        $parts = explode('.', $normalized);
        $whole = $parts[0];
        $fraction = $parts[1] ?? '00';

        // Reverse whole string to group from the least significant digit
        $reversed = strrev($whole);
        $chunks = str_split($reversed, 3);
        $groupedReversed = implode(',', $chunks);
        $formattedWhole = strrev($groupedReversed);

        return 'LKR ' . $formattedWhole . '.' . $fraction;
    }

    /**
     * Calculate exact integer cents from subtotal and discount strings using BCMath.
     * Detects and rejects discounts exceeding subtotal as critical data-integrity violations.
     *
     * @param string|int|null $subtotal Order product subtotal (mandatory)
     * @param string|int|null $discount Order discount amount (nullable: null allowed as 0.00)
     * @return string Exact integer cents as string (e.g., "950025")
     * @throws InvalidArgumentException If subtotal is negative or malformed
     * @throws DomainException If discount exceeds subtotal
     */
    public static function calculateFullReturnCents(string|int|null $subtotal, string|int|null $discount): string
    {
        $subtotalStr = self::normalizeDecimalString($subtotal, false);
        $discountStr = self::normalizeDecimalString($discount, true);

        if (bccomp($subtotalStr, '0.00', 2) < 0) {
            throw new InvalidArgumentException("Subtotal cannot be negative: {$subtotalStr}");
        }

        if (bccomp($discountStr, '0.00', 2) < 0) {
            throw new InvalidArgumentException("Discount cannot be negative: {$discountStr}");
        }

        if (bccomp($discountStr, $subtotalStr, 2) > 0) {
            throw new DomainException("Data integrity violation: Discount ({$discountStr} LKR) exceeds subtotal ({$subtotalStr} LKR).");
        }

        $netDecimalStr = bcsub($subtotalStr, $discountStr, 2);

        return bcmul($netDecimalStr, '100', 0);
    }

    // =========================================================================
    // 2. DETERMINISTIC ATTRIBUTION QUERY SCOPE HELPER
    // =========================================================================

    /**
     * Apply deterministic attribution filter to an orders query builder.
     *
     * Precedence:
     * 1. If orders.agent_id is NOT NULL, attribute directly to agent_id.
     * 2. Only when orders.agent_id IS NULL, fall back to orders.referral_code.
     *
     * @param Builder|\Illuminate\Database\Eloquent\Builder $query
     * @param int|array<int> $agentIds
     * @param string|array<string>|null $referralCodes
     * @return void
     */
    public function applyAttributionScope($query, int|array $agentIds, string|array|null $referralCodes = null): void
    {
        $ids = is_array($agentIds) ? array_values(array_filter($agentIds)) : [$agentIds];
        $codes = [];

        if ($referralCodes !== null) {
            $rawCodes = is_array($referralCodes) ? $referralCodes : [$referralCodes];
            $codes = array_values(array_filter(array_map('trim', $rawCodes)));
        }

        $query->where(function ($q) use ($ids, $codes) {
            if (count($ids) === 1) {
                $q->where('orders.agent_id', $ids[0]);
            } elseif (count($ids) > 1) {
                $q->whereIn('orders.agent_id', $ids);
            } else {
                $q->whereRaw('0 = 1');
            }

            if (!empty($codes)) {
                $q->orWhere(function ($fallback) use ($codes) {
                    $fallback->whereNull('orders.agent_id');
                    if (count($codes) === 1) {
                        $fallback->where('orders.referral_code', $codes[0]);
                    } else {
                        $fallback->whereIn('orders.referral_code', $codes);
                    }
                });
            }
        });
    }

    // =========================================================================
    // 3. DATABASE-PORTABLE MONETARY AGGREGATION METHODS
    // =========================================================================

    /**
     * Compute Gross Product Sales and Product Discounts using a driver-safe exact strategy.
     * Population: P_commercial ('delivered', 'cash_collected', 'completed', 'returned').
     */
    public function getGrossSalesAndDiscounts(int|array $agentIds, string|array|null $referralCodes, string $startUtc, string $endUtc): array
    {
        $baseQuery = DB::table('orders')
            ->whereIn('orders.status', ['delivered', 'cash_collected', 'completed', 'returned'])
            ->where('orders.placed_at', '>=', $startUtc)
            ->where('orders.placed_at', '<', $endUtc);

        $this->applyAttributionScope($baseQuery, $agentIds, $referralCodes);

        if (DB::getDriverName() === 'mysql') {
            $result = (clone $baseQuery)->selectRaw('
                COALESCE(SUM(subtotal), 0.00) as sum_subtotal,
                COALESCE(SUM(discount_amount), 0.00) as sum_discount
            ')->first();

            return [
                'gross_product_sales' => self::normalizeDecimalString($result->sum_subtotal ?? '0.00', false),
                'product_discounts' => self::normalizeDecimalString($result->sum_discount ?? '0.00', true),
            ];
        }

        // SQLite: Avoid SQL REAL SUM. Retrieve formatted text via printf() and accumulate in BCMath
        $grossSales = '0.00';
        $discounts = '0.00';

        (clone $baseQuery)->selectRaw("
                orders.id,
                printf('%.2f', orders.subtotal) as subtotal_text,
                printf('%.2f', COALESCE(orders.discount_amount, 0.00)) as discount_text
            ")
            ->orderBy('orders.id')
            ->chunk(500, function ($rows) use (&$grossSales, &$discounts) {
                foreach ($rows as $row) {
                    $subStr = self::normalizeDecimalString($row->subtotal_text, false);
                    $discStr = self::normalizeDecimalString($row->discount_text, true);
                    $grossSales = bcadd($grossSales, $subStr, 2);
                    $discounts = bcadd($discounts, $discStr, 2);
                }
            });

        return [
            'gross_product_sales' => $grossSales,
            'product_discounts' => $discounts,
        ];
    }

    /**
     * Compute Full-Return Value and Fulfilled Partial Refunds using a driver-safe exact strategy.
     * Full returns: status = 'returned'.
     * Partial refunds: order_refunds on orders where status IN ('delivered', 'cash_collected', 'completed').
     */
    public function getCombinedRefundSummary(int|array $agentIds, string|array|null $referralCodes, string $startUtc, string $endUtc): array
    {
        // 1. Full Returns Base Query (orders where status = 'returned')
        $fullReturnsQuery = DB::table('orders')
            ->where('orders.status', 'returned')
            ->where('orders.placed_at', '>=', $startUtc)
            ->where('orders.placed_at', '<', $endUtc);

        $this->applyAttributionScope($fullReturnsQuery, $agentIds, $referralCodes);

        // 2. Partial Refunds Base Query (order_refunds on fulfilled orders ONLY)
        $partialRefundsQuery = DB::table('order_refunds as r')
            ->join('orders as o', 'o.id', '=', 'r.order_id')
            ->whereIn('o.status', ['delivered', 'cash_collected', 'completed'])
            ->where('o.placed_at', '>=', $startUtc)
            ->where('o.placed_at', '<', $endUtc);

        // Apply attribution on joined 'orders' (aliased as 'o')
        $ids = is_array($agentIds) ? array_values(array_filter($agentIds)) : [$agentIds];
        $codes = [];
        if ($referralCodes !== null) {
            $rawCodes = is_array($referralCodes) ? $referralCodes : [$referralCodes];
            $codes = array_values(array_filter(array_map('trim', $rawCodes)));
        }

        $partialRefundsQuery->where(function ($q) use ($ids, $codes) {
            if (count($ids) === 1) {
                $q->where('o.agent_id', $ids[0]);
            } elseif (count($ids) > 1) {
                $q->whereIn('o.agent_id', $ids);
            } else {
                $q->whereRaw('0 = 1');
            }

            if (!empty($codes)) {
                $q->orWhere(function ($fallback) use ($codes) {
                    $fallback->whereNull('o.agent_id');
                    if (count($codes) === 1) {
                        $fallback->where('o.referral_code', $codes[0]);
                    } else {
                        $fallback->whereIn('o.referral_code', $codes);
                    }
                });
            }
        });

        if (DB::getDriverName() === 'mysql') {
            $fullReturnsResult = (clone $fullReturnsQuery)->selectRaw('
                COALESCE(SUM(subtotal), 0.00) as sum_subtotal,
                COALESCE(SUM(discount_amount), 0.00) as sum_discount
            ')->first();

            $subtotalStr = self::normalizeDecimalString($fullReturnsResult->sum_subtotal ?? '0.00', false);
            $discountStr = self::normalizeDecimalString($fullReturnsResult->sum_discount ?? '0.00', true);

            if (bccomp($discountStr, $subtotalStr, 2) > 0) {
                throw new DomainException("Data integrity violation: Returned discount ({$discountStr}) exceeds returned subtotal ({$subtotalStr}).");
            }
            $netReturnedStr = bcsub($subtotalStr, $discountStr, 2);
            $fullReturnCentsStr = bcmul($netReturnedStr, '100', 0);

            $partialResult = (clone $partialRefundsQuery)->selectRaw('COALESCE(SUM(r.refund_cents), 0) as sum_cents')->first();
            $partialRefundCentsStr = trim((string) ($partialResult->sum_cents ?? '0'));
        } else {
            // SQLite: Stream rows with printf() and accumulate in BCMath
            $fullReturnCentsAcc = '0';
            (clone $fullReturnsQuery)->selectRaw("
                    orders.id,
                    printf('%.2f', orders.subtotal) as subtotal_text,
                    printf('%.2f', COALESCE(orders.discount_amount, 0.00)) as discount_text
                ")
                ->orderBy('orders.id')
                ->chunk(500, function ($rows) use (&$fullReturnCentsAcc) {
                    foreach ($rows as $row) {
                        $orderCents = self::calculateFullReturnCents($row->subtotal_text, $row->discount_text);
                        $fullReturnCentsAcc = bcadd($fullReturnCentsAcc, $orderCents, 0);
                    }
                });
            $fullReturnCentsStr = $fullReturnCentsAcc;

            $partialCentsAcc = '0';
            (clone $partialRefundsQuery)->select(['r.id', 'r.refund_cents'])
                ->orderBy('r.id')
                ->chunk(500, function ($rows) use (&$partialCentsAcc) {
                    foreach ($rows as $row) {
                        $cents = trim((string) $row->refund_cents);
                        $partialCentsAcc = bcadd($partialCentsAcc, $cents, 0);
                    }
                });
            $partialRefundCentsStr = $partialCentsAcc;
        }

        $totalDeductionCentsStr = bcadd($fullReturnCentsStr, $partialRefundCentsStr, 0);

        return [
            'full_return_order_cents' => $fullReturnCentsStr,
            'full_return_order_lkr' => self::centsToLkr($fullReturnCentsStr),
            'partial_refund_cents' => $partialRefundCentsStr,
            'partial_refund_lkr' => self::centsToLkr($partialRefundCentsStr),
            'total_deduction_cents' => $totalDeductionCentsStr,
            'total_deduction_lkr' => self::centsToLkr($totalDeductionCentsStr),
        ];
    }

    /**
     * Compute Period Commission Activity using verified event timestamps.
     * Approvals: approved_at in window. Reversals: reversed_at in window.
     */
    public function getPeriodCommissionActivity(int|array $agentIds, string $startUtc, string $endUtc): array
    {
        $ids = is_array($agentIds) ? array_values(array_filter($agentIds)) : [$agentIds];

        $approvedQuery = DB::table('commission_ledger_entries')
            ->whereIn('agent_id', $ids)
            ->where('status', CommissionLedgerEntry::STATUS_APPROVED)
            ->where('commission_type', '!=', CommissionLedgerEntry::TYPE_REVERSAL)
            ->where('approved_at', '>=', $startUtc)
            ->where('approved_at', '<', $endUtc);

        $reversalQuery = DB::table('commission_ledger_entries')
            ->whereIn('agent_id', $ids)
            ->where('commission_type', CommissionLedgerEntry::TYPE_REVERSAL)
            ->where('reversed_at', '>=', $startUtc)
            ->where('reversed_at', '<', $endUtc);

        if (DB::getDriverName() === 'mysql') {
            $approvedSum = $approvedQuery->sum('calculated_amount');
            $reversalSum = $reversalQuery->sum('calculated_amount');
            $approvedStr = self::normalizeDecimalString($approvedSum ?? '0.00', true);
            $reversalStr = self::normalizeDecimalString($reversalSum ?? '0.00', true);
        } else {
            $approvedAcc = '0.00';
            $approvedQuery->selectRaw("id, printf('%.2f', calculated_amount) as amount_text")
                ->orderBy('id')
                ->chunk(500, function ($rows) use (&$approvedAcc) {
                    foreach ($rows as $row) {
                        $approvedAcc = bcadd($approvedAcc, self::normalizeDecimalString($row->amount_text, false), 2);
                    }
                });

            $reversalAcc = '0.00';
            $reversalQuery->selectRaw("id, printf('%.2f', calculated_amount) as amount_text")
                ->orderBy('id')
                ->chunk(500, function ($rows) use (&$reversalAcc) {
                    foreach ($rows as $row) {
                        $reversalAcc = bcadd($reversalAcc, self::normalizeDecimalString($row->amount_text, false), 2);
                    }
                });

            $approvedStr = $approvedAcc;
            $reversalStr = $reversalAcc;
        }

        $netPeriodActivityStr = bcadd($approvedStr, $reversalStr, 2);

        return [
            'approved_credits' => $approvedStr,
            'reversal_debits' => $reversalStr,
            'net_period_activity' => $netPeriodActivityStr,
        ];
    }

    /**
     * Compute Active Commission Cohort for orders placed in the interval.
     * Uses approvedActive() and pendingActive() scopes on CommissionLedgerEntry.
     */
    public function getActiveCommissionCohort(int|array $agentIds, string $startUtc, string $endUtc): array
    {
        $ids = is_array($agentIds) ? array_values(array_filter($agentIds)) : [$agentIds];

        $approvedQuery = CommissionLedgerEntry::query()
            ->whereIn('agent_id', $ids)
            ->approvedActive()
            ->whereHas('order', function ($q) use ($startUtc, $endUtc) {
                $q->where('placed_at', '>=', $startUtc)
                  ->where('placed_at', '<', $endUtc);
            });

        $pendingQuery = CommissionLedgerEntry::query()
            ->whereIn('agent_id', $ids)
            ->pendingActive()
            ->whereHas('order', function ($q) use ($startUtc, $endUtc) {
                $q->where('placed_at', '>=', $startUtc)
                  ->where('placed_at', '<', $endUtc);
            });

        if (DB::getDriverName() === 'mysql') {
            $activeApproved = $approvedQuery->sum('calculated_amount');
            $activePending = $pendingQuery->sum('calculated_amount');
            $approvedStr = self::normalizeDecimalString($activeApproved ?? '0.00', true);
            $pendingStr = self::normalizeDecimalString($activePending ?? '0.00', true);
        } else {
            $approvedAcc = '0.00';
            $approvedQuery->selectRaw("id, printf('%.2f', calculated_amount) as amount_text")
                ->orderBy('id')
                ->chunk(500, function ($rows) use (&$approvedAcc) {
                    foreach ($rows as $row) {
                        $approvedAcc = bcadd($approvedAcc, self::normalizeDecimalString($row->amount_text, false), 2);
                    }
                });

            $pendingAcc = '0.00';
            $pendingQuery->selectRaw("id, printf('%.2f', calculated_amount) as amount_text")
                ->orderBy('id')
                ->chunk(500, function ($rows) use (&$pendingAcc) {
                    foreach ($rows as $row) {
                        $pendingAcc = bcadd($pendingAcc, self::normalizeDecimalString($row->amount_text, false), 2);
                    }
                });

            $approvedStr = $approvedAcc;
            $pendingStr = $pendingAcc;
        }

        return [
            'active_approved_commission' => $approvedStr,
            'active_pending_commission' => $pendingStr,
        ];
    }

    /**
     * Compute order counts across the 4 approved populations and customer counts.
     */
    public function getOrderCountsAndPopulations(int|array $agentIds, string|array|null $referralCodes, string $startUtc, string $endUtc): array
    {
        $baseQuery = DB::table('orders')
            ->where('orders.placed_at', '>=', $startUtc)
            ->where('orders.placed_at', '<', $endUtc);

        $this->applyAttributionScope($baseQuery, $agentIds, $referralCodes);

        $counts = (clone $baseQuery)->selectRaw("
            COUNT(id) as total_placed,
            COUNT(CASE WHEN status IN ('shipped', 'delivered', 'cash_collected', 'completed', 'refused', 'returned') THEN 1 END) as total_dispatched,
            COUNT(CASE WHEN status IN ('delivered', 'cash_collected', 'completed', 'returned') THEN 1 END) as total_commercial,
            COUNT(CASE WHEN status IN ('delivered', 'cash_collected', 'completed') THEN 1 END) as total_fulfilled,
            COUNT(CASE WHEN status = 'returned' THEN 1 END) as total_returned,
            COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as total_cancelled,
            COUNT(CASE WHEN status = 'refused' THEN 1 END) as total_refused,
            COUNT(DISTINCT CASE WHEN status IN ('delivered', 'cash_collected', 'completed', 'returned') AND customer_id IS NOT NULL THEN customer_id END) as registered_customers,
            COUNT(CASE WHEN status IN ('delivered', 'cash_collected', 'completed', 'returned') AND customer_id IS NULL THEN 1 END) as guest_orders
        ")->first();

        // Count distinct orders with partial refunds in fulfilled status
        $partialRefundOrdersCount = DB::table('order_refunds as r')
            ->join('orders as o', 'o.id', '=', 'r.order_id')
            ->whereIn('o.status', ['delivered', 'cash_collected', 'completed'])
            ->where('o.placed_at', '>=', $startUtc)
            ->where('o.placed_at', '<', $endUtc);

        $ids = is_array($agentIds) ? array_values(array_filter($agentIds)) : [$agentIds];
        $codes = [];
        if ($referralCodes !== null) {
            $rawCodes = is_array($referralCodes) ? $referralCodes : [$referralCodes];
            $codes = array_values(array_filter(array_map('trim', $rawCodes)));
        }

        $partialRefundOrdersCount->where(function ($q) use ($ids, $codes) {
            if (count($ids) === 1) {
                $q->where('o.agent_id', $ids[0]);
            } elseif (count($ids) > 1) {
                $q->whereIn('o.agent_id', $ids);
            } else {
                $q->whereRaw('0 = 1');
            }

            if (!empty($codes)) {
                $q->orWhere(function ($fallback) use ($codes) {
                    $fallback->whereNull('o.agent_id');
                    if (count($codes) === 1) {
                        $fallback->where('o.referral_code', $codes[0]);
                    } else {
                        $fallback->whereIn('o.referral_code', $codes);
                    }
                });
            }
        });

        $distinctPartialRefundOrders = (clone $partialRefundOrdersCount)->distinct('r.order_id')->count('r.order_id');

        // Sum of placed subtotals for Demand Gross AOV
        if (DB::getDriverName() === 'mysql') {
            $placedSubtotalSum = (clone $baseQuery)->sum('subtotal');
            $placedSubtotalStr = self::normalizeDecimalString($placedSubtotalSum ?? '0.00', true);
        } else {
            $subtotalAcc = '0.00';
            (clone $baseQuery)->selectRaw("id, printf('%.2f', subtotal) as subtotal_text")
                ->orderBy('id')
                ->chunk(500, function ($rows) use (&$subtotalAcc) {
                    foreach ($rows as $row) {
                        $subtotalAcc = bcadd($subtotalAcc, self::normalizeDecimalString($row->subtotal_text, false), 2);
                    }
                });
            $placedSubtotalStr = $subtotalAcc;
        }

        return [
            'total_placed_orders' => (int) ($counts->total_placed ?? 0),
            'total_dispatched_orders' => (int) ($counts->total_dispatched ?? 0),
            'total_commercial_orders' => (int) ($counts->total_commercial ?? 0),
            'total_fulfilled_orders' => (int) ($counts->total_fulfilled ?? 0),
            'total_returned_orders' => (int) ($counts->total_returned ?? 0),
            'total_cancelled_orders' => (int) ($counts->total_cancelled ?? 0),
            'total_refused_orders' => (int) ($counts->total_refused ?? 0),
            'registered_customer_count' => (int) ($counts->registered_customers ?? 0),
            'guest_order_count' => (int) ($counts->guest_orders ?? 0),
            'distinct_partial_refund_orders' => (int) $distinctPartialRefundOrders,
            'placed_subtotal_sum' => $placedSubtotalStr,
        ];
    }

    // =========================================================================
    // 4. COMPREHENSIVE KPI ROLLUP & SUMMARY
    // =========================================================================

    /**
     * Compute the full Revision 08 analytics metrics summary for an agent or team.
     */
    public function computeAnalyticsSummary(
        int|array $agentIds,
        string|array|null $referralCodes,
        string $startUtc,
        string $endUtc,
        ?string $salesTargetAmount = null
    ): array {
        // 1. Sales & Discounts
        $sales = $this->getGrossSalesAndDiscounts($agentIds, $referralCodes, $startUtc, $endUtc);
        $grossSales = $sales['gross_product_sales'];
        $discounts = $sales['product_discounts'];

        // 2. Refunds & Returns
        $refunds = $this->getCombinedRefundSummary($agentIds, $referralCodes, $startUtc, $endUtc);
        $fullReturnLkr = $refunds['full_return_order_lkr'];
        $partialRefundLkr = $refunds['partial_refund_lkr'];
        $totalDeductionsLkr = $refunds['total_deduction_lkr'];

        // 3. Net Product Sales = Gross - Discounts - Total Deductions
        $commercialNetBeforeDeductions = bcsub($grossSales, $discounts, 2);
        $netSales = bcsub($commercialNetBeforeDeductions, $totalDeductionsLkr, 2);

        // 4. Order Counts & Populations
        $counts = $this->getOrderCountsAndPopulations($agentIds, $referralCodes, $startUtc, $endUtc);
        $placedCount = $counts['total_placed_orders'];
        $dispatchedCount = $counts['total_dispatched_orders'];
        $commercialCount = $counts['total_commercial_orders'];
        $fulfilledCount = $counts['total_fulfilled_orders'];
        $returnedCount = $counts['total_returned_orders'];
        $partialRefundCount = $counts['distinct_partial_refund_orders'];

        // 5. AOV Metrics (Null on Zero Denominator)
        $grossAov = $placedCount > 0 ? bcdiv($counts['placed_subtotal_sum'], (string) $placedCount, 2) : null;
        $commercialGrossAov = $commercialCount > 0 ? bcdiv($grossSales, (string) $commercialCount, 2) : null;
        $netAov = $fulfilledCount > 0 ? bcdiv($netSales, (string) $fulfilledCount, 2) : null;

        // 6. Operational & Friction Rates (Null on Zero Denominator)
        $deliverySuccessRate = $dispatchedCount > 0
            ? bcmul(bcdiv((string) $fulfilledCount, (string) $dispatchedCount, 4), '100', 2) . '%'
            : null;

        $fullReturnRate = $commercialCount > 0
            ? bcmul(bcdiv((string) $returnedCount, (string) $commercialCount, 4), '100', 2) . '%'
            : null;

        $partialRefundIncidence = $commercialCount > 0
            ? bcmul(bcdiv((string) $partialRefundCount, (string) $commercialCount, 4), '100', 2) . '%'
            : null;

        $distinctFrictionCount = $returnedCount + $partialRefundCount; // Mutually exclusive populations
        $frictionRate = $commercialCount > 0
            ? bcmul(bcdiv((string) $distinctFrictionCount, (string) $commercialCount, 4), '100', 2) . '%'
            : null;

        // 7. Commission Metrics
        $commissions = $this->getActiveCommissionCohort($agentIds, $startUtc, $endUtc);
        $periodActivity = $this->getPeriodCommissionActivity($agentIds, $startUtc, $endUtc);

        // 8. Target Attainment (Null if no target assigned or non-positive target)
        $targetAttainment = null;
        if ($salesTargetAmount !== null && bccomp($salesTargetAmount, '0.00', 2) > 0) {
            $targetAttainment = bcmul(bcdiv($netSales, $salesTargetAmount, 4), '100', 2) . '%';
        }

        return [
            // Revenue & Sales
            'gross_product_sales' => $grossSales,
            'product_discounts' => $discounts,
            'full_return_order_value' => $fullReturnLkr,
            'fulfilled_partial_refund_amount' => $partialRefundLkr,
            'total_commercial_deductions' => $totalDeductionsLkr,
            'net_product_sales' => $netSales,

            // Average Order Value
            'gross_aov' => $grossAov,
            'commercial_gross_aov' => $commercialGrossAov,
            'net_aov' => $netAov,

            // Order Counts & Populations
            'total_placed_orders' => $placedCount,
            'total_dispatched_orders' => $dispatchedCount,
            'total_commercial_orders' => $commercialCount,
            'total_fulfilled_orders' => $fulfilledCount,
            'total_returned_orders' => $returnedCount,
            'total_cancelled_orders' => $counts['total_cancelled_orders'],
            'total_refused_orders' => $counts['total_refused_orders'],
            'registered_customer_count' => $counts['registered_customer_count'],
            'guest_order_count' => $counts['guest_order_count'],

            // Conversion & Friction Rates
            'delivery_success_rate' => $deliverySuccessRate,
            'full_return_order_rate' => $fullReturnRate,
            'partial_refund_order_incidence' => $partialRefundIncidence,
            'refund_return_order_rate' => $frictionRate,

            // Target Attainment
            'monthly_target' => $salesTargetAmount,
            'target_attainment_pct' => $targetAttainment,

            // Commission Metrics
            'active_approved_commission' => $commissions['active_approved_commission'],
            'active_pending_commission' => $commissions['active_pending_commission'],
            'period_approved_credits' => $periodActivity['approved_credits'],
            'period_reversal_debits' => $periodActivity['reversal_debits'],
            'period_net_commission' => $periodActivity['net_period_activity'],
        ];
    }

    // =========================================================================
    // 5. AGENT PERSONAL ANALYTICS
    // =========================================================================

    /**
     * Retrieve personal performance analytics for an individual agent.
     */
    public function getPersonalAnalytics(User $agent, array $boundaries): array
    {
        // 1. Fetch sales target for target month
        $targetMonth = $boundaries['targetMonth'];
        $target = AgentSalesTarget::where('agent_id', $agent->id)
            ->where('target_month', $targetMonth)
            ->first();

        $targetAmount = $target ? self::normalizeDecimalString($target->sales_target, false) : null;

        // 2. Compute Summary using personal ID and referral code
        $summary = $this->computeAnalyticsSummary(
            $agent->id,
            $agent->referral_code,
            $boundaries['startUtc'],
            $boundaries['endUtc'],
            $targetAmount
        );

        return [
            'agent' => [
                'id' => $agent->id,
                'name' => $agent->name,
                'email' => $agent->email,
                'referral_code' => $agent->referral_code,
                'role' => $agent->getRoleValue(),
            ],
            'period' => $boundaries,
            'metrics' => $summary,
            'target' => $target ? [
                'id' => $target->id,
                'target_month' => $target->target_month,
                'sales_target' => (string) $target->sales_target,
                'created_at' => $target->created_at?->toIso8601String(),
            ] : null,
        ];
    }

    // =========================================================================
    // 6. HLA TEAM & DOWNLINE ANALYTICS
    // =========================================================================

    /**
     * Retrieve aggregated downline team performance analytics for a High-Level Agent.
     */
    public function getTeamAnalytics(User $hlaAgent, array $boundaries): array
    {
        if (!$hlaAgent->isHighLevelAgent()) {
            throw new DomainException('Downline team analytics are exclusively accessible to High-Level Agents.');
        }

        // 1. Retrieve all downline agents using AgentHierarchyService
        $downline = $this->hierarchyService->getDownline($hlaAgent);

        if ($downline->isEmpty()) {
            return [
                'hla' => [
                    'id' => $hlaAgent->id,
                    'name' => $hlaAgent->name,
                    'referral_code' => $hlaAgent->referral_code,
                ],
                'downline_count' => 0,
                'period' => $boundaries,
                'team_summary' => $this->computeAnalyticsSummary([], [], $boundaries['startUtc'], $boundaries['endUtc'], null),
                'downline_members' => [],
            ];
        }

        $downlineIds = $downline->pluck('id')->all();
        $downlineCodes = $downline->pluck('referral_code')->filter()->all();

        // 2. Aggregated Team Summary across all downline agents
        $teamSummary = $this->computeAnalyticsSummary(
            $downlineIds,
            $downlineCodes,
            $boundaries['startUtc'],
            $boundaries['endUtc'],
            null
        );

        // 3. Per-member breakdown
        $targetMonth = $boundaries['targetMonth'];
        $targets = AgentSalesTarget::whereIn('agent_id', $downlineIds)
            ->where('target_month', $targetMonth)
            ->get()
            ->keyBy('agent_id');

        $closureDepths = UserClosure::where('ancestor_id', $hlaAgent->id)
            ->whereIn('descendant_id', $downlineIds)
            ->pluck('depth', 'descendant_id');

        $currentColomboMonth = Carbon::now(PeriodBoundaryService::TIMEZONE)->format('Y-m');
        $targetService = app(AgentSalesTargetService::class);

        $members = [];
        foreach ($downline as $member) {
            $memberTarget = $targets->get($member->id);
            $memberTargetAmount = $memberTarget ? self::normalizeDecimalString($memberTarget->sales_target, false) : null;

            $memberSummary = $this->computeAnalyticsSummary(
                $member->id,
                $member->referral_code,
                $boundaries['startUtc'],
                $boundaries['endUtc'],
                $memberTargetAmount
            );

            $isManagementControlled = $memberTarget ? $targetService->isTargetManagementControlled($memberTarget) : false;
            $isActiveLla = $member->isLowLevelAgent() && ((string) ($member->status->value ?? $member->status)) === 'active';

            $members[] = [
                'id' => $member->id,
                'name' => $member->name,
                'email' => $member->email,
                'referral_code' => $member->referral_code,
                'role' => $member->getRoleValue(),
                'status' => (string) ($member->status->value ?? $member->status),
                'parent_id' => $member->parent_id,
                'depth' => (int) ($closureDepths[$member->id] ?? 1),
                'metrics' => $memberSummary,
                'target' => $memberTarget ? [
                    'id' => $memberTarget->id,
                    'target_month' => $memberTarget->target_month,
                    'sales_target' => (string) $memberTarget->sales_target,
                    'sales_target_formatted' => self::formatExactLkr($memberTarget->sales_target),
                    'created_by' => $memberTarget->created_by,
                    'is_management_controlled' => $isManagementControlled,
                    'can_edit' => !$isManagementControlled && ($memberTarget->target_month >= $currentColomboMonth) && $isActiveLla,
                ] : null,
            ];
        }

        return [
            'hla' => [
                'id' => $hlaAgent->id,
                'name' => $hlaAgent->name,
                'referral_code' => $hlaAgent->referral_code,
            ],
            'downline_count' => $downline->count(),
            'period' => $boundaries,
            'team_summary' => $teamSummary,
            'downline_members' => $members,
        ];
    }

    // =========================================================================
    // 7. ADMIN / AGENT MANAGER OPERATIONAL LEADERBOARD & DIRECTORY
    // =========================================================================

    /**
     * Retrieve paginated agent performance analytics for administrative oversight.
     */
    public function getAdminLeaderboard(array $boundaries, array $filters = [], int $perPage = 15): array
    {
        $roleFilter = $filters['role'] ?? 'all';
        $search = trim($filters['search'] ?? '');

        $query = User::query()
            ->whereIn('role', ['high_level_agent', 'low_level_agent'])
            ->with(['agentProfile', 'parent:id,name,referral_code']);

        if ($roleFilter === 'high_level_agent' || $roleFilter === 'low_level_agent') {
            $query->where('role', $roleFilter);
        }

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('referral_code', 'like', "%{$search}%");
            });
        }

        $paginator = $query->orderBy('name')->paginate($perPage);

        $targetMonth = $boundaries['targetMonth'];
        $agentIds = $paginator->getCollection()->pluck('id')->all();

        $targets = AgentSalesTarget::whereIn('agent_id', $agentIds)
            ->where('target_month', $targetMonth)
            ->get()
            ->keyBy('agent_id');

        $rows = [];
        foreach ($paginator->getCollection() as $agent) {
            $agentTarget = $targets->get($agent->id);
            $targetAmt = $agentTarget ? self::normalizeDecimalString($agentTarget->sales_target, false) : null;

            $summary = $this->computeAnalyticsSummary(
                $agent->id,
                $agent->referral_code,
                $boundaries['startUtc'],
                $boundaries['endUtc'],
                $targetAmt
            );

            $rows[] = [
                'id' => $agent->id,
                'name' => $agent->name,
                'email' => $agent->email,
                'phone' => $agent->phone,
                'role' => $agent->getRoleValue(),
                'status' => $agent->status,
                'referral_code' => $agent->referral_code,
                'parent' => $agent->parent ? [
                    'id' => $agent->parent->id,
                    'name' => $agent->parent->name,
                    'referral_code' => $agent->parent->referral_code,
                ] : null,
                'target' => $agentTarget ? [
                    'id' => $agentTarget->id,
                    'target_month' => $agentTarget->target_month,
                    'sales_target' => (string) $agentTarget->sales_target,
                ] : null,
                'metrics' => $summary,
            ];
        }

        return [
            'period' => $boundaries,
            'filters' => $filters,
            'data' => $rows,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }

    // =========================================================================
    // 8. HISTORICAL DAILY SALES TRENDS
    // =========================================================================

    /**
     * Compute daily sales performance trend points within the interval.
     */
    public function getDailyPerformanceTrends(int|array $agentIds, string|array|null $referralCodes, string $startUtc, string $endUtc): array
    {
        $ordersQuery = DB::table('orders')
            ->whereIn('orders.status', ['delivered', 'cash_collected', 'completed', 'returned'])
            ->where('orders.placed_at', '>=', $startUtc)
            ->where('orders.placed_at', '<', $endUtc);

        $this->applyAttributionScope($ordersQuery, $agentIds, $referralCodes);

        $dateExpr = DB::getDriverName() === 'sqlite'
            ? "strftime('%Y-%m-%d', placed_at)"
            : "DATE(CONVERT_TZ(placed_at, '+00:00', '+05:30'))";

        $rows = (clone $ordersQuery)->selectRaw("
            {$dateExpr} as order_date,
            COUNT(id) as orders_count,
            COALESCE(SUM(subtotal), 0.00) as sum_subtotal,
            COALESCE(SUM(discount_amount), 0.00) as sum_discount
        ")
        ->groupBy('order_date')
        ->orderBy('order_date')
        ->get();

        $points = [];
        foreach ($rows as $row) {
            $subStr = self::normalizeDecimalString($row->sum_subtotal ?? '0.00', false);
            $discStr = self::normalizeDecimalString($row->sum_discount ?? '0.00', true);
            $netStr = bcsub($subStr, $discStr, 2);

            $points[] = [
                'date' => $row->order_date,
                'orders_count' => (int) $row->orders_count,
                'gross_sales' => $subStr,
                'net_sales' => $netStr,
            ];
        }

        return $points;
    }
}

