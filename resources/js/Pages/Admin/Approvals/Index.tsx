import * as Icons from '@/Components/Icons';
import AdminLayout from '@/Layouts/AdminLayout';
import { Head, router } from '@inertiajs/react';
import React, { useState } from 'react';

interface AgentProfile {
    business_name?: string;
    nic?: string;
    bank_name?: string;
    bank_branch?: string;
    bank_account_number?: string;
    bank_account_name?: string;
}

interface User {
    id: number;
    name: string;
    email: string;
    referral_code?: string;
    agent_profile?: AgentProfile;
}

interface PayoutRequest {
    id: number;
    reference_number: string;
    agent_id: number;
    amount: string | number;
    currency: string;
    status: string;
    bank_name: string;
    bank_branch: string;
    bank_account_number: string;
    bank_account_name: string;
    created_at: string;
    agent?: User;
}

interface CommissionRuleChange {
    id: number;
    rule_key: string;
    old_value: string;
    new_value: string;
    reason: string;
    created_at: string;
    requester?: User;
    requested_by?: User;
}

interface HierarchyMoveRequest {
    id: number;
    agent_id: number;
    current_parent_id: number | null;
    requested_parent_id: number | null;
    reason: string;
    created_at: string;
    agent?: User;
    current_parent?: User;
    requested_parent?: User;
    requested_by?: User;
}

interface PermissionRequest {
    id: number;
    role_id: number;
    permission_id: number;
    reason: string;
    created_at: string;
    role?: { id: number; name: string };
    permission?: { id: number; name: string; slug: string };
    requester?: User;
}

interface Props {
    activeTab: 'payouts' | 'commission_rules' | 'hierarchy_moves' | 'permission_requests';
    counts: {
        payouts: number;
        commission_rules: number;
        hierarchy_moves: number;
        permission_requests: number;
        total: number;
    };
    payouts: PayoutRequest[];
    commissionRules: CommissionRuleChange[];
    hierarchyMoves: HierarchyMoveRequest[];
    permissionRequests: PermissionRequest[];
}

