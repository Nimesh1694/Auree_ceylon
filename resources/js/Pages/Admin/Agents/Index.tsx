import React, { useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import * as Icons from '@/Components/Icons';
import { PageProps, User } from '@/types';

interface ExtendedUser extends User {
    children_count?: number;
}

interface PaginatedAgents {
    data: ExtendedUser[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
    links: {
        url: string | null;
        label: string;
        active: boolean;
    }[];
}

interface IndexProps extends PageProps {
    agents: PaginatedAgents;
    filters: {
        search?: string;
        role?: string;
        status?: string;
        kyc_status?: string;
        from_date?: string;
        to_date?: string;
    };
    stats: {
        pending_applications: number;
        pending_kyc: number;
        pending_hierarchy_requests: number;
        total_hla: number;
        total_lla: number;
        total_agents: number;
    };
    canManageApplications?: boolean;
    canManageKyc?: boolean;
}

export default function Index({ agents, filters, stats, canManageApplications = true }: IndexProps) {
    const { flash } = usePage<PageProps>().props;

    const [search, setSearch] = useState(filters.search || '');
    const [role, setRole] = useState(filters.role || '');
    const [status, setStatus] = useState(filters.status || '');
    const [kycStatus, setKycStatus] = useState(filters.kyc_status || '');
    const [fromDate, setFromDate] = useState(filters.from_date || '');
    const [toDate, setToDate] = useState(filters.to_date || '');

    const [rejectingAgentId, setRejectingAgentId] = useState<number | null>(null);
    const [rejectReason, setRejectReason] = useState('');

    const applyFilters = (overrides: Record<string, string | undefined> = {}) => {
        const queryParams: Record<string, string | undefined> = {
            search: search.trim() || undefined,
            role: role || undefined,
            status: status || undefined,
            kyc_status: kycStatus || undefined,
            from_date: fromDate || undefined,
            to_date: toDate || undefined,
            ...overrides,
        };

        // Remove undefined keys
        Object.keys(queryParams).forEach((key) => {
            if (queryParams[key] === undefined || queryParams[key] === '') {
                delete queryParams[key];
            }
        });

        router.get(route('admin.agents.index'), queryParams, { preserveState: true });
    };

    const handleSearchSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        applyFilters();
    };

    const handleResetFilters = () => {
        setSearch('');
        setRole('');
        setStatus('');
        setKycStatus('');
        setFromDate('');
        setToDate('');
        router.get(route('admin.agents.index'), {}, { preserveState: true });
    };

    const hasActiveFilters = Boolean(
        search || role || status || kycStatus || fromDate || toDate
    );

    const handleApprove = (agentId: number, name: string) => {
        if (
            confirm(
                `Are you sure you want to approve the agent application for ${name}? This will activate their account and position them in the network hierarchy.`
            )
        ) {
            router.post(route('admin.agents.approve', agentId));
        }
    };

    const handleConfirmReject = (agentId: number) => {
        if (!rejectReason.trim()) {
            alert('Please specify a rejection reason.');
            return;
        }
        router.post(
            route('admin.agents.reject', agentId),
            { reason: rejectReason },
            {
                onSuccess: () => {
                    setRejectingAgentId(null);
                    setRejectReason('');
                },
            }
        );
    };

    const getKycBadge = (kStatus?: string) => {
        switch (kStatus) {
            case 'verified':
                return (
                    <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-100 dark:bg-emerald-950/60 text-emerald-800 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-900/60">
                        <Icons.CheckCircle2 className="w-3 h-3 text-emerald-600 dark:text-emerald-400" />
                        Verified
                    </span>
                );
            case 'rejected':
                return (
                    <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-rose-100 dark:bg-rose-950/60 text-rose-800 dark:text-rose-300 border border-rose-200 dark:border-rose-900/60">
                        <Icons.XCircle className="w-3 h-3 text-rose-600 dark:text-rose-400" />
                        Rejected
                    </span>
                );
            default:
                return (
                    <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-amber-100 dark:bg-amber-950/60 text-amber-800 dark:text-amber-300 border border-amber-200 dark:border-amber-900/60">
                        <Icons.Clock className="w-3 h-3 text-amber-600 dark:text-amber-400" />
                        Pending
                    </span>
                );
        }
    };

    const getStatusBadge = (sStatus: string) => {
        switch (sStatus) {
            case 'active':
                return (
                    <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-100 dark:bg-emerald-950/60 text-emerald-800 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-900/60">
                        Active
                    </span>
                );
            case 'suspended':
                return (
                    <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-rose-100 dark:bg-rose-950/60 text-rose-800 dark:text-rose-300 border border-rose-200 dark:border-rose-900/60">
                        Suspended
                    </span>
                );
            default:
                return (
                    <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-amber-100 dark:bg-amber-950/60 text-amber-800 dark:text-amber-300 border border-amber-200 dark:border-amber-900/60">
                        Pending
                    </span>
                );
        }
    };

    return (
        <AdminLayout>
            <Head title="Agent Directory & Network Roster" />

            <div className="space-y-6">
                {/* 1. Header & Title Banner */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white dark:bg-[#0F172A] p-6 rounded-2xl border border-slate-200/80 dark:border-slate-800/80 shadow-xs transition-colors">
                    <div>
                        <div className="flex items-center gap-2 mb-1">
                            <span className="px-2.5 py-0.5 text-[10px] font-mono font-bold tracking-wider uppercase rounded-full bg-blue-100 dark:bg-blue-950/70 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-900/50">
                                Network Directory
                            </span>
                        </div>
                        <h1 className="text-xl sm:text-2xl font-serif font-bold text-slate-900 dark:text-white tracking-tight">
                            Agent Network Directory
                        </h1>
                        <p className="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1">
                            Search, filter, inspect downlines, and verify status for all registered agents.
                        </p>
                    </div>

                    <div className="flex items-center gap-3">
                        <Link
                            href="/admin/agents/invitations"
                            className="inline-flex items-center gap-2 px-4 py-2 border border-slate-300 dark:border-slate-700 rounded-xl text-xs font-semibold text-slate-700 dark:text-slate-200 bg-white dark:bg-[#1E293B] hover:bg-slate-50 dark:hover:bg-slate-800 shadow-xs transition-colors"
                        >
                            <Icons.Mail className="w-4 h-4 text-slate-500 dark:text-slate-400" />
                            <span>Invitations</span>
                        </Link>
                        <Link
                            href="/admin/agents/hierarchy"
                            className="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 dark:bg-blue-600 dark:hover:bg-blue-500 text-white rounded-xl text-xs font-semibold shadow-xs transition-colors"
                        >
                            <Icons.GitBranch className="w-4 h-4 text-white" />
                            <span>Tree Explorer</span>
                        </Link>
                    </div>
                </div>

                {/* 2. Flash Messages */}
                {flash?.success && (
                    <div className="p-4 bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-300 rounded-2xl flex items-center gap-3 transition-colors">
                        <Icons.CheckCircle2 className="w-5 h-5 text-emerald-600 dark:text-emerald-400 shrink-0" />
                        <p className="text-sm font-medium">{flash.success}</p>
                    </div>
                )}
                {flash?.error && (
                    <div className="p-4 bg-rose-50 dark:bg-rose-950/50 border border-rose-200 dark:border-rose-800 text-rose-800 dark:text-rose-300 rounded-2xl flex items-center gap-3 transition-colors">
                        <Icons.AlertCircle className="w-5 h-5 text-rose-600 dark:text-rose-400 shrink-0" />
                        <p className="text-sm font-medium">{flash.error}</p>
                    </div>
                )}

                {/* 3. Stat Badges Grid */}
                <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                    {/* All Agents */}
                    <div
                        onClick={() => {
                            setRole('');
                            setStatus('');
                            setKycStatus('');
                            applyFilters({ role: '', status: '', kyc_status: '' });
                        }}
                        className={`bg-white dark:bg-[#0F172A] rounded-2xl border p-4 shadow-xs cursor-pointer transition-colors ${
                            !role && !status && !kycStatus
                                ? 'border-blue-500 dark:border-blue-500 ring-2 ring-blue-500/20'
                                : 'border-slate-200/80 dark:border-slate-800/80 hover:border-slate-300'
                        }`}
                    >
                        <p className="text-[10px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                            Total Network
                        </p>
                        <p className="text-xl font-extrabold text-slate-900 dark:text-white mt-1 font-mono">
                            {stats.total_agents}
                        </p>
                        <p className="text-[10px] text-slate-400 mt-0.5">All Registered</p>
                    </div>

                    {/* HLA Filter Badge */}
                    <div
                        onClick={() => {
                            const newRole = role === 'high_level_agent' ? '' : 'high_level_agent';
                            setRole(newRole);
                            applyFilters({ role: newRole });
                        }}
                        className={`bg-white dark:bg-[#0F172A] rounded-2xl border p-4 shadow-xs cursor-pointer transition-colors ${
                            role === 'high_level_agent'
                                ? 'border-indigo-500 dark:border-indigo-500 ring-2 ring-indigo-500/20'
                                : 'border-slate-200/80 dark:border-slate-800/80 hover:border-indigo-300'
                        }`}
                    >
                        <p className="text-[10px] font-semibold text-indigo-600 dark:text-indigo-400 uppercase tracking-wider">
                            High-Level (HLA)
                        </p>
                        <p className="text-xl font-extrabold text-indigo-600 dark:text-indigo-400 mt-1 font-mono">
                            {stats.total_hla}
                        </p>
                        <p className="text-[10px] text-slate-400 mt-0.5">Direct Partners</p>
                    </div>

                    {/* LLA Filter Badge */}
                    <div
                        onClick={() => {
                            const newRole = role === 'low_level_agent' ? '' : 'low_level_agent';
                            setRole(newRole);
                            applyFilters({ role: newRole });
                        }}
                        className={`bg-white dark:bg-[#0F172A] rounded-2xl border p-4 shadow-xs cursor-pointer transition-colors ${
                            role === 'low_level_agent'
                                ? 'border-purple-500 dark:border-purple-500 ring-2 ring-purple-500/20'
                                : 'border-slate-200/80 dark:border-slate-800/80 hover:border-purple-300'
                        }`}
                    >
                        <p className="text-[10px] font-semibold text-purple-600 dark:text-purple-400 uppercase tracking-wider">
                            Low-Level (LLA)
                        </p>
                        <p className="text-xl font-extrabold text-purple-600 dark:text-purple-400 mt-1 font-mono">
                            {stats.total_lla}
                        </p>
                        <p className="text-[10px] text-slate-400 mt-0.5">Sub-tier Retail</p>
                    </div>

                    {/* Pending Applications Badge */}
                    <div
                        onClick={() => {
                            const newStatus = status === 'pending' ? '' : 'pending';
                            setStatus(newStatus);
                            applyFilters({ status: newStatus });
                        }}
                        className={`bg-white dark:bg-[#0F172A] rounded-2xl border p-4 shadow-xs cursor-pointer transition-colors ${
                            status === 'pending'
                                ? 'border-amber-500 dark:border-amber-500 ring-2 ring-amber-500/20'
                                : 'border-slate-200/80 dark:border-slate-800/80 hover:border-amber-300'
                        }`}
                    >
                        <p className="text-[10px] font-semibold text-amber-600 dark:text-amber-400 uppercase tracking-wider">
                            Pending Apps
                        </p>
                        <p className="text-xl font-extrabold text-amber-600 dark:text-amber-400 mt-1 font-mono">
                            {stats.pending_applications}
                        </p>
                        <p className="text-[10px] text-slate-400 mt-0.5">Need Approval</p>
                    </div>

                    {/* Pending KYC Badge */}
                    <div
                        onClick={() => {
                            const newKyc = kycStatus === 'pending' ? '' : 'pending';
                            setKycStatus(newKyc);
                            applyFilters({ kyc_status: newKyc });
                        }}
                        className={`bg-white dark:bg-[#0F172A] rounded-2xl border p-4 shadow-xs cursor-pointer transition-colors ${
                            kycStatus === 'pending'
                                ? 'border-blue-500 dark:border-blue-500 ring-2 ring-blue-500/20'
                                : 'border-slate-200/80 dark:border-slate-800/80 hover:border-blue-300'
                        }`}
                    >
                        <p className="text-[10px] font-semibold text-blue-600 dark:text-blue-400 uppercase tracking-wider">
                            Pending KYC
                        </p>
                        <p className="text-xl font-extrabold text-blue-600 dark:text-blue-400 mt-1 font-mono">
                            {stats.pending_kyc}
                        </p>
                        <p className="text-[10px] text-slate-400 mt-0.5">Need Verification</p>
                    </div>

                    {/* Hierarchy Requests Badge */}
                    <Link
                        href="/admin/agents/hierarchy-requests"
                        className="bg-white dark:bg-[#0F172A] rounded-2xl border border-slate-200/80 dark:border-slate-800/80 p-4 shadow-xs hover:border-slate-400 transition-colors block"
                    >
                        <p className="text-[10px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                            Move Requests
                        </p>
                        <p className="text-xl font-extrabold text-slate-800 dark:text-slate-200 mt-1 font-mono">
                            {stats.pending_hierarchy_requests}
                        </p>
                        <p className="text-[10px] text-slate-400 mt-0.5">Owner Review</p>
                    </Link>
                </div>

                {/* 4. Comprehensive Filter & Search Bar */}
                <div className="bg-white dark:bg-[#0F172A] rounded-2xl border border-slate-200/80 dark:border-slate-800/80 p-5 shadow-xs transition-colors">
                    <form onSubmit={handleSearchSubmit} className="space-y-3">
                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3 text-xs">
                            {/* Search Input (2 cols on desktop) */}
                            <div className="lg:col-span-2 relative">
                                <Icons.Search className="w-4 h-4 text-slate-400 absolute left-3 top-2.5" />
                                <input
                                    type="text"
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    placeholder="Search name, email, phone, code..."
                                    className="w-full pl-9 pr-3 py-2 border border-slate-300 dark:border-slate-700 rounded-xl bg-white dark:bg-[#1E293B] text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-xs"
                                />
                            </div>

                            {/* Tier Filter */}
                            <div>
                                <select
                                    value={role}
                                    onChange={(e) => {
                                        setRole(e.target.value);
                                        applyFilters({ role: e.target.value });
                                    }}
                                    className="w-full px-3 py-2 border border-slate-300 dark:border-slate-700 rounded-xl bg-white dark:bg-[#1E293B] text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-xs"
                                >
                                    <option value="">All Tiers (HLA & LLA)</option>
                                    <option value="high_level_agent">High-Level (HLA Only)</option>
                                    <option value="low_level_agent">Low-Level (LLA Only)</option>
                                </select>
                            </div>

                            {/* Account Status Filter */}
                            <div>
                                <select
                                    value={status}
                                    onChange={(e) => {
                                        setStatus(e.target.value);
                                        applyFilters({ status: e.target.value });
                                    }}
                                    className="w-full px-3 py-2 border border-slate-300 dark:border-slate-700 rounded-xl bg-white dark:bg-[#1E293B] text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-xs"
                                >
                                    <option value="">All Account Statuses</option>
                                    <option value="active">Active Accounts</option>
                                    <option value="pending">Pending Application</option>
                                    <option value="suspended">Suspended Accounts</option>
                                </select>
                            </div>

                            {/* KYC Status Filter */}
                            <div>
                                <select
                                    value={kycStatus}
                                    onChange={(e) => {
                                        setKycStatus(e.target.value);
                                        applyFilters({ kyc_status: e.target.value });
                                    }}
                                    className="w-full px-3 py-2 border border-slate-300 dark:border-slate-700 rounded-xl bg-white dark:bg-[#1E293B] text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-xs"
                                >
                                    <option value="">All KYC Statuses</option>
                                    <option value="verified">Verified KYC</option>
                                    <option value="pending">Pending KYC</option>
                                    <option value="rejected">Rejected KYC</option>
                                </select>
                            </div>

                            {/* Action Buttons */}
                            <div className="flex items-center gap-2">
                                <button
                                    type="submit"
                                    className="flex-1 py-2 px-3 bg-slate-900 dark:bg-blue-600 hover:bg-slate-800 dark:hover:bg-blue-500 text-white font-semibold rounded-xl text-xs transition-colors cursor-pointer shadow-xs"
                                >
                                    Filter
                                </button>
                                {hasActiveFilters && (
                                    <button
                                        type="button"
                                        onClick={handleResetFilters}
                                        className="py-2 px-3 border border-slate-300 dark:border-slate-700 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white rounded-xl text-xs font-semibold transition-colors cursor-pointer"
                                        title="Clear all filters"
                                    >
                                        Reset
                                    </button>
                                )}
                            </div>
                        </div>

                        {/* Date Range Sub-Bar */}
                        <div className="pt-2 border-t border-slate-100 dark:border-slate-800/80 flex flex-wrap items-center gap-3 text-xs text-slate-500 dark:text-slate-400">
                            <span className="font-semibold text-slate-700 dark:text-slate-300">
                                Registration Date:
                            </span>
                            <div className="flex items-center gap-2">
                                <input
                                    type="date"
                                    value={fromDate}
                                    onChange={(e) => {
                                        setFromDate(e.target.value);
                                        applyFilters({ from_date: e.target.value });
                                    }}
                                    className="px-2.5 py-1.5 border border-slate-300 dark:border-slate-700 rounded-xl bg-white dark:bg-[#1E293B] text-slate-900 dark:text-white text-xs"
                                    placeholder="From Date"
                                />
                                <span>to</span>
                                <input
                                    type="date"
                                    value={toDate}
                                    onChange={(e) => {
                                        setToDate(e.target.value);
                                        applyFilters({ to_date: e.target.value });
                                    }}
                                    className="px-2.5 py-1.5 border border-slate-300 dark:border-slate-700 rounded-xl bg-white dark:bg-[#1E293B] text-slate-900 dark:text-white text-xs"
                                    placeholder="To Date"
                                />
                            </div>
                            {hasActiveFilters && (
                                <span className="ml-auto text-[11px] text-blue-600 dark:text-blue-400 font-mono">
                                    Showing {agents.total} matching {agents.total === 1 ? 'agent' : 'agents'}
                                </span>
                            )}
                        </div>
                    </form>
                </div>

                {/* 5. Main Agent Table */}
                <div className="bg-white dark:bg-[#0F172A] rounded-2xl border border-slate-200/80 dark:border-slate-800/80 shadow-xs overflow-hidden transition-colors">
                    {agents.data.length === 0 ? (
                        <div className="p-12 text-center">
                            <Icons.Users className="w-12 h-12 text-slate-300 dark:text-slate-600 mx-auto mb-3" />
                            <p className="text-base font-medium text-slate-700 dark:text-slate-300">
                                No agents match your filter criteria
                            </p>
                            <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
                                Try adjusting search terms, tier options, or date ranges.
                            </p>
                            {hasActiveFilters && (
                                <button
                                    onClick={handleResetFilters}
                                    className="mt-4 px-4 py-2 text-xs font-semibold bg-blue-600 hover:bg-blue-700 text-white rounded-xl shadow-xs transition-colors cursor-pointer"
                                >
                                    Reset All Filters
                                </button>
                            )}
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-xs divide-y divide-slate-100 dark:divide-slate-800/80">
                                <thead className="bg-slate-50 dark:bg-slate-900/60 uppercase font-semibold text-[11px] text-slate-500 dark:text-slate-400">
                                    <tr>
                                        <th className="px-5 py-3.5">Agent Name & Code</th>
                                        <th className="px-5 py-3.5">Tier / Role</th>
                                        <th className="px-5 py-3.5">Sponsor (Parent)</th>
                                        <th className="px-5 py-3.5">Direct Downlines</th>
                                        <th className="px-5 py-3.5">KYC Status</th>
                                        <th className="px-5 py-3.5">Account Status</th>
                                        <th className="px-5 py-3.5">Registered</th>
                                        <th className="px-5 py-3.5 text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100 dark:divide-slate-800/60 font-sans">
                                    {agents.data.map((agent) => (
                                        <tr
                                            key={agent.id}
                                            className="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition-colors"
                                        >
                                            {/* Name & Contact */}
                                            <td className="px-5 py-4 whitespace-nowrap">
                                                <Link
                                                    href={route('admin.agents.show', agent.id)}
                                                    className="font-bold text-slate-900 dark:text-white hover:text-blue-600 dark:hover:text-blue-400 transition-colors"
                                                >
                                                    {agent.name}
                                                </Link>
                                                <div className="text-[11px] text-slate-500 dark:text-slate-400 font-mono mt-0.5">
                                                    <span className="font-bold text-slate-700 dark:text-slate-300">
                                                        {agent.referral_code ?? 'NO-CODE'}
                                                    </span>
                                                    {' '}&bull; {agent.email}
                                                </div>
                                            </td>

                                            {/* Tier Role */}
                                            <td className="px-5 py-4 whitespace-nowrap">
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

                                            {/* Parent Sponsor */}
                                            <td className="px-5 py-4 whitespace-nowrap">
                                                {agent.parent ? (
                                                    <div>
                                                        <span className="font-semibold text-slate-800 dark:text-slate-200">
                                                            {agent.parent.name}
                                                        </span>
                                                        <div className="text-[10px] font-mono text-slate-400 dark:text-slate-500">
                                                            {agent.parent.referral_code}
                                                        </div>
                                                    </div>
                                                ) : (
                                                    <span className="text-slate-400 dark:text-slate-500 italic">
                                                        Company Direct (Top Level)
                                                    </span>
                                                )}
                                            </td>

                                            {/* Downlines */}
                                            <td className="px-5 py-4 whitespace-nowrap font-mono text-slate-700 dark:text-slate-300 font-semibold">
                                                {agent.children_count ?? 0} direct
                                            </td>

                                            {/* KYC Status */}
                                            <td className="px-5 py-4 whitespace-nowrap">
                                                {getKycBadge(agent.agent_profile?.kyc_status)}
                                            </td>

                                            {/* Account Status */}
                                            <td className="px-5 py-4 whitespace-nowrap">
                                                {getStatusBadge(agent.status)}
                                            </td>

                                            {/* Registered Date */}
                                            <td className="px-5 py-4 whitespace-nowrap text-slate-500 dark:text-slate-400 font-mono">
                                                {agent.created_at
                                                    ? new Date(agent.created_at).toLocaleDateString()
                                                    : '—'}
                                            </td>

                                            {/* Actions */}
                                            <td className="px-5 py-4 text-right whitespace-nowrap">
                                                <div className="flex items-center justify-end gap-2">
                                                    {agent.status === 'pending' && canManageApplications && (
                                                        <>
                                                            <button
                                                                type="button"
                                                                onClick={() => handleApprove(agent.id, agent.name)}
                                                                className="px-2.5 py-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-semibold transition-colors cursor-pointer shadow-xs"
                                                            >
                                                                Approve
                                                            </button>
                                                            <button
                                                                type="button"
                                                                onClick={() => setRejectingAgentId(agent.id)}
                                                                className="px-2.5 py-1 bg-rose-100 dark:bg-rose-950/60 hover:bg-rose-200 text-rose-800 dark:text-rose-300 border border-rose-200 dark:border-rose-900/60 rounded-lg text-xs font-semibold transition-colors cursor-pointer"
                                                            >
                                                                Reject
                                                            </button>
                                                        </>
                                                    )}
                                                    <Link
                                                        href={route('admin.agents.show', agent.id)}
                                                        className="px-2.5 py-1 text-xs font-semibold text-slate-700 dark:text-slate-200 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 rounded-lg transition-colors"
                                                    >
                                                        Details
                                                    </Link>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}

                    {/* Pagination */}
                    {agents.last_page > 1 && (
                        <div className="p-4 border-t border-slate-100 dark:border-slate-800/80 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 text-xs text-slate-500 dark:text-slate-400">
                            <div>
                                Showing <span className="font-semibold text-slate-900 dark:text-white">{agents.from ?? 0}</span> to{' '}
                                <span className="font-semibold text-slate-900 dark:text-white">{agents.to ?? 0}</span> of{' '}
                                <span className="font-semibold text-slate-900 dark:text-white">{agents.total}</span> agents
                            </div>

                            <div className="flex items-center gap-1">
                                {agents.links.map((link, idx) => {
                                    if (!link.url) {
                                        return (
                                            <span
                                                key={idx}
                                                dangerouslySetInnerHTML={{ __html: link.label }}
                                                className="px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-800 text-slate-400 dark:text-slate-600 opacity-60 text-xs"
                                            />
                                        );
                                    }
                                    return (
                                        <Link
                                            key={idx}
                                            href={link.url}
                                            preserveState
                                            dangerouslySetInnerHTML={{ __html: link.label }}
                                            className={`px-3 py-1.5 rounded-lg border text-xs font-semibold transition-colors ${
                                                link.active
                                                    ? 'bg-blue-600 text-white border-blue-600 shadow-xs'
                                                    : 'border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800'
                                            }`}
                                        />
                                    );
                                })}
                            </div>
                        </div>
                    )}
                </div>

                {/* 6. Rejection Modal Dialog */}
                {rejectingAgentId && (
                    <div className="fixed inset-0 z-50 bg-slate-900/60 dark:bg-black/80 backdrop-blur-xs flex items-center justify-center p-4">
                        <div className="bg-white dark:bg-[#0F172A] rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-200/80 dark:border-slate-800 text-slate-900 dark:text-white">
                            <h3 className="text-base font-serif font-bold text-slate-900 dark:text-white">
                                Reject Agent Application
                            </h3>
                            <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
                                State the reason for rejecting this agent application. The applicant will be notified.
                            </p>

                            <div className="mt-4">
                                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase mb-1">
                                    Rejection Reason *
                                </label>
                                <textarea
                                    value={rejectReason}
                                    onChange={(e) => setRejectReason(e.target.value)}
                                    rows={3}
                                    placeholder="e.g. Incomplete documentation, invalid national identity card, or unverified contact details..."
                                    className="w-full px-3 py-2 border border-slate-300 dark:border-slate-700 rounded-xl bg-white dark:bg-[#1E293B] text-slate-900 dark:text-white text-xs focus:ring-2 focus:ring-rose-500 focus:border-rose-500"
                                />
                            </div>

                            <div className="mt-5 flex justify-end gap-2">
                                <button
                                    type="button"
                                    onClick={() => {
                                        setRejectingAgentId(null);
                                        setRejectReason('');
                                    }}
                                    className="px-4 py-2 text-xs font-semibold text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="button"
                                    onClick={() => handleConfirmReject(rejectingAgentId)}
                                    className="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded-xl text-xs font-bold cursor-pointer shadow-xs"
                                >
                                    Confirm Rejection
                                </button>
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </AdminLayout>
    );
}
