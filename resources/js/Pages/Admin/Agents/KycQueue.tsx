import React, { useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import axios from 'axios';
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
    per_page: number;
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

interface KycQueueProps extends PageProps {
    agents: PaginatedAgents;
    filters: {
        status?: string;
        role?: string;
        search?: string;
        from_date?: string;
        to_date?: string;
    };
    stats: {
        total: number;
        pending: number;
        verified: number;
        rejected: number;
    };
    canManageKyc: boolean;
    canUnmaskPii: boolean;
}

interface UnmaskedData {
    nic: string | null;
    bank_account_number: string | null;
    bank_name: string | null;
    bank_branch: string | null;
    bank_account_name: string | null;
    address: string | null;
    business_name: string | null;
}

export default function KycQueue({
    agents,
    filters,
    stats,
    canManageKyc,
    canUnmaskPii,
}: KycQueueProps) {
    const { flash } = usePage<PageProps>().props;

    // Filters state
    const [search, setSearch] = useState(filters.search || '');
    const [status, setStatus] = useState(filters.status || 'pending');
    const [role, setRole] = useState(filters.role || '');
    const [fromDate, setFromDate] = useState(filters.from_date || '');
    const [toDate, setToDate] = useState(filters.to_date || '');

    // Verification Modal State
    const [verifyingAgent, setVerifyingAgent] = useState<ExtendedUser | null>(null);
    const [verifyNotes, setVerifyNotes] = useState('');
    const [isVerifying, setIsVerifying] = useState(false);

    // Rejection Modal State
    const [rejectingAgent, setRejectingAgent] = useState<ExtendedUser | null>(null);
    const [rejectReason, setRejectReason] = useState('');
    const [rejectError, setRejectError] = useState('');
    const [isRejecting, setIsRejecting] = useState(false);

    // Secure PII Inspection & Unmasking Modal State
    const [inspectingAgent, setInspectingAgent] = useState<ExtendedUser | null>(null);
    const [unmaskReason, setUnmaskReason] = useState('KYC identity verification');
    const [unmaskedData, setUnmaskedData] = useState<UnmaskedData | null>(null);
    const [isUnmasking, setIsUnmasking] = useState(false);
    const [unmaskError, setUnmaskError] = useState('');

    // Apply query filters
    const applyFilters = (overrides: Record<string, string | undefined> = {}) => {
        const queryParams: Record<string, string | undefined> = {
            search: search.trim() || undefined,
            status: status || undefined,
            role: role || undefined,
            from_date: fromDate || undefined,
            to_date: toDate || undefined,
            ...overrides,
        };

        Object.keys(queryParams).forEach((key) => {
            if (queryParams[key] === undefined || queryParams[key] === '') {
                delete queryParams[key];
            }
        });

        router.get(route('admin.agents.kyc.queue'), queryParams, { preserveState: true });
    };

    const handleSearchSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        applyFilters();
    };

    const handleResetFilters = () => {
        setSearch('');
        setStatus('');
        setRole('');
        setFromDate('');
        setToDate('');
        router.get(route('admin.agents.kyc.queue'), {}, { preserveState: true });
    };

    // Handle Verify Submit
    const handleVerifySubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!verifyingAgent) return;
        setIsVerifying(true);

        router.post(
            route('admin.agents.kyc.update', verifyingAgent.id),
            {
                kyc_status: 'verified',
                kyc_notes: verifyNotes.trim() || undefined,
            },
            {
                onSuccess: () => {
                    setVerifyingAgent(null);
                    setVerifyNotes('');
                    setIsVerifying(false);
                    // Also close inspecting modal if it was open
                    if (inspectingAgent?.id === verifyingAgent.id) {
                        setInspectingAgent(null);
                        setUnmaskedData(null);
                    }
                },
                onError: () => {
                    setIsVerifying(false);
                },
            }
        );
    };

    // Handle Reject Submit (Mandatory reason)
    const handleRejectSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!rejectingAgent) return;

        if (!rejectReason.trim()) {
            setRejectError('A clear rejection reason is mandatory for compliance and agent notification.');
            return;
        }

        setIsRejecting(true);
        setRejectError('');

        router.post(
            route('admin.agents.kyc.update', rejectingAgent.id),
            {
                kyc_status: 'rejected',
                kyc_notes: rejectReason.trim(),
                rejection_reason: rejectReason.trim(),
            },
            {
                onSuccess: () => {
                    setRejectingAgent(null);
                    setRejectReason('');
                    setIsRejecting(false);
                    if (inspectingAgent?.id === rejectingAgent.id) {
                        setInspectingAgent(null);
                        setUnmaskedData(null);
                    }
                },
                onError: (errors) => {
                    setIsRejecting(false);
                    if (errors.kyc_notes || errors.rejection_reason) {
                        setRejectError(errors.kyc_notes || errors.rejection_reason || 'Rejection failed.');
                    }
                },
            }
        );
    };

    // Handle Secure On-Demand PII Unmasking
    const handleTriggerUnmask = async () => {
        if (!inspectingAgent) return;
        setIsUnmasking(true);
        setUnmaskError('');

        try {
            const response = await axios.post(route('admin.agents.unmask-pii', inspectingAgent.id), {
                reason: unmaskReason.trim() || 'KYC identity verification in KYC Queue',
            });

            if (response.data && response.data.success) {
                setUnmaskedData({
                    nic: response.data.nic,
                    bank_account_number: response.data.bank_account_number,
                    bank_name: response.data.bank_name,
                    bank_branch: response.data.bank_branch,
                    bank_account_name: response.data.bank_account_name,
                    address: response.data.address,
                    business_name: response.data.business_name,
                });
            } else {
                setUnmaskError('Failed to unmask sensitive identity details.');
            }
        } catch (err: any) {
            setUnmaskError(
                err?.response?.data?.error ||
                'Access Denied: You do not have permission to unmask sensitive personal records.'
            );
        } finally {
            setIsUnmasking(false);
        }
    };

    const hasActiveFilters = Boolean(
        search || (status && status !== 'pending') || role || fromDate || toDate
    );

    // Status Badge Component
    const renderKycStatusBadge = (agentStatus?: string | null) => {
        const s = agentStatus || 'pending';
        switch (s) {
            case 'verified':
                return (
                    <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-100 dark:bg-emerald-950/60 text-emerald-800 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800/60">
                        <Icons.Check className="w-3.5 h-3.5 text-emerald-600 dark:text-emerald-400" />
                        Verified
                    </span>
                );
            case 'rejected':
                return (
                    <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-rose-100 dark:bg-rose-950/60 text-rose-800 dark:text-rose-300 border border-rose-200 dark:border-rose-800/60">
                        <Icons.XCircle className="w-3.5 h-3.5 text-rose-600 dark:text-rose-400" />
                        Rejected
                    </span>
                );
            case 'pending':
            default:
                return (
                    <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-100 dark:bg-amber-950/60 text-amber-800 dark:text-amber-300 border border-amber-200 dark:border-amber-800/60">
                        <span className="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse" />
                        Pending Verification
                    </span>
                );
        }
    };

    return (
        <AdminLayout>
            <Head title="Agent KYC Verification Queue" />

            <div className="space-y-6">
                {/* Header Banner */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white dark:bg-[#0F172A] p-6 rounded-2xl border border-slate-200/80 dark:border-slate-800/80 shadow-xs transition-colors">
                    <div>
                        <div className="flex items-center gap-2 mb-1">
                            <span className="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-100 dark:bg-blue-950/60 text-blue-800 dark:text-blue-300 border border-blue-200 dark:border-blue-800/60">
                                Identity & Compliance
                            </span>
                            <span className="text-xs text-slate-400 dark:text-slate-500 font-mono">
                                Least-Privilege PII Protected
                            </span>
                        </div>
                        <h1 className="text-xl sm:text-2xl font-serif font-bold text-slate-900 dark:text-white tracking-tight">
                            Agent KYC Verification Queue
                        </h1>
                        <p className="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1">
                            Review National Identity Cards (NIC), banking credentials, and verify identity compliance for network agents. Sensitive PII remains masked until authorized on-demand inspection.
                        </p>
                    </div>

                    <div className="flex items-center gap-3">
                        <Link
                            href={route('admin.agents.index')}
                            className="inline-flex items-center gap-2 px-4 py-2 border border-slate-300 dark:border-slate-700 rounded-xl text-xs font-semibold text-slate-700 dark:text-slate-200 bg-white dark:bg-[#0F172A] hover:bg-slate-50 dark:hover:bg-slate-800 shadow-xs transition-colors"
                        >
                            <Icons.Users className="w-4 h-4 text-slate-500" />
                            Agent Directory
                        </Link>
                        <Link
                            href={route('admin.agents.invitations.index')}
                            className="inline-flex items-center gap-2 px-4 py-2 bg-slate-900 hover:bg-slate-800 dark:bg-blue-600 dark:hover:bg-blue-500 text-white rounded-xl text-xs font-semibold shadow-xs transition-colors"
                        >
                            <Icons.Mail className="w-4 h-4" />
                            Recruitment & Invitations
                        </Link>
                    </div>
                </div>

                {/* KPI Statistics Badges */}
                <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    <button
                        type="button"
                        onClick={() => {
                            setStatus('pending');
                            applyFilters({ status: 'pending' });
                        }}
                        className={`p-4 rounded-2xl border text-left transition-all cursor-pointer shadow-xs ${
                            status === 'pending'
                                ? 'bg-amber-600 text-white border-amber-600 ring-2 ring-amber-500/30'
                                : 'bg-white dark:bg-[#0F172A] text-amber-800 dark:text-amber-400 border-slate-200/80 dark:border-slate-800/80 hover:bg-amber-50/50 dark:hover:bg-amber-950/30'
                        }`}
                    >
                        <div className="text-[11px] uppercase tracking-wider font-semibold opacity-80 flex items-center gap-1.5">
                            <span className="w-2 h-2 rounded-full bg-amber-500 animate-pulse" />
                            Pending Review
                        </div>
                        <div className="text-2xl font-bold font-mono mt-1">{stats.pending}</div>
                        <div className="text-[11px] opacity-80 mt-0.5">Awaiting Verification</div>
                    </button>

                    <button
                        type="button"
                        onClick={() => {
                            setStatus('verified');
                            applyFilters({ status: 'verified' });
                        }}
                        className={`p-4 rounded-2xl border text-left transition-all cursor-pointer shadow-xs ${
                            status === 'verified'
                                ? 'bg-emerald-600 text-white border-emerald-600 ring-2 ring-emerald-500/30'
                                : 'bg-white dark:bg-[#0F172A] text-emerald-800 dark:text-emerald-400 border-slate-200/80 dark:border-slate-800/80 hover:bg-emerald-50/50 dark:hover:bg-emerald-950/30'
                        }`}
                    >
                        <div className="text-[11px] uppercase tracking-wider font-semibold opacity-80 flex items-center gap-1.5">
                            <Icons.CheckCircle2 className="w-3.5 h-3.5 text-emerald-500" />
                            Verified Agents
                        </div>
                        <div className="text-2xl font-bold font-mono mt-1">{stats.verified}</div>
                        <div className="text-[11px] opacity-80 mt-0.5">Compliant Profiles</div>
                    </button>

                    <button
                        type="button"
                        onClick={() => {
                            setStatus('rejected');
                            applyFilters({ status: 'rejected' });
                        }}
                        className={`p-4 rounded-2xl border text-left transition-all cursor-pointer shadow-xs ${
                            status === 'rejected'
                                ? 'bg-rose-600 text-white border-rose-600 ring-2 ring-rose-500/30'
                                : 'bg-white dark:bg-[#0F172A] text-rose-800 dark:text-rose-400 border-slate-200/80 dark:border-slate-800/80 hover:bg-rose-50/50 dark:hover:bg-rose-950/30'
                        }`}
                    >
                        <div className="text-[11px] uppercase tracking-wider font-semibold opacity-80 flex items-center gap-1.5">
                            <Icons.XCircle className="w-3.5 h-3.5 text-rose-500" />
                            Rejected
                        </div>
                        <div className="text-2xl font-bold font-mono mt-1">{stats.rejected}</div>
                        <div className="text-[11px] opacity-80 mt-0.5">Requires Resubmission</div>
                    </button>

                    <button
                        type="button"
                        onClick={() => {
                            setStatus('all');
                            applyFilters({ status: 'all' });
                        }}
                        className={`p-4 rounded-2xl border text-left transition-all cursor-pointer shadow-xs ${
                            status === 'all'
                                ? 'bg-slate-900 dark:bg-blue-600 text-white border-slate-900 dark:border-blue-600 ring-2 ring-blue-500/30'
                                : 'bg-white dark:bg-[#0F172A] text-slate-800 dark:text-slate-200 border-slate-200/80 dark:border-slate-800/80 hover:bg-slate-50 dark:hover:bg-slate-800/50'
                        }`}
                    >
                        <div className="text-[11px] uppercase tracking-wider font-semibold opacity-70">
                            Total Submissions
                        </div>
                        <div className="text-2xl font-bold font-mono mt-1">{stats.total}</div>
                        <div className="text-[11px] opacity-70 mt-0.5">All Recorded Agents</div>
                    </button>
                </div>

                {/* Filter and Search Bar */}
                <div className="bg-white dark:bg-[#0F172A] rounded-2xl border border-slate-200/80 dark:border-slate-800/80 p-5 shadow-xs transition-colors">
                    <form onSubmit={handleSearchSubmit} className="flex flex-wrap items-center gap-3 text-xs">
                        {/* Search Input */}
                        <div className="flex-1 min-w-[240px] relative">
                            <span className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                                <Icons.Search className="w-4 h-4" />
                            </span>
                            <input
                                type="text"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Search by agent name, email, referral code, or phone..."
                                className="w-full pl-9 pr-3 py-2 border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/80 text-slate-900 dark:text-white rounded-xl text-xs placeholder:text-slate-400 dark:placeholder:text-slate-500 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                            />
                        </div>

                        {/* KYC Status Filter */}
                        <select
                            value={status}
                            onChange={(e) => {
                                setStatus(e.target.value);
                                applyFilters({ status: e.target.value });
                            }}
                            className="px-3 py-2 border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/80 text-slate-900 dark:text-white rounded-xl text-xs focus:ring-blue-500 focus:border-blue-500"
                        >
                            <option value="pending">Pending Verification</option>
                            <option value="verified">Verified</option>
                            <option value="rejected">Rejected</option>
                            <option value="all">All Submissions</option>
                        </select>

                        {/* Role / Tier Filter */}
                        <select
                            value={role}
                            onChange={(e) => {
                                setRole(e.target.value);
                                applyFilters({ role: e.target.value || undefined });
                            }}
                            className="px-3 py-2 border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/80 text-slate-900 dark:text-white rounded-xl text-xs focus:ring-blue-500 focus:border-blue-500"
                        >
                            <option value="">All Agent Tiers</option>
                            <option value="high_level_agent">High-Level Agent (HLA)</option>
                            <option value="low_level_agent">Low-Level Agent (LLA)</option>
                        </select>

                        {/* From Date */}
                        <input
                            type="date"
                            value={fromDate}
                            onChange={(e) => {
                                setFromDate(e.target.value);
                                applyFilters({ from_date: e.target.value || undefined });
                            }}
                            className="px-3 py-2 border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/80 text-slate-900 dark:text-white rounded-xl text-xs focus:ring-blue-500 focus:border-blue-500"
                            title="From Submission Date"
                        />

                        {/* To Date */}
                        <input
                            type="date"
                            value={toDate}
                            onChange={(e) => {
                                setToDate(e.target.value);
                                applyFilters({ to_date: e.target.value || undefined });
                            }}
                            className="px-3 py-2 border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/80 text-slate-900 dark:text-white rounded-xl text-xs focus:ring-blue-500 focus:border-blue-500"
                            title="To Submission Date"
                        />

                        {/* Submit Button */}
                        <button
                            type="submit"
                            className="px-5 py-2 bg-slate-900 dark:bg-blue-600 hover:bg-slate-800 dark:hover:bg-blue-500 text-white font-semibold rounded-xl cursor-pointer transition-colors shadow-xs"
                        >
                            Filter
                        </button>

                        {/* Reset Filters */}
                        {hasActiveFilters && (
                            <button
                                type="button"
                                onClick={handleResetFilters}
                                className="px-3 py-2 text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white cursor-pointer underline transition-colors"
                            >
                                Reset
                            </button>
                        )}
                    </form>
                </div>

                {/* Queue Table */}
                <div className="bg-white dark:bg-[#0F172A] rounded-2xl border border-slate-200/80 dark:border-slate-800/80 shadow-xs overflow-hidden transition-colors">
                    {agents.data.length === 0 ? (
                        <div className="p-16 text-center">
                            <div className="w-12 h-12 rounded-2xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center mx-auto mb-3 text-slate-400">
                                <Icons.ShieldCheck className="w-6 h-6" />
                            </div>
                            <h3 className="text-sm font-semibold text-slate-900 dark:text-white">
                                No KYC submissions found
                            </h3>
                            <p className="text-xs text-slate-500 dark:text-slate-400 mt-1 max-w-sm mx-auto">
                                {hasActiveFilters
                                    ? 'No agent records matched the specified filter criteria. Try clearing or expanding your filters.'
                                    : 'There are currently no agent KYC records awaiting verification in this queue.'}
                            </p>
                            {hasActiveFilters && (
                                <button
                                    type="button"
                                    onClick={handleResetFilters}
                                    className="mt-4 inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold text-blue-600 dark:text-blue-400 hover:underline"
                                >
                                    <Icons.RotateCcw className="w-3.5 h-3.5" />
                                    Clear all filters
                                </button>
                            )}
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-slate-100 dark:divide-slate-800/80 text-left text-xs bg-white dark:bg-[#0F172A]">
                                <thead className="bg-slate-50 dark:bg-slate-900/60 text-slate-500 dark:text-slate-400 uppercase font-semibold text-[11px]">
                                    <tr>
                                        <th className="px-6 py-3.5">Agent Candidate</th>
                                        <th className="px-6 py-3.5">Tier & Upline</th>
                                        <th className="px-6 py-3.5">National Identity (NIC)</th>
                                        <th className="px-6 py-3.5">Banking Information</th>
                                        <th className="px-6 py-3.5">KYC Status</th>
                                        <th className="px-6 py-3.5">Remarks / Reason</th>
                                        <th className="px-6 py-3.5 text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100 dark:divide-slate-800/60 font-sans bg-white dark:bg-[#0F172A]">
                                    {agents.data.map((agent) => {
                                        const profile = agent.agent_profile;
                                        const isHla = agent.role === 'high_level_agent';
                                        const kycStatus = profile?.kyc_status || 'pending';

                                        return (
                                            <tr
                                                key={agent.id}
                                                className="bg-white dark:bg-[#0F172A] hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition-colors"
                                            >
                                                {/* Agent Identity */}
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <div className="font-semibold text-slate-900 dark:text-white">
                                                        <Link
                                                            href={route('admin.agents.show', agent.id)}
                                                            className="hover:text-blue-600 dark:hover:text-blue-400 hover:underline"
                                                        >
                                                            {agent.name}
                                                        </Link>
                                                    </div>
                                                    <div className="text-[11px] text-slate-500 dark:text-slate-400 font-mono mt-0.5">
                                                        {agent.referral_code} • {agent.email}
                                                    </div>
                                                    <div className="text-[11px] text-slate-400 dark:text-slate-500 mt-0.5">
                                                        {agent.phone || 'No phone'}
                                                    </div>
                                                </td>

                                                {/* Tier & Upline */}
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    {isHla ? (
                                                        <div>
                                                            <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-semibold bg-purple-100 text-purple-800 dark:bg-purple-950/60 dark:text-purple-300 border border-purple-200 dark:border-purple-800/60">
                                                                High-Level Agent
                                                            </span>
                                                            <div className="text-[11px] text-slate-500 dark:text-slate-400 mt-1">
                                                                Company Direct
                                                            </div>
                                                        </div>
                                                    ) : (
                                                        <div>
                                                            <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-semibold bg-sky-100 text-sky-800 dark:bg-sky-950/60 dark:text-sky-300 border border-sky-200 dark:border-sky-800/60">
                                                                Low-Level Agent
                                                            </span>
                                                            <div className="text-[11px] text-slate-500 dark:text-slate-400 mt-1">
                                                                Sponsor: {agent.parent?.name || 'HLA Sponsor'}
                                                            </div>
                                                        </div>
                                                    )}
                                                </td>

                                                {/* National Identity (NIC) */}
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <div className="flex items-center gap-2">
                                                        <span className="font-mono text-slate-900 dark:text-white font-semibold">
                                                            {profile?.nic || 'Not Submitted'}
                                                        </span>
                                                        {canUnmaskPii && profile?.nic && (
                                                            <button
                                                                type="button"
                                                                onClick={() => {
                                                                    setInspectingAgent(agent);
                                                                    setUnmaskedData(null);
                                                                    setUnmaskError('');
                                                                }}
                                                                title="Inspect and unmask sensitive credentials (Audited)"
                                                                className="text-slate-400 hover:text-blue-600 dark:hover:text-blue-400 cursor-pointer transition-colors p-1 rounded-md hover:bg-slate-100 dark:hover:bg-slate-800"
                                                            >
                                                                <Icons.Eye className="w-3.5 h-3.5" />
                                                            </button>
                                                        )}
                                                    </div>
                                                    <div className="text-[11px] text-slate-400 dark:text-slate-500 mt-0.5 truncate max-w-[200px]" title={profile?.address || ''}>
                                                        {profile?.address || 'No address'}
                                                    </div>
                                                </td>

                                                {/* Banking Credentials */}
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <div className="font-medium text-slate-900 dark:text-white">
                                                        {profile?.bank_name || 'Bank Not Set'}
                                                        {profile?.bank_branch ? ` (${profile.bank_branch})` : ''}
                                                    </div>
                                                    <div className="text-[11px] text-slate-500 dark:text-slate-400 font-mono mt-0.5">
                                                        Account: {profile?.bank_account_number || '****'}
                                                    </div>
                                                    <div className="text-[11px] text-slate-400 dark:text-slate-500">
                                                        Holder: {profile?.bank_account_name || agent.name}
                                                    </div>
                                                </td>

                                                {/* KYC Status */}
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    {renderKycStatusBadge(kycStatus)}
                                                </td>

                                                {/* Remarks / Notes */}
                                                <td className="px-6 py-4 max-w-[220px]">
                                                    {profile?.kyc_notes ? (
                                                        <span className="text-[11px] text-slate-600 dark:text-slate-300 italic line-clamp-2">
                                                            "{profile.kyc_notes}"
                                                        </span>
                                                    ) : (
                                                        <span className="text-[11px] text-slate-400 dark:text-slate-500">
                                                            —
                                                        </span>
                                                    )}
                                                </td>

                                                {/* Actions */}
                                                <td className="px-6 py-4 text-right whitespace-nowrap">
                                                    <div className="flex items-center justify-end gap-1.5">
                                                        {canUnmaskPii && (
                                                            <button
                                                                type="button"
                                                                onClick={() => {
                                                                    setInspectingAgent(agent);
                                                                    setUnmaskedData(null);
                                                                    setUnmaskError('');
                                                                }}
                                                                className="px-2.5 py-1 text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg text-xs font-medium transition-colors cursor-pointer border border-slate-200 dark:border-slate-700"
                                                            >
                                                                Inspect
                                                            </button>
                                                        )}

                                                        {canManageKyc && kycStatus !== 'verified' && (
                                                            <button
                                                                type="button"
                                                                onClick={() => {
                                                                    setVerifyingAgent(agent);
                                                                    setVerifyNotes('');
                                                                }}
                                                                className="px-2.5 py-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-semibold shadow-xs transition-colors cursor-pointer"
                                                            >
                                                                Verify
                                                            </button>
                                                        )}

                                                        {canManageKyc && kycStatus !== 'rejected' && (
                                                            <button
                                                                type="button"
                                                                onClick={() => {
                                                                    setRejectingAgent(agent);
                                                                    setRejectReason('');
                                                                    setRejectError('');
                                                                }}
                                                                className="px-2.5 py-1 bg-rose-600 hover:bg-rose-700 text-white rounded-lg text-xs font-semibold shadow-xs transition-colors cursor-pointer"
                                                            >
                                                                Reject
                                                            </button>
                                                        )}

                                                        <Link
                                                            href={route('admin.agents.show', agent.id)}
                                                            className="p-1 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition-colors"
                                                            title="Full Agent Profile"
                                                        >
                                                            <Icons.ChevronRight className="w-4 h-4" />
                                                        </Link>
                                                    </div>
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    )}

                    {/* Pagination */}
                    {agents.total > agents.per_page && (
                        <div className="p-4 border-t border-slate-200/80 dark:border-slate-800/80 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-slate-500 dark:text-slate-400">
                            <div>
                                Showing <span className="font-semibold text-slate-900 dark:text-white">{agents.from ?? 0}</span> to{' '}
                                <span className="font-semibold text-slate-900 dark:text-white">{agents.to ?? 0}</span> of{' '}
                                <span className="font-semibold text-slate-900 dark:text-white">{agents.total}</span> agents
                            </div>
                            <div className="flex items-center gap-1">
                                {agents.links.map((link, idx) => (
                                    <button
                                        key={idx}
                                        type="button"
                                        disabled={!link.url}
                                        onClick={() => link.url && router.get(link.url, {}, { preserveState: true })}
                                        className={`px-3 py-1.5 rounded-lg border text-xs font-medium transition-colors ${
                                            link.active
                                                ? 'bg-slate-900 dark:bg-blue-600 text-white border-slate-900 dark:border-blue-600'
                                                : link.url
                                                ? 'bg-white dark:bg-[#0F172A] border-slate-200 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800'
                                                : 'bg-slate-50 dark:bg-slate-900 text-slate-300 dark:text-slate-700 border-slate-100 dark:border-slate-800 cursor-not-allowed'
                                        }`}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                    />
                                ))}
                            </div>
                        </div>
                    )}
                </div>

                {/* Privacy & Governance Notice Box */}
                <div className="bg-slate-50 dark:bg-[#0F172A]/60 border border-slate-200/80 dark:border-slate-800/80 rounded-2xl p-5 text-xs text-slate-600 dark:text-slate-400 space-y-2">
                    <div className="flex items-center gap-2 font-semibold text-slate-900 dark:text-white font-serif">
                        <Icons.Shield className="w-4 h-4 text-blue-500" />
                        Aura Luxe Least-Privilege PII Protection & KYC Compliance Protocol
                    </div>
                    <ul className="list-disc list-inside space-y-1 text-slate-500 dark:text-slate-400">
                        <li>
                            <strong className="text-slate-700 dark:text-slate-300">Data Masking by Default:</strong> National Identity Cards and bank account numbers are redacted across general queues to prevent shoulder-surfing and unauthorized internal data collection.
                        </li>
                        <li>
                            <strong className="text-slate-700 dark:text-slate-300">Audited On-Demand Unmasking:</strong> Revealing unmasked credentials requires authorized credentials and generates an immutable <code className="font-mono text-[11px] text-blue-600 dark:text-blue-400">agent_pii_unmasked</code> audit log containing actor identity, target agent, timestamp, and verification purpose.
                        </li>
                        <li>
                            <strong className="text-slate-700 dark:text-slate-300">Mandatory Rejection Reasons:</strong> Any KYC rejection must be accompanied by an explanatory reason. The candidate receives an immediate automated notification to correct their documentation.
                        </li>
                    </ul>
                </div>
            </div>

            {/* Secure PII Inspection & Unmasking Modal */}
            {inspectingAgent && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 dark:bg-black/80 backdrop-blur-xs">
                    <div className="bg-white dark:bg-[#0F172A] border border-slate-200 dark:border-slate-800 rounded-2xl p-6 max-w-lg w-full shadow-2xl space-y-5 transition-all">
                        <div className="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-4">
                            <div className="flex items-center gap-2.5">
                                <div className="w-9 h-9 rounded-xl bg-blue-100 dark:bg-blue-950/60 flex items-center justify-center text-blue-600 dark:text-blue-400">
                                    <Icons.Lock className="w-5 h-5" />
                                </div>
                                <div>
                                    <h2 className="text-base font-serif font-bold text-slate-900 dark:text-white">
                                        Secure Identity & PII Inspection
                                    </h2>
                                    <p className="text-xs text-slate-500 dark:text-slate-400">
                                        Agent: <span className="font-semibold text-slate-800 dark:text-slate-200">{inspectingAgent.name}</span> ({inspectingAgent.referral_code})
                                    </p>
                                </div>
                            </div>
                            <button
                                type="button"
                                onClick={() => {
                                    setInspectingAgent(null);
                                    setUnmaskedData(null);
                                }}
                                className="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 cursor-pointer"
                            >
                                <Icons.XCircle className="w-5 h-5" />
                            </button>
                        </div>

                        {!unmaskedData ? (
                            <div className="space-y-4 text-xs">
                                <div className="bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800/80 rounded-xl p-3.5 text-amber-900 dark:text-amber-200 space-y-1">
                                    <div className="font-semibold flex items-center gap-1.5">
                                        <Icons.ShieldAlert className="w-4 h-4 text-amber-600" />
                                        Immutable Security Audit Advisory
                                    </div>
                                    <p className="text-[11px] leading-relaxed">
                                        National Identity Card (NIC) and full banking details are restricted under platform data privacy rules. Accessing unmasked credentials will log an <code className="font-mono font-bold text-amber-700 dark:text-amber-300">agent_pii_unmasked</code> event with your staff identity and timestamp.
                                    </p>
                                </div>

                                <div>
                                    <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                        Reason for Unmasking & Inspection
                                    </label>
                                    <input
                                        type="text"
                                        value={unmaskReason}
                                        onChange={(e) => setUnmaskReason(e.target.value)}
                                        placeholder="e.g. Identity document verification for commission compliance"
                                        className="w-full px-3 py-2 border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white rounded-xl text-xs focus:ring-blue-500 focus:border-blue-500"
                                    />
                                </div>

                                {unmaskError && (
                                    <div className="p-3 rounded-xl bg-rose-50 dark:bg-rose-950/50 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-900 text-xs">
                                        {unmaskError}
                                    </div>
                                )}

                                <div className="flex items-center justify-end gap-3 pt-3 border-t border-slate-200 dark:border-slate-800">
                                    <button
                                        type="button"
                                        onClick={() => setInspectingAgent(null)}
                                        className="px-4 py-2 border border-slate-300 dark:border-slate-700 rounded-xl text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors cursor-pointer"
                                    >
                                        Cancel
                                    </button>
                                    <button
                                        type="button"
                                        disabled={isUnmasking}
                                        onClick={handleTriggerUnmask}
                                        className="inline-flex items-center gap-2 px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-xl text-xs shadow-xs transition-colors cursor-pointer disabled:opacity-50"
                                    >
                                        <Icons.Eye className="w-4 h-4" />
                                        {isUnmasking ? 'Authorizing & Unmasking...' : 'Authorize & Reveal PII'}
                                    </button>
                                </div>
                            </div>
                        ) : (
                            <div className="space-y-4 text-xs">
                                <div className="bg-emerald-50/80 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 rounded-xl p-3 text-[11px] text-emerald-800 dark:text-emerald-300 flex items-center justify-between">
                                    <span className="font-semibold flex items-center gap-1.5">
                                        <Icons.CheckCircle2 className="w-4 h-4 text-emerald-600" />
                                        Audit Log Recorded: <code className="font-mono">agent_pii_unmasked</code>
                                    </span>
                                    <span className="text-[10px] opacity-75">
                                        {new Date().toLocaleTimeString()}
                                    </span>
                                </div>

                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 bg-slate-50 dark:bg-slate-900/60 p-4 rounded-xl border border-slate-200 dark:border-slate-800">
                                    <div>
                                        <div className="text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                            National Identity (NIC)
                                        </div>
                                        <div className="font-mono text-sm font-bold text-slate-900 dark:text-white mt-1">
                                            {unmaskedData.nic || 'Not Provided'}
                                        </div>
                                    </div>

                                    <div>
                                        <div className="text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                            Bank Account Number
                                        </div>
                                        <div className="font-mono text-sm font-bold text-slate-900 dark:text-white mt-1">
                                            {unmaskedData.bank_account_number || 'Not Provided'}
                                        </div>
                                    </div>

                                    <div>
                                        <div className="text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                            Bank & Branch
                                        </div>
                                        <div className="text-xs font-medium text-slate-800 dark:text-slate-200 mt-1">
                                            {unmaskedData.bank_name || '—'} {unmaskedData.bank_branch ? `(${unmaskedData.bank_branch})` : ''}
                                        </div>
                                    </div>

                                    <div>
                                        <div className="text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                            Account Holder
                                        </div>
                                        <div className="text-xs font-medium text-slate-800 dark:text-slate-200 mt-1">
                                            {unmaskedData.bank_account_name || '—'}
                                        </div>
                                    </div>

                                    <div className="sm:col-span-2">
                                        <div className="text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                            Residential / Business Address
                                        </div>
                                        <div className="text-xs text-slate-800 dark:text-slate-200 mt-1">
                                            {unmaskedData.address || '—'}
                                        </div>
                                    </div>
                                </div>

                                <div className="flex items-center justify-between gap-3 pt-3 border-t border-slate-200 dark:border-slate-800">
                                    <button
                                        type="button"
                                        onClick={() => {
                                            setInspectingAgent(null);
                                            setUnmaskedData(null);
                                        }}
                                        className="px-4 py-2 border border-slate-300 dark:border-slate-700 rounded-xl text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors cursor-pointer"
                                    >
                                        Close
                                    </button>

                                    <div className="flex items-center gap-2">
                                        {canManageKyc && (
                                            <>
                                                <button
                                                    type="button"
                                                    onClick={() => {
                                                        setRejectingAgent(inspectingAgent);
                                                        setRejectReason('');
                                                        setRejectError('');
                                                    }}
                                                    className="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white font-semibold rounded-xl text-xs shadow-xs transition-colors cursor-pointer"
                                                >
                                                    Reject KYC
                                                </button>
                                                <button
                                                    type="button"
                                                    onClick={() => {
                                                        setVerifyingAgent(inspectingAgent);
                                                        setVerifyNotes('Verified after inspecting unmasked credentials');
                                                    }}
                                                    className="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded-xl text-xs shadow-xs transition-colors cursor-pointer"
                                                >
                                                    Verify KYC
                                                </button>
                                            </>
                                        )}
                                    </div>
                                </div>
                            </div>
                        )}
                    </div>
                </div>
            )}

            {/* Verify KYC Modal */}
            {verifyingAgent && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 dark:bg-black/80 backdrop-blur-xs">
                    <div className="bg-white dark:bg-[#0F172A] border border-slate-200 dark:border-slate-800 rounded-2xl p-6 max-w-md w-full shadow-2xl space-y-4 transition-all">
                        <div className="flex items-center gap-3 text-emerald-600 dark:text-emerald-400">
                            <div className="w-10 h-10 rounded-xl bg-emerald-100 dark:bg-emerald-950/60 flex items-center justify-center">
                                <Icons.CheckCircle2 className="w-5 h-5" />
                            </div>
                            <div>
                                <h3 className="text-base font-serif font-bold text-slate-900 dark:text-white">
                                    Verify Agent KYC
                                </h3>
                                <p className="text-xs text-slate-500 dark:text-slate-400">
                                    Approve identity compliance for {verifyingAgent.name}
                                </p>
                            </div>
                        </div>

                        <p className="text-xs text-slate-600 dark:text-slate-300">
                            Are you sure you want to approve the KYC compliance submission for{' '}
                            <strong className="text-slate-900 dark:text-white font-semibold">{verifyingAgent.name}</strong>?
                            The agent will be notified and marked as verified in the network.
                        </p>

                        <form onSubmit={handleVerifySubmit} className="space-y-3">
                            <div>
                                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                    Verification Remarks (Optional)
                                </label>
                                <input
                                    type="text"
                                    value={verifyNotes}
                                    onChange={(e) => setVerifyNotes(e.target.value)}
                                    placeholder="e.g. NIC and bank account verified against documentation"
                                    className="w-full px-3 py-2 border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white rounded-xl text-xs focus:ring-blue-500 focus:border-blue-500"
                                />
                            </div>

                            <div className="flex items-center justify-end gap-3 pt-3 border-t border-slate-200 dark:border-slate-800">
                                <button
                                    type="button"
                                    onClick={() => setVerifyingAgent(null)}
                                    className="px-4 py-2 border border-slate-300 dark:border-slate-700 rounded-xl text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors cursor-pointer"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={isVerifying}
                                    className="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded-xl text-xs shadow-xs transition-colors cursor-pointer disabled:opacity-50"
                                >
                                    {isVerifying ? 'Verifying...' : 'Confirm Verification'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Reject KYC Modal */}
            {rejectingAgent && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 dark:bg-black/80 backdrop-blur-xs">
                    <div className="bg-white dark:bg-[#0F172A] border border-slate-200 dark:border-slate-800 rounded-2xl p-6 max-w-md w-full shadow-2xl space-y-4 transition-all">
                        <div className="flex items-center gap-3 text-rose-600 dark:text-rose-400">
                            <div className="w-10 h-10 rounded-xl bg-rose-100 dark:bg-rose-950/60 flex items-center justify-center">
                                <Icons.ShieldAlert className="w-5 h-5" />
                            </div>
                            <div>
                                <h3 className="text-base font-serif font-bold text-slate-900 dark:text-white">
                                    Reject KYC Submission
                                </h3>
                                <p className="text-xs text-slate-500 dark:text-slate-400">
                                    Candidate: {rejectingAgent.name}
                                </p>
                            </div>
                        </div>

                        <p className="text-xs text-slate-600 dark:text-slate-300">
                            Please provide a clear and actionable reason for rejecting the KYC submission for{' '}
                            <strong className="text-slate-900 dark:text-white font-semibold">{rejectingAgent.name}</strong>.
                            This message will be dispatched directly to the agent.
                        </p>

                        <form onSubmit={handleRejectSubmit} className="space-y-3">
                            <div>
                                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                    Rejection Reason <span className="text-rose-500">*</span>
                                </label>
                                <textarea
                                    rows={3}
                                    value={rejectReason}
                                    onChange={(e) => {
                                        setRejectReason(e.target.value);
                                        if (rejectError) setRejectError('');
                                    }}
                                    placeholder="e.g. NIC number does not match registered name or bank account holder mismatch"
                                    className={`w-full px-3 py-2 border rounded-xl text-xs bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-rose-500 focus:border-rose-500 ${
                                        rejectError ? 'border-rose-500' : 'border-slate-300 dark:border-slate-700'
                                    }`}
                                />
                                {rejectError && (
                                    <p className="text-[11px] text-rose-600 dark:text-rose-400 mt-1">
                                        {rejectError}
                                    </p>
                                )}
                            </div>

                            <div className="flex items-center justify-end gap-3 pt-3 border-t border-slate-200 dark:border-slate-800">
                                <button
                                    type="button"
                                    onClick={() => setRejectingAgent(null)}
                                    className="px-4 py-2 border border-slate-300 dark:border-slate-700 rounded-xl text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors cursor-pointer"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={isRejecting}
                                    className="px-5 py-2 bg-rose-600 hover:bg-rose-700 text-white font-semibold rounded-xl text-xs shadow-xs transition-colors cursor-pointer disabled:opacity-50"
                                >
                                    {isRejecting ? 'Rejecting...' : 'Confirm Rejection'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AdminLayout>
    );
}

