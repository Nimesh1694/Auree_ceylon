import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AgentLayout from '@/Layouts/AgentLayout';
import * as Icons from '@/Components/Icons';
import { PageProps } from '@/types';

export interface AnalyticsSummary {
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

export interface DailyTrendItem {
    date: string;
    orders_placed: number;
    gross_sales: string;
    discounts: string;
    net_sales: string;
    orders_fulfilled: number;
    orders_returned: number;
    orders_cancelled: number;
}

export interface DownlineTargetInfo {
    id: number;
    target_month: string;
    sales_target: string;
    sales_target_formatted: string;
    created_by: number;
    is_management_controlled: boolean;
    can_edit: boolean;
}

export interface DownlineMember {
    id: number;
    name: string;
    email: string;
    referral_code: string;
    role: string;
    status: string;
    parent_id: number | null;
    depth: number;
    metrics: AnalyticsSummary;
    target: DownlineTargetInfo | null;
}

interface Props extends PageProps {
    period: string;
    periodLabel: string;
    personal: AnalyticsSummary;
    downline: AnalyticsSummary | null;
    downlineMembers?: DownlineMember[];
    dailyTrends: DailyTrendItem[];
    isHla: boolean;
    currentMonth?: string;
}

export default function Index({
    period,
    periodLabel,
    personal,
    downline,
    downlineMembers = [],
    dailyTrends,
    isHla,
    currentMonth,
}: Props) {
    const [selectedPeriod, setSelectedPeriod] = useState(period || 'this_month');
    const [activeTab, setActiveTab] = useState<'personal' | 'downline' | 'trends'>('personal');

    // Downline Target Assignment / Update Modal State
    const [targetModalOpen, setTargetModalOpen] = useState(false);
    const [selectedMember, setSelectedMember] = useState<DownlineMember | null>(null);
    const [targetForm, setTargetForm] = useState({
        agent_id: 0,
        target_month: currentMonth || new Date().toISOString().slice(0, 7),
        sales_target: '',
        reason: '',
    });
    const [formErrors, setFormErrors] = useState<Record<string, string>>({});
    const [isSubmitting, setIsSubmitting] = useState(false);

    const handlePeriodChange = (newPeriod: string) => {
        setSelectedPeriod(newPeriod);
        router.get(
            '/agent/analytics',
            { period: newPeriod },
            { preserveState: true, preserveScroll: true }
        );
    };

    const openAssignModal = (member: DownlineMember) => {
        setSelectedMember(member);
        setTargetForm({
            agent_id: member.id,
            target_month: member.target ? member.target.target_month : (currentMonth || new Date().toISOString().slice(0, 7)),
            sales_target: member.target ? member.target.sales_target : '',
            reason: '',
        });
        setFormErrors({});
        setTargetModalOpen(true);
    };

    const handleTargetSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!selectedMember) return;

        setIsSubmitting(true);
        setFormErrors({});

        const isEditing = !!selectedMember.target;
        const url = isEditing
            ? `/agent/team/targets/${selectedMember.target!.id}`
            : '/agent/team/targets';

        const method = isEditing ? 'put' : 'post';

