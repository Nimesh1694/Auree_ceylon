import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import * as Icons from '@/Components/Icons';
import { PageProps } from '@/types';

export interface HierarchyAgentSummary {
    id: number;
    name: string;
    referral_code: string;
    role: string;
    status: string;
    parent_id?: number | null;
    parent?: {
        id: number;
        name: string;
        referral_code: string;
    } | null;
}

export interface HierarchyRequestItem {
    id: number;
    agent_id: number;
    current_parent_id: number | null;
    requested_parent_id: number | null;
    requested_by: number;
    reason: string;
    status: 'pending' | 'approved' | 'rejected';
    review_notes: string | null;
    reviewed_by: number | null;
    reviewed_at: string | null;
    created_at: string;
    subtree_size: number;
    agent?: HierarchyAgentSummary | null;
    current_parent?: HierarchyAgentSummary | null;
    requested_parent?: HierarchyAgentSummary | null;
    requested_by_user?: { id: number; name: string; role?: string } | null;
    reviewed_by_user?: { id: number; name: string; role?: string } | null;
}

export interface PaginatedRequests {
    data: HierarchyRequestItem[];
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

interface HierarchyRequestsProps extends PageProps {
    requests: PaginatedRequests;
    filters: {
        status?: string;
        search?: string;
    };
    metrics: {
        pending: number;
        approved: number;
        rejected: number;
        total: number;
    };
    eligibleAgents: HierarchyAgentSummary[];
    eligibleSponsors: HierarchyAgentSummary[];
    isOwner: boolean;
    canRequest: boolean;
}

export default function HierarchyRequests({
    requests,
    filters,
    metrics,
    eligibleAgents = [],
    eligibleSponsors = [],
    isOwner,
    canRequest,
}: HierarchyRequestsProps) {
    // Search & Filter state
    const [searchTerm, setSearchTerm] = useState(filters.search || '');
    const [statusFilter, setStatusFilter] = useState(filters.status || '');

    // Executive Review Modal state (Owner Only)
    const [reviewModalOpen, setReviewModalOpen] = useState(false);
    const [selectedRequest, setSelectedRequest] = useState<HierarchyRequestItem | null>(null);
    const [reviewAction, setReviewAction] = useState<'approve' | 'reject'>('approve');
    const [reviewNotes, setReviewNotes] = useState('');
    const [isSubmittingReview, setIsSubmittingReview] = useState(false);
    const [reviewError, setReviewError] = useState<string | null>(null);

    // New Request Modal state (Agent Manager & Owner)
    const [newRequestModalOpen, setNewRequestModalOpen] = useState(false);
    const [selectedAgentId, setSelectedAgentId] = useState<string>('');
    const [selectedSponsorId, setSelectedSponsorId] = useState<string>('');
    const [requestReason, setRequestReason] = useState('');
    const [isSubmittingRequest, setIsSubmittingRequest] = useState(false);
    const [requestFormError, setRequestFormError] = useState<string | null>(null);

    // Filter application
    const applyFilters = (newStatus?: string, newSearch?: string) => {
        const query: Record<string, string> = {};
        const statusVal = newStatus !== undefined ? newStatus : statusFilter;
        const searchVal = newSearch !== undefined ? newSearch : searchTerm;

        if (statusVal) query.status = statusVal;
        if (searchVal) query.search = searchVal;

        router.get('/admin/agents/hierarchy-requests', query, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const handleSearchSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        applyFilters(undefined, searchTerm.trim());
    };

    const handleClearFilters = () => {
        setSearchTerm('');
        setStatusFilter('');
        router.get('/admin/agents/hierarchy-requests');
    };

    // Open Owner Review Modal
    const handleOpenReview = (requestItem: HierarchyRequestItem) => {
        setSelectedRequest(requestItem);
        setReviewAction('approve');
        setReviewNotes('');
        setReviewError(null);
        setReviewModalOpen(true);
    };

    // Submit Owner Review Decision
    const handleSubmitReview = (e: React.FormEvent) => {
        e.preventDefault();
        if (!selectedRequest) return;

        if (reviewAction === 'reject' && !reviewNotes.trim()) {
            setReviewError('A decision note explaining the rejection reason is required.');
            return;
        }

        setIsSubmittingReview(true);
        setReviewError(null);

        router.post(
            `/admin/agents/hierarchy-requests/${selectedRequest.id}/review`,
            {
                action: reviewAction,
                review_notes: reviewNotes.trim(),
            },
            {
                onSuccess: () => {
                    setReviewModalOpen(false);
                    setSelectedRequest(null);
                },
                onError: (errors) => {
                    const firstError = Object.values(errors)[0] as string;
                    setReviewError(firstError || 'Failed to submit review decision.');
                },
                onFinish: () => {
                    setIsSubmittingReview(false);
                },
            }
        );
    };

    // Selected agent metadata helper for New Request form
    const currentSelectedAgent = eligibleAgents.find((a) => String(a.id) === selectedAgentId);

    // Submit New Hierarchy Change Request
    const handleSubmitNewRequest = (e: React.FormEvent) => {
        e.preventDefault();

        if (!selectedAgentId) {
            setRequestFormError('Please select a target agent to relocate.');
            return;
        }

        if (requestReason.trim().length < 10) {
            setRequestFormError('Reason for move must be at least 10 characters long.');
            return;
        }

        // Tier validation
        if (currentSelectedAgent?.role === 'high_level_agent' && selectedSponsorId !== '') {
            setRequestFormError('High-Level Agents must remain company-direct and cannot be assigned under a sponsor.');
            return;
        }

        if (currentSelectedAgent && selectedSponsorId && String(currentSelectedAgent.id) === selectedSponsorId) {
            setRequestFormError('An agent cannot sponsor themselves.');
            return;
        }

        if (currentSelectedAgent?.parent_id && String(currentSelectedAgent.parent_id) === selectedSponsorId) {
            setRequestFormError('The agent is already assigned to this sponsor.');
            return;
        }

        setIsSubmittingRequest(true);
        setRequestFormError(null);

        router.post(
            '/admin/agents/hierarchy-requests',
            {
                agent_id: parseInt(selectedAgentId, 10),
                requested_parent_id: selectedSponsorId ? parseInt(selectedSponsorId, 10) : null,
                reason: requestReason.trim(),
            },
            {
                onSuccess: () => {
                    setNewRequestModalOpen(false);
                    setSelectedAgentId('');
                    setSelectedSponsorId('');
                    setRequestReason('');
                },
                onError: (errors) => {
                    const firstErr = Object.values(errors)[0] as string;
                    setRequestFormError(firstErr || 'Failed to submit hierarchy change request.');
                },
                onFinish: () => {
                    setIsSubmittingRequest(false);
                },
            }
        );
    };

    return (
        <AdminLayout>
            <Head title="Hierarchy Position Change Requests" />

            <div className="space-y-6 max-w-7xl mx-auto">
                {/* Header Card */}
                <div className="bg-white dark:bg-slate-900 rounded-2xl p-6 border border-slate-200 dark:border-slate-800 shadow-sm flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                    <div>
                        <div className="flex items-center gap-3">
                            <span className="p-2.5 rounded-xl bg-amber-500/10 text-amber-600 dark:text-amber-400">
                                <Icons.ArrowRightLeft className="w-6 h-6" />
                            </span>
                            <div>
                                <h1 className="text-xl md:text-2xl font-bold text-slate-900 dark:text-white">
                                    Hierarchy Position Change Requests
                                </h1>
                                <p className="text-xs md:text-sm text-slate-500 dark:text-slate-400 mt-0.5">
                                    {isOwner
                                        ? 'Executive approval center: Review proposed sponsor relocations and execute atomic movements.'
                                        : 'Submit and track agent relocation requests awaiting Executive Owner review.'}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div className="flex flex-wrap items-center gap-2.5 w-full md:w-auto">
                        <Link
                            href="/admin/agents/hierarchy"
                            className="px-3.5 py-2 rounded-xl text-xs font-medium bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 transition-colors flex items-center gap-1.5"
                        >
                            <Icons.GitBranch className="w-4 h-4 text-amber-500" />
                            <span>Network Tree Explorer</span>
                        </Link>

                        {canRequest && (
                            <button
                                type="button"
                                onClick={() => {
                                    setRequestFormError(null);
                                    setNewRequestModalOpen(true);
                                }}
                                className="px-4 py-2 rounded-xl text-xs font-semibold bg-amber-500 hover:bg-amber-600 text-white shadow-sm transition-colors flex items-center gap-1.5"
                            >
                                <Icons.Plus className="w-4 h-4" />
                                <span>Request Hierarchy Move</span>
                            </button>
                        )}
                    </div>
                </div>

                {/* Metrics Cards */}
                <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div className="bg-white dark:bg-slate-900 p-4 rounded-xl border border-slate-200 dark:border-slate-800 shadow-xs">
                        <div className="text-xs font-medium text-slate-500 dark:text-slate-400">Total Requests</div>
                        <div className="text-2xl font-bold text-slate-900 dark:text-white mt-1">{metrics.total}</div>
                        <div className="text-[11px] text-slate-400 mt-0.5">All-time relocation log</div>
                    </div>

                    <div className="bg-white dark:bg-slate-900 p-4 rounded-xl border border-slate-200 dark:border-slate-800 shadow-xs">
                        <div className="text-xs font-medium text-amber-600 dark:text-amber-400 flex items-center gap-1.5">
                            <span className="w-2 h-2 rounded-full bg-amber-500 animate-pulse" />
                            <span>Pending Owner Review</span>
                        </div>
                        <div className="text-2xl font-bold text-amber-600 dark:text-amber-400 mt-1">{metrics.pending}</div>
                        <div className="text-[11px] text-slate-400 mt-0.5">Awaiting executive decision</div>
                    </div>

                    <div className="bg-white dark:bg-slate-900 p-4 rounded-xl border border-slate-200 dark:border-slate-800 shadow-xs">
                        <div className="text-xs font-medium text-emerald-600 dark:text-emerald-400">Approved Moves</div>
                        <div className="text-2xl font-bold text-emerald-600 dark:text-emerald-400 mt-1">{metrics.approved}</div>
                        <div className="text-[11px] text-slate-400 mt-0.5">Executed tree relocations</div>
                    </div>

                    <div className="bg-white dark:bg-slate-900 p-4 rounded-xl border border-slate-200 dark:border-slate-800 shadow-xs">
                        <div className="text-xs font-medium text-rose-600 dark:text-rose-400">Rejected Requests</div>
                        <div className="text-2xl font-bold text-rose-600 dark:text-rose-400 mt-1">{metrics.rejected}</div>
                        <div className="text-[11px] text-slate-400 mt-0.5">Declined by Executive Owner</div>
                    </div>
                </div>

                {/* Filter and Search Bar */}
                <div className="bg-white dark:bg-slate-900 rounded-2xl p-4 border border-slate-200 dark:border-slate-800 shadow-sm flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3">
                    {/* Status Tabs */}
                    <div className="flex flex-wrap items-center gap-1.5">
                        {[
                            { label: 'All Requests', val: '', count: metrics.total },
                            { label: 'Pending', val: 'pending', count: metrics.pending },
                            { label: 'Approved', val: 'approved', count: metrics.approved },
                            { label: 'Rejected', val: 'rejected', count: metrics.rejected },
                        ].map((tab) => (
                            <button
                                key={tab.val}
                                type="button"
                                onClick={() => {
                                    setStatusFilter(tab.val);
                                    applyFilters(tab.val, undefined);
                                }}
                                className={`px-3 py-1.5 rounded-lg text-xs font-medium transition-colors flex items-center gap-1.5 ${
                                    statusFilter === tab.val
                                        ? 'bg-amber-500 text-white shadow-xs'
                                        : 'bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300'
                                }`}
                            >
                                <span>{tab.label}</span>
                                <span
                                    className={`px-1.5 py-0.2 rounded-full text-[10px] ${
                                        statusFilter === tab.val
                                            ? 'bg-white/20 text-white'
                                            : 'bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-400'
                                    }`}
                                >
                                    {tab.count}
                                </span>
                            </button>
                        ))}
                    </div>

                    {/* Search Bar */}
                    <form onSubmit={handleSearchSubmit} className="flex items-center gap-2">
                        <div className="relative flex-1 sm:w-64">
                            <Icons.Search className="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
                            <input
                                type="text"
                                placeholder="Search by agent name or code..."
                                value={searchTerm}
                                onChange={(e) => setSearchTerm(e.target.value)}
                                className="w-full pl-9 pr-3 py-1.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-xs text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500"
                            />
                        </div>

                        <button
                            type="submit"
                            className="px-3 py-1.5 rounded-lg text-xs font-medium bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200"
                        >
                            Search
                        </button>

                        {(searchTerm || statusFilter) && (
                            <button
                                type="button"
                                onClick={handleClearFilters}
                                className="px-2.5 py-1.5 rounded-lg text-xs text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
                                title="Clear all filters"
                            >
                                <Icons.XCircle className="w-4 h-4" />
                            </button>
                        )}
                    </form>
                </div>

                {/* Requests Table */}
                <div className="bg-white dark:bg-slate-900 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-800 overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left border-collapse text-xs">
                            <thead>
                                <tr className="border-b border-slate-200 dark:border-slate-800 bg-slate-50/80 dark:bg-slate-800/50 text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                    <th className="py-3.5 px-4">Target Agent</th>
                                    <th className="py-3.5 px-4">Current Sponsor</th>
                                    <th className="py-3.5 px-4">Proposed Sponsor</th>
                                    <th className="py-3.5 px-4">Subtree Impact</th>
                                    <th className="py-3.5 px-4">Reason & Requester</th>
                                    <th className="py-3.5 px-4">Status & Decision</th>
                                    <th className="py-3.5 px-4 text-right">Executive Action</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                                {requests.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={7} className="py-16 text-center text-slate-500 dark:text-slate-400">
                                            <Icons.FileCheck className="w-10 h-10 mx-auto mb-2 text-slate-300 dark:text-slate-600" />
                                            <p className="font-semibold text-slate-800 dark:text-slate-200">
                                                No hierarchy change requests found.
                                            </p>
                                            <p className="text-[11px] text-slate-400 mt-1">
                                                {statusFilter || searchTerm
                                                    ? 'No requests match your filter criteria.'
                                                    : 'The relocation queue is currently empty.'}
                                            </p>
                                        </td>
                                    </tr>
                                ) : (
                                    requests.data.map((req) => (
                                        <tr
                                            key={req.id}
                                            className="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition-colors"
                                        >
                                            {/* Target Agent */}
                                            <td className="py-3.5 px-4">
                                                <div className="flex items-center gap-2">
                                                    <span
                                                        className={`px-1.5 py-0.5 rounded text-[10px] font-bold ${
                                                            req.agent?.role === 'high_level_agent'
                                                                ? 'bg-amber-500/15 text-amber-700 dark:text-amber-400'
                                                                : 'bg-indigo-500/15 text-indigo-700 dark:text-indigo-400'
                                                        }`}
                                                    >
                                                        {req.agent?.role === 'high_level_agent' ? 'HLA' : 'LLA'}
                                                    </span>
                                                    <span className="font-semibold text-slate-900 dark:text-white">
                                                        {req.agent?.name ?? `Agent #${req.agent_id}`}
                                                    </span>
                                                </div>
                                                <div className="font-mono text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">
                                                    {req.agent?.referral_code}
                                                </div>
                                            </td>

                                            {/* Current Sponsor */}
                                            <td className="py-3.5 px-4 text-slate-700 dark:text-slate-300">
                                                {req.current_parent ? (
                                                    <div>
                                                        <div className="font-medium">{req.current_parent.name}</div>
                                                        <div className="text-[10px] font-mono text-slate-400">
                                                            {req.current_parent.referral_code}
                                                        </div>
                                                    </div>
                                                ) : (
                                                    <span className="text-slate-400 italic">None (Company-Direct)</span>
                                                )}
                                            </td>

                                            {/* Proposed Sponsor */}
                                            <td className="py-3.5 px-4 text-slate-700 dark:text-slate-300">
                                                {req.requested_parent ? (
                                                    <div>
                                                        <div className="font-medium text-amber-600 dark:text-amber-400">
                                                            {req.requested_parent.name}
                                                        </div>
                                                        <div className="text-[10px] font-mono text-slate-400">
                                                            {req.requested_parent.referral_code}
                                                        </div>
                                                    </div>
                                                ) : (
                                                    <span className="font-semibold text-amber-600 dark:text-amber-400">
                                                        Company-Direct (HLA Promotion)
                                                    </span>
                                                )}
                                            </td>

                                            {/* Subtree Impact */}
                                            <td className="py-3.5 px-4">
                                                <span
                                                    className={`px-2 py-0.5 rounded-md font-medium text-[11px] ${
                                                        req.subtree_size > 0
                                                            ? 'bg-amber-100 dark:bg-amber-950/60 text-amber-800 dark:text-amber-300 border border-amber-300/40'
                                                            : 'bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400'
                                                    }`}
                                                >
                                                    {req.subtree_size > 0
                                                        ? `${req.subtree_size} Downlines Affected`
                                                        : '0 Downlines (Single Node)'}
                                                </span>
                                            </td>

                                            {/* Reason & Requester */}
                                            <td className="py-3.5 px-4 max-w-xs">
                                                <p className="text-slate-800 dark:text-slate-200 truncate" title={req.reason}>
                                                    {req.reason}
                                                </p>
                                                <p className="text-[10px] text-slate-400 mt-0.5">
                                                    By {req.requested_by_user?.name || 'Agent Manager'} •{' '}
                                                    {new Date(req.created_at).toLocaleDateString()}
                                                </p>
                                            </td>

                                            {/* Status & Decision Notes */}
                                            <td className="py-3.5 px-4">
                                                <div className="flex items-center gap-1.5">
                                                    <span
                                                        className={`px-2 py-0.5 rounded-full font-medium text-[10px] inline-flex items-center gap-1 ${
                                                            req.status === 'approved'
                                                                ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400'
                                                                : req.status === 'rejected'
                                                                ? 'bg-rose-500/10 text-rose-700 dark:text-rose-400'
                                                                : 'bg-amber-500/10 text-amber-700 dark:text-amber-400'
                                                        }`}
                                                    >
                                                        <span
                                                            className={`w-1.5 h-1.5 rounded-full ${
                                                                req.status === 'approved'
                                                                    ? 'bg-emerald-500'
                                                                    : req.status === 'rejected'
                                                                    ? 'bg-rose-500'
                                                                    : 'bg-amber-500 animate-pulse'
                                                            }`}
                                                        />
                                                        {req.status.charAt(0).toUpperCase() + req.status.slice(1)}
                                                    </span>
                                                </div>

                                                {req.review_notes && (
                                                    <p
                                                        className="text-[10px] text-slate-500 dark:text-slate-400 mt-1 italic truncate max-w-xs"
                                                        title={req.review_notes}
                                                    >
                                                        Note: {req.review_notes}
                                                    </p>
                                                )}
                                                {req.reviewed_by_user && (
                                                    <p className="text-[9px] text-slate-400">
                                                        Reviewed by {req.reviewed_by_user.name}
                                                    </p>
                                                )}
                                            </td>

                                            {/* Executive Actions */}
                                            <td className="py-3.5 px-4 text-right">
                                                {req.status === 'pending' && isOwner ? (
                                                    <button
                                                        type="button"
                                                        onClick={() => handleOpenReview(req)}
                                                        className="px-3 py-1.5 text-xs font-semibold rounded-lg bg-amber-500 hover:bg-amber-600 text-white shadow-xs transition-colors inline-flex items-center gap-1.5"
                                                    >
                                                        <Icons.ShieldCheck className="w-3.5 h-3.5" />
                                                        <span>Review</span>
                                                    </button>
                                                ) : (
                                                    <span className="text-[11px] text-slate-400">
                                                        {req.status !== 'pending' ? 'Resolved' : 'Awaiting Owner'}
                                                    </span>
                                                )}
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Pagination Links */}
                    {requests.last_page > 1 && (
                        <div className="p-4 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between text-xs text-slate-500">
                            <div>
                                Showing {requests.from} to {requests.to} of {requests.total} requests
                            </div>
                            <div className="flex items-center gap-1">
                                {requests.links.map((link, idx) => (
                                    <Link
                                        key={idx}
                                        href={link.url || '#'}
                                        preserveScroll
                                        className={`px-3 py-1 rounded-md transition-colors ${
                                            link.active
                                                ? 'bg-amber-500 text-white font-semibold'
                                                : link.url
                                                ? 'hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-300'
                                                : 'text-slate-300 dark:text-slate-700 pointer-events-none'
                                        }`}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                    />
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            </div>

            {/* ========================================================================= */}
            {/* MODAL 1: OWNER EXECUTIVE APPROVAL / REJECTION DIALOG (OWNER ONLY)         */}
            {/* ========================================================================= */}
            {reviewModalOpen && selectedRequest && isOwner && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
                    <div className="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 max-w-lg w-full p-6 shadow-2xl space-y-5">
                        {/* Modal Header */}
                        <div className="flex items-start justify-between">
                            <div className="flex items-center gap-3">
                                <span className="p-2 rounded-xl bg-amber-500/15 text-amber-600 dark:text-amber-400">
                                    <Icons.ShieldCheck className="w-5 h-5" />
                                </span>
                                <div>
                                    <h3 className="text-base font-bold text-slate-900 dark:text-white">
                                        Executive Hierarchy Review
                                    </h3>
                                    <p className="text-xs text-slate-500 dark:text-slate-400">
                                        Owner authorization required to relocate agent branches.
                                    </p>
                                </div>
                            </div>
                            <button
                                type="button"
                                onClick={() => setReviewModalOpen(false)}
                                className="p-1 rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
                            >
                                <Icons.XCircle className="w-5 h-5" />
                            </button>
                        </div>

                        {/* Relocation Impact Summary Card */}
                        <div className="p-4 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/60 dark:border-slate-700/60 space-y-3 text-xs">
                            <div className="flex items-center justify-between">
                                <span className="text-slate-500 dark:text-slate-400">Target Agent:</span>
                                <span className="font-bold text-slate-900 dark:text-white">
                                    {selectedRequest.agent?.name} ({selectedRequest.agent?.referral_code})
                                </span>
                            </div>

                            <div className="flex items-center justify-between">
                                <span className="text-slate-500 dark:text-slate-400">Current Sponsor:</span>
                                <span className="font-semibold text-slate-700 dark:text-slate-300">
                                    {selectedRequest.current_parent?.name || 'Company-Direct'}
                                </span>
                            </div>

                            <div className="flex items-center justify-between">
                                <span className="text-slate-500 dark:text-slate-400">Proposed Sponsor:</span>
                                <span className="font-bold text-amber-600 dark:text-amber-400">
                                    {selectedRequest.requested_parent?.name || 'Company-Direct (HLA)'}
                                </span>
                            </div>

                            <div className="flex items-center justify-between pt-2 border-t border-slate-200/60 dark:border-slate-700/60">
                                <span className="text-slate-500 dark:text-slate-400">Subtree Impact:</span>
                                <span className="font-semibold text-slate-900 dark:text-white">
                                    {selectedRequest.subtree_size} downline agents will move atomically
                                </span>
                            </div>

                            <div className="pt-2 border-t border-slate-200/60 dark:border-slate-700/60">
                                <span className="text-slate-500 dark:text-slate-400 block mb-0.5">Manager Reason:</span>
                                <p className="italic text-slate-700 dark:text-slate-300 bg-white dark:bg-slate-900/50 p-2 rounded-lg">
                                    &ldquo;{selectedRequest.reason}&rdquo;
                                </p>
                            </div>
                        </div>

                        {/* Error Alert */}
                        {reviewError && (
                            <div className="p-3 rounded-xl bg-rose-500/10 border border-rose-500/20 text-xs text-rose-700 dark:text-rose-400 flex items-center gap-2">
                                <Icons.AlertCircle className="w-4 h-4 shrink-0" />
                                <span>{reviewError}</span>
                            </div>
                        )}

                        <form onSubmit={handleSubmitReview} className="space-y-4">
                            {/* Action Choice Radio/Buttons */}
                            <div>
                                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
                                    Executive Decision
                                </label>
                                <div className="grid grid-cols-2 gap-3">
                                    <button
                                        type="button"
                                        onClick={() => setReviewAction('approve')}
                                        className={`p-3 rounded-xl text-xs font-semibold border transition-all flex items-center justify-center gap-2 ${
                                            reviewAction === 'approve'
                                                ? 'bg-emerald-500/15 border-emerald-500 text-emerald-700 dark:text-emerald-400 shadow-xs'
                                                : 'border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800'
                                        }`}
                                    >
                                        <Icons.CheckCircle2 className="w-4 h-4" />
                                        <span>Approve Movement</span>
                                    </button>

                                    <button
                                        type="button"
                                        onClick={() => setReviewAction('reject')}
                                        className={`p-3 rounded-xl text-xs font-semibold border transition-all flex items-center justify-center gap-2 ${
                                            reviewAction === 'reject'
                                                ? 'bg-rose-500/15 border-rose-500 text-rose-700 dark:text-rose-400 shadow-xs'
                                                : 'border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800'
                                        }`}
                                    >
                                        <Icons.XCircle className="w-4 h-4" />
                                        <span>Reject Request</span>
                                    </button>
                                </div>
                            </div>

                            {/* Decision Notes */}
                            <div>
                                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                    Decision Notes {reviewAction === 'reject' && <span className="text-rose-500">* (Mandatory for rejection)</span>}
                                </label>
                                <textarea
                                    rows={3}
                                    placeholder={
                                        reviewAction === 'approve'
                                            ? 'Optional notes regarding territorial approval...'
                                            : 'Please enter the specific reason why this movement request is rejected...'
                                    }
                                    value={reviewNotes}
                                    onChange={(e) => setReviewNotes(e.target.value)}
                                    className="w-full p-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500"
                                />
                            </div>

                            {/* Irreversible Action Warning */}
                            <div className="p-3 rounded-xl bg-amber-500/10 border border-amber-500/20 text-[11px] text-amber-800 dark:text-amber-300 flex items-start gap-2">
                                <Icons.Lock className="w-4 h-4 text-amber-500 shrink-0 mt-0.5" />
                                <div>
                                    <span className="font-semibold">Atomic Transaction Protection:</span> Approval executes
                                    an immediate pessimistic row lock, re-checks cycle constraints, and updates the closure table atomically.
                                </div>
                            </div>

                            {/* Modal Footer Controls */}
                            <div className="flex items-center justify-end gap-2.5 pt-2">
                                <button
                                    type="button"
                                    onClick={() => setReviewModalOpen(false)}
                                    disabled={isSubmittingReview}
                                    className="px-4 py-2 rounded-xl text-xs font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                                >
                                    Cancel
                                </button>

                                <button
                                    type="submit"
                                    disabled={isSubmittingReview}
                                    className={`px-4 py-2 rounded-xl text-xs font-semibold text-white shadow-sm transition-colors flex items-center gap-1.5 ${
                                        reviewAction === 'approve'
                                            ? 'bg-emerald-600 hover:bg-emerald-700'
                                            : 'bg-rose-600 hover:bg-rose-700'
                                    }`}
                                >
                                    {isSubmittingReview && (
                                        <span className="w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full animate-spin" />
                                    )}
                                    <span>
                                        {reviewAction === 'approve' ? 'Execute Hierarchy Move' : 'Confirm Rejection'}
                                    </span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* ========================================================================= */}
            {/* MODAL 2: SUBMIT HIERARCHY MOVE REQUEST (AGENT MANAGER & OWNER)             */}
            {/* ========================================================================= */}
            {newRequestModalOpen && canRequest && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
                    <div className="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 max-w-lg w-full p-6 shadow-2xl space-y-5">
                        <div className="flex items-start justify-between">
                            <div className="flex items-center gap-3">
                                <span className="p-2 rounded-xl bg-amber-500/15 text-amber-600 dark:text-amber-400">
                                    <Icons.ArrowRightLeft className="w-5 h-5" />
                                </span>
                                <div>
                                    <h3 className="text-base font-bold text-slate-900 dark:text-white">
                                        Request Hierarchy Move
                                    </h3>
                                    <p className="text-xs text-slate-500 dark:text-slate-400">
                                        Propose an agent sponsor change for Executive Owner approval.
                                    </p>
                                </div>
                            </div>
                            <button
                                type="button"
                                onClick={() => setNewRequestModalOpen(false)}
                                className="p-1 rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
                            >
                                <Icons.XCircle className="w-5 h-5" />
                            </button>
                        </div>

                        {requestFormError && (
                            <div className="p-3 rounded-xl bg-rose-500/10 border border-rose-500/20 text-xs text-rose-700 dark:text-rose-400 flex items-center gap-2">
                                <Icons.AlertCircle className="w-4 h-4 shrink-0" />
                                <span>{requestFormError}</span>
                            </div>
                        )}

                        <form onSubmit={handleSubmitNewRequest} className="space-y-4">
                            {/* 1. Target Agent Selector */}
                            <div>
                                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                    Target Agent to Move <span className="text-rose-500">*</span>
                                </label>
                                <select
                                    value={selectedAgentId}
                                    onChange={(e) => setSelectedAgentId(e.target.value)}
                                    className="w-full p-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500"
                                >
                                    <option value="">-- Choose an Agent --</option>
                                    {eligibleAgents.map((ag) => (
                                        <option key={ag.id} value={ag.id}>
                                            {ag.name} ({ag.referral_code}) — {ag.role === 'high_level_agent' ? 'HLA' : 'LLA'} [{ag.status}]
                                        </option>
                                    ))}
                                </select>
                            </div>

                            {/* Current Sponsor Notice if selected */}
                            {currentSelectedAgent && (
                                <div className="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/60 dark:border-slate-700/60 text-xs space-y-1">
                                    <div className="flex items-center justify-between">
                                        <span className="text-slate-500">Current Role / Tier:</span>
                                        <span className="font-semibold text-slate-900 dark:text-white">
                                            {currentSelectedAgent.role === 'high_level_agent'
                                                ? 'High-Level Agent (Company-Direct)'
                                                : 'Low-Level Agent'}
                                        </span>
                                    </div>
                                    <div className="flex items-center justify-between">
                                        <span className="text-slate-500">Current Sponsor:</span>
                                        <span className="font-semibold text-slate-900 dark:text-white">
                                            {currentSelectedAgent.parent?.name
                                                ? `${currentSelectedAgent.parent.name} (${currentSelectedAgent.parent.referral_code})`
                                                : 'None (Company-Direct)'}
                                        </span>
                                    </div>
                                </div>
                            )}

                            {/* 2. Proposed Sponsor Selector */}
                            <div>
                                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                    Proposed New Sponsor
                                </label>
                                <select
                                    value={selectedSponsorId}
                                    onChange={(e) => setSelectedSponsorId(e.target.value)}
                                    disabled={currentSelectedAgent?.role === 'high_level_agent'}
                                    className="w-full p-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 disabled:opacity-50"
                                >
                                    <option value="">
                                        {currentSelectedAgent?.role === 'high_level_agent'
                                            ? 'None (HLAs Must Remain Company-Direct)'
                                            : '-- Select Proposed Sponsor --'}
                                    </option>
                                    {eligibleSponsors
                                        .filter((s) => String(s.id) !== selectedAgentId)
                                        .map((sp) => (
                                            <option key={sp.id} value={sp.id}>
                                                {sp.name} ({sp.referral_code}) — {sp.role === 'high_level_agent' ? 'HLA Leader' : 'LLA Member'}
                                            </option>
                                        ))}
                                </select>
                                <p className="text-[10px] text-slate-400 mt-1">
                                    Sponsors must be active agents. Circular hierarchy links are blocked by backend cycle detection.
                                </p>
                            </div>

                            {/* 3. Reason for Move */}
                            <div>
                                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                    Reason for Requested Relocation <span className="text-rose-500">*</span>
                                </label>
                                <textarea
                                    rows={3}
                                    placeholder="Explain territorial realignment, branch transfer, or sponsor agreement..."
                                    value={requestReason}
                                    onChange={(e) => setRequestReason(e.target.value)}
                                    className="w-full p-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500"
                                />
                            </div>

                            {/* Modal Controls */}
                            <div className="flex items-center justify-end gap-2.5 pt-2">
                                <button
                                    type="button"
                                    onClick={() => setNewRequestModalOpen(false)}
                                    disabled={isSubmittingRequest}
                                    className="px-4 py-2 rounded-xl text-xs font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                                >
                                    Cancel
                                </button>

                                <button
                                    type="submit"
                                    disabled={isSubmittingRequest}
                                    className="px-4 py-2 rounded-xl text-xs font-semibold bg-amber-500 hover:bg-amber-600 text-white shadow-sm transition-colors flex items-center gap-1.5"
                                >
                                    {isSubmittingRequest && (
                                        <span className="w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full animate-spin" />
                                    )}
                                    <span>Submit for Owner Review</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AdminLayout>
    );
}
