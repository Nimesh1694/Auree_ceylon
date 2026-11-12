import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AgentLayout from '@/Layouts/AgentLayout';
import { PageProps, CommissionLedgerEntry } from '@/types';
import {
    CreditCard,
    Clock,
    CheckCircle2,
    XCircle,
    ChevronLeft,
    ChevronRight,
    TrendingUp,
    ShieldAlert,
    FileCheck,
    Search,
    ArrowUpCircle,
} from '@/Components/Icons';

interface PaginatedEntries {
    data: CommissionLedgerEntry[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
    total: number;
}

interface IndexProps extends PageProps {
    entries: PaginatedEntries;
    filters: {
        status?: string;
        type?: string;
    };
    stats: {
        pending_amount: number;
        approved_amount: number;
        reversed_amount: number;
        wallet_balance: number;
        total_earned: number;
        total_entries_count: number;
    };
}

export default function Index({ entries, filters, stats }: IndexProps) {
    const [selectedStatus, setSelectedStatus] = useState(filters.status || '');
    const [selectedType, setSelectedType] = useState(filters.type || '');

    const handleFilterChange = (status: string, type: string) => {
        setSelectedStatus(status);
        setSelectedType(type);
        router.get(
            route('agent.commissions.index'),
            { status, type },
            { preserveState: true }
        );
    };

    const getTypeBadge = (type: string) => {
        switch (type) {
            case 'direct_high_level':
                return (
                    <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">
                        Direct Sale (25%)
                    </span>
                );
            case 'direct_low_level':
                return (
                    <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-800">
                        Direct Sale (25%)
                    </span>
                );
            case 'upline_override':
                return (
                    <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-purple-100 text-purple-800">
                        Upline Override (10%)
                    </span>
                );
            case 'reversal':
                return (
                    <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-800">
                        Reversal / Refund
                    </span>
                );
            default:
                return (
                    <span className="inline-block px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-800 capitalize">
                        {type.replace(/_/g, ' ')}
                    </span>
                );
        }
    };

    const getStatusBadge = (status: string) => {
        switch (status) {
            case 'approved':
                return (
                    <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">
                        <CheckCircle2 className="w-3.5 h-3.5" /> Approved
                    </span>
                );
            case 'reversed':
                return (
                    <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-800">
                        <XCircle className="w-3.5 h-3.5" /> Reversed
                    </span>
                );
            default:
                return (
                    <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800">
                        <Clock className="w-3.5 h-3.5" /> Pending
                    </span>
                );
        }
    };

    return (
        <AgentLayout>
            <Head title="My Commissions Ledger" />

            <div className="max-w-6xl mx-auto space-y-6">
                {/* Header */}
                <div>
                    <h1 className="text-2xl font-bold text-slate-900 tracking-tight">My Commission Ledger</h1>
                    <p className="text-sm text-slate-500 mt-1">
                        Traceable, immutable record of all product sales commissions and network upline overrides earned by your account.
                    </p>
                </div>

                {/* Metrics Cards */}
                <div className="grid grid-cols-1 sm:grid-cols-4 gap-5">
                    <div className="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm">
                        <span className="text-xs font-semibold text-amber-700 uppercase">Pending Approval</span>
                        <p className="text-2xl font-bold text-slate-900 mt-1 font-mono">
                            LKR {stats.pending_amount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                        </p>
                        <span className="text-[11px] text-slate-400 mt-0.5 block">Matures after COD & return window</span>
                    </div>

                    <div className="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm">
                        <span className="text-xs font-semibold text-emerald-700 uppercase">Approved Available</span>
                        <p className="text-2xl font-bold text-slate-900 mt-1 font-mono">
                            LKR {stats.approved_amount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                        </p>
                        <span className="text-[11px] text-slate-400 mt-0.5 block">Credited to wallet balance</span>
                    </div>

                    <div className="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm">
                        <span className="text-xs font-semibold text-red-700 uppercase">Reversed / Cancelled</span>
                        <p className="text-2xl font-bold text-slate-900 mt-1 font-mono">
                            LKR {Math.abs(stats.reversed_amount).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                        </p>
                        <span className="text-[11px] text-slate-400 mt-0.5 block">Returns & order cancellations</span>
                    </div>

                    <div className="bg-white rounded-2xl border border-emerald-200 bg-emerald-50/40 p-5 shadow-sm">
                        <span className="text-xs font-semibold text-emerald-800 uppercase">Total Lifetime Earned</span>
                        <p className="text-2xl font-bold text-emerald-950 mt-1 font-mono">
                            LKR {stats.total_earned.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                        </p>
                        <span className="text-[11px] text-emerald-700 mt-0.5 block">Approved net sales volume</span>
                    </div>
                </div>

                {/* Filters */}
                <div className="bg-white rounded-2xl border border-slate-200 p-4 shadow-sm flex flex-wrap items-center justify-between gap-4">
                    <div className="flex flex-wrap items-center gap-3">
                        <select
                            value={selectedStatus}
                            onChange={(e) => handleFilterChange(e.target.value, selectedType)}
                            className="px-3 py-1.5 border border-slate-300 rounded-lg text-xs font-medium focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                        >
                            <option value="">All Statuses</option>
                            <option value="pending">Pending</option>
                            <option value="approved">Approved</option>
                            <option value="reversed">Reversed</option>
                        </select>

                        <select
                            value={selectedType}
                            onChange={(e) => handleFilterChange(selectedStatus, e.target.value)}
                            className="px-3 py-1.5 border border-slate-300 rounded-lg text-xs font-medium focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                        >
                            <option value="">All Commission Types</option>
                            <option value="direct_high_level">Direct Sale (High-Level 25%)</option>
                            <option value="direct_low_level">Direct Sale (Low-Level 25%)</option>
                            <option value="upline_override">Upline Override (10%)</option>
                            <option value="reversal">Reversals</option>
                        </select>
                    </div>

                    <div className="text-xs text-slate-500">
                        Showing <strong>{entries.total}</strong> ledger transactions
                    </div>
                </div>

                {/* Ledger Table */}
                <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                    {entries.data.length === 0 ? (
                        <div className="p-12 text-center">
                            <CreditCard className="w-12 h-12 text-slate-300 mx-auto mb-3" />
                            <p className="text-base font-medium text-slate-700">No commission records found</p>
                            <p className="text-xs text-slate-500 mt-1">
                                Commissions are automatically credited to this ledger when orders are placed with your referral attribution.
                            </p>
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-sm text-slate-600">
                                <thead className="bg-slate-50 text-xs font-semibold text-slate-500 uppercase tracking-wider border-b border-slate-200">
                                    <tr>
                                        <th className="px-5 py-3">Entry Number</th>
                                        <th className="px-5 py-3">Order Reference</th>
                                        <th className="px-5 py-3">Type</th>
                                        <th className="px-5 py-3">Sale Base</th>
                                        <th className="px-5 py-3">Rate</th>
                                        <th className="px-5 py-3">Commission Amount</th>
                                        <th className="px-5 py-3">Status</th>
                                        <th className="px-5 py-3">Date</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {entries.data.map((entry) => (
                                        <tr key={entry.id} className="hover:bg-slate-50 transition-colors">
                                            <td className="px-5 py-4 font-mono font-semibold text-xs text-slate-800">
                                                {entry.entry_number}
                                            </td>
                                            <td className="px-5 py-4 font-mono font-bold text-xs text-slate-900">
                                                {entry.order?.order_number || `#${entry.order_id}`}
                                            </td>
                                            <td className="px-5 py-4">
                                                {getTypeBadge(entry.commission_type)}
                                            </td>
                                            <td className="px-5 py-4 text-xs font-mono text-slate-700">
                                                LKR {Number(entry.base_amount).toLocaleString(undefined, { minimumFractionDigits: 2 })}
                                            </td>
                                            <td className="px-5 py-4 text-xs font-mono font-semibold text-slate-800">
                                                {Number(entry.rate_percentage)}%
                                            </td>
                                            <td className={`px-5 py-4 font-mono font-bold ${
                                                Number(entry.calculated_amount) < 0
                                                    ? 'text-red-600'
                                                    : 'text-emerald-700'
                                            }`}>
                                                {Number(entry.calculated_amount) < 0 ? '-' : '+'} LKR {Math.abs(Number(entry.calculated_amount)).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                            </td>
                                            <td className="px-5 py-4">
                                                {getStatusBadge(entry.status)}
                                            </td>
                                            <td className="px-5 py-4 text-xs text-slate-500 whitespace-nowrap">
                                                {new Date(entry.created_at).toLocaleDateString()}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}

                    {/* Pagination */}
                    {entries.last_page > 1 && (
                        <div className="p-4 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
                            <span>
                                Page {entries.current_page} of {entries.last_page} ({entries.total} total)
                            </span>
                            <div className="flex items-center gap-2">
                                {entries.prev_page_url && (
                                    <Link
                                        href={entries.prev_page_url}
                                        className="px-3 py-1.5 border border-slate-300 rounded-lg text-slate-700 hover:bg-slate-50 flex items-center gap-1 font-medium"
                                    >
                                        <ChevronLeft className="w-3.5 h-3.5" /> Previous
                                    </Link>
                                )}
                                {entries.next_page_url && (
                                    <Link
                                        href={entries.next_page_url}
                                        className="px-3 py-1.5 border border-slate-300 rounded-lg text-slate-700 hover:bg-slate-50 flex items-center gap-1 font-medium"
                                    >
                                        Next <ChevronRight className="w-3.5 h-3.5" />
                                    </Link>
                                )}
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </AgentLayout>
    );
}

