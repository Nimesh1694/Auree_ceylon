import React, { useState } from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import * as Icons from '@/Components/Icons';
import { PageProps } from '@/types';

export interface LeaderboardSummary {
    gross_product_sales: string;
    discounts_applied: string;
    net_product_sales: string;
    orders_placed: number;
    orders_commercial: number;
    orders_fulfilled: number;
    orders_returned: number;
    orders_cancelled: number;
    placed_gross_aov: string;
    commercial_gross_aov: string;
    net_aov: string;
    fulfillment_rate: number;
    return_rate: number;
    dispatched_return_rate: number;
    cancellation_friction_rate: number;
    approved_commissions: string;
    pending_commissions: string;
    reversed_commissions: string;
    total_deductions_cents: string;
    full_return_order_value: string;
    fulfilled_partial_refund_amount: string;
    active_agents_count?: number;
    target_attainment_pct?: number | null;
}

export interface LeaderboardAgent {
    id: number;
    name: string;
    email: string;
    role: string;
    status: string;
    referral_code: string;
    parent_agent: { id: number; name: string } | null;
    sales_target: string | null;
    target_attainment_pct: number | null;
    summary: LeaderboardSummary;
}

interface PaginatedData<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: { url: string | null; label: string; active: boolean }[];
}

interface Props extends PageProps {
    filters: {
        period: string;
        role: string;
        search: string;
    };
    periodLabel: string;
    leaderboard: PaginatedData<LeaderboardAgent>;
    companySummary: LeaderboardSummary;
    canManageTargets: boolean;
    canExport: boolean;
}