export default function ApprovalCenter({
    activeTab: initialTab,
    counts,
    payouts,
    commissionRules,
    hierarchyMoves,
    permissionRequests,
}: Props) {
    const [currentTab, setCurrentTab] = useState<'payouts' | 'commission_rules' | 'hierarchy_moves' | 'permission_requests'>(initialTab);
    const [rejectModalOpen, setRejectModalOpen] = useState(false);
    const [rejectAction, setRejectAction] = useState<{ url: string; payloadKey: string; data?: Record<string, string | number | boolean | null | undefined> } | null>(null);
    const [rejectReason, setRejectReason] = useState('');
    const [submitting, setSubmitting] = useState(false);

    const formatCurrency = (amount: number | string) => {
        return new Intl.NumberFormat('en-LK', {
            style: 'currency',
            currency: 'LKR',
            minimumFractionDigits: 2,
        }).format(Number(amount));
    };

    // Generic Approve Handler
    const handleApprove = (url: string, data: Record<string, string | number | boolean | null | undefined> = {}) => {
        if (!confirm('Are you sure you want to approve this request? This action is legally binding.')) return;
        setSubmitting(true);
        router.post(url, data, {
            preserveScroll: true,
            onFinish: () => setSubmitting(false),
        });
    };

    // Open Rejection Dialog
    const openRejectModal = (url: string, payloadKey = 'rejection_reason', additionalData: Record<string, string | number | boolean | null | undefined> = {}) => {
        setRejectReason('');
        setRejectAction({ url, payloadKey, data: additionalData });
        setRejectModalOpen(true);
    };

    // Submit Rejection
    const submitRejection = () => {
        if (!rejectAction || !rejectReason.trim()) return;
        setSubmitting(true);
        const payload: Record<string, string | number | boolean | null | undefined> = {
            ...(rejectAction.data ?? {}),
            [rejectAction.payloadKey]: rejectReason.trim(),
        };

        router.post(rejectAction.url, payload, {
            preserveScroll: true,
            onSuccess: () => {
                setRejectModalOpen(false);
                setRejectAction(null);
                setRejectReason('');
            },
            onFinish: () => setSubmitting(false),
        });
    };

    return (
        <AdminLayout>
            <Head title="Business Approval Center" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white dark:bg-[#0F172A] p-6 rounded-3xl border border-slate-200/80 dark:border-slate-800/80 shadow-2xs">
                    <div>
                        <div className="flex items-center gap-2.5">
                            <div className="w-10 h-10 rounded-2xl bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center font-bold">
                                <Icons.ShieldCheck className="w-6 h-6" />
                            </div>
                            <div>
                                <h1 className="text-xl font-bold tracking-tight text-slate-900 dark:text-white">
                                    Business Approval Center
                                </h1>
                                <p className="text-xs text-slate-500 dark:text-slate-400">
                                    Centralized governance hub for Owner-exclusive commercial & financial authorizations.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div className="flex items-center gap-2">
                        <span className="inline-flex items-center gap-1.5 px-3 py-1.5 bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 rounded-xl text-xs font-semibold border border-amber-200/60 dark:border-amber-800/60">
                            <span className="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span>
                            <span>{counts.total} Pending Decision{counts.total !== 1 ? 's' : ''}</span>
                        </span>
                    </div>
                </div>

                {/* Tab Navigation */}
                <div className="flex items-center gap-2 overflow-x-auto pb-2 custom-scrollbar">
                    <button
                        onClick={() => setCurrentTab('payouts')}
                        className={`inline-flex items-center gap-2 px-4 py-2.5 rounded-2xl text-xs font-semibold cursor-pointer transition-all ${
                            currentTab === 'payouts'
                                ? 'bg-slate-900 dark:bg-white text-white dark:text-slate-900 shadow-sm'
                                : 'bg-white dark:bg-[#0F172A] text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white border border-slate-200/80 dark:border-slate-800/80'
                        }`}
                    >
                        <Icons.DollarSign className="w-4 h-4" />
                        <span>Payout Requests</span>
                        {counts.payouts > 0 && (
                            <span className={`px-2 py-0.5 rounded-full text-[10px] font-bold ${
                                currentTab === 'payouts'
                                    ? 'bg-rose-500 text-white'
                                    : 'bg-rose-100 dark:bg-rose-950/50 text-rose-600 dark:text-rose-400'
                            }`}>
                                {counts.payouts}
                            </span>
                        )}
                    </button>

                    <button
                        onClick={() => setCurrentTab('commission_rules')}
                        className={`inline-flex items-center gap-2 px-4 py-2.5 rounded-2xl text-xs font-semibold cursor-pointer transition-all ${
                            currentTab === 'commission_rules'
                                ? 'bg-slate-900 dark:bg-white text-white dark:text-slate-900 shadow-sm'
                                : 'bg-white dark:bg-[#0F172A] text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white border border-slate-200/80 dark:border-slate-800/80'
                        }`}
                    >
                        <Icons.Tag className="w-4 h-4" />
                        <span>Commission Rules</span>
                        {counts.commission_rules > 0 && (
                            <span className={`px-2 py-0.5 rounded-full text-[10px] font-bold ${
                                currentTab === 'commission_rules'
                                    ? 'bg-rose-500 text-white'
                                    : 'bg-rose-100 dark:bg-rose-950/50 text-rose-600 dark:text-rose-400'
                            }`}>
                                {counts.commission_rules}
                            </span>
                        )}
                    </button>

                    <button
                        onClick={() => setCurrentTab('hierarchy_moves')}
                        className={`inline-flex items-center gap-2 px-4 py-2.5 rounded-2xl text-xs font-semibold cursor-pointer transition-all ${
                            currentTab === 'hierarchy_moves'
                                ? 'bg-slate-900 dark:bg-white text-white dark:text-slate-900 shadow-sm'
                                : 'bg-white dark:bg-[#0F172A] text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white border border-slate-200/80 dark:border-slate-800/80'
                        }`}
                    >
                        <Icons.GitBranch className="w-4 h-4" />
                        <span>Hierarchy Moves</span>
                        {counts.hierarchy_moves > 0 && (
                            <span className={`px-2 py-0.5 rounded-full text-[10px] font-bold ${
                                currentTab === 'hierarchy_moves'
                                    ? 'bg-rose-500 text-white'
                                    : 'bg-rose-100 dark:bg-rose-950/50 text-rose-600 dark:text-rose-400'
                            }`}>
                                {counts.hierarchy_moves}
                            </span>
                        )}
                    </button>

                    <button
                        onClick={() => setCurrentTab('permission_requests')}
                        className={`inline-flex items-center gap-2 px-4 py-2.5 rounded-2xl text-xs font-semibold cursor-pointer transition-all ${
                            currentTab === 'permission_requests'
                                ? 'bg-slate-900 dark:bg-white text-white dark:text-slate-900 shadow-sm'
                                : 'bg-white dark:bg-[#0F172A] text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white border border-slate-200/80 dark:border-slate-800/80'
                        }`}
                    >
                        <Icons.Lock className="w-4 h-4" />
                        <span>Permission Requests</span>
                        {counts.permission_requests > 0 && (
                            <span className={`px-2 py-0.5 rounded-full text-[10px] font-bold ${
                                currentTab === 'permission_requests'
                                    ? 'bg-rose-500 text-white'
                                    : 'bg-rose-100 dark:bg-rose-950/50 text-rose-600 dark:text-rose-400'
                            }`}>
                                {counts.permission_requests}
                            </span>
                        )}
                    </button>
                </div>

                {/* TAB 1: PAYOUT REQUESTS */}
                {currentTab === 'payouts' && (
                    <div className="space-y-4">
                        {payouts.length === 0 ? (
                            <div className="bg-white dark:bg-[#0F172A] p-12 text-center rounded-3xl border border-slate-200/80 dark:border-slate-800/80">
                                <Icons.CheckCircle2 className="w-12 h-12 text-emerald-500 mx-auto mb-3" />
                                <h3 className="text-base font-bold text-slate-900 dark:text-white">All Payouts Clear</h3>
                                <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">There are no pending agent payout requests awaiting your approval.</p>
                            </div>
                        ) : (
                            payouts.map((p) => (
                                <div key={p.id} className="bg-white dark:bg-[#0F172A] p-5 sm:p-6 rounded-3xl border border-slate-200/80 dark:border-slate-800/80 shadow-2xs flex flex-col md:flex-row md:items-center justify-between gap-6">
                                    <div className="space-y-2">
                                        <div className="flex items-center gap-3">
                                            <span className="font-mono text-xs font-bold text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-950/40 px-2.5 py-1 rounded-xl">
                                                {p.reference_number}
                                            </span>
                                            <span className="text-xs text-slate-400">
                                                Requested: {new Date(p.created_at).toLocaleString()}
                                            </span>
                                        </div>

                                        <div className="flex items-center gap-3">
                                            <div className="w-10 h-10 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-900 dark:text-white flex items-center justify-center font-bold text-sm">
                                                {p.agent?.name?.charAt(0) ?? 'A'}
                                            </div>
                                            <div>
                                                <div className="text-sm font-bold text-slate-900 dark:text-white">
                                                    {p.agent?.name}
                                                </div>
                                                <div className="text-xs text-slate-500 dark:text-slate-400 flex items-center gap-2">
                                                    <span>Ref: {p.agent?.referral_code ?? 'N/A'}</span>
                                                    <span>&bull;</span>
                                                    <span>{p.agent?.email}</span>
                                                </div>
                                            </div>
                                        </div>

                                        <div className="grid grid-cols-2 sm:grid-cols-3 gap-3 pt-2 text-xs">
                                            <div className="bg-slate-50 dark:bg-slate-900/50 p-2.5 rounded-xl">
                                                <div className="text-[10px] text-slate-400 uppercase font-mono">Bank Name</div>
                                                <div className="font-semibold text-slate-800 dark:text-slate-200">{p.bank_name}</div>
                                            </div>
                                            <div className="bg-slate-50 dark:bg-slate-900/50 p-2.5 rounded-xl">
                                                <div className="text-[10px] text-slate-400 uppercase font-mono">Account Number</div>
                                                <div className="font-mono font-semibold text-slate-800 dark:text-slate-200">{p.bank_account_number}</div>
                                            </div>
                                            <div className="bg-slate-50 dark:bg-slate-900/50 p-2.5 rounded-xl">
                                                <div className="text-[10px] text-slate-400 uppercase font-mono">Beneficiary Name</div>
                                                <div className="font-semibold text-slate-800 dark:text-slate-200 truncate">{p.bank_account_name}</div>
                                            </div>
                                        </div>
                                    </div>

                                    <div className="flex flex-col sm:flex-row md:flex-col items-start md:items-end justify-between gap-4 shrink-0 border-t md:border-t-0 pt-4 md:pt-0 border-slate-100 dark:border-slate-800">
                                        <div className="text-left md:text-right">
                                            <div className="text-[11px] font-mono text-slate-400 uppercase">Requested Amount</div>
                                            <div className="text-2xl font-black tracking-tight text-slate-900 dark:text-white">
                                                {formatCurrency(p.amount)}
                                            </div>
                                        </div>

                                        <div className="flex items-center gap-2 w-full sm:w-auto">
                                            <button
                                                disabled={submitting}
                                                onClick={() => handleApprove(`/admin/payouts/${p.id}/approve`)}
                                                className="flex-1 sm:flex-initial inline-flex items-center justify-center gap-1.5 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-semibold shadow-xs cursor-pointer transition-colors"
                                            >
                                                <Icons.CheckCircle2 className="w-4 h-4" />
                                                <span>Approve Payout</span>
                                            </button>
                                            <button
                                                disabled={submitting}
                                                onClick={() => openRejectModal(`/admin/payouts/${p.id}/reject`, 'rejection_reason')}
                                                className="flex-1 sm:flex-initial inline-flex items-center justify-center gap-1.5 px-3 py-2 text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/40 rounded-xl text-xs font-semibold border border-rose-200/60 dark:border-rose-900/60 cursor-pointer transition-colors"
                                            >
                                                <Icons.XCircle className="w-4 h-4" />
                                                <span>Reject</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            ))
                        )}
                    </div>
                )}

                {/* TAB 2: COMMISSION RULE CHANGES */}
                {currentTab === 'commission_rules' && (
                    <div className="space-y-4">
                        {commissionRules.length === 0 ? (
                            <div className="bg-white dark:bg-[#0F172A] p-12 text-center rounded-3xl border border-slate-200/80 dark:border-slate-800/80">
                                <Icons.CheckCircle2 className="w-12 h-12 text-emerald-500 mx-auto mb-3" />
                                <h3 className="text-base font-bold text-slate-900 dark:text-white">No Rule Changes Pending</h3>
                                <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">Commission rates are currently locked at approved business parameters.</p>
                            </div>
                        ) : (
                            commissionRules.map((r) => (
                                <div key={r.id} className="bg-white dark:bg-[#0F172A] p-5 sm:p-6 rounded-3xl border border-slate-200/80 dark:border-slate-800/80 shadow-2xs flex flex-col md:flex-row md:items-center justify-between gap-6">
                                    <div className="space-y-2">
                                        <div className="flex items-center gap-2">
                                            <span className="text-xs font-mono font-bold bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 px-2.5 py-1 rounded-xl">
                                                {r.rule_key.replace(/_/g, ' ').toUpperCase()}
                                            </span>
                                            <span className="text-xs text-slate-400">
                                                Submitted: {new Date(r.created_at).toLocaleString()}
                                            </span>
                                        </div>

                                        <div className="flex items-center gap-4 py-2">
                                            <div className="bg-slate-100 dark:bg-slate-800 px-4 py-2 rounded-2xl">
                                                <div className="text-[10px] text-slate-400 font-mono uppercase">Current Rate</div>
                                                <div className="text-lg font-bold text-slate-700 dark:text-slate-300">{r.old_value}%</div>
                                            </div>
                                            <Icons.ChevronRight className="w-5 h-5 text-slate-400" />
                                            <div className="bg-blue-50 dark:bg-blue-950/40 px-4 py-2 rounded-2xl border border-blue-200/60 dark:border-blue-900/60">
                                                <div className="text-[10px] text-blue-500 font-mono uppercase">Proposed Rate</div>
                                                <div className="text-lg font-black text-blue-600 dark:text-blue-400">{r.new_value}%</div>
                                            </div>
                                        </div>

                                        <div className="text-xs text-slate-600 dark:text-slate-300 bg-slate-50 dark:bg-slate-900/40 p-3 rounded-2xl">
                                            <span className="font-semibold text-slate-800 dark:text-slate-200">Justification: </span>
                                            <span>{r.reason}</span>
                                        </div>
                                    </div>

                                    <div className="flex items-center gap-2 shrink-0">
                                        <button
                                            disabled={submitting}
                                            onClick={() => handleApprove(`/admin/commissions/rules/${r.id}/review`, { action: 'approve' })}
                                            className="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-semibold shadow-xs cursor-pointer transition-colors"
                                        >
                                            Approve Rate Change
                                        </button>
                                        <button
                                            disabled={submitting}
                                            onClick={() => openRejectModal(`/admin/commissions/rules/${r.id}/review`, 'review_notes', { action: 'reject' })}
                                            className="px-3 py-2 text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/40 rounded-xl text-xs font-semibold border border-rose-200/60 dark:border-rose-900/60 cursor-pointer transition-colors"
                                        >
                                            Reject
                                        </button>
                                    </div>
                                </div>
                            ))
                        )}
                    </div>
                )}

                {/* TAB 3: HIERARCHY MOVE REQUESTS */}
                {currentTab === 'hierarchy_moves' && (
                    <div className="space-y-4">
                        {hierarchyMoves.length === 0 ? (
                            <div className="bg-white dark:bg-[#0F172A] p-12 text-center rounded-3xl border border-slate-200/80 dark:border-slate-800/80">
                                <Icons.CheckCircle2 className="w-12 h-12 text-emerald-500 mx-auto mb-3" />
                                <h3 className="text-base font-bold text-slate-900 dark:text-white">No Hierarchy Moves Pending</h3>
                                <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">Agent network sponsorship structure is stable.</p>
                            </div>
                        ) : (
                            hierarchyMoves.map((h) => (
                                <div key={h.id} className="bg-white dark:bg-[#0F172A] p-5 sm:p-6 rounded-3xl border border-slate-200/80 dark:border-slate-800/80 shadow-2xs flex flex-col md:flex-row md:items-center justify-between gap-6">
                                    <div className="space-y-2">
                                        <div className="text-xs text-slate-400">
                                            Request ID: #{h.id} &bull; {new Date(h.created_at).toLocaleString()}
                                        </div>

                                        <div className="text-sm font-bold text-slate-900 dark:text-white">
                                            Agent: {h.agent?.name} ({h.agent?.referral_code})
                                        </div>

                                        <div className="flex items-center gap-3 pt-1 text-xs">
                                            <div className="bg-slate-100 dark:bg-slate-800 p-2.5 rounded-xl">
                                                <div className="text-[10px] text-slate-400 font-mono">Current Upline</div>
                                                <div className="font-semibold text-slate-700 dark:text-slate-300">
                                                    {h.current_parent?.name ?? 'Top Level (None)'}
                                                </div>
                                            </div>
                                            <Icons.ChevronRight className="w-4 h-4 text-slate-400" />
                                            <div className="bg-blue-50 dark:bg-blue-950/40 p-2.5 rounded-xl border border-blue-200/60 dark:border-blue-900/60">
                                                <div className="text-[10px] text-blue-500 font-mono">Proposed Upline</div>
                                                <div className="font-semibold text-blue-700 dark:text-blue-300">
                                                    {h.requested_parent?.name ?? 'Root Network'}
                                                </div>
                                            </div>
                                        </div>

                                        <div className="text-xs text-slate-600 dark:text-slate-400 bg-slate-50 dark:bg-slate-900/40 p-2.5 rounded-xl">
                                            Reason: {h.reason}
                                        </div>
                                    </div>

                                    <div className="flex items-center gap-2 shrink-0">
                                        <button
                                            disabled={submitting}
                                            onClick={() => handleApprove(`/admin/agents/hierarchy-requests/${h.id}/review`, { action: 'approve' })}
                                            className="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-semibold shadow-xs cursor-pointer transition-colors"
                                        >
                                            Approve Move
                                        </button>
                                        <button
                                            disabled={submitting}
                                            onClick={() => openRejectModal(`/admin/agents/hierarchy-requests/${h.id}/review`, 'review_notes', { action: 'reject' })}
                                            className="px-3 py-2 text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/40 rounded-xl text-xs font-semibold border border-rose-200/60 dark:border-rose-900/60 cursor-pointer transition-colors"
                                        >
                                            Reject
                                        </button>
                                    </div>
                                </div>
                            ))
                        )}
                    </div>
                )}

                {/* TAB 4: PERMISSION REQUESTS */}
                {currentTab === 'permission_requests' && (
                    <div className="space-y-4">
                        {permissionRequests.length === 0 ? (
                            <div className="bg-white dark:bg-[#0F172A] p-12 text-center rounded-3xl border border-slate-200/80 dark:border-slate-800/80">
                                <Icons.CheckCircle2 className="w-12 h-12 text-emerald-500 mx-auto mb-3" />
                                <h3 className="text-base font-bold text-slate-900 dark:text-white">No Permission Requests</h3>
                                <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">Super Admin has not requested any elevated financial roles.</p>
                            </div>
                        ) : (
                            permissionRequests.map((pr) => (
                                <div key={pr.id} className="bg-white dark:bg-[#0F172A] p-5 sm:p-6 rounded-3xl border border-slate-200/80 dark:border-slate-800/80 shadow-2xs flex flex-col md:flex-row md:items-center justify-between gap-6">
                                    <div className="space-y-2">
                                        <div className="flex items-center gap-2">
                                            <span className="text-xs font-mono font-bold bg-purple-50 dark:bg-purple-950/40 text-purple-700 dark:text-purple-300 px-2.5 py-1 rounded-xl">
                                                Role: {pr.role?.name}
                                            </span>
                                            <span className="text-xs text-slate-400">
                                                Requested: {new Date(pr.created_at).toLocaleString()}
                                            </span>
                                        </div>

                                        <div className="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                            <Icons.Lock className="w-4 h-4 text-amber-500" />
                                            <span>Requested Permission: {pr.permission?.name} ({pr.permission?.slug})</span>
                                        </div>

                                        <div className="text-xs text-slate-600 dark:text-slate-300 bg-slate-50 dark:bg-slate-900/40 p-3 rounded-2xl">
                                            <span className="font-semibold text-slate-800 dark:text-slate-200">Justification: </span>
                                            <span>{pr.reason}</span>
                                        </div>
                                    </div>

                                    <div className="flex items-center gap-2 shrink-0">
                                        <button
                                            disabled={submitting}
                                            onClick={() => handleApprove(`/admin/roles/permission-requests/${pr.id}/approve`)}
                                            className="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-semibold shadow-xs cursor-pointer transition-colors"
                                        >
                                            Approve Permission
                                        </button>
                                        <button
                                            disabled={submitting}
                                            onClick={() => openRejectModal(`/admin/roles/permission-requests/${pr.id}/reject`, 'review_notes')}
                                            className="px-3 py-2 text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/40 rounded-xl text-xs font-semibold border border-rose-200/60 dark:border-rose-900/60 cursor-pointer transition-colors"
                                        >
                                            Reject
                                        </button>
                                    </div>
                                </div>
                            ))
                        )}
                    </div>
                )}

                {/* Rejection Modal */}
                {rejectModalOpen && (
                    <div className="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
                        <div className="bg-white dark:bg-[#1E293B] max-w-md w-full rounded-3xl p-6 shadow-2xl border border-slate-200/80 dark:border-slate-700/80 space-y-4">
                            <div className="flex items-center gap-2.5">
                                <div className="w-10 h-10 rounded-2xl bg-rose-50 dark:bg-rose-950/50 text-rose-600 dark:text-rose-400 flex items-center justify-center">
                                    <Icons.AlertCircle className="w-5 h-5" />
                                </div>
                                <div>
                                    <h3 className="text-base font-bold text-slate-900 dark:text-white">Reject Request</h3>
                                    <p className="text-xs text-slate-500 dark:text-slate-400">Please provide a mandatory justification for the audit trail.</p>
                                </div>
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                    Reason for Rejection *
                                </label>
                                <textarea
                                    value={rejectReason}
                                    onChange={(e) => setRejectReason(e.target.value)}
                                    placeholder="State clear reasons why this commercial action is being denied..."
                                    rows={3}
                                    className="w-full p-3 text-xs bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-2xl focus:outline-none focus:ring-2 focus:ring-rose-500/20 text-slate-900 dark:text-white"
                                />
                            </div>

                            <div className="flex items-center justify-end gap-2 pt-2">
                                <button
                                    onClick={() => setRejectModalOpen(false)}
                                    className="px-4 py-2 text-xs font-semibold text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-xl cursor-pointer"
                                >
                                    Cancel
                                </button>
                                <button
                                    disabled={!rejectReason.trim() || submitting}
                                    onClick={submitRejection}
                                    className="px-4 py-2 text-xs font-semibold bg-rose-600 hover:bg-rose-700 disabled:opacity-50 text-white rounded-xl shadow-xs cursor-pointer transition-colors"
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
