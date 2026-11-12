import * as Icons from '@/Components/Icons';
import AdminLayout from '@/Layouts/AdminLayout';
import { AuditLog, Order, Product } from '@/types';
import { Head, Link } from '@inertiajs/react';
import React, { useMemo, useState } from 'react';

interface SalesTrendItem {
    date: string;
    label: string;
    sales: number;
    orders: number;
}

interface Props {
    stats: {
        total_orders: number;
        placed_orders: number;
        in_transit_orders: number;
        delivered_orders: number;
        completed_orders: number;
        total_revenue: number;
    };
    executiveMetrics?: {
        total_sales: number;
        total_orders: number;
        total_customers: number;
        active_agents_total: number;
        high_level_agents_count: number;
        low_level_agents_count: number;
        pending_agent_applications: number;
    };
    financialOverview?: {
        gross_sales: number;
        collected_cod: number;
        pending_cod: number;
        total_commission: number;
        pending_commission: number;
        matured_commission: number;
        approved_payouts: number;
        total_wallet_balance: number;
        reserved_wallet_balance: number;
        available_wallet_balance: number;
    };
    orderPipeline?: {
        placed: number;
        confirmed: number;
        packing: number;
        shipped: number;
        delivered: number;
        cash_collected: number;
        completed: number;
        cancelled: number;
        returned: number;
    };
    salesTrend?: SalesTrendItem[];
    inventoryOverview?: {
        total_products: number;
        active_products: number;
        total_categories: number;
        low_stock_count: number;
        out_of_stock_count: number;
    };
    pendingActionCounts?: {
        payouts: number;
        permission_requests: number;
        hierarchy_requests: number;
        low_stock: number;
        total_pending: number;
    };
    lowStockProducts: Product[];
    recentOrders: Order[];
    recentLogs: AuditLog[];
}

