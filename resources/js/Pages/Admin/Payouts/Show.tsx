import React, { useState } from 'react';
import { Head, Link, useForm, router, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { PageProps, PayoutRequest } from '@/types';
import {
    CreditCard,
    Clock,
    CheckCircle2,
    XCircle,
    Building2,
    ShieldAlert,
} from '@/Components/Icons';

interface ShowProps extends PageProps {
    payout: PayoutRequest;
    is_underfunded?: boolean;
    has_aggregate_deficit?: boolean;
    aggregate_shortfall?: number;
}

export default function Show({
    payout,
    is_underfunded = false,
    has_aggregate_deficit = false,
    aggregate_shortfall = 0,
}: ShowProps) {
    const { auth } = usePage<PageProps>().props;
    const isOwner = auth.user.role === 'owner';
    const isAccountantOrOwner = auth.user.role === 'owner' || auth.user.role === 'accountant';

    // Modals
    const [isApproveOpen, setIsApproveOpen] = useState(false);
    const [isRejectOpen, setIsRejectOpen] = useState(false);
    const [isPayOpen, setIsPayOpen] = useState(false);
    const [isFailOpen, setIsFailOpen] = useState(false);

    const { data: approveData, setData: setApproveData, post: postApprove, processing: approveProcessing } = useForm({
        review_notes: '',
    });

    const { data: rejectData, setData: setRejectData, post: postReject, processing: rejectProcessing, errors: rejectErrors } = useForm({
        rejection_reason: '',
    });

    const { data: failData, setData: setFailData, post: postFail, processing: failProcessing, errors: failErrors } = useForm({
        failure_reason: '',
    });

    const { data: payData, setData: setPayData, post: postPay, processing: payProcessing, errors: payErrors } = useForm<{
        bank_transfer_reference: string;
        bank_transfer_notes: string;
        receipt: File | null;
    }>({
        bank_transfer_reference: '',
        bank_transfer_notes: '',
        receipt: null,
    });

    const handleApprove = (e: React.FormEvent) => {
        e.preventDefault();
        postApprove(`/admin/payouts/${payout.id}/approve`, {
            onSuccess: () => setIsApproveOpen(false),
        });
    };

    const handleReject = (e: React.FormEvent) => {
        e.preventDefault();
        postReject(`/admin/payouts/${payout.id}/reject`, {
            onSuccess: () => setIsRejectOpen(false),
        });
    };

    const handleMarkProcessing = () => {
        if (confirm('Mark this payout as actively processing with the bank?')) {
            router.post(`/admin/payouts/${payout.id}/process`);
        }
    };

    const handleRecordPayment = (e: React.FormEvent) => {
        e.preventDefault();
        postPay(`/admin/payouts/${payout.id}/record-payment`, {
            forceFormData: true,
            onSuccess: () => setIsPayOpen(false),
        });
    };

    const handleFail = (e: React.FormEvent) => {
        e.preventDefault();
        postFail(`/admin/payouts/${payout.id}/fail`, {
            onSuccess: () => setIsFailOpen(false),
        });
    };

    const getStatusBadge = (status: string) => {
        switch (status) {
            case 'pending':
                return (
                    <span className="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-amber-100 dark:bg-amber-950/60 text-amber-800 dark:text-amber-300 border border-amber-200 dark:border-amber-900/60">
                        Pending Owner Approval
                    </span>
                );
            case 'approved':
                return (
                    <span className="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-blue-100 dark:bg-blue-950/60 text-blue-800 dark:text-blue-300 border border-blue-200 dark:border-blue-900/60">
                        Approved (Ready For Remittance)
                    </span>
                );
            case 'processing':
                return (
                    <span className="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-indigo-100 dark:bg-indigo-950/60 text-indigo-800 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-900/60">
                        Processing With Bank
                    </span>
                );
            case 'paid':
                return (
                    <span className="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-emerald-100 dark:bg-emerald-950/60 text-emerald-800 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-900/60">
                        Settled & Paid
                    </span>
                );
            case 'rejected':
                return (
                    <span className="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-rose-100 dark:bg-rose-950/60 text-rose-800 dark:text-rose-300 border border-rose-200 dark:border-rose-900/60">
                        Rejected
                    </span>
                );
            case 'cancelled':
                return (
                    <span className="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700">
                        Cancelled by Agent
                    </span>
                );
            default:
                return (
                    <span className="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-slate-100 dark:bg-slate-800 text-slate-800 dark:text-slate-200 border border-slate-200 dark:border-slate-700">
                        {status}
                    </span>
                );
        }
    };

    return (
        <AdminLayout>
            <Head title={`Payout #${payout.reference_number}`} />

            <div className="space-y-6">
                {/* Top Nav & Breadcrumbs */}
                <div className="flex items-center justify-between">
                    <Link
                        href="/admin/payouts"
                        className="text-xs font-semibold text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white flex items-center gap-1 transition-colors"
                    >
                        &larr; Back to Payout Queue
                    </Link>

                    <div className="flex items-center gap-2">
                        {payout.status === 'pending' && isOwner && (
                            <>
                                <button
                                    type="button"
                                    onClick={() => setIsApproveOpen(true)}
                                    className="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold shadow-xs cursor-pointer transition-colors"
                                >
                                    Approve Request
                                </button>
                                <button
                                    type="button"
                                    onClick={() => setIsRejectOpen(true)}
                                    className="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded-xl text-xs font-bold shadow-xs cursor-pointer transition-colors"
                                >
                                    Reject Request
                                </button>
                            </>
                        )}

                        {payout.status === 'approved' && isAccountantOrOwner && (
                            <button
                                type="button"
                                onClick={handleMarkProcessing}
                                disabled={is_underfunded || has_aggregate_deficit}
                                title={is_underfunded || has_aggregate_deficit ? "Underfunded payout: cannot move to processing" : undefined}
                                className={`px-4 py-2 rounded-xl text-xs font-bold shadow-xs transition-colors ${
                                    is_underfunded || has_aggregate_deficit
                                        ? 'bg-slate-300 dark:bg-slate-700 text-slate-500 cursor-not-allowed'
                                        : 'bg-indigo-600 hover:bg-indigo-700 text-white cursor-pointer'
                                }`}
                            >
                                Mark Processing
                            </button>
                        )}

                        {(payout.status === 'approved' || payout.status === 'processing') && isAccountantOrOwner && (
                            <>
                                <button
                                    type="button"
                                    onClick={() => setIsPayOpen(true)}
                                    disabled={is_underfunded || has_aggregate_deficit}
                                    title={is_underfunded || has_aggregate_deficit ? "Underfunded payout: do not transfer bank funds" : undefined}
                                    className={`px-4 py-2 rounded-xl text-xs font-bold shadow-xs transition-colors ${
                                        is_underfunded || has_aggregate_deficit
                                            ? 'bg-slate-300 dark:bg-slate-700 text-slate-500 cursor-not-allowed'
                                            : 'bg-blue-600 hover:bg-blue-700 text-white cursor-pointer'
                                    }`}
                                >
                                    Record Bank Transfer Paid
                                </button>
                                <button
                                    type="button"
                                    onClick={() => setIsFailOpen(true)}
                                    className="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-xl text-xs font-bold shadow-xs cursor-pointer transition-colors"
                                >
                                    Mark Transfer Failed
                                </button>
                            </>
                        )}
                    </div>
                </div>

                {/* Underfunding & Aggregate Deficit Alert Banner */}
                {(is_underfunded || has_aggregate_deficit) && (
                    <div className="bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-900/60 rounded-2xl p-5 mb-6 text-xs text-rose-900 dark:text-rose-200 flex items-start gap-3 shadow-xs">
                        <ShieldAlert className="w-5 h-5 text-rose-600 dark:text-rose-400 shrink-0 mt-0.5" />
                        <div className="space-y-1">
                            <p className="font-bold text-sm text-rose-700 dark:text-rose-300">
                                ⚠️ Underfunded Payout — DO NOT Initiate External Bank Transfer
                            </p>
                            <p className="text-rose-600 dark:text-rose-400">
                                {is_underfunded
                                    ? `Agent current wallet balance (LKR ${Number(payout.agent?.agent_profile?.wallet_balance ?? 0).toFixed(2)}) is less than requested payout amount (LKR ${Number(payout.amount).toFixed(2)}) due to commission clawbacks.`
                                    : `Agent has an aggregate reservation deficit of LKR ${Number(aggregate_shortfall).toFixed(2)} across multiple in-flight payouts.`}
                                {" "}To protect financial integrity, bank transfer recording is locked. The executive Owner must reject this request to release funds for agent resubmission.
                            </p>
                        </div>
                    </div>
                )}

                {/* Primary Card */}
                <div className="bg-white dark:bg-[#0F172A] rounded-2xl border border-slate-200/80 dark:border-slate-800/80 p-8 shadow-xs transition-colors">
                    <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-6 border-b border-slate-100 dark:border-slate-800/80">
                        <div>
                            <span className="text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                                Commission Payout Record
                            </span>
                            <h1 className="text-2xl font-serif font-bold text-slate-900 dark:text-white mt-1 font-mono">
                                #{payout.reference_number}
                            </h1>
                            <p className="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                Submitted on {new Date(payout.created_at).toLocaleString()}
                            </p>
                        </div>

                        <div className="flex flex-col items-start md:items-end gap-2">
                            {getStatusBadge(payout.status)}
                            <p className="text-3xl font-extrabold font-mono text-slate-900 dark:text-white">
                                LKR {Number(payout.amount).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                            </p>
                        </div>
                    </div>

                    {/* Details Grid */}
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 py-6 border-b border-slate-100 dark:border-slate-800/80 text-xs">
                        {/* Agent Information */}
                        <div className="space-y-1.5">
                            <span className="text-[11px] font-bold text-slate-400 dark:text-slate-500 uppercase">Beneficiary Agent</span>
                            <p className="text-sm font-bold text-slate-900 dark:text-white">{payout.agent?.name}</p>
                            <p className="text-slate-600 dark:text-slate-400 font-mono">Code: {payout.agent?.referral_code}</p>
                            <p className="text-slate-600 dark:text-slate-400">Email: {payout.agent?.email}</p>
                            <p className="text-slate-600 dark:text-slate-400">
                                Role: <span className="uppercase font-semibold text-slate-800 dark:text-slate-200">{payout.agent?.role}</span>
                            </p>
                            <p className="text-slate-600 dark:text-slate-400">
                                Current Wallet: <strong className="font-mono text-emerald-700 dark:text-emerald-400">LKR {Number(payout.agent?.agent_profile?.wallet_balance ?? 0).toFixed(2)}</strong>
                            </p>
                        </div>

                        {/* Immutable Bank Snapshot at Request Time */}
                        <div className="space-y-1.5">
                            <span className="text-[11px] font-bold text-slate-400 dark:text-slate-500 uppercase">
                                Immutable Bank Destination Snapshot
                            </span>
                            <p className="text-sm font-bold text-slate-900 dark:text-white">{payout.bank_name}</p>
                            {payout.bank_branch && <p className="text-slate-700 dark:text-slate-300">Branch: {payout.bank_branch}</p>}
                            <p className="text-slate-700 dark:text-slate-300 font-mono font-bold">Account #: {payout.bank_account_number}</p>
                            <p className="text-slate-600 dark:text-slate-400">Account Name: {payout.bank_account_name}</p>
                            {payout.bank_snapshot?.captured_at && (
                                <p className="text-[10px] text-slate-400 dark:text-slate-500 font-mono">
                                    Snapshot Captured: {payout.bank_snapshot.captured_at}
                                </p>
                            )}
                        </div>

                        {/* Audit Trail & Approvals */}
                        <div className="space-y-1.5">
                            <span className="text-[11px] font-bold text-slate-400 dark:text-slate-500 uppercase">Governance & Audit</span>
                            {payout.reviewed_by && (
                                <p className="text-slate-700 dark:text-slate-300">
                                    Reviewed By: <strong className="text-slate-900 dark:text-white">{payout.reviewer?.name}</strong>
                                    <span className="block text-[11px] text-slate-500 dark:text-slate-400 font-mono">
                                        At: {payout.reviewed_at ? new Date(payout.reviewed_at).toLocaleString() : ''}
                                    </span>
                                </p>
                            )}
                            {payout.review_notes && (
                                <p className="text-slate-600 dark:text-slate-400 italic">Notes: "{payout.review_notes}"</p>
                            )}
                            {payout.rejection_reason && (
                                <p className="text-rose-700 dark:text-rose-400">
                                    Rejection Reason: <strong>{payout.rejection_reason}</strong>
                                </p>
                            )}
                            {payout.paid_by && (
                                <p className="text-slate-700 dark:text-slate-300">
                                    Paid By: <strong className="text-slate-900 dark:text-white">{payout.payer?.name}</strong>
                                    <span className="block text-[11px] text-slate-500 dark:text-slate-400 font-mono">
                                        At: {payout.paid_at ? new Date(payout.paid_at).toLocaleString() : ''}
                                    </span>
                                </p>
                            )}
                        </div>
                    </div>

                    {/* Bank Transfer Execution Info */}
                    {payout.bank_transfer_reference && (
                        <div className="py-6 border-b border-slate-100 dark:border-slate-800/80">
                            <h3 className="text-xs font-bold uppercase text-slate-400 dark:text-slate-500 tracking-wider mb-2">
                                Bank Transfer Execution Details
                            </h3>
                            <div className="p-4 bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 rounded-2xl flex flex-col sm:flex-row sm:items-center justify-between gap-4 text-xs">
                                <div>
                                    <span className="text-emerald-800 dark:text-emerald-300 font-semibold uppercase">Transfer Reference:</span>
                                    <p className="text-base font-mono font-bold text-emerald-950 dark:text-emerald-200 mt-0.5">
                                        {payout.bank_transfer_reference}
                                    </p>
                                    {payout.bank_transfer_notes && (
                                        <p className="text-emerald-900 dark:text-emerald-300 mt-1">{payout.bank_transfer_notes}</p>
                                    )}
                                </div>

                                {payout.bank_transfer_receipt_path && (
                                    <a
                                        href={`/admin/payouts/${payout.id}/receipt`}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded-xl shrink-0 transition-colors"
                                    >
                                        Download Bank Receipt &rarr;
                                    </a>
                                )}
                            </div>
                        </div>
                    )}

                    {/* Agent Notes if provided */}
                    {payout.agent_notes && (
                        <div className="py-4 border-b border-slate-100 dark:border-slate-800/80 text-xs">
                            <span className="text-[11px] font-bold text-slate-400 dark:text-slate-500 uppercase">Agent Submission Notes</span>
                            <p className="text-slate-700 dark:text-slate-300 mt-1 italic">"{payout.agent_notes}"</p>
                        </div>
                    )}

                    {/* Associated Wallet Transactions */}
                    <div className="pt-6">
                        <h3 className="text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-3">
                            Associated Journal Ledger Transactions
                        </h3>

                        {!payout.wallet_transactions || payout.wallet_transactions.length === 0 ? (
                            <div className="text-xs text-slate-400 dark:text-slate-500 italic">No direct linked transactions.</div>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="min-w-full text-xs divide-y divide-slate-100 dark:divide-slate-800/80 bg-white dark:bg-[#0F172A]">
                                    <thead className="bg-slate-50 dark:bg-slate-900/60 text-slate-500 dark:text-slate-400 uppercase font-semibold text-[11px]">
                                        <tr>
                                            <th className="py-2.5 px-3 text-left">Tx #</th>
                                            <th className="py-2.5 px-3 text-left">Date</th>
                                            <th className="py-2.5 px-3 text-left">Type</th>
                                            <th className="py-2.5 px-3 text-left">Description</th>
                                            <th className="py-2.5 px-3 text-right">Amount</th>
                                            <th className="py-2.5 px-3 text-right">Balance After</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100 dark:divide-slate-800/60 font-sans bg-white dark:bg-[#0F172A]">
                                        {payout.wallet_transactions.map((tx) => (
                                            <tr key={tx.id} className="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition-colors">
                                                <td className="py-2 px-3 font-mono text-slate-900 dark:text-white font-medium">{tx.transaction_number}</td>
                                                <td className="py-2 px-3 text-slate-500 dark:text-slate-400">{new Date(tx.created_at).toLocaleString()}</td>
                                                <td className="py-2 px-3 uppercase font-semibold text-slate-700 dark:text-slate-300">{tx.transaction_type}</td>
                                                <td className="py-2 px-3 text-slate-800 dark:text-slate-200">{tx.description}</td>
                                                <td className="py-2 px-3 font-mono font-bold text-right text-slate-900 dark:text-white">
                                                    LKR {Number(tx.amount).toFixed(2)}
                                                </td>
                                                <td className="py-2 px-3 font-mono font-semibold text-right text-slate-900 dark:text-white">
                                                    LKR {Number(tx.balance_after).toFixed(2)}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>
                </div>
            </div>

            {/* Approve Modal (Owner) */}
            {isApproveOpen && (
                <div className="fixed inset-0 z-50 bg-slate-900/60 dark:bg-black/80 backdrop-blur-xs flex items-center justify-center p-4">
                    <div className="bg-white dark:bg-[#0F172A] rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-200/80 dark:border-slate-800 text-slate-900 dark:text-white">
                        <h3 className="text-lg font-serif font-bold text-slate-900 dark:text-white">
                            Approve Commission Payout
                        </h3>
                        <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
                            Owner Authorization for #{payout.reference_number} (LKR {Number(payout.amount).toFixed(2)})
                        </p>
                        <form onSubmit={handleApprove} className="mt-4 space-y-4">
                            <div>
                                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase mb-1">
                                    Review Notes (Optional)
                                </label>
                                <textarea
                                    value={approveData.review_notes}
                                    onChange={(e) => setApproveData('review_notes', e.target.value)}
                                    rows={2}
                                    className="w-full px-3 py-2 border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white rounded-xl text-xs"
                                    placeholder="Add any authorization notes..."
                                />
                            </div>
                            <div className="pt-2 flex justify-end gap-3">
                                <button
                                    type="button"
                                    onClick={() => setIsApproveOpen(false)}
                                    className="px-4 py-2 text-xs font-semibold text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={approveProcessing}
                                    className="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold cursor-pointer transition-colors"
                                >
                                    {approveProcessing ? 'Authorizing...' : 'Authorize Payout'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Reject Modal (Owner) */}
            {isRejectOpen && (
                <div className="fixed inset-0 z-50 bg-slate-900/60 dark:bg-black/80 backdrop-blur-xs flex items-center justify-center p-4">
                    <div className="bg-white dark:bg-[#0F172A] rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-200/80 dark:border-slate-800 text-slate-900 dark:text-white">
                        <h3 className="text-lg font-serif font-bold text-rose-900 dark:text-rose-400">
                            Reject Commission Payout
                        </h3>
                        <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
                            Reserved balance will be refunded to the agent immediately.
                        </p>
                        <form onSubmit={handleReject} className="mt-4 space-y-4">
                            <div>
                                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase mb-1">
                                    Mandatory Reason *
                                </label>
                                <textarea
                                    value={rejectData.rejection_reason}
                                    onChange={(e) => setRejectData('rejection_reason', e.target.value)}
                                    rows={3}
                                    required
                                    minLength={5}
                                    maxLength={500}
                                    className="w-full px-3 py-2 border border-rose-300 dark:border-rose-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white rounded-xl text-xs"
                                    placeholder="Explain reason for rejection..."
                                />
                                {rejectErrors.rejection_reason && (
                                    <p className="text-rose-600 dark:text-rose-400 text-xs mt-1">{rejectErrors.rejection_reason}</p>
                                )}
                            </div>
                            <div className="pt-2 flex justify-end gap-3">
                                <button
                                    type="button"
                                    onClick={() => setIsRejectOpen(false)}
                                    className="px-4 py-2 text-xs font-semibold text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={rejectProcessing}
                                    className="px-5 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded-xl text-xs font-bold cursor-pointer transition-colors"
                                >
                                    {rejectProcessing ? 'Rejecting...' : 'Confirm Rejection'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Record Payment Modal */}
            {isPayOpen && (
                <div className="fixed inset-0 z-50 bg-slate-900/60 dark:bg-black/80 backdrop-blur-xs flex items-center justify-center p-4">
                    <div className="bg-white dark:bg-[#0F172A] rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-200/80 dark:border-slate-800 text-slate-900 dark:text-white">
                        <h3 className="text-lg font-serif font-bold text-slate-900 dark:text-white">
                            Record Bank Transfer Remittance
                        </h3>
                        <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
                            Complete payout #{payout.reference_number} and atomically finalize ledger deduction.
                        </p>
                        <form onSubmit={handleRecordPayment} className="mt-4 space-y-4">
                            <div>
                                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase mb-1">
                                    Bank Transfer Reference # *
                                </label>
                                <input
                                    type="text"
                                    value={payData.bank_transfer_reference}
                                    onChange={(e) => setPayData('bank_transfer_reference', e.target.value)}
                                    required
                                    className="w-full px-3 py-2 border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white rounded-xl text-xs font-mono"
                                    placeholder="e.g. SLIPS-992384 or FT-29384"
                                />
                                {payErrors.bank_transfer_reference && (
                                    <p className="text-rose-600 dark:text-rose-400 text-xs mt-1">{payErrors.bank_transfer_reference}</p>
                                )}
                            </div>
                            <div>
                                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase mb-1">
                                    Receipt Upload (Optional PDF or Image)
                                </label>
                                <input
                                    type="file"
                                    accept="image/*,.pdf"
                                    onChange={(e) => setPayData('receipt', e.target.files ? e.target.files[0] : null)}
                                    className="w-full text-xs text-slate-600 dark:text-slate-400 file:mr-3 file:py-1.5 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-slate-100 dark:file:bg-slate-800 file:text-slate-700 dark:file:text-slate-300 hover:file:bg-slate-200 dark:hover:file:bg-slate-700 cursor-pointer"
                                />
                            </div>
                            <div>
                                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase mb-1">
                                    Notes (Optional)
                                </label>
                                <textarea
                                    value={payData.bank_transfer_notes}
                                    onChange={(e) => setPayData('bank_transfer_notes', e.target.value)}
                                    rows={2}
                                    className="w-full px-3 py-2 border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white rounded-xl text-xs"
                                    placeholder="Notes..."
                                />
                            </div>
                            <div className="pt-2 flex justify-end gap-3">
                                <button
                                    type="button"
                                    onClick={() => setIsPayOpen(false)}
                                    className="px-4 py-2 text-xs font-semibold text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={payProcessing}
                                    className="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold cursor-pointer transition-colors"
                                >
                                    {payProcessing ? 'Recording...' : 'Mark Transfer Paid'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Mark Failed Modal (OPS-06-01) */}
            {isFailOpen && (
                <div className="fixed inset-0 z-50 bg-slate-900/60 dark:bg-black/80 backdrop-blur-xs flex items-center justify-center p-4">
                    <div className="bg-white dark:bg-[#0F172A] rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-200/80 dark:border-slate-800 text-slate-900 dark:text-white">
                        <h3 className="text-lg font-serif font-bold text-amber-900 dark:text-amber-400">
                            Record Bank Transfer Failure
                        </h3>
                        <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
                            Marks payout #{payout.reference_number} as failed and releases reserved balance back to the agent.
                        </p>
                        <form onSubmit={handleFail} className="mt-4 space-y-4">
                            <div>
                                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase mb-1">
                                    Failure Reason / Bank Return Code *
                                </label>
                                <textarea
                                    value={failData.failure_reason}
                                    onChange={(e) => setFailData('failure_reason', e.target.value)}
                                    rows={3}
                                    required
                                    minLength={5}
                                    maxLength={500}
                                    className="w-full px-3 py-2 border border-amber-300 dark:border-amber-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white rounded-xl text-xs"
                                    placeholder="Explain why the bank remittance could not clear..."
                                />
                                {failErrors.failure_reason && (
                                    <p className="text-rose-600 dark:text-rose-400 text-xs mt-1">{failErrors.failure_reason}</p>
                                )}
                            </div>
                            <div className="pt-2 flex justify-end gap-3">
                                <button
                                    type="button"
                                    onClick={() => setIsFailOpen(false)}
                                    className="px-4 py-2 text-xs font-semibold text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={failProcessing}
                                    className="px-5 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-xl text-xs font-bold cursor-pointer transition-colors"
                                >
                                    {failProcessing ? 'Failing...' : 'Confirm Bank Failure'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AdminLayout>
    );
}

