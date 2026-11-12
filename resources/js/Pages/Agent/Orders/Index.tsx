import React from 'react';
import { Head, Link } from '@inertiajs/react';
import AgentLayout from '@/Layouts/AgentLayout';
import { PageProps, Order } from '@/types';
import {
    ShoppingCart,
    Plus,
    Clock,
    CheckCircle2,
    Truck,
    Package,
    XCircle,
    ChevronLeft,
    ChevronRight,
} from '@/Components/Icons';

interface PaginatedOrders {
    data: Order[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
    total: number;
}

interface IndexProps extends PageProps {
    orders: PaginatedOrders;
}

export default function Index({ orders }: IndexProps) {
    const getStatusBadge = (status: string) => {
        switch (status) {
            case 'delivered':
                return (
                    <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">
                        <CheckCircle2 className="w-3.5 h-3.5" /> Delivered
                    </span>
                );
            case 'dispatched':
                return (
                    <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-800">
                        <Truck className="w-3.5 h-3.5" /> Dispatched
                    </span>
                );
            case 'processing':
            case 'confirmed':
                return (
                    <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800">
                        <Package className="w-3.5 h-3.5" /> Processing
                    </span>
                );
            case 'cancelled':
            case 'returned':
                return (
                    <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-800">
                        <XCircle className="w-3.5 h-3.5" /> {status}
                    </span>
                );
            default:
                return (
                    <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-800">
                        <Clock className="w-3.5 h-3.5" /> {status}
                    </span>
                );
        }
    };

    return (
        <AgentLayout>
            <Head title="My Placed Orders" />

            <div className="max-w-6xl mx-auto space-y-6">
                {/* Header */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold text-slate-900 tracking-tight">My Placed Orders</h1>
                        <p className="text-sm text-slate-500 mt-1">
                            Orders you have personally assisted and placed on behalf of customers ({orders.total} total).
                        </p>
                    </div>

                    <Link
                        href={route('agent.orders.create')}
                        className="inline-flex items-center gap-2 px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-sm font-semibold shadow-sm transition-colors"
                    >
                        <Plus className="w-4 h-4" /> Place New Order
                    </Link>
                </div>

                {/* Orders List Table */}
                <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                    {orders.data.length === 0 ? (
                        <div className="p-12 text-center">
                            <ShoppingCart className="w-12 h-12 text-slate-300 mx-auto mb-3" />
                            <p className="text-base font-medium text-slate-700">No customer orders placed yet</p>
                            <p className="text-xs text-slate-500 mt-1">
                                When you assist offline clients and register orders under your account, they will appear here.
                            </p>
                            <Link
                                href={route('agent.orders.create')}
                                className="inline-flex items-center gap-1.5 px-4 py-2 bg-slate-900 hover:bg-slate-800 text-white rounded-lg text-xs font-semibold mt-4 transition-colors"
                            >
                                <Plus className="w-3.5 h-3.5" /> Place First Customer Order
                            </Link>
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-sm text-slate-600">
                                <thead className="bg-slate-50 text-xs font-semibold text-slate-500 uppercase tracking-wider border-b border-slate-200">
                                    <tr>
                                        <th className="px-5 py-3">Order Number</th>
                                        <th className="px-5 py-3">Customer</th>
                                        <th className="px-5 py-3">Destination</th>
                                        <th className="px-5 py-3">Items</th>
                                        <th className="px-5 py-3">Amount</th>
                                        <th className="px-5 py-3">Delivery Status</th>
                                        <th className="px-5 py-3">Date</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {orders.data.map((order) => (
                                        <tr key={order.id} className="hover:bg-slate-50 transition-colors">
                                            <td className="px-5 py-4 font-mono font-bold text-slate-900">
                                                {order.order_number}
                                            </td>
                                            <td className="px-5 py-4">
                                                <div className="font-semibold text-slate-900">{order.customer_name}</div>
                                                <div className="text-xs text-slate-500 font-mono">{order.customer_phone}</div>
                                            </td>
                                            <td className="px-5 py-4 text-xs text-slate-700">
                                                {order.city || '—'}, {order.district || '—'}
                                            </td>
                                            <td className="px-5 py-4 text-xs text-slate-700">
                                                {order.items?.length || 0} {order.items?.length === 1 ? 'item' : 'items'}
                                            </td>
                                            <td className="px-5 py-4 font-bold text-slate-900">
                                                LKR {Number(order.total_amount).toLocaleString()}
                                            </td>
                                            <td className="px-5 py-4">
                                                {getStatusBadge(order.status)}
                                            </td>
                                            <td className="px-5 py-4 text-xs text-slate-500 whitespace-nowrap">
                                                {order.placed_at ? new Date(order.placed_at).toLocaleDateString() : '—'}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}

                    {/* Pagination */}
                    {orders.last_page > 1 && (
                        <div className="p-4 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
                            <span>
                                Page {orders.current_page} of {orders.last_page}
                            </span>
                            <div className="flex items-center gap-2">
                                {orders.prev_page_url && (
                                    <Link
                                        href={orders.prev_page_url}
                                        className="px-3 py-1.5 border border-slate-300 rounded-lg text-slate-700 hover:bg-slate-50 flex items-center gap-1 font-medium"
                                    >
                                        <ChevronLeft className="w-3.5 h-3.5" /> Previous
                                    </Link>
                                )}
                                {orders.next_page_url && (
                                    <Link
                                        href={orders.next_page_url}
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