export default function AdminDashboard({
    stats,
    executiveMetrics,
    financialOverview,
    orderPipeline,
    salesTrend = [],
    inventoryOverview,
    pendingActionCounts,
    lowStockProducts = [],
    recentOrders = [],
    recentLogs = [],
}: Props) {
    // Interactive Date Range Filter for Sales Chart
    const [chartRange, setChartRange] = useState<'7d' | '14d' | '30d'>('30d');

    // Filter sales trend data based on selected range
    const filteredTrend = useMemo(() => {
        if (!salesTrend || salesTrend.length === 0) return [];
        const sliceCount = chartRange === '7d' ? 7 : chartRange === '14d' ? 14 : 30;
        return salesTrend.slice(-sliceCount);
    }, [salesTrend, chartRange]);

    // Compute chart math (SVG coordinates)
    const chartMetrics = useMemo(() => {
        if (filteredTrend.length === 0) {
            return {
                points: '',
                areaPoints: '',
                maxSales: 1000,
                totalRangeSales: 0,
                totalRangeOrders: 0,
            };
        }

        const max = Math.max(...filteredTrend.map((d) => d.sales), 1000);
        const width = 600;
        const height = 180;
        const padding = 20;

        const totalRangeSales = filteredTrend.reduce((acc, curr) => acc + curr.sales, 0);
        const totalRangeOrders = filteredTrend.reduce((acc, curr) => acc + curr.orders, 0);

        const coords = filteredTrend.map((d, idx) => {
            const x = padding + (idx / (filteredTrend.length - 1 || 1)) * (width - padding * 2);
            const y = height - padding - (d.sales / max) * (height - padding * 2);
            return { x, y, ...d };
        });

        const points = coords.map((c) => `${c.x.toFixed(1)},${c.y.toFixed(1)}`).join(' ');
        const firstX = coords[0]?.x ?? padding;
        const lastX = coords[coords.length - 1]?.x ?? width - padding;
        const areaPoints = `${firstX},${height - padding} ${points} ${lastX},${height - padding}`;

        return {
            points,
            areaPoints,
            coords,
            maxSales: max,
            totalRangeSales,
            totalRangeOrders,
        };
    }, [filteredTrend]);

    // Donut chart percentages for Order Pipeline
    const donutData = useMemo(() => {
        const pipeline = orderPipeline ?? {
            placed: stats.placed_orders,
            confirmed: 0,
            packing: 0,
            shipped: stats.in_transit_orders,
            delivered: stats.delivered_orders,
            cash_collected: 0,
            completed: stats.completed_orders,
            cancelled: 0,
            returned: 0,
        };

        const total = Object.values(pipeline).reduce((a, b) => a + b, 0) || 1;
        const pending = pipeline.placed;
        const processing = pipeline.confirmed + pipeline.packing;
        const transit = pipeline.shipped;
        const completed = pipeline.delivered + pipeline.cash_collected + pipeline.completed;
        const returned = pipeline.cancelled + pipeline.returned;

        return [
            { label: 'Completed & Delivered', count: completed, color: '#10B981', pct: Math.round((completed / total) * 100) },
            { label: 'In Transit', count: transit, color: '#3B82F6', pct: Math.round((transit / total) * 100) },
            { label: 'Processing & Packing', count: processing, color: '#6366F1', pct: Math.round((processing / total) * 100) },
            { label: 'Placed / Pending', count: pending, color: '#F59E0B', pct: Math.round((pending / total) * 100) },
            { label: 'Cancelled / Returned', count: returned, color: '#EF4444', pct: Math.round((returned / total) * 100) },
        ];
    }, [orderPipeline, stats]);

    // Format currency
    const fmtLKR = (val: number | undefined | null) => {
        const num = Number(val ?? 0);
        return `LKR ${num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
    };

    return (
        <AdminLayout>
            <Head title="Executive Business Dashboard | Aura Luxe" />

            <div className="space-y-8 pb-12">
                {/* =========================================================================
                    SECTION A: WELCOME HEADER & QUICK ACTIONS
                   ========================================================================= */}
                <div className="bg-white dark:bg-[#0F172A] rounded-2xl p-6 sm:p-8 border border-slate-200/80 dark:border-slate-800/80 shadow-xs relative overflow-hidden transition-colors">
                    <div className="absolute top-0 right-0 -mt-10 -mr-10 w-64 h-64 bg-gradient-to-br from-blue-500/10 via-indigo-500/5 to-transparent rounded-full pointer-events-none blur-2xl" />

                    <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-6 relative z-10">
                        <div>
                            <div className="flex items-center gap-2 mb-2">
                                <span className="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800">
                                    <span className="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse" />
                                    Live Operations
                                </span>
                                <span className="text-xs text-slate-300 dark:text-slate-700">&bull;</span>
                                <span className="text-xs text-slate-500 dark:text-slate-400 font-medium">
                                    {new Date().toLocaleDateString('en-US', { weekday: 'long', month: 'short', day: 'numeric', year: 'numeric' })}
                                </span>
                            </div>

                            <h1 className="font-sans text-2xl sm:text-3xl text-slate-900 dark:text-white font-bold tracking-tight">
                                Business Overview
                            </h1>
                            <p className="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1 max-w-xl">
                                Real-time executive metrics, order fulfillment, financial reconciliation, and agent network telemetry.
                            </p>
                        </div>

                        {/* Quick Action Navigation Pills */}
                        <div className="flex flex-wrap items-center gap-2.5">
                            <Link
                                href="/admin/approvals"
                                className="inline-flex items-center gap-2 px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-2xl text-xs font-semibold shadow-sm transition-colors cursor-pointer"
                            >
                                <Icons.CheckSquare className="w-4 h-4" />
                                <span>Approval Center</span>
                                {Boolean(pendingActionCounts?.total_pending) && (
                                    <span className="px-2 py-0.5 bg-rose-500 text-white rounded-full text-[10px] font-bold">
                                        {pendingActionCounts?.total_pending}
                                    </span>
                                )}
                            </Link>

                            <Link
                                href="/admin/payouts"
                                className="inline-flex items-center gap-2 px-4 py-2.5 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 rounded-2xl text-xs font-semibold transition-colors cursor-pointer"
                            >
                                <Icons.CreditCard className="w-4 h-4 text-slate-500 dark:text-slate-400" />
                                <span>Payouts</span>
                                {Boolean(pendingActionCounts?.payouts) && (
                                    <span className="px-2 py-0.5 bg-[#EF4444] text-white rounded-full text-[10px] font-bold">
                                        {pendingActionCounts?.payouts}
                                    </span>
                                )}
                            </Link>

                            <Link
                                href="/admin/agents"
                                className="inline-flex items-center gap-2 px-4 py-2.5 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 rounded-2xl text-xs font-semibold transition-colors cursor-pointer"
                            >
                                <Icons.GitBranch className="w-4 h-4 text-slate-500 dark:text-slate-400" />
                                <span>Agent Network</span>
                            </Link>

                            <Link
                                href="/admin/products/create"
                                className="inline-flex items-center gap-2 px-4 py-2.5 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 rounded-2xl text-xs font-semibold transition-colors cursor-pointer"
                            >
                                <Icons.Plus className="w-4 h-4 text-slate-500 dark:text-slate-400" />
                                <span>Add Product</span>
                            </Link>
                        </div>
                    </div>
                </div>

                {/* =========================================================================
                    SECTION B: 4 PRIMARY EXECUTIVE KPI CARDS
                   ========================================================================= */}
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
                    {/* Card 1: Total Sales */}
                    <div className="bg-white dark:bg-[#0F172A] p-5 rounded-2xl border border-slate-200/80 dark:border-slate-800/80 shadow-xs hover:border-slate-300 dark:hover:border-slate-700 transition-all">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                Total Gross Sales
                            </span>
                            <div className="w-10 h-10 rounded-2xl bg-emerald-50 dark:bg-emerald-950/50 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
                                <Icons.DollarSign className="w-5 h-5" />
                            </div>
                        </div>
                        <div className="mt-3">
                            <div className="text-2xl font-bold font-mono tracking-tight text-slate-900 dark:text-white">
                                {fmtLKR(executiveMetrics?.total_sales ?? stats.total_revenue)}
                            </div>
                            <div className="flex items-center gap-1.5 text-xs text-slate-500 dark:text-slate-400 mt-1">
                                <Icons.TrendingUp className="w-3.5 h-3.5 text-emerald-600 dark:text-emerald-400" />
                                <span>Invoiced sales across all orders</span>
                            </div>
                        </div>
                    </div>

                    {/* Card 2: Total Orders */}
                    <div className="bg-white dark:bg-[#0F172A] p-5 rounded-2xl border border-slate-200/80 dark:border-slate-800/80 shadow-xs hover:border-slate-300 dark:hover:border-slate-700 transition-all">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                Total Orders
                            </span>
                            <div className="w-10 h-10 rounded-2xl bg-blue-50 dark:bg-blue-950/50 text-blue-600 dark:text-blue-400 flex items-center justify-center">
                                <Icons.ShoppingCart className="w-5 h-5" />
                            </div>
                        </div>
                        <div className="mt-3">
                            <div className="text-2xl font-bold font-mono tracking-tight text-slate-900 dark:text-white">
                                {(executiveMetrics?.total_orders ?? stats.total_orders).toLocaleString()}
                            </div>
                            <div className="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400 mt-1">
                                <span className="font-semibold text-amber-700 dark:text-amber-400 bg-amber-50 dark:bg-amber-950/50 px-2 py-0.5 rounded-full text-[10px]">
                                    {stats.placed_orders} pending
                                </span>
                                <span>&bull; {stats.in_transit_orders} in transit</span>
                            </div>
                        </div>
                    </div>

                    {/* Card 3: Total Customers */}
                    <div className="bg-white dark:bg-[#0F172A] p-5 rounded-2xl border border-slate-200/80 dark:border-slate-800/80 shadow-xs hover:border-slate-300 dark:hover:border-slate-700 transition-all">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                Registered Buyers
                            </span>
                            <div className="w-10 h-10 rounded-2xl bg-violet-50 dark:bg-violet-950/50 text-violet-600 dark:text-violet-400 flex items-center justify-center">
                                <Icons.Users className="w-5 h-5" />
                            </div>
                        </div>
                        <div className="mt-3">
                            <div className="text-2xl font-bold font-mono tracking-tight text-slate-900 dark:text-white">
                                {(executiveMetrics?.total_customers ?? 0).toLocaleString()}
                            </div>
                            <div className="text-xs text-slate-500 dark:text-slate-400 mt-1">
                                Verified consumer customer accounts
                            </div>
                        </div>
                    </div>

                    {/* Card 4: Active Agent Network */}
                    <div className="bg-white dark:bg-[#0F172A] p-5 rounded-2xl border border-slate-200/80 dark:border-slate-800/80 shadow-xs hover:border-slate-300 dark:hover:border-slate-700 transition-all">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                Active Agent Network
                            </span>
                            <div className="w-10 h-10 rounded-2xl bg-amber-50 dark:bg-amber-950/50 text-amber-600 dark:text-amber-400 flex items-center justify-center">
                                <Icons.GitBranch className="w-5 h-5" />
                            </div>
                        </div>
                        <div className="mt-3">
                            <div className="text-2xl font-bold font-mono tracking-tight text-slate-900 dark:text-white">
                                {(executiveMetrics?.active_agents_total ?? 0).toLocaleString()}
                            </div>
                            <div className="flex items-center gap-1.5 text-xs text-slate-500 dark:text-slate-400 mt-1">
                                <span className="font-semibold text-slate-900 dark:text-white">
                                    {executiveMetrics?.high_level_agents_count ?? 0} High
                                </span>
                                <span>/</span>
                                <span className="font-semibold text-slate-700 dark:text-slate-300">
                                    {executiveMetrics?.low_level_agents_count ?? 0} Low
                                </span>
                                {Boolean(executiveMetrics?.pending_agent_applications) && (
                                    <span className="text-[10px] text-amber-700 dark:text-amber-400 bg-amber-50 dark:bg-amber-950/50 px-2 py-0.2 rounded-full font-semibold ml-1">
                                        {executiveMetrics?.pending_agent_applications} kyc
                                    </span>
                                )}
                            </div>
                        </div>
                    </div>
                </div>

                {/* =========================================================================
                    SECTION C: BUSINESS ANALYTICS & INTERACTIVE CHARTS
                   ========================================================================= */}
                <div className="grid grid-cols-1 lg:grid-cols-5 gap-6">
                    {/* Left Chart: Sales Performance (3 cols) */}
                    <div className="lg:col-span-3 bg-white dark:bg-[#0F172A] p-6 rounded-2xl border border-slate-200/80 dark:border-slate-800/80 shadow-xs flex flex-col justify-between transition-colors">
                        <div>
                            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-4 border-b border-slate-100 dark:border-slate-800/80">
                                <div>
                                    <h2 className="text-sm font-semibold uppercase tracking-wider text-slate-800 dark:text-slate-200">
                                        Sales Velocity & Trend
                                    </h2>
                                    <p className="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                        Real order value over time (30-day window)
                                    </p>
                                </div>

                                {/* Range Selector */}
                                <div className="flex items-center bg-slate-100 dark:bg-slate-800 p-1 rounded-2xl text-xs font-semibold">
                                    {(['7d', '14d', '30d'] as const).map((r) => (
                                        <button
                                            key={r}
                                            onClick={() => setChartRange(r)}
                                            className={`px-3 py-1.5 rounded-xl transition-colors cursor-pointer ${
                                                chartRange === r
                                                    ? 'bg-white dark:bg-slate-900 text-slate-900 dark:text-white shadow-2xs font-bold'
                                                    : 'text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
                                            }`}
                                        >
                                            {r === '7d' ? '7 Days' : r === '14d' ? '14 Days' : '30 Days'}
                                        </button>
                                    ))}
                                </div>
                            </div>

                            <div className="flex items-center gap-6 mt-4">
                                <div>
                                    <span className="text-[11px] text-slate-400 dark:text-slate-500 block uppercase font-medium">Period Total</span>
                                    <span className="text-xl font-bold font-mono text-slate-900 dark:text-white">
                                        {fmtLKR(chartMetrics.totalRangeSales)}
                                    </span>
                                </div>
                                <div className="border-l border-slate-200 dark:border-slate-800 pl-6">
                                    <span className="text-[11px] text-slate-400 dark:text-slate-500 block uppercase font-medium">Total Orders</span>
                                    <span className="text-xl font-bold font-mono text-slate-900 dark:text-white">
                                        {chartMetrics.totalRangeOrders}
                                    </span>
                                </div>
                            </div>
                        </div>

                        {/* Interactive SVG Area Chart */}
                        <div className="mt-6 w-full h-48 relative">
                            {filteredTrend.length === 0 ? (
                                <div className="w-full h-full flex items-center justify-center text-xs text-slate-400">
                                    No sales activity recorded in this window.
                                </div>
                            ) : (
                                <svg
                                    viewBox="0 0 600 180"
                                    className="w-full h-full overflow-visible"
                                    preserveAspectRatio="none"
                                >
                                    <defs>
                                        <linearGradient id="salesGrad" x1="0%" y1="0%" x2="0%" y2="100%">
                                            <stop offset="0%" stopColor="#2563EB" stopOpacity="0.3" />
                                            <stop offset="100%" stopColor="#2563EB" stopOpacity="0.0" />
                                        </linearGradient>
                                    </defs>

                                    {/* Guidelines */}
                                    <line x1="20" y1="20" x2="580" y2="20" stroke="#94A3B8" strokeWidth="0.5" strokeDasharray="4 4" opacity="0.3" />
                                    <line x1="20" y1="90" x2="580" y2="90" stroke="#94A3B8" strokeWidth="0.5" strokeDasharray="4 4" opacity="0.3" />
                                    <line x1="20" y1="160" x2="580" y2="160" stroke="#94A3B8" strokeWidth="1" opacity="0.5" />

                                    {/* Area Fill */}
                                    <polygon points={chartMetrics.areaPoints} fill="url(#salesGrad)" />

                                    {/* Line */}
                                    <polyline
                                        points={chartMetrics.points}
                                        fill="none"
                                        stroke="#2563EB"
                                        strokeWidth="2.5"
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                    />

                                    {/* Data Points */}
                                    {chartMetrics.coords?.map((pt, i) => (
                                        <circle
                                            key={i}
                                            cx={pt.x}
                                            cy={pt.y}
                                            r={pt.sales > 0 ? 3.5 : 2}
                                            fill="#FFFFFF"
                                            stroke="#2563EB"
                                            strokeWidth="2"
                                            className="hover:r-5 transition-all cursor-pointer"
                                        >
                                            <title>{`${pt.label}: LKR ${pt.sales.toLocaleString()} (${pt.orders} orders)`}</title>
                                        </circle>
                                    ))}
                                </svg>
                            )}

                            {/* X-axis date labels */}
                            <div className="flex justify-between text-[10px] text-slate-400 dark:text-slate-500 mt-2 px-2 font-mono">
                                <span>{filteredTrend[0]?.label ?? ''}</span>
                                <span>{filteredTrend[Math.floor(filteredTrend.length / 2)]?.label ?? ''}</span>
                                <span>{filteredTrend[filteredTrend.length - 1]?.label ?? ''}</span>
                            </div>
                        </div>
                    </div>

                    {/* Right Chart: Fulfillment Pipeline (2 cols) */}
                    <div className="lg:col-span-2 bg-white dark:bg-[#0F172A] p-6 rounded-2xl border border-slate-200/80 dark:border-slate-800/80 shadow-xs flex flex-col justify-between transition-colors">
                        <div>
                            <div className="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800/80">
                                <div>
                                    <h2 className="text-sm font-semibold uppercase tracking-wider text-slate-800 dark:text-slate-200">
                                        Fulfillment Pipeline
                                    </h2>
                                    <p className="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                        Status distribution of all customer orders
                                    </p>
                                </div>
                                <Link href="/admin/orders" className="text-xs text-blue-600 dark:text-blue-400 hover:underline font-semibold">
                                    Manage &rarr;
                                </Link>
                            </div>

                            {/* Donut representation */}
                            <div className="my-6 flex items-center justify-center">
                                <div className="relative w-36 h-36 flex items-center justify-center">
                                    <svg viewBox="0 0 36 36" className="w-full h-full transform -rotate-90">
                                        <circle
                                            cx="18"
                                            cy="18"
                                            r="15.9155"
                                            fill="transparent"
                                            stroke="#F1F5F9"
                                            className="dark:stroke-slate-800"
                                            strokeWidth="3.8"
                                        />
                                        {(() => {
                                            let currentOffset = 0;
                                            return donutData.map((seg, sIdx) => {
                                                const strokeDash = `${seg.pct} ${100 - seg.pct}`;
                                                const offset = currentOffset;
                                                currentOffset += seg.pct;
                                                return (
                                                    <circle
                                                        key={sIdx}
                                                        cx="18"
                                                        cy="18"
                                                        r="15.9155"
                                                        fill="transparent"
                                                        stroke={seg.color}
                                                        strokeWidth="3.8"
                                                        strokeDasharray={strokeDash}
                                                        strokeDashoffset={-offset}
                                                    />
                                                );
                                            });
                                        })()}
                                    </svg>
                                    <div className="absolute text-center">
                                        <span className="text-slate-400 dark:text-slate-500 block uppercase text-[10px] font-semibold">Total</span>
                                        <span className="text-lg font-bold font-mono text-slate-900 dark:text-white leading-none">
                                            {stats.total_orders}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* Pipeline Legend */}
                        <div className="space-y-2 pt-2 border-t border-slate-100 dark:border-slate-800/80 text-xs">
                            {donutData.map((seg, idx) => (
                                <div key={idx} className="flex items-center justify-between text-slate-600 dark:text-slate-400">
                                    <div className="flex items-center gap-2">
                                        <span className="w-2.5 h-2.5 rounded-full shrink-0" style={{ backgroundColor: seg.color }} />
                                        <span>{seg.label}</span>
                                    </div>
                                    <div className="flex items-center gap-2 font-mono font-semibold text-slate-800 dark:text-slate-200">
                                        <span>{seg.count}</span>
                                        <span className="text-[10px] text-slate-400">({seg.pct}%)</span>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>

                {/* =========================================================================
                    SECTION D: 8 FINANCIAL SUMMARY & RECONCILIATION CARDS
                   ========================================================================= */}
                <div>
                    <div className="flex items-center justify-between mb-4">
                        <div>
                            <h2 className="text-sm font-semibold uppercase tracking-wider text-slate-900 dark:text-white">
                                Financial Reconciliation & Ledger
                            </h2>
                            <p className="text-xs text-slate-500 dark:text-slate-400">
                                Strict ledger reconciliation between cash collected, commission reserves, and payouts.
                            </p>
                        </div>
                        <div className="flex items-center gap-3">
                            <Link href="/admin/finance/contribution" className="text-xs font-semibold text-rose-600 dark:text-rose-400 hover:underline">
                                Operating Contribution Statement &rarr;
                            </Link>
                            <Link href="/admin/commissions/ledger" className="text-xs font-semibold text-blue-600 dark:text-blue-400 hover:underline">
                                Commission Ledger &rarr;
                            </Link>
                        </div>
                    </div>

                    <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                        {/* 1. Gross Sales */}
                        <div className="bg-white dark:bg-[#0F172A] p-4 rounded-2xl border border-slate-200/80 dark:border-slate-800/80 shadow-2xs">
                            <span className="text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider block">
                                Gross Invoiced Sales
                            </span>
                            <span className="text-lg font-bold font-mono text-slate-900 dark:text-white mt-1 block">
                                {fmtLKR(financialOverview?.gross_sales ?? stats.total_revenue)}
                            </span>
                            <span className="text-[10px] text-slate-400 mt-0.5 block">Excluding cancelled orders</span>
                        </div>

                        {/* 2. Collected COD */}
                        <div className="bg-white dark:bg-[#0F172A] p-4 rounded-2xl border border-emerald-200 dark:border-emerald-800/60 bg-emerald-50/20 dark:bg-emerald-950/20 shadow-2xs">
                            <span className="text-[11px] font-semibold text-emerald-800 dark:text-emerald-400 uppercase tracking-wider block">
                                Reconciled COD
                            </span>
                            <span className="text-lg font-bold font-mono text-emerald-900 dark:text-emerald-300 mt-1 block">
                                {fmtLKR(financialOverview?.collected_cod ?? stats.total_revenue)}
                            </span>
                            <span className="text-[10px] text-emerald-700/80 dark:text-emerald-500 mt-0.5 block">Delivered & confirmed cash</span>
                        </div>

                        {/* 3. Pending COD */}
                        <div className="bg-white dark:bg-[#0F172A] p-4 rounded-2xl border border-blue-200 dark:border-blue-800/60 bg-blue-50/20 dark:bg-blue-950/20 shadow-2xs">
                            <span className="text-[11px] font-semibold text-blue-800 dark:text-blue-400 uppercase tracking-wider block">
                                COD In Transit
                            </span>
                            <span className="text-lg font-bold font-mono text-blue-900 dark:text-blue-300 mt-1 block">
                                {fmtLKR(financialOverview?.pending_cod ?? 0)}
                            </span>
                            <span className="text-[10px] text-blue-700/80 dark:text-blue-500 mt-0.5 block">With courier delivery partners</span>
                        </div>

                        {/* 4. Total Commission */}
                        <div className="bg-white dark:bg-[#0F172A] p-4 rounded-2xl border border-slate-200/80 dark:border-slate-800/80 shadow-2xs">
                            <span className="text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider block">
                                Cumulative Commission
                            </span>
                            <span className="text-lg font-bold font-mono text-slate-900 dark:text-white mt-1 block">
                                {fmtLKR(financialOverview?.total_commission ?? 0)}
                            </span>
                            <span className="text-[10px] text-slate-400 mt-0.5 block">All earned agent commissions</span>
                        </div>

                        {/* 5. Pending Commission */}
                        <div className="bg-white dark:bg-[#0F172A] p-4 rounded-2xl border border-amber-200 dark:border-amber-800/60 bg-amber-50/20 dark:bg-amber-950/20 shadow-2xs">
                            <span className="text-[11px] font-semibold text-amber-800 dark:text-amber-400 uppercase tracking-wider block">
                                Holding Commissions
                            </span>
                            <span className="text-lg font-bold font-mono text-amber-900 dark:text-amber-300 mt-1 block">
                                {fmtLKR(financialOverview?.pending_commission ?? 0)}
                            </span>
                            <span className="text-[10px] text-amber-700/80 dark:text-amber-500 mt-0.5 block">Within 7-day return grace window</span>
                        </div>

                        {/* 6. Matured Commission */}
                        <div className="bg-white dark:bg-[#0F172A] p-4 rounded-2xl border border-indigo-200 dark:border-indigo-800/60 bg-indigo-50/20 dark:bg-indigo-950/20 shadow-2xs">
                            <span className="text-[11px] font-semibold text-indigo-800 dark:text-indigo-400 uppercase tracking-wider block">
                                Matured Commissions
                            </span>
                            <span className="text-lg font-bold font-mono text-indigo-900 dark:text-indigo-300 mt-1 block">
                                {fmtLKR(financialOverview?.matured_commission ?? 0)}
                            </span>
                            <span className="text-[10px] text-indigo-700/80 dark:text-indigo-500 mt-0.5 block">Eligible for agent withdrawal</span>
                        </div>

                        {/* 7. Approved Payouts */}
                        <div className="bg-white dark:bg-[#0F172A] p-4 rounded-2xl border border-rose-200 dark:border-rose-800/60 bg-rose-50/20 dark:bg-rose-950/20 shadow-2xs">
                            <span className="text-[11px] font-semibold text-rose-800 dark:text-rose-400 uppercase tracking-wider block">
                                Completed Payouts
                            </span>
                            <span className="text-lg font-bold font-mono text-rose-900 dark:text-rose-300 mt-1 block">
                                {fmtLKR(financialOverview?.approved_payouts ?? 0)}
                            </span>
                            <span className="text-[10px] text-rose-700/80 dark:text-rose-500 mt-0.5 block">Disbursed to agent bank accounts</span>
                        </div>

                        {/* 8. Available Wallet Balance */}
                        <div className="bg-[#1E293B] text-white p-4 rounded-2xl shadow-xs">
                            <span className="text-[11px] font-semibold text-slate-400 uppercase tracking-wider block">
                                Available Agent Wallets
                            </span>
                            <span className="text-lg font-bold font-mono text-blue-300 mt-1 block">
                                {fmtLKR(financialOverview?.available_wallet_balance ?? 0)}
                            </span>
                            <span className="text-[10px] text-slate-400 mt-0.5 block">Net unwithdrawn balances</span>
                        </div>
                    </div>
                </div>

                {/* =========================================================================
                    SECTION E & F: RECENT ORDERS & LOW STOCK WATCHLIST
                   ========================================================================= */}
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    {/* Recent Orders Table (2 cols) */}
                    <div className="lg:col-span-2 bg-white dark:bg-[#0F172A] rounded-2xl border border-slate-200/80 dark:border-slate-800/80 shadow-xs overflow-hidden transition-colors">
                        <div className="px-6 py-4 border-b border-slate-100 dark:border-slate-800/80 flex items-center justify-between">
                            <div>
                                <h2 className="text-sm font-semibold uppercase tracking-wider text-slate-900 dark:text-white">
                                    Recent Orders
                                </h2>
                                <p className="text-xs text-slate-500 dark:text-slate-400">Live order activity across customer storefront</p>
                            </div>
                            <Link href="/admin/orders" className="text-xs font-semibold text-blue-600 dark:text-blue-400 hover:underline">
                                View All Orders &rarr;
                            </Link>
                        </div>

                        <div className="overflow-x-auto">
                            <table className="w-full text-left border-collapse text-xs">
                                <thead>
                                    <tr className="bg-slate-50 dark:bg-slate-900/60 border-b border-slate-100 dark:border-slate-800 text-slate-500 dark:text-slate-400 uppercase font-mono text-[10px]">
                                        <th className="py-3 px-4">Order #</th>
                                        <th className="py-3 px-4">Customer & City</th>
                                        <th className="py-3 px-4">Date</th>
                                        <th className="py-3 px-4 text-right">Amount</th>
                                        <th className="py-3 px-4 text-center">Status</th>
                                        <th className="py-3 px-4 text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100 dark:divide-slate-800/60">
                                    {recentOrders.length === 0 ? (
                                        <tr>
                                            <td colSpan={6} className="py-8 text-center text-slate-400 text-xs">
                                                No customer orders recorded yet.
                                            </td>
                                        </tr>
                                    ) : (
                                        recentOrders.map((ord) => (
                                            <tr key={ord.id} className="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition-colors">
                                                <td className="py-3 px-4 font-mono font-bold text-blue-600 dark:text-blue-400">
                                                    <Link href={`/admin/orders/${ord.id}`} className="hover:underline">
                                                        {ord.order_number}
                                                    </Link>
                                                </td>
                                                <td className="py-3 px-4">
                                                    <div className="font-semibold text-slate-900 dark:text-white">{ord.customer_name}</div>
                                                    <div className="text-[11px] text-slate-500 dark:text-slate-400">{ord.district}</div>
                                                </td>
                                                <td className="py-3 px-4 text-slate-500 dark:text-slate-400">
                                                    {new Date(ord.placed_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })}
                                                </td>
                                                <td className="py-3 px-4 text-right font-mono font-semibold text-slate-900 dark:text-white">
                                                    {fmtLKR(Number(ord.total_amount))}
                                                </td>
                                                <td className="py-3 px-4 text-center">
                                                    <span className={`inline-block text-[10px] font-semibold uppercase px-2.5 py-0.5 rounded-full ${
                                                        ord.status === 'completed' || ord.status === 'delivered'
                                                            ? 'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-800 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800'
                                                            : ord.status === 'shipped'
                                                            ? 'bg-blue-50 dark:bg-blue-950/60 text-blue-800 dark:text-blue-300 border border-blue-200 dark:border-blue-800'
                                                            : ord.status === 'placed'
                                                            ? 'bg-amber-50 dark:bg-amber-950/60 text-amber-800 dark:text-amber-300 border border-amber-200 dark:border-amber-800'
                                                            : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300'
                                                    }`}>
                                                        {ord.status.replace('_', ' ')}
                                                    </span>
                                                </td>
                                                <td className="py-3 px-4 text-right">
                                                    <Link
                                                        href={`/admin/orders/${ord.id}`}
                                                        className="text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white font-semibold text-[11px]"
                                                    >
                                                        Inspect &rarr;
                                                    </Link>
                                                </td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {/* Low Stock Watchlist (1 col) */}
                    <div className="bg-white dark:bg-[#0F172A] rounded-2xl border border-slate-200/80 dark:border-slate-800/80 shadow-xs overflow-hidden flex flex-col justify-between transition-colors">
                        <div>
                            <div className="px-6 py-4 border-b border-slate-100 dark:border-slate-800/80 flex items-center justify-between bg-rose-50/30 dark:bg-rose-950/20">
                                <div className="flex items-center gap-2">
                                    <div className="w-2 h-2 rounded-full bg-rose-500 animate-ping" />
                                    <h2 className="text-sm font-semibold uppercase tracking-wider text-rose-950 dark:text-rose-300">
                                        Low Stock Alert
                                    </h2>
                                </div>
                                <Link href="/admin/inventory" className="text-xs font-semibold text-rose-700 dark:text-rose-400 hover:underline">
                                    Inventory &rarr;
                                </Link>
                            </div>

                            <div className="divide-y divide-slate-100 dark:divide-slate-800/60">
                                {lowStockProducts.length === 0 ? (
                                    <div className="p-8 text-center text-xs text-slate-400">
                                        <Icons.CheckCircle2 className="w-8 h-8 text-emerald-500 mx-auto mb-2" />
                                        <span>All products are healthy above thresholds.</span>
                                    </div>
                                ) : (
                                    lowStockProducts.map((p) => (
                                        <div key={p.id} className="p-4 flex items-center justify-between hover:bg-slate-50 dark:hover:bg-slate-800/40 transition-colors">
                                            <div className="min-w-0 pr-3">
                                                <div className="font-semibold text-slate-900 dark:text-white text-xs truncate">
                                                    {p.name_en}
                                                </div>
                                                <div className="text-[11px] text-slate-400 font-mono">
                                                    SKU: {p.sku}
                                                </div>
                                            </div>
                                            <div className="text-right shrink-0">
                                                <span className={`inline-block px-2.5 py-0.5 rounded-full text-xs font-mono font-bold ${
                                                    p.stock_quantity <= 0
                                                        ? 'bg-rose-100 dark:bg-rose-950/60 text-rose-900 dark:text-rose-300 border border-rose-300 dark:border-rose-800'
                                                        : 'bg-amber-100 dark:bg-amber-950/60 text-amber-900 dark:text-amber-300 border border-amber-300 dark:border-amber-800'
                                                }`}>
                                                    {p.stock_quantity} in stock
                                                </span>
                                                <span className="block text-[10px] text-slate-400 mt-0.5 font-mono">
                                                    Limit: {p.low_stock_threshold}
                                                </span>
                                            </div>
                                        </div>
                                    ))
                                )}
                            </div>
                        </div>

                        {/* Footer stats */}
                        <div className="p-4 border-t border-slate-100 dark:border-slate-800/80 bg-slate-50/60 dark:bg-slate-900/60 flex items-center justify-between text-xs">
                            <span className="text-slate-500 dark:text-slate-400 font-medium">Catalog Health</span>
                            <div className="flex items-center gap-3 font-semibold text-slate-700 dark:text-slate-300 font-mono text-[11px]">
                                <span>{inventoryOverview?.total_products ?? 0} total</span>
                                <span>&bull;</span>
                                <span className="text-rose-600 dark:text-rose-400">{inventoryOverview?.out_of_stock_count ?? 0} out of stock</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