        router[method](
            url,
            {
                agent_id: selectedMember.id,
                target_month: targetForm.target_month,
                sales_target: targetForm.sales_target,
                reason: targetForm.reason,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setTargetModalOpen(false);
                    setSelectedMember(null);
                    setIsSubmitting(false);
                },
                onError: (errors: Record<string, string>) => {
                    setFormErrors(errors);
                    setIsSubmitting(false);
                },
            }
        );
    };

    const attainment = personal.target_attainment_pct;

    return (
        <AgentLayout>
            <Head title="Performance Analytics | Aura Luxe Agent Portal" />

            <div className="space-y-6">
                {/* Header Banner */}
                <div className="bg-white p-6 rounded-2xl border border-stone-200 shadow-xs flex flex-col md:flex-row md:items-center justify-between gap-6">
                    <div>
                        <div className="flex items-center gap-2">
                            <span className="text-[11px] text-rose-800 uppercase tracking-widest font-semibold block">
                                Performance Intelligence
                            </span>
                            {isHla && (
                                <span className="text-[10px] font-bold px-2 py-0.5 rounded-full bg-purple-100 text-purple-800 border border-purple-200">
                                    High-Level Agent
                                </span>
                            )}
                        </div>
                        <h1 className="font-serif text-2xl sm:text-3xl font-semibold text-stone-900 mt-1">
                            Performance Analytics
                        </h1>
                        <p className="text-xs text-stone-500 mt-1">
                            Real-time sales velocity, order fulfillment health, and commission earnings breakdown.
                        </p>
                    </div>

                    {/* Period Selector */}
                    <div className="flex items-center gap-2 self-start md:self-auto">
                        <select
                            value={selectedPeriod}
                            onChange={(e) => handlePeriodChange(e.target.value)}
                            className="text-xs font-medium border-stone-300 rounded-lg shadow-2xs focus:border-rose-600 focus:ring-rose-600 bg-white"
                        >
                            <option value="today">Today</option>
                            <option value="this_week">This Week</option>
                            <option value="this_month">This Month</option>
                            <option value="last_month">Last Month</option>
                            <option value="this_quarter">This Quarter</option>
                            <option value="this_year">This Year</option>
                            <option value="all_time">All Time</option>
                        </select>
                        <span className="text-[11px] text-stone-400 font-mono hidden sm:inline">
                            {periodLabel}
                        </span>
                    </div>
                </div>

                {/* Monthly Target Attainment Banner */}
                {attainment !== null && attainment !== undefined && (
                    <div className="bg-gradient-to-r from-rose-900 via-rose-800 to-stone-900 text-white p-6 rounded-2xl shadow-sm">
                        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                            <div>
                                <span className="text-[10px] uppercase tracking-widest text-rose-200 font-semibold">
                                    Monthly Sales Target Attainment
                                </span>
                                <div className="flex items-baseline gap-3 mt-1">
                                    <h2 className="text-3xl sm:text-4xl font-mono font-bold">
                                        {attainment}%
                                    </h2>
                                    <span className="text-xs text-rose-200">
                                        {attainment >= 100
                                            ? 'Goal Achieved! Outstanding performance this month.'
                                            : attainment >= 75
                                            ? 'Pacing strong towards monthly target.'
                                            : 'Focus on commercial conversion to hit quota.'}
                                    </span>
                                </div>
                            </div>
                            <div className="w-full sm:w-48 bg-white/20 rounded-full h-3 overflow-hidden">
                                <div
                                    className={`h-full rounded-full transition-all duration-500 ${
                                        attainment >= 100
                                            ? 'bg-emerald-400'
                                            : attainment >= 75
                                            ? 'bg-amber-300'
                                            : 'bg-rose-400'
                                    }`}
                                    style={{ width: `${Math.min(100, Math.max(0, attainment))}%` }}
                                />
                            </div>
                        </div>
                    </div>
                )}

                {/* Tabs */}
                <div className="flex border-b border-stone-200 gap-6 text-xs font-semibold tracking-wide uppercase">
                    <button
                        onClick={() => setActiveTab('personal')}
                        className={`pb-3 border-b-2 transition ${
                            activeTab === 'personal'
                                ? 'border-rose-600 text-rose-900'
                                : 'border-transparent text-stone-500 hover:text-stone-800'
                        }`}
                    >
                        My Performance
                    </button>
                    {isHla && downline && (
                        <button
                            onClick={() => setActiveTab('downline')}
                            className={`pb-3 border-b-2 transition flex items-center gap-1.5 ${
                                activeTab === 'downline'
                                    ? 'border-purple-600 text-purple-900'
                                    : 'border-transparent text-stone-500 hover:text-stone-800'
                            }`}
                        >
                            <span>Team Downline Network</span>
                            <span className="px-1.5 py-0.2 rounded bg-purple-100 text-purple-800 text-[10px]">
                                {downline.active_agents_count} LLA(s)
                            </span>
                        </button>
                    )}
                    <button
                        onClick={() => setActiveTab('trends')}
                        className={`pb-3 border-b-2 transition ${
                            activeTab === 'trends'
                                ? 'border-rose-600 text-rose-900'
                                : 'border-transparent text-stone-500 hover:text-stone-800'
                        }`}
                    >
                        Daily Trends ({dailyTrends.length} Days)
                    </button>
                </div>

                {/* Tab: Personal Performance */}
                {activeTab === 'personal' && (
                    <div className="space-y-6">
                        {/* KPI Grid */}
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
                                <span className="text-xs text-stone-500 font-medium">Order Delivery & Health</span>
                                <div className="mt-2 text-2xl font-bold font-mono text-stone-900">
                                    {personal.fulfillment_rate}%
                                </div>
                                <div className="mt-2 text-xs text-stone-500 flex justify-between">
                                    <span>Return Rate:</span>
                                    <strong className="text-rose-600">{personal.return_rate}%</strong>
                                </div>
                                <div className="text-xs text-stone-500 flex justify-between">
                                    <span>Dispatched Return:</span>
                                    <strong className="text-rose-600">{personal.dispatched_return_rate}%</strong>
                                </div>
                                <div className="text-xs text-stone-500 flex justify-between">
                                    <span>Cancellation Friction:</span>
                                    <strong className="text-amber-600">{personal.cancellation_friction_rate}%</strong>
                                </div>
                            </div>

                            <div className="bg-white p-5 rounded-xl border border-stone-200 shadow-xs">
                                <div className="flex items-center justify-between text-xs text-stone-500 font-medium">
                                    <span>Commission Earnings</span>
                                    <Link href="/agent/commissions" className="text-rose-700 hover:underline">
                                        Ledger &rarr;
                                    </Link>
                                </div>
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

                        {/* Deductions & Returns Disclosure */}
                        <div className="p-4 bg-stone-50 rounded-xl border border-stone-200 text-xs text-stone-600 flex items-start gap-3">
                            <Icons.AlertCircle className="w-5 h-5 text-stone-400 shrink-0 mt-0.5" />
                            <div className="space-y-1">
                                <span className="font-semibold text-stone-800 block">
                                    Attribution & Financial Deductions Summary
                                </span>
                                <p>
                                    • <strong>Full-Return Commercial Turnover Reversal:</strong> LKR {personal.full_return_order_value} across {personal.orders_returned} returned order(s).
                                    <em> (Gross commercial turnover reversed upon complete return, distinct from cash disbursement).</em>
                                </p>
                                <p>
                                    • <strong>Item-Level Partial Refunds:</strong> LKR {personal.fulfilled_partial_refund_amount} on fulfilled orders.
                                </p>
                                <p>
                                    • <strong>Order Volumes:</strong> Placed: {personal.orders_placed} | Commercial: {personal.orders_commercial} | Delivered: {personal.orders_fulfilled} | Cancelled: {personal.orders_cancelled}.
                                </p>
                            </div>
                        </div>
                    </div>
                )}

                {/* Tab: HLA Downline Network */}
                {activeTab === 'downline' && downline && (
                    <div className="space-y-6">
                        <div className="bg-purple-50 p-4 rounded-xl border border-purple-200 text-xs text-purple-900 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <div>
                                <span className="font-bold block">HLA Downline Network Production</span>
                                <span className="text-purple-700">
                                    Aggregate commercial turnover generated by the {downline.active_agents_count} Low-Level Agent(s) in your recruitment network.
                                </span>
                            </div>
                            <a
                                href={`/agent/analytics/export?period=${encodeURIComponent(selectedPeriod)}`}
                                download
                                className="px-3 py-1.5 bg-purple-900 text-white rounded-lg text-xs font-semibold hover:bg-purple-800 transition whitespace-nowrap text-center"
                            >
                                Export Team CSV &darr;
                            </a>
                        </div>

                        {/* Downline Aggregate KPI Cards */}
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
                                <span className="text-xs text-stone-500 font-medium">Downline Network Commissions</span>
                                <div className="mt-2 text-2xl font-bold font-mono text-emerald-700">
                                    LKR {Number(downline.approved_commissions).toLocaleString('en-US', { minimumFractionDigits: 2 })}
                                </div>
                                <div className="mt-2 text-xs text-stone-500">
                                    Fulfillment Rate: <strong>{downline.fulfillment_rate}%</strong>
                                </div>
                            </div>
                        </div>

                        {/* Downline Target Management Table (FV-04-02 Stage 2) */}
                        <div className="bg-white rounded-xl border border-stone-200 shadow-xs overflow-hidden">
                            <div className="px-6 py-4 border-b border-stone-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-stone-50/50">
                                <div>
                                    <h3 className="font-serif font-bold text-sm text-stone-900">
                                        Downline Sales Target Governance
                                    </h3>
                                    <p className="text-xs text-stone-500 mt-0.5">
                                        Establish and monitor monthly sales targets for Low-Level Agents within your recruitment tree.
                                    </p>
                                </div>
                                <span className="text-xs font-semibold px-2.5 py-1 rounded-full bg-purple-100 text-purple-800 border border-purple-200 self-start sm:self-auto">
                                    {downlineMembers.length} Downline Member(s)
                                </span>
                            </div>

                            <div className="overflow-x-auto">
                                <table className="w-full text-left text-sm text-stone-600">
                                    <thead className="bg-stone-50 text-[11px] font-semibold text-stone-600 uppercase border-b border-stone-200 tracking-wider">
                                        <tr>
                                            <th className="px-6 py-3">Agent</th>
                                            <th className="px-4 py-3 text-center">Hierarchy</th>
                                            <th className="px-4 py-3 text-center">Status</th>
                                            <th className="px-4 py-3 text-right">Target Amount</th>
                                            <th className="px-4 py-3 text-center">Attainment</th>
                                            <th className="px-4 py-3 text-center">Governance</th>
                                            <th className="px-6 py-3 text-right">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-stone-100 text-xs">
                                        {downlineMembers.length === 0 ? (
                                            <tr>
                                                <td colSpan={7} className="px-6 py-8 text-center text-stone-400">
                                                    No downline agents found in your hierarchy network.
                                                </td>
                                            </tr>
                                        ) : (
                                            downlineMembers.map((member) => {
                                                const hasTarget = !!member.target;
                                                const isMgmt = member.target?.is_management_controlled;
                                                const canEdit = member.target?.can_edit;
                                                const memberAttainment = member.metrics.target_attainment_pct;

                                                return (
                                                    <tr key={member.id} className="hover:bg-stone-50/80 transition">
                                                        <td className="px-6 py-3">
                                                            <div className="font-medium text-stone-900">{member.name}</div>
                                                            <div className="text-[11px] text-stone-400 font-mono">
                                                                {member.referral_code} • {member.email}
                                                            </div>
                                                        </td>
                                                        <td className="px-4 py-3 text-center">
                                                            {member.depth === 1 ? (
                                                                <span className="px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                                    Direct (L1)
                                                                </span>
                                                            ) : (
                                                                <span className="px-2 py-0.5 rounded text-[10px] font-semibold bg-indigo-50 text-indigo-700 border border-indigo-200">
                                                                    Tier {member.depth}
                                                                </span>
                                                            )}
                                                        </td>
                                                        <td className="px-4 py-3 text-center">
                                                            {member.status === 'active' ? (
                                                                <span className="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800">
                                                                    Active
                                                                </span>
                                                            ) : (
                                                                <span className="px-2 py-0.5 rounded-full text-[10px] font-bold bg-stone-100 text-stone-600 capitalize">
                                                                    {member.status}
                                                                </span>
                                                            )}
                                                        </td>
                                                        <td className="px-4 py-3 text-right">
                                                            {hasTarget ? (
                                                                <div>
                                                                    <span className="font-mono font-bold text-stone-900">
                                                                        LKR {member.target!.sales_target_formatted}
                                                                    </span>
                                                                    <span className="block text-[10px] text-stone-400 font-mono">
                                                                        {member.target!.target_month}
                                                                    </span>
                                                                </div>
                                                            ) : (
                                                                <span className="text-stone-400 italic">No Target Set</span>
                                                            )}
                                                        </td>
                                                        <td className="px-4 py-3 text-center">
                                                            {memberAttainment !== null && memberAttainment !== undefined ? (
                                                                <span
                                                                    className={`px-2 py-0.5 rounded-full text-[10px] font-bold font-mono ${
                                                                        memberAttainment >= 100
                                                                            ? 'bg-emerald-100 text-emerald-800'
                                                                            : memberAttainment >= 75
                                                                            ? 'bg-amber-100 text-amber-800'
                                                                            : 'bg-rose-100 text-rose-800'
                                                                    }`}
                                                                >
                                                                    {memberAttainment}%
                                                                </span>
                                                            ) : (
                                                                <span className="text-stone-400">&mdash;</span>
                                                            )}
                                                        </td>
                                                        <td className="px-4 py-3 text-center">
                                                            {isMgmt ? (
                                                                <span
                                                                    className="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-semibold bg-blue-50 text-blue-700 border border-blue-200"
                                                                    title="Target established or modified by Corporate Management. Modifications are locked."
                                                                >
                                                                    <Icons.Lock className="w-3 h-3" /> Management-Controlled
                                                                </span>
                                                            ) : hasTarget ? (
                                                                <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-semibold bg-purple-50 text-purple-700 border border-purple-200">
                                                                    <Icons.Check className="w-3 h-3" /> HLA Assigned
                                                                </span>
                                                            ) : (
                                                                <span className="text-stone-400 text-[10px]">Unassigned</span>
                                                            )}
                                                        </td>
                                                        <td className="px-6 py-3 text-right">
                                                            {!hasTarget ? (
                                                                member.status === 'active' ? (
                                                                    <button
                                                                        type="button"
                                                                        onClick={() => openAssignModal(member)}
                                                                        className="px-3 py-1 bg-purple-600 hover:bg-purple-700 text-white rounded-lg text-xs font-semibold shadow-2xs transition"
                                                                    >
                                                                        Assign Target
                                                                    </button>
                                                                ) : (
                                                                    <span className="text-stone-400 text-[11px] italic">
                                                                        Agent Inactive
                                                                    </span>
                                                                )
                                                            ) : isMgmt ? (
                                                                <button
                                                                    type="button"
                                                                    disabled
                                                                    className="px-3 py-1 bg-stone-100 text-stone-400 rounded-lg text-xs font-medium cursor-not-allowed border border-stone-200"
                                                                    title="Corporate Management has established control over this target. It cannot be altered by HLAs."
                                                                >
                                                                    Locked by Mgmt
                                                                </button>
                                                            ) : !canEdit ? (
                                                                <button
                                                                    type="button"
                                                                    disabled
                                                                    className="px-3 py-1 bg-stone-100 text-stone-400 rounded-lg text-xs font-medium cursor-not-allowed border border-stone-200"
                                                                    title="Target is locked in a closed historical period or agent is inactive."
                                                                >
                                                                    Locked
                                                                </button>
                                                            ) : (
                                                                <button
                                                                    type="button"
                                                                    onClick={() => openAssignModal(member)}
                                                                    className="px-3 py-1 bg-stone-100 hover:bg-stone-200 text-stone-700 rounded-lg text-xs font-semibold border border-stone-300 transition"
                                                                >
                                                                    Update Target
                                                                </button>
                                                            )}
                                                        </td>
                                                    </tr>
                                                );
                                            })
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                )}

                {/* Tab: Daily Trends */}
                {activeTab === 'trends' && (
                    <div className="bg-white rounded-xl border border-stone-200 shadow-xs overflow-hidden">
                        <div className="px-6 py-4 border-b border-stone-200">
                            <h3 className="font-serif font-bold text-sm text-stone-900">
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

            {/* Downline Target Assignment / Update Modal */}
            {targetModalOpen && selectedMember && (
                <div className="fixed inset-0 z-50 overflow-y-auto bg-stone-900/60 backdrop-blur-xs flex items-center justify-center p-4">
                    <div className="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-stone-200 space-y-5 animate-in fade-in duration-150">
                        <div className="flex items-center justify-between border-b border-stone-100 pb-3">
                            <h3 className="font-serif text-lg font-bold text-stone-900">
                                {selectedMember.target ? 'Update Downline Sales Target' : 'Assign Downline Sales Target'}
                            </h3>
                            <button
                                type="button"
                                onClick={() => setTargetModalOpen(false)}
                                className="text-stone-400 hover:text-stone-600 text-xl font-semibold leading-none"
                            >
                                &times;
                            </button>
                        </div>

                        {/* Recipient Details */}
                        <div className="text-xs text-stone-600 bg-purple-50/60 p-3 rounded-lg border border-purple-200/60 space-y-1">
                            <div>
                                <strong>Agent:</strong> {selectedMember.name} ({selectedMember.referral_code})
                            </div>
                            <div>
                                <strong>Hierarchy:</strong> {selectedMember.depth === 1 ? 'Direct Descendant (L1)' : `Indirect Descendant (Tier ${selectedMember.depth})`}
                            </div>
                            <div>
                                <strong>Role:</strong> Low-Level Agent (Active)
                            </div>
                        </div>

                        <form onSubmit={handleTargetSubmit} className="space-y-4">
                            <div>
                                <label className="block text-xs font-semibold text-stone-700 uppercase tracking-wider mb-1">
                                    Target Month (YYYY-MM) *
                                </label>
                                <input
                                    type="month"
                                    value={targetForm.target_month}
                                    min={currentMonth}
                                    disabled={!!selectedMember.target}
                                    onChange={(e) => setTargetForm({ ...targetForm, target_month: e.target.value })}
                                    className="w-full text-sm border-stone-300 rounded-lg focus:ring-purple-500 focus:border-purple-500 font-mono disabled:bg-stone-100 disabled:text-stone-500"
                                    required
                                />
                                {selectedMember.target && (
                                    <p className="mt-1 text-[11px] text-stone-500">
                                        Target month cannot be altered for an existing assignment.
                                    </p>
                                )}
                                {formErrors.target_month && (
                                    <p className="mt-1 text-xs text-rose-600">{formErrors.target_month}</p>
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
                                        onChange={(e) => setTargetForm({ ...targetForm, sales_target: e.target.value })}
                                        className="w-full text-sm border-stone-300 rounded-lg focus:ring-purple-500 focus:border-purple-500 pl-14 font-mono font-medium"
                                        required
                                    />
                                </div>
                                <p className="mt-1 text-[11px] text-stone-500">
                                    Enter positive commercial revenue target. Recorded in immutable audit logs.
                                </p>
                                {formErrors.sales_target && (
                                    <p className="mt-1 text-xs text-rose-600">{formErrors.sales_target}</p>
                                )}
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-stone-700 uppercase tracking-wider mb-1">
                                    Rationale / Reason (Min. 5 Characters) *
                                </label>
                                <textarea
                                    rows={3}
                                    placeholder="Explain the performance rationale or strategic quota for this target..."
                                    value={targetForm.reason}
                                    onChange={(e) => setTargetForm({ ...targetForm, reason: e.target.value })}
                                    className="w-full text-sm border-stone-300 rounded-lg focus:ring-purple-500 focus:border-purple-500"
                                    required
                                    minLength={5}
                                />
                                <p className="mt-1 text-[11px] text-stone-500">
                                    Mandatory governance reason recorded for accountability.
                                </p>
                                {formErrors.reason && (
                                    <p className="mt-1 text-xs text-rose-600">{formErrors.reason}</p>
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
                                    disabled={isSubmitting}
                                    className="px-4 py-2 bg-purple-700 hover:bg-purple-800 text-white rounded-lg text-xs font-semibold transition disabled:opacity-50"
                                >
                                    {isSubmitting ? 'Saving...' : selectedMember.target ? 'Update Target' : 'Assign Target'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AgentLayout>
    );
}
