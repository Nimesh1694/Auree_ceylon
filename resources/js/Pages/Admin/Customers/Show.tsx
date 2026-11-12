import AdminLayout from '@/Layouts/AdminLayout';
import { AuditLog, PaginatedData } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import React, { useState } from 'react';

export interface CustomerAddress {
    id: number;
    user_id: number;
    label: string;
    recipient_name: string;
    phone: string;
    alternative_phone: string | null;
    address_line_1: string;
    address_line_2: string | null;
    city: string;
    district: string;
    postal_code: string | null;
    delivery_instructions: string | null;
    is_default: boolean;
    created_at?: string;
}

interface CustomerProfile {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    status: string;
    email_verified_at: string | null;
    created_at: string | null;
}

interface CustomerOrderSummary {
    id: number;
    order_number: string;
    status: string;
    total_amount: number;
    delivery_charge: number;
    payment_method: string;
    placed_at: string | null;
    items_count: number;
}

interface CustomerMetrics {
    total_orders: number;
    completed_orders: number;
    total_spent: number;
    saved_addresses_count: number;
    latest_order_date: string | null;
}

interface Props {
    customer: CustomerProfile;
    addresses: CustomerAddress[];
    orders: PaginatedData<CustomerOrderSummary>;
    filters: {
        search?: string;
        status?: string;
    };
    metrics: CustomerMetrics;
    auditLogs: AuditLog[];
}

