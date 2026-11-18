import AdminLayout from '@/Layouts/AdminLayout';
import { PaginatedData } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import React, { useState } from 'react';

interface SupplierRow {
    id: number;
    code: string;
    name: string;
    contact_person: string | null;
    email: string | null;
    phone: string | null;
    city: string | null;
    payment_terms: string;
    default_lead_time_days: number;
    status: 'active' | 'inactive';
    products_count: number;
    purchase_orders_count: number;
}

interface Props {
    suppliers: PaginatedData<SupplierRow>;
    filters: {
        search?: string;
        status?: string;
    };
    stats: {
        total: number;
        active: number;
        inactive: number;
    };
    canManage: boolean;
}

const PAYMENT_TERMS_LABELS: Record<string, string> = {
    cod: 'Cash on Delivery',
    advance: 'Advance Payment',
    net_7: 'Net 7 Days',
    net_15: 'Net 15 Days',
    net_30: 'Net 30 Days',
    net_60: 'Net 60 Days',
};

export default function SuppliersIndex({ suppliers, filters, stats, canManage }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [status, setStatus] = useState(filters.status ?? 'all');
    const [togglingId, setTogglingId] = useState<number | null>(null);

    const handleFilter = (e: React.FormEvent) => {
        e.preventDefault();
        router.get('/admin/suppliers', {
            search: search || undefined,
            status: status !== 'all' ? status : undefined,
        }, { preserveState: true });
    };

    const handleToggleStatus = (supplier: SupplierRow) => {
        const action = supplier.status === 'active' ? 'deactivate' : 'activate';
        if (!window.confirm(`Are you sure you want to ${action} supplier "${supplier.name}"?`)) {
            return;
        }
        setTogglingId(supplier.id);
        router.patch(`/admin/suppliers/${supplier.id}/toggle-status`, {}, {
            preserveScroll: true,
            onFinish: () => setTogglingId(null),
        });
    };

    return (
        <AdminLayout>
            <Head title="Supplier Management | Aura Luxe" />

            <div className="space-y-6">
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <h1 className="font-serif text-2xl text-slate-900 dark:text-white font-semibold">Supplier Management</h1>
                        <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
                            Manage approved suppliers, payment terms, lead times, and product sourcing relationships.
                        </p>
                    </div>
                    {canManage && (
                        <Link
                            href="/admin/suppliers/create"
                            className="bg-slate-900 dark:bg-blue-600 text-white text-xs font-semibold px-4 py-2.5 rounded-xl hover:bg-slate-800 dark:hover:bg-blue-500 transition-colors shadow-xs text-center"
                        >
                            + Add Supplier
                        </Link>
                    )}
                </div>

                {/* Summary Cards */}
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div className="bg-white dark:bg-[#0F172A] p-5 rounded-2xl border border-slate-200/80 dark:border-slate-800/80 shadow-xs">
                        <span className="text-[11px] text-slate-500 dark:text-slate-400 uppercase tracking-wider font-semibold">Total Suppliers</span>
                        <span className="text-2xl font-bold text-slate-900 dark:text-white mt-1 block font-mono">{stats.total}</span>
                    </div>
                    <div className="bg-white dark:bg-[#0F172A] p-5 rounded-2xl border border-emerald-200/80 dark:border-emerald-900/40 shadow-xs">
                        <span className="text-[11px] text-emerald-700 dark:text-emerald-400 uppercase tracking-wider font-semibold">Active Suppliers</span>
                        <span className="text-2xl font-bold text-emerald-800 dark:text-emerald-300 mt-1 block font-mono">{stats.active}</span>
                    </div>
                    <div className="bg-white dark:bg-[#0F172A] p-5 rounded-2xl border border-slate-200/80 dark:border-slate-800/80 shadow-xs">
                        <span className="text-[11px] text-slate-500 dark:text-slate-400 uppercase tracking-wider font-semibold">Inactive Suppliers</span>
                        <span className="text-2xl font-bold text-slate-700 dark:text-slate-300 mt-1 block font-mono">{stats.inactive}</span>
                    </div>
                </div>

                {/* Filters */}
                <form onSubmit={handleFilter} className="bg-white dark:bg-[#0F172A] p-4 rounded-2xl border border-slate-200/80 dark:border-slate-800/80 flex flex-col sm:flex-row gap-3">
                    <input
                        type="text"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Search by supplier name, code, contact, email, or phone..."
                        className="flex-1 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 px-3.5 py-2 text-xs text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-slate-900 dark:focus:ring-blue-500"
                    />
                    <select
                        value={status}
                        onChange={(e) => setStatus(e.target.value)}
                        className="rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 px-3.5 py-2 text-xs text-slate-900 dark:text-white"
                    >
                        <option value="all">All Statuses</option>
                        <option value="active">Active Only</option>
                        <option value="inactive">Inactive Only</option>
                    </select>
                    <button
                        type="submit"
                        className="bg-slate-900 dark:bg-blue-600 text-white text-xs font-semibold px-4 py-2 rounded-xl hover:bg-slate-800 dark:hover:bg-blue-500 transition-colors"
                    >
                        Filter
                    </button>
                </form>

                {/* Table */}
                <div className="bg-white dark:bg-[#0F172A] rounded-2xl border border-slate-200/80 dark:border-slate-800/80 overflow-hidden shadow-xs">
                    {suppliers.data.length === 0 ? (
                        <div className="p-12 text-center">
                            <p className="text-sm font-medium text-slate-700 dark:text-slate-300">No suppliers found</p>
                            <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
                                {filters.search || (filters.status && filters.status !== 'all')
                                    ? 'Try adjusting your search or status filter.'
                                    : 'Register your first supplier to begin managing product sourcing.'}
                            </p>
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-left border-collapse">
                                <thead>
                                    <tr className="border-b border-slate-200/80 dark:border-slate-800 text-[11px] uppercase tracking-wider text-slate-500 dark:text-slate-400 bg-slate-50/70 dark:bg-slate-900/50">
                                        <th className="py-3 px-4 font-semibold">Supplier</th>
                                        <th className="py-3 px-4 font-semibold">Contact</th>
                                        <th className="py-3 px-4 font-semibold">Terms &amp; Lead Time</th>
                                        <th className="py-3 px-4 font-semibold text-center">Linked Products</th>
                                        <th className="py-3 px-4 font-semibold text-center">Status</th>
                                        <th className="py-3 px-4 font-semibold text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100 dark:divide-slate-800/80 text-xs">
                                    {suppliers.data.map((supplier) => (
                                        <tr key={supplier.id} className="hover:bg-slate-50/60 dark:hover:bg-slate-900/40 transition-colors">
                                            <td className="py-3.5 px-4">
                                                <Link href={`/admin/suppliers/${supplier.id}`} className="font-semibold text-slate-900 dark:text-white hover:underline">
                                                    {supplier.name}
                                                </Link>
                                                <div className="text-[11px] font-mono text-slate-500 dark:text-slate-400 mt-0.5">{supplier.code}</div>
                                            </td>
                                            <td className="py-3.5 px-4 text-slate-600 dark:text-slate-300">
                                                <div>{supplier.contact_person || '—'}</div>
                                                <div className="text-[11px] text-slate-400">{supplier.phone || supplier.email || 'No contact info'}</div>
                                            </td>
                                            <td className="py-3.5 px-4 text-slate-600 dark:text-slate-300">
                                                <div>{PAYMENT_TERMS_LABELS[supplier.payment_terms] ?? supplier.payment_terms}</div>
                                                <div className="text-[11px] text-slate-400">{supplier.default_lead_time_days} days lead time</div>
                                            </td>
                                            <td className="py-3.5 px-4 text-center font-mono text-slate-700 dark:text-slate-300">
                                                {supplier.products_count}
                                            </td>
                                            <td className="py-3.5 px-4 text-center">
                                                <span className={`inline-flex px-2.5 py-1 rounded-full text-[11px] font-semibold ${
                                                    supplier.status === 'active'
                                                        ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300'
                                                        : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400'
                                                }`}>
                                                    {supplier.status === 'active' ? 'Active' : 'Inactive'}
                                                </span>
                                            </td>
                                            <td className="py-3.5 px-4 text-right space-x-2 whitespace-nowrap">
                                                <Link
                                                    href={`/admin/suppliers/${supplier.id}`}
                                                    className="text-blue-600 dark:text-blue-400 hover:underline font-medium"
                                                >
                                                    View
                                                </Link>
                                                {canManage && (
                                                    <>
                                                        <Link
                                                            href={`/admin/suppliers/${supplier.id}/edit`}
                                                            className="text-slate-600 dark:text-slate-300 hover:underline font-medium"
                                                        >
                                                            Edit
                                                        </Link>
                                                        <button
                                                            type="button"
                                                            disabled={togglingId === supplier.id}
                                                            onClick={() => handleToggleStatus(supplier)}
                                                            className={`font-medium hover:underline disabled:opacity-50 ${
                                                                supplier.status === 'active'
                                                                    ? 'text-amber-700 dark:text-amber-400'
                                                                    : 'text-emerald-700 dark:text-emerald-400'
                                                            }`}
                                                        >
                                                            {togglingId === supplier.id
                                                                ? 'Updating...'
                                                                : supplier.status === 'active'
                                                                    ? 'Deactivate'
                                                                    : 'Activate'}
                                                        </button>
                                                    </>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}

                    {/* Pagination */}
                    {suppliers.links && suppliers.links.length > 3 && (
                        <div className="px-4 py-3 border-t border-slate-200/80 dark:border-slate-800 flex flex-wrap gap-1 justify-end">
                            {suppliers.links.map((link, idx) => (
                                <Link
                                    key={idx}
                                    href={link.url ?? '#'}
                                    preserveScroll
                                    className={`px-3 py-1 rounded-lg text-xs ${
                                        link.active
                                            ? 'bg-slate-900 text-white dark:bg-blue-600'
                                            : link.url
                                                ? 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800'
                                                : 'text-slate-400 pointer-events-none'
                                    }`}
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </AdminLayout>
    );
}

