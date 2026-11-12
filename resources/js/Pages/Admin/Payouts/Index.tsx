import React, { useState } from 'react';
import { Head, Link, useForm, router, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { PageProps, PaginatedData, PayoutRequest } from '@/types';
import {
    CreditCard,
    Clock,
    CheckCircle2,
    XCircle,
    Building2,
    Search,
    ShieldAlert,
} from '@/Components/Icons';

interface Counts {
    pending: number;
    approved: number;
    processing: number;
    paid: number;
    rejected: number;
}

interface IndexProps extends PageProps {
    payouts: PaginatedData<PayoutRequest>;
    filters: {
        status?: string;
        search?: string;
        from_date?: string;
        to_date?: string;
    };
    counts: Counts;
}

export default function Index({ payouts, filters, counts }: IndexProps) {
    const { auth } = usePage<PageProps>().props;
    const isOwner = auth.user.role === 'owner';
    const isAccountantOrOwner = auth.user.role === 'owner' || auth.user.role === 'accountant';

    const [statusFilter, setStatusFilter] = useState(filters.status || '');
    const [searchQuery, setSearchQuery] = useState(filters.search || '');
    const [fromDate, setFromDate] = useState(filters.from_date || '');
    const [toDate, setToDate] = useState(filters.to_date || '');

    // Approval Modal State
    const [approveModalPayout, setApproveModalPayout] = useState<PayoutRequest | null>(null);
    const { data: approveData, setData: setApproveData, post: postApprove, processing: approveProcessing, reset: resetApprove } = useForm({
        review_notes: '',
    });

    // Rejection Modal State
    const [rejectModalPayout, setRejectModalPayout] = useState<PayoutRequest | null>(null);
    const { data: rejectData, setData: setRejectData, post: postReject, processing: rejectProcessing, errors: rejectErrors, reset: resetReject } = useForm({
        rejection_reason: '',
    });

    // Record Payment Modal State
    const [payModalPayout, setPayModalPayout] = useState<PayoutRequest | null>(null);
    const { data: payData, setData: setPayData, post: postPay, processing: payProcessing, errors: payErrors, reset: resetPay } = useForm<{
        bank_transfer_reference: string;
        bank_transfer_notes: string;
        receipt: File | null;
    }>({
        bank_transfer_reference: '',
        bank_transfer_notes: '',
        receipt: null,
    });

    const handleFilterSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(
            '/admin/payouts',
            {
                status: statusFilter || undefined,
                search: searchQuery || undefined,
                from_date: fromDate || undefined,
                to_date: toDate || undefined,
            },
            { preserveState: true }
        );
    };

    const handleClearFilters = () => {
        setStatusFilter('');
        setSearchQuery('');
        setFromDate('');
        setToDate('');
        router.get('/admin/payouts', {}, { preserveState: true });
    };

    const handleApproveSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!approveModalPayout) return;

        postApprove(`/admin/payouts/${approveModalPayout.id}/approve`, {
            onSuccess: () => {
                setApproveModalPayout(null);
                resetApprove();
            },
        });
    };

    const handleRejectSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!rejectModalPayout) return;

        postReject(`/admin/payouts/${rejectModalPayout.id}/reject`, {
            onSuccess: () => {
                setRejectModalPayout(null);
                resetReject();
            },
        });
    };

    const handleMarkProcessing = (payoutId: number) => {
        if (confirm('Mark this payout as actively processing with the bank?')) {
            router.post(`/admin/payouts/${payoutId}/process`);
        }
    };

    const handlePaySubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!payModalPayout) return;

        postPay(`/admin/payouts/${payModalPayout.id}/record-payment`, {
            forceFormData: true,
            onSuccess: () => {
                setPayModalPayout(null);
                resetPay();
            },
        });
    };

    const getStatusBadge = (status: string) => {
        switch (status) {
            case 'pending':
                return (
                    <span className="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-amber-100 dark:bg-amber-950/60 text-amber-800 dark:text-amber-300 border border-amber-200 dark:border-amber-900/60">
                        Pending Owner Approval
                    </span>
                );
            case 'approved':
                return (
                    <span className="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-blue-100 dark:bg-blue-950/60 text-blue-800 dark:text-blue-300 border border-blue-200 dark:border-blue-900/60">
                        Approved (Ready For Remittance)
                    </span>
                );
            case 'processing':
                return (
                    <span className="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-indigo-100 dark:bg-indigo-950/60 text-indigo-800 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-900/60">
                        Processing With Bank
                    </span>
                );
            case 'paid':
                return (
                    <span className="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 dark:bg-emerald-950/60 text-emerald-800 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-900/60">
                        Settled & Paid
                    </span>
                );
            case 'rejected':
                return (
                    <span className="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-rose-100 dark:bg-rose-950/60 text-rose-800 dark:text-rose-300 border border-rose-200 dark:border-rose-900/60">
                        Rejected
                    </span>
                );
            case 'cancelled':
                return (
                    <span className="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700">
                        Cancelled by Agent
                    </span>
                );
            default:
                return (
                    <span className="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-slate-100 dark:bg-slate-800 text-slate-800 dark:text-slate-200 border border-slate-200 dark:border-slate-700">
                        {status}
                    </span>
                );
        }
    };

    return (
        <AdminLayout>
            <Head title="Payout Requests Queue & Clearing" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white dark:bg-[#0F172A] p-6 rounded-2xl border border-slate-200/80 dark:border-slate-800/80 shadow-xs transition-colors">
                    <div>
                        <h1 className="text-xl sm:text-2xl font-serif font-bold text-slate-900 dark:text-white tracking-tight">
                            Commission Payout Requests Queue
                        </h1>
                        <p className="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1">
                            Review, authorize, and record bank transfers for verified agent commission withdrawals.
                        </p>
                    </div>

                    <div className="flex items-center gap-3">
                        <Link
                            href="/admin/payouts/dashboard"
                            className="inline-flex items-center px-4 py-2 border border-slate-300 dark:border-slate-700 rounded-xl text-xs font-semibold text-slate-700 dark:text-slate-200 bg-white dark:bg-[#0F172A] hover:bg-slate-50 dark:hover:bg-slate-800 shadow-xs transition-colors"
                        >
                            Executive Financial Dashboard
                        </Link>
                    </div>
                </div>

                {/* Status Filter Tabs */}
                <div className="flex flex-wrap gap-2 text-xs font-semibold">
                    <button
                        type="button"
                        onClick={() => {
                            setStatusFilter('');
                            router.get('/admin/payouts', { search: searchQuery || undefined });
                        }}
                        className={`px-3.5 py-2 rounded-xl border transition-colors cursor-pointer ${
                            !statusFilter
                                ? 'bg-slate-900 dark:bg-blue-600 text-white border-slate-900 dark:border-blue-600 shadow-xs'
                                : 'bg-white dark:bg-[#0F172A] text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800'
                        }`}
                    >
                        All ({payouts.total})
                    </button>

                    <button
                        type="button"
                        onClick={() => {
                            setStatusFilter('pending');
                            router.get('/admin/payouts', { status: 'pending', search: searchQuery || undefined });
                        }}
                        className={`px-3.5 py-2 rounded-xl border transition-colors cursor-pointer ${
                            statusFilter === 'pending'
                                ? 'bg-amber-600 text-white border-amber-600 shadow-xs'
                                : 'bg-white dark:bg-[#0F172A] text-amber-800 dark:text-amber-400 border-amber-200 dark:border-amber-900/60 hover:bg-amber-50 dark:hover:bg-amber-950/40'
                        }`}
                    >
                        Pending Review ({counts.pending})
                    </button>

                    <button
                        type="button"
                        onClick={() => {
                            setStatusFilter('approved');
                            router.get('/admin/payouts', { status: 'approved', search: searchQuery || undefined });
                        }}
                        className={`px-3.5 py-2 rounded-xl border transition-colors cursor-pointer ${
                            statusFilter === 'approved'
                                ? 'bg-blue-600 text-white border-blue-600 shadow-xs'
                                : 'bg-white dark:bg-[#0F172A] text-blue-800 dark:text-blue-400 border-blue-200 dark:border-blue-900/60 hover:bg-blue-50 dark:hover:bg-blue-950/40'
                        }`}
                    >
                        Approved ({counts.approved})
                    </button>

                    <button
                        type="button"
                        onClick={() => {
                            setStatusFilter('processing');
                            router.get('/admin/payouts', { status: 'processing', search: searchQuery || undefined });
                        }}
                        className={`px-3.5 py-2 rounded-xl border transition-colors cursor-pointer ${
                            statusFilter === 'processing'
                                ? 'bg-indigo-600 text-white border-indigo-600 shadow-xs'
                                : 'bg-white dark:bg-[#0F172A] text-indigo-800 dark:text-indigo-400 border-indigo-200 dark:border-indigo-900/60 hover:bg-indigo-50 dark:hover:bg-indigo-950/40'
                        }`}
                    >
                        Processing ({counts.processing})
                    </button>

                    <button
                        type="button"
                        onClick={() => {
                            setStatusFilter('paid');
                            router.get('/admin/payouts', { status: 'paid', search: searchQuery || undefined });
                        }}
                        className={`px-3.5 py-2 rounded-xl border transition-colors cursor-pointer ${
                            statusFilter === 'paid'
                                ? 'bg-emerald-600 text-white border-emerald-600 shadow-xs'
                                : 'bg-white dark:bg-[#0F172A] text-emerald-800 dark:text-emerald-400 border-emerald-200 dark:border-emerald-900/60 hover:bg-emerald-50 dark:hover:bg-emerald-950/40'
                        }`}
                    >
                        Paid ({counts.paid})
                    </button>

                    <button
                        type="button"
                        onClick={() => {
                            setStatusFilter('rejected');
                            router.get('/admin/payouts', { status: 'rejected', search: searchQuery || undefined });
                        }}
                        className={`px-3.5 py-2 rounded-xl border transition-colors cursor-pointer ${
                            statusFilter === 'rejected'
                                ? 'bg-rose-600 text-white border-rose-600 shadow-xs'
                                : 'bg-white dark:bg-[#0F172A] text-rose-800 dark:text-rose-400 border-rose-200 dark:border-rose-900/60 hover:bg-rose-50 dark:hover:bg-rose-950/40'
                        }`}
                    >
                        Rejected ({counts.rejected})
                    </button>
                </div>

                {/* Filter and Search Bar */}
                <div className="bg-white dark:bg-[#0F172A] rounded-2xl border border-slate-200/80 dark:border-slate-800/80 p-5 shadow-xs transition-colors">
                    <form onSubmit={handleFilterSubmit} className="flex flex-wrap items-center gap-3 text-xs">
                        <div className="flex-1 min-w-[240px] relative">
                            <input
                                type="text"
                                value={searchQuery}
                                onChange={(e) => setSearchQuery(e.target.value)}
                                placeholder="Search reference #, agent name, code, bank transfer ref..."
                                className="w-full pl-3 pr-3 py-2 border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white rounded-xl text-xs placeholder:text-slate-400 dark:placeholder:text-slate-500 focus:ring-blue-500 focus:border-blue-500"
                            />
                        </div>

                        <input
                            type="date"
                            value={fromDate}
                            onChange={(e) => setFromDate(e.target.value)}
                            className="px-3 py-2 border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white rounded-xl text-xs focus:ring-blue-500 focus:border-blue-500"
                            placeholder="From Date"
                        />
                        <input
                            type="date"
                            value={toDate}
                            onChange={(e) => setToDate(e.target.value)}
                            className="px-3 py-2 border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white rounded-xl text-xs focus:ring-blue-500 focus:border-blue-500"
                            placeholder="To Date"
                        />

                        <button
                            type="submit"
                            className="px-5 py-2 bg-slate-900 dark:bg-blue-600 hover:bg-slate-800 dark:hover:bg-blue-500 text-white font-semibold rounded-xl cursor-pointer transition-colors shadow-xs"
                        >
                            Filter
                        </button>

                        {(searchQuery || fromDate || toDate) && (
                            <button
                                type="button"
                                onClick={handleClearFilters}
                                className="px-3 py-2 text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white cursor-pointer underline transition-colors"
                            >
                                Reset
                            </button>
                        )}
                    </form>
                </div>

                {/* Main Queue Table */}
                <div className="bg-white dark:bg-[#0F172A] rounded-2xl border border-slate-200/80 dark:border-slate-800/80 shadow-xs overflow-hidden transition-colors">
                    {payouts.data.length === 0 ? (
                        <div className="p-12 text-center text-slate-400 dark:text-slate-500 text-sm">
                            No payout requests matched the specified filters.
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-slate-100 dark:divide-slate-800/80 text-left text-xs bg-white dark:bg-[#0F172A]">
                                <thead className="bg-slate-50 dark:bg-slate-900/60 text-slate-500 dark:text-slate-400 uppercase font-semibold text-[11px]">
                                    <tr>
                                        <th className="px-6 py-3.5">Reference</th>
                                        <th className="px-6 py-3.5">Agent</th>
                                        <th className="px-6 py-3.5">Amount</th>
                                        <th className="px-6 py-3.5">Destination Account</th>
                                        <th className="px-6 py-3.5">Status</th>
                                        <th className="px-6 py-3.5">Date</th>
                                        <th className="px-6 py-3.5 text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100 dark:divide-slate-800/60 font-sans bg-white dark:bg-[#0F172A]">
                                    {payouts.data.map((payout) => (
                                        <tr key={payout.id} className="bg-white dark:bg-[#0F172A] hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition-colors">
                                            <td className="px-6 py-3.5 whitespace-nowrap">
                                                <Link
                                                    href={`/admin/payouts/${payout.id}`}
                                                    className="font-mono font-bold text-slate-900 dark:text-white hover:text-blue-600 dark:hover:text-blue-400"
                                                >
                                                    {payout.reference_number}
                                                </Link>
                                            </td>

                                            <td className="px-6 py-3.5 whitespace-nowrap">
                                                <div className="font-semibold text-slate-900 dark:text-white">{payout.agent?.name}</div>
                                                <div className="text-[11px] text-slate-500 dark:text-slate-400 font-mono">
                                                    {payout.agent?.referral_code} ({payout.agent?.role === 'high_level_agent' ? 'High' : 'Low'})
                                                </div>
                                            </td>

                                            <td className="px-6 py-3.5 font-mono font-bold text-slate-900 dark:text-white whitespace-nowrap text-sm">
                                                LKR {Number(payout.amount).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                            </td>

                                            <td className="px-6 py-3.5 whitespace-nowrap">
                                                <div className="font-medium text-slate-900 dark:text-white">{payout.bank_name}</div>
                                                <div className="text-[11px] text-slate-500 dark:text-slate-400 font-mono">
                                                    {payout.bank_account_number} ({payout.bank_account_name})
                                                </div>
                                            </td>

                                            <td className="px-6 py-3.5 whitespace-nowrap">
                                                {getStatusBadge(payout.status)}
                                            </td>

                                            <td className="px-6 py-3.5 text-slate-500 dark:text-slate-400 whitespace-nowrap">
                                                {new Date(payout.created_at).toLocaleDateString()}
                                            </td>

                                            <td className="px-6 py-3.5 text-right whitespace-nowrap">
                                                <div className="flex items-center justify-end gap-2">
                                                    <Link
                                                        href={`/admin/payouts/${payout.id}`}
                                                        className="px-2.5 py-1 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white font-medium"
                                                    >
                                                        Details
                                                    </Link>

                                                    {/* Owner-Only Approval / Rejection */}
                                                    {payout.status === 'pending' && isOwner && (
                                                        <>
                                                            <button
                                                                type="button"
                                                                onClick={() => setApproveModalPayout(payout)}
                                                                className="px-3 py-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-semibold cursor-pointer text-xs"
                                                            >
                                                                Approve
                                                            </button>

                                                            <button
                                                                type="button"
                                                                onClick={() => setRejectModalPayout(payout)}
                                                                className="px-3 py-1 bg-rose-600 hover:bg-rose-700 text-white rounded-lg font-semibold cursor-pointer text-xs"
                                                            >
                                                                Reject
                                                            </button>
                                                        </>
                                                    )}

                                                    {/* Process and Record Payment (Owner or Accountant) */}
                                                    {payout.status === 'approved' && isAccountantOrOwner && (
                                                        <button
                                                            type="button"
                                                            onClick={() => handleMarkProcessing(payout.id)}
                                                            className="px-3 py-1 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-semibold cursor-pointer text-xs"
                                                        >
                                                            Process
                                                        </button>
                                                    )}

                                                    {(payout.status === 'approved' || payout.status === 'processing') && isAccountantOrOwner && (
                                                        <button
                                                            type="button"
                                                            onClick={() => setPayModalPayout(payout)}
                                                            className="px-3 py-1 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-semibold cursor-pointer text-xs"
                                                        >
                                                            Record Transfer
                                                        </button>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            </div>

            {/* Approve Modal (Owner Exclusive) */}
            {approveModalPayout && (
                <div className="fixed inset-0 z-50 bg-slate-900/60 dark:bg-black/80 backdrop-blur-xs flex items-center justify-center p-4">
                    <div className="bg-white dark:bg-[#0F172A] rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-200/80 dark:border-slate-800 text-slate-900 dark:text-white">
                        <h3 className="text-lg font-serif font-bold text-slate-900 dark:text-white">
                            Approve Commission Payout
                        </h3>
                        <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
                            Executive Owner Authorization for #{approveModalPayout.reference_number}
                        </p>

                        <div className="my-4 p-3 bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 rounded-xl text-xs space-y-1">
                            <div className="flex justify-between">
                                <span className="text-slate-600 dark:text-slate-400">Agent:</span>
                                <span className="font-semibold text-slate-900 dark:text-white">{approveModalPayout.agent?.name}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-slate-600 dark:text-slate-400">Amount:</span>
                                <span className="font-mono font-bold text-emerald-900 dark:text-emerald-300">
                                    LKR {Number(approveModalPayout.amount).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                </span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-slate-600 dark:text-slate-400">Bank Destination:</span>
                                <span className="text-slate-800 dark:text-slate-200">{approveModalPayout.bank_name} ({approveModalPayout.bank_account_number})</span>
                            </div>
                        </div>

                        <form onSubmit={handleApproveSubmit} className="space-y-4">
                            <div>
                                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase mb-1">
                                    Owner Review Notes (Optional)
                                </label>
                                <textarea
                                    value={approveData.review_notes}
                                    onChange={(e) => setApproveData('review_notes', e.target.value)}
                                    rows={2}
                                    placeholder="Add any authorization notes or conditions..."
                                    className="w-full px-3 py-2 border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white rounded-xl text-xs"
                                />
                            </div>

                            <div className="pt-2 flex justify-end gap-3">
                                <button
                                    type="button"
                                    onClick={() => setApproveModalPayout(null)}
                                    className="px-4 py-2 text-xs font-semibold text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={approveProcessing}
                                    className="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold cursor-pointer"
                                >
                                    {approveProcessing ? 'Authorizing...' : 'Authorize Payout'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Reject Modal (Owner Exclusive) */}
            {rejectModalPayout && (
                <div className="fixed inset-0 z-50 bg-slate-900/60 dark:bg-black/80 backdrop-blur-xs flex items-center justify-center p-4">
                    <div className="bg-white dark:bg-[#0F172A] rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-200/80 dark:border-slate-800 text-slate-900 dark:text-white">
                        <h3 className="text-lg font-serif font-bold text-rose-900 dark:text-rose-400">
                            Reject Commission Payout
                        </h3>
                        <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
                            Reserved funds will be immediately released back to the agent's available balance.
                        </p>

                        <div className="my-4 p-3 bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800 rounded-xl text-xs space-y-1">
                            <div className="flex justify-between">
                                <span className="text-slate-600 dark:text-slate-400">Payout Reference:</span>
                                <span className="font-mono font-bold text-slate-900 dark:text-white">{rejectModalPayout.reference_number}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-slate-600 dark:text-slate-400">Amount:</span>
                                <span className="font-mono font-bold text-rose-900 dark:text-rose-300">
                                    LKR {Number(rejectModalPayout.amount).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                </span>
                            </div>
                        </div>

                        <form onSubmit={handleRejectSubmit} className="space-y-4">
                            <div>
                                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase mb-1">
                                    Mandatory Justification / Reason *
                                </label>
                                <textarea
                                    value={rejectData.rejection_reason}
                                    onChange={(e) => setRejectData('rejection_reason', e.target.value)}
                                    rows={3}
                                    required
                                    minLength={5}
                                    maxLength={500}
                                    placeholder="State the exact reason for rejecting this payout request..."
                                    className="w-full px-3 py-2 border border-rose-300 dark:border-rose-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white rounded-xl text-xs"
                                />
                                {rejectErrors.rejection_reason && (
                                    <p className="text-rose-600 dark:text-rose-400 text-xs mt-1">{rejectErrors.rejection_reason}</p>
                                )}
                            </div>

                            <div className="pt-2 flex justify-end gap-3">
                                <button
                                    type="button"
                                    onClick={() => setRejectModalPayout(null)}
                                    className="px-4 py-2 text-xs font-semibold text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={rejectProcessing}
                                    className="px-5 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded-xl text-xs font-bold cursor-pointer"
                                >
                                    {rejectProcessing ? 'Rejecting...' : 'Confirm Rejection'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Record Payment Modal (Owner or Accountant) */}
            {payModalPayout && (
                <div className="fixed inset-0 z-50 bg-slate-900/60 dark:bg-black/80 backdrop-blur-xs flex items-center justify-center p-4">
                    <div className="bg-white dark:bg-[#0F172A] rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-200/80 dark:border-slate-800 text-slate-900 dark:text-white">
                        <h3 className="text-lg font-serif font-bold text-slate-900 dark:text-white">
                            Record Bank Remittance
                        </h3>
                        <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
                            Complete payout #{payModalPayout.reference_number} and atomically deduct wallet balance.
                        </p>

                        <div className="my-4 p-3 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 rounded-xl text-xs space-y-1">
                            <div className="flex justify-between">
                                <span className="text-slate-600 dark:text-slate-400">Amount to Transfer:</span>
                                <span className="font-mono font-bold text-slate-900 dark:text-white">
                                    LKR {Number(payModalPayout.amount).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                </span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-slate-600 dark:text-slate-400">Target Bank:</span>
                                <span className="font-semibold text-slate-900 dark:text-white">{payModalPayout.bank_name}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-slate-600 dark:text-slate-400">Account #:</span>
                                <span className="font-mono text-slate-800 dark:text-slate-200">{payModalPayout.bank_account_number}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-slate-600 dark:text-slate-400">Account Name:</span>
                                <span className="text-slate-800 dark:text-slate-200">{payModalPayout.bank_account_name}</span>
                            </div>
                        </div>

                        <form onSubmit={handlePaySubmit} className="space-y-4">
                            <div>
                                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase mb-1">
                                    Bank Transfer Reference # *
                                </label>
                                <input
                                    type="text"
                                    value={payData.bank_transfer_reference}
                                    onChange={(e) => setPayData('bank_transfer_reference', e.target.value)}
                                    required
                                    placeholder="e.g. SLIPS-2026-9938472 or FT-88273"
                                    className="w-full px-3 py-2 border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white rounded-xl text-xs font-mono"
                                />
                                {payErrors.bank_transfer_reference && (
                                    <p className="text-rose-600 dark:text-rose-400 text-xs mt-1">{payErrors.bank_transfer_reference}</p>
                                )}
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase mb-1">
                                    Bank Transfer Receipt (Optional PDF or Image)
                                </label>
                                <input
                                    type="file"
                                    accept="image/*,.pdf"
                                    onChange={(e) => setPayData('receipt', e.target.files ? e.target.files[0] : null)}
                                    className="w-full text-xs text-slate-600 dark:text-slate-400 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-slate-100 dark:file:bg-slate-800 file:text-slate-700 dark:file:text-slate-300 hover:file:bg-slate-200 dark:hover:file:bg-slate-700 cursor-pointer"
                                />
                                {payErrors.receipt && (
                                    <p className="text-rose-600 dark:text-rose-400 text-xs mt-1">{payErrors.receipt}</p>
                                )}
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase mb-1">
                                    Accounting Notes (Optional)
                                </label>
                                <textarea
                                    value={payData.bank_transfer_notes}
                                    onChange={(e) => setPayData('bank_transfer_notes', e.target.value)}
                                    rows={2}
                                    placeholder="e.g. Cleared via Commercial Bank online portal"
                                    className="w-full px-3 py-2 border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white rounded-xl text-xs"
                                />
                            </div>

                            <div className="pt-2 flex justify-end gap-3">
                                <button
                                    type="button"
                                    onClick={() => setPayModalPayout(null)}
                                    className="px-4 py-2 text-xs font-semibold text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={payProcessing}
                                    className="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold cursor-pointer"
                                >
                                    {payProcessing ? 'Recording...' : 'Mark Transfer Paid'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AdminLayout>
    );
}