export default function CustomerShow({
    customer,
    addresses,
    orders,
    filters,
    metrics,
    auditLogs,
}: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [statusFilter, setStatusFilter] = useState(filters.status ?? '');
    const [showSuspendModal, setShowSuspendModal] = useState(false);
    const [showReactivateModal, setShowReactivateModal] = useState(false);

    // Suspension Form
    const suspendForm = useForm({
        status: 'suspended',
        reason: '',
    });

    // Reactivation Form
    const reactivateForm = useForm({
        status: 'active',
        reason: '',
    });

    const handleFilterOrders = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(
            `/admin/customers/${customer.id}`,
            {
                search: search || undefined,
                status: statusFilter || undefined,
            },
            { preserveState: true }
        );
    };

    const handleClearOrders = () => {
        setSearch('');
        setStatusFilter('');
        router.get(`/admin/customers/${customer.id}`);
    };

    const submitSuspend = (e: React.FormEvent) => {
        e.preventDefault();
        suspendForm.post(`/admin/customers/${customer.id}/status`, {
            preserveScroll: true,
            onSuccess: () => {
                setShowSuspendModal(false);
                suspendForm.reset();
            },
        });
    };

    const submitReactivate = (e: React.FormEvent) => {
        e.preventDefault();
        reactivateForm.post(`/admin/customers/${customer.id}/status`, {
            preserveScroll: true,
            onSuccess: () => {
                setShowReactivateModal(false);
                reactivateForm.reset();
            },
        });
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
            hour: '2-digit',
            minute: '2-digit',
        });
    };

    const getStatusBadge = (status: string) => {
        switch (status) {
            case 'completed':
            case 'delivered':
            case 'cash_collected':
                return 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400 border-emerald-200/60 dark:border-emerald-800/40';
            case 'shipped':
            case 'packed':
            case 'confirmed':
                return 'bg-blue-50 text-blue-700 dark:bg-blue-950/40 dark:text-blue-400 border-blue-200/60 dark:border-blue-800/40';
            case 'cancelled':
            case 'refused':
            case 'returned':
                return 'bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-400 border-rose-200/60 dark:border-rose-800/40';
            default:
                return 'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-400 border-amber-200/60 dark:border-amber-800/40';
        }
    };

    return (
        <AdminLayout>
            <Head title={`Customer: ${customer.name} | Aura Luxe`} />

            <div className="space-y-6">
                {/* Breadcrumbs & Title */}
                <div>
                    <nav className="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400 mb-1">
                        <Link href="/admin/customers" className="hover:text-amber-600 transition-colors">
                            Customers
                        </Link>
                        <span>&gt;</span>
                        <span className="text-slate-800 dark:text-slate-200 font-medium truncate max-w-xs">
                            {customer.name}
                        </span>
                    </nav>
                    <div className="flex flex-wrap items-center justify-between gap-4">
                        <div className="flex items-center gap-3">
                            <div className="w-12 h-12 rounded-2xl bg-amber-100 dark:bg-amber-950/80 border border-amber-300 dark:border-amber-700 text-amber-900 dark:text-amber-200 flex items-center justify-center font-serif font-bold text-lg uppercase">
                                {customer.name.charAt(0)}
                            </div>
                            <div>
                                <h1 className="font-serif text-2xl text-slate-900 dark:text-white font-semibold flex items-center gap-2">
                                    <span>{customer.name}</span>
                                    {customer.status === 'active' ? (
                                        <span className="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400 border border-emerald-200/60 dark:border-emerald-800/40">
                                            <span className="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                            Active Account
                                        </span>
                                    ) : (
                                        <span className="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-400 border border-rose-200/60 dark:border-rose-800/40">
                                            <span className="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                                            Suspended
                                        </span>
                                    )}
                                </h1>
                                <p className="text-xs text-slate-500 dark:text-slate-400">
                                    Customer ID: #{customer.id} &bull; Joined {formatDate(customer.created_at)}
                                </p>
                            </div>
                        </div>

                        {/* Status Action Buttons */}
                        <div className="flex items-center gap-2">
                            {customer.status === 'active' ? (
                                <button
                                    type="button"
                                    onClick={() => setShowSuspendModal(true)}
                                    className="px-4 py-2 rounded-xl text-xs font-semibold bg-rose-50 text-rose-700 hover:bg-rose-100 dark:bg-rose-950/40 dark:text-rose-300 dark:hover:bg-rose-900/40 border border-rose-200 dark:border-rose-800 transition-colors shadow-xs"
                                >
                                    Suspend Customer
                                </button>
                            ) : (
                                <button
                                    type="button"
                                    onClick={() => setShowReactivateModal(true)}
                                    className="px-4 py-2 rounded-xl text-xs font-semibold bg-emerald-50 text-emerald-700 hover:bg-emerald-100 dark:bg-emerald-950/40 dark:text-emerald-300 dark:hover:bg-emerald-900/40 border border-emerald-200 dark:border-emerald-800 transition-colors shadow-xs"
                                >
                                    Reactivate Customer
                                </button>
                            )}
                        </div>
                    </div>
                </div>

                {/* Metrics Grid */}
                <div className="grid grid-cols-2 sm:grid-cols-4 gap-4 text-xs">
                    <div className="bg-white dark:bg-[#0F172A] p-4 rounded-2xl border border-slate-200/80 dark:border-slate-800/80 shadow-xs">
                        <span className="text-slate-400 block text-[11px]">Total Orders</span>
                        <span className="font-serif text-xl font-bold text-slate-900 dark:text-white mt-0.5 block">
                            {metrics.total_orders}
                        </span>
                        <span className="text-[10px] text-slate-500 dark:text-slate-400">
                            {metrics.completed_orders} completed / delivered
                        </span>
                    </div>

                    <div className="bg-white dark:bg-[#0F172A] p-4 rounded-2xl border border-slate-200/80 dark:border-slate-800/80 shadow-xs">
                        <span className="text-slate-400 block text-[11px]">Lifetime Spend</span>
                        <span className="font-serif text-xl font-bold text-slate-900 dark:text-white mt-0.5 block">
                            {formatCurrency(metrics.total_spent)}
                        </span>
                        <span className="text-[10px] text-slate-500 dark:text-slate-400">Completed order revenue</span>
                    </div>

                    <div className="bg-white dark:bg-[#0F172A] p-4 rounded-2xl border border-slate-200/80 dark:border-slate-800/80 shadow-xs">
                        <span className="text-slate-400 block text-[11px]">Saved Addresses</span>
                        <span className="font-serif text-xl font-bold text-slate-900 dark:text-white mt-0.5 block">
                            {metrics.saved_addresses_count} / 10
                        </span>
                        <span className="text-[10px] text-slate-500 dark:text-slate-400">Delivery address book</span>
                    </div>

                    <div className="bg-white dark:bg-[#0F172A] p-4 rounded-2xl border border-slate-200/80 dark:border-slate-800/80 shadow-xs">
                        <span className="text-slate-400 block text-[11px]">Latest Order</span>
                        <span className="font-semibold text-slate-900 dark:text-white mt-0.5 block">
                            {formatDate(metrics.latest_order_date)}
                        </span>
                        <span className="text-[10px] text-slate-500 dark:text-slate-400">Most recent checkout</span>
                    </div>
                </div>

                {/* Main Content Layout: Left 2 Cols (Orders & Audit), Right 1 Col (Contact & Addresses) */}
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
                    {/* Left 2 Cols */}
                    <div className="lg:col-span-2 space-y-6">
                        {/* Customer Orders */}
                        <div className="bg-white dark:bg-[#0F172A] rounded-2xl border border-slate-200/80 dark:border-slate-800/80 shadow-xs overflow-hidden text-xs">
                            <div className="p-4 border-b border-slate-100 dark:border-slate-800/80 flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <h2 className="font-serif text-base font-semibold text-slate-900 dark:text-white">
                                        Order History ({orders.total})
                                    </h2>
                                    <p className="text-[11px] text-slate-500 dark:text-slate-400">
                                        All historical orders placed by this customer account.
                                    </p>
                                </div>

                                {/* Order Search & Filter */}
                                <form onSubmit={handleFilterOrders} className="flex items-center gap-2">
                                    <input
                                        type="text"
                                        placeholder="Order #..."
                                        value={search}
                                        onChange={(e) => setSearch(e.target.value)}
                                        className="rounded-xl border-slate-300 dark:border-slate-700 bg-white dark:bg-[#1E293B] text-slate-900 dark:text-white text-xs px-2.5 py-1 w-32 focus:border-amber-500 focus:ring-amber-500"
                                    />
                                    <select
                                        value={statusFilter}
                                        onChange={(e) => setStatusFilter(e.target.value)}
                                        className="rounded-xl border-slate-300 dark:border-slate-700 bg-white dark:bg-[#1E293B] text-slate-900 dark:text-white text-xs py-1 focus:border-amber-500 focus:ring-amber-500"
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
                                    </select>
                                    <button
                                        type="submit"
                                        className="px-2.5 py-1 rounded-xl bg-slate-900 dark:bg-amber-600 text-white font-medium hover:bg-slate-800 text-xs"
                                    >
                                        Filter
                                    </button>
                                    {(filters.search || filters.status) && (
                                        <button
                                            type="button"
                                            onClick={handleClearOrders}
                                            className="px-2 py-1 rounded-xl border border-slate-300 dark:border-slate-700 text-slate-600 dark:text-slate-300 text-xs"
                                        >
                                            Reset
                                        </button>
                                    )}
                                </form>
                            </div>

                            <div className="overflow-x-auto">
                                <table className="w-full text-left border-collapse">
                                    <thead>
                                        <tr className="border-b border-slate-200/80 dark:border-slate-800/80 bg-slate-50/75 dark:bg-slate-900/40 text-slate-500 dark:text-slate-400 uppercase tracking-wider text-[10px]">
                                            <th className="px-4 py-3 font-semibold">Order #</th>
                                            <th className="px-4 py-3 font-semibold">Placed Date</th>
                                            <th className="px-4 py-3 font-semibold">Status</th>
                                            <th className="px-4 py-3 font-semibold text-center">Items</th>
                                            <th className="px-4 py-3 font-semibold text-right">Total</th>
                                            <th className="px-4 py-3 font-semibold text-right">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100 dark:divide-slate-800/60">
                                        {orders.data.length > 0 ? (
                                            orders.data.map((ord) => (
                                                <tr
                                                    key={ord.id}
                                                    className="hover:bg-slate-50/60 dark:hover:bg-slate-800/30 transition-colors"
                                                >
                                                    <td className="px-4 py-3 font-mono font-semibold text-slate-900 dark:text-white">
                                                        <Link
                                                            href={`/admin/orders/${ord.id}`}
                                                            className="text-amber-700 dark:text-amber-400 hover:underline"
                                                        >
                                                            {ord.order_number}
                                                        </Link>
                                                    </td>
                                                    <td className="px-4 py-3 text-slate-600 dark:text-slate-400">
                                                        {formatDate(ord.placed_at)}
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        <span
                                                            className={`inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold uppercase tracking-wider border ${getStatusBadge(
                                                                ord.status
                                                            )}`}
                                                        >
                                                            {ord.status.replace('_', ' ')}
                                                        </span>
                                                    </td>
                                                    <td className="px-4 py-3 text-center text-slate-700 dark:text-slate-300 font-mono">
                                                        {ord.items_count}
                                                    </td>
                                                    <td className="px-4 py-3 text-right font-mono font-semibold text-slate-900 dark:text-white">
                                                        {formatCurrency(ord.total_amount)}
                                                    </td>
                                                    <td className="px-4 py-3 text-right">
                                                        <Link
                                                            href={`/admin/orders/${ord.id}`}
                                                            className="text-amber-600 hover:underline font-medium text-xs"
                                                        >
                                                            Inspect &rarr;
                                                        </Link>
                                                    </td>
                                                </tr>
                                            ))
                                        ) : (
                                            <tr>
                                                <td colSpan={6} className="py-8 text-center text-slate-400">
                                                    No orders found for this customer.
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>

                            {/* Pagination */}
                            {orders.links && orders.links.length > 3 && (
                                <div className="p-3 border-t border-slate-100 dark:border-slate-800/80 flex items-center justify-between text-xs">
                                    <span className="text-slate-500">
                                        Showing {orders.from ?? 0} to {orders.to ?? 0} of {orders.total}
                                    </span>
                                    <div className="flex gap-1">
                                        {orders.links.map((link, i) => (
                                            <Link
                                                key={i}
                                                href={link.url ?? '#'}
                                                preserveScroll
                                                dangerouslySetInnerHTML={{ __html: link.label }}
                                                className={`px-2.5 py-1 rounded-lg border text-xs ${
                                                    link.active
                                                        ? 'bg-amber-600 border-amber-600 text-white font-semibold'
                                                        : link.url
                                                        ? 'border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300'
                                                        : 'border-transparent text-slate-300 dark:text-slate-600 cursor-not-allowed'
                                                }`}
                                            />
                                        ))}
                                    </div>
                                </div>
                            )}
                        </div>

                        {/* Audit Log Activity */}
                        <div className="bg-white dark:bg-[#0F172A] p-5 rounded-2xl border border-slate-200/80 dark:border-slate-800/80 shadow-xs text-xs">
                            <h2 className="font-serif text-base font-semibold text-slate-900 dark:text-white mb-3">
                                Administrative Audit History
                            </h2>
                            {auditLogs.length > 0 ? (
                                <div className="divide-y divide-slate-100 dark:divide-slate-800/60">
                                    {auditLogs.map((log) => (
                                        <div key={log.id} className="py-2.5 flex items-start justify-between gap-4">
                                            <div>
                                                <span className="font-semibold text-slate-800 dark:text-slate-200 capitalize">
                                                    {log.action.replace('_', ' ')}
                                                </span>
                                                <div className="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">
                                                    By: {log.user?.name ?? 'System'} ({log.user?.role ?? 'admin'}) &bull; IP: {log.ip_address ?? '—'}
                                                </div>
                                                {Boolean(log.new_values && (log.new_values as Record<string, unknown>).reason) && (
                                                    <p className="text-[11px] text-slate-600 dark:text-slate-400 italic mt-0.5 bg-slate-50 dark:bg-slate-900/60 p-1.5 rounded-lg border border-slate-100 dark:border-slate-800">
                                                        Reason: {String((log.new_values as Record<string, unknown>).reason)}
                                                    </p>
                                                )}
                                            </div>
                                            <span className="text-[11px] text-slate-400 whitespace-nowrap">
                                                {formatDate(log.created_at)}
                                            </span>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <p className="text-slate-400 italic">No administrative events recorded for this customer.</p>
                            )}
                        </div>
                    </div>

                    {/* Right 1 Col: Contact Profile & Saved Addresses */}
                    <div className="space-y-6">
                        {/* Contact Information */}
                        <div className="bg-white dark:bg-[#0F172A] p-5 rounded-2xl border border-slate-200/80 dark:border-slate-800/80 shadow-xs text-xs space-y-3">
                            <h2 className="font-serif text-base font-semibold text-slate-900 dark:text-white border-b border-slate-100 dark:border-slate-800/80 pb-2">
                                Customer Contact
                            </h2>
                            <div>
                                <span className="text-slate-400 block text-[11px]">Primary Email</span>
                                <span className="font-semibold text-slate-900 dark:text-white block mt-0.5">
                                    {customer.email}
                                </span>
                                {customer.email_verified_at ? (
                                    <span className="text-emerald-600 dark:text-emerald-400 text-[10px] block font-medium">
                                        ✓ Email Verified
                                    </span>
                                ) : (
                                    <span className="text-amber-600 dark:text-amber-400 text-[10px] block font-medium">
                                        Email Unverified
                                    </span>
                                )}
                            </div>
                            <div>
                                <span className="text-slate-400 block text-[11px]">Phone Number</span>
                                <span className="font-mono font-semibold text-slate-900 dark:text-white block mt-0.5">
                                    {customer.phone || 'No phone recorded'}
                                </span>
                            </div>
                            <div>
                                <span className="text-slate-400 block text-[11px]">Account Status</span>
                                <span className="font-medium text-slate-800 dark:text-slate-200 capitalize mt-0.5 block">
                                    {customer.status}
                                </span>
                            </div>
                        </div>

                        {/* Saved Delivery Addresses */}
                        <div className="bg-white dark:bg-[#0F172A] p-5 rounded-2xl border border-slate-200/80 dark:border-slate-800/80 shadow-xs text-xs space-y-4">
                            <div className="border-b border-slate-100 dark:border-slate-800/80 pb-2 flex items-center justify-between">
                                <h2 className="font-serif text-base font-semibold text-slate-900 dark:text-white">
                                    Saved Addresses ({addresses.length})
                                </h2>
                                <span className="text-[10px] text-slate-400 font-mono">Max 10</span>
                            </div>

                            {addresses.length > 0 ? (
                                <div className="space-y-3">
                                    {addresses.map((addr) => (
                                        <div
                                            key={addr.id}
                                            className={`p-3.5 rounded-xl border transition-colors ${
                                                addr.is_default
                                                    ? 'bg-amber-50/50 dark:bg-amber-950/20 border-amber-300/80 dark:border-amber-700/60'
                                                    : 'bg-slate-50/50 dark:bg-slate-900/40 border-slate-200/70 dark:border-slate-800/70'
                                            }`}
                                        >
                                            <div className="flex items-center justify-between mb-1.5">
                                                <span className="font-semibold text-slate-900 dark:text-white">
                                                    {addr.label}
                                                </span>
                                                {addr.is_default && (
                                                    <span className="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-200 text-amber-900 dark:bg-amber-800 dark:text-amber-100">
                                                        DEFAULT
                                                    </span>
                                                )}
                                            </div>
                                            <div className="text-slate-700 dark:text-slate-300 space-y-0.5">
                                                <p className="font-medium">{addr.recipient_name}</p>
                                                <p className="font-mono text-[11px] text-slate-500 dark:text-slate-400">
                                                    {addr.phone} {addr.alternative_phone && `/ ${addr.alternative_phone}`}
                                                </p>
                                                <p className="mt-1">{addr.address_line_1}</p>
                                                {addr.address_line_2 && <p>{addr.address_line_2}</p>}
                                                <p className="font-medium text-slate-800 dark:text-slate-200">
                                                    {addr.city}, {addr.district} {addr.postal_code && `(${addr.postal_code})`}
                                                </p>
                                                {addr.delivery_instructions && (
                                                    <p className="text-[11px] text-slate-500 italic mt-1 bg-white/60 dark:bg-black/20 p-1.5 rounded">
                                                        Note: {addr.delivery_instructions}
                                                    </p>
                                                )}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <p className="text-slate-400 italic text-center py-4">
                                    No delivery addresses saved in address book.
                                </p>
                            )}
                        </div>
                    </div>
                </div>
            </div>

            {/* Suspend Confirmation Modal */}
            {showSuspendModal && (
                <div className="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4">
                    <div className="bg-white dark:bg-[#0F172A] rounded-2xl max-w-md w-full p-6 border border-slate-200 dark:border-slate-800 shadow-2xl space-y-4 text-xs">
                        <div className="flex items-center gap-3 text-rose-600 dark:text-rose-400">
                            <div className="w-10 h-10 rounded-full bg-rose-100 dark:bg-rose-950/60 flex items-center justify-center shrink-0">
                                <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        strokeWidth={2}
                                        d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
                                    />
                                </svg>
                            </div>
                            <div>
                                <h3 className="font-serif text-base font-semibold text-slate-900 dark:text-white">
                                    Suspend Customer Account
                                </h3>
                                <p className="text-slate-500 text-[11px]">
                                    Customer: {customer.name} (#{customer.id})
                                </p>
                            </div>
                        </div>

                        <p className="text-slate-600 dark:text-slate-400">
                            Suspending this customer will <strong>immediately terminate all their active sessions</strong>. They will be strictly blocked from logging in, viewing their portal, and placing orders.
                        </p>

                        <form onSubmit={submitSuspend} className="space-y-4">
                            <div>
                                <label className="block font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                    Suspension Reason (Mandatory)
                                </label>
                                <textarea
                                    rows={3}
                                    placeholder="e.g. Fraudulent COD refusal history / Policy violation..."
                                    value={suspendForm.data.reason}
                                    onChange={(e) => suspendForm.setData('reason', e.target.value)}
                                    className="w-full rounded-xl border-slate-300 dark:border-slate-700 bg-white dark:bg-[#1E293B] text-slate-900 dark:text-white text-xs p-2.5 focus:border-rose-500 focus:ring-rose-500"
                                    required
                                />
                                {suspendForm.errors.reason && (
                                    <span className="text-rose-600 text-[11px] block mt-1">
                                        {suspendForm.errors.reason}
                                    </span>
                                )}
                            </div>

                            <div className="flex justify-end gap-2 pt-2">
                                <button
                                    type="button"
                                    onClick={() => setShowSuspendModal(false)}
                                    className="px-4 py-2 rounded-xl border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={suspendForm.processing}
                                    className="px-4 py-2 rounded-xl bg-rose-600 hover:bg-rose-700 text-white font-semibold shadow-xs disabled:opacity-50 transition-colors"
                                >
                                    {suspendForm.processing ? 'Suspending...' : 'Confirm Suspension'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Reactivate Confirmation Modal */}
            {showReactivateModal && (
                <div className="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4">
                    <div className="bg-white dark:bg-[#0F172A] rounded-2xl max-w-md w-full p-6 border border-slate-200 dark:border-slate-800 shadow-2xl space-y-4 text-xs">
                        <div className="flex items-center gap-3 text-emerald-600 dark:text-emerald-400">
                            <div className="w-10 h-10 rounded-full bg-emerald-100 dark:bg-emerald-950/60 flex items-center justify-center shrink-0">
                                <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        strokeWidth={2}
                                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
                                    />
                                </svg>
                            </div>
                            <div>
                                <h3 className="font-serif text-base font-semibold text-slate-900 dark:text-white">
                                    Reactivate Customer Account
                                </h3>
                                <p className="text-slate-500 text-[11px]">
                                    Customer: {customer.name} (#{customer.id})
                                </p>
                            </div>
                        </div>

                        <p className="text-slate-600 dark:text-slate-400">
                            Reactivating this customer will restore their ability to log in, view their saved addresses, and place new orders. Terminated sessions will <strong>not</strong> be automatically restored; the customer will be required to authenticate freshly.
                        </p>

                        <form onSubmit={submitReactivate} className="space-y-4">
                            <div>
                                <label className="block font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                    Reactivation Notes (Optional)
                                </label>
                                <textarea
                                    rows={2}
                                    placeholder="e.g. Identity verified / Dispute resolved..."
                                    value={reactivateForm.data.reason}
                                    onChange={(e) => reactivateForm.setData('reason', e.target.value)}
                                    className="w-full rounded-xl border-slate-300 dark:border-slate-700 bg-white dark:bg-[#1E293B] text-slate-900 dark:text-white text-xs p-2.5 focus:border-emerald-500 focus:ring-emerald-500"
                                />
                            </div>

                            <div className="flex justify-end gap-2 pt-2">
                                <button
                                    type="button"
                                    onClick={() => setShowReactivateModal(false)}
                                    className="px-4 py-2 rounded-xl border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={reactivateForm.processing}
                                    className="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-semibold shadow-xs disabled:opacity-50 transition-colors"
                                >
                                    {reactivateForm.processing ? 'Reactivating...' : 'Confirm Reactivation'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AdminLayout>
    );
}
