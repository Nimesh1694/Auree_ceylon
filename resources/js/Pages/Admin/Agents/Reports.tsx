import React, { useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import * as Icons from '@/Components/Icons';
import { PageProps } from '@/types';

interface ReportStats {
    total_agents: number;
    total_hla: number;
    total_lla: number;
    pending_kyc: number;
}

interface Props extends PageProps {
    stats: ReportStats;
}

export default function Reports({ stats }: Props) {
    const [downloadingType, setDownloadingType] = useState<string | null>(null);

    const handleDownload = (type: string) => {
        setDownloadingType(type);
        // Direct browser navigation to download attachment
        window.location.href = `/admin/agents/reports/export?type=${type}`;
        setTimeout(() => {
            setDownloadingType(null);
        }, 2000);
    };

    const reportCards = [
        {
            type: 'directory',
            title: 'Agent Directory Extract',
            badge: 'Directory & Status',
            description:
                'Full export of all registered agents in the network. Includes name, phone, email, tier (HLA/LLA), account status, referral code, and assigned sponsor details.',
            icon: Icons.Users,
            color: 'from-blue-600 to-indigo-600',
            bgIcon: 'text-blue-500/10 dark:text-blue-400/10',
        },
        {
            type: 'performance',
            title: 'Sales Performance Report',
            badge: 'Commercial Turnover',
            description:
                'Consolidated commercial performance metrics per agent. Displays total orders placed, completed delivery count, and reconciled sales turnover (LKR).',
            icon: Icons.TrendingUp,
            color: 'from-emerald-600 to-teal-600',
            bgIcon: 'text-emerald-500/10 dark:text-emerald-400/10',
        },
        {
            type: 'hierarchy',
            title: 'Hierarchy & Downline Closure Map',
            badge: 'Network Topology',
            description:
                'Mathematical dump of the transitive closure table. Maps every ancestor-to-descendant path and depth level across the active agent network.',
            icon: Icons.GitBranch,
            color: 'from-purple-600 to-indigo-600',
            bgIcon: 'text-purple-500/10 dark:text-purple-400/10',
        },
        {
            type: 'kyc',
            title: 'KYC & Compliance Report',
            badge: 'Compliance & Verification',
            description:
                'Audit extract of agent verification statuses. Lists KYC states (verified, pending, rejected), bank branch identifiers, and timestamp records.',
            icon: Icons.ShieldCheck,
            color: 'from-amber-600 to-orange-600',
            bgIcon: 'text-amber-500/10 dark:text-amber-400/10',
        },
    ];

    return (
        <AdminLayout>
            <Head title="Export Reports - Agent Network" />

            <div className="space-y-6">
                {/* Header Banner */}
                <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-gradient-to-r from-slate-900 via-slate-800 to-indigo-950 p-6 rounded-2xl border border-slate-700 shadow-xl text-white">
                    <div className="space-y-1">
                        <div className="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-amber-400">
                            <Icons.Download className="w-4 h-4" />
                            <span>Operational Intelligence &amp; Data Extracts</span>
                        </div>
                        <h1 className="text-2xl font-bold tracking-tight text-white flex items-center gap-3">
                            Agent Export Center
                            <span className="text-xs px-2.5 py-0.5 rounded-full border border-amber-400/40 bg-amber-500/10 text-amber-300 font-medium">
                                CSV Streaming
                            </span>
                        </h1>
                        <p className="text-sm text-slate-300 max-w-2xl">
                            Generate memory-safe, chunked CSV exports of agent directory records, commercial sales turnover, transitive hierarchy downlines, and KYC statuses.
                        </p>
                    </div>

                    <div className="flex items-center gap-3 shrink-0">
                        <Link
                            href="/admin/agents/performance"
                            className="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-xl border border-slate-600 bg-slate-800/80 hover:bg-slate-700 text-slate-200 transition-colors shadow-sm"
                        >
                            <Icons.TrendingUp className="w-4 h-4 text-slate-300" />
                            <span>Sales Leaderboard</span>
                        </Link>
                    </div>
                </div>

                {/* Telemetry Summary Cards */}
                <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div className="bg-white dark:bg-slate-900 p-4 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm">
                        <div className="flex items-center justify-between text-slate-500 dark:text-slate-400 mb-1">
                            <span className="text-xs font-semibold uppercase tracking-wider">Total Agents</span>
                            <Icons.Users className="w-4 h-4 text-blue-500" />
                        </div>
                        <div className="text-2xl font-bold text-slate-900 dark:text-white">
                            {stats.total_agents}
                        </div>
                        <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
                            Registered workforce
                        </p>
                    </div>

                    <div className="bg-white dark:bg-slate-900 p-4 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm">
                        <div className="flex items-center justify-between text-slate-500 dark:text-slate-400 mb-1">
                            <span className="text-xs font-semibold uppercase tracking-wider">High-Level Agents</span>
                            <Icons.ShieldCheck className="w-4 h-4 text-amber-500" />
                        </div>
                        <div className="text-2xl font-bold text-slate-900 dark:text-white">
                            {stats.total_hla}
                        </div>
                        <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
                            Company-direct HLA roots
                        </p>
                    </div>

                    <div className="bg-white dark:bg-slate-900 p-4 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm">
                        <div className="flex items-center justify-between text-slate-500 dark:text-slate-400 mb-1">
                            <span className="text-xs font-semibold uppercase tracking-wider">Low-Level Agents</span>
                            <Icons.GitBranch className="w-4 h-4 text-indigo-500" />
                        </div>
                        <div className="text-2xl font-bold text-slate-900 dark:text-white">
                            {stats.total_lla}
                        </div>
                        <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
                            Downline selling network
                        </p>
                    </div>

                    <div className="bg-white dark:bg-slate-900 p-4 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm">
                        <div className="flex items-center justify-between text-slate-500 dark:text-slate-400 mb-1">
                            <span className="text-xs font-semibold uppercase tracking-wider">Pending KYC</span>
                            <Icons.Clock className="w-4 h-4 text-rose-500" />
                        </div>
                        <div className={`text-2xl font-bold ${stats.pending_kyc > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-slate-900 dark:text-white'}`}>
                            {stats.pending_kyc}
                        </div>
                        <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
                            Awaiting verification
                        </p>
                    </div>
                </div>

                {/* Available Report Extracts Grid */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    {reportCards.map((card) => {
                        const CardIcon = card.icon;
                        const isDownloading = downloadingType === card.type;

                        return (
                            <div
                                key={card.type}
                                className="relative bg-white dark:bg-slate-900 p-6 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm hover:shadow-md transition-all flex flex-col justify-between overflow-hidden"
                            >
                                <CardIcon className={`w-32 h-32 absolute -right-6 -bottom-6 ${card.bgIcon} pointer-events-none`} />

                                <div className="space-y-3 relative z-10">
                                    <div className="flex items-center justify-between">
                                        <span className="text-xs font-bold uppercase tracking-wider px-2.5 py-0.5 rounded-full border bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400">
                                            {card.badge}
                                        </span>
                                        <span className="text-xs font-mono font-semibold text-slate-400">
                                            .CSV format
                                        </span>
                                    </div>

                                    <h3 className="text-lg font-bold text-slate-900 dark:text-white">
                                        {card.title}
                                    </h3>

                                    <p className="text-xs text-slate-600 dark:text-slate-400 leading-relaxed">
                                        {card.description}
                                    </p>
                                </div>

                                <div className="pt-6 mt-4 border-t border-slate-100 dark:border-slate-800/80 flex items-center justify-between relative z-10">
                                    <span className="text-[11px] text-slate-400 font-mono">
                                        UTF-8 BOM Encoded
                                    </span>

                                    <button
                                        onClick={() => handleDownload(card.type)}
                                        disabled={isDownloading}
                                        className={`inline-flex items-center gap-2 px-4 py-2 text-xs font-semibold rounded-xl text-white shadow-md bg-gradient-to-r ${card.color} hover:opacity-95 transition-all active:scale-95 disabled:opacity-50`}
                                    >
                                        <Icons.Download className={`w-3.5 h-3.5 ${isDownloading ? 'animate-bounce' : ''}`} />
                                        <span>{isDownloading ? 'Exporting...' : 'Download CSV'}</span>
                                    </button>
                                </div>
                            </div>
                        );
                    })}
                </div>

                {/* Privacy & Governance Notice */}
                <div className="bg-slate-50 dark:bg-slate-900/60 p-4 rounded-xl border border-slate-200 dark:border-slate-800 text-xs text-slate-500 dark:text-slate-400 flex items-start gap-3">
                    <Icons.ShieldCheck className="w-5 h-5 text-emerald-500 shrink-0 mt-0.5" />
                    <div className="space-y-0.5">
                        <span className="font-semibold text-slate-700 dark:text-slate-300">
                            Strict Data Privacy &amp; Masking Protocol
                        </span>
                        <p>
                            National Identity Card (NIC) numbers and full bank account numbers are excluded or masked in all generated export files. Every export operation is permanently logged to the system audit trail for executive oversight.
                        </p>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}

