<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\CodCollection;
use App\Models\CommissionLedgerEntry;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PayoutRequest;
use App\Models\Setting;
use App\Models\WalletTransaction;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FinancialReportService
{
    public const STATUS_CERTIFIED = 'certified';
    public const STATUS_QUALIFIED = 'qualified';
    public const STATUS_PROVISIONAL = 'provisional';

    /**
     * Generate comprehensive Operating Financial Contribution statement.
     *
     * @param CarbonInterface|string|null $startDate
     * @param CarbonInterface|string|null $endDate
     * @param string $perspective 'fulfilled_date' | 'placed_date' | 'cash_date'
     * @return array<string, mixed>
     */
    public function generateReport(
        CarbonInterface|string|null $startDate = null,
        CarbonInterface|string|null $endDate = null,
        string $perspective = 'fulfilled_date'
    ): array {
        $parsedStartDate = $startDate ? Carbon::parse($startDate)->startOfDay() : null;
        $parsedEndDate = $endDate ? Carbon::parse($endDate)->endOfDay() : null;
        $now = Carbon::now();

        $returnWindowDays = (int) Setting::get('commission_return_period_days', 7);

        // 1. Fetch relevant orders based on perspective and date range
        $orderQuery = $this->buildOrderQuery($parsedStartDate, $parsedEndDate, $perspective);
        $orders = $orderQuery->with([
            'items.product',
            'delivery.recordedBy',
            'codCollection.reconciledBy',
            'commissions',
        ])->get();

        $orderIds = $orders->pluck('id')->all();

        // 2. Commercial Product Sales & Refunds
        $grossProductSales = 0.00;
        $discounts = 0.00; // Placeholder for Phase 3 coupon module
        $refundsAndReturns = 0.00;
        $fulfilledOrdersCount = 0;
        $returnedOrdersCount = 0;
        $refusedOrdersCount = 0;
        $cancelledOrdersCount = 0;
        $pipelineOrdersCount = 0;

        // 3. COGS: Strict Segregation of Audited Snapshots vs Estimated Legacy Baseline
        $auditedCogs = 0.00;
        $estimatedLegacyCogs = 0.00;
        $auditedItemsCount = 0;
        $estimatedItemsCount = 0;

        // 4. Logistics: Billed vs Collected vs Courier Invoiced
        $billedDeliveryCharge = 0.00;
        $collectedDeliveryRevenue = 0.00;
        $uncollectedDeliveryCharge = 0.00;
        $actualCourierExpense = 0.00;
        $invoicedDeliveriesCount = 0;
        $missingCourierFeesCount = 0;

        // 5. Commission Accounting (Order-by-Order Grace Window Evaluation)
        $maturedCommissionExpense = 0.00;
        $pendingGraceCommissions = 0.00;
        $reversedCommissionCredit = 0.00;
        $ordersInActiveReturnGraceWindow = 0;

        // 6. COD Discrepancies & Recovery
        $codExpectedTotal = 0.00;
        $codCollectedTotal = 0.00;
        $codInTransit = 0.00;
        $unresolvedCodDisputes = 0.00;
        $confirmedUnrecoverableCodLosses = 0.00;
        $recoveredCodAmount = 0.00;

        // Preload COD confirmed losses and recoveries from immutable AuditLog
        $confirmedLossLogs = empty($orderIds) ? collect() : AuditLog::where('action', 'cod_loss_confirmed')
            ->whereIn('auditable_id', $orderIds)
            ->get()
            ->keyBy('auditable_id');

        $recoveryLogs = empty($orderIds) ? collect() : AuditLog::where('action', 'cod_shortfall_recovered')
            ->whereIn('auditable_id', $orderIds)
            ->get();

        foreach ($recoveryLogs as $recLog) {
            $recoveredCodAmount += (float) ($recLog->new_values['recovered_amount'] ?? 0.00);
        }

        // Process each order independently
        foreach ($orders as $order) {
            $status = $order->status;
            $delivery = $order->delivery;
            $cod = $order->codCollection;

            $billedDeliveryCharge += (float) $order->delivery_charge;

            // Pipeline tracking
            if (in_array($status, ['placed', 'confirmed', 'packed', 'shipped'], true)) {
                $pipelineOrdersCount++;
                if ($status === 'shipped') {
                    $codInTransit += (float) $order->total_amount;
                }
                continue;
            }

            // Cancelled orders (zero cash collected, zero revenue, zero refund deduction)
            if ($status === 'cancelled') {
                $cancelledOrdersCount++;
                $uncollectedDeliveryCharge += (float) $order->delivery_charge;
                continue;
            }

            // Refused orders (rejected at doorstep, zero revenue, courier cost incurred)
            if ($status === 'refused') {
                $refusedOrdersCount++;
                $uncollectedDeliveryCharge += (float) $order->delivery_charge;

                // Courier attempted delivery: courier expense still payable
                if ($delivery) {
                    if ($delivery->actual_courier_fee !== null) {
                        $actualCourierExpense += (float) $delivery->actual_courier_fee;
                        $invoicedDeliveriesCount++;
                    } else {
                        $missingCourierFeesCount++;
                    }
                }
                continue;
            }

            // Customer Returned orders (post-delivery return within grace period)
            if ($status === 'returned') {
                $returnedOrdersCount++;
                $grossProductSales += (float) $order->subtotal; // Original sales volume
                $refundsAndReturns += (float) $order->subtotal; // Fully offset by return/refund deduction
                $uncollectedDeliveryCharge += (float) $order->delivery_charge;

                if ($delivery) {
                    if ($delivery->actual_courier_fee !== null) {
                        $actualCourierExpense += (float) $delivery->actual_courier_fee;
                        $invoicedDeliveriesCount++;
                    } else {
                        $missingCourierFeesCount++;
                    }
                }

                // Process commission reversals associated with returned orders
                foreach ($order->commissions as $comm) {
                    $amt = (float) $comm->calculated_amount;
                    if ($comm->commission_type === CommissionLedgerEntry::TYPE_REVERSAL) {
                        $reversedCommissionCredit += abs($amt);
                    }
                }

                continue;
            }

            // Fulfilled Orders: 'delivered', 'cash_collected', 'completed'
            if (in_array($status, ['delivered', 'cash_collected', 'completed'], true)) {
                $fulfilledOrdersCount++;
                $grossProductSales += (float) $order->subtotal;

                // Calculate COGS order item by order item
                foreach ($order->items as $item) {
                    $qty = (int) $item->quantity;
                    if ($item->cost_price !== null) {
                        $auditedCogs += round(((float) $item->cost_price) * $qty, 2);
                        $auditedItemsCount++;
                    } else {
                        $fallbackProductCost = $item->product?->cost_price !== null
                            ? (float) $item->product->cost_price
                            : 0.00;
                        $estimatedLegacyCogs += round($fallbackProductCost * $qty, 2);
                        $estimatedItemsCount++;
                    }
                }

                // Delivery Revenue & Logistics Expense
                if ($delivery) {
                    if ($delivery->actual_courier_fee !== null) {
                        $actualCourierExpense += (float) $delivery->actual_courier_fee;
                        $invoicedDeliveriesCount++;
                    } else {
                        $missingCourierFeesCount++;
                    }
                }

                // COD Reconciliation Analysis
                if ($cod) {
                    $codExpectedTotal += (float) $cod->expected_amount;
                    $codCollectedTotal += (float) $cod->collected_amount;

                    if ($cod->reconciliation_status === CodCollection::STATUS_MATCHED) {
                        $collectedDeliveryRevenue += (float) $order->delivery_charge;
                    } elseif ($cod->reconciliation_status === CodCollection::STATUS_MISMATCHED_UNDERPAID) {
                        $shortfall = max(0.00, ((float) $cod->expected_amount) - ((float) $cod->collected_amount));

                        // Check if this shortfall has been formally investigated and certified as unrecoverable
                        if ($confirmedLossLogs->has($order->id)) {
                            $confirmedUnrecoverableCodLosses += $shortfall;
                        } else {
                            $unresolvedCodDisputes += $shortfall;
                        }

                        // Pro-rate or record partial collected delivery revenue if cash covered delivery
                        if ((float) $cod->collected_amount >= (float) $order->delivery_charge) {
                            $collectedDeliveryRevenue += (float) $order->delivery_charge;
                        }
                    } elseif ($cod->reconciliation_status === CodCollection::STATUS_PENDING) {
                        $codInTransit += (float) $order->total_amount;
                    }
                } else {
                    $codInTransit += (float) $order->total_amount;
                }

                // Order-by-order Commission Grace Period Evaluation
                $deliveryDate = $delivery?->delivered_at ?? $order->placed_at;
                $maturityDate = $deliveryDate ? Carbon::parse($deliveryDate)->addDays($returnWindowDays) : null;
                $isMatured = $maturityDate ? $maturityDate->lessThanOrEqualTo($now) : false;

                if (!$isMatured) {
                    $ordersInActiveReturnGraceWindow++;
                }

                // Commissions evaluation from immutable ledger
                foreach ($order->commissions as $comm) {
                    $amt = (float) $comm->calculated_amount;

                    if ($comm->commission_type === CommissionLedgerEntry::TYPE_REVERSAL) {
                        $reversedCommissionCredit += abs($amt);
                    } elseif ($comm->status === CommissionLedgerEntry::STATUS_APPROVED && $isMatured) {
                        $maturedCommissionExpense += $amt;
                    } elseif ($comm->status === CommissionLedgerEntry::STATUS_PENDING || !$isMatured) {
                        $pendingGraceCommissions += $amt;
                    }
                }
            }
        }

        // 7. Core Derived Metrics
        $netRecognizedProductRevenue = round($grossProductSales - $discounts - $refundsAndReturns, 2);
        $totalCogs = round($auditedCogs + $estimatedLegacyCogs, 2);
        $grossProductProfit = round($netRecognizedProductRevenue - $totalCogs, 2);
        $grossProductMarginPercent = $netRecognizedProductRevenue > 0
            ? round(($grossProductProfit / $netRecognizedProductRevenue) * 100, 2)
            : 0.00;

        $logisticsContribution = round($collectedDeliveryRevenue - $actualCourierExpense, 2);
        $netCommissionExpense = round(max(0.00, $maturedCommissionExpense - $reversedCommissionCredit), 2);

        // Final Phase 2 Operational Metric (OPEX strictly excluded)
        $grossOperatingContribution = round(
            $grossProductProfit + $logisticsContribution - $netCommissionExpense - $confirmedUnrecoverableCodLosses,
            2
        );
        $operatingContributionMarginPercent = $netRecognizedProductRevenue > 0
            ? round(($grossOperatingContribution / $netRecognizedProductRevenue) * 100, 2)
            : 0.00;

        // 8. Dual-Ledger Non-Double-Counting Audit Summary
        $commissionGeneratedTotal = empty($orderIds) ? 0.00 : (float) CommissionLedgerEntry::whereIn('order_id', $orderIds)
            ->where('commission_type', '!=', CommissionLedgerEntry::TYPE_REVERSAL)
            ->sum('calculated_amount');

        $walletCreditsTotal = empty($orderIds) ? 0.00 : (float) WalletTransaction::where('source_type', CommissionLedgerEntry::class)
            ->where('transaction_type', WalletTransaction::TYPE_COMMISSION_CREDIT)
            ->whereIn('metadata->order_id', $orderIds)
            ->sum('amount');

        $payoutsDisbursedTotal = (float) PayoutRequest::where('status', 'paid')
            ->when($parsedStartDate, fn ($q) => $q->where('paid_at', '>=', $parsedStartDate))
            ->when($parsedEndDate, fn ($q) => $q->where('paid_at', '<=', $parsedEndDate))
            ->sum('amount');

        // 9. Data Completeness & Certification Status
        $totalItemsCount = $auditedItemsCount + $estimatedItemsCount;
        $costSnapshotCoverage = $totalItemsCount > 0
            ? round(($auditedItemsCount / $totalItemsCount) * 100, 1)
            : 100.0;

        $totalDeliveriesCount = $invoicedDeliveriesCount + $missingCourierFeesCount;
        $courierFeeCoverage = $totalDeliveriesCount > 0
            ? round(($invoicedDeliveriesCount / $totalDeliveriesCount) * 100, 1)
            : 100.0;

        $isOpenPeriod = $parsedEndDate ? $parsedEndDate->diffInDays($now, false) < $returnWindowDays : true;

        if ($missingCourierFeesCount > 0 || $unresolvedCodDisputes > 0.00) {
            $certificationStatus = self::STATUS_PROVISIONAL;
            $certificationReason = 'Active unresolved inputs present (missing courier fee invoices or unresolved COD remittances).';
        } elseif ($estimatedItemsCount > 0) {
            $certificationStatus = self::STATUS_QUALIFIED;
            $certificationReason = 'Operational data is complete, but contains historical orders evaluated using estimated baseline COGS.';
        } else {
            $certificationStatus = self::STATUS_CERTIFIED;
            $certificationReason = '100% audited cost snapshots, verified courier fees, and matched COD remittances.';
        }

        return [
            'period' => [
                'start_date' => $parsedStartDate?->toDateString(),
                'end_date' => $parsedEndDate?->toDateString(),
                'perspective' => $perspective,
                'is_open_period' => $isOpenPeriod,
                'return_grace_window_days' => $returnWindowDays,
                'orders_in_grace_window' => $ordersInActiveReturnGraceWindow,
                'generated_at' => $now->toIso8601String(),
            ],
            'certification' => [
                'status' => $certificationStatus,
                'reason' => $certificationReason,
                'cost_snapshot_coverage_percent' => $costSnapshotCoverage,
                'courier_fee_coverage_percent' => $courierFeeCoverage,
                'audited_items_count' => $auditedItemsCount,
                'estimated_legacy_items_count' => $estimatedItemsCount,
                'missing_courier_fees_count' => $missingCourierFeesCount,
                'unresolved_cod_disputes_count' => $unresolvedCodDisputes > 0 ? 1 : 0,
            ],
            'revenue' => [
                'gross_product_sales' => round($grossProductSales, 2),
                'discounts' => round($discounts, 2),
                'returns_and_refunds' => round($refundsAndReturns, 2),
                'net_recognized_product_revenue' => $netRecognizedProductRevenue,
                'fulfilled_orders_count' => $fulfilledOrdersCount,
                'returned_orders_count' => $returnedOrdersCount,
                'refused_orders_count' => $refusedOrdersCount,
                'cancelled_orders_count' => $cancelledOrdersCount,
                'pipeline_orders_count' => $pipelineOrdersCount,
            ],
            'cogs' => [
                'audited_cogs' => round($auditedCogs, 2),
                'estimated_legacy_cogs' => round($estimatedLegacyCogs, 2),
                'total_cogs' => $totalCogs,
                'gross_product_profit' => $grossProductProfit,
                'gross_product_margin_percent' => $grossProductMarginPercent,
            ],
            'logistics' => [
                'billed_delivery_charge' => round($billedDeliveryCharge, 2),
                'collected_delivery_revenue' => round($collectedDeliveryRevenue, 2),
                'uncollected_delivery_charge' => round($uncollectedDeliveryCharge, 2),
                'actual_courier_expense' => round($actualCourierExpense, 2),
                'logistics_contribution' => $logisticsContribution,
                'invoiced_deliveries_count' => $invoicedDeliveriesCount,
                'missing_courier_fees_count' => $missingCourierFeesCount,
            ],
            'commissions' => [
                'matured_commission_expense' => round($maturedCommissionExpense, 2),
                'reversed_commission_credit' => round($reversedCommissionCredit, 2),
                'net_commission_expense' => $netCommissionExpense,
                'pending_grace_window_accrual' => round($pendingGraceCommissions, 2),
                'commission_generated_total' => round($commissionGeneratedTotal, 2),
                'wallet_credited_amount' => round($walletCreditsTotal, 2),
                'payouts_disbursed_cash' => round($payoutsDisbursedTotal, 2),
            ],
            'cod_reconciliation' => [
                'expected_total' => round($codExpectedTotal, 2),
                'collected_total' => round($codCollectedTotal, 2),
                'in_transit_amount' => round($codInTransit, 2),
                'unresolved_disputes_amount' => round($unresolvedCodDisputes, 2),
                'recovered_amount' => round($recoveredCodAmount, 2),
                'confirmed_unrecoverable_loss' => round($confirmedUnrecoverableCodLosses, 2),
            ],
            'contribution' => [
                'metric_name' => 'Gross Operating Contribution (Before OPEX)',
                'amount' => $grossOperatingContribution,
                'margin_percent' => $operatingContributionMarginPercent,
                'opex_excluded_disclaimer' => 'Corporate OPEX (rent, staff salaries, digital advertising, packaging consumables) is excluded and deferred to Phase 3.',
            ],
        ];
    }

    /**
     * Build base query filtering by perspective and date boundaries.
     */
    protected function buildOrderQuery(
        ?CarbonInterface $startDate,
        ?CarbonInterface $endDate,
        string $perspective
    ): Builder {
        $query = Order::query();

        if ($perspective === 'cash_date') {
            $query->whereHas('codCollection', function ($q) use ($startDate, $endDate) {
                if ($startDate) {
                    $q->where('collected_at', '>=', $startDate);
                }
                if ($endDate) {
                    $q->where('collected_at', '<=', $endDate);
                }
            });
        } elseif ($perspective === 'placed_date') {
            if ($startDate) {
                $query->where('placed_at', '>=', $startDate);
            }
            if ($endDate) {
                $query->where('placed_at', '<=', $endDate);
            }
        } else {
            // Default: 'fulfilled_date' (matches delivery or completion)
            $query->where(function ($q) use ($startDate, $endDate) {
                $q->whereHas('delivery', function ($dq) use ($startDate, $endDate) {
                    if ($startDate) {
                        $dq->where('delivered_at', '>=', $startDate);
                    }
                    if ($endDate) {
                        $dq->where('delivered_at', '<=', $endDate);
                    }
                })->orWhere(function ($oq) use ($startDate, $endDate) {
                    // Fallback to placed_at if delivered_at not yet recorded on fulfilled orders
                    if ($startDate) {
                        $oq->where('placed_at', '>=', $startDate);
                    }
                    if ($endDate) {
                        $oq->where('placed_at', '<=', $endDate);
                    }
                });
            });
        }

        return $query;
    }
}
