import React, { useState } from 'react';
import { Head, Link, useForm, router } from '@inertiajs/react';
import AgentLayout from '@/Layouts/AgentLayout';
import { PageProps, PaginatedData, PayoutRequest, WalletTransaction } from '@/types';
import {
    CreditCard,
    Clock,
    CheckCircle2,
    XCircle,
    TrendingUp,
    ShieldAlert,
    ShieldCheck,
    AlertCircle,
    Building2,
    ChevronLeft,
    ChevronRight,
} from '@/Components/Icons';

interface SummaryProps {
    wallet_balance: number;
    reserved_balance: number;
    available_balance: number;
    total_earned: number;
    total_withdrawn: number;
    pending_commissions: number;
    reversed_commissions: number;
    kyc_status: string;
    has_bank_details: boolean;
    bank_name: string | null;
    bank_branch: string | null;
    bank_account_number: string | null;
    bank_account_name: string | null;
}

interface IndexProps extends PageProps {
    summary: SummaryProps;
    payoutRequests: PaginatedData<PayoutRequest>;
    transactions: PaginatedData<WalletTransaction>;
    filters: {
        type?: string;
        from_date?: string;
        to_date?: string;
    };
}

export default function Index({ summary, payoutRequests, transactions, filters }: IndexProps) {
    const [isPayoutModalOpen, setIsPayoutModalOpen] = useState(false);
    const [selectedType, setSelectedType] = useState(filters.type || '');
    const [fromDate, setFromDate] = useState(filters.from_date || '');
    const [toDate, setToDate] = useState(filters.to_date || '');

    const { data, setData, post, processing, errors, reset } = useForm({
        amount: '',
        agent_notes: '',
    });

    const handleFilterSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(
            '/agent/wallet',
            {
                type: selectedType || undefined,
                from_date: fromDate || undefined,
                to_date: toDate || undefined,
            },
            { preserveState: true }
        );
    };

    const handleClearFilters = () => {
        setSelectedType('');
        setFromDate('');
        setToDate('');
        router.get('/agent/wallet', {}, { preserveState: true });
    };

    const handlePayoutSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/agent/wallet/payout-request', {
            onSuccess: () => {
                setIsPayoutModalOpen(false);
                reset();
            },
        });
    };

    const handleCancelPayout = (payoutId: number) => {
        if (confirm('Are you sure you want to cancel this payout request? Reserved funds will immediately return to your available balance.')) {
            router.post(`/agent/wallet/payout-requests/${payoutId}/cancel`);
        }
    };

    const getPayoutStatusBadge = (status: string) => {
        switch (status) {
            case 'pending':
                return (
                    <span className="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800">
                        <Clock className="w-3 h-3 mr-1" /> Pending Owner Review
                    </span>
                );
            case 'approved':
                return (
                    <span className="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                        <CheckCircle2 className="w-3 h-3 mr-1" /> Approved
                    </span>
                );
            case 'processing':
                return (
                    <span className="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">
                        <Clock className="w-3 h-3 mr-1" /> Processing Transfer
                    </span>
                );
            case 'paid':
                return (
                    <span className="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">
                        <CheckCircle2 className="w-3 h-3 mr-1" /> Paid
                    </span>
                );
            case 'rejected':
                return (
                    <span className="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-rose-100 text-rose-800">
                        <XCircle className="w-3 h-3 mr-1" /> Rejected
                    </span>
                );
            case 'cancelled':
                return (
                    <span className="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-stone-100 text-stone-700">
                        Cancelled
                    </span>
                );
            default:
                return (
                    <span className="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-stone-100 text-stone-800">
                        {status}
                    </span>
                );
        }
    };

    const getTxTypeBadge = (type: string) => {
        switch (type) {
            case 'commission_credit':
                return (
                    <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-emerald-100 text-emerald-800">
                        + Commission Credit
                    </span>
                );
            case 'commission_reversal':
                return (
                    <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-rose-100 text-rose-800">
                        - Commission Reversal
                    </span>
                );
            case 'payout_reserved':
                return (
                    <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-amber-100 text-amber-800">
                        Payout Reserved
                    </span>
                );
            case 'payout_released':
                return (
                    <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-blue-100 text-blue-800">
                        Payout Released
                    </span>
                );
            case 'payout_completed':
                return (
                    <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-purple-100 text-purple-800">
                        - Payout Transferred
                    </span>
                );
            default:
                return (
                    <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-stone-100 text-stone-800">
                        {type}
                    </span>
                );
        }
    };

    const canRequestPayout = summary.kyc_status === 'verified' && summary.has_bank_details && summary.available_balance > 0;

    return (
        <AgentLayout>
            <Head title="Agent Wallet & Commission Payouts" />

            <div className="space-y-8">
                {/* Header & Quick Action */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-serif font-bold text-stone-900 tracking-tight">Agent Wallet & Payouts</h1>
                        <p className="text-sm text-stone-500 mt-1">
                            Review your available earnings balance, request bank payouts, and inspect all ledger transactions.
                        </p>
                    </div>

                    <div className="flex items-center gap-3">
                        <Link
                            href="/agent/wallet/statements"
                            className="inline-flex items-center px-4 py-2 border border-stone-300 rounded-lg text-sm font-medium text-stone-700 bg-white hover:bg-stone-50 shadow-xs"
                        >
                            Monthly Statements
                        </Link>

                        <button
                            type="button"
                            onClick={() => setIsPayoutModalOpen(true)}
                            disabled={!canRequestPayout}
                            className={`inline-flex items-center px-4 py-2 rounded-lg text-sm font-semibold shadow-xs transition-colors ${
                                canRequestPayout
                                    ? 'bg-rose-700 hover:bg-rose-800 text-white cursor-pointer'
                                    : 'bg-stone-300 text-stone-500 cursor-not-allowed'
                            }`}
                        >
                            + Request Payout
                        </button>
                    </div>
                </div>

                {/* KYC / Bank Details Warnings if not eligible */}
                {summary.kyc_status !== 'verified' && (
                    <div className="bg-amber-50 border border-amber-200 rounded-xl p-4 flex items-start gap-3">
                        <ShieldAlert className="w-5 h-5 text-amber-600 shrink-0 mt-0.5" />
                        <div className="text-sm text-amber-800">
                            <strong>KYC Verification Required:</strong> Your account KYC status is currently{' '}
                            <span className="uppercase font-semibold">{summary.kyc_status}</span>. Payout requests are restricted until your KYC documentation is reviewed and verified by management.{' '}
                            <Link href="/agent/profile" className="font-semibold underline hover:text-amber-950">
                                Check KYC Profile &rarr;
                            </Link>
                        </div>
                    </div>
                )}

                {summary.kyc_status === 'verified' && !summary.has_bank_details && (
                    <div className="bg-amber-50 border border-amber-200 rounded-xl p-4 flex items-start gap-3">
                        <Building2 className="w-5 h-5 text-amber-600 shrink-0 mt-0.5" />
                        <div className="text-sm text-amber-800">
                            <strong>Bank Account Information Required:</strong> You must configure your bank name, branch, account number, and account holder name before submitting payout requests.{' '}
                            <Link href="/agent/profile" className="font-semibold underline hover:text-amber-950">
                                Update Bank Information &rarr;
                            </Link>
                        </div>
                    </div>
                )}

                {/* Financial Summary Cards */}
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
                    {/* Available for Payout (Primary) */}
                    <div className="bg-emerald-900 text-white rounded-2xl p-6 shadow-md border border-emerald-800 flex flex-col justify-between">
                        <div>
                            <span className="text-xs font-semibold text-emerald-200 uppercase tracking-wider">Available For Payout</span>
                            <p className="text-3xl font-extrabold font-mono mt-2 text-white">
                                LKR {summary.available_balance.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                            </p>
                        </div>
                        <div className="mt-4 pt-3 border-t border-emerald-800/80 text-xs text-emerald-200 flex justify-between">
                            <span>Gross Wallet:</span>
                            <span className="font-mono">LKR {summary.wallet_balance.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                        </div>
                    </div>

                    {/* Reserved in Payouts */}
                    <div className="bg-white rounded-2xl border border-stone-200 p-6 shadow-xs flex flex-col justify-between">
                        <div>
                            <span className="text-xs font-semibold text-stone-500 uppercase tracking-wider">Reserved In Payouts</span>
                            <p className="text-2xl font-bold font-mono text-amber-700 mt-2">
                                LKR {summary.reserved_balance.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                            </p>
                        </div>
                        <p className="mt-4 pt-3 border-t border-stone-100 text-xs text-stone-500">
                            Locked for pending / approved requests
                        </p>
                    </div>

                    {/* Pending Maturation */}
                    <div className="bg-white rounded-2xl border border-stone-200 p-6 shadow-xs flex flex-col justify-between">
                        <div>
                            <span className="text-xs font-semibold text-stone-500 uppercase tracking-wider">Pending Maturation</span>
                            <p className="text-2xl font-bold font-mono text-stone-700 mt-2">
                                LKR {summary.pending_commissions.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                            </p>
                        </div>
                        <p className="mt-4 pt-3 border-t border-stone-100 text-xs text-stone-500">
                            Awaiting delivery confirmation
                        </p>
                    </div>

                    {/* Total Lifetime Withdrawn */}
                    <div className="bg-white rounded-2xl border border-stone-200 p-6 shadow-xs flex flex-col justify-between">
                        <div>
                            <span className="text-xs font-semibold text-stone-500 uppercase tracking-wider">Total Lifetime Paid Out</span>
                            <p className="text-2xl font-bold font-mono text-purple-900 mt-2">
                                LKR {summary.total_withdrawn.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                            </p>
                        </div>
                        <div className="mt-4 pt-3 border-t border-stone-100 text-xs text-stone-500 flex justify-between">
                            <span>Total Earned:</span>
                            <span className="font-mono font-semibold text-stone-800">
                                LKR {summary.total_earned.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                            </span>
                        </div>
                    </div>
                </div>

                {/* Bank Account Snapshot Card */}
                {summary.has_bank_details && (
                    <div className="bg-stone-50 border border-stone-200 rounded-xl p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div className="flex items-center gap-3">
                            <div className="p-2.5 bg-stone-200 text-stone-700 rounded-lg">
                                <Building2 className="w-5 h-5" />
                            </div>
                            <div>
                                <span className="text-xs font-semibold text-stone-500 uppercase">Configured Transfer Destination</span>
                                <p className="text-sm font-semibold text-stone-900">
                                    {summary.bank_name} {summary.bank_branch ? `(${summary.bank_branch})` : ''} — Account: {summary.bank_account_number}
                                </p>
                                <p className="text-xs text-stone-600">Account Holder: {summary.bank_account_name}</p>
                            </div>
                        </div>
                        <Link href="/agent/profile" className="text-xs text-rose-700 font-semibold hover:underline shrink-0">
                            Edit Destination Details &rarr;
                        </Link>
                    </div>
                )}

                {/* Section: Recent Payout Requests */}
                <div className="bg-white rounded-2xl border border-stone-200 shadow-xs overflow-hidden">
                    <div className="px-6 py-4 border-b border-stone-200 flex items-center justify-between">
                        <div>
                            <h2 className="text-base font-bold text-stone-900">Payout Requests</h2>
                            <p className="text-xs text-stone-500">Track status and bank references for your withdrawal requests</p>
                        </div>
                        <span className="text-xs text-stone-500">Total: {payoutRequests.total}</span>
                    </div>

                    {payoutRequests.data.length === 0 ? (
                        <div className="p-12 text-center text-stone-400">
                            <CreditCard className="w-12 h-12 mx-auto mb-3 text-stone-300" />
                            <p className="text-sm font-medium text-stone-600">No payout requests submitted yet</p>
                            <p className="text-xs mt-1">Once you have available earnings, you can submit a withdrawal request anytime.</p>
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-stone-200 text-left text-xs">
                                <thead className="bg-stone-50 text-stone-500 uppercase font-semibold">
                                    <tr>
                                        <th className="px-6 py-3">Reference</th>
                                        <th className="px-6 py-3">Date</th>
                                        <th className="px-6 py-3">Amount</th>
                                        <th className="px-6 py-3">Destination</th>
                                        <th className="px-6 py-3">Status</th>
                                        <th className="px-6 py-3">Transfer Details / Notes</th>
                                        <th className="px-6 py-3 text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-stone-100 font-sans">
                                    {payoutRequests.data.map((payout) => (
                                        <tr key={payout.id} className="hover:bg-stone-50/60 transition-colors">
                                            <td className="px-6 py-3.5 font-mono font-semibold text-stone-900 whitespace-nowrap">
                                                {payout.reference_number}
                                            </td>
                                            <td className="px-6 py-3.5 text-stone-600 whitespace-nowrap">
                                                {new Date(payout.created_at).toLocaleDateString()}
                                            </td>
                                            <td className="px-6 py-3.5 font-mono font-bold text-stone-900 whitespace-nowrap">
                                                LKR {Number(payout.amount).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                            </td>
                                            <td className="px-6 py-3.5 text-stone-600 whitespace-nowrap">
                                                <div className="font-medium text-stone-900">{payout.bank_name}</div>
                                                <div className="text-[11px] text-stone-500">{payout.bank_account_number} ({payout.bank_account_name})</div>
                                            </td>
                                            <td className="px-6 py-3.5 whitespace-nowrap">
                                                {getPayoutStatusBadge(payout.status)}
                                            </td>
                                            <td className="px-6 py-3.5 text-stone-600 max-w-xs">
                                                {payout.bank_transfer_reference && (
                                                    <div className="font-mono text-emerald-800 text-[11px] font-semibold">
                                                        Ref: {payout.bank_transfer_reference}
                                                    </div>
                                                )}
                                                {payout.bank_transfer_receipt_path && (
                                                    <a
                                                        href={`/agent/wallet/payout-requests/${payout.id}/receipt`}
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        className="text-[11px] text-emerald-700 font-semibold hover:underline block mt-0.5"
                                                    >
                                                        Download Receipt 📄
                                                    </a>
                                                )}
                                                {payout.rejection_reason && (
                                                    <div className="text-rose-700 text-[11px]">
                                                        Reason: {payout.rejection_reason}
                                                    </div>
                                                )}
                                                {payout.agent_notes && (
                                                    <div className="text-stone-500 text-[11px] italic">
                                                        "{payout.agent_notes}"
                                                    </div>
                                                )}
                                            </td>
                                            <td className="px-6 py-3.5 text-right whitespace-nowrap">
                                                {payout.status === 'pending' && (
                                                    <button
                                                        type="button"
                                                        onClick={() => handleCancelPayout(payout.id)}
                                                        className="text-xs text-rose-600 hover:text-rose-800 font-semibold cursor-pointer"
                                                    >
                                                        Cancel Request
                                                    </button>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>

                {/* Section: Wallet Transactions Journal */}
                <div className="bg-white rounded-2xl border border-stone-200 shadow-xs overflow-hidden">
                    <div className="px-6 py-4 border-b border-stone-200 flex flex-col md:flex-row md:items-center justify-between gap-4">
                        <div>
                            <h2 className="text-base font-bold text-stone-900">Wallet Transactions History</h2>
                            <p className="text-xs text-stone-500">Every credit, reversal, payout reservation, and bank transfer</p>
                        </div>

                        {/* Filters */}
                        <form onSubmit={handleFilterSubmit} className="flex flex-wrap items-center gap-2 text-xs">
                            <select
                                value={selectedType}
                                onChange={(e) => setSelectedType(e.target.value)}
                                className="px-3 py-1.5 border border-stone-300 rounded-lg text-xs"
                            >
                                <option value="">All Transaction Types</option>
                                <option value="commission_credit">Commission Credits</option>
                                <option value="commission_reversal">Commission Reversals</option>
                                <option value="payout_reserved">Payout Reservations</option>
                                <option value="payout_released">Payout Releases</option>
                                <option value="payout_completed">Payouts Transferred</option>
                            </select>

                            <input
                                type="date"
                                value={fromDate}
                                onChange={(e) => setFromDate(e.target.value)}
                                className="px-2.5 py-1.5 border border-stone-300 rounded-lg text-xs"
                                placeholder="From Date"
                            />
                            <input
                                type="date"
                                value={toDate}
                                onChange={(e) => setToDate(e.target.value)}
                                className="px-2.5 py-1.5 border border-stone-300 rounded-lg text-xs"
                                placeholder="To Date"
                            />

                            <button
                                type="submit"
                                className="px-3 py-1.5 bg-stone-800 text-white font-medium rounded-lg hover:bg-stone-900 cursor-pointer"
                            >
                                Filter
                            </button>

                            {(selectedType || fromDate || toDate) && (
                                <button
                                    type="button"
                                    onClick={handleClearFilters}
                                    className="px-2.5 py-1.5 text-stone-600 hover:text-stone-900 cursor-pointer underline"
                                >
                                    Reset
                                </button>
                            )}
                        </form>
                    </div>

                    {transactions.data.length === 0 ? (
                        <div className="p-12 text-center text-stone-400">
                            <p className="text-sm font-medium text-stone-600">No wallet transactions found</p>
                            <p className="text-xs mt-1">Transactions will appear as commissions mature and payouts are requested.</p>
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-stone-200 text-left text-xs">
                                <thead className="bg-stone-50 text-stone-500 uppercase font-semibold">
                                    <tr>
                                        <th className="px-6 py-3">Tx Number</th>
                                        <th className="px-6 py-3">Date</th>
                                        <th className="px-6 py-3">Type</th>
                                        <th className="px-6 py-3">Description</th>
                                        <th className="px-6 py-3 text-right">Amount</th>
                                        <th className="px-6 py-3 text-right">Balance After</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-stone-100 font-sans">
                                    {transactions.data.map((tx) => {
                                        const amountNum = Number(tx.amount);
                                        const isPositive = amountNum > 0;
                                        return (
                                            <tr key={tx.id} className="hover:bg-stone-50/60 transition-colors">
                                                <td className="px-6 py-3.5 font-mono font-medium text-stone-900 whitespace-nowrap">
                                                    {tx.transaction_number}
                                                </td>
                                                <td className="px-6 py-3.5 text-stone-600 whitespace-nowrap">
                                                    {new Date(tx.created_at).toLocaleString()}
                                                </td>
                                                <td className="px-6 py-3.5 whitespace-nowrap">
                                                    {getTxTypeBadge(tx.transaction_type)}
                                                </td>
                                                <td className="px-6 py-3.5 text-stone-800">
                                                    {tx.description}
                                                </td>
                                                <td className={`px-6 py-3.5 font-mono font-bold text-right whitespace-nowrap ${
                                                    isPositive ? 'text-emerald-700' : 'text-stone-700'
                                                }`}>
                                                    {isPositive ? '+' : ''}LKR {amountNum.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                                </td>
                                                <td className="px-6 py-3.5 font-mono text-stone-900 font-semibold text-right whitespace-nowrap">
                                                    LKR {Number(tx.balance_after).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            </div>

            {/* Payout Request Modal */}
            {isPayoutModalOpen && (
                <div className="fixed inset-0 z-50 overflow-y-auto bg-stone-900/60 backdrop-blur-xs flex items-center justify-center p-4">
                    <div className="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-stone-200">
                        <div className="flex items-center justify-between pb-4 border-b border-stone-100">
                            <div>
                                <h3 className="text-lg font-serif font-bold text-stone-900">Request Commission Payout</h3>
                                <p className="text-xs text-stone-500">Funds will be transferred to your registered bank account</p>
                            </div>
                            <button
                                type="button"
                                onClick={() => setIsPayoutModalOpen(false)}
                                className="text-stone-400 hover:text-stone-600 text-lg font-bold"
                            >
                                &times;
                            </button>
                        </div>

                        <form onSubmit={handlePayoutSubmit} className="mt-5 space-y-4">
                            {/* Destination Bank Snapshot Notice */}
                            <div className="p-3 bg-stone-50 rounded-lg border border-stone-200 text-xs">
                                <span className="text-stone-500 font-semibold uppercase">Destination Bank Snapshot:</span>
                                <div className="font-semibold text-stone-900 mt-1">{summary.bank_name} ({summary.bank_branch})</div>
                                <div className="text-stone-600">Account: {summary.bank_account_number}</div>
                                <div className="text-stone-600">Name: {summary.bank_account_name}</div>
                            </div>

                            {/* Max Available Balance */}
                            <div className="flex justify-between text-xs">
                                <span className="text-stone-600">Available Balance:</span>
                                <span className="font-mono font-bold text-emerald-800">
                                    LKR {summary.available_balance.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                </span>
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-stone-700 uppercase mb-1">
                                    Payout Amount (LKR)
                                </label>
                                <div className="relative">
                                    <span className="absolute left-3 top-2.5 text-stone-400 font-mono text-sm">LKR</span>
                                    <input
                                        type="number"
                                        step="0.01"
                                        min="1"
                                        max={summary.available_balance}
                                        value={data.amount}
                                        onChange={(e) => setData('amount', e.target.value)}
                                        placeholder="0.00"
                                        required
                                        className="w-full pl-12 pr-4 py-2 border border-stone-300 rounded-lg font-mono text-stone-900 focus:ring-2 focus:ring-rose-500 focus:border-rose-500 text-sm"
                                    />
                                </div>
                                {errors.amount && (
                                    <p className="text-rose-600 text-xs mt-1">{errors.amount}</p>
                                )}
                                <div className="flex justify-between items-center mt-1.5">
                                    <span className="text-[11px] text-stone-400">Min: LKR 1.00</span>
                                    <button
                                        type="button"
                                        onClick={() => setData('amount', summary.available_balance.toString())}
                                        className="text-[11px] font-semibold text-rose-700 hover:underline cursor-pointer"
                                    >
                                        Use Max Available
                                    </button>
                                </div>
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-stone-700 uppercase mb-1">
                                    Agent Notes (Optional)
                                </label>
                                <textarea
                                    value={data.agent_notes}
                                    onChange={(e) => setData('agent_notes', e.target.value)}
                                    rows={2}
                                    maxLength={500}
                                    placeholder="Add any specific payment reference or transfer instructions..."
                                    className="w-full px-3 py-2 border border-stone-300 rounded-lg text-xs focus:ring-2 focus:ring-rose-500 focus:border-rose-500"
                                />
                                {errors.agent_notes && (
                                    <p className="text-rose-600 text-xs mt-1">{errors.agent_notes}</p>
                                )}
                            </div>

                            <div className="pt-3 flex items-center justify-end gap-3 border-t border-stone-100">
                                <button
                                    type="button"
                                    onClick={() => setIsPayoutModalOpen(false)}
                                    className="px-4 py-2 text-xs font-semibold text-stone-600 hover:text-stone-800"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="px-5 py-2 bg-rose-700 hover:bg-rose-800 text-white rounded-lg text-xs font-bold shadow-xs disabled:opacity-50 cursor-pointer"
                                >
                                    {processing ? 'Submitting...' : 'Confirm Payout Request'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AgentLayout>
    );
}

