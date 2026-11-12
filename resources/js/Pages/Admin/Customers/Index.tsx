import AdminLayout from '@/Layouts/AdminLayout';
import { PaginatedData } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import React, { useState } from 'react';

interface CustomerListItem {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    status: string;
    created_at: string | null;
    orders_count: number;
    total_spent: number;
    latest_order_date: string | null;
}

interface Props {
    customers: PaginatedData<CustomerListItem>;
    filters: {
        search?: string;
        status?: string;
    };
    stats: {
        total: number;
        active: number;
        suspended: number;
    };
}

export default function CustomerIndex({ customers, filters, stats }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [status, setStatus] = useState(filters.status ?? '');

    const handleFilter = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(
            '/admin/customers',
            {
                search: search || undefined,
                status: status || undefined,
            },
            { preserveState: true }
        );
    };

    const handleClear = () => {
        setSearch('');
        setStatus('');
        router.get('/admin/customers');
    };

    const formatCurrency = (amount: number) => {
        return new Intl.NumberFormat('en-LK', {
            style: 'currency',
            currency: 'LKR',
            minimumFractionDigits: 2,
        }).format(amount);
    };

    const formatDate = (dateString: string | null) => {
        if (!dateString) return '—';
        return new Date(dateString).toLocaleDateString('en-GB', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
        });
    };

    return (
        <AdminLayout>
            <Head title="Customer Directory | Aura Luxe" />

            <div className="space-y-6">
                {/* Header */}
                <div>
                    <h1 className="font-serif text-2xl text-slate-900 dark:text-white font-semibold">
                        Customer Directory
                    </h1>
                    <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
                        View registered storefront customers, inspect delivery addresses, analyze customer order history, and manage account status.
                    </p>
                </div>

                {/* Quick Metric Cards */}
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs">
                    <div className="bg-white dark:bg-[#0F172A] p-4 rounded-2xl border border-slate-200/80 dark:border-slate-800/80 shadow-xs">
                        <span className="text-slate-500 dark:text-slate-400 block">Total Customers</span>
                        <span className="font-serif text-2xl font-bold text-slate-900 dark:text-white mt-1 block">
                            {stats.total.toLocaleString()}
                        </span>
                        <span className="text-[11px] text-slate-400 mt-0.5 block">Registered storefront accounts</span>
                    </div>

                    <div className="bg-white dark:bg-[#0F172A] p-4 rounded-2xl border border-slate-200/80 dark:border-slate-800/80 shadow-xs">
                        <span className="text-emerald-600 dark:text-emerald-400 font-medium block">Active Customers</span>
                        <span className="font-serif text-2xl font-bold text-emerald-700 dark:text-emerald-300 mt-1 block">
                            {stats.active.toLocaleString()}
                        </span>
                        <span className="text-[11px] text-slate-400 mt-0.5 block">Good standing & active</span>
                    </div>

                    <div className="bg-white dark:bg-[#0F172A] p-4 rounded-2xl border border-slate-200/80 dark:border-slate-800/80 shadow-xs">
                        <span className="text-rose-600 dark:text-rose-400 font-medium block">Suspended Accounts</span>
                        <span className="font-serif text-2xl font-bold text-rose-700 dark:text-rose-300 mt-1 block">
                            {stats.suspended.toLocaleString()}
                        </span>
                        <span className="text-[11px] text-slate-400 mt-0.5 block">Access restricted & sessions killed</span>
                    </div>
                </div>

                {/* Filter Toolbar */}
                <form
                    onSubmit={handleFilter}
                    className="bg-white dark:bg-[#0F172A] p-4 rounded-2xl border border-slate-200/80 dark:border-slate-800/80 shadow-xs flex flex-wrap items-center gap-3 text-xs"
                >
                    <input
                        type="text"
                        placeholder="Search name, email, phone..."
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        className="rounded-xl border-slate-300 dark:border-slate-700 bg-white dark:bg-[#1E293B] text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 text-xs px-3 py-1.5 w-64 focus:border-amber-500 focus:ring-amber-500"
                    />

                    <select
                        value={status}
                        onChange={(e) => setStatus(e.target.value)}
                        className="rounded-xl border-slate-300 dark:border-slate-700 bg-white dark:bg-[#1E293B] text-slate-900 dark:text-white text-xs py-1.5 focus:border-amber-500 focus:ring-amber-500"
                    >
                        <option value="">All Statuses</option>
                        <option value="active">Active</option>
                        <option value="suspended">Suspended</option>
                    </select>

                    <button
                        type="submit"
                        className="px-4 py-1.5 rounded-xl bg-slate-900 dark:bg-amber-600 text-white font-medium hover:bg-slate-800 dark:hover:bg-amber-500 transition-colors shadow-xs"
                    >
                        Filter
                    </button>

                    {(filters.search || filters.status) && (
                        <button
                            type="button"
                            onClick={handleClear}
                            className="px-3 py-1.5 rounded-xl border border-slate-300 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                        >
                            Clear
                        </button>
                    )}
                </form>

                {/* Customers Table */}
                <div className="bg-white dark:bg-[#0F172A] rounded-2xl border border-slate-200/80 dark:border-slate-800/80 shadow-xs overflow-hidden text-xs">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left border-collapse">
                            <thead>
                                <tr className="border-b border-slate-200/80 dark:border-slate-800/80 bg-slate-50/75 dark:bg-slate-900/40 text-slate-500 dark:text-slate-400 uppercase tracking-wider text-[10px]">
                                    <th className="px-5 py-3.5 font-semibold">Customer</th>
                                    <th className="px-5 py-3.5 font-semibold">Phone</th>
                                    <th className="px-5 py-3.5 font-semibold">Status</th>
                                    <th className="px-5 py-3.5 font-semibold text-center">Orders</th>
                                    <th className="px-5 py-3.5 font-semibold text-right">Lifetime Spend</th>
                                    <th className="px-5 py-3.5 font-semibold">Latest Order</th>
                                    <th className="px-5 py-3.5 font-semibold">Registered</th>
                                    <th className="px-5 py-3.5 font-semibold text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 dark:divide-slate-800/60">
                                {customers.data.length > 0 ? (
                                    customers.data.map((c) => (
                                        <tr
                                            key={c.id}
                                            className="hover:bg-slate-50/60 dark:hover:bg-slate-800/30 transition-colors"
                                        >
                                            <td className="px-5 py-3.5">
                                                <div className="flex items-center gap-3">
                                                    <div className="w-8 h-8 rounded-full bg-amber-100 dark:bg-amber-950/60 border border-amber-300/60 dark:border-amber-700/60 text-amber-900 dark:text-amber-200 flex items-center justify-center font-serif font-bold text-xs uppercase shrink-0">
                                                        {c.name.charAt(0)}
                                                    </div>
                                                    <div className="min-w-0">
                                                        <Link
                                                            href={`/admin/customers/${c.id}`}
                                                            className="font-semibold text-slate-900 dark:text-white hover:text-amber-600 dark:hover:text-amber-400 transition-colors block truncate"
                                                        >
                                                            {c.name}
                                                        </Link>
                                                        <span className="text-[11px] text-slate-500 dark:text-slate-400 block truncate">
                                                            {c.email}
                                                        </span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="px-5 py-3.5 font-mono text-slate-700 dark:text-slate-300">
                                                {c.phone || <span className="text-slate-400 italic">None</span>}
                                            </td>
                                            <td className="px-5 py-3.5">
                                                {c.status === 'active' ? (
                                                    <span className="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[11px] font-medium bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400 border border-emerald-200/60 dark:border-emerald-800/40">
                                                        <span className="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                                        Active
                                                    </span>
                                                ) : (
                                                    <span className="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[11px] font-medium bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-400 border border-rose-200/60 dark:border-rose-800/40">
                                                        <span className="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                                                        Suspended
                                                    </span>
                                                )}
                                            </td>
                                            <td className="px-5 py-3.5 text-center">
                                                <span className="inline-block px-2 py-0.5 rounded-lg bg-slate-100 dark:bg-slate-800 font-mono font-medium text-slate-800 dark:text-slate-200">
                                                    {c.orders_count}
                                                </span>
                                            </td>
                                            <td className="px-5 py-3.5 text-right font-medium text-slate-900 dark:text-white font-mono">
                                                {formatCurrency(c.total_spent)}
                                            </td>
                                            <td className="px-5 py-3.5 text-slate-600 dark:text-slate-400">
                                                {formatDate(c.latest_order_date)}
                                            </td>
                                            <td className="px-5 py-3.5 text-slate-500 dark:text-slate-400">
                                                {formatDate(c.created_at)}
                                            </td>
                                            <td className="px-5 py-3.5 text-right whitespace-nowrap">
                                                <Link
                                                    href={`/admin/customers/${c.id}`}
                                                    className="inline-flex items-center gap-1 px-3 py-1 rounded-xl text-xs font-medium text-amber-700 dark:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-950/40 transition-colors"
                                                >
                                                    View Profile &rarr;
                                                </Link>
                                            </td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td colSpan={8} className="py-12 text-center text-slate-400 dark:text-slate-500">
                                            <div className="flex flex-col items-center justify-center space-y-2">
                                                <svg
                                                    className="w-8 h-8 text-slate-300 dark:text-slate-600"
                                                    fill="none"
                                                    viewBox="0 0 24 24"
                                                    stroke="currentColor"
                                                >
                                                    <path
                                                        strokeLinecap="round"
                                                        strokeLinejoin="round"
                                                        strokeWidth={1.5}
                                                        d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"
                                                    />
                                                </svg>
                                                <span>No customers found matching your filters.</span>
                                                {(filters.search || filters.status) && (
                                                    <button
                                                        type="button"
                                                        onClick={handleClear}
                                                        className="text-amber-600 hover:underline text-xs mt-1"
                                                    >
                                                        Clear filters
                                                    </button>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Pagination */}
                    {customers.links && customers.links.length > 3 && (
                        <div className="p-4 border-t border-slate-100 dark:border-slate-800/80 flex items-center justify-between text-xs">
                            <span className="text-slate-500 dark:text-slate-400">
                                Showing {customers.from ?? 0} to {customers.to ?? 0} of {customers.total} customers
                            </span>
                            <div className="flex gap-1">
                                {customers.links.map((link, i) => (
                                    <Link
                                        key={i}
                                        href={link.url ?? '#'}
                                        preserveScroll
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                        className={`px-3 py-1.5 rounded-xl border text-xs transition-colors ${
                                            link.active
                                                ? 'bg-amber-600 border-amber-600 text-white font-semibold'
                                                : link.url
                                                ? 'border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800'
                                                : 'border-transparent text-slate-300 dark:text-slate-600 cursor-not-allowed'
                                        }`}
                                    />
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </AdminLayout>
    );
}

