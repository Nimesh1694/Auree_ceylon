import React from 'react';
import { Head, Link } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import * as Icons from '@/Components/Icons';
import { PageProps } from '@/types';

interface Metrics {
    total_agents: number;
    hla_count: number;
    lla_count: number;
    active_agents: number;
    pending_agents: number;
    suspended_agents: number;
    verified_kyc: number;
    pending_kyc: number;
    rejected_kyc: number;
    kyc_compliance_rate: number;
    active_invitations: number;
    pending_hierarchy_moves: number;
}

interface TrendItem {
    date: string;
    label: string;
    hla: number;
    lla: number;
    total: number;
}

interface RecentAgent {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    role: string;
    status: string;
    referral_code: string | null;
    parent_name: string;
    parent_code: string | null;
    children_count: number;
    kyc_status: string;
    created_at: string;
}

interface PendingKycItem {
    profile_id: number;
    agent_id: number;
    agent_name: string;
    agent_role: string;
    business_name: string | null;
    bank_name: string | null;
    bank_account_name: string | null;
    created_at: string;
}

interface TopPerformer {
    id: number;
    name: string;
    role: string;
    referral_code: string | null;
    completed_orders_count: number;
    total_sales_volume: string | number;
}

interface DashboardProps extends PageProps {
    metrics: Metrics;
    registrationTrend: TrendItem[];
    recentRegistrations: RecentAgent[];
    pendingQueues: {
        applications: RecentAgent[];
        kyc: PendingKycItem[];
        hierarchyMoves: unknown[];
    };
    topPerformers: TopPerformer[];
    canManageKyc: boolean;
    canManageApplications: boolean;
    canCreateInvitations: boolean;
    canRequestHierarchyMove: boolean;
    isOwner: boolean;
}

