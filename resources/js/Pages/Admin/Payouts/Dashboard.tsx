import React from 'react';
import { Head, Link } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { PageProps, PayoutRequest } from '@/types';

interface Metrics {
    total_approved_commissions: number;
    total_pending_commissions: number;
    total_wallet_balances: number;
    total_reserved_payouts: number;
    total_completed_payouts: number;
    pending_requests_count: number;
    pending_requests_amount: number;
    processing_requests_count: number;
    processing_requests_amount: number;
    paid_requests_count: number;
    paid_requests_amount: number;
    rejected_requests_count: number;
}

interface DashboardProps extends PageProps {
    metrics: Metrics;
    recentPayouts: PayoutRequest[];
}

export default function Dashboard({ metrics, recentPayouts }: DashboardProps) {
    const netAvailableCompanyWide = Math.max(0, metrics.total_wallet_balances - metrics.total_reserved_payouts);

    return (
        <AdminLayout>
            <Head title="Financial Overview & Commission Payouts" />

            <div className="space-y-8">
                {/* Header */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-serif font-bold text-slate-900 dark:text-white tracking-tight">
                            Commission Payouts & Financial Governance
                        </h1>
                        <p className="text-sm text-slate-500 dark:text-slate-400 mt-1">
                            Real-time executive metrics on agent liabilities, pending payout requests, and bank remittances.
                        </p>
                    </div>

                    <div className="flex items-center gap-3">
                        <Link
                            href="/admin/commissions/ledger"
                            className="inline-flex items-center px-4 py-2 border border-slate-300 dark:border-slate-700 rounded-xl text-xs font-semibold text-slate-700 dark:text-slate-200 bg-white dark:bg-[#1E293B] hover:bg-slate-50 dark:hover:bg-slate-800 shadow-xs transition-colors"
                        >
                            Commission Ledger
                        </Link>
                        <Link
                            href="/admin/payouts"
                            className="inline-flex items-center px-4 py-2 bg-rose-700 dark:bg-blue-600 hover:bg-rose-800 dark:hover:bg-blue-500 text-white rounded-xl text-xs font-semibold shadow-xs transition-colors"
                        >
                            Review Payout Queue ({metrics.pending_requests_count}) &rarr;
                        </Link>
                    </div>
                </div>

                {/* Primary Financial Metric Cards */}
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
                    {/* Total Company Agent Liability */}
                    <div className="bg-slate-900 text-white rounded-2xl p-6 shadow-xs border border-slate-800 flex flex-col justify-between">
                        <div>
                            <span className="text-xs font-semibold text-slate-400 uppercase tracking-wider">
                                Total Agent Wallet Liability
                            </span>
                            <p className="text-3xl font-extrabold font-mono mt-2 text-white">
                                LKR {metrics.total_wallet_balances.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                            </p>
                        </div>
                        <div className="mt-4 pt-3 border-t border-slate-800 text-xs text-slate-400 flex justify-between">
                            <span>Available:</span>
                            <span className="font-mono text-emerald-300 font-semibold">
                                LKR {netAvailableCompanyWide.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                            </span>
                        </div>
                    </div>

                    {/* Pending Owner Approvals */}
                    <div className="bg-white dark:bg-[#0F172A] rounded-2xl border border-slate-200/80 dark:border-slate-800/80 p-6 shadow-xs flex flex-col justify-between">
                        <div>
                            <span className="text-xs font-semibold text-amber-700 dark:text-amber-400 uppercase tracking-wider">
                                Pending Owner Review
                            </span>
                            <p className="text-2xl font-bold font-mono text-amber-900 dark:text-amber-300 mt-2">
                                LKR {metrics.pending_requests_amount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                            </p>
                        </div>
                        <div className="mt-4 pt-3 border-t border-slate-100 dark:border-slate-800/80 text-xs text-slate-600 dark:text-slate-400 flex justify-between items-center">
                            <span>Requests:</span>
                            <span className="font-mono font-bold bg-amber-100 dark:bg-amber-950/60 text-amber-800 dark:text-amber-300 px-2 py-0.5 rounded-full">
                                {metrics.pending_requests_count} pending
                            </span>
                        </div>
                    </div>

                    {/* Reserved in Approved / Processing */}
                    <div className="bg-white dark:bg-[#0F172A] rounded-2xl border border-slate-200/80 dark:border-slate-800/80 p-6 shadow-xs flex flex-col justify-between">
                        <div>
                            <span className="text-xs font-semibold text-blue-700 dark:text-blue-400 uppercase tracking-wider">
                                In Transfer / Processing
                            </span>
                            <p className="text-2xl font-bold font-mono text-blue-950 dark:text-blue-300 mt-2">
                                LKR {metrics.processing_requests_amount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                            </p>
                        </div>
                        <div className="mt-4 pt-3 border-t border-slate-100 dark:border-slate-800/80 text-xs text-slate-600 dark:text-slate-400 flex justify-between items-center">
                            <span>Approved queue:</span>
                            <span className="font-mono font-bold bg-blue-100 dark:bg-blue-950/60 text-blue-800 dark:text-blue-300 px-2 py-0.5 rounded-full">
                                {metrics.processing_requests_count} awaiting payment
                            </span>
                        </div>
                    </div>

                    {/* Total Completed Payouts */}
                    <div className="bg-white dark:bg-[#0F172A] rounded-2xl border border-slate-200/80 dark:border-slate-800/80 p-6 shadow-xs flex flex-col justify-between">
                        <div>
                            <span className="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                Total Settled Payouts
                            </span>
                            <p className="text-2xl font-bold font-mono text-purple-950 dark:text-purple-300 mt-2">
                                LKR {metrics.total_completed_payouts.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                            </p>
                        </div>
                        <div className="mt-4 pt-3 border-t border-slate-100 dark:border-slate-800/80 text-xs text-slate-600 dark:text-slate-400 flex justify-between items-center">
                            <span>Completed transfers:</span>
                            <span className="font-mono font-semibold text-slate-800 dark:text-slate-200">
                                {metrics.paid_requests_count} paid
                            </span>
                        </div>
                    </div>
                </div>

                {/* Secondary Status Counts */}
                <div className="grid grid-cols-2 sm:grid-cols-4 gap-4 text-xs font-medium">
                    <div className="bg-slate-50 dark:bg-[#1E293B]/70 p-4 rounded-xl border border-slate-200 dark:border-slate-800">
                        <span className="text-slate-500 dark:text-slate-400 block">Total Approved Commissions</span>
                        <strong className="text-sm font-mono text-slate-900 dark:text-white mt-1 block">
                            LKR {metrics.total_approved_commissions.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                        </strong>
                    </div>

                    <div className="bg-slate-50 dark:bg-[#1E293B]/70 p-4 rounded-xl border border-slate-200 dark:border-slate-800">
                        <span className="text-slate-500 dark:text-slate-400 block">Commissions Pending Maturation</span>
                        <strong className="text-sm font-mono text-slate-700 dark:text-slate-300 mt-1 block">
                            LKR {metrics.total_pending_commissions.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                        </strong>
                    </div>

                    <div className="bg-slate-50 dark:bg-[#1E293B]/70 p-4 rounded-xl border border-slate-200 dark:border-slate-800">
                        <span className="text-slate-500 dark:text-slate-400 block">Total Reserved Balances</span>
                        <strong className="text-sm font-mono text-amber-700 dark:text-amber-400 mt-1 block">
                            LKR {metrics.total_reserved_payouts.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                        </strong>
                    </div>

                    <div className="bg-slate-50 dark:bg-[#1E293B]/70 p-4 rounded-xl border border-slate-200 dark:border-slate-800">
                        <span className="text-slate-500 dark:text-slate-400 block">Rejected Payout Requests</span>
                        <strong className="text-sm font-mono text-rose-700 dark:text-rose-400 mt-1 block">
                            {metrics.rejected_requests_count} rejected
                        </strong>
                    </div>
                </div>

                {/* Recent Payout Requests Queue Table */}
                <div className="bg-white dark:bg-[#0F172A] rounded-2xl border border-slate-200/80 dark:border-slate-800/80 shadow-xs overflow-hidden">
                    <div className="px-6 py-4 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
                        <div>
                            <h2 className="text-base font-bold text-slate-900 dark:text-white">Recent Payout Requests Activity</h2>
                            <p className="text-xs text-slate-500 dark:text-slate-400">Live feed of withdrawal requests and settlement progress</p>
                        </div>
                        <Link href="/admin/payouts" className="text-xs font-semibold text-rose-700 dark:text-blue-400 hover:underline">
                            View Full Queue &rarr;
                        </Link>
                    </div>

                    {recentPayouts.length === 0 ? (
                        <div className="p-12 text-center text-slate-400 dark:text-slate-500 text-sm">
                            No payout requests recorded yet.
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-slate-200 dark:divide-slate-800 text-left text-xs">
                                <thead className="bg-slate-50 dark:bg-[#1E293B]/70 text-slate-500 dark:text-slate-400 uppercase font-semibold">
                                    <tr>
                                        <th className="px-6 py-3.5">Reference</th>
                                        <th className="px-6 py-3.5">Agent</th>
                                        <th className="px-6 py-3.5">Amount</th>
                                        <th className="px-6 py-3.5">Destination Bank</th>
                                        <th className="px-6 py-3.5">Status</th>
                                        <th className="px-6 py-3.5">Requested At</th>
                                        <th className="px-6 py-3.5 text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100 dark:divide-slate-800/60 font-sans">
                                    {recentPayouts.map((payout) => (
                                        <tr key={payout.id} className="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition-colors">
                                            <td className="px-6 py-3.5 font-mono font-semibold text-slate-900 dark:text-white whitespace-nowrap">
                                                {payout.reference_number}
                                            </td>
                                            <td className="px-6 py-3.5 whitespace-nowrap">
                                                <div className="font-semibold text-slate-900 dark:text-white">{payout.agent?.name}</div>
                                                <div className="text-[11px] text-slate-500 dark:text-slate-400 font-mono">{payout.agent?.referral_code}</div>
                                            </td>
                                            <td className="px-6 py-3.5 font-mono font-bold text-slate-900 dark:text-white whitespace-nowrap">
                                                LKR {Number(payout.amount).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                            </td>
                                            <td className="px-6 py-3.5 whitespace-nowrap">
                                                <div className="font-medium text-slate-900 dark:text-white">{payout.bank_name}</div>
                                                <div className="text-[11px] text-slate-500 dark:text-slate-400">{payout.bank_account_number}</div>
                                            </td>
                                            <td className="px-6 py-3.5 whitespace-nowrap">
                                                <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold ${
                                                    payout.status === 'pending' ? 'bg-amber-100 dark:bg-amber-950/60 text-amber-800 dark:text-amber-300' :
                                                    payout.status === 'approved' ? 'bg-blue-100 dark:bg-blue-950/60 text-blue-800 dark:text-blue-300' :
                                                    payout.status === 'processing' ? 'bg-indigo-100 dark:bg-indigo-950/60 text-indigo-800 dark:text-indigo-300' :
                                                    payout.status === 'paid' ? 'bg-emerald-100 dark:bg-emerald-950/60 text-emerald-800 dark:text-emerald-300' :
                                                    payout.status === 'rejected' ? 'bg-rose-100 dark:bg-rose-950/60 text-rose-800 dark:text-rose-300' :
                                                    'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300'
                                                }`}>
                                                    {payout.status.toUpperCase()}
                                                </span>
                                            </td>
                                            <td className="px-6 py-3.5 text-slate-600 dark:text-slate-400 whitespace-nowrap">
                                                {new Date(payout.created_at).toLocaleString()}
                                            </td>
                                            <td className="px-6 py-3.5 text-right whitespace-nowrap">
                                                <Link
                                                    href={`/admin/payouts/${payout.id}`}
                                                    className="text-xs font-semibold text-rose-700 dark:text-blue-400 hover:underline"
                                                >
                                                    Inspect Record &rarr;
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
