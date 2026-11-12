import AdminLayout from '@/Layouts/AdminLayout';
import { AuditLog, Courier, Order } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import React, { useState } from 'react';

interface Props {
    order: Order;
    auditLogs: AuditLog[];
    couriers: Courier[];
}

export default function OrderShow({ order, auditLogs, couriers }: Props) {
    const [actionReason, setActionReason] = useState('');
    const [targetAction, setTargetAction] = useState<string | null>(null);

    // Courier Assignment & Actual Fee Form
    const courierForm = useForm({
        courier_name: order.delivery?.courier_name ?? '',
        tracking_number: order.delivery?.tracking_number ?? '',
        actual_courier_fee: order.delivery?.actual_courier_fee !== null && order.delivery?.actual_courier_fee !== undefined
            ? String(order.delivery.actual_courier_fee)
            : '',
        courier_invoice_reference: order.delivery?.courier_invoice_reference ?? '',
        notes: order.delivery?.notes ?? '',
    });

    // COD Reconciliation Form
    const codForm = useForm({
        collected_amount: order.cod_collection?.collected_amount
            ? String(order.cod_collection.collected_amount)
            : String(order.total_amount),
        courier_reference: order.cod_collection?.courier_reference ?? '',
        courier_name: order.delivery?.courier_name ?? '',
        notes: order.cod_collection?.notes ?? '',
    });

    const handleTransition = (newStatus: string, reason?: string) => {
        router.post(`/admin/orders/${order.id}/status`, {
            status: newStatus,
            reason: reason || undefined,
            courier_name: order.delivery?.courier_name,
            tracking_number: order.delivery?.tracking_number,
        }, {
            onSuccess: () => {
                setTargetAction(null);
                setActionReason('');
            },
        });
    };

    const handleAssignCourier = (e: React.FormEvent) => {
        e.preventDefault();
        courierForm.post(`/admin/orders/${order.id}/courier`);
    };

    const handleReconcileCod = (e: React.FormEvent) => {
        e.preventDefault();
        codForm.post(`/admin/orders/${order.id}/reconcile-cod`);
    };

    const isTerminal = ['completed', 'cancelled', 'refused', 'returned'].includes(order.status);

    return (
        <AdminLayout>
            <Head title={`Order #${order.order_number} | Aura Luxe Administration`} />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex flex-col sm:flex-row sm:items-center justify-between pb-4 border-b border-slate-200 dark:border-slate-800 gap-4">
                    <div>
                        <div className="flex items-center gap-3">
                            <h1 className="font-serif text-2xl text-slate-900 dark:text-white font-semibold font-mono">
                                #{order.order_number}
                            </h1>
                            <span className={`inline-block px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wider ${
                                order.status === 'completed'
                                    ? 'bg-rose-900 dark:bg-rose-950/80 text-rose-100 dark:text-rose-200 border border-rose-800'
                                    : order.status === 'cash_collected'
                                    ? 'bg-emerald-100 dark:bg-emerald-950/60 text-emerald-900 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800'
                                    : order.status === 'shipped' || order.status === 'delivered'
                                    ? 'bg-sky-100 dark:bg-sky-950/60 text-sky-900 dark:text-sky-300 border border-sky-200 dark:border-sky-800'
                                    : order.status === 'cancelled' || order.status === 'refused' || order.status === 'returned'
                                    ? 'bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300'
                                    : 'bg-amber-100 dark:bg-amber-950/60 text-amber-900 dark:text-amber-300 border border-amber-200 dark:border-amber-800'
                            }`}>
                                {order.status.replace('_', ' ')}
                            </span>
                        </div>
                        <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
                            Placed on {new Date(order.placed_at).toLocaleString()} &bull; Payment: Cash on Delivery (COD)
                        </p>
                    </div>

                    <div className="flex items-center gap-3">
                        <a
                            href={`/admin/orders/${order.id}/invoice`}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 rounded-lg text-xs font-medium shadow-xs transition-colors"
                        >
                            <svg className="w-3.5 h-3.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <span>Download PDF Invoice</span>
                        </a>

                        <Link href="/admin/orders" className="text-xs text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition-colors">
                            &larr; Back to Orders
                        </Link>
                    </div>
                </div>

                {/* State Machine Transition Action Bar */}
                {!isTerminal && (
                    <div className="bg-white dark:bg-[#0F172A] p-5 rounded-2xl border border-slate-200/80 dark:border-slate-800/80 shadow-xs space-y-3">
                        <span className="text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider block">
                            Order Lifecycle Actions (Strict State Machine)
                        </span>

                        <div className="flex flex-wrap items-center gap-2 text-xs font-semibold">
                            {order.status === 'placed' && (
                                <>
                                    <button
                                        type="button"
                                        onClick={() => handleTransition('confirmed')}
                                        className="bg-slate-900 dark:bg-blue-600 text-white px-4 py-2 rounded-xl hover:bg-slate-800 dark:hover:bg-blue-500 transition-colors cursor-pointer"
                                    >
                                        ✓ Confirm Order
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => setTargetAction('cancelled')}
                                        className="bg-rose-50 dark:bg-rose-950/30 text-rose-800 dark:text-rose-300 border border-rose-200 dark:border-rose-900/60 px-4 py-2 rounded-xl hover:bg-rose-100 dark:hover:bg-rose-900/40 transition-colors cursor-pointer"
                                    >
                                        ✕ Cancel Order
                                    </button>
                                </>
                            )}

                            {order.status === 'confirmed' && (
                                <>
                                    <button
                                        type="button"
                                        onClick={() => handleTransition('packed')}
                                        className="bg-slate-900 dark:bg-blue-600 text-white px-4 py-2 rounded-xl hover:bg-slate-800 dark:hover:bg-blue-500 transition-colors cursor-pointer"
                                    >
                                        📦 Mark as Packed
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => setTargetAction('cancelled')}
                                        className="bg-rose-50 dark:bg-rose-950/30 text-rose-800 dark:text-rose-300 border border-rose-200 dark:border-rose-900/60 px-4 py-2 rounded-xl hover:bg-rose-100 dark:hover:bg-rose-900/40 transition-colors cursor-pointer"
                                    >
                                        ✕ Cancel Order
                                    </button>
                                </>
                            )}

                            {order.status === 'packed' && (
                                <>
                                    <button
                                        type="button"
                                        onClick={() => {
                                            if (!order.delivery?.courier_name || !order.delivery?.tracking_number) {
                                                alert('Please assign a courier name and tracking number below before marking as shipped.');
                                                return;
                                            }
                                            handleTransition('shipped');
                                        }}
                                        className="bg-slate-900 dark:bg-blue-600 text-white px-4 py-2 rounded-xl hover:bg-slate-800 dark:hover:bg-blue-500 transition-colors cursor-pointer"
                                    >
                                        🚚 Dispatch & Ship Order
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => setTargetAction('cancelled')}
                                        className="bg-rose-50 dark:bg-rose-950/30 text-rose-800 dark:text-rose-300 border border-rose-200 dark:border-rose-900/60 px-4 py-2 rounded-xl hover:bg-rose-100 dark:hover:bg-rose-900/40 transition-colors cursor-pointer"
                                    >
                                        ✕ Cancel Order
                                    </button>
                                </>
                            )}

                            {order.status === 'shipped' && (
                                <>
                                    <button
                                        type="button"
                                        onClick={() => handleTransition('delivered')}
                                        className="bg-emerald-700 dark:bg-emerald-600 text-white px-4 py-2 rounded-xl hover:bg-emerald-800 dark:hover:bg-emerald-500 transition-colors cursor-pointer"
                                    >
                                        ✓ Mark as Delivered
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => setTargetAction('refused')}
                                        className="bg-rose-50 dark:bg-rose-950/30 text-rose-800 dark:text-rose-300 border border-rose-200 dark:border-rose-900/60 px-4 py-2 rounded-xl hover:bg-rose-100 dark:hover:bg-rose-900/40 transition-colors cursor-pointer"
                                    >
                                        ✕ Customer Refused Parcel
                                    </button>
                                </>
                            )}

                            {order.status === 'delivered' && (
                                <>
                                    <button
                                        type="button"
                                        onClick={() => {
                                            if (!order.cod_collection || order.cod_collection.reconciliation_status === 'pending') {
                                                alert('Please reconcile the physical COD collection record in the section below before advancing to Cash Collected.');
                                                return;
                                            }
                                            handleTransition('cash_collected');
                                        }}
                                        className="bg-emerald-700 dark:bg-emerald-600 text-white px-4 py-2 rounded-xl hover:bg-emerald-800 dark:hover:bg-emerald-500 transition-colors cursor-pointer"
                                    >
                                        💰 Confirm COD Cash Collected
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => setTargetAction('returned')}
                                        className="bg-rose-50 dark:bg-rose-950/30 text-rose-800 dark:text-rose-300 border border-rose-200 dark:border-rose-900/60 px-4 py-2 rounded-xl hover:bg-rose-100 dark:hover:bg-rose-900/40 transition-colors cursor-pointer"
                                    >
                                        ↩ Process Customer Return
                                    </button>
                                </>
                            )}

                            {order.status === 'cash_collected' && (
                                <>
                                    <button
                                        type="button"
                                        onClick={() => handleTransition('completed')}
                                        className="bg-rose-900 dark:bg-blue-600 text-white px-5 py-2 rounded-xl hover:bg-rose-800 dark:hover:bg-blue-500 transition-colors cursor-pointer"
                                    >
                                        ★ Complete Order
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => setTargetAction('returned')}
                                        className="bg-rose-50 dark:bg-rose-950/30 text-rose-800 dark:text-rose-300 border border-rose-200 dark:border-rose-900/60 px-4 py-2 rounded-xl hover:bg-rose-100 dark:hover:bg-rose-900/40 transition-colors cursor-pointer"
                                    >
                                        ↩ Process Post-Collection Return
                                    </button>
                                </>
                            )}
                        </div>

                        {/* Modal/Prompt for reason if cancelling, refusing, or returning */}
                        {targetAction && (
                            <div className="p-4 bg-rose-50 dark:bg-rose-950/40 rounded-xl border border-rose-200 dark:border-rose-900/60 space-y-2 mt-2">
                                <label className="block text-xs font-semibold text-rose-900 dark:text-rose-300">
                                    Reason for {targetAction.toUpperCase()} (Required for Audit Trail & Stock Restoration):
                                </label>
                                <div className="flex gap-2">
                                    <input
                                        type="text"
                                        value={actionReason}
                                        onChange={(e) => setActionReason(e.target.value)}
                                        placeholder="e.g. Customer cancelled via phone / wrong address..."
                                        className="flex-1 rounded-xl border-slate-300 dark:border-slate-700 bg-white dark:bg-[#1E293B] text-slate-900 dark:text-white text-xs px-3 py-1.5 focus:border-blue-500 focus:ring-blue-500"
                                        required
                                    />
                                    <button
                                        type="button"
                                        onClick={() => {
                                            if (!actionReason.trim()) {
                                                alert('Please enter a reason.');
                                                return;
                                            }
                                            handleTransition(targetAction, actionReason);
                                        }}
                                        className="bg-rose-900 dark:bg-rose-600 text-white px-4 py-1.5 rounded-xl text-xs font-semibold hover:bg-rose-800 dark:hover:bg-rose-500 transition-colors cursor-pointer"
                                    >
                                        Confirm {targetAction}
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => setTargetAction(null)}
                                        className="text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-white text-xs px-2 cursor-pointer"
                                    >
                                        Cancel
                                    </button>
                                </div>
                            </div>
                        )}
                    </div>
                )}

                {/* Grid: Order Items & Delivery/Reconciliation */}
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {/* Left 2 Cols: Order Items & Financials */}
                    <div className="lg:col-span-2 space-y-6">
                        {/* Order Items Snapshot */}
                        <div className="bg-white dark:bg-[#0F172A] rounded-2xl border border-slate-200/80 dark:border-slate-800/80 shadow-xs overflow-hidden">
                            <div className="px-6 py-4 border-b border-slate-100 dark:border-slate-800/80">
                                <h2 className="font-serif text-base font-semibold text-slate-900 dark:text-white">
                                    Purchased Items Snapshot
                                </h2>
                                <span className="text-[11px] text-slate-400 dark:text-slate-500">
                                    Immutable historical pricing and SKU recorded at checkout.
                                </span>
                            </div>

                            <table className="w-full text-left text-xs divide-y divide-slate-200 dark:divide-slate-800">
                                <thead className="bg-slate-50 dark:bg-[#1E293B]/70 text-slate-600 dark:text-slate-400 font-semibold uppercase text-[10px] tracking-wider">
                                    <tr>
                                        <th className="px-6 py-3.5">Item Snapshot</th>
                                        <th className="px-6 py-3.5">Unit Price</th>
                                        <th className="px-6 py-3.5">Qty</th>
                                        <th className="px-6 py-3.5 text-right">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100 dark:divide-slate-800/60">
                                    {order.items?.map((item) => (
                                        <tr key={item.id}>
                                            <td className="px-6 py-3.5">
                                                <span className="font-semibold text-slate-900 dark:text-white block">{item.product_name}</span>
                                                <span className="text-slate-400 dark:text-slate-500 font-mono text-[10px]">{item.product_sku ?? 'NO-SKU'}</span>
                                            </td>
                                            <td className="px-6 py-3.5 font-mono text-slate-700 dark:text-slate-300">
                                                LKR {Number(item.unit_price).toLocaleString('en-US', { minimumFractionDigits: 2 })}
                                            </td>
                                            <td className="px-6 py-3.5 font-mono font-semibold text-slate-900 dark:text-white">
                                                {item.quantity}
                                            </td>
                                            <td className="px-6 py-3.5 font-mono font-semibold text-right text-slate-900 dark:text-white">
                                                LKR {Number(item.subtotal).toLocaleString('en-US', { minimumFractionDigits: 2 })}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>

                            <div className="p-6 bg-slate-50/50 dark:bg-slate-900/50 border-t border-slate-100 dark:border-slate-800/80 flex flex-col items-end space-y-1.5 text-xs font-mono">
                                <div className="flex justify-between w-64 text-slate-600 dark:text-slate-400">
                                    <span>Items Subtotal:</span>
                                    <span>LKR {Number(order.subtotal).toLocaleString('en-US', { minimumFractionDigits: 2 })}</span>
                                </div>
                                <div className="flex justify-between w-64 text-slate-600 dark:text-slate-400">
                                    <span>Delivery Charge:</span>
                                    <span>LKR {Number(order.delivery_charge).toLocaleString('en-US', { minimumFractionDigits: 2 })}</span>
                                </div>
                                <div className="flex justify-between w-64 text-sm font-bold text-slate-900 dark:text-white pt-2 border-t border-slate-200 dark:border-slate-800">
                                    <span>Total Payable (COD):</span>
                                    <span className="text-rose-700 dark:text-rose-400">
                                        LKR {Number(order.total_amount).toLocaleString('en-US', { minimumFractionDigits: 2 })}
                                    </span>
                                </div>
                            </div>
                        </div>

                        {/* Customer Information */}
                        <div className="bg-white dark:bg-[#0F172A] p-6 rounded-2xl border border-slate-200/80 dark:border-slate-800/80 shadow-xs space-y-3 text-xs">
                            <h2 className="font-serif text-base font-semibold text-slate-900 dark:text-white border-b border-slate-100 dark:border-slate-800/80 pb-2">
                                Customer & Shipping Address
                            </h2>
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <span className="text-slate-400 dark:text-slate-500 block text-[11px]">Customer Name:</span>
                                    <span className="font-semibold text-slate-900 dark:text-white text-sm">{order.customer_name}</span>
                                    {order.customer && (
                                        <Link
                                            href={`/admin/customers/${order.customer.id}`}
                                            className="text-emerald-600 dark:text-emerald-400 hover:underline text-[10px] block font-medium mt-0.5"
                                        >
                                            ✓ Registered Account (View Profile →)
                                        </Link>
                                    )}
                                </div>
                                <div>
                                    <span className="text-slate-400 dark:text-slate-500 block text-[11px]">Phone Number:</span>
                                    <span className="font-mono font-semibold text-slate-900 dark:text-white text-sm">{order.customer_phone}</span>
                                </div>
                                <div className="sm:col-span-2">
                                    <span className="text-slate-400 dark:text-slate-500 block text-[11px]">Delivery Address:</span>
                                    <p className="text-slate-800 dark:text-slate-200 font-medium mt-0.5">{order.shipping_address}</p>
                                    <p className="text-slate-500 dark:text-slate-400 mt-0.5">{order.city}, {order.district} {order.postal_code && `(${order.postal_code})`}</p>
                                </div>
                                {order.notes && (
                                    <div className="sm:col-span-2 p-3 bg-slate-50 dark:bg-slate-900/60 rounded-xl border border-slate-100 dark:border-slate-800">
                                        <span className="text-[11px] font-semibold text-slate-600 dark:text-slate-400 block">Customer Notes:</span>
                                        <p className="text-slate-700 dark:text-slate-300 italic mt-0.5">{order.notes}</p>
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>

                    {/* Right 1 Col: Courier & Cash Reconciliation */}
                    <div className="space-y-6">
                        {/* Courier Details */}
                        <div className="bg-white dark:bg-[#0F172A] p-6 rounded-2xl border border-slate-200/80 dark:border-slate-800/80 shadow-xs space-y-4 text-xs">
                            <div className="flex items-center justify-between border-b border-slate-100 dark:border-slate-800/80 pb-2">
                                <h2 className="font-serif text-base font-semibold text-slate-900 dark:text-white">Courier & Logistics Details</h2>
                                <span className="text-[10px] uppercase font-semibold text-slate-500 dark:text-slate-400">
                                    {order.delivery?.status ?? 'Pending'}
                                </span>
                            </div>

                            {/* Logistics Financial Tracking: Customer Revenue vs Actual Expense */}
                            <div className="p-3 bg-slate-50 dark:bg-slate-900/60 rounded-xl border border-slate-200/80 dark:border-slate-800 space-y-2 text-xs">
                                <span className="text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 block">
                                    Delivery Logistics Separation
                                </span>
                                <div className="flex items-center justify-between text-slate-600 dark:text-slate-400">
                                    <span className="font-medium">Customer Delivery Revenue:</span>
                                    <span className="font-mono font-semibold text-slate-900 dark:text-white">
                                        LKR {Number(order.delivery_charge).toLocaleString('en-US', { minimumFractionDigits: 2 })}
                                    </span>
                                </div>
                                <div className="flex items-center justify-between text-slate-600 dark:text-slate-400">
                                    <span className="font-medium">Actual Courier Expense:</span>
                                    {order.delivery?.actual_courier_fee !== null && order.delivery?.actual_courier_fee !== undefined ? (
                                        <span className="font-mono font-bold text-emerald-700 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/50 px-2 py-0.5 rounded border border-emerald-200 dark:border-emerald-800">
                                            LKR {Number(order.delivery.actual_courier_fee).toLocaleString('en-US', { minimumFractionDigits: 2 })}
                                        </span>
                                    ) : (
                                        <span className="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-amber-100 dark:bg-amber-950/60 text-amber-800 dark:text-amber-300 border border-amber-300 dark:border-amber-800">
                                            Unrecorded / Missing
                                        </span>
                                    )}
                                </div>
                                {order.delivery?.courier_invoice_reference && (
                                    <div className="flex items-center justify-between text-slate-500 dark:text-slate-400 text-[11px] pt-1 border-t border-slate-200/60 dark:border-slate-800">
                                        <span>Invoice / Bill Ref:</span>
                                        <span className="font-mono font-semibold text-slate-800 dark:text-slate-200">{order.delivery.courier_invoice_reference}</span>
                                    </div>
                                )}
                                {order.delivery?.courier_fee_recorded_at && (
                                    <div className="text-[10px] text-slate-400 dark:text-slate-500 text-right">
                                        Fee verified on {new Date(order.delivery.courier_fee_recorded_at).toLocaleDateString()}
                                    </div>
                                )}
                            </div>

                            <form onSubmit={handleAssignCourier} className="space-y-3">
                                <div>
                                    <label className="block font-medium text-slate-700 dark:text-slate-300">Courier Partner *</label>
                                    <input
                                        type="text"
                                        list="couriers_list"
                                        value={courierForm.data.courier_name}
                                        onChange={(e) => courierForm.setData('courier_name', e.target.value)}
                                        placeholder="e.g. Domex / Certis / Pronto"
                                        className="mt-1 w-full text-xs rounded-xl border-slate-300 dark:border-slate-700 bg-white dark:bg-[#1E293B] text-slate-900 dark:text-white px-3 py-1.5 focus:border-blue-500 focus:ring-blue-500"
                                        required
                                    />
                                    <datalist id="couriers_list">
                                        {couriers.map((c) => (
                                             <option key={c.id} value={c.name} />
                                        ))}
                                    </datalist>
                                </div>

                                <div>
                                    <label className="block font-medium text-slate-700 dark:text-slate-300">Courier Waybill / Tracking # *</label>
                                    <input
                                        type="text"
                                        value={courierForm.data.tracking_number}
                                        onChange={(e) => courierForm.setData('tracking_number', e.target.value)}
                                        placeholder="e.g. DMX-88912301"
                                        className="mt-1 w-full text-xs rounded-xl border-slate-300 dark:border-slate-700 bg-white dark:bg-[#1E293B] text-slate-900 dark:text-white font-mono px-3 py-1.5 focus:border-blue-500 focus:ring-blue-500"
                                        required
                                    />
                                </div>

                                <div>
                                    <div className="flex items-center justify-between">
                                        <label className="block font-medium text-slate-700 dark:text-slate-300">Actual Courier Fee (LKR Expense)</label>
                                        <span className="text-[10px] text-slate-400 dark:text-slate-500">Internal Expense</span>
                                    </div>
                                    <input
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        value={courierForm.data.actual_courier_fee}
                                        onChange={(e) => courierForm.setData('actual_courier_fee', e.target.value)}
                                        placeholder="e.g. 380.00 (leave empty if pending invoice)"
                                        className="mt-1 w-full text-xs rounded-xl border-slate-300 dark:border-slate-700 bg-white dark:bg-[#1E293B] text-slate-900 dark:text-white font-mono px-3 py-1.5 focus:border-blue-500 focus:ring-blue-500"
                                    />
                                    <p className="text-[10px] text-slate-400 dark:text-slate-500 mt-0.5">
                                        Leave empty if courier invoice has not arrived. Expense is kept as missing/unrecorded.
                                    </p>
                                </div>

                                <div>
                                    <label className="block font-medium text-slate-700 dark:text-slate-300">Courier Invoice / Bill Reference</label>
                                    <input
                                        type="text"
                                        value={courierForm.data.courier_invoice_reference}
                                        onChange={(e) => courierForm.setData('courier_invoice_reference', e.target.value)}
                                        placeholder="e.g. INV-DMX-2026-11-098"
                                        className="mt-1 w-full text-xs rounded-xl border-slate-300 dark:border-slate-700 bg-white dark:bg-[#1E293B] text-slate-900 dark:text-white font-mono px-3 py-1.5 focus:border-blue-500 focus:ring-blue-500"
                                    />
                                </div>

                                <div>
                                    <label className="block font-medium text-slate-700 dark:text-slate-300">Courier Dispatch Notes</label>
                                    <textarea
                                        rows={2}
                                        value={courierForm.data.notes}
                                        onChange={(e) => courierForm.setData('notes', e.target.value)}
                                        placeholder="Delivery instructions or courier slip notes..."
                                        className="mt-1 w-full text-xs rounded-xl border-slate-300 dark:border-slate-700 bg-white dark:bg-[#1E293B] text-slate-900 dark:text-white px-3 py-1.5 focus:border-blue-500 focus:ring-blue-500"
                                    />
                                </div>

                                <button
                                    type="submit"
                                    disabled={courierForm.processing}
                                    className="w-full bg-slate-900 dark:bg-blue-600 text-white font-semibold py-2 rounded-xl hover:bg-slate-800 dark:hover:bg-blue-500 disabled:opacity-50 transition-colors cursor-pointer"
                                >
                                    Save Courier & Fee Details
                                </button>
                            </form>
                        </div>

                        {/* COD Cash Collection Reconciliation */}
                        <div className="bg-white dark:bg-[#0F172A] p-6 rounded-2xl border border-slate-200/80 dark:border-slate-800/80 shadow-xs space-y-4 text-xs">
                            <div className="flex items-center justify-between border-b border-slate-100 dark:border-slate-800/80 pb-2">
                                <h2 className="font-serif text-base font-semibold text-slate-900 dark:text-white">COD Cash Reconciliation</h2>
                                <span className={`text-[10px] uppercase font-semibold px-2 py-0.5 rounded ${
                                    order.cod_collection?.reconciliation_status === 'matched'
                                        ? 'bg-emerald-100 dark:bg-emerald-950/60 text-emerald-800 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800'
                                        : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300'
                                }`}>
                                    {order.cod_collection?.reconciliation_status?.replace('_', ' ') ?? 'Pending'}
                                </span>
                            </div>

                            <form onSubmit={handleReconcileCod} className="space-y-3">
                                <div>
                                    <label className="block font-medium text-slate-700 dark:text-slate-300">Expected Amount (LKR)</label>
                                    <div className="p-2 bg-slate-50 dark:bg-slate-900/60 rounded-xl border border-slate-200 dark:border-slate-800 font-mono font-bold text-slate-800 dark:text-slate-200">
                                        LKR {Number(order.total_amount).toLocaleString('en-US', { minimumFractionDigits: 2 })}
                                    </div>
                                </div>

                                <div>
                                    <label className="block font-medium text-slate-700 dark:text-slate-300">Collected Cash Amount (LKR) *</label>
                                    <input
                                        type="number"
                                        step="0.01"
                                        value={codForm.data.collected_amount}
                                        onChange={(e) => codForm.setData('collected_amount', e.target.value)}
                                        className="mt-1 w-full text-xs rounded-xl border-slate-300 dark:border-slate-700 bg-white dark:bg-[#1E293B] text-slate-900 dark:text-white font-mono font-bold px-3 py-1.5 focus:border-blue-500 focus:ring-blue-500"
                                        required
                                    />
                                </div>

                                <div>
                                    <label className="block font-medium text-slate-700 dark:text-slate-300">Courier Remittance Ref #</label>
                                    <input
                                        type="text"
                                        value={codForm.data.courier_reference}
                                        onChange={(e) => codForm.setData('courier_reference', e.target.value)}
                                        placeholder="e.g. REMIT-2026-099"
                                        className="mt-1 w-full text-xs rounded-xl border-slate-300 dark:border-slate-700 bg-white dark:bg-[#1E293B] text-slate-900 dark:text-white font-mono px-3 py-1.5 focus:border-blue-500 focus:ring-blue-500"
                                    />
                                </div>

                                <div>
                                    <label className="block font-medium text-slate-700 dark:text-slate-300">Reconciliation Notes</label>
                                    <textarea
                                        rows={2}
                                        value={codForm.data.notes}
                                        onChange={(e) => codForm.setData('notes', e.target.value)}
                                        placeholder="Bank deposit slip # or mismatch explanation..."
                                        className="mt-1 w-full text-xs rounded-xl border-slate-300 dark:border-slate-700 bg-white dark:bg-[#1E293B] text-slate-900 dark:text-white px-3 py-1.5 focus:border-blue-500 focus:ring-blue-500"
                                    />
                                </div>

                                <button
                                    type="submit"
                                    disabled={codForm.processing}
                                    className="w-full bg-emerald-800 dark:bg-emerald-600 text-white font-semibold py-2 rounded-xl hover:bg-emerald-700 dark:hover:bg-emerald-500 disabled:opacity-50 transition-colors cursor-pointer"
                                >
                                    Reconcile COD Collection
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                {/* Audit Logs for this order */}
                <div className="bg-white dark:bg-[#0F172A] rounded-2xl border border-slate-200/80 dark:border-slate-800/80 shadow-xs overflow-hidden">
                    <div className="px-6 py-4 border-b border-slate-100 dark:border-slate-800/80">
                        <h2 className="font-serif text-base font-semibold text-slate-900 dark:text-white">
                            Order Lifecycle & Security Audit Trail
                        </h2>
                    </div>
                    <div className="divide-y divide-slate-100 dark:divide-slate-800/60 text-xs">
                        {auditLogs.map((log) => (
                            <div key={log.id} className="p-4 flex items-center justify-between">
                                <div className="space-y-0.5">
                                    <div className="flex items-center gap-2">
                                        <span className="font-mono text-[10px] uppercase font-bold px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
                                            {log.action}
                                        </span>
                                        <span className="font-medium text-slate-800 dark:text-slate-200">
                                            {log.user?.name ?? 'System'}
                                        </span>
                                    </div>
                                    {log.new_values && (
                                        <p className="text-slate-500 dark:text-slate-400 text-[11px] font-mono">
                                            {JSON.stringify(log.new_values)}
                                        </p>
                                    )}
                                </div>
                                <div className="text-right text-slate-400 dark:text-slate-500 text-[11px] font-mono">
                                    {new Date(log.created_at).toLocaleString()}
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