export default function AgentManagerDashboard({
    metrics,
    registrationTrend,
    recentRegistrations,
    pendingQueues,
    topPerformers,
    canManageKyc,
    canCreateInvitations,
    isOwner,
}: DashboardProps) {
    const totalPendingActions =
        metrics.pending_agents + metrics.pending_kyc + metrics.pending_hierarchy_moves;

    const maxTrendTotal = Math.max(
        ...registrationTrend.map((t) => t.total),
        4
    );

    const getKycBadge = (status: string) => {
        switch (status) {
            case 'verified':
                return (
                    <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-100 dark:bg-emerald-950/60 text-emerald-800 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-900/60">
                        <Icons.CheckCircle2 className="w-3 h-3 text-emerald-600 dark:text-emerald-400" />
                        Verified
                    </span>
                );
            case 'rejected':
                return (
                    <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-rose-100 dark:bg-rose-950/60 text-rose-800 dark:text-rose-300 border border-rose-200 dark:border-rose-900/60">
                        <Icons.XCircle className="w-3 h-3 text-rose-600 dark:text-rose-400" />
                        Rejected
                    </span>
                );
            default:
                return (
                    <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-amber-100 dark:bg-amber-950/60 text-amber-800 dark:text-amber-300 border border-amber-200 dark:border-amber-900/60">
                        <Icons.Clock className="w-3 h-3 text-amber-600 dark:text-amber-400" />
                        Pending
                    </span>
                );
        }
    };

    const getStatusBadge = (status: string) => {
        switch (status) {
            case 'active':
                return (
                    <span className="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-100 dark:bg-emerald-950/60 text-emerald-800 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-900/60">
                        Active
                    </span>
                );
            case 'suspended':
                return (
                    <span className="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-rose-100 dark:bg-rose-950/60 text-rose-800 dark:text-rose-300 border border-rose-200 dark:border-rose-900/60">
                        Suspended
                    </span>
                );
            default:
                return (
                    <span className="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-amber-100 dark:bg-amber-950/60 text-amber-800 dark:text-amber-300 border border-amber-200 dark:border-amber-900/60">
                        Pending
                    </span>
                );
        }
    };

    return (
        <AdminLayout>
            <Head title="Agent Operations & Network Console" />

            <div className="space-y-6">
                {/* 1. Executive Console Header */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white dark:bg-[#0F172A] p-6 rounded-2xl border border-slate-200/80 dark:border-slate-800/80 shadow-xs transition-colors">
                    <div>
                        <div className="flex items-center gap-2 mb-1">
                            <span className="px-2.5 py-0.5 text-[10px] font-mono font-bold tracking-wider uppercase rounded-full bg-blue-100 dark:bg-blue-950/70 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-900/50">
                                Network Administration
                            </span>
                            {isOwner && (
                                <span className="px-2.5 py-0.5 text-[10px] font-mono font-bold tracking-wider uppercase rounded-full bg-purple-100 dark:bg-purple-950/70 text-purple-700 dark:text-purple-300 border border-purple-200 dark:border-purple-900/50">
                                    Owner View
                                </span>
                            )}
                        </div>
                        <h1 className="text-xl sm:text-2xl font-serif font-bold text-slate-900 dark:text-white tracking-tight">
                            Agent Operations & Network Console
                        </h1>
                        <p className="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1">
                            Operational management for agent onboarding, KYC verification, recruitment pipelines, and network downlines.
                        </p>
                    </div>

                    <div className="flex items-center gap-3">
                        {canCreateInvitations && (
                            <Link
                                href="/admin/agents/invitations"
                                className="inline-flex items-center gap-2 px-4 py-2 border border-slate-300 dark:border-slate-700 rounded-xl text-xs font-semibold text-slate-700 dark:text-slate-200 bg-white dark:bg-[#1E293B] hover:bg-slate-50 dark:hover:bg-slate-800 shadow-xs transition-colors"
                            >
                                <Icons.Mail className="w-4 h-4 text-slate-500 dark:text-slate-400" />
                                <span>Recruitment Invitations</span>
                            </Link>
                        )}
                        <Link
                            href="/admin/agents"
                            className="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 dark:bg-blue-600 dark:hover:bg-blue-500 text-white rounded-xl text-xs font-semibold shadow-xs transition-colors"
                        >
                            <Icons.Users className="w-4 h-4 text-white" />
                            <span>Agent Directory</span>
                        </Link>
                    </div>
                </div>

                {/* 2. Action Alert Strip (Pending Queues) */}
                {totalPendingActions > 0 && (
                    <div className="bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-900/50 rounded-2xl p-4 transition-colors">
                        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                            <div className="flex items-center gap-3">
                                <div className="p-2 rounded-xl bg-amber-100 dark:bg-amber-900/50 text-amber-700 dark:text-amber-300">
                                    <Icons.AlertCircle className="w-5 h-5" />
                                </div>
                                <div>
                                    <h4 className="text-xs sm:text-sm font-bold text-amber-900 dark:text-amber-200">
                                        Operational Pending Tasks ({totalPendingActions} Actions Required)
                                    </h4>
                                    <p className="text-[11px] sm:text-xs text-amber-700 dark:text-amber-400">
                                        {metrics.pending_agents} registrations awaiting approval &bull; {metrics.pending_kyc} KYC submissions pending verification &bull; {metrics.pending_hierarchy_moves} hierarchy move requests in review.
                                    </p>
                                </div>
                            </div>

                            <div className="flex items-center gap-2">
                                {metrics.pending_agents > 0 && (
                                    <Link
                                        href="/admin/agents?status=pending"
                                        className="px-3 py-1.5 rounded-lg text-xs font-semibold bg-amber-600 hover:bg-amber-700 text-white shadow-xs transition-colors"
                                    >
                                        Review Applications ({metrics.pending_agents})
                                    </Link>
                                )}
                                {metrics.pending_kyc > 0 && (
                                    <Link
                                        href="/admin/agents?kyc_status=pending"
                                        className="px-3 py-1.5 rounded-lg text-xs font-semibold bg-slate-900 dark:bg-slate-800 hover:bg-slate-800 text-white shadow-xs transition-colors"
                                    >
                                        Verify KYC ({metrics.pending_kyc})
                                    </Link>
                                )}
                            </div>
                        </div>
                    </div>
                )}

                {/* 3. Primary KPI Cards Grid */}
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    {/* Total Agents */}
                    <div className="bg-white dark:bg-[#0F172A] rounded-2xl border border-slate-200/80 dark:border-slate-800/80 p-5 shadow-xs transition-colors">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                Total Agent Network
                            </span>
                            <div className="p-2 rounded-xl bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400">
                                <Icons.Users className="w-4 h-4" />
                            </div>
                        </div>
                        <div className="mt-3 flex items-baseline gap-2">
                            <span className="text-3xl font-extrabold text-slate-900 dark:text-white font-mono">
                                {metrics.total_agents}
                            </span>
                            <span className="text-xs text-slate-500 dark:text-slate-400">Registered</span>
                        </div>
                        <div className="mt-3 pt-3 border-t border-slate-100 dark:border-slate-800/80 flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
                            <span className="text-emerald-600 dark:text-emerald-400 font-semibold font-mono">
                                {metrics.active_agents} Active
                            </span>
                            <span>&bull;</span>
                            <span className="text-amber-600 dark:text-amber-400 font-mono">
                                {metrics.pending_agents} Pending
                            </span>
                            <span>&bull;</span>
                            <span className="text-rose-600 dark:text-rose-400 font-mono">
                                {metrics.suspended_agents} Suspended
                            </span>
                        </div>
                    </div>

                    {/* High-Level Agents (HLA) */}
                    <div className="bg-white dark:bg-[#0F172A] rounded-2xl border border-slate-200/80 dark:border-slate-800/80 p-5 shadow-xs transition-colors">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                High-Level Agents (HLA)
                            </span>
                            <div className="p-2 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400">
                                <Icons.ShieldCheck className="w-4 h-4" />
                            </div>
                        </div>
                        <div className="mt-3 flex items-baseline gap-2">
                            <span className="text-3xl font-extrabold text-indigo-600 dark:text-indigo-400 font-mono">
                                {metrics.hla_count}
                            </span>
                            <span className="text-xs text-slate-500 dark:text-slate-400">Direct Hubs</span>
                        </div>
                        <div className="mt-3 pt-3 border-t border-slate-100 dark:border-slate-800/80 flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
                            <span>Direct Company Attribution</span>
                            <span className="font-mono font-semibold text-slate-700 dark:text-slate-300">
                                {metrics.total_agents > 0
                                    ? ((metrics.hla_count / metrics.total_agents) * 100).toFixed(1)
                                    : 0}
                                %
                            </span>
                        </div>
                    </div>

                    {/* Low-Level Agents (LLA) */}
                    <div className="bg-white dark:bg-[#0F172A] rounded-2xl border border-slate-200/80 dark:border-slate-800/80 p-5 shadow-xs transition-colors">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                Low-Level Agents (LLA)
                            </span>
                            <div className="p-2 rounded-xl bg-purple-50 dark:bg-purple-950/60 text-purple-600 dark:text-purple-400">
                                <Icons.GitBranch className="w-4 h-4" />
                            </div>
                        </div>
                        <div className="mt-3 flex items-baseline gap-2">
                            <span className="text-3xl font-extrabold text-purple-600 dark:text-purple-400 font-mono">
                                {metrics.lla_count}
                            </span>
                            <span className="text-xs text-slate-500 dark:text-slate-400">Sub-tier Retailers</span>
                        </div>
                        <div className="mt-3 pt-3 border-t border-slate-100 dark:border-slate-800/80 flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
                            <span>Sponsor Downline Tier</span>
                            <span className="font-mono font-semibold text-slate-700 dark:text-slate-300">
                                {metrics.total_agents > 0
                                    ? ((metrics.lla_count / metrics.total_agents) * 100).toFixed(1)
                                    : 0}
                                %
                            </span>
                        </div>
                    </div>

                    {/* KYC Verification Rate */}
                    <div className="bg-white dark:bg-[#0F172A] rounded-2xl border border-slate-200/80 dark:border-slate-800/80 p-5 shadow-xs transition-colors">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                KYC Compliance Rate
                            </span>
                            <div className="p-2 rounded-xl bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400">
                                <Icons.FileCheck className="w-4 h-4" />
                            </div>
                        </div>
                        <div className="mt-3 flex items-baseline gap-2">
                            <span className="text-3xl font-extrabold text-emerald-600 dark:text-emerald-400 font-mono">
                                {metrics.kyc_compliance_rate}%
                            </span>
                            <span className="text-xs text-slate-500 dark:text-slate-400">Verified</span>
                        </div>
                        <div className="mt-3 pt-3 border-t border-slate-100 dark:border-slate-800/80 flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
                            <span className="font-mono">{metrics.verified_kyc} Verified</span>
                            <span>&bull;</span>
                            <span className="font-mono text-amber-600 dark:text-amber-400">
                                {metrics.pending_kyc} Pending
                            </span>
                        </div>
                    </div>
                </div>

                {/* 4. Two-Column Analytics Section */}
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {/* Left: 30-Day Registration Velocity Chart (2 cols) */}
                    <div className="lg:col-span-2 bg-white dark:bg-[#0F172A] rounded-2xl border border-slate-200/80 dark:border-slate-800/80 p-6 shadow-xs transition-colors flex flex-col justify-between">
                        <div>
                            <div className="flex items-center justify-between mb-4">
                                <div>
                                    <h3 className="text-sm sm:text-base font-bold text-slate-900 dark:text-white">
                                        Agent Registration Velocity (Last 30 Days)
                                    </h3>
                                    <p className="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                        Daily registrations breakdown between High-Level and Low-Level agent signups.
                                    </p>
                                </div>
                                <div className="flex items-center gap-3 text-xs">
                                    <span className="flex items-center gap-1.5 text-slate-600 dark:text-slate-400">
                                        <span className="w-2.5 h-2.5 rounded-full bg-indigo-500"></span> HLA
                                    </span>
                                    <span className="flex items-center gap-1.5 text-slate-600 dark:text-slate-400">
                                        <span className="w-2.5 h-2.5 rounded-full bg-purple-500"></span> LLA
                                    </span>
                                </div>
                            </div>

                            {/* SVG Bar Chart */}
                            <div className="h-44 w-full flex items-end gap-1 sm:gap-1.5 pt-6 pb-2">
                                {registrationTrend.map((day, idx) => {
                                    const totalHeight = day.total > 0
                                        ? Math.min(100, Math.round((day.total / maxTrendTotal) * 100))
                                        : 4;
                                    const hlaShare = day.total > 0
                                        ? Math.round((day.hla / day.total) * 100)
                                        : 0;

                                    return (
                                        <div
                                            key={idx}
                                            className="flex-1 flex flex-col items-center h-full justify-end group relative"
                                        >
                                            {/* Hover Tooltip */}
                                            <div className="opacity-0 group-hover:opacity-100 transition-opacity absolute bottom-full mb-2 z-20 pointer-events-none bg-slate-900 text-white text-[10px] rounded-lg py-1 px-2 whitespace-nowrap shadow-md">
                                                <div className="font-bold">{day.label}</div>
                                                <div>Total: {day.total}</div>
                                                <div className="text-indigo-300">HLA: {day.hla}</div>
                                                <div className="text-purple-300">LLA: {day.lla}</div>
                                            </div>

                                            {/* Stacked Bar Container */}
                                            <div
                                                style={{ height: `${totalHeight}%` }}
                                                className="w-full rounded-t-sm overflow-hidden flex flex-col justify-end transition-all bg-slate-100 dark:bg-slate-800/40 group-hover:brightness-110"
                                            >
                                                {day.total > 0 ? (
                                                    <>
                                                        <div
                                                            style={{ height: `${100 - hlaShare}%` }}
                                                            className="w-full bg-purple-500"
                                                        />
                                                        <div
                                                            style={{ height: `${hlaShare}%` }}
                                                            className="w-full bg-indigo-500"
                                                        />
                                                    </>
                                                ) : (
                                                    <div className="h-1 w-full bg-slate-200 dark:bg-slate-800" />
                                                )}
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>

                            {/* Axis Labels */}
                            <div className="flex justify-between items-center text-[10px] font-mono text-slate-400 dark:text-slate-500 pt-2 border-t border-slate-100 dark:border-slate-800">
                                <span>{registrationTrend[0]?.label}</span>
                                <span>{registrationTrend[14]?.label}</span>
                                <span>Today ({registrationTrend[registrationTrend.length - 1]?.label})</span>
                            </div>
                        </div>

                        <div className="mt-4 pt-3 border-t border-slate-100 dark:border-slate-800/80 flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
                            <span>
                                30-Day Growth: <strong className="font-mono text-slate-800 dark:text-slate-200">+{registrationTrend.reduce((acc, t) => acc + t.total, 0)} Agents</strong>
                            </span>
                            <Link
                                href="/admin/agents/activity"
                                className="text-blue-600 dark:text-blue-400 hover:underline font-semibold"
                            >
                                View Activity Log &rarr;
                            </Link>
                        </div>
                    </div>

                    {/* Right: Network Distribution & Recruitment Pipeline (1 col) */}
                    <div className="bg-white dark:bg-[#0F172A] rounded-2xl border border-slate-200/80 dark:border-slate-800/80 p-6 shadow-xs transition-colors flex flex-col justify-between">
                        <div>
                            <h3 className="text-sm sm:text-base font-bold text-slate-900 dark:text-white mb-1">
                                Network Structure
                            </h3>
                            <p className="text-xs text-slate-500 dark:text-slate-400 mb-5">
                                Tier balance and operational recruitment capacity.
                            </p>

                            {/* Progress Bar Tier Distribution */}
                            <div className="space-y-4">
                                <div>
                                    <div className="flex justify-between text-xs mb-1">
                                        <span className="font-semibold text-slate-700 dark:text-slate-300">
                                            Tier Distribution
                                        </span>
                                        <span className="font-mono text-slate-500 dark:text-slate-400">
                                            {metrics.hla_count} HLA / {metrics.lla_count} LLA
                                        </span>
                                    </div>
                                    <div className="h-2.5 w-full bg-slate-100 dark:bg-slate-800 rounded-full overflow-hidden flex">
                                        <div
                                            style={{
                                                width: `${metrics.total_agents > 0 ? (metrics.hla_count / metrics.total_agents) * 100 : 0}%`,
                                            }}
                                            className="bg-indigo-500"
                                            title="HLA"
                                        />
                                        <div
                                            style={{
                                                width: `${metrics.total_agents > 0 ? (metrics.lla_count / metrics.total_agents) * 100 : 0}%`,
                                            }}
                                            className="bg-purple-500"
                                            title="LLA"
                                        />
                                    </div>
                                </div>

                                {/* Metric Breakdown Items */}
                                <div className="space-y-2.5 pt-2 text-xs">
                                    <div className="flex items-center justify-between p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/50">
                                        <span className="text-slate-600 dark:text-slate-400">
                                            Active Recruitment Invitations
                                        </span>
                                        <span className="font-mono font-bold text-slate-900 dark:text-white">
                                            {metrics.active_invitations} tokens
                                        </span>
                                    </div>

                                    <div className="flex items-center justify-between p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/50">
                                        <span className="text-slate-600 dark:text-slate-400">
                                            Pending KYC Queue
                                        </span>
                                        <span className="font-mono font-bold text-amber-600 dark:text-amber-400">
                                            {metrics.pending_kyc} submissions
                                        </span>
                                    </div>

                                    <div className="flex items-center justify-between p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/50">
                                        <span className="text-slate-600 dark:text-slate-400">
                                            Pending Hierarchy Move Proposals
                                        </span>
                                        <span className="font-mono font-bold text-blue-600 dark:text-blue-400">
                                            {metrics.pending_hierarchy_moves} requests
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div className="mt-6 pt-3 border-t border-slate-100 dark:border-slate-800/80">
                            <Link
                                href="/admin/agents/hierarchy"
                                className="w-full inline-flex items-center justify-center gap-2 px-3 py-2 text-xs font-semibold rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 transition-colors"
                            >
                                <Icons.GitBranch className="w-3.5 h-3.5" />
                                <span>Explore Network Tree</span>
                            </Link>
                        </div>
                    </div>
                </div>

                {/* 5. Top Sales Performers (Sanitized Leaderboard Preview) */}
                {topPerformers.length > 0 && (
                    <div className="bg-white dark:bg-[#0F172A] rounded-2xl border border-slate-200/80 dark:border-slate-800/80 p-6 shadow-xs transition-colors">
                        <div className="flex items-center justify-between mb-4">
                            <div>
                                <h3 className="text-sm sm:text-base font-bold text-slate-900 dark:text-white">
                                    Top Performing Agents (This Month)
                                </h3>
                                <p className="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                    Ranked by completed customer order volume (LKR).
                                </p>
                            </div>
                            <Link
                                href="/admin/agents/performance"
                                className="text-xs font-semibold text-blue-600 dark:text-blue-400 hover:underline inline-flex items-center gap-1"
                            >
                                Full Leaderboard &rarr;
                            </Link>
                        </div>

                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                            {topPerformers.map((performer, idx) => (
                                <div
                                    key={performer.id}
                                    className="p-3.5 rounded-xl border border-slate-200/70 dark:border-slate-800/80 bg-slate-50/60 dark:bg-slate-800/30 flex flex-col justify-between"
                                >
                                    <div>
                                        <div className="flex items-center justify-between text-xs text-slate-400 font-mono mb-1">
                                            <span>#{idx + 1}</span>
                                            <span className="uppercase text-[10px]">
                                                {performer.role === 'high_level_agent' ? 'HLA' : 'LLA'}
                                            </span>
                                        </div>
                                        <h4 className="font-bold text-xs sm:text-sm text-slate-900 dark:text-white truncate">
                                            {performer.name}
                                        </h4>
                                        <p className="text-[11px] font-mono text-slate-500 dark:text-slate-400 truncate">
                                            {performer.referral_code ?? 'Direct'}
                                        </p>
                                    </div>
                                    <div className="mt-3 pt-2 border-t border-slate-200/60 dark:border-slate-800 text-xs">
                                        <div className="text-[10px] text-slate-400 uppercase">Sales Volume</div>
                                        <div className="font-mono font-extrabold text-blue-600 dark:text-blue-400">
                                            LKR {Number(performer.total_sales_volume).toLocaleString(undefined, { minimumFractionDigits: 2 })}
                                        </div>
                                        <div className="text-[10px] text-slate-400">
                                            {performer.completed_orders_count} orders completed
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                )}

                {/* 6. Recent Registrations & Applications Table */}
                <div className="bg-white dark:bg-[#0F172A] rounded-2xl border border-slate-200/80 dark:border-slate-800/80 shadow-xs overflow-hidden transition-colors">
                    <div className="p-6 border-b border-slate-100 dark:border-slate-800/80 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div>
                            <h3 className="text-sm sm:text-base font-bold text-slate-900 dark:text-white">
                                Recent Registrations & Network Onboarding
                            </h3>
                            <p className="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                Latest 10 agent additions across High-Level and Low-Level tiers.
                            </p>
                        </div>
                        <Link
                            href="/admin/agents"
                            className="text-xs font-semibold text-blue-600 dark:text-blue-400 hover:underline"
                        >
                            View All Agents ({metrics.total_agents}) &rarr;
                        </Link>
                    </div>

                    {recentRegistrations.length === 0 ? (
                        <div className="p-12 text-center text-slate-400 dark:text-slate-500 text-sm">
                            No agent registrations recorded yet.
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-slate-100 dark:divide-slate-800/80 text-left text-xs bg-white dark:bg-[#0F172A]">
                                <thead className="bg-slate-50 dark:bg-slate-900/60 text-slate-500 dark:text-slate-400 uppercase font-semibold text-[11px]">
                                    <tr>
                                        <th className="px-6 py-3.5">Agent Name & Code</th>
                                        <th className="px-6 py-3.5">Tier / Role</th>
                                        <th className="px-6 py-3.5">Parent Sponsor</th>
                                        <th className="px-6 py-3.5">Direct Downlines</th>
                                        <th className="px-6 py-3.5">KYC Status</th>
                                        <th className="px-6 py-3.5">Account Status</th>
                                        <th className="px-6 py-3.5">Registered</th>
                                        <th className="px-6 py-3.5 text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100 dark:divide-slate-800/60 font-sans bg-white dark:bg-[#0F172A]">
                                    {recentRegistrations.map((agent) => (
                                        <tr
                                            key={agent.id}
                                            className="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition-colors"
                                        >
                                            <td className="px-6 py-3.5 whitespace-nowrap">
                                                <Link
                                                    href={`/admin/agents/${agent.id}`}
                                                    className="font-bold text-slate-900 dark:text-white hover:text-blue-600 dark:hover:text-blue-400"
                                                >
                                                    {agent.name}
                                                </Link>
                                                <div className="text-[11px] font-mono text-slate-500 dark:text-slate-400">
                                                    {agent.referral_code ?? 'NO-CODE'} &bull; {agent.email}
                                                </div>
                                            </td>

                                            <td className="px-6 py-3.5 whitespace-nowrap">
                                                {agent.role === 'high_level_agent' ? (
                                                    <span className="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-indigo-100 dark:bg-indigo-950/60 text-indigo-800 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-900/60">
                                                        High-Level (HLA)
                                                    </span>
                                                ) : (
                                                    <span className="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-purple-100 dark:bg-purple-950/60 text-purple-800 dark:text-purple-300 border border-purple-200 dark:border-purple-900/60">
                                                        Low-Level (LLA)
                                                    </span>
                                                )}
                                            </td>

                                            <td className="px-6 py-3.5 whitespace-nowrap">
                                                <span className="text-slate-800 dark:text-slate-200 font-medium">
                                                    {agent.parent_name}
                                                </span>
                                                {agent.parent_code && (
                                                    <div className="text-[11px] font-mono text-slate-400">
                                                        {agent.parent_code}
                                                    </div>
                                                )}
                                            </td>

                                            <td className="px-6 py-3.5 whitespace-nowrap font-mono text-slate-700 dark:text-slate-300">
                                                {agent.children_count} agents
                                            </td>

                                            <td className="px-6 py-3.5 whitespace-nowrap">
                                                {getKycBadge(agent.kyc_status)}
                                            </td>

                                            <td className="px-6 py-3.5 whitespace-nowrap">
                                                {getStatusBadge(agent.status)}
                                            </td>

                                            <td className="px-6 py-3.5 whitespace-nowrap text-slate-500 dark:text-slate-400">
                                                {new Date(agent.created_at).toLocaleDateString()}
                                            </td>

                                            <td className="px-6 py-3.5 text-right whitespace-nowrap">
                                                <Link
                                                    href={`/admin/agents/${agent.id}`}
                                                    className="inline-flex items-center px-3 py-1 rounded-lg text-xs font-semibold bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 transition-colors"
                                                >
                                                    Inspect
                                                </Link>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            </div>
        </AdminLayout>
    );
}

