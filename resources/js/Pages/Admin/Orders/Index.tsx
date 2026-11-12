import AdminLayout from '@/Layouts/AdminLayout';
import { Order, PaginatedData } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import React, { useState } from 'react';

interface Props {
    orders: PaginatedData<Order>;
    filters: {
        search?: string;
        status?: string;
        district?: string;
        date_from?: string;
        date_to?: string;
    };
}

export default function OrderIndex({ orders, filters }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [status, setStatus] = useState(filters.status ?? '');
    const [district, setDistrict] = useState(filters.district ?? '');
    const [dateFrom, setDateFrom] = useState(filters.date_from ?? '');
    const [dateTo, setDateTo] = useState(filters.date_to ?? '');

    const handleFilter = (e: React.FormEvent) => {
        e.preventDefault();
        router.get('/admin/orders', {
            search: search || undefined,
            status: status || undefined,
            district: district || undefined,
            date_from: dateFrom || undefined,
            date_to: dateTo || undefined,
        }, { preserveState: true });
    };

    const handleClear = () => {
        setSearch('');
        setStatus('');
        setDistrict('');
        setDateFrom('');
        setDateTo('');
        router.get('/admin/orders');
    };

    return (
        <AdminLayout>
            <Head title="Order & COD Administration | Aura Luxe" />

            <div className="space-y-6">
                <div>
                    <h1 className="font-serif text-2xl text-slate-900 dark:text-white font-semibold">Orders & Cash on Delivery (COD)</h1>
                    <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">Manage order fulfillment lifecycles, assign couriers, and reconcile physical cash collections.</p>
                </div>

                {/* Filter Toolbar */}
                <form onSubmit={handleFilter} className="bg-white dark:bg-[#0F172A] p-4 rounded-2xl border border-slate-200/80 dark:border-slate-800/80 shadow-xs flex flex-wrap items-center gap-3 text-xs">
                    <input
                        type="text"
                        placeholder="Search order #, customer, phone..."
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        className="rounded-xl border-slate-300 dark:border-slate-700 bg-white dark:bg-[#1E293B] text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 text-xs px-3 py-1.5 w-60 focus:border-blue-500 focus:ring-blue-500"
                    />

                    <select
                        value={status}
                        onChange={(e) => setStatus(e.target.value)}
                        className="rounded-xl border-slate-300 dark:border-slate-700 bg-white dark:bg-[#1E293B] text-slate-900 dark:text-white text-xs py-1.5 focus:border-blue-500 focus:ring-blue-500"
                    >
                        <option value="">All Statuses</option>
                        <option value="placed">Placed</option>
                        <option value="confirmed">Confirmed</option>
                        <option value="packed">Packed</option>
                        <option value="shipped">Shipped</option>
                        <option value="delivered">Delivered</option>
                        <option value="cash_collected">Cash Collected</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                        <option value="refused">Refused</option>
                        <option value="returned">Returned</option>
                    </select>

                    <input
                        type="date"
                        value={dateFrom}
                        onChange={(e) => setDateFrom(e.target.value)}
                        className="rounded-xl border-slate-300 dark:border-slate-700 bg-white dark:bg-[#1E293B] text-slate-900 dark:text-white text-xs py-1.5 focus:border-blue-500 focus:ring-blue-500"
                        title="From Date"
                    />

                    <input
                        type="date"
                        value={dateTo}
                        onChange={(e) => setDateTo(e.target.value)}
                        className="rounded-xl border-slate-300 dark:border-slate-700 bg-white dark:bg-[#1E293B] text-slate-900 dark:text-white text-xs py-1.5 focus:border-blue-500 focus:ring-blue-500"
                        title="To Date"
                    />

                    <div className="flex items-center gap-2 ml-auto">
                        <button type="submit" className="bg-slate-900 dark:bg-blue-600 text-white px-3.5 py-1.5 rounded-xl font-semibold hover:bg-slate-800 dark:hover:bg-blue-500 transition-colors cursor-pointer">
                            Filter
                        </button>
                        <button type="button" onClick={handleClear} className="text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-white px-2 py-1.5 transition-colors cursor-pointer">
                            Reset
                        </button>
                    </div>
                </form>

                {/* Orders Table */}
                <div className="bg-white dark:bg-[#0F172A] rounded-2xl border border-slate-200/80 dark:border-slate-800/80 shadow-xs overflow-hidden">
                    <table className="w-full text-left text-xs divide-y divide-slate-200 dark:divide-slate-800">
                        <thead className="bg-slate-50 dark:bg-[#1E293B]/70 text-slate-600 dark:text-slate-400 font-semibold uppercase text-[10px] tracking-wider">
                            <tr>
                                <th className="px-6 py-3.5">Order Number</th>
                                <th className="px-6 py-3.5">Customer & Phone</th>
                                <th className="px-6 py-3.5">District & City</th>
                                <th className="px-6 py-3.5">Total Amount</th>
                                <th className="px-6 py-3.5">Status</th>
                                <th className="px-6 py-3.5">COD Reconciliation</th>
                                <th className="px-6 py-3.5">Date Placed</th>
                                <th className="px-6 py-3.5 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100 dark:divide-slate-800/60">
                            {orders.data.length === 0 ? (
                                <tr>
                                    <td colSpan={8} className="px-6 py-8 text-center text-slate-400 dark:text-slate-500">
                                        No orders found matching filters.
                                    </td>
                                </tr>
                            ) : (
                                orders.data.map((ord) => (
                                    <tr key={ord.id} className="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition-colors">
                                        <td className="px-6 py-4 font-mono font-semibold text-rose-700 dark:text-rose-400">
                                            <Link href={`/admin/orders/${ord.id}`} className="hover:underline">
                                                {ord.order_number}
                                            </Link>
                                        </td>
                                        <td className="px-6 py-4">
                                            <span className="font-semibold text-slate-900 dark:text-white block">{ord.customer_name}</span>
                                            <span className="text-slate-500 dark:text-slate-400 font-mono text-[11px] block">{ord.customer_phone}</span>
                                        </td>
                                        <td className="px-6 py-4 text-slate-600 dark:text-slate-300">
                                            {ord.city}, <span className="font-medium">{ord.district}</span>
                                        </td>
                                        <td className="px-6 py-4 font-mono font-semibold text-slate-900 dark:text-white">
                                            LKR {Number(ord.total_amount).toLocaleString('en-US', { minimumFractionDigits: 2 })}
                                        </td>
                                        <td className="px-6 py-4">
                                            <span className={`inline-block px-2.5 py-0.5 rounded-full text-[10px] font-semibold uppercase ${
                                                ord.status === 'completed'
                                                    ? 'bg-rose-100 dark:bg-rose-950/60 text-rose-900 dark:text-rose-300 border border-rose-200 dark:border-rose-900/60'
                                                    : ord.status === 'cash_collected'
                                                    ? 'bg-emerald-100 dark:bg-emerald-950/60 text-emerald-800 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-900/60'
                                                    : ord.status === 'shipped' || ord.status === 'delivered'
                                                    ? 'bg-sky-100 dark:bg-sky-950/60 text-sky-800 dark:text-sky-300 border border-sky-200 dark:border-sky-900/60'
                                                    : ord.status === 'cancelled' || ord.status === 'refused' || ord.status === 'returned'
                                                    ? 'bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300'
                                                    : 'bg-amber-100 dark:bg-amber-950/60 text-amber-800 dark:text-amber-300 border border-amber-200 dark:border-amber-900/60'
                                            }`}>
                                                {ord.status.replace('_', ' ')}
                                            </span>
                                            {ord.delivery?.courier_name && (
                                                <div className="text-[10px] text-slate-500 dark:text-slate-400 mt-1 flex items-center gap-1 font-mono">
                                                    <span>{ord.delivery.courier_name}</span>
                                                    {ord.delivery.actual_courier_fee !== null && ord.delivery.actual_courier_fee !== undefined ? (
                                                        <span className="text-emerald-600 dark:text-emerald-400 font-semibold" title="Actual Courier Fee Recorded">
                                                            &bull; Fee: LKR {Number(ord.delivery.actual_courier_fee).toFixed(0)}
                                                        </span>
                                                    ) : (
                                                        <span className="text-amber-600 dark:text-amber-400 font-semibold" title="Courier fee unrecorded">
                                                            &bull; Fee: Unrecorded
                                                        </span>
                                                    )}
                                                </div>
                                            )}
                                        </td>
                                        <td className="px-6 py-4">
                                            {ord.cod_collection ? (
                                                <span className={`inline-block px-2 py-0.5 rounded text-[10px] font-semibold uppercase ${
                                                    ord.cod_collection.reconciliation_status === 'matched'
                                                        ? 'bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800'
                                                        : ord.cod_collection.reconciliation_status === 'pending'
                                                        ? 'bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400'
                                                        : 'bg-rose-50 dark:bg-rose-950/50 text-rose-700 dark:text-rose-400 border border-rose-200 dark:border-rose-800'
                                                }`}>
                                                    {ord.cod_collection.reconciliation_status.replace('_', ' ')}
                                                </span>
                                            ) : (
                                                <span className="text-slate-400 dark:text-slate-600">—</span>
                                            )}
                                        </td>
                                        <td className="px-6 py-4 text-slate-500 dark:text-slate-400 text-[11px] whitespace-nowrap">
                                            {new Date(ord.placed_at).toLocaleDateString()}
                                        </td>
                                        <td className="px-6 py-4 text-right">
                                            <Link
                                                href={`/admin/orders/${ord.id}`}
                                                className="bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 px-3 py-1.5 rounded-xl font-semibold inline-block transition-colors"
                                            >
                                                Manage &rarr;
                                            </Link>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>

                    {/* Pagination */}
                    {orders.last_page > 1 && (
                        <div className="px-6 py-3.5 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
                            <span>Showing page {orders.current_page} of {orders.last_page}</span>
                            <div className="flex gap-1">
                                {orders.links.map((link, idx) => (
                                    <Link
                                        key={idx}
                                        href={link.url ?? '#'}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                        className={`px-3 py-1 rounded-lg transition-colors ${
                                            link.active
                                                ? 'bg-slate-900 dark:bg-blue-600 text-white font-semibold'
                                                : link.url
                                                ? 'hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300'
                                                : 'text-slate-300 dark:text-slate-600 cursor-not-allowed'
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
