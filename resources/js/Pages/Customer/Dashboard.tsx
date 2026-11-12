import React from 'react';
import { Head, Link } from '@inertiajs/react';
import PublicLayout from '@/Layouts/PublicLayout';
import * as Icons from '@/Components/Icons';
import { PageProps } from '@/types';

export interface CustomerInfo {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    joined_date: string;
}

export interface CustomerStats {
    total_orders: number;
    pending_orders: number;
    completed_orders: number;
    cancelled_orders: number;
    total_spend: number;
    total_spend_formatted: string;
}

export interface RecentOrder {
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

interface Props extends PageProps {
    customer: CustomerInfo;
    statistics: CustomerStats;
    recentOrders: RecentOrder[];
}

export default function CustomerDashboard({ customer, statistics, recentOrders }: Props) {
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
            <Head title="My Customer Account — Aura Luxe" />

            <div className="min-h-screen bg-stone-50/60 pb-16 pt-8 font-sans">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">
                    {/* Welcome Header */}
                    <div className="bg-white border border-stone-200/80 rounded-2xl p-6 sm:p-8 shadow-xs">
                        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-6">
                            <div className="flex items-center gap-4">
                                <div className="w-14 h-14 rounded-full bg-stone-900 text-amber-200 font-serif text-xl font-bold flex items-center justify-center shrink-0 shadow-sm border border-stone-800">
                                    {customer.name ? customer.name.charAt(0).toUpperCase() : 'C'}
                                </div>
                                <div>
                                    <div className="flex items-center gap-2.5">
                                        <h1 className="font-serif text-2xl sm:text-3xl font-medium tracking-tight text-stone-900">
                                            Welcome, {customer.name}
                                        </h1>
                                        <span className="inline-flex items-center gap-1 text-[11px] font-semibold tracking-wide uppercase px-2.5 py-0.5 rounded-full bg-amber-50 text-amber-800 border border-amber-200/60 font-mono">
                                            <Icons.Sparkles className="w-3 h-3 text-amber-600" />
                                            Customer
                                        </span>
                                    </div>
                                    <p className="text-xs sm:text-sm text-stone-500 mt-1 font-light">
                                        Aura Luxe Storefront Account &bull; Member since {customer.joined_date}
                                    </p>
                                </div>
                            </div>

                            {/* Storefront Action */}
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
                                className="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-semibold bg-stone-900 text-white shadow-xs cursor-pointer shrink-0"
                            >
                                <Icons.LayoutDashboard className="w-3.5 h-3.5 text-amber-300" />
                                <span>Overview</span>
                            </Link>

                            <Link
                                href="/customer/orders"
                                className="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-medium text-stone-600 hover:text-stone-900 hover:bg-stone-100 transition-colors cursor-pointer shrink-0"
                            >
                                <Icons.Boxes className="w-3.5 h-3.5 text-stone-500" />
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

                    {/* Order Statistics Grid */}
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        {/* 1. Total Orders */}
                        <div className="bg-white border border-stone-200/80 rounded-2xl p-5 shadow-xs transition-all hover:border-stone-300">
                            <div className="flex items-center justify-between">
                                <span className="text-xs font-semibold uppercase tracking-wider text-stone-500 font-mono">
                                    Total Orders
                                </span>
                                <div className="p-2 rounded-xl bg-stone-100 text-stone-700">
                                    <Icons.Boxes className="w-4 h-4" />
                                </div>
                            </div>
                            <div className="mt-3">
                                <div className="text-2xl font-bold font-serif text-stone-900">
                                    {statistics.total_orders}
                                </div>
                                <p className="text-[11px] text-stone-400 mt-0.5">
                                    Lifetime purchases placed
                                </p>
                            </div>
                        </div>

                        {/* 2. Pending Deliveries */}
                        <div className="bg-white border border-stone-200/80 rounded-2xl p-5 shadow-xs transition-all hover:border-stone-300">
                            <div className="flex items-center justify-between">
                                <span className="text-xs font-semibold uppercase tracking-wider text-amber-700 font-mono">
                                    Active Deliveries
                                </span>
                                <div className="p-2 rounded-xl bg-amber-50 text-amber-700">
                                    <Icons.Truck className="w-4 h-4" />
                                </div>
                            </div>
                            <div className="mt-3">
                                <div className="text-2xl font-bold font-serif text-amber-900">
                                    {statistics.pending_orders}
                                </div>
                                <p className="text-[11px] text-stone-400 mt-0.5">
                                    In processing or out for delivery
                                </p>
                            </div>
                        </div>

                        {/* 3. Completed Orders */}
                        <div className="bg-white border border-stone-200/80 rounded-2xl p-5 shadow-xs transition-all hover:border-stone-300">
                            <div className="flex items-center justify-between">
                                <span className="text-xs font-semibold uppercase tracking-wider text-emerald-700 font-mono">
                                    Completed
                                </span>
                                <div className="p-2 rounded-xl bg-emerald-50 text-emerald-700">
                                    <Icons.CheckCircle2 className="w-4 h-4" />
                                </div>
                            </div>
                            <div className="mt-3">
                                <div className="text-2xl font-bold font-serif text-emerald-900">
                                    {statistics.completed_orders}
                                </div>
                                <p className="text-[11px] text-stone-400 mt-0.5">
                                    Successfully delivered & paid
                                </p>
                            </div>
                        </div>

                        {/* 4. Total Verified Spend */}
                        <div className="bg-white border border-stone-200/80 rounded-2xl p-5 shadow-xs transition-all hover:border-stone-300">
                            <div className="flex items-center justify-between">
                                <span className="text-xs font-semibold uppercase tracking-wider text-stone-500 font-mono">
                                    Completed Spend
                                </span>
                                <div className="p-2 rounded-xl bg-rose-50 text-rose-800">
                                    <Icons.CreditCard className="w-4 h-4" />
                                </div>
                            </div>
                            <div className="mt-3">
                                <div className="text-xl font-bold font-serif text-stone-900 truncate">
                                    {statistics.total_spend_formatted}
                                </div>
                                <p className="text-[11px] text-stone-400 mt-0.5">
                                    Delivered order turnover
                                </p>
                            </div>
                        </div>
                    </div>

                    {/* Recent Orders Section */}
                    <div className="bg-white border border-stone-200/80 rounded-2xl shadow-xs overflow-hidden">
                        <div className="p-6 border-b border-stone-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                            <div>
                                <h2 className="font-serif text-lg font-semibold text-stone-900">
                                    Recent Orders
                                </h2>
                                <p className="text-xs text-stone-500 mt-0.5">
                                    Your latest order placements and fulfillment status
                                </p>
                            </div>
                            <Link
                                href="/track"
                                className="inline-flex items-center gap-1.5 text-xs font-semibold text-rose-800 hover:text-rose-900 cursor-pointer"
                            >
                                <span>Track with reference & phone</span>
                                <Icons.ChevronRight className="w-3.5 h-3.5" />
                            </Link>
                        </div>

                        {recentOrders.length === 0 ? (
                            /* Empty State */
                            <div className="p-12 text-center">
                                <div className="w-12 h-12 rounded-full bg-stone-100 text-stone-400 flex items-center justify-center mx-auto mb-4">
                                    <Icons.Boxes className="w-6 h-6" />
                                </div>
                                <h3 className="font-serif text-base font-semibold text-stone-900">
                                    No orders placed yet
                                </h3>
                                <p className="text-xs text-stone-500 max-w-sm mx-auto mt-1 mb-6">
                                    When you place an order with Aura Luxe, it will appear here so you can monitor preparation, courier dispatch, and COD delivery.
                                </p>
                                <Link
                                    href="/products"
                                    className="inline-flex items-center gap-2 px-5 py-2.5 bg-stone-900 hover:bg-stone-800 text-white text-xs font-semibold uppercase tracking-wider rounded-xl transition-all shadow-xs"
                                >
                                    <Icons.Sparkles className="w-3.5 h-3.5 text-amber-300" />
                                    <span>Explore Cosmetic Collection</span>
                                </Link>
                            </div>
                        ) : (
                            /* Orders Table */
                            <div className="overflow-x-auto">
                                <table className="w-full text-left text-xs text-stone-600">
                                    <thead className="bg-stone-50/80 text-[11px] uppercase tracking-wider text-stone-500 border-b border-stone-100 font-mono">
                                        <tr>
                                            <th className="px-6 py-3.5 font-semibold">Order Reference</th>
                                            <th className="px-6 py-3.5 font-semibold">Date Placed</th>
                                            <th className="px-6 py-3.5 font-semibold">Products</th>
                                            <th className="px-6 py-3.5 font-semibold">Total (LKR)</th>
                                            <th className="px-6 py-3.5 font-semibold">Status</th>
                                            <th className="px-6 py-3.5 font-semibold text-right">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-stone-100">
                                        {recentOrders.map((ord) => {
                                            const badge = getStatusBadge(ord.status);
                                            return (
                                                <tr key={ord.id} className="hover:bg-stone-50/50 transition-colors">
                                                    <td className="px-6 py-4 font-mono font-semibold text-stone-900">
                                                        #{ord.order_number}
                                                    </td>
                                                    <td className="px-6 py-4 text-stone-500">
                                                        {ord.placed_at}
                                                    </td>
                                                    <td className="px-6 py-4 text-stone-700">
                                                        <div className="truncate max-w-[200px]" title={ord.first_item_name}>
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
                                                                title="Track package"
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
                        )}
                    </div>

                    {/* Profile Summary & Support Cards */}
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                        {/* Profile Summary Card */}
                        <div className="bg-white border border-stone-200/80 rounded-2xl p-6 shadow-xs flex flex-col justify-between">
                            <div>
                                <div className="flex items-center justify-between mb-4">
                                    <h3 className="font-serif text-base font-semibold text-stone-900">
                                        Account Details
                                    </h3>
                                    <span className="text-[11px] text-stone-400 font-mono">
                                        User ID #{customer.id}
                                    </span>
                                </div>
                                <div className="space-y-3 text-xs">
                                    <div className="flex justify-between py-1.5 border-b border-stone-100">
                                        <span className="text-stone-500">Name:</span>
                                        <span className="font-semibold text-stone-900">{customer.name}</span>
                                    </div>
                                    <div className="flex justify-between py-1.5 border-b border-stone-100">
                                        <span className="text-stone-500">Email:</span>
                                        <span className="font-semibold text-stone-900">{customer.email}</span>
                                    </div>
                                    <div className="flex justify-between py-1.5 border-b border-stone-100">
                                        <span className="text-stone-500">Phone:</span>
                                        <span className="text-stone-700">
                                            {customer.phone || 'Not provided'}
                                        </span>
                                    </div>
                                    <div className="flex justify-between py-1.5">
                                        <span className="text-stone-500">Account Type:</span>
                                        <span className="font-semibold text-amber-800">Direct Customer</span>
                                    </div>
                                </div>
                            </div>

                            <div className="mt-6 pt-4 border-t border-stone-100">
                                <Link
                                    href="/profile"
                                    className="inline-flex items-center gap-2 text-xs font-semibold text-stone-800 hover:text-stone-950 hover:underline cursor-pointer"
                                >
                                    <Icons.Settings className="w-4 h-4 text-stone-400" />
                                    <span>Manage Profile & Password</span>
                                </Link>
                            </div>
                        </div>

                        {/* Customer Care & Assurance Card */}
                        <div className="bg-white border border-stone-200/80 rounded-2xl p-6 shadow-xs flex flex-col justify-between">
                            <div>
                                <div className="flex items-center justify-between mb-4">
                                    <h3 className="font-serif text-base font-semibold text-stone-900">
                                        Customer Care & Policies
                                    </h3>
                                    <span className="text-[11px] text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-full font-mono font-medium">
                                        Islandwide COD
                                    </span>
                                </div>
                                <div className="space-y-3 text-xs text-stone-600">
                                    <div className="flex items-start gap-2.5">
                                        <Icons.Truck className="w-4 h-4 text-stone-400 shrink-0 mt-0.5" />
                                        <p>
                                            <strong className="text-stone-900 font-medium">Delivery:</strong> Standard door-to-door delivery within 2–4 business days across all 25 districts in Sri Lanka.
                                        </p>
                                    </div>
                                    <div className="flex items-start gap-2.5">
                                        <Icons.RotateCcw className="w-4 h-4 text-stone-400 shrink-0 mt-0.5" />
                                        <p>
                                            <strong className="text-stone-900 font-medium">Returns:</strong> 7-day hassle-free return grace period for unopened, sealed cosmetic products.
                                        </p>
                                    </div>
                                    <div className="flex items-start gap-2.5">
                                        <Icons.ShieldCheck className="w-4 h-4 text-stone-400 shrink-0 mt-0.5" />
                                        <p>
                                            <strong className="text-stone-900 font-medium">Authenticity:</strong> 100% genuine botanical and dermatologically-tested luxury cosmetics.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div className="mt-6 pt-4 border-t border-stone-100 flex items-center justify-between text-xs">
                                <Link
                                    href="/contact"
                                    className="font-semibold text-stone-800 hover:text-stone-950 hover:underline cursor-pointer"
                                >
                                    Contact Support &rarr;
                                </Link>
                                <Link
                                    href="/return-policy"
                                    className="text-stone-500 hover:text-stone-700 hover:underline cursor-pointer"
                                >
                                    View Return Policy
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </PublicLayout>
    );
}

