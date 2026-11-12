import React, { useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import * as Icons from '@/Components/Icons';
import { AgentInvitation, PageProps } from '@/types';

interface PaginatedInvitations {
    data: AgentInvitation[];
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

interface InvitationsProps extends PageProps {
    invitations: PaginatedInvitations;
    filters: {
        status?: string;
        type?: string;
        search?: string;
    };
    stats: {
        total: number;
        pending: number;
        accepted: number;
        expired: number;
        revoked: number;
    };
    canCreateHla: boolean;
    newInvitation?: {
        link: string;
        token?: string;
        candidate_name?: string;
    } | null;
}

export default function Invitations({
    invitations,
    filters,
    stats,
    canCreateHla,
    newInvitation,
}: InvitationsProps) {
    const { flash } = usePage<PageProps>().props;

    // Filters state
    const [search, setSearch] = useState(filters.search || '');
    const [status, setStatus] = useState(filters.status || '');
    const [type, setType] = useState(filters.type || '');

    // Copy to clipboard state
    const [copiedLink, setCopiedLink] = useState(false);
    const [copiedRowId, setCopiedRowId] = useState<number | null>(null);

    // Modal state for Create HLA Invitation
    const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);
    const [candidateName, setCandidateName] = useState('');
    const [candidateEmail, setCandidateEmail] = useState('');
    const [candidatePhone, setCandidatePhone] = useState('');
    const [expiryDays, setExpiryDays] = useState(7);
    const [isSubmitting, setIsSubmitting] = useState(false);

    // Modal state for Revocation
    const [revokingInvitation, setRevokingInvitation] = useState<AgentInvitation | null>(null);
    const [isRevoking, setIsRevoking] = useState(false);

    // Filter handling
    const applyFilters = (overrides: Record<string, string | undefined> = {}) => {
        const queryParams: Record<string, string | undefined> = {
            search: search.trim() || undefined,
            status: status || undefined,
            type: type || undefined,
            ...overrides,
        };

        Object.keys(queryParams).forEach((key) => {
            if (queryParams[key] === undefined || queryParams[key] === '') {
                delete queryParams[key];
            }
        });

        router.get(route('admin.agents.invitations.index'), queryParams, { preserveState: true });
    };

    const handleSearchSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        applyFilters();
    };

    const handleResetFilters = () => {
        setSearch('');
        setStatus('');
        setType('');
        router.get(route('admin.agents.invitations.index'), {}, { preserveState: true });
    };

    const handleCreateSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setIsSubmitting(true);

        router.post(
            route('admin.agents.invitations.store'),
            {
                candidate_name: candidateName.trim() || undefined,
                candidate_email: candidateEmail.trim() || undefined,
                candidate_phone: candidatePhone.trim() || undefined,
                expiry_days: expiryDays,
            },
            {
                onSuccess: () => {
                    setIsCreateModalOpen(false);
                    setCandidateName('');
                    setCandidateEmail('');
                    setCandidatePhone('');
                    setExpiryDays(7);
                    setIsSubmitting(false);
                },
                onError: () => {
                    setIsSubmitting(false);
                },
            }
        );
    };

    const handleRevokeConfirm = () => {
        if (!revokingInvitation) return;
        setIsRevoking(true);

        router.post(
            route('admin.agents.invitations.revoke', revokingInvitation.id),
            {},
            {
                onSuccess: () => {
                    setRevokingInvitation(null);
                    setIsRevoking(false);
                },
                onError: () => {
                    setIsRevoking(false);
                },
            }
        );
    };

    const copyToClipboard = (text: string, rowId?: number) => {
        navigator.clipboard.writeText(text).then(() => {
            if (rowId !== undefined) {
                setCopiedRowId(rowId);
                setTimeout(() => setCopiedRowId(null), 2500);
            } else {
                setCopiedLink(true);
                setTimeout(() => setCopiedLink(false), 2500);
            }
        });
    };

    const activeGeneratedLink = newInvitation?.link || flash?.generated_link;
    const activeCandidateName = newInvitation?.candidate_name || flash?.candidate_name;

    const hasActiveFilters = Boolean(search || status || type);

    // Status badge component
    const renderStatusBadge = (inv: AgentInvitation) => {
        const isPast = new Date(inv.expires_at) < new Date();
        const effectiveStatus = inv.status === 'pending' && isPast ? 'expired' : inv.status;

        switch (effectiveStatus) {
            case 'pending':
                return (
                    <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-100 dark:bg-amber-950/60 text-amber-800 dark:text-amber-300 border border-amber-200 dark:border-amber-800/60">
                        <span className="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse" />
                        Pending Active
                    </span>
                );
            case 'accepted':
                return (
                    <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-100 dark:bg-emerald-950/60 text-emerald-800 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800/60">
                        <Icons.Check className="w-3.5 h-3.5 text-emerald-600 dark:text-emerald-400" />
                        Accepted & Onboarded
                    </span>
                );
            case 'expired':
                return (
                    <span className="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 border border-slate-200 dark:border-slate-700">
                        Expired
                    </span>
                );
            case 'revoked':
                return (
                    <span className="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-rose-100 dark:bg-rose-950/60 text-rose-800 dark:text-rose-300 border border-rose-200 dark:border-rose-800/60">
                        Revoked
                    </span>
                );
            default:
                return (
                    <span className="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
                        {inv.status}
                    </span>
                );
        }
    };

    return (
        <AdminLayout>
            <Head title="Agent Recruitment & Invitations" />

            <div className="space-y-6">
                {/* Header Banner */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white dark:bg-[#0F172A] p-6 rounded-2xl border border-slate-200/80 dark:border-slate-800/80 shadow-xs transition-colors">
                    <div>
                        <div className="flex items-center gap-2 mb-1">
                            <span className="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-100 dark:bg-blue-950/60 text-blue-800 dark:text-blue-300 border border-blue-200 dark:border-blue-800/60">
                                Network Recruitment
                            </span>
                            <span className="text-xs text-slate-400 dark:text-slate-500 font-mono">
                                Company Direct HLA Onboarding
                            </span>
                        </div>
                        <h1 className="text-xl sm:text-2xl font-serif font-bold text-slate-900 dark:text-white tracking-tight">
                            Agent Recruitment & Invitations
                        </h1>
                        <p className="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1">
                            Generate and manage secure single-use invitation links for High-Level Agent onboarding. High-Level Agents report directly to Aura Luxe Corporate.
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

                        {canCreateHla && (
                            <button
                                type="button"
                                onClick={() => setIsCreateModalOpen(true)}
                                className="inline-flex items-center gap-2 px-4 py-2 bg-slate-900 hover:bg-slate-800 dark:bg-blue-600 dark:hover:bg-blue-500 text-white rounded-xl text-xs font-semibold shadow-xs transition-colors cursor-pointer"
                            >
                                <Icons.Plus className="w-4 h-4" />
                                Create HLA Invitation
                            </button>
                        )}
                    </div>
                </div>

                {/* Newly Generated Link Callout Banner */}
                {activeGeneratedLink && (
                    <div className="bg-emerald-50/90 dark:bg-emerald-950/40 border border-emerald-300/80 dark:border-emerald-800/80 rounded-2xl p-5 shadow-xs transition-colors">
                        <div className="flex items-start justify-between gap-4">
                            <div className="space-y-1.5 flex-1">
                                <div className="flex items-center gap-2">
                                    <span className="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-ping" />
                                    <h3 className="text-sm font-bold text-emerald-900 dark:text-emerald-200 font-serif">
                                        Invitation Link Generated Successfully!
                                    </h3>
                                    {activeCandidateName && (
                                        <span className="text-xs px-2 py-0.5 rounded-md bg-emerald-200/80 dark:bg-emerald-900/60 text-emerald-800 dark:text-emerald-300 font-medium">
                                            For {activeCandidateName}
                                        </span>
                                    )}
                                </div>
                                <p className="text-xs text-emerald-800 dark:text-emerald-300">
                                    Copy and share this secure, single-use link with the High-Level Agent candidate. The candidate will register directly under Aura Luxe Corporate.
                                </p>
                                <div className="mt-3 flex items-center gap-2 max-w-3xl">
                                    <input
                                        type="text"
                                        readOnly
                                        value={activeGeneratedLink}
                                        className="flex-1 font-mono text-xs px-3.5 py-2.5 bg-white dark:bg-[#0B0F19] text-slate-800 dark:text-slate-200 rounded-xl border border-emerald-300 dark:border-emerald-800/60 focus:ring-emerald-500 focus:border-emerald-500 shadow-inner select-all"
                                        onClick={(e) => (e.target as HTMLInputElement).select()}
                                    />
                                    <button
                                        type="button"
                                        onClick={() => copyToClipboard(activeGeneratedLink)}
                                        className="inline-flex items-center gap-2 px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-semibold shadow-xs transition-colors cursor-pointer shrink-0"
                                    >
                                        {copiedLink ? (
                                            <>
                                                <Icons.Check className="w-4 h-4" />
                                                Copied!
                                            </>
                                        ) : (
                                            <>
                                                <Icons.Copy className="w-4 h-4" />
                                                Copy Link
                                            </>
                                        )}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                )}

                {/* Summary KPI Badges */}
                <div className="grid grid-cols-2 sm:grid-cols-5 gap-3">
                    <button
                        type="button"
                        onClick={() => {
                            setStatus('');
                            applyFilters({ status: undefined });
                        }}
                        className={`p-4 rounded-2xl border text-left transition-all cursor-pointer shadow-xs ${
                            !status
                                ? 'bg-slate-900 dark:bg-blue-600 text-white border-slate-900 dark:border-blue-600 ring-2 ring-blue-500/30'
                                : 'bg-white dark:bg-[#0F172A] text-slate-800 dark:text-slate-200 border-slate-200/80 dark:border-slate-800/80 hover:bg-slate-50 dark:hover:bg-slate-800/50'
                        }`}
                    >
                        <div className="text-[11px] uppercase tracking-wider font-semibold opacity-70">
                            All Invitations
                        </div>
                        <div className="text-2xl font-bold font-mono mt-1">{stats.total}</div>
                        <div className="text-[11px] opacity-70 mt-0.5">Total Generated</div>
                    </button>

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
                            Pending Active
                        </div>
                        <div className="text-2xl font-bold font-mono mt-1">{stats.pending}</div>
                        <div className="text-[11px] opacity-80 mt-0.5">Awaiting Registration</div>
                    </button>

                    <button
                        type="button"
                        onClick={() => {
                            setStatus('accepted');
                            applyFilters({ status: 'accepted' });
                        }}
                        className={`p-4 rounded-2xl border text-left transition-all cursor-pointer shadow-xs ${
                            status === 'accepted'
                                ? 'bg-emerald-600 text-white border-emerald-600 ring-2 ring-emerald-500/30'
                                : 'bg-white dark:bg-[#0F172A] text-emerald-800 dark:text-emerald-400 border-slate-200/80 dark:border-slate-800/80 hover:bg-emerald-50/50 dark:hover:bg-emerald-950/30'
                        }`}
                    >
                        <div className="text-[11px] uppercase tracking-wider font-semibold opacity-80">
                            Accepted
                        </div>
                        <div className="text-2xl font-bold font-mono mt-1">{stats.accepted}</div>
                        <div className="text-[11px] opacity-80 mt-0.5">Onboarded Agents</div>
                    </button>

                    <button
                        type="button"
                        onClick={() => {
                            setStatus('expired');
                            applyFilters({ status: 'expired' });
                        }}
                        className={`p-4 rounded-2xl border text-left transition-all cursor-pointer shadow-xs ${
                            status === 'expired'
                                ? 'bg-slate-700 text-white border-slate-700 ring-2 ring-slate-500/30'
                                : 'bg-white dark:bg-[#0F172A] text-slate-600 dark:text-slate-400 border-slate-200/80 dark:border-slate-800/80 hover:bg-slate-50 dark:hover:bg-slate-800/50'
                        }`}
                    >
                        <div className="text-[11px] uppercase tracking-wider font-semibold opacity-80">
                            Expired
                        </div>
                        <div className="text-2xl font-bold font-mono mt-1">{stats.expired}</div>
                        <div className="text-[11px] opacity-80 mt-0.5">Timed Out</div>
                    </button>

                    <button
                        type="button"
                        onClick={() => {
                            setStatus('revoked');
                            applyFilters({ status: 'revoked' });
                        }}
                        className={`p-4 rounded-2xl border text-left transition-all cursor-pointer shadow-xs ${
                            status === 'revoked'
                                ? 'bg-rose-600 text-white border-rose-600 ring-2 ring-rose-500/30'
                                : 'bg-white dark:bg-[#0F172A] text-rose-800 dark:text-rose-400 border-slate-200/80 dark:border-slate-800/80 hover:bg-rose-50/50 dark:hover:bg-rose-950/30'
                        }`}
                    >
                        <div className="text-[11px] uppercase tracking-wider font-semibold opacity-80">
                            Revoked
                        </div>
                        <div className="text-2xl font-bold font-mono mt-1">{stats.revoked}</div>
                        <div className="text-[11px] opacity-80 mt-0.5">Cancelled Links</div>
                    </button>
                </div>

                {/* Filter and Search Bar */}
                <div className="bg-white dark:bg-[#0F172A] rounded-2xl border border-slate-200/80 dark:border-slate-800/80 p-5 shadow-xs transition-colors">
                    <form onSubmit={handleSearchSubmit} className="flex flex-wrap items-center gap-3 text-xs">
                        {/* Search Input */}
                        <div className="flex-1 min-w-[260px] relative">
                            <span className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                                <Icons.Search className="w-4 h-4" />
                            </span>
                            <input
                                type="text"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Search by candidate name, email, or phone..."
                                className="w-full pl-9 pr-3 py-2 border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/80 text-slate-900 dark:text-white rounded-xl text-xs placeholder:text-slate-400 dark:placeholder:text-slate-500 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                            />
                        </div>

                        {/* Status Select */}
                        <select
                            value={status}
                            onChange={(e) => {
                                setStatus(e.target.value);
                                applyFilters({ status: e.target.value || undefined });
                            }}
                            className="px-3 py-2 border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/80 text-slate-900 dark:text-white rounded-xl text-xs focus:ring-blue-500 focus:border-blue-500"
                        >
                            <option value="">All Statuses</option>
                            <option value="pending">Pending Active</option>
                            <option value="accepted">Accepted / Onboarded</option>
                            <option value="expired">Expired</option>
                            <option value="revoked">Revoked</option>
                        </select>

                        {/* Type Select */}
                        <select
                            value={type}
                            onChange={(e) => {
                                setType(e.target.value);
                                applyFilters({ type: e.target.value || undefined });
                            }}
                            className="px-3 py-2 border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/80 text-slate-900 dark:text-white rounded-xl text-xs focus:ring-blue-500 focus:border-blue-500"
                        >
                            <option value="">All Invitation Types</option>
                            <option value="high_level_agent">High-Level Agent (Company Direct)</option>
                            <option value="low_level_agent">Low-Level Agent (HLA Recruited)</option>
                        </select>

                        {/* Submit button */}
                        <button
                            type="submit"
                            className="px-5 py-2 bg-slate-900 dark:bg-blue-600 hover:bg-slate-800 dark:hover:bg-blue-500 text-white font-semibold rounded-xl cursor-pointer transition-colors shadow-xs"
                        >
                            Filter
                        </button>

                        {/* Reset button */}
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

                {/* Invitations Table */}
                <div className="bg-white dark:bg-[#0F172A] rounded-2xl border border-slate-200/80 dark:border-slate-800/80 shadow-xs overflow-hidden transition-colors">
                    {invitations.data.length === 0 ? (
                        <div className="p-16 text-center">
                            <div className="w-12 h-12 rounded-2xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center mx-auto mb-3 text-slate-400">
                                <Icons.Mail className="w-6 h-6" />
                            </div>
                            <h3 className="text-sm font-semibold text-slate-900 dark:text-white">
                                No invitations found
                            </h3>
                            <p className="text-xs text-slate-500 dark:text-slate-400 mt-1 max-w-sm mx-auto">
                                {hasActiveFilters
                                    ? 'No invitations matched the specified search and filter criteria. Try clearing the filters.'
                                    : 'No invitations have been generated yet. Use the "Create HLA Invitation" button above to recruit a High-Level Agent.'}
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
                                        <th className="px-6 py-3.5">Candidate / Target</th>
                                        <th className="px-6 py-3.5">Tier & Network Parent</th>
                                        <th className="px-6 py-3.5">Generated By</th>
                                        <th className="px-6 py-3.5">Status</th>
                                        <th className="px-6 py-3.5">Expiration / Outcome</th>
                                        <th className="px-6 py-3.5 text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100 dark:divide-slate-800/60 font-sans bg-white dark:bg-[#0F172A]">
                                    {invitations.data.map((inv) => {
                                        const isPast = new Date(inv.expires_at) < new Date();
                                        const isPending = inv.status === 'pending' && !isPast;
                                        const isHla = inv.invitation_type === 'high_level_agent';
                                        const parentInfo = inv.parent_agent || inv.parentAgent;
                                        const usedByInfo = inv.used_by_user || inv.usedBy;

                                        return (
                                            <tr
                                                key={inv.id}
                                                className="bg-white dark:bg-[#0F172A] hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition-colors"
                                            >
                                                {/* Candidate / Target */}
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <div className="font-semibold text-slate-900 dark:text-white">
                                                        {inv.candidate_name || (
                                                            <span className="text-slate-400 dark:text-slate-500 italic">
                                                                Open Candidate Link
                                                            </span>
                                                        )}
                                                    </div>
                                                    <div className="text-[11px] text-slate-500 dark:text-slate-400 font-mono mt-0.5">
                                                        {inv.candidate_email || inv.candidate_phone ? (
                                                            <>
                                                                {inv.candidate_email}
                                                                {inv.candidate_email && inv.candidate_phone ? ' • ' : ''}
                                                                {inv.candidate_phone}
                                                            </>
                                                        ) : (
                                                            'Direct Invitation Token'
                                                        )}
                                                    </div>
                                                </td>

                                                {/* Tier & Network Parent */}
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    {isHla ? (
                                                        <div>
                                                            <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-semibold bg-purple-100 text-purple-800 dark:bg-purple-950/60 dark:text-purple-300 border border-purple-200 dark:border-purple-800/60">
                                                                High-Level Agent
                                                            </span>
                                                            <div className="text-[11px] text-slate-500 dark:text-slate-400 mt-1 flex items-center gap-1">
                                                                <Icons.Building2 className="w-3.5 h-3.5 text-slate-400" />
                                                                Company Direct (No Upline)
                                                            </div>
                                                        </div>
                                                    ) : (
                                                        <div>
                                                            <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-semibold bg-sky-100 text-sky-800 dark:bg-sky-950/60 dark:text-sky-300 border border-sky-200 dark:border-sky-800/60">
                                                                Low-Level Agent
                                                            </span>
                                                            <div className="text-[11px] text-slate-500 dark:text-slate-400 mt-1">
                                                                Parent: <span className="font-semibold">{parentInfo?.name || 'HLA Sponsor'}</span> ({parentInfo?.referral_code || 'HLA'})
                                                            </div>
                                                        </div>
                                                    )}
                                                </td>

                                                {/* Generated By */}
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <div className="font-medium text-slate-900 dark:text-white">
                                                        {inv.creator?.name || 'Staff'}
                                                    </div>
                                                    <div className="text-[11px] text-slate-500 dark:text-slate-400 capitalize">
                                                        {inv.creator?.role ? inv.creator.role.replace('_', ' ') : 'Manager'} • {new Date(inv.created_at).toLocaleDateString()}
                                                    </div>
                                                </td>

                                                {/* Status */}
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    {renderStatusBadge(inv)}
                                                </td>

                                                {/* Expiration / Outcome */}
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    {inv.status === 'accepted' ? (
                                                        <div>
                                                            <div className="text-xs font-semibold text-emerald-700 dark:text-emerald-400">
                                                                Registered: {usedByInfo?.name || 'Agent'}
                                                            </div>
                                                            <div className="text-[11px] text-slate-500 dark:text-slate-400">
                                                                {inv.used_at ? new Date(inv.used_at).toLocaleDateString() : 'Completed'}
                                                            </div>
                                                        </div>
                                                    ) : (
                                                        <div>
                                                            <div className={`text-xs ${isPast ? 'text-rose-600 dark:text-rose-400' : 'text-slate-700 dark:text-slate-300'}`}>
                                                                Expires: {new Date(inv.expires_at).toLocaleDateString()}
                                                            </div>
                                                            <div className="text-[11px] text-slate-400 dark:text-slate-500">
                                                                {isPast ? 'Expired' : `${Math.ceil((new Date(inv.expires_at).getTime() - Date.now()) / (1000 * 60 * 60 * 24))} days left`}
                                                            </div>
                                                        </div>
                                                    )}
                                                </td>

                                                {/* Actions */}
                                                <td className="px-6 py-4 text-right whitespace-nowrap">
                                                    <div className="flex items-center justify-end gap-2">
                                                        {isPending && (
                                                            <button
                                                                type="button"
                                                                onClick={() => setRevokingInvitation(inv)}
                                                                className="px-2.5 py-1 text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/40 rounded-lg text-xs font-semibold transition-colors cursor-pointer"
                                                            >
                                                                Revoke
                                                            </button>
                                                        )}

                                                        {inv.status === 'accepted' && usedByInfo && (
                                                            <Link
                                                                href={route('admin.agents.show', usedByInfo.id)}
                                                                className="px-2.5 py-1 text-blue-600 dark:text-blue-400 hover:underline font-medium text-xs inline-flex items-center gap-1"
                                                            >
                                                                View Agent Profile
                                                                <Icons.ChevronRight className="w-3 h-3" />
                                                            </Link>
                                                        )}
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
                    {invitations.total > invitations.per_page && (
                        <div className="p-4 border-t border-slate-200/80 dark:border-slate-800/80 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-slate-500 dark:text-slate-400">
                            <div>
                                Showing <span className="font-semibold text-slate-900 dark:text-white">{invitations.from ?? 0}</span> to{' '}
                                <span className="font-semibold text-slate-900 dark:text-white">{invitations.to ?? 0}</span> of{' '}
                                <span className="font-semibold text-slate-900 dark:text-white">{invitations.total}</span> invitations
                            </div>
                            <div className="flex items-center gap-1">
                                {invitations.links.map((link, idx) => (
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

                {/* Recruitment Policy Callout Notice */}
                <div className="bg-slate-50 dark:bg-[#0F172A]/60 border border-slate-200/80 dark:border-slate-800/80 rounded-2xl p-5 text-xs text-slate-600 dark:text-slate-400 space-y-2">
                    <div className="flex items-center gap-2 font-semibold text-slate-900 dark:text-white font-serif">
                        <Icons.Shield className="w-4 h-4 text-blue-500" />
                        Aura Luxe Agent Network Recruitment Governance Rules
                    </div>
                    <ul className="list-disc list-inside space-y-1 text-slate-500 dark:text-slate-400">
                        <li>
                            <strong className="text-slate-700 dark:text-slate-300">Company Direct High-Level Agents:</strong> Agent Managers issue HLA invitations. All HLAs sit directly under Aura Luxe Corporate (<code className="font-mono text-[11px] text-blue-600 dark:text-blue-400">parent_agent_id = null</code>). Staff members never become network sponsors.
                        </li>
                        <li>
                            <strong className="text-slate-700 dark:text-slate-300">Low-Level Agent Recruitment:</strong> Low-Level Agents are recruited strictly by active High-Level Agents through the agent portal. They are bound directly to the inviting HLA.
                        </li>
                        <li>
                            <strong className="text-slate-700 dark:text-slate-300">Zero Recruitment Fees / Commissions:</strong> In compliance with platform governance, no recruitment or multi-level onboarding bonuses are paid. Commissions are earned strictly from verified retail product sales.
                        </li>
                        <li>
                            <strong className="text-slate-700 dark:text-slate-300">Cryptographic Security:</strong> Invitation tokens are generated with 256-bit cryptographic entropy and stored exclusively as SHA-256 hashes. Once accepted or expired, links cannot be re-used.
                        </li>
                    </ul>
                </div>
            </div>

            {/* Create HLA Invitation Modal */}
            {isCreateModalOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 dark:bg-black/80 backdrop-blur-xs">
                    <div className="bg-white dark:bg-[#0F172A] border border-slate-200 dark:border-slate-800 rounded-2xl p-6 max-w-lg w-full shadow-2xl space-y-5 transition-all">
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-2">
                                <div className="w-9 h-9 rounded-xl bg-blue-100 dark:bg-blue-950/60 flex items-center justify-center text-blue-600 dark:text-blue-400">
                                    <Icons.Plus className="w-5 h-5" />
                                </div>
                                <div>
                                    <h2 className="text-base font-serif font-bold text-slate-900 dark:text-white">
                                        Create High-Level Agent Invitation
                                    </h2>
                                    <p className="text-xs text-slate-500 dark:text-slate-400">
                                        Issue a secure single-use link for an HLA candidate.
                                    </p>
                                </div>
                            </div>
                            <button
                                type="button"
                                onClick={() => setIsCreateModalOpen(false)}
                                className="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 cursor-pointer"
                            >
                                <Icons.XCircle className="w-5 h-5" />
                            </button>
                        </div>

                        <form onSubmit={handleCreateSubmit} className="space-y-4 text-xs">
                            <div>
                                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                    Candidate Full Name (Optional)
                                </label>
                                <input
                                    type="text"
                                    value={candidateName}
                                    onChange={(e) => setCandidateName(e.target.value)}
                                    placeholder="e.g. Kasun Jayawardena"
                                    className="w-full px-3 py-2 border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white rounded-xl text-xs focus:ring-blue-500 focus:border-blue-500"
                                />
                                <p className="text-[11px] text-slate-400 dark:text-slate-500 mt-1">
                                    Pre-populates candidate tracking in the admin invitation log.
                                </p>
                            </div>

                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                        Candidate Email (Optional)
                                    </label>
                                    <input
                                        type="email"
                                        value={candidateEmail}
                                        onChange={(e) => setCandidateEmail(e.target.value)}
                                        placeholder="candidate@example.lk"
                                        className="w-full px-3 py-2 border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white rounded-xl text-xs focus:ring-blue-500 focus:border-blue-500"
                                    />
                                </div>

                                <div>
                                    <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                        Candidate Phone (Optional)
                                    </label>
                                    <input
                                        type="text"
                                        value={candidatePhone}
                                        onChange={(e) => setCandidatePhone(e.target.value)}
                                        placeholder="0771234567"
                                        className="w-full px-3 py-2 border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white rounded-xl text-xs focus:ring-blue-500 focus:border-blue-500"
                                    />
                                </div>
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                    Link Expiration Duration
                                </label>
                                <select
                                    value={expiryDays}
                                    onChange={(e) => setExpiryDays(Number(e.target.value))}
                                    className="w-full px-3 py-2 border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white rounded-xl text-xs focus:ring-blue-500 focus:border-blue-500"
                                >
                                    <option value={1}>1 Day (24 Hours)</option>
                                    <option value={3}>3 Days</option>
                                    <option value={7}>7 Days (Recommended Default)</option>
                                    <option value={14}>14 Days</option>
                                    <option value={30}>30 Days (Maximum)</option>
                                </select>
                            </div>

                            <div className="bg-blue-50/80 dark:bg-blue-950/40 border border-blue-200/80 dark:border-blue-800/80 rounded-xl p-3 text-[11px] text-blue-800 dark:text-blue-300 space-y-1">
                                <div className="font-semibold flex items-center gap-1.5">
                                    <Icons.ShieldCheck className="w-3.5 h-3.5 text-blue-600 dark:text-blue-400" />
                                    Company-Direct High-Level Agent Guarantee
                                </div>
                                <p>
                                    The candidate will register as a High-Level Agent under Corporate Aura Luxe (<code className="font-mono">parent_agent_id = null</code>). Staff members are never assigned as parent sponsors.
                                </p>
                            </div>

                            <div className="flex items-center justify-end gap-3 pt-3 border-t border-slate-200 dark:border-slate-800">
                                <button
                                    type="button"
                                    onClick={() => setIsCreateModalOpen(false)}
                                    className="px-4 py-2 border border-slate-300 dark:border-slate-700 rounded-xl text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors cursor-pointer"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={isSubmitting}
                                    className="inline-flex items-center gap-2 px-5 py-2 bg-slate-900 dark:bg-blue-600 hover:bg-slate-800 dark:hover:bg-blue-500 text-white font-semibold rounded-xl text-xs shadow-xs transition-colors cursor-pointer disabled:opacity-50"
                                >
                                    {isSubmitting ? 'Generating...' : 'Generate Invitation Link'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Revoke Invitation Modal */}
            {revokingInvitation && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 dark:bg-black/80 backdrop-blur-xs">
                    <div className="bg-white dark:bg-[#0F172A] border border-slate-200 dark:border-slate-800 rounded-2xl p-6 max-w-md w-full shadow-2xl space-y-4 transition-all">
                        <div className="flex items-center gap-3 text-rose-600 dark:text-rose-400">
                            <div className="w-10 h-10 rounded-xl bg-rose-100 dark:bg-rose-950/60 flex items-center justify-center">
                                <Icons.ShieldAlert className="w-5 h-5" />
                            </div>
                            <div>
                                <h3 className="text-base font-serif font-bold text-slate-900 dark:text-white">
                                    Revoke Invitation?
                                </h3>
                                <p className="text-xs text-slate-500 dark:text-slate-400">
                                    This action immediately invalidates the link.
                                </p>
                            </div>
                        </div>

                        <p className="text-xs text-slate-600 dark:text-slate-300">
                            Are you sure you want to revoke the invitation for{' '}
                            <strong className="text-slate-900 dark:text-white font-semibold">
                                {revokingInvitation.candidate_name || 'this candidate'}
                            </strong>
                            ? Once revoked, any attempt to register using this link will be rejected.
                        </p>

                        <div className="flex items-center justify-end gap-3 pt-3 border-t border-slate-200 dark:border-slate-800">
                            <button
                                type="button"
                                onClick={() => setRevokingInvitation(null)}
                                className="px-4 py-2 border border-slate-300 dark:border-slate-700 rounded-xl text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors cursor-pointer"
                            >
                                Cancel
                            </button>
                            <button
                                type="button"
                                disabled={isRevoking}
                                onClick={handleRevokeConfirm}
                                className="px-5 py-2 bg-rose-600 hover:bg-rose-700 text-white font-semibold rounded-xl text-xs shadow-xs transition-colors cursor-pointer disabled:opacity-50"
                            >
                                {isRevoking ? 'Revoking...' : 'Confirm Revocation'}
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </AdminLayout>
    );
}