export default function Index({
    filters,
    periodLabel,
    leaderboard,
    companySummary,
    canManageTargets,
    canExport,
}: Props) {
    const [period, setPeriod] = useState(filters.period || 'this_month');
    const [role, setRole] = useState(filters.role || 'all');
    const [search, setSearch] = useState(filters.search || '');
    const [targetModalOpen, setTargetModalOpen] = useState(false);
    const [selectedAgentForTarget, setSelectedAgentForTarget] = useState<LeaderboardAgent | null>(null);

    const { data: targetForm, setData: setTargetForm, post, processing, errors, reset } = useForm({
        agent_id: 0,
        month: new Date().toISOString().slice(0, 7), // YYYY-MM
        sales_target: '',
    });

    const handleFilterChange = (newPeriod?: string, newRole?: string) => {
        const p = newPeriod !== undefined ? newPeriod : period;
        const r = newRole !== undefined ? newRole : role;
        router.get(
            '/admin/agents/analytics',
            { period: p, role: r, search },
            { preserveState: true, preserveScroll: true }
        );
    };

    const handleSearchSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(
            '/admin/agents/analytics',
            { period, role, search },
            { preserveState: true, preserveScroll: true }
        );
    };

    const openTargetModal = (agent: LeaderboardAgent) => {
        setSelectedAgentForTarget(agent);
        setTargetForm({
            agent_id: agent.id,
            month: new Date().toISOString().slice(0, 7),
            sales_target: agent.sales_target || '',
        });
        setTargetModalOpen(true);
    };

    const handleTargetSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/admin/agents/analytics/targets', {
            preserveScroll: true,
            onSuccess: () => {
                setTargetModalOpen(false);
                reset();
                setSelectedAgentForTarget(null);
            },
        });
    };

    const exportCsvUrl = `/admin/agents/analytics/export?period=${encodeURIComponent(period)}&role=${encodeURIComponent(role)}&search=${encodeURIComponent(search)}`;

    return (
        <AdminLayout>
            <Head title="Agent Performance Analytics | Aura Luxe Administration" />

            <div className="space-y-6">
                {/* Header & Export */}
                <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <div className="flex items-center gap-2">
                            <h1 className="text-2xl font-bold text-stone-900 font-serif">
                                Agent Performance Analytics
                            </h1>
                            <span className="text-xs font-semibold px-2.5 py-0.5 rounded-full bg-rose-100 text-rose-800 border border-rose-200">
                                FV-04-01
                            </span>
                        </div>
                        <p className="text-sm text-stone-500 mt-1">
                            Operational overview, sales performance metrics, and quota attainment across the brand agent network.
                        </p>
                    </div>

                    <div className="flex items-center gap-3">
                        {canExport && (
                            <a
                                href={exportCsvUrl}
                                download
                                className="inline-flex items-center gap-2 px-4 py-2 bg-white border border-stone-300 rounded-lg text-sm font-medium text-stone-700 hover:bg-stone-50 transition shadow-xs"
                            >
                                <Icons.Download className="w-4 h-4 text-stone-500" />
                                Export CSV
                            </a>
                        )}
                    </div>
                </div>

                {/* Filter Toolbar */}
                <div className="bg-white p-4 rounded-xl border border-stone-200 shadow-xs flex flex-col md:flex-row gap-4 items-stretch md:items-center justify-between">
                    <div className="flex flex-wrap items-center gap-3">
                        <div>
                            <label className="block text-xs font-medium text-stone-500 mb-1">Period</label>
                            <select
                                value={period}
                                onChange={(e) => {
                                    setPeriod(e.target.value);
                                    handleFilterChange(e.target.value, undefined);
                                }}
                                className="text-sm border-stone-300 rounded-lg focus:ring-rose-500 focus:border-rose-500 bg-stone-50 py-1.5 px-3"
                            >
                                <option value="this_month">This Month</option>
                                <option value="last_month">Last Month</option>
                                <option value="this_quarter">This Quarter</option>
                                <option value="this_year">This Year</option>
                                <option value="today">Today</option>
                                <option value="yesterday">Yesterday</option>
                                <option value="all_time">All Time</option>
                            </select>
                        </div>

                        <div>
                            <label className="block text-xs font-medium text-stone-500 mb-1">Tier / Role</label>
                            <select
                                value={role}
                                onChange={(e) => {
                                    setRole(e.target.value);
                                    handleFilterChange(undefined, e.target.value);
                                }}
                                className="text-sm border-stone-300 rounded-lg focus:ring-rose-500 focus:border-rose-500 bg-stone-50 py-1.5 px-3"
                            >
                                <option value="all">All Tiers</option>
                                <option value="high_level_agent">High-Level Agents (HLA)</option>
                                <option value="low_level_agent">Low-Level Agents (LLA)</option>
                            </select>
                        </div>

                        <div className="self-end pb-1 text-xs font-semibold text-stone-400">
                            Range: <span className="text-stone-700">{periodLabel}</span>
                        </div>
                    </div>

                    <form onSubmit={handleSearchSubmit} className="flex items-center gap-2">
                        <div className="relative">
                            <input
                                type="text"
                                placeholder="Search agent name, email, code..."
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                className="text-sm border-stone-300 rounded-lg focus:ring-rose-500 focus:border-rose-500 pl-8 pr-3 py-1.5 w-64"
                            />
                            <Icons.Search className="w-4 h-4 text-stone-400 absolute left-2.5 top-2.5" />
                        </div>
                        <button
                            type="submit"
                            className="px-3 py-1.5 bg-stone-800 text-white rounded-lg text-sm hover:bg-stone-700 transition"
                        >
                            Filter
                        </button>
                    </form>
                </div>

                {/* Company-Wide Performance Summary Cards */}
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div className="bg-white p-5 rounded-xl border border-stone-200 shadow-xs">
                        <div className="flex items-center justify-between text-xs text-stone-500 font-medium">
                            <span>Gross Product Sales</span>
                            <Icons.DollarSign className="w-4 h-4 text-emerald-600" />
                        </div>
                        <div className="mt-2 text-2xl font-bold text-stone-900 font-mono">
                            LKR {Number(companySummary.gross_product_sales).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                        </div>
                        <div className="mt-1 flex items-center justify-between text-xs text-stone-500">
                            <span>Placed Orders: <strong>{companySummary.orders_placed}</strong></span>
                            <span>Placed AOV: <strong>LKR {companySummary.placed_gross_aov}</strong></span>
                        </div>
                    </div>

                    <div className="bg-white p-5 rounded-xl border border-stone-200 shadow-xs">
                        <div className="flex items-center justify-between text-xs text-stone-500 font-medium">
                            <span>Net Product Sales</span>
                            <Icons.TrendingUp className="w-4 h-4 text-rose-600" />
                        </div>
                        <div className="mt-2 text-2xl font-bold text-stone-900 font-mono">
                            LKR {Number(companySummary.net_product_sales).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                        </div>
                        <div className="mt-1 flex items-center justify-between text-xs text-stone-500">
                            <span>Discounts: <strong className="text-amber-700">-LKR {companySummary.discounts_applied}</strong></span>
                            <span>Net AOV: <strong>LKR {companySummary.net_aov}</strong></span>
                        </div>
                    </div>

                    <div className="bg-white p-5 rounded-xl border border-stone-200 shadow-xs">
                        <div className="flex items-center justify-between text-xs text-stone-500 font-medium">
                            <span>Fulfillment & Health</span>
                            <Icons.Package className="w-4 h-4 text-blue-600" />
                        </div>
                        <div className="mt-2 text-2xl font-bold text-stone-900 font-mono">
                            {companySummary.fulfillment_rate}%
                        </div>
                        <div className="mt-1 flex items-center justify-between text-xs text-stone-500">
                            <span>Returns: <strong className="text-rose-600">{companySummary.return_rate}%</strong> ({companySummary.orders_returned})</span>
                            <span>Cancelled: <strong className="text-amber-600">{companySummary.cancellation_friction_rate}%</strong> ({companySummary.orders_cancelled})</span>
                        </div>
                    </div>

                    <div className="bg-white p-5 rounded-xl border border-stone-200 shadow-xs">
                        <div className="flex items-center justify-between text-xs text-stone-500 font-medium">
                            <span>Agent Network Activity</span>
                            <Icons.Users className="w-4 h-4 text-indigo-600" />
                        </div>
                        <div className="mt-2 text-2xl font-bold text-stone-900 font-mono">
                            {companySummary.active_agents_count ?? leaderboard.total}
                        </div>
                        <div className="mt-1 flex items-center justify-between text-xs text-stone-500">
                            <span>Commissions: <strong>LKR {companySummary.approved_commissions}</strong></span>
                            <span className="text-amber-600">Pending: LKR {companySummary.pending_commissions}</span>
                        </div>
                    </div>
                </div>

                {/* Deductions Disclosure Banner */}
                <div className="p-4 bg-stone-50 rounded-xl border border-stone-200 text-xs text-stone-600 flex items-start gap-3">
                    <Icons.AlertCircle className="w-5 h-5 text-stone-400 shrink-0 mt-0.5" />
                    <div className="space-y-1">
                        <p className="font-semibold text-stone-800">
                            Financial Deductions Disclosure (Reconciliation Basis)
                        </p>
                        <p>
                            • <strong>Full-Return Commercial Turnover Reversal:</strong> LKR {companySummary.full_return_order_value} across {companySummary.orders_returned} returned order(s).
                            <em> (Represents gross commercial revenue reversed upon full return, distinct from cash disbursement).</em>
                        </p>
                        <p>
                            • <strong>Fulfilled Partial Refund Items:</strong> LKR {companySummary.fulfilled_partial_refund_amount}.
                            <em> (Represents item-level partial concessions issued on delivered orders).</em>
                        </p>
                    </div>
                </div>

                {/* Agent Leaderboard Table */}
                <div className="bg-white rounded-xl border border-stone-200 shadow-xs overflow-hidden">
                    <div className="px-6 py-4 border-b border-stone-200 flex items-center justify-between">
                        <h2 className="text-base font-semibold text-stone-900 font-serif">
                            Agent Performance Ranking ({leaderboard.total} Total)
                        </h2>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm text-stone-600">
                            <thead className="bg-stone-50 text-xs font-semibold text-stone-600 uppercase tracking-wider border-b border-stone-200">
                                <tr>
                                    <th className="px-6 py-3.5">Rank & Agent</th>
                                    <th className="px-4 py-3.5">Role</th>
                                    <th className="px-4 py-3.5 text-right">Gross Sales</th>
                                    <th className="px-4 py-3.5 text-right">Net Sales</th>
                                    <th className="px-4 py-3.5 text-center">Orders (P / F / R)</th>
                                    <th className="px-4 py-3.5 text-right">Placed AOV</th>
                                    <th className="px-4 py-3.5 text-center">Target Attainment</th>
                                    <th className="px-4 py-3.5 text-right">Commissions</th>
                                    <th className="px-6 py-3.5 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-stone-100">
                                {leaderboard.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={9} className="px-6 py-12 text-center text-stone-400">
                                            No agent performance data found for the selected period and criteria.
                                        </td>
                                    </tr>
                                ) : (
                                    leaderboard.data.map((agent, index) => {
                                        const rank = (leaderboard.current_page - 1) * leaderboard.per_page + index + 1;
                                        const attainment = agent.target_attainment_pct;
                                        return (
                                            <tr key={agent.id} className="hover:bg-stone-50/80 transition-colors">
                                                <td className="px-6 py-4">
                                                    <div className="flex items-center gap-3">
                                                        <span className={`w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold ${
                                                            rank === 1 ? 'bg-amber-100 text-amber-800 border border-amber-300' :
                                                            rank === 2 ? 'bg-stone-200 text-stone-700' :
                                                            rank === 3 ? 'bg-amber-50 text-amber-900' :
                                                            'bg-stone-50 text-stone-500'
                                                        }`}>
                                                            {rank}
                                                        </span>
                                                        <div>
                                                            <Link
                                                                href={`/admin/agents/analytics/${agent.id}?period=${period}`}
                                                                className="font-semibold text-stone-900 hover:text-rose-600 transition"
                                                            >
                                                                {agent.name}
                                                            </Link>
                                                            <div className="text-xs text-stone-400 flex items-center gap-2 mt-0.5">
                                                                <span className="font-mono">{agent.referral_code}</span>
                                                                {agent.parent_agent && (
                                                                    <span>• Spons: {agent.parent_agent.name}</span>
                                                                )}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>

                                                <td className="px-4 py-4">
                                                    <span className={`inline-block px-2 py-0.5 rounded text-[11px] font-semibold ${
                                                        agent.role === 'high_level_agent'
                                                            ? 'bg-purple-100 text-purple-800 border border-purple-200'
                                                            : 'bg-blue-100 text-blue-800 border border-blue-200'
                                                    }`}>
                                                        {agent.role === 'high_level_agent' ? 'HLA' : 'LLA'}
                                                    </span>
                                                </td>

                                                <td className="px-4 py-4 text-right font-mono font-medium text-stone-900">
                                                    LKR {Number(agent.summary.gross_product_sales).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                                </td>

                                                <td className="px-4 py-4 text-right font-mono font-medium text-stone-900">
                                                    LKR {Number(agent.summary.net_product_sales).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                                </td>

                                                <td className="px-4 py-4 text-center font-mono text-xs">
                                                    <span className="text-stone-900">{agent.summary.orders_placed}</span> /{' '}
                                                    <span className="text-emerald-700">{agent.summary.orders_fulfilled}</span> /{' '}
                                                    <span className="text-rose-600">{agent.summary.orders_returned}</span>
                                                </td>

                                                <td className="px-4 py-4 text-right font-mono text-stone-700">
                                                    LKR {agent.summary.placed_gross_aov}
                                                </td>

                                                <td className="px-4 py-4 text-center">
                                                    {agent.sales_target ? (
                                                        <div className="w-32 mx-auto">
                                                            <div className="flex justify-between text-[11px] font-mono text-stone-500 mb-1">
                                                                <span>{attainment !== null ? `${attainment}%` : '0%'}</span>
                                                                <span>LKR {Number(agent.sales_target).toLocaleString()}</span>
                                                            </div>
                                                            <div className="w-full bg-stone-200 rounded-full h-1.5 overflow-hidden">
                                                                <div
                                                                    className={`h-1.5 rounded-full ${
                                                                        (attainment ?? 0) >= 100 ? 'bg-emerald-500' :
                                                                        (attainment ?? 0) >= 70 ? 'bg-blue-500' :
                                                                        'bg-rose-500'
                                                                    }`}
                                                                    style={{ width: `${Math.min(100, attainment ?? 0)}%` }}
                                                                />
                                                            </div>
                                                        </div>
                                                    ) : (
                                                        <span className="text-xs text-stone-400 italic">No Target</span>
                                                    )}
                                                </td>

                                                <td className="px-4 py-4 text-right font-mono text-xs">
                                                    <div className="text-emerald-700 font-semibold">
                                                        LKR {agent.summary.approved_commissions}
                                                    </div>
                                                    {Number(agent.summary.pending_commissions) > 0 && (
                                                        <div className="text-amber-600 text-[10px]">
                                                            +LKR {agent.summary.pending_commissions} pend
                                                        </div>
                                                    )}
                                                </td>

                                                <td className="px-6 py-4 text-right">
                                                    <div className="flex items-center justify-end gap-2">
                                                        {canManageTargets && (
                                                            <button
                                                                onClick={() => openTargetModal(agent)}
                                                                className="px-2.5 py-1 text-xs font-medium text-stone-700 bg-stone-100 hover:bg-stone-200 rounded-md transition"
                                                                title="Set or revise monthly sales target"
                                                            >
                                                                Target
                                                            </button>
                                                        )}
                                                        <Link
                                                            href={`/admin/agents/analytics/${agent.id}?period=${period}`}
                                                            className="px-2.5 py-1 text-xs font-semibold text-rose-700 bg-rose-50 hover:bg-rose-100 rounded-md transition"
                                                        >
                                                            Inspect &rarr;
                                                        </Link>
                                                    </div>
                                                </td>
                                            </tr>
                                        );
                                    })
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Pagination */}
                    {leaderboard.last_page > 1 && (
                        <div className="px-6 py-4 border-t border-stone-200 flex items-center justify-between text-xs text-stone-500">
                            <div>
                                Showing {leaderboard.from ?? 0} to {leaderboard.to ?? 0} of {leaderboard.total} agents
                            </div>
                            <div className="flex gap-1">
                                {leaderboard.links.map((link, idx) => (
                                    <Link
                                        key={idx}
                                        href={link.url || '#'}
                                        preserveScroll
                                        className={`px-3 py-1.5 rounded border text-xs font-medium transition ${
                                            link.active
                                                ? 'bg-rose-600 text-white border-rose-600'
                                                : link.url
                                                ? 'bg-white text-stone-700 border-stone-300 hover:bg-stone-50'
                                                : 'bg-stone-100 text-stone-400 border-stone-200 cursor-not-allowed'
                                        }`}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                    />
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            </div>

            {/* Target Setting Modal */}
            {targetModalOpen && selectedAgentForTarget && (
                <div className="fixed inset-0 z-50 overflow-y-auto bg-stone-900/60 backdrop-blur-xs flex items-center justify-center p-4">
                    <div className="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-stone-200 space-y-5 animate-in fade-in duration-150">
                        <div className="flex items-center justify-between border-b border-stone-100 pb-3">
                            <h3 className="font-serif text-lg font-bold text-stone-900">
                                Set Monthly Sales Target
                            </h3>
                            <button
                                onClick={() => setTargetModalOpen(false)}
                                className="text-stone-400 hover:text-stone-600 text-xl font-semibold"
                            >
                                &times;
                            </button>
                        </div>

                        <div className="text-xs text-stone-600 bg-stone-50 p-3 rounded-lg border border-stone-200 space-y-1">
                            <div><strong>Agent:</strong> {selectedAgentForTarget.name} ({selectedAgentForTarget.referral_code})</div>
                            <div><strong>Role:</strong> {selectedAgentForTarget.role === 'high_level_agent' ? 'High-Level Agent' : 'Low-Level Agent'}</div>
                        </div>

                        <form onSubmit={handleTargetSubmit} className="space-y-4">
                            <div>
                                <label className="block text-xs font-semibold text-stone-700 uppercase tracking-wider mb-1">
                                    Target Month (YYYY-MM) *
                                </label>
                                <input
                                    type="month"
                                    value={targetForm.month}
                                    onChange={(e) => setTargetForm('month', e.target.value)}
                                    className="w-full text-sm border-stone-300 rounded-lg focus:ring-rose-500 focus:border-rose-500 font-mono"
                                    required
                                />
                                {errors.month && (
                                    <p className="mt-1 text-xs text-rose-600">{errors.month}</p>
                                )}
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-stone-700 uppercase tracking-wider mb-1">
                                    Monthly Sales Target (LKR) *
                                </label>
                                <div className="relative">
                                    <span className="absolute left-3 top-2 text-sm text-stone-400 font-mono">LKR</span>
                                    <input
                                        type="number"
                                        step="0.01"
                                        min="0.01"
                                        placeholder="100000.00"
                                        value={targetForm.sales_target}
                                        onChange={(e) => setTargetForm('sales_target', e.target.value)}
                                        className="w-full text-sm border-stone-300 rounded-lg focus:ring-rose-500 focus:border-rose-500 pl-14 font-mono font-medium"
                                        required
                                    />
                                </div>
                                <p className="mt-1 text-[11px] text-stone-500">
                                    Must be a positive value greater than zero. Updates are tracked in audit logs.
                                </p>
                                {errors.sales_target && (
                                    <p className="mt-1 text-xs text-rose-600">{errors.sales_target}</p>
                                )}
                            </div>

                            <div className="flex items-center justify-end gap-3 pt-3 border-t border-stone-100">
                                <button
                                    type="button"
                                    onClick={() => setTargetModalOpen(false)}
                                    className="px-4 py-2 text-xs font-medium text-stone-600 hover:text-stone-800"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded-lg text-xs font-semibold transition disabled:opacity-50"
                                >
                                    {processing ? 'Saving...' : 'Save Target'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AdminLayout>
    );
}

