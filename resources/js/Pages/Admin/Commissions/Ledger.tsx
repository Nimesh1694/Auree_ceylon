import React, { useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { PageProps, CommissionLedgerEntry } from '@/types';
import {
    CreditCard,
    Search,
    Clock,
    CheckCircle2,
    XCircle,
    ChevronLeft,
    ChevronRight,
    Play,
    AlertCircle,
} from '@/Components/Icons';

interface PaginatedEntries {
    data: CommissionLedgerEntry[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
    total: number;
}

interface LedgerProps extends PageProps {
    entries: PaginatedEntries;
    filters: {
        status?: string;
        type?: string;
        search?: string;
    };
    stats: {
        total_pending: number;
        total_approved: number;
        total_reversed: number;
        total_count: number;
    };
}

export default function Ledger({ entries, filters, stats }: LedgerProps) {
    const { flash } = usePage<PageProps>().props;
    const [search, setSearch] = useState(filters.search || '');
    const [status, setStatus] = useState(filters.status || '');
    const [type, setType] = useState(filters.type || '');

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(
            route('admin.commissions.ledger'),
            { search, status, type },
            { preserveState: true }
        );
    };

    const handleFilterChange = (newStatus: string, newType: string) => {
        setStatus(newStatus);
        setType(newType);
        router.get(
            route('admin.commissions.ledger'),
            { search, status: newStatus, type: newType },
            { preserveState: true }
        );
    };

    const handleProcessEligible = () => {
        if (confirm('Trigger evaluation of all pending commissions past the return window?')) {
            router.post(route('admin.commissions.process-eligible'));
        }
    };

    const getTypeBadge = (entryType: string) => {
        switch (entryType) {
            case 'direct_high_level':
                return (
                    <span className="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 dark:bg-emerald-950/60 text-emerald-800 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                        Direct High (25%)
                    </span>
                );
            case 'direct_low_level':
                return (
                    <span className="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-blue-100 dark:bg-blue-950/60 text-blue-800 dark:text-blue-300 border border-blue-200 dark:border-blue-800">
                        Direct Low (25%)
                    </span>
                );
            case 'upline_override':
                return (
                    <span className="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-purple-100 dark:bg-purple-950/60 text-purple-800 dark:text-purple-300 border border-purple-200 dark:border-purple-800">
                        Upline Override (10%)
                    </span>
                );
            case 'reversal':
                return (
                    <span className="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-rose-100 dark:bg-rose-950/60 text-rose-800 dark:text-rose-300 border border-rose-200 dark:border-rose-800">
                        Reversal / Refund
                    </span>
                );
            default:
                return (
                    <span className="inline-block px-2 py-0.5 rounded-full text-xs font-medium bg-slate-100 dark:bg-slate-800 text-slate-800 dark:text-slate-200 capitalize">
                        {entryType.replace(/_/g, ' ')}
                    </span>
                );
        }
    };

    const getStatusBadge = (entryStatus: string) => {
        switch (entryStatus) {
            case 'approved':
                return (
                    <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 dark:bg-emerald-950/60 text-emerald-800 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                        <CheckCircle2 className="w-3.5 h-3.5" /> Approved
                    </span>
                );
            case 'reversed':
                return (
                    <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-rose-100 dark:bg-rose-950/60 text-rose-800 dark:text-rose-300 border border-rose-200 dark:border-rose-800">
                        <XCircle className="w-3.5 h-3.5" /> Reversed
                    </span>
                );
            default:
                return (
                    <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-100 dark:bg-amber-950/60 text-amber-800 dark:text-amber-300 border border-amber-200 dark:border-amber-800">
                        <Clock className="w-3.5 h-3.5" /> Pending
                    </span>
                );
        }
    };

    return (
        <AdminLayout>
            <Head title="Commission Ledger & Audit" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold text-slate-900 dark:text-white tracking-tight">Commission Financial Ledger</h1>
                        <p className="text-sm text-slate-500 dark:text-slate-400 mt-1">
                            Immutable, append-only record of all multi-tier agent commissions, upline overrides, and reversal entries.
                        </p>
                    </div>

                    <button
                        type="button"
                        onClick={handleProcessEligible}
                        className="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-semibold shadow-xs transition-colors cursor-pointer"
                    >
                        <Play className="w-3.5 h-3.5" />
                        Process Eligible Matured Commissions
                    </button>
                </div>

                {/* Flash Messages */}
                {flash?.success && (
                    <div className="p-4 bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-300 rounded-2xl flex items-center gap-3">
                        <CheckCircle2 className="w-5 h-5 text-emerald-600 dark:text-emerald-400 shrink-0" />
                        <p className="text-sm font-medium">{flash.success}</p>
                    </div>
                )}
                {flash?.error && (
                    <div className="p-4 bg-rose-50 dark:bg-rose-950/50 border border-rose-200 dark:border-rose-800 text-rose-800 dark:text-rose-300 rounded-2xl flex items-center gap-3">
                        <AlertCircle className="w-5 h-5 text-rose-600 dark:text-rose-400 shrink-0" />
                        <p className="text-sm font-medium">{flash.error}</p>
                    </div>
                )}

                {/* Stats */}
                <div className="grid grid-cols-1 sm:grid-cols-4 gap-5">
                    <div className="bg-white dark:bg-[#0F172A] rounded-2xl border border-slate-200/80 dark:border-slate-800/80 p-5 shadow-xs">
                        <span className="text-xs font-semibold text-amber-700 dark:text-amber-400 uppercase tracking-wider">Pending Review & Maturing</span>
                        <p className="text-2xl font-bold text-slate-900 dark:text-white mt-1 font-mono">
                            LKR {stats.total_pending.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                        </p>
                        <span className="text-[11px] text-slate-400 dark:text-slate-500 mt-0.5 block">Delivered COD undergoing return grace</span>
                    </div>

                    <div className="bg-white dark:bg-[#0F172A] rounded-2xl border border-slate-200/80 dark:border-slate-800/80 p-5 shadow-xs">
                        <span className="text-xs font-semibold text-emerald-700 dark:text-emerald-400 uppercase tracking-wider">Total Approved (Payable)</span>
                        <p className="text-2xl font-bold text-slate-900 dark:text-white mt-1 font-mono">
                            LKR {stats.total_approved.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                        </p>
                        <span className="text-[11px] text-slate-400 dark:text-slate-500 mt-0.5 block">Approved net liability to network</span>
                    </div>

                    <div className="bg-white dark:bg-[#0F172A] rounded-2xl border border-slate-200/80 dark:border-slate-800/80 p-5 shadow-xs">
                        <span className="text-xs font-semibold text-rose-700 dark:text-rose-400 uppercase tracking-wider">Total Reversals</span>
                        <p className="text-2xl font-bold text-slate-900 dark:text-white mt-1 font-mono">
                            LKR {Math.abs(stats.total_reversed).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                        </p>
                        <span className="text-[11px] text-slate-400 dark:text-slate-500 mt-0.5 block">Cancelled & returned product deductions</span>
                    </div>

                    <div className="bg-white dark:bg-[#0F172A] rounded-2xl border border-slate-200/80 dark:border-slate-800/80 p-5 shadow-xs">
                        <span className="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Total Ledger Entries</span>
                        <p className="text-2xl font-bold text-slate-900 dark:text-white mt-1 font-mono">{stats.total_count}</p>
                        <span className="text-[11px] text-slate-400 dark:text-slate-500 mt-0.5 block">Append-only immutable transactions</span>
                    </div>
                </div>

                {/* Filters */}
                <div className="bg-white dark:bg-[#0F172A] rounded-2xl border border-slate-200/80 dark:border-slate-800/80 p-4 shadow-xs">
                    <form onSubmit={handleSearch} className="flex flex-wrap items-center gap-3">
                        <div className="relative flex-1 min-w-[240px]">
                            <Search className="w-4 h-4 text-slate-400 absolute left-3 top-3" />
                            <input
                                type="text"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Search by entry #, order #, or agent..."
                                className="w-full pl-9 pr-3 py-2 border border-slate-300 dark:border-slate-700 rounded-xl text-sm bg-white dark:bg-[#1E293B] text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 focus:ring-2 focus:ring-blue-500"
                            />
                        </div>

                        <select
                            value={status}
                            onChange={(e) => handleFilterChange(e.target.value, type)}
                            className="px-3 py-2 border border-slate-300 dark:border-slate-700 rounded-xl text-sm bg-white dark:bg-[#1E293B] text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500"
                        >
                            <option value="">All Statuses</option>
                            <option value="pending">Pending</option>
                            <option value="approved">Approved</option>
                            <option value="reversed">Reversed</option>
                        </select>

                        <select
                            value={type}
                            onChange={(e) => handleFilterChange(status, e.target.value)}
                            className="px-3 py-2 border border-slate-300 dark:border-slate-700 rounded-xl text-sm bg-white dark:bg-[#1E293B] text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500"
                        >
                            <option value="">All Types</option>
                            <option value="direct_high_level">Direct High-Level (25%)</option>
                            <option value="direct_low_level">Direct Low-Level (25%)</option>
                            <option value="upline_override">Upline Override (10%)</option>
                            <option value="reversal">Reversals</option>
                        </select>

                        <button
                            type="submit"
                            className="px-4 py-2 bg-slate-900 dark:bg-blue-600 hover:bg-slate-800 dark:hover:bg-blue-500 text-white rounded-xl text-sm font-semibold transition-colors cursor-pointer"
                        >
                            Search
                        </button>
                    </form>
                </div>

                {/* Ledger Table */}
                <div className="bg-white dark:bg-[#0F172A] rounded-2xl border border-slate-200/80 dark:border-slate-800/80 shadow-xs overflow-hidden">
                    {entries.data.length === 0 ? (
                        <div className="p-12 text-center">
                            <CreditCard className="w-12 h-12 text-slate-300 dark:text-slate-600 mx-auto mb-3" />
                            <p className="text-base font-medium text-slate-700 dark:text-slate-300">No ledger entries found</p>
                            <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
                                Ledger transactions are automatically generated when customer orders are placed with agent referral attribution.
                            </p>
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-sm text-slate-600 dark:text-slate-300 divide-y divide-slate-200 dark:divide-slate-800">
                                <thead className="bg-slate-50 dark:bg-[#1E293B]/70 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                    <tr>
                                        <th className="px-5 py-3.5">Entry Number</th>
                                        <th className="px-5 py-3.5">Order Number</th>
                                        <th className="px-5 py-3.5">Beneficiary Agent</th>
                                        <th className="px-5 py-3.5">Type</th>
                                        <th className="px-5 py-3.5">Base Subtotal</th>
                                        <th className="px-5 py-3.5">Rate</th>
                                        <th className="px-5 py-3.5">Amount</th>
                                        <th className="px-5 py-3.5">Status</th>
                                        <th className="px-5 py-3.5">Date</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100 dark:divide-slate-800/60">
                                    {entries.data.map((entry) => (
                                        <tr key={entry.id} className="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition-colors">
                                            <td className="px-5 py-4 font-mono font-semibold text-xs text-slate-800 dark:text-slate-200">
                                                {entry.entry_number}
                                            </td>
                                            <td className="px-5 py-4 font-mono font-bold text-xs text-slate-900 dark:text-white">
                                                <Link
                                                    href={route('admin.orders.show', entry.order_id)}
                                                    className="hover:text-emerald-600 dark:hover:text-blue-400 hover:underline"
                                                >
                                                    {entry.order?.order_number || `#${entry.order_id}`}
                                                </Link>
                                            </td>
                                            <td className="px-5 py-4">
                                                <div className="font-semibold text-slate-900 dark:text-white">
                                                    {entry.agent?.name || '—'}
                                                </div>
                                                <div className="text-xs font-mono text-slate-400 dark:text-slate-500">
                                                    Ref: {entry.agent?.referral_code}
                                                </div>
                                            </td>
                                            <td className="px-5 py-4">
                                                {getTypeBadge(entry.commission_type)}
                                            </td>
                                            <td className="px-5 py-4 text-xs font-mono text-slate-700 dark:text-slate-300">
                                                LKR {Number(entry.base_amount).toLocaleString(undefined, { minimumFractionDigits: 2 })}
                                            </td>
                                            <td className="px-5 py-4 text-xs font-mono font-semibold text-slate-800 dark:text-slate-200">
                                                {Number(entry.rate_percentage)}%
                                            </td>
                                            <td className={`px-5 py-4 font-mono font-bold ${
                                                Number(entry.calculated_amount) < 0
                                                    ? 'text-rose-600 dark:text-rose-400'
                                                    : 'text-emerald-700 dark:text-emerald-400'
                                            }`}>
                                                {Number(entry.calculated_amount) < 0 ? '-' : '+'} LKR {Math.abs(Number(entry.calculated_amount)).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                            </td>
                                            <td className="px-5 py-4">
                                                {getStatusBadge(entry.status)}
                                            </td>
                                            <td className="px-5 py-4 text-xs text-slate-500 dark:text-slate-400 whitespace-nowrap">
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
                        <div className="p-4 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
                            <span>
                                Page {entries.current_page} of {entries.last_page} ({entries.total} total)
                            </span>
                            <div className="flex items-center gap-2">
                                {entries.prev_page_url && (
                                    <Link
                                        href={entries.prev_page_url}
                                        className="px-3 py-1.5 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 flex items-center gap-1 font-medium transition-colors"
                                    >
                                        <ChevronLeft className="w-3.5 h-3.5" /> Previous
                                    </Link>
                                )}
                                {entries.next_page_url && (
                                    <Link
                                        href={entries.next_page_url}
                                        className="px-3 py-1.5 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 flex items-center gap-1 font-medium transition-colors"
                                    >
                                        Next <ChevronRight className="w-3.5 h-3.5" />
                                    </Link>
                                )}
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </AdminLayout>
    );
}
