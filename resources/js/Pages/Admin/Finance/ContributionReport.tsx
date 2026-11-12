import React, { useMemo, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import * as Icons from '@/Components/Icons';

export interface ReportPeriod {
    start_date: string | null;
    end_date: string | null;
    perspective: 'fulfilled_date' | 'placed_date' | 'cash_date';
    is_open_period: boolean;
    return_grace_window_days: number;
    orders_in_grace_window: number;
    generated_at: string;
}

export interface ReportCertification {
    status: 'certified' | 'qualified' | 'provisional';
    reason: string;
    cost_snapshot_coverage_percent: number;
    courier_fee_coverage_percent: number;
    audited_items_count: number;
    estimated_legacy_items_count: number;
    missing_courier_fees_count: number;
    unresolved_cod_disputes_count: number;
}

export interface ReportRevenue {
    gross_product_sales: number;
    discounts: number;
    returns_and_refunds: number;
    net_recognized_product_revenue: number;
    fulfilled_orders_count: number;
    returned_orders_count: number;
    refused_orders_count: number;
    cancelled_orders_count: number;
    pipeline_orders_count: number;
}

export interface ReportCogs {
    audited_cogs: number;
    estimated_legacy_cogs: number;
    total_cogs: number;
    gross_product_profit: number;
    gross_product_margin_percent: number;
}

export interface ReportLogistics {
    billed_delivery_charge: number;
    collected_delivery_revenue: number;
    uncollected_delivery_charge: number;
    actual_courier_expense: number;
    logistics_contribution: number;
    invoiced_deliveries_count: number;
    missing_courier_fees_count: number;
}

export interface ReportCommissions {
    matured_commission_expense: number;
    reversed_commission_credit: number;
    net_commission_expense: number;
    pending_grace_window_accrual: number;
    commission_generated_total: number;
    wallet_credited_amount: number;
    payouts_disbursed_cash: number;
}

export interface ReportCodReconciliation {
    expected_total: number;
    collected_total: number;
    in_transit_amount: number;
    unresolved_disputes_amount: number;
    recovered_amount: number;
    confirmed_unrecoverable_loss: number;
}

export interface ReportContribution {
    metric_name: string;
    amount: number;
    margin_percent: number;
    opex_excluded_disclaimer: string;
}

export interface FinancialContributionReport {
    period: ReportPeriod;
    certification: ReportCertification;
    revenue: ReportRevenue;
    cogs: ReportCogs;
    logistics: ReportLogistics;
    commissions: ReportCommissions;
    cod_reconciliation: ReportCodReconciliation;
    contribution: ReportContribution;
}

export interface ContributionReportProps {
    report: FinancialContributionReport;
    filters: {
        date_from: string | null;
        date_to: string | null;
        perspective: string;
    };
}

export default function ContributionReport({ report, filters }: ContributionReportProps) {
    const [dateFrom, setDateFrom] = useState<string>(filters?.date_from || '');
    const [dateTo, setDateTo] = useState<string>(filters?.date_to || '');
    const [perspective, setPerspective] = useState<'fulfilled_date' | 'placed_date' | 'cash_date'>(
        (filters?.perspective as 'fulfilled_date' | 'placed_date' | 'cash_date') || 'fulfilled_date'
    );
    const [isNavigating, setIsNavigating] = useState<boolean>(false);
    const [validationError, setValidationError] = useState<string | null>(null);

    // Currency Formatter
    const fmtLKR = (val: number | null | undefined): string => {
        const num = Number(val ?? 0);
        return `LKR ${num.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
    };

    // Percentage Formatter
    const fmtPercent = (val: number | null | undefined): string => {
        const num = Number(val ?? 0);
        return `${num.toFixed(1)}%`;
    };

    // Apply Filter Function
    const applyFilters = (customFrom?: string | null, customTo?: string | null, customPersp?: string) => {
        const from = customFrom !== undefined ? customFrom : dateFrom;
        const to = customTo !== undefined ? customTo : dateTo;
        const persp = customPersp !== undefined ? customPersp : perspective;

        setValidationError(null);

        // Validation: Date Inversion
        if (from && to && from > to) {
            setValidationError('The start date cannot be after the end date.');
            return;
        }

        // Validation: Max 5 years span
        if (from && to) {
            const startDateObj = new Date(from);
            const endDateObj = new Date(to);
            const diffDays = Math.ceil(Math.abs(endDateObj.getTime() - startDateObj.getTime()) / (1000 * 60 * 60 * 24));
            if (diffDays > 1826) {
                setValidationError('The selected reporting period cannot exceed 5 years (1,826 days).');
                return;
            }
        }

        setIsNavigating(true);

        const params: Record<string, string> = {};
        if (from) params.date_from = from;
        if (to) params.date_to = to;
        if (persp && persp !== 'fulfilled_date') params.perspective = persp;

        router.get('/admin/finance/contribution', params, {
            preserveState: true,
            preserveScroll: true,
            onFinish: () => setIsNavigating(false),
        });
    };

    // Quick Date Presets
    const handlePreset = (preset: 'all' | 'this_month' | 'last_month' | '30d' | '90d') => {
        const now = new Date();
        const year = now.getFullYear();
        const month = now.getMonth();

        const toIso = (d: Date) => d.toISOString().split('T')[0];

        let from: string | null = null;
        let to: string | null = null;

        if (preset === 'all') {
            from = null;
            to = null;
        } else if (preset === 'this_month') {
            from = toIso(new Date(year, month, 1));
            to = toIso(now);
        } else if (preset === 'last_month') {
            from = toIso(new Date(year, month - 1, 1));
            to = toIso(new Date(year, month, 0));
        } else if (preset === '30d') {
            const past30 = new Date();
            past30.setDate(now.getDate() - 30);
            from = toIso(past30);
            to = toIso(now);
        } else if (preset === '90d') {
            const past90 = new Date();
            past90.setDate(now.getDate() - 90);
            from = toIso(past90);
            to = toIso(now);
        }

        setDateFrom(from || '');
        setDateTo(to || '');
        applyFilters(from, to, perspective);
    };

    // Perspective details helper
    const perspectiveDescription = useMemo(() => {
        switch (perspective) {
            case 'cash_date':
                return 'Cash Settlement Date: Orders evaluated strictly by COD collection and bank remittance date.';
            case 'placed_date':
                return 'Order Placed Date: Orders evaluated by customer checkout timestamp.';
            case 'fulfilled_date':
            default:
                return 'Fulfilled Date (Standard): Orders evaluated by courier delivery timestamp, aligning revenue with risk transfer and the 7-day return grace window.';
        }
    }, [perspective]);

    // Certification badge color config
    const certConfig = useMemo(() => {
        switch (report.certification.status) {
            case 'certified':
                return {
                    badge: 'bg-emerald-100 text-emerald-800 border-emerald-300 dark:bg-emerald-950/60 dark:text-emerald-300 dark:border-emerald-800',
                    indicator: 'bg-emerald-500',
                    label: 'Certified Complete',
                    icon: Icons.CheckCircle2,
                };
            case 'qualified':
                return {
                    badge: 'bg-amber-100 text-amber-800 border-amber-300 dark:bg-amber-950/60 dark:text-amber-300 dark:border-amber-800',
                    indicator: 'bg-amber-500',
                    label: 'Qualified Audit',
                    icon: Icons.AlertCircle,
                };
            case 'provisional':
            default:
                return {
                    badge: 'bg-rose-100 text-rose-800 border-rose-300 dark:bg-rose-950/60 dark:text-rose-300 dark:border-rose-800',
                    indicator: 'bg-rose-500',
                    label: 'Provisional (Unresolved Inputs)',
                    icon: Icons.Clock,
                };
        }
    }, [report.certification.status]);

    return (
        <AdminLayout>
            <Head title="Executive Financial Contribution (Before OPEX)" />

            <div className="space-y-8 pb-12">
                {/* =========================================================================
                    HEADER & TOP NAVIGATION
                   ========================================================================= */}
                <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-stone-200 dark:border-stone-800 pb-6">
                    <div>
                        <div className="flex items-center gap-3">
                            <span className="p-2 rounded-xl bg-rose-50 text-rose-700 dark:bg-rose-950/60 dark:text-rose-400 border border-rose-100 dark:border-rose-900/40">
                                <Icons.TrendingUp className="w-6 h-6" />
                            </span>
                            <div>
                                <h1 className="text-2xl font-serif font-bold text-stone-900 dark:text-stone-100 tracking-tight">
                                    Gross Operating Contribution Statement
                                </h1>
                                <p className="text-xs sm:text-sm text-stone-500 dark:text-stone-400 mt-0.5">
                                    Authoritative P&L waterfall reconciling commercial sales, audited COGS, logistics margin, partner commissions, and COD remittances.
                                </p>
                            </div>
                        </div>
                    </div>

                    {/* Certification Pill in Header */}
                    <div className="flex items-center gap-3">
                        <div className={`inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full text-xs font-semibold border ${certConfig.badge}`}>
                            <span className={`w-2 h-2 rounded-full ${certConfig.indicator} animate-pulse`} />
                            <span>{certConfig.label}</span>
                        </div>

                        {isNavigating && (
                            <div className="inline-flex items-center gap-2 px-3 py-1 rounded-lg text-xs font-medium text-rose-700 dark:text-rose-400 bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-900 animate-pulse">
                                <Icons.RefreshCw className="w-3.5 h-3.5 animate-spin" />
                                <span>Refreshing...</span>
                            </div>
                        )}
                    </div>
                </div>

                {/* =========================================================================
                    SECTION 1: REPORTING PERIOD & PERSPECTIVE SELECTOR
                   ========================================================================= */}
                <div className="bg-white dark:bg-[#0F172A] p-6 rounded-2xl border border-stone-200/80 dark:border-stone-800 shadow-xs space-y-5">
                    <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                        {/* Quick Presets */}
                        <div className="flex flex-wrap items-center gap-2">
                            <span className="text-xs font-semibold text-stone-400 dark:text-stone-500 uppercase tracking-wider mr-1">
                                Quick Period:
                            </span>
                            {(['all', 'this_month', 'last_month', '30d', '90d'] as const).map((preset) => (
                                <button
                                    key={preset}
                                    type="button"
                                    onClick={() => handlePreset(preset)}
                                    className="px-3 py-1.5 rounded-xl text-xs font-medium text-stone-700 dark:text-stone-300 bg-stone-100 dark:bg-stone-800 hover:bg-stone-200 dark:hover:bg-stone-700 transition-colors cursor-pointer"
                                >
                                    {preset === 'all' && 'All Time'}
                                    {preset === 'this_month' && 'This Month'}
                                    {preset === 'last_month' && 'Last Month'}
                                    {preset === '30d' && 'Last 30 Days'}
                                    {preset === '90d' && 'Last 90 Days'}
                                </button>
                            ))}
                        </div>

                        {/* Perspective Toggle */}
                        <div className="flex items-center gap-2">
                            <label htmlFor="perspective-select" className="text-xs font-semibold text-stone-400 dark:text-stone-500 uppercase tracking-wider">
                                Accounting Perspective:
                            </label>
                            <select
                                id="perspective-select"
                                value={perspective}
                                onChange={(e) => {
                                    const nextPersp = e.target.value as 'fulfilled_date' | 'placed_date' | 'cash_date';
                                    setPerspective(nextPersp);
                                    applyFilters(undefined, undefined, nextPersp);
                                }}
                                className="text-xs font-medium bg-stone-50 dark:bg-stone-800 border border-stone-300 dark:border-stone-700 rounded-xl px-3 py-1.5 text-stone-800 dark:text-stone-200 focus:ring-rose-500 focus:border-rose-500"
                            >
                                <option value="fulfilled_date">Fulfilled Date (Recommended)</option>
                                <option value="placed_date">Order Placed Date</option>
                                <option value="cash_date">Cash Settlement Date</option>
                            </select>
                        </div>
                    </div>

                    {/* Custom Date Pickers */}
                    <div className="grid grid-cols-1 sm:grid-cols-12 gap-3 pt-3 border-t border-stone-100 dark:border-stone-800/80 items-end">
                        <div className="sm:col-span-4">
                            <label htmlFor="date-from" className="block text-xs font-medium text-stone-500 dark:text-stone-400 mb-1">
                                Start Date (From)
                            </label>
                            <input
                                id="date-from"
                                type="date"
                                value={dateFrom}
                                onChange={(e) => setDateFrom(e.target.value)}
                                className="w-full text-xs bg-stone-50 dark:bg-stone-800 border border-stone-300 dark:border-stone-700 rounded-xl px-3 py-2 text-stone-800 dark:text-stone-200 focus:ring-rose-500 focus:border-rose-500"
                            />
                        </div>

                        <div className="sm:col-span-4">
                            <label htmlFor="date-to" className="block text-xs font-medium text-stone-500 dark:text-stone-400 mb-1">
                                End Date (To)
                            </label>
                            <input
                                id="date-to"
                                type="date"
                                value={dateTo}
                                onChange={(e) => setDateTo(e.target.value)}
                                className="w-full text-xs bg-stone-50 dark:bg-stone-800 border border-stone-300 dark:border-stone-700 rounded-xl px-3 py-2 text-stone-800 dark:text-stone-200 focus:ring-rose-500 focus:border-rose-500"
                            />
                        </div>

                        <div className="sm:col-span-4 flex items-center gap-2">
                            <button
                                type="button"
                                onClick={() => applyFilters()}
                                disabled={isNavigating}
                                className="flex-1 inline-flex items-center justify-center px-4 py-2 bg-rose-700 hover:bg-rose-800 disabled:bg-rose-400 text-white rounded-xl text-xs font-semibold shadow-xs transition-colors cursor-pointer"
                            >
                                <Icons.Filter className="w-3.5 h-3.5 mr-1.5" />
                                {isNavigating ? 'Loading...' : 'Apply Date Range'}
                            </button>
                            {(dateFrom || dateTo) && (
                                <button
                                    type="button"
                                    onClick={() => handlePreset('all')}
                                    className="px-3 py-2 border border-stone-300 dark:border-stone-700 hover:bg-stone-100 dark:hover:bg-stone-800 text-stone-600 dark:text-stone-300 rounded-xl text-xs font-medium transition-colors cursor-pointer"
                                    title="Reset to All Time"
                                >
                                    Reset
                                </button>
                            )}
                        </div>
                    </div>

                    {/* Perspective Note & Validation Feedback */}
                    <div className="flex flex-col sm:flex-row sm:items-center justify-between text-xs gap-2 pt-1">
                        <span className="text-stone-500 dark:text-stone-400 italic">
                            ℹ️ {perspectiveDescription}
                        </span>
                        {validationError && (
                            <span className="text-rose-600 dark:text-rose-400 font-semibold">
                                ⚠️ {validationError}
                            </span>
                        )}
                    </div>
                </div>

                {/* =========================================================================
                    SECTION 2: CERTIFICATION STATUS & AUDIT INTEGRITY BANNER
                   ========================================================================= */}
                <div className={`p-5 rounded-2xl border ${certConfig.badge} shadow-xs space-y-4`}>
                    <div className="flex flex-col md:flex-row md:items-start justify-between gap-4">
                        <div className="flex items-start gap-3">
                            <certConfig.icon className="w-5 h-5 shrink-0 mt-0.5" />
                            <div>
                                <h2 className="text-sm font-bold tracking-tight">
                                    Statement Certification: {certConfig.label}
                                </h2>
                                <p className="text-xs mt-1 opacity-90 max-w-3xl leading-relaxed">
                                    {report.certification.reason}
                                </p>
                            </div>
                        </div>

                        {/* Open vs Finalized Window */}
                        <div className="shrink-0 text-right">
                            {report.period.is_open_period ? (
                                <span className="inline-flex items-center gap-1.5 text-xs font-semibold bg-white/80 dark:bg-stone-900/80 px-3 py-1 rounded-lg border border-amber-300/60 dark:border-amber-800">
                                    <Icons.Clock className="w-3.5 h-3.5 text-amber-600" />
                                    Active Return Window ({report.period.orders_in_grace_window} orders pending 7-day maturity)
                                </span>
                            ) : (
                                <span className="inline-flex items-center gap-1.5 text-xs font-semibold bg-white/80 dark:bg-stone-900/80 px-3 py-1 rounded-lg border border-emerald-300/60 dark:border-emerald-800">
                                    <Icons.CheckCircle2 className="w-3.5 h-3.5 text-emerald-600" />
                                    Finalized Reporting Period
                                </span>
                            )}
                        </div>
                    </div>

                    {/* Progress Metrics: Snapshot Coverage & Courier Invoicing */}
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-3 border-t border-current/20 text-xs">
                        {/* Cost Price Snapshot Coverage */}
                        <div>
                            <div className="flex justify-between font-semibold mb-1">
                                <span>COGS Snapshot Coverage</span>
                                <span>{fmtPercent(report.certification.cost_snapshot_coverage_percent)}</span>
                            </div>
                            <div className="w-full h-2 rounded-full bg-current/10 overflow-hidden">
                                <div
                                    className="h-full bg-emerald-600 rounded-full transition-all duration-500"
                                    style={{ width: `${Math.min(100, report.certification.cost_snapshot_coverage_percent)}%` }}
                                />
                            </div>
                            <p className="mt-1 opacity-75 text-[11px]">
                                {report.certification.audited_items_count} items with checkout cost snapshot &middot; {report.certification.estimated_legacy_items_count} historical items on estimated baseline
                            </p>
                        </div>

                        {/* Courier Fee Invoicing Coverage */}
                        <div>
                            <div className="flex justify-between font-semibold mb-1">
                                <span>Courier Invoice Coverage</span>
                                <span>{fmtPercent(report.certification.courier_fee_coverage_percent)}</span>
                            </div>
                            <div className="w-full h-2 rounded-full bg-current/10 overflow-hidden">
                                <div
                                    className={`h-full rounded-full transition-all duration-500 ${report.certification.missing_courier_fees_count > 0 ? 'bg-amber-600' : 'bg-emerald-600'}`}
                                    style={{ width: `${Math.min(100, report.certification.courier_fee_coverage_percent)}%` }}
                                />
                            </div>
                            <p className="mt-1 opacity-75 text-[11px]">
                                {report.logistics.invoiced_deliveries_count} courier invoices verified &middot;{' '}
                                {report.certification.missing_courier_fees_count > 0 ? (
                                    <span className="font-bold underline text-rose-700 dark:text-rose-300">
                                        {report.certification.missing_courier_fees_count} deliveries missing invoice (NOT treated as zero expense)
                                    </span>
                                ) : (
                                    '100% invoices reconciled'
                                )}
                            </p>
                        </div>
                    </div>
                </div>

                {/* =========================================================================
                    SECTION 3: HERO METRIC — GROSS OPERATING CONTRIBUTION (BEFORE OPEX)
                   ========================================================================= */}
                <div className="bg-gradient-to-br from-stone-900 via-stone-900 to-rose-950 text-white rounded-3xl p-6 sm:p-8 shadow-lg border border-stone-800 space-y-6">
                    <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
                        <div>
                            <span className="text-xs font-bold text-rose-300 uppercase tracking-widest bg-rose-950/80 px-3 py-1 rounded-full border border-rose-800/80">
                                Primary Operational Result
                            </span>
                            <h2 className="text-xl sm:text-2xl font-serif font-bold text-white mt-3">
                                {report.contribution.metric_name}
                            </h2>
                            <p className="text-xs text-stone-300 mt-1 max-w-2xl leading-relaxed">
                                Operating contribution after direct product cost, net logistics margin, matured agent commissions, and certified COD write-offs.
                            </p>
                        </div>

                        {/* Amount & Margin Hero Block */}
                        <div className="text-left md:text-right">
                            <span className="text-[11px] font-semibold text-stone-400 uppercase tracking-wider block">
                                Net Operating Contribution
                            </span>
                            <p className="text-3xl sm:text-4xl lg:text-5xl font-extrabold font-mono tracking-tight text-white mt-1">
                                {fmtLKR(report.contribution.amount)}
                            </p>
                            <span className="inline-block mt-2 px-3 py-1 bg-rose-500/20 text-rose-300 border border-rose-500/30 rounded-full text-xs font-semibold">
                                {fmtPercent(report.contribution.margin_percent)} Margin on Net Revenue
                            </span>
                        </div>
                    </div>

                    {/* OPEX Mandatory Disclaimer Notice */}
                    <div className="bg-rose-950/40 border border-rose-800/60 rounded-2xl p-4 text-xs text-rose-200/90 flex items-start gap-3">
                        <Icons.AlertCircle className="w-5 h-5 text-rose-400 shrink-0 mt-0.5" />
                        <div className="space-y-1">
                            <p className="font-bold text-rose-200">
                                MANDATORY ACCOUNTING CLASSIFICATION (BEFORE OPEX):
                            </p>
                            <p className="leading-relaxed">
                                {report.contribution.opex_excluded_disclaimer} Never cite this operational contribution as Corporate Net Profit or Operating Net Profit. Corporate overheads are deferred to Phase 3.
                            </p>
                        </div>
                    </div>

                    {/* Quick Contribution Pillars */}
                    <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 pt-3 border-t border-stone-800/80">
                        <div className="bg-stone-800/50 p-3 rounded-xl border border-stone-700/50">
                            <span className="text-[11px] text-stone-400 block">Gross Product Profit</span>
                            <span className="text-sm sm:text-base font-bold font-mono text-emerald-400">
                                +{fmtLKR(report.cogs.gross_product_profit)}
                            </span>
                        </div>
                        <div className="bg-stone-800/50 p-3 rounded-xl border border-stone-700/50">
                            <span className="text-[11px] text-stone-400 block">Logistics Contribution</span>
                            <span className={`text-sm sm:text-base font-bold font-mono ${report.logistics.logistics_contribution >= 0 ? 'text-emerald-400' : 'text-rose-400'}`}>
                                {report.logistics.logistics_contribution >= 0 ? '+' : ''}{fmtLKR(report.logistics.logistics_contribution)}
                            </span>
                        </div>
                        <div className="bg-stone-800/50 p-3 rounded-xl border border-stone-700/50">
                            <span className="text-[11px] text-stone-400 block">Net Commission Expense</span>
                            <span className="text-sm sm:text-base font-bold font-mono text-amber-400">
                                -{fmtLKR(report.commissions.net_commission_expense)}
                            </span>
                        </div>
                        <div className="bg-stone-800/50 p-3 rounded-xl border border-stone-700/50">
                            <span className="text-[11px] text-stone-400 block">Confirmed COD Losses</span>
                            <span className="text-sm sm:text-base font-bold font-mono text-rose-400">
                                -{fmtLKR(report.cod_reconciliation.confirmed_unrecoverable_loss)}
                            </span>
                        </div>
                    </div>
                </div>

                {/* =========================================================================
                    SECTION 4: MATHEMATICAL WATERFALL RECONCILIATION
                   ========================================================================= */}
                <div className="bg-white dark:bg-[#0F172A] p-6 rounded-2xl border border-stone-200/80 dark:border-stone-800 shadow-xs space-y-4">
                    <div className="flex items-center justify-between pb-3 border-b border-stone-100 dark:border-stone-800">
                        <div>
                            <h2 className="text-base font-bold text-stone-900 dark:text-stone-100">
                                Mathematical P&L Waterfall Reconciliation
                            </h2>
                            <p className="text-xs text-stone-500 dark:text-stone-400 mt-0.5">
                                Step-by-step arithmetic from Gross Commercial Sales down to Gross Operating Contribution.
                            </p>
                        </div>
                        <span className="text-xs font-mono font-semibold bg-stone-100 dark:bg-stone-800 text-stone-600 dark:text-stone-300 px-3 py-1 rounded-lg">
                            Non-Double-Counting Certified
                        </span>
                    </div>

                    <div className="divide-y divide-stone-100 dark:divide-stone-800 text-xs">
                        {/* Step 1: Gross Sales */}
                        <div className="py-2.5 flex items-center justify-between">
                            <span className="font-medium text-stone-700 dark:text-stone-300">
                                1. Gross Commercial Product Sales
                            </span>
                            <span className="font-mono font-bold text-stone-900 dark:text-stone-100">
                                {fmtLKR(report.revenue.gross_product_sales)}
                            </span>
                        </div>

                        {/* Step 2: Returns / Refunds */}
                        <div className="py-2.5 flex items-center justify-between text-rose-600 dark:text-rose-400">
                            <span>
                                2. Less Customer Returns & Refunds ({report.revenue.returned_orders_count} orders)
                            </span>
                            <span className="font-mono font-semibold">
                                - {fmtLKR(report.revenue.returns_and_refunds)}
                            </span>
                        </div>

                        {/* Step 3: Net Recognized Revenue */}
                        <div className="py-2.5 flex items-center justify-between bg-stone-50/70 dark:bg-stone-900/50 px-2 rounded-lg font-bold text-stone-900 dark:text-stone-100">
                            <span>= Net Recognized Product Revenue</span>
                            <span className="font-mono text-sm">{fmtLKR(report.revenue.net_recognized_product_revenue)}</span>
                        </div>

                        {/* Step 4: Cost of Goods Sold */}
                        <div className="py-2.5 flex items-center justify-between text-rose-600 dark:text-rose-400">
                            <div>
                                <span>3. Less Cost of Goods Sold (Total COGS)</span>
                                <span className="block text-[11px] text-stone-500 dark:text-stone-400">
                                    Audited: {fmtLKR(report.cogs.audited_cogs)} | Estimated Legacy: {fmtLKR(report.cogs.estimated_legacy_cogs)}
                                </span>
                            </div>
                            <span className="font-mono font-semibold">
                                - {fmtLKR(report.cogs.total_cogs)}
                            </span>
                        </div>

                        {/* Step 5: Gross Product Profit */}
                        <div className="py-2.5 flex items-center justify-between bg-emerald-50/60 dark:bg-emerald-950/30 px-2 rounded-lg font-bold text-emerald-900 dark:text-emerald-300">
                            <span>= Gross Product Profit (Margin: {fmtPercent(report.cogs.gross_product_margin_percent)})</span>
                            <span className="font-mono text-sm">{fmtLKR(report.cogs.gross_product_profit)}</span>
                        </div>

                        {/* Step 6: Logistics Contribution */}
                        <div className="py-2.5 flex items-center justify-between">
                            <div>
                                <span className="font-medium text-stone-700 dark:text-stone-300">
                                    4. Net Logistics Contribution (Shipping)
                                </span>
                                <span className="block text-[11px] text-stone-500 dark:text-stone-400">
                                    Collected: {fmtLKR(report.logistics.collected_delivery_revenue)} &minus; Actual Courier Expense: {fmtLKR(report.logistics.actual_courier_expense)}
                                </span>
                            </div>
                            <span className={`font-mono font-bold ${report.logistics.logistics_contribution >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'}`}>
                                {report.logistics.logistics_contribution >= 0 ? '+' : ''}{fmtLKR(report.logistics.logistics_contribution)}
                            </span>
                        </div>

                        {/* Step 7: Net Commission Expense */}
                        <div className="py-2.5 flex items-center justify-between text-rose-600 dark:text-rose-400">
                            <div>
                                <span>5. Less Net Agent Commission Expense</span>
                                <span className="block text-[11px] text-stone-500 dark:text-stone-400">
                                    Matured: {fmtLKR(report.commissions.matured_commission_expense)} &minus; Reversals: {fmtLKR(report.commissions.reversed_commission_credit)}
                                </span>
                            </div>
                            <span className="font-mono font-semibold">
                                - {fmtLKR(report.commissions.net_commission_expense)}
                            </span>
                        </div>

                        {/* Step 8: Confirmed COD Losses */}
                        <div className="py-2.5 flex items-center justify-between text-rose-600 dark:text-rose-400">
                            <div>
                                <span>6. Less Confirmed Unrecoverable COD Losses</span>
                                <span className="block text-[11px] text-stone-500 dark:text-stone-400">
                                    Approved courier discrepancy write-offs (Unresolved disputes held in suspense)
                                </span>
                            </div>
                            <span className="font-mono font-semibold">
                                - {fmtLKR(report.cod_reconciliation.confirmed_unrecoverable_loss)}
                            </span>
                        </div>

                        {/* Step 9: Final Operating Contribution */}
                        <div className="py-3 flex items-center justify-between bg-stone-900 text-white px-3 rounded-xl font-bold">
                            <span className="text-sm">
                                = Gross Operating Contribution (Before OPEX)
                            </span>
                            <span className="font-mono text-base text-rose-300">
                                {fmtLKR(report.contribution.amount)}
                            </span>
                        </div>
                    </div>
                </div>

                {/* =========================================================================
                    SECTION 5: DETAILED DOMAIN CARDS (5 Core Domains)
                   ========================================================================= */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    {/* -------------------------------------------------------------
                        DOMAIN 1: COMMERCIAL PRODUCT SALES & DISCOUNTS
                       ------------------------------------------------------------- */}
                    <div className="bg-white dark:bg-[#0F172A] p-6 rounded-2xl border border-stone-200/80 dark:border-stone-800 shadow-xs flex flex-col justify-between">
                        <div>
                            <div className="flex items-center justify-between pb-3 border-b border-stone-100 dark:border-stone-800">
                                <h3 className="text-sm font-bold text-stone-900 dark:text-stone-100 flex items-center gap-2">
                                    <Icons.ShoppingCart className="w-4 h-4 text-rose-600" />
                                    Commercial Product Sales
                                </h3>
                                <span className="text-[11px] font-semibold text-stone-500 bg-stone-100 dark:bg-stone-800 px-2.5 py-0.5 rounded-full">
                                    {report.revenue.fulfilled_orders_count} Fulfilled
                                </span>
                            </div>

                            <div className="mt-4 space-y-3 text-xs">
                                <div className="flex justify-between items-center">
                                    <span className="text-stone-500 dark:text-stone-400">Gross Product Sales</span>
                                    <span className="font-mono font-semibold text-stone-800 dark:text-stone-200">
                                        {fmtLKR(report.revenue.gross_product_sales)}
                                    </span>
                                </div>
                                <div className="flex justify-between items-center text-stone-400">
                                    <span>Promotional Discounts</span>
                                    <span className="font-mono font-semibold">
                                        {fmtLKR(report.revenue.discounts)}
                                    </span>
                                </div>
                                <div className="flex justify-between items-center text-rose-600 dark:text-rose-400">
                                    <span>Returns & Customer Refunds</span>
                                    <span className="font-mono font-semibold">
                                        - {fmtLKR(report.revenue.returns_and_refunds)}
                                    </span>
                                </div>
                                <div className="pt-2 border-t border-stone-100 dark:border-stone-800 flex justify-between items-center font-bold text-stone-900 dark:text-stone-100">
                                    <span>Net Recognized Revenue</span>
                                    <span className="font-mono text-sm text-emerald-600 dark:text-emerald-400">
                                        {fmtLKR(report.revenue.net_recognized_product_revenue)}
                                    </span>
                                </div>
                            </div>
                        </div>

                        {/* Order breakdown pills */}
                        <div className="mt-5 pt-3 border-t border-stone-100 dark:border-stone-800/80 flex flex-wrap gap-2 text-[11px]">
                            <span className="px-2 py-0.5 rounded-md bg-stone-100 dark:bg-stone-800 text-stone-700 dark:text-stone-300">
                                Returned: <strong>{report.revenue.returned_orders_count}</strong>
                            </span>
                            <span className="px-2 py-0.5 rounded-md bg-stone-100 dark:bg-stone-800 text-stone-700 dark:text-stone-300">
                                Refused: <strong>{report.revenue.refused_orders_count}</strong>
                            </span>
                            <span className="px-2 py-0.5 rounded-md bg-stone-100 dark:bg-stone-800 text-stone-700 dark:text-stone-300">
                                Cancelled: <strong>{report.revenue.cancelled_orders_count}</strong>
                            </span>
                            <span className="px-2 py-0.5 rounded-md bg-stone-100 dark:bg-stone-800 text-stone-700 dark:text-stone-300">
                                In Pipeline: <strong>{report.revenue.pipeline_orders_count}</strong>
                            </span>
                        </div>
                    </div>

                    {/* -------------------------------------------------------------
                        DOMAIN 2: COGS & HISTORICAL COST SEGREGATION
                       ------------------------------------------------------------- */}
                    <div className="bg-white dark:bg-[#0F172A] p-6 rounded-2xl border border-stone-200/80 dark:border-stone-800 shadow-xs flex flex-col justify-between">
                        <div>
                            <div className="flex items-center justify-between pb-3 border-b border-stone-100 dark:border-stone-800">
                                <h3 className="text-sm font-bold text-stone-900 dark:text-stone-100 flex items-center gap-2">
                                    <Icons.Package className="w-4 h-4 text-amber-600" />
                                    Cost of Goods Sold (Strictly Segregated)
                                </h3>
                                <span className="text-[11px] font-semibold text-emerald-600 bg-emerald-50 dark:bg-emerald-950/60 dark:text-emerald-400 px-2.5 py-0.5 rounded-full">
                                    Margin: {fmtPercent(report.cogs.gross_product_margin_percent)}
                                </span>
                            </div>

                            <div className="mt-4 space-y-3 text-xs">
                                <div className="flex justify-between items-center">
                                    <div>
                                        <span className="text-stone-700 dark:text-stone-300 font-medium">Audited Snapshot COGS</span>
                                        <span className="block text-[11px] text-stone-400">Captured at checkout placement</span>
                                    </div>
                                    <span className="font-mono font-semibold text-stone-900 dark:text-stone-100">
                                        {fmtLKR(report.cogs.audited_cogs)}
                                    </span>
                                </div>

                                <div className="flex justify-between items-center">
                                    <div>
                                        <span className="text-amber-700 dark:text-amber-400 font-medium">Estimated Legacy Baseline COGS</span>
                                        <span className="block text-[11px] text-stone-400">Historical orders without cost snapshot</span>
                                    </div>
                                    <span className="font-mono font-semibold text-amber-600 dark:text-amber-400">
                                        {fmtLKR(report.cogs.estimated_legacy_cogs)}
                                    </span>
                                </div>

                                <div className="pt-2 border-t border-stone-100 dark:border-stone-800 flex justify-between items-center">
                                    <span className="font-medium text-stone-600 dark:text-stone-400">Total Product Cost (COGS)</span>
                                    <span className="font-mono font-semibold text-rose-600 dark:text-rose-400">
                                        {fmtLKR(report.cogs.total_cogs)}
                                    </span>
                                </div>

                                <div className="pt-2 border-t border-stone-100 dark:border-stone-800 flex justify-between items-center font-bold text-stone-900 dark:text-stone-100">
                                    <span>Gross Product Profit</span>
                                    <span className="font-mono text-sm text-emerald-600 dark:text-emerald-400">
                                        {fmtLKR(report.cogs.gross_product_profit)}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div className="mt-5 p-3 rounded-xl bg-amber-50/60 dark:bg-amber-950/30 border border-amber-200/60 dark:border-amber-800/60 text-[11px] text-amber-800 dark:text-amber-300">
                            Strict Rule: Audited checkout snapshots and estimated legacy historical costs are never blended without separate line-item accountability.
                        </div>
                    </div>

                    {/* -------------------------------------------------------------
                        DOMAIN 3: LOGISTICS CONTRIBUTION & COURIER FEES
                       ------------------------------------------------------------- */}
                    <div className="bg-white dark:bg-[#0F172A] p-6 rounded-2xl border border-stone-200/80 dark:border-stone-800 shadow-xs flex flex-col justify-between">
                        <div>
                            <div className="flex items-center justify-between pb-3 border-b border-stone-100 dark:border-stone-800">
                                <h3 className="text-sm font-bold text-stone-900 dark:text-stone-100 flex items-center gap-2">
                                    <Icons.Truck className="w-4 h-4 text-blue-600" />
                                    Logistics Margin & Courier Expenses
                                </h3>
                                <span className={`text-[11px] font-semibold px-2.5 py-0.5 rounded-full ${report.logistics.logistics_contribution >= 0 ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-400' : 'bg-rose-50 text-rose-700 dark:bg-rose-950/60 dark:text-rose-400'}`}>
                                    Contribution: {fmtLKR(report.logistics.logistics_contribution)}
                                </span>
                            </div>

                            <div className="mt-4 space-y-3 text-xs">
                                <div className="flex justify-between items-center">
                                    <div>
                                        <span className="text-stone-700 dark:text-stone-300 font-medium">Collected Delivery Revenue</span>
                                        <span className="block text-[11px] text-stone-400">Actual cash collected from customers</span>
                                    </div>
                                    <span className="font-mono font-semibold text-emerald-600 dark:text-emerald-400">
                                        {fmtLKR(report.logistics.collected_delivery_revenue)}
                                    </span>
                                </div>

                                <div className="flex justify-between items-center">
                                    <div>
                                        <span className="text-stone-700 dark:text-stone-300 font-medium">Actual Invoiced Courier Fee</span>
                                        <span className="block text-[11px] text-stone-400">Verified partner invoice expense</span>
                                    </div>
                                    <span className="font-mono font-semibold text-rose-600 dark:text-rose-400">
                                        - {fmtLKR(report.logistics.actual_courier_expense)}
                                    </span>
                                </div>

                                <div className="flex justify-between items-center text-stone-500">
                                    <span>Billed Customer Charges vs Uncollected</span>
                                    <span className="font-mono">
                                        {fmtLKR(report.logistics.billed_delivery_charge)} / {fmtLKR(report.logistics.uncollected_delivery_charge)}
                                    </span>
                                </div>

                                <div className="pt-2 border-t border-stone-100 dark:border-stone-800 flex justify-between items-center font-bold text-stone-900 dark:text-stone-100">
                                    <span>Net Logistics Contribution</span>
                                    <span className={`font-mono text-sm ${report.logistics.logistics_contribution >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'}`}>
                                        {report.logistics.logistics_contribution >= 0 ? '+' : ''}{fmtLKR(report.logistics.logistics_contribution)}
                                    </span>
                                </div>
                            </div>
                        </div>

                        {/* Missing Courier Fee Alert */}
                        <div className="mt-5">
                            {report.certification.missing_courier_fees_count > 0 ? (
                                <div className="p-3 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-900 text-xs text-rose-800 dark:text-rose-300 flex items-center gap-2">
                                    <Icons.AlertCircle className="w-4 h-4 shrink-0 text-rose-600" />
                                    <span>
                                        <strong>{report.certification.missing_courier_fees_count} deliveries</strong> missing courier invoice. They are held as provisional and NOT treated as free shipping!
                                    </span>
                                </div>
                            ) : (
                                <div className="p-3 rounded-xl bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-900 text-xs text-emerald-800 dark:text-emerald-300 flex items-center gap-2">
                                    <Icons.CheckCircle2 className="w-4 h-4 shrink-0 text-emerald-600" />
                                    <span>All delivered orders have verified courier fee invoices recorded.</span>
                                </div>
                            )}
                        </div>
                    </div>

                    {/* -------------------------------------------------------------
                        DOMAIN 4: COMMISSIONS & DUAL-LEDGER GOVERNANCE
                       ------------------------------------------------------------- */}
                    <div className="bg-white dark:bg-[#0F172A] p-6 rounded-2xl border border-stone-200/80 dark:border-stone-800 shadow-xs flex flex-col justify-between">
                        <div>
                            <div className="flex items-center justify-between pb-3 border-b border-stone-100 dark:border-stone-800">
                                <h3 className="text-sm font-bold text-stone-900 dark:text-stone-100 flex items-center gap-2">
                                    <Icons.Users className="w-4 h-4 text-purple-600" />
                                    Agent Commissions & Dual-Ledger
                                </h3>
                                <span className="text-[11px] font-semibold text-stone-500 bg-stone-100 dark:bg-stone-800 px-2.5 py-0.5 rounded-full">
                                    Net: {fmtLKR(report.commissions.net_commission_expense)}
                                </span>
                            </div>

                            <div className="mt-4 space-y-3 text-xs">
                                <div className="flex justify-between items-center">
                                    <div>
                                        <span className="text-stone-700 dark:text-stone-300 font-medium">Matured Commission Expense</span>
                                        <span className="block text-[11px] text-stone-400">Elapsed &ge; 7-day return grace window</span>
                                    </div>
                                    <span className="font-mono font-semibold text-rose-600 dark:text-rose-400">
                                        {fmtLKR(report.commissions.matured_commission_expense)}
                                    </span>
                                </div>

                                <div className="flex justify-between items-center text-emerald-600 dark:text-emerald-400">
                                    <div>
                                        <span className="font-medium">Reversed Commission Credit</span>
                                        <span className="block text-[11px] opacity-75">Customer returns / cancellations clawback</span>
                                    </div>
                                    <span className="font-mono font-semibold">
                                        - {fmtLKR(report.commissions.reversed_commission_credit)}
                                    </span>
                                </div>

                                <div className="flex justify-between items-center text-amber-600 dark:text-amber-400">
                                    <div>
                                        <span>Active Return Window Accrual</span>
                                        <span className="block text-[11px] opacity-75">Commissions pending 7-day return maturity</span>
                                    </div>
                                    <span className="font-mono font-semibold">
                                        {fmtLKR(report.commissions.pending_grace_window_accrual)}
                                    </span>
                                </div>

                                <div className="pt-2 border-t border-stone-100 dark:border-stone-800 flex justify-between items-center font-bold text-stone-900 dark:text-stone-100">
                                    <span>Net Commission Expense Recognized</span>
                                    <span className="font-mono text-sm text-rose-600 dark:text-rose-400">
                                        {fmtLKR(report.commissions.net_commission_expense)}
                                    </span>
                                </div>
                            </div>
                        </div>

                        {/* Dual-Ledger Non-Double-Counting Explainer */}
                        <div className="mt-5 p-3 rounded-xl bg-purple-50/60 dark:bg-purple-950/30 border border-purple-200/60 dark:border-purple-800/60 text-[11px] text-purple-900 dark:text-purple-300 space-y-1">
                            <p className="font-bold flex items-center gap-1.5">
                                <Icons.ShieldCheck className="w-3.5 h-3.5" />
                                Dual-Ledger Non-Double-Counting Rule:
                            </p>
                            <p className="leading-relaxed">
                                Wallet balance credits ({fmtLKR(report.commissions.wallet_credited_amount)}) and cash payout disbursements ({fmtLKR(report.commissions.payouts_disbursed_cash)}) represent liquidity movements, not new commission expenses.
                            </p>
                        </div>
                    </div>

                    {/* -------------------------------------------------------------
                        DOMAIN 5: COD RECONCILIATION & LOSS ACCOUNTING (Full Span)
                       ------------------------------------------------------------- */}
                    <div className="md:col-span-2 bg-white dark:bg-[#0F172A] p-6 rounded-2xl border border-stone-200/80 dark:border-stone-800 shadow-xs space-y-4">
                        <div className="flex items-center justify-between pb-3 border-b border-stone-100 dark:border-stone-800">
                            <div>
                                <h3 className="text-sm font-bold text-stone-900 dark:text-stone-100 flex items-center gap-2">
                                    <Icons.DollarSign className="w-4 h-4 text-emerald-600" />
                                    Cash On Delivery (COD) Remittance & Discrepancy Accounting
                                </h3>
                                <p className="text-xs text-stone-500 dark:text-stone-400 mt-0.5">
                                    Strict accounting separation between unresolved partner disputes and formally confirmed losses.
                                </p>
                            </div>

                            <span className="text-xs font-semibold px-3 py-1 rounded-full bg-stone-100 dark:bg-stone-800 text-stone-700 dark:text-stone-300">
                                Expected: {fmtLKR(report.cod_reconciliation.expected_total)}
                            </span>
                        </div>

                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 text-xs">
                            {/* Card 1: Collected Total */}
                            <div className="bg-stone-50 dark:bg-stone-800/60 p-4 rounded-xl border border-stone-200/60 dark:border-stone-700/60">
                                <span className="text-[11px] text-stone-500 dark:text-stone-400 block font-medium">Reconciled Collections</span>
                                <span className="text-base font-bold font-mono text-emerald-600 dark:text-emerald-400 mt-1 block">
                                    {fmtLKR(report.cod_reconciliation.collected_total)}
                                </span>
                                <span className="text-[10px] text-stone-400 block mt-1">Matched bank remittances</span>
                            </div>

                            {/* Card 2: In-Transit */}
                            <div className="bg-stone-50 dark:bg-stone-800/60 p-4 rounded-xl border border-stone-200/60 dark:border-stone-700/60">
                                <span className="text-[11px] text-stone-500 dark:text-stone-400 block font-medium">COD In-Transit</span>
                                <span className="text-base font-bold font-mono text-stone-700 dark:text-stone-300 mt-1 block">
                                    {fmtLKR(report.cod_reconciliation.in_transit_amount)}
                                </span>
                                <span className="text-[10px] text-stone-400 block mt-1">Pending courier payout cycle</span>
                            </div>

                            {/* Card 3: Unresolved Disputes */}
                            <div className="bg-amber-50/70 dark:bg-amber-950/40 p-4 rounded-xl border border-amber-200 dark:border-amber-900/60">
                                <span className="text-[11px] text-amber-800 dark:text-amber-300 block font-medium">Unresolved Disputes</span>
                                <span className="text-base font-bold font-mono text-amber-700 dark:text-amber-400 mt-1 block">
                                    {fmtLKR(report.cod_reconciliation.unresolved_disputes_amount)}
                                </span>
                                <span className="text-[10px] text-amber-700/80 dark:text-amber-400/80 block mt-1">
                                    Suspense balance &middot; NOT written off
                                </span>
                            </div>

                            {/* Card 4: Confirmed Losses */}
                            <div className="bg-rose-50/70 dark:bg-rose-950/40 p-4 rounded-xl border border-rose-200 dark:border-rose-900/60">
                                <span className="text-[11px] text-rose-800 dark:text-rose-300 block font-medium">Confirmed Unrecoverable Losses</span>
                                <span className="text-base font-bold font-mono text-rose-700 dark:text-rose-400 mt-1 block">
                                    {fmtLKR(report.cod_reconciliation.confirmed_unrecoverable_loss)}
                                </span>
                                <span className="text-[10px] text-rose-700/80 dark:text-rose-400/80 block mt-1">
                                    Formally approved write-off
                                </span>
                            </div>

                            {/* Card 5: Recovered Amount */}
                            <div className="bg-emerald-50/70 dark:bg-emerald-950/40 p-4 rounded-xl border border-emerald-200 dark:border-emerald-900/60">
                                <span className="text-[11px] text-emerald-800 dark:text-emerald-300 block font-medium">Recovered Shortfalls</span>
                                <span className="text-base font-bold font-mono text-emerald-700 dark:text-emerald-400 mt-1 block">
                                    {fmtLKR(report.cod_reconciliation.recovered_amount)}
                                </span>
                                <span className="text-[10px] text-emerald-700/80 dark:text-emerald-400/80 block mt-1">
                                    Clawed back from couriers
                                </span>
                            </div>
                        </div>

                        <div className="text-[11px] text-stone-500 dark:text-stone-400 italic pt-1">
                            Note: Courier shortfalls are held in suspense under Unresolved Disputes during active investigations. They only reduce Gross Operating Contribution once formally approved and logged as a confirmed unrecoverable loss.
                        </div>
                    </div>
                </div>

                {/* =========================================================================
                    SECTION 6: AUDIT TRAIL & SYSTEM COMPLIANCE METADATA
                   ========================================================================= */}
                <div className="text-xs text-stone-400 dark:text-stone-500 flex flex-col sm:flex-row items-center justify-between gap-2 border-t border-stone-200 dark:border-stone-800 pt-6">
                    <div>
                        Statement Generated At: <strong>{new Date(report.period.generated_at).toLocaleString()}</strong> &middot; Perspective: <strong>{report.period.perspective}</strong>
                    </div>
                    <div>
                        Aura Luxe Financial Governance Module &middot; Phase 2 Audited
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}

