import AdminLayout from '@/Layouts/AdminLayout';
import { PaginatedData } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import React, { useState } from 'react';

interface PoRow {
    id: number;
    po_number: string;
    supplier_name: string | null;
    supplier_code: string | null;
    status: string;
    items_count: number;
    expected_delivery_date: string | null;
    ordered_at: string | null;
    total_cost: string | null;
}

interface Props {
    purchaseOrders: PaginatedData<PoRow>;
    filters: {
        search?: string;
        status?: string;
    };
    stats: {
        draft: number;
        ordered: number;
        partially_received: number;
        received: number;
    };
    canCreate: boolean;
    canViewCost: boolean;
}

const STATUS_BADGES: Record<string, string> = {
    draft: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
    ordered: 'bg-blue-100 text-blue-800 dark:bg-blue-950/60 dark:text-blue-300',
    partially_received: 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300',
    received: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300',
    cancelled: 'bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300',
};

export default function PurchaseOrdersIndex({ purchaseOrders, filters, stats, canCreate, canViewCost }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [status, setStatus] = useState(filters.status ?? 'all');

    const handleFilter = (e: React.FormEvent) => {
        e.preventDefault();
        router.get('/admin/purchase-orders', {
            search: search || undefined,
            status: status !== 'all' ? status : undefined,
        }, { preserveState: true });
    };

    return (
        <AdminLayout>
            <Head title="Purchase Orders | Aura Luxe" />

            <div className="space-y-6">
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <h1 className="font-serif text-2xl text-slate-900 dark:text-white font-semibold">Purchase Orders</h1>
                        <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
                            Create, approve, and receive supplier purchase orders into warehouse inventory.
                        </p>
                    </div>
                    {canCreate && (
                        <Link
                            href="/admin/purchase-orders/create"
                            className="bg-slate-900 dark:bg-blue-600 text-white text-xs font-semibold px-4 py-2.5 rounded-xl hover:bg-slate-800 dark:hover:bg-blue-500 transition-colors shadow-xs text-center"
                        >
                            + New Purchase Order
                        </Link>
                    )}
                </div>

                {/* Stats */}
                <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <div className="bg-white dark:bg-[#0F172A] p-4 rounded-2xl border border-slate-200/80 dark:border-slate-800/80">
                        <span className="text-[11px] uppercase tracking-wider text-slate-500 font-semibold">Draft</span>
                        <span className="text-2xl font-bold font-mono text-slate-900 dark:text-white block mt-1">{stats.draft}</span>
                    </div>
                    <div className="bg-white dark:bg-[#0F172A] p-4 rounded-2xl border border-blue-200/80 dark:border-blue-900/40">
                        <span className="text-[11px] uppercase tracking-wider text-blue-700 dark:text-blue-400 font-semibold">Ordered</span>
                        <span className="text-2xl font-bold font-mono text-blue-900 dark:text-blue-300 block mt-1">{stats.ordered}</span>
                    </div>
                    <div className="bg-white dark:bg-[#0F172A] p-4 rounded-2xl border border-amber-200/80 dark:border-amber-900/40">
                        <span className="text-[11px] uppercase tracking-wider text-amber-700 dark:text-amber-400 font-semibold">Partially Received</span>
                        <span className="text-2xl font-bold font-mono text-amber-900 dark:text-amber-300 block mt-1">{stats.partially_received}</span>
                    </div>
                    <div className="bg-white dark:bg-[#0F172A] p-4 rounded-2xl border border-emerald-200/80 dark:border-emerald-900/40">
                        <span className="text-[11px] uppercase tracking-wider text-emerald-700 dark:text-emerald-400 font-semibold">Received</span>
                        <span className="text-2xl font-bold font-mono text-emerald-900 dark:text-emerald-300 block mt-1">{stats.received}</span>
                    </div>
                </div>

                {/* Filter */}
                <form onSubmit={handleFilter} className="bg-white dark:bg-[#0F172A] p-4 rounded-2xl border border-slate-200/80 dark:border-slate-800/80 flex flex-col sm:flex-row gap-3">
                    <input
                        type="text"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Search by PO number or supplier name..."
                        className="flex-1 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 px-3.5 py-2 text-xs text-slate-900 dark:text-white"
                    />
                    <select
                        value={status}
                        onChange={(e) => setStatus(e.target.value)}
                        className="rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 px-3.5 py-2 text-xs text-slate-900 dark:text-white"
                    >
                        <option value="all">All Statuses</option>
                        <option value="draft">Draft</option>
                        <option value="ordered">Ordered</option>
                        <option value="partially_received">Partially Received</option>
                        <option value="received">Received</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                    <button type="submit" className="bg-slate-900 dark:bg-blue-600 text-white text-xs font-semibold px-4 py-2 rounded-xl">
                        Filter
                    </button>
                </form>

                {/* Table */}
                <div className="bg-white dark:bg-[#0F172A] rounded-2xl border border-slate-200/80 dark:border-slate-800/80 overflow-hidden shadow-xs">
                    {purchaseOrders.data.length === 0 ? (
                        <div className="p-12 text-center text-xs text-slate-500">
                            No purchase orders found.
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-left border-collapse text-xs">
                                <thead>
                                    <tr className="border-b border-slate-200/80 dark:border-slate-800 text-[11px] uppercase tracking-wider text-slate-500 bg-slate-50/70 dark:bg-slate-900/50">
                                        <th className="py-3 px-4 font-semibold">PO Number</th>
                                        <th className="py-3 px-4 font-semibold">Supplier</th>
                                        <th className="py-3 px-4 font-semibold text-center">Items</th>
                                        <th className="py-3 px-4 font-semibold">Expected Delivery</th>
                                        {canViewCost && <th className="py-3 px-4 font-semibold text-right">Total Cost (LKR)</th>}
                                        <th className="py-3 px-4 font-semibold text-center">Status</th>
                                        <th className="py-3 px-4 font-semibold text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100 dark:divide-slate-800/80">
                                    {purchaseOrders.data.map((po) => (
                                        <tr key={po.id} className="hover:bg-slate-50/60 dark:hover:bg-slate-900/40">
                                            <td className="py-3.5 px-4 font-mono font-semibold text-slate-900 dark:text-white">
                                                <Link href={`/admin/purchase-orders/${po.id}`} className="hover:underline">
                                                    {po.po_number}
                                                </Link>
                                            </td>
                                            <td className="py-3.5 px-4">
                                                <div className="font-medium text-slate-900 dark:text-white">{po.supplier_name}</div>
                                                <div className="text-[11px] font-mono text-slate-400">{po.supplier_code}</div>
                                            </td>
                                            <td className="py-3.5 px-4 text-center font-mono">{po.items_count}</td>
                                            <td className="py-3.5 px-4 text-slate-600 dark:text-slate-300">{po.expected_delivery_date || '—'}</td>
                                            {canViewCost && (
                                                <td className="py-3.5 px-4 text-right font-mono font-semibold text-slate-900 dark:text-white">
                                                    {po.total_cost !== null ? Number(po.total_cost).toLocaleString(undefined, { minimumFractionDigits: 2 }) : '—'}
                                                </td>
                                            )}
                                            <td className="py-3.5 px-4 text-center">
                                                <span className={`inline-flex px-2.5 py-0.5 rounded-full text-[11px] font-semibold capitalize ${STATUS_BADGES[po.status] ?? ''}`}>
                                                    {po.status.replace('_', ' ')}
                                                </span>
                                            </td>
                                            <td className="py-3.5 px-4 text-right">
                                                <Link href={`/admin/purchase-orders/${po.id}`} className="text-blue-600 dark:text-blue-400 hover:underline font-medium">
                                                    View
                                                </Link>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}

                    {purchaseOrders.links && purchaseOrders.links.length > 3 && (
                        <div className="px-4 py-3 border-t border-slate-200/80 dark:border-slate-800 flex flex-wrap gap-1 justify-end">
                            {purchaseOrders.links.map((link, idx) => (
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

