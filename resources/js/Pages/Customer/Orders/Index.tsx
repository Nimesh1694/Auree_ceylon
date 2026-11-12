import React, { useState } from 'react';
import PublicLayout from '@/Layouts/PublicLayout';
import { Head, Link, router } from '@inertiajs/react';
import { PageProps } from '@/types';
import * as Icons from '@/Components/Icons';

export interface CustomerOrderSummary {
    id: number;
    order_number: string;
    status: string;
    total_amount: number;
    total_amount_formatted: string;
    items_count: number;
    first_item_name: string;
    placed_at: string;
    tracking_token: string;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedOrders {
    data: CustomerOrderSummary[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
}

interface Props extends PageProps {
    orders: PaginatedOrders;
    filters: {
        search: string;
        status: string;
    };
    customer: {
        name: string;
        email: string;
    };
}

export default function CustomerOrdersIndex({ orders, filters, customer }: Props) {
    const [search, setSearch] = useState(filters.search || '');

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(
            '/customer/orders',
            {
                search: search.trim(),
                status: filters.status !== 'all' ? filters.status : undefined,
            },
            {
                preserveState: true,
                replace: true,
            }
        );
    };

    const handleStatusFilter = (newStatus: string) => {
        router.get(
            '/customer/orders',
            {
                search: search.trim() || undefined,
                status: newStatus !== 'all' ? newStatus : undefined,
            },
            {
                preserveState: true,
                replace: true,
            }
        );
    };

    const getStatusBadge = (status: string) => {
        switch (status) {
            case 'placed':
                return { label: 'Order Placed', color: 'bg-blue-50 text-blue-700 border-blue-200' };
            case 'confirmed':
                return { label: 'Confirmed', color: 'bg-indigo-50 text-indigo-700 border-indigo-200' };
            case 'packed':
                return { label: 'Packed & Ready', color: 'bg-amber-50 text-amber-700 border-amber-200' };
            case 'shipped':
                return { label: 'In Transit', color: 'bg-purple-50 text-purple-700 border-purple-200' };
            case 'delivered':
            case 'cash_collected':
            case 'completed':
                return { label: 'Delivered', color: 'bg-emerald-50 text-emerald-700 border-emerald-200' };
            case 'cancelled':
                return { label: 'Cancelled', color: 'bg-rose-50 text-rose-700 border-rose-200' };
            case 'refused':
            case 'returned':
                return { label: 'Returned', color: 'bg-stone-100 text-stone-700 border-stone-200' };
            default:
                return { label: status, color: 'bg-stone-50 text-stone-600 border-stone-200' };
        }
    };

    return (
        <PublicLayout>
            <Head title="My Orders — Aura Luxe" />

            <div className="min-h-screen bg-stone-50/60 pb-16 pt-8 font-sans">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">
                    {/* Header Banner with Navigation */}
                    <div className="bg-white border border-stone-200/80 rounded-2xl p-6 sm:p-8 shadow-xs">
                        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-6">
                            <div>
                                <div className="flex items-center gap-2.5">
                                    <h1 className="font-serif text-2xl sm:text-3xl font-medium tracking-tight text-stone-900">
                                        My Order History
                                    </h1>
                                    <span className="inline-flex items-center gap-1 text-[11px] font-semibold tracking-wide uppercase px-2.5 py-0.5 rounded-full bg-amber-50 text-amber-800 border border-amber-200/60 font-mono">
                                        {orders.total} {orders.total === 1 ? 'Order' : 'Orders'}
                                    </span>
                                </div>
                                <p className="text-xs sm:text-sm text-stone-500 mt-1 font-light">
                                    View and manage all purchases placed under your Aura Luxe account
                                </p>
                            </div>

                            <div className="flex items-center gap-3">
                                <Link
                                    href="/products"
                                    className="inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-stone-900 hover:bg-stone-800 text-white text-xs font-semibold uppercase tracking-wider rounded-xl transition-all shadow-xs cursor-pointer"
                                >
                                    <Icons.ShoppingCart className="w-4 h-4 text-amber-300" />
                                    <span>Continue Shopping</span>
                                </Link>
                            </div>
                        </div>

                        {/* Customer Navigation Tabs */}
                        <div className="mt-8 pt-6 border-t border-stone-100 flex items-center gap-2 overflow-x-auto pb-1 custom-scrollbar">
                            <Link
                                href="/dashboard"
                                className="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-medium text-stone-600 hover:text-stone-900 hover:bg-stone-100 transition-colors cursor-pointer shrink-0"
                            >
                                <Icons.LayoutDashboard className="w-3.5 h-3.5 text-stone-500" />
                                <span>Overview</span>
                            </Link>

                            <Link
                                href="/customer/orders"
                                className="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-semibold bg-stone-900 text-white shadow-xs cursor-pointer shrink-0"
                            >
                                <Icons.Boxes className="w-3.5 h-3.5 text-amber-300" />
                                <span>My Orders</span>
                            </Link>

                            <Link
                                href="/track"
                                className="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-medium text-stone-600 hover:text-stone-900 hover:bg-stone-100 transition-colors cursor-pointer shrink-0"
                            >
                                <Icons.Truck className="w-3.5 h-3.5 text-stone-500" />
                                <span>Track Order</span>
                            </Link>

                            <Link
                                href="/profile"
                                className="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-medium text-stone-600 hover:text-stone-900 hover:bg-stone-100 transition-colors cursor-pointer shrink-0"
                            >
                                <Icons.User className="w-3.5 h-3.5 text-stone-500" />
                                <span>Account Profile</span>
                            </Link>

                            <Link
                                href="/customer/addresses"
                                className="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-medium text-stone-600 hover:text-stone-900 hover:bg-stone-100 transition-colors cursor-pointer shrink-0"
                            >
                                <Icons.MapPin className="w-3.5 h-3.5 text-stone-500" />
                                <span>Address Book</span>
                            </Link>
                        </div>
                    </div>

                    {/* Filter and Search Bar */}
                    <div className="bg-white border border-stone-200/80 rounded-2xl p-4 sm:p-5 shadow-xs flex flex-col md:flex-row md:items-center justify-between gap-4">
                        {/* Status Filter Buttons */}
                        <div className="flex items-center gap-1.5 overflow-x-auto pb-1 md:pb-0 custom-scrollbar">
                            {[
                                { key: 'all', label: 'All Orders' },
                                { key: 'pending', label: 'In Progress' },
                                { key: 'delivered', label: 'Delivered' },
                                { key: 'cancelled', label: 'Cancelled' },
                            ].map((tab) => {
                                const isActive = (filters.status || 'all') === tab.key;
                                return (
                                    <button
                                        key={tab.key}
                                        type="button"
                                        onClick={() => handleStatusFilter(tab.key)}
                                        className={`px-3.5 py-1.5 rounded-xl text-xs font-medium transition-all shrink-0 cursor-pointer ${
                                            isActive
                                                ? 'bg-stone-900 text-white shadow-xs font-semibold'
                                                : 'text-stone-600 hover:text-stone-900 hover:bg-stone-100'
                                        }`}
                                    >
                                        {tab.label}
                                    </button>
                                );
                            })}
                        </div>

                        {/* Search Input */}
                        <form onSubmit={handleSearch} className="flex items-center gap-2 w-full md:w-auto">
                            <div className="relative w-full md:w-72">
                                <Icons.Search className="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-stone-400" />
                                <input
                                    type="text"
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    placeholder="Search by order reference..."
                                    className="w-full pl-9 pr-3 py-2 bg-stone-50 border border-stone-200 rounded-xl text-xs text-stone-800 placeholder-stone-400 focus:outline-hidden focus:ring-1 focus:ring-stone-800 focus:bg-white transition-all"
                                />
                                {search && (
                                    <button
                                        type="button"
                                        onClick={() => {
                                            setSearch('');
                                            router.get(
                                                '/customer/orders',
                                                { status: filters.status !== 'all' ? filters.status : undefined },
                                                { preserveState: true, replace: true }
                                            );
                                        }}
                                        className="absolute right-2.5 top-1/2 -translate-y-1/2 text-stone-400 hover:text-stone-600"
                                    >
                                        <Icons.XCircle className="w-3.5 h-3.5" />
                                    </button>
                                )}
                            </div>
                            <button
                                type="submit"
                                className="px-3.5 py-2 bg-stone-100 hover:bg-stone-200 text-stone-800 text-xs font-semibold rounded-xl transition-colors cursor-pointer shrink-0"
                            >
                                Search
                            </button>
                        </form>
                    </div>

                    {/* Orders Content */}
                    <div className="bg-white border border-stone-200/80 rounded-2xl shadow-xs overflow-hidden">
                        {orders.data.length === 0 ? (
                            <div className="p-12 text-center">
                                <div className="w-12 h-12 rounded-full bg-stone-100 text-stone-400 flex items-center justify-center mx-auto mb-4">
                                    <Icons.Boxes className="w-6 h-6" />
                                </div>
                                <h3 className="font-serif text-base font-semibold text-stone-900">
                                    {filters.search || (filters.status && filters.status !== 'all')
                                        ? 'No matching orders found'
                                        : 'No orders placed yet'}
                                </h3>
                                <p className="text-xs text-stone-500 max-w-sm mx-auto mt-1 mb-6">
                                    {filters.search || (filters.status && filters.status !== 'all')
                                        ? 'Try adjusting your search criteria or resetting the status filter.'
                                        : 'When you place an order with Aura Luxe, it will appear here so you can review items and monitor dispatch.'}
                                </p>
                                {filters.search || (filters.status && filters.status !== 'all') ? (
                                    <button
                                        type="button"
                                        onClick={() => {
                                            setSearch('');
                                            router.get('/customer/orders', {}, { replace: true });
                                        }}
                                        className="inline-flex items-center gap-1.5 px-4 py-2 bg-stone-100 hover:bg-stone-200 text-stone-800 text-xs font-semibold rounded-xl transition-all cursor-pointer"
                                    >
                                        <Icons.RotateCcw className="w-3.5 h-3.5" />
                                        <span>Clear All Filters</span>
                                    </button>
                                ) : (
                                    <Link
                                        href="/products"
                                        className="inline-flex items-center gap-2 px-5 py-2.5 bg-stone-900 hover:bg-stone-800 text-white text-xs font-semibold uppercase tracking-wider rounded-xl transition-all shadow-xs cursor-pointer"
                                    >
                                        <Icons.Sparkles className="w-3.5 h-3.5 text-amber-300" />
                                        <span>Explore Cosmetic Collection</span>
                                    </Link>
                                )}
                            </div>
                        ) : (
                            <>
                                {/* Desktop Table */}
                                <div className="overflow-x-auto">
                                    <table className="w-full text-left text-xs text-stone-600">
                                        <thead className="bg-stone-50/80 text-[11px] uppercase tracking-wider text-stone-500 border-b border-stone-100 font-mono">
                                            <tr>
                                                <th className="px-6 py-3.5 font-semibold">Order Reference</th>
                                                <th className="px-6 py-3.5 font-semibold">Date Placed</th>
                                                <th className="px-6 py-3.5 font-semibold">Items</th>
                                                <th className="px-6 py-3.5 font-semibold">Total (LKR)</th>
                                                <th className="px-6 py-3.5 font-semibold">Status</th>
                                                <th className="px-6 py-3.5 font-semibold text-right">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-stone-100">
                                            {orders.data.map((ord) => {
                                                const badge = getStatusBadge(ord.status);
                                                return (
                                                    <tr key={ord.id} className="hover:bg-stone-50/50 transition-colors">
                                                        <td className="px-6 py-4 font-mono font-semibold text-stone-900">
                                                            <Link
                                                                href={`/customer/orders/${ord.order_number}`}
                                                                className="hover:text-amber-800 hover:underline"
                                                            >
                                                                #{ord.order_number}
                                                            </Link>
                                                        </td>
                                                        <td className="px-6 py-4 text-stone-500">
                                                            {ord.placed_at}
                                                        </td>
                                                        <td className="px-6 py-4 text-stone-700">
                                                            <div className="truncate max-w-[220px]" title={ord.first_item_name}>
                                                                {ord.first_item_name}
                                                            </div>
                                                            <span className="text-[10px] text-stone-400">
                                                                {ord.items_count} {ord.items_count === 1 ? 'item' : 'items'}
                                                            </span>
                                                        </td>
                                                        <td className="px-6 py-4 font-semibold text-stone-900">
                                                            {ord.total_amount_formatted}
                                                        </td>
                                                        <td className="px-6 py-4">
                                                            <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-medium border ${badge.color}`}>
                                                                {badge.label}
                                                            </span>
                                                        </td>
                                                        <td className="px-6 py-4 text-right">
                                                            <div className="flex items-center justify-end gap-2.5">
                                                                <Link
                                                                    href={`/customer/orders/${ord.order_number}`}
                                                                    className="inline-flex items-center gap-1 text-xs font-semibold text-stone-900 hover:text-amber-800 hover:underline cursor-pointer"
                                                                >
                                                                    <span>Details</span>
                                                                    <Icons.ChevronRight className="w-3.5 h-3.5" />
                                                                </Link>
                                                                <Link
                                                                    href="/track"
                                                                    className="inline-flex items-center gap-1 text-xs text-stone-400 hover:text-stone-700 cursor-pointer"
                                                                    title="Track order"
                                                                >
                                                                    <Icons.Truck className="w-3.5 h-3.5" />
                                                                </Link>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                );
                                            })}
                                        </tbody>
                                    </table>
                                </div>

                                {/* Pagination Footer */}
                                {orders.links.length > 3 && (
                                    <div className="p-4 sm:p-5 border-t border-stone-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 text-xs text-stone-500">
                                        <div>
                                            Showing <span className="font-semibold text-stone-800">{orders.from || 0}</span> to{' '}
                                            <span className="font-semibold text-stone-800">{orders.to || 0}</span> of{' '}
                                            <span className="font-semibold text-stone-800">{orders.total}</span> orders
                                        </div>

                                        <div className="flex items-center gap-1 overflow-x-auto pb-1 sm:pb-0">
                                            {orders.links.map((link, idx) => {
                                                if (!link.url) {
                                                    return (
                                                        <span
                                                            key={idx}
                                                            className="px-3 py-1.5 rounded-lg text-stone-300 text-xs font-medium cursor-not-allowed select-none"
                                                            dangerouslySetInnerHTML={{ __html: link.label }}
                                                        />
                                                    );
                                                }

                                                return (
                                                    <Link
                                                        key={idx}
                                                        href={link.url}
                                                        preserveState
                                                        className={`px-3 py-1.5 rounded-lg text-xs font-medium transition-colors cursor-pointer ${
                                                            link.active
                                                                ? 'bg-stone-900 text-white font-semibold'
                                                                : 'text-stone-600 hover:text-stone-900 hover:bg-stone-100'
                                                        }`}
                                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                                    />
                                                );
                                            })}
                                        </div>
                                    </div>
                                )}
                            </>
                        )}
                    </div>
                </div>
            </div>
        </PublicLayout>
    );
}
