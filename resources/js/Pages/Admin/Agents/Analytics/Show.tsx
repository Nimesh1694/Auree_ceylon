import React, { useState } from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import * as Icons from '@/Components/Icons';
import { PageProps } from '@/types';
import { LeaderboardSummary } from './Index';

export interface DailyTrendPoint {
    date: string;
    orders_placed: number;
    gross_sales: string;
    discounts: string;
    net_sales: string;
    orders_fulfilled: number;
    orders_returned: number;
    orders_cancelled: number;
}

interface Props extends PageProps {
    agent: {
        id: number;
        name: string;
        email: string;
        referral_code: string;
        role: string;
        parent_agent: { id: number; name: string; referral_code: string } | null;
        agent_profile: { kyc_status: string; wallet_balance: number } | null;
    };
    period: string;
    periodLabel: string;
    personal: LeaderboardSummary;
    downline: LeaderboardSummary | null;
    dailyTrends: DailyTrendPoint[];
    salesTarget: {
        id: number;
        month: string;
        sales_target: string;
        created_at: string;
        updated_at: string;
        creator: { id: number; name: string } | null;
    } | null;
    canManageTargets: boolean;
}

export default function Show({
    agent,
    period,
    periodLabel,
    personal,
    downline,
    dailyTrends,
    salesTarget,
    canManageTargets,
}: Props) {
    const [activeTab, setActiveTab] = useState<'personal' | 'downline' | 'trends'>('personal');
    const [targetModalOpen, setTargetModalOpen] = useState(false);

    const { data: targetForm, setData: setTargetForm, post, put, processing, errors } = useForm({
        agent_id: agent.id,
        month: salesTarget?.month || new Date().toISOString().slice(0, 7),
        sales_target: salesTarget?.sales_target || '',
    });

    const handlePeriodChange = (newPeriod: string) => {
        router.get(
            `/admin/agents/analytics/${agent.id}`,
            { period: newPeriod },
            { preserveState: true, preserveScroll: true }
        );
    };

    const handleTargetSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (salesTarget) {
            put(`/admin/agents/analytics/targets/${salesTarget.id}`, {
                preserveScroll: true,
                onSuccess: () => setTargetModalOpen(false),
            });
        } else {
            post('/admin/agents/analytics/targets', {
                preserveScroll: true,
                onSuccess: () => setTargetModalOpen(false),
            });
        }
    };

    const attainment = personal.target_attainment_pct;

    return (
        <AdminLayout>
            <Head title={`${agent.name} - Performance Analytics | Aura Luxe Administration`} />

            <div className="space-y-6">
                {/* Back Link & Header */}
                <div>
                    <Link
                        href="/admin/agents/analytics"
                        className="inline-flex items-center gap-1.5 text-xs font-medium text-stone-500 hover:text-stone-900 transition mb-3"
                    >
                        <Icons.ArrowLeft className="w-3.5 h-3.5" />
                        Back to Agent Performance Leaderboard
                    </Link>

                    <div className="bg-white p-6 rounded-2xl border border-stone-200 shadow-xs flex flex-col md:flex-row md:items-center justify-between gap-6">
                        <div className="flex items-start gap-4">
                            <div className="w-14 h-14 rounded-2xl bg-rose-50 border border-rose-200 flex items-center justify-center font-serif text-2xl font-bold text-rose-800 shrink-0">
                                {agent.name.charAt(0)}
                            </div>
                            <div>
                                <div className="flex flex-wrap items-center gap-2">
                                    <h1 className="text-2xl font-serif font-bold text-stone-900">
                                        {agent.name}
                                    </h1>
                                    <span className={`px-2.5 py-0.5 rounded-full text-xs font-semibold ${
                                        agent.role === 'high_level_agent'
                                            ? 'bg-purple-100 text-purple-800 border border-purple-200'
                                            : 'bg-blue-100 text-blue-800 border border-blue-200'
                                    }`}>
                                        {agent.role === 'high_level_agent' ? 'High-Level Agent (HLA)' : 'Low-Level Agent (LLA)'}
                                    </span>
                                    {agent.agent_profile && (
                                        <span className={`px-2 py-0.5 rounded text-[11px] font-semibold uppercase ${
                                            agent.agent_profile.kyc_status === 'verified'
                                                ? 'bg-emerald-100 text-emerald-800'
                                                : 'bg-amber-100 text-amber-800'
                                        }`}>
                                            KYC: {agent.agent_profile.kyc_status}
                                        </span>
                                    )}
                                </div>

                                <div className="mt-2 flex flex-wrap items-center gap-4 text-xs text-stone-500">
                                    <span>Email: <strong className="text-stone-800">{agent.email}</strong></span>
                                    <span>Referral Code: <strong className="font-mono text-rose-700 bg-rose-50 px-1.5 py-0.5 rounded">{agent.referral_code}</strong></span>
                                    {agent.parent_agent && (
                                        <span>
                                            Sponsor: <Link href={`/admin/agents/analytics/${agent.parent_agent.id}`} className="text-rose-700 hover:underline font-semibold">{agent.parent_agent.name}</Link> ({agent.parent_agent.referral_code})
                                        </span>
                                    )}
                                    {agent.agent_profile && (
                                        <span>Wallet Balance: <strong className="text-stone-800">LKR {Number(agent.agent_profile.wallet_balance).toFixed(2)}</strong></span>
                                    )}
                                </div>
                            </div>
                        </div>

                        {/* Period Selector */}
                        <div className="flex items-center gap-3">
                            <div className="text-right">
                                <label className="block text-xs font-medium text-stone-400">Analysis Period</label>
                                <select
                                    value={period}
                                    onChange={(e) => handlePeriodChange(e.target.value)}
                                    className="text-sm border-stone-300 rounded-lg focus:ring-rose-500 focus:border-rose-500 bg-stone-50 py-1.5 px-3 font-medium text-stone-800"
                                >
                                    <option value="this_month">This Month</option>
                                    <option value="last_month">Last Month</option>
                                    <option value="this_quarter">This Quarter</option>
                                    <option value="this_year">This Year</option>
                                    <option value="today">Today</option>
                                    <option value="yesterday">Yesterday</option>
                                    <option value="all_time">All Time</option>
                                </select>
                                <div className="text-[11px] text-stone-400 mt-1">{periodLabel}</div>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Monthly Quota Attainment Card */}
                <div className="bg-white p-6 rounded-2xl border border-stone-200 shadow-xs">
                    <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div>
                            <div className="flex items-center gap-2">
                                <h3 className="font-serif font-bold text-base text-stone-900">
                                    Monthly Sales Quota & Attainment
                                </h3>
                                <span className="text-xs text-stone-500 font-mono">({salesTarget?.month || periodLabel})</span>
                            </div>
                            <p className="text-xs text-stone-500 mt-0.5">
                                Performance benchmark calculated against verified commercial turnover ($P_{'{'}commercial{'}'}$).
                            </p>
                        </div>

                        {canManageTargets && (
                            <button
                                onClick={() => {
                                    setTargetForm({
                                        agent_id: agent.id,
                                        month: salesTarget?.month || new Date().toISOString().slice(0, 7),
                                        sales_target: salesTarget?.sales_target || '',
                                    });
                                    setTargetModalOpen(true);
                                }}
                                className="px-3 py-1.5 bg-stone-900 hover:bg-stone-800 text-white rounded-lg text-xs font-semibold transition"
                            >
                                {salesTarget ? 'Edit Quota Target' : '+ Assign Sales Target'}
                            </button>
                        )}
                    </div>

                    <div className="mt-5 grid grid-cols-1 sm:grid-cols-3 gap-6 pt-4 border-t border-stone-100">
                        <div>
                            <span className="text-xs text-stone-500 block">Assigned Target</span>
                            <span className="text-xl font-bold font-mono text-stone-900 mt-1 block">
                                {salesTarget ? `LKR ${Number(salesTarget.sales_target).toLocaleString('en-US', { minimumFractionDigits: 2 })}` : 'Not Set'}
                            </span>
                            {salesTarget?.creator && (
                                <span className="text-[11px] text-stone-400 block mt-0.5">
                                    Set by {salesTarget.creator.name}
                                </span>
                            )}
                        </div>

                        <div>
                            <span className="text-xs text-stone-500 block">Commercial Sales Achieved</span>
                            <span className="text-xl font-bold font-mono text-stone-900 mt-1 block">
                                LKR {Number(personal.gross_product_sales).toLocaleString('en-US', { minimumFractionDigits: 2 })}
                            </span>
                            <span className="text-[11px] text-stone-400 block mt-0.5">
                                Across {personal.orders_commercial} commercial orders
                            </span>
                        </div>

                        <div>
                            <div className="flex justify-between text-xs text-stone-500 mb-1">
                                <span>Attainment Rate</span>
                                <span className="font-bold text-stone-800">{attainment !== null ? `${attainment}%` : 'N/A'}</span>
                            </div>
                            <div className="w-full bg-stone-100 rounded-full h-2.5 overflow-hidden">
                                <div
                                    className={`h-2.5 rounded-full transition-all duration-500 ${
                                        (attainment ?? 0) >= 100 ? 'bg-emerald-500' :
                                        (attainment ?? 0) >= 70 ? 'bg-blue-500' :
                                        'bg-rose-500'
                                    }`}
                                    style={{ width: `${Math.min(100, attainment ?? 0)}%` }}
                                />
                            </div>
                            <span className="text-[11px] text-stone-400 block mt-1">
                                {(attainment ?? 0) >= 100 ? 'Target achieved! 🎉' : 'In progress towards monthly target.'}
                            </span>
                        </div>
                    </div>
                </div>

                {/* Tabs */}
                <div className="flex border-b border-stone-200 gap-6 text-sm font-medium">
                    <button
                        onClick={() => setActiveTab('personal')}
                        className={`pb-3 border-b-2 transition ${
                            activeTab === 'personal'
                                ? 'border-rose-600 text-rose-800 font-semibold'
                                : 'border-transparent text-stone-500 hover:text-stone-800'
                        }`}
                    >
                        Personal Metrics
                    </button>
                    {agent.role === 'high_level_agent' && downline && (
                        <button
                            onClick={() => setActiveTab('downline')}
                            className={`pb-3 border-b-2 transition flex items-center gap-2 ${
                                activeTab === 'downline'
                                    ? 'border-rose-600 text-rose-800 font-semibold'
                                    : 'border-transparent text-stone-500 hover:text-stone-800'
                            }`}
                        >
                            <span>Downline Team Network</span>
                            <span className="text-[10px] bg-purple-100 text-purple-800 px-2 py-0.5 rounded-full font-bold">
                                HLA Exclusive
                            </span>
                        </button>
                    )}
                    <button
                        onClick={() => setActiveTab('trends')}
                        className={`pb-3 border-b-2 transition ${
                            activeTab === 'trends'
                                ? 'border-rose-600 text-rose-800 font-semibold'
                                : 'border-transparent text-stone-500 hover:text-stone-800'
                        }`}
                    >
                        Daily Trends ({dailyTrends.length} Days)
                    </button>
                </div>

                {/* Tab: Personal Performance Metrics */}
                {activeTab === 'personal' && (
                    <div className="space-y-6">
                        {/* KPI Cards */}
                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                            <div className="bg-white p-5 rounded-xl border border-stone-200 shadow-xs">
                                <span className="text-xs text-stone-500 font-medium">Gross Product Sales</span>
                                <div className="mt-2 text-2xl font-bold font-mono text-stone-900">
                                    LKR {Number(personal.gross_product_sales).toLocaleString('en-US', { minimumFractionDigits: 2 })}
                                </div>
                                <div className="mt-2 text-xs text-stone-500 flex justify-between">
                                    <span>Placed AOV:</span>
                                    <strong className="text-stone-800">LKR {personal.placed_gross_aov}</strong>
                                </div>
                                <div className="text-xs text-stone-500 flex justify-between">
                                    <span>Commercial AOV:</span>
                                    <strong className="text-stone-800">LKR {personal.commercial_gross_aov}</strong>
                                </div>
                            </div>

                            <div className="bg-white p-5 rounded-xl border border-stone-200 shadow-xs">
                                <span className="text-xs text-stone-500 font-medium">Net Product Sales</span>
                                <div className="mt-2 text-2xl font-bold font-mono text-stone-900">
                                    LKR {Number(personal.net_product_sales).toLocaleString('en-US', { minimumFractionDigits: 2 })}
                                </div>
                                <div className="mt-2 text-xs text-stone-500 flex justify-between">
                                    <span>Discounts Applied:</span>
                                    <strong className="text-amber-700">-LKR {personal.discounts_applied}</strong>
                                </div>
                                <div className="text-xs text-stone-500 flex justify-between">
                                    <span>Net AOV:</span>
                                    <strong className="text-stone-800">LKR {personal.net_aov}</strong>
                                </div>
                            </div>

                            <div className="bg-white p-5 rounded-xl border border-stone-200 shadow-xs">
                                <span className="text-xs text-stone-500 font-medium">Operational Health</span>
                                <div className="mt-2 text-2xl font-bold font-mono text-stone-900">
                                    {personal.fulfillment_rate}%
                                </div>
                                <div className="mt-2 text-xs text-stone-500 flex justify-between">
                                    <span>Overall Return Rate:</span>
                                    <strong className="text-rose-600">{personal.return_rate}%</strong>
                                </div>
                                <div className="text-xs text-stone-500 flex justify-between">
                                    <span>Dispatched Return Rate:</span>
                                    <strong className="text-rose-600">{personal.dispatched_return_rate}%</strong>
                                </div>
                                <div className="text-xs text-stone-500 flex justify-between">
                                    <span>Cancellation Rate:</span>
                                    <strong className="text-amber-600">{personal.cancellation_friction_rate}%</strong>
                                </div>
                            </div>

                            <div className="bg-white p-5 rounded-xl border border-stone-200 shadow-xs">
                                <span className="text-xs text-stone-500 font-medium">Commissions Overview</span>
                                <div className="mt-2 text-2xl font-bold font-mono text-emerald-700">
                                    LKR {Number(personal.approved_commissions).toLocaleString('en-US', { minimumFractionDigits: 2 })}
                                </div>
                                <div className="mt-2 text-xs text-stone-500 flex justify-between">
                                    <span>Pending Active:</span>
                                    <strong className="text-amber-600">LKR {personal.pending_commissions}</strong>
                                </div>
                                <div className="text-xs text-stone-500 flex justify-between">
                                    <span>Reversed:</span>
                                    <strong className="text-rose-600">LKR {personal.reversed_commissions}</strong>
                                </div>
                            </div>
                        </div>

                        {/* Order Volumes & Deductions Breakdown */}
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div className="bg-white p-6 rounded-xl border border-stone-200 shadow-xs space-y-4">
                                <h4 className="font-serif font-bold text-sm text-stone-900">
                                    Order Population Volumes
                                </h4>
                                <div className="divide-y divide-stone-100 text-xs">
                                    <div className="py-2.5 flex justify-between">
                                        <span className="text-stone-600">Placed Orders ($P_{'{'}placed{'}'}$):</span>
                                        <span className="font-mono font-semibold text-stone-900">{personal.orders_placed}</span>
                                    </div>
                                    <div className="py-2.5 flex justify-between">
                                        <span className="text-stone-600">Commercial Orders ($P_{'{'}commercial{'}'}$):</span>
                                        <span className="font-mono font-semibold text-stone-900">{personal.orders_commercial}</span>
                                    </div>
                                    <div className="py-2.5 flex justify-between">
                                        <span className="text-stone-600">Fulfilled Orders ($P_{'{'}fulfilled{'}'}$):</span>
                                        <span className="font-mono font-semibold text-emerald-700">{personal.orders_fulfilled}</span>
                                    </div>
                                    <div className="py-2.5 flex justify-between">
                                        <span className="text-stone-600">Full Returns (Returned Orders):</span>
                                        <span className="font-mono font-semibold text-rose-600">{personal.orders_returned}</span>
                                    </div>
                                    <div className="py-2.5 flex justify-between">
                                        <span className="text-stone-600">Cancelled Orders:</span>
                                        <span className="font-mono font-semibold text-amber-600">{personal.orders_cancelled}</span>
                                    </div>
                                </div>
                            </div>

                            <div className="bg-white p-6 rounded-xl border border-stone-200 shadow-xs space-y-4">
                                <h4 className="font-serif font-bold text-sm text-stone-900">
                                    Financial Deductions Breakdown
                                </h4>
                                <div className="divide-y divide-stone-100 text-xs">
                                    <div className="py-2.5 flex justify-between">
                                        <span className="text-stone-600">Full-Return Order Turnover Reversal:</span>
                                        <span className="font-mono font-semibold text-stone-900">LKR {personal.full_return_order_value}</span>
                                    </div>
                                    <div className="py-2.5 flex justify-between">
                                        <span className="text-stone-600">Fulfilled Item Partial Refunds:</span>
                                        <span className="font-mono font-semibold text-stone-900">LKR {personal.fulfilled_partial_refund_amount}</span>
                                    </div>
                                    <div className="py-2.5 flex justify-between bg-stone-50 px-2 rounded font-semibold">
                                        <span className="text-stone-800">Total Deductions Combined:</span>
                                        <span className="font-mono text-rose-700">LKR {Number(personal.total_deductions_cents).toFixed(2)}</span>
                                    </div>
                                </div>
                                <p className="text-[11px] text-stone-400 italic">
                                    * Full-Return Order Value represents gross commercial revenue reversed upon complete order return, while Item Partial Refunds are product returns issued on otherwise fulfilled orders.
                                </p>
                            </div>
                        </div>
                    </div>
                )}

                {/* Tab: Downline Team Network (HLA Only) */}
                {activeTab === 'downline' && downline && (
                    <div className="space-y-6">
                        <div className="bg-purple-50/50 p-4 rounded-xl border border-purple-200 text-xs text-purple-900 flex items-center justify-between">
                            <div>
                                <span className="font-bold">HLA Downline Team Performance Overview</span>
                                <p className="text-purple-700 mt-0.5">
                                    Aggregate production of all Low-Level Agents sponsored directly or indirectly by {agent.name}.
                                </p>
                            </div>
                            <span className="font-mono font-bold text-sm bg-purple-200 px-3 py-1 rounded-lg">
                                {downline.active_agents_count} Active LLA(s)
                            </span>
                        </div>

                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                            <div className="bg-white p-5 rounded-xl border border-stone-200 shadow-xs">
                                <span className="text-xs text-stone-500 font-medium">Downline Gross Sales</span>
                                <div className="mt-2 text-2xl font-bold font-mono text-stone-900">
                                    LKR {Number(downline.gross_product_sales).toLocaleString('en-US', { minimumFractionDigits: 2 })}
                                </div>
                                <div className="mt-2 text-xs text-stone-500">
                                    Placed Orders: <strong>{downline.orders_placed}</strong>
                                </div>
                            </div>

                            <div className="bg-white p-5 rounded-xl border border-stone-200 shadow-xs">
                                <span className="text-xs text-stone-500 font-medium">Downline Net Sales</span>
                                <div className="mt-2 text-2xl font-bold font-mono text-stone-900">
                                    LKR {Number(downline.net_product_sales).toLocaleString('en-US', { minimumFractionDigits: 2 })}
                                </div>
                                <div className="mt-2 text-xs text-stone-500">
                                    Discounts: <strong className="text-amber-700">-LKR {downline.discounts_applied}</strong>
                                </div>
                            </div>

                            <div className="bg-white p-5 rounded-xl border border-stone-200 shadow-xs">
                                <span className="text-xs text-stone-500 font-medium">Downline Placed AOV</span>
                                <div className="mt-2 text-2xl font-bold font-mono text-stone-900">
                                    LKR {downline.placed_gross_aov}
                                </div>
                                <div className="mt-2 text-xs text-stone-500">
                                    Commercial AOV: <strong>LKR {downline.commercial_gross_aov}</strong>
                                </div>
                            </div>

                            <div className="bg-white p-5 rounded-xl border border-stone-200 shadow-xs">
                                <span className="text-xs text-stone-500 font-medium">Downline Approved Commissions</span>
                                <div className="mt-2 text-2xl font-bold font-mono text-emerald-700">
                                    LKR {Number(downline.approved_commissions).toLocaleString('en-US', { minimumFractionDigits: 2 })}
                                </div>
                                <div className="mt-2 text-xs text-stone-500">
                                    Fulfillment: <strong>{downline.fulfillment_rate}%</strong>
                                </div>
                            </div>
                        </div>
                    </div>
                )}

                {/* Tab: Daily Trends */}
                {activeTab === 'trends' && (
                    <div className="bg-white rounded-xl border border-stone-200 shadow-xs overflow-hidden">
                        <div className="px-6 py-4 border-b border-stone-200">
                            <h3 className="font-serif font-bold text-base text-stone-900">
                                Daily Sales & Fulfillment Breakdown
                            </h3>
                        </div>

                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-sm text-stone-600">
                                <thead className="bg-stone-50 text-xs font-semibold text-stone-600 uppercase border-b border-stone-200">
                                    <tr>
                                        <th className="px-6 py-3">Date</th>
                                        <th className="px-4 py-3 text-center">Placed</th>
                                        <th className="px-4 py-3 text-right">Gross Sales</th>
                                        <th className="px-4 py-3 text-right">Discounts</th>
                                        <th className="px-4 py-3 text-right">Net Sales</th>
                                        <th className="px-4 py-3 text-center">Fulfilled</th>
                                        <th className="px-4 py-3 text-center">Returned</th>
                                        <th className="px-4 py-3 text-center">Cancelled</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-stone-100 font-mono text-xs">
                                    {dailyTrends.length === 0 ? (
                                        <tr>
                                            <td colSpan={8} className="px-6 py-8 text-center text-stone-400 font-sans">
                                                No daily activity recorded for this period.
                                            </td>
                                        </tr>
                                    ) : (
                                        dailyTrends.map((d) => (
                                            <tr key={d.date} className="hover:bg-stone-50">
                                                <td className="px-6 py-3 font-sans font-medium text-stone-800">{d.date}</td>
                                                <td className="px-4 py-3 text-center">{d.orders_placed}</td>
                                                <td className="px-4 py-3 text-right font-medium text-stone-900">LKR {d.gross_sales}</td>
                                                <td className="px-4 py-3 text-right text-amber-700">LKR {d.discounts}</td>
                                                <td className="px-4 py-3 text-right font-medium text-stone-900">LKR {d.net_sales}</td>
                                                <td className="px-4 py-3 text-center text-emerald-700">{d.orders_fulfilled}</td>
                                                <td className="px-4 py-3 text-center text-rose-600">{d.orders_returned}</td>
                                                <td className="px-4 py-3 text-center text-stone-500">{d.orders_cancelled}</td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}
            </div>

            {/* Target Modal */}
            {targetModalOpen && (
                <div className="fixed inset-0 z-50 bg-stone-900/60 backdrop-blur-xs flex items-center justify-center p-4">
                    <div className="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-stone-200 space-y-5">
                        <div className="flex items-center justify-between border-b border-stone-100 pb-3">
                            <h3 className="font-serif text-lg font-bold text-stone-900">
                                {salesTarget ? 'Update Monthly Target' : 'Create Monthly Target'}
                            </h3>
                            <button
                                onClick={() => setTargetModalOpen(false)}
                                className="text-stone-400 hover:text-stone-600 text-xl font-semibold"
                            >
                                &times;
                            </button>
                        </div>

                        <form onSubmit={handleTargetSubmit} className="space-y-4">
                            <div>
                                <label className="block text-xs font-semibold text-stone-700 uppercase mb-1">
                                    Target Month (YYYY-MM) *
                                </label>
                                <input
                                    type="month"
                                    value={targetForm.month}
                                    onChange={(e) => setTargetForm('month', e.target.value)}
                                    className="w-full text-sm border-stone-300 rounded-lg focus:ring-rose-500 font-mono"
                                    required
                                    disabled={!!salesTarget}
                                />
                                {errors.month && <p className="mt-1 text-xs text-rose-600">{errors.month}</p>}
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-stone-700 uppercase mb-1">
                                    Sales Target (LKR) *
                                </label>
                                <div className="relative">
                                    <span className="absolute left-3 top-2 text-sm text-stone-400 font-mono">LKR</span>
                                    <input
                                        type="number"
                                        step="0.01"
                                        min="0.01"
                                        value={targetForm.sales_target}
                                        onChange={(e) => setTargetForm('sales_target', e.target.value)}
                                        className="w-full text-sm border-stone-300 rounded-lg focus:ring-rose-500 pl-14 font-mono font-medium"
                                        required
                                    />
                                </div>
                                {errors.sales_target && <p className="mt-1 text-xs text-rose-600">{errors.sales_target}</p>}
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

