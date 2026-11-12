import React, { useState } from 'react';
import PublicLayout from '@/Layouts/PublicLayout';
import { Head, Link, router } from '@inertiajs/react';
import { PageProps } from '@/types';
import * as Icons from '@/Components/Icons';

export interface OrderItemDetail {
    id: number;
    product_id: number | null;
    product_name: string;
    product_sku: string | null;
    unit_price: number;
    unit_price_formatted: string;
    quantity: number;
    subtotal: number;
    subtotal_formatted: string;
}

export interface ShippingAddressDetail {
    customer_name: string;
    customer_phone: string;
    customer_email: string | null;
    shipping_address: string;
    district: string;
    city: string;
    postal_code: string | null;
}

export interface DeliveryDetail {
    courier_name: string | null;
    tracking_number: string | null;
    status: string;
    dispatched_at: string | null;
    delivered_at: string | null;
    notes: string | null;
}

export interface OrderDetail {
    id: number;
    order_number: string;
    status: string;
    can_cancel: boolean;
    subtotal: number;
    subtotal_formatted: string;
    delivery_charge: number;
    delivery_charge_formatted: string;
    total_amount: number;
    total_amount_formatted: string;
    payment_method: string;
    payment_status: string;
    placed_at: string;
    notes: string | null;
    tracking_token: string;
    invoice_url?: string;
    shipping: ShippingAddressDetail;
    delivery: DeliveryDetail | null;
    items: OrderItemDetail[];
}

interface Props extends PageProps {
    order: OrderDetail;
}

export default function CustomerOrderShow({ order }: Props) {
    const [showCancelModal, setShowCancelModal] = useState(false);
    const [cancelReasonCategory, setCancelReasonCategory] = useState('Changed mind / No longer needed');
    const [customReason, setCustomReason] = useState('');
    const [isCancelling, setIsCancelling] = useState(false);

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

    const statusBadge = getStatusBadge(order.status);

    // Standard progression steps
    const progressionSteps = [
        { key: 'placed', label: 'Placed' },
        { key: 'confirmed', label: 'Confirmed' },
        { key: 'packed', label: 'Packed' },
        { key: 'shipped', label: 'Shipped' },
        { key: 'delivered', label: 'Delivered' },
    ];

    const getProgressionIndex = (status: string) => {
        switch (status) {
            case 'placed':
                return 0;
            case 'confirmed':
                return 1;
            case 'packed':
                return 2;
            case 'shipped':
                return 3;
            case 'delivered':
            case 'cash_collected':
            case 'completed':
                return 4;
            default:
                return -1;
        }
    };

    const currentStepIndex = getProgressionIndex(order.status);
    const isTerminated = ['cancelled', 'refused', 'returned'].includes(order.status);

    const handleConfirmCancel = () => {
        const fullReason = customReason.trim()
            ? `${cancelReasonCategory} — ${customReason.trim()}`
            : cancelReasonCategory;

        setIsCancelling(true);
        router.post(
            `/customer/orders/${order.order_number}/cancel`,
            { reason: fullReason },
            {
                onFinish: () => {
                    setIsCancelling(false);
                    setShowCancelModal(false);
                },
            }
        );
    };

    return (
        <PublicLayout>
            <Head title={`Order #${order.order_number} — Aura Luxe`} />

            <div className="min-h-screen bg-stone-50/60 pb-16 pt-8 font-sans">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">
                    {/* Top Navigation & Breadcrumbs */}
                    <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <Link
                            href="/customer/orders"
                            className="inline-flex items-center gap-2 text-xs font-semibold text-stone-600 hover:text-stone-900 transition-colors"
                        >
                            <Icons.ArrowLeft className="w-4 h-4" />
                            <span>Back to Order History</span>
                        </Link>

                        <div className="flex items-center gap-2.5">
                            {order.can_cancel && (
                                <button
                                    type="button"
                                    onClick={() => setShowCancelModal(true)}
                                    className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold text-rose-700 bg-rose-50 border border-rose-200 hover:bg-rose-100 transition-colors shadow-2xs cursor-pointer"
                                >
                                    <Icons.XCircle className="w-3.5 h-3.5 text-rose-600" />
                                    <span>Cancel Order</span>
                                </button>
                            )}

                            <a
                                href={order.invoice_url || `/customer/orders/${order.order_number}/invoice`}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold text-stone-700 bg-white border border-stone-200 hover:bg-stone-50 transition-colors shadow-2xs"
                            >
                                <Icons.Download className="w-3.5 h-3.5 text-stone-500" />
                                <span>Download Invoice</span>
                            </a>

                            <Link
                                href={`/track?order_number=${order.order_number}`}
                                className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold text-stone-700 bg-white border border-stone-200 hover:bg-stone-50 transition-colors shadow-2xs"
                            >
                                <Icons.Truck className="w-3.5 h-3.5 text-stone-500" />
                                <span>Public Tracker</span>
                            </Link>
                        </div>
                    </div>

                    {/* Order Header Card */}
                    <div className="bg-white border border-stone-200/80 rounded-2xl p-6 sm:p-8 shadow-xs">
                        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                            <div>
                                <div className="flex items-center gap-3">
                                    <h1 className="font-serif text-2xl sm:text-3xl font-medium tracking-tight text-stone-900">
                                        Order #{order.order_number}
                                    </h1>
                                    <span className={`inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold border ${statusBadge.color}`}>
                                        {statusBadge.label}
                                    </span>
                                </div>
                                <p className="text-xs text-stone-500 mt-1 font-light">
                                    Placed on <span className="font-medium text-stone-700">{order.placed_at}</span> &bull; Payment Method:{' '}
                                    <span className="font-medium text-stone-700">{order.payment_method}</span>
                                </p>
                            </div>

                            <div className="text-left sm:text-right">
                                <span className="text-[11px] uppercase tracking-wider text-stone-400 font-mono block">
                                    Grand Total
                                </span>
                                <span className="font-serif text-2xl font-bold text-stone-900">
                                    {order.total_amount_formatted}
                                </span>
                            </div>
                        </div>

                        {/* Fulfillment Progress Stepper */}
                        <div className="mt-8 pt-6 border-t border-stone-100">
                            {isTerminated ? (
                                <div className="p-4 bg-rose-50 border border-rose-200 rounded-xl text-rose-800 text-xs flex items-center gap-3">
                                    <Icons.AlertCircle className="w-5 h-5 text-rose-600 shrink-0" />
                                    <div>
                                        <span className="font-semibold capitalize">Order {order.status}:</span> This order was marked as {order.status}. No cash collection is due, and any reserved catalog inventory was safely restored. If you have any inquiries, please contact our concierge support.
                                    </div>
                                </div>
                            ) : (
                                <div>
                                    <h3 className="text-xs font-semibold uppercase tracking-wider text-stone-400 mb-4 font-mono">
                                        Fulfillment Timeline
                                    </h3>
                                    <div className="grid grid-cols-2 sm:grid-cols-5 gap-3">
                                        {progressionSteps.map((step, idx) => {
                                            const isDone = currentStepIndex >= idx;
                                            const isCurrent = currentStepIndex === idx;

                                            return (
                                                <div
                                                    key={step.key}
                                                    className={`p-3 rounded-xl border text-center transition-all ${
                                                        isCurrent
                                                            ? 'border-stone-900 bg-stone-900 text-white shadow-xs'
                                                            : isDone
                                                            ? 'border-stone-200 bg-stone-50 text-stone-800 font-medium'
                                                            : 'border-stone-100 bg-white text-stone-400'
                                                    }`}
                                                >
                                                    <span className="text-[10px] block uppercase tracking-wider opacity-75 font-mono mb-0.5">
                                                        Step {idx + 1}
                                                    </span>
                                                    <span className="text-xs block font-semibold truncate">
                                                        {step.label}
                                                    </span>
                                                    {step.key === 'placed' && (
                                                        <span className="text-[10px] block opacity-80 mt-1 truncate">
                                                            {order.placed_at.split(' ')[0]}
                                                        </span>
                                                    )}
                                                    {step.key === 'shipped' && order.delivery?.dispatched_at && (
                                                        <span className="text-[10px] block opacity-80 mt-1 truncate">
                                                            {order.delivery.dispatched_at.split(' ')[0]}
                                                        </span>
                                                    )}
                                                    {step.key === 'delivered' && order.delivery?.delivered_at && (
                                                        <span className="text-[10px] block opacity-80 mt-1 truncate">
                                                            {order.delivery.delivered_at.split(' ')[0]}
                                                        </span>
                                                    )}
                                                </div>
                                            );
                                        })}
                                    </div>
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Main Content Grid: Items (Left 2 cols) & Shipping/Payment (Right 1 col) */}
                    <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
                        {/* Left Column: Ordered Items Table & Notes */}
                        <div className="lg:col-span-2 space-y-6">
                            {/* Products Card */}
                            <div className="bg-white border border-stone-200/80 rounded-2xl shadow-xs overflow-hidden">
                                <div className="p-5 border-b border-stone-100 flex items-center justify-between">
                                    <div className="flex items-center gap-2">
                                        <Icons.Package className="w-4 h-4 text-stone-700" />
                                        <h2 className="font-serif text-base font-semibold text-stone-900">
                                            Ordered Items ({order.items.length})
                                        </h2>
                                    </div>
                                    <span className="text-[11px] font-mono text-stone-400">
                                        Snapshot records
                                    </span>
                                </div>

                                <div className="overflow-x-auto">
                                    <table className="w-full text-left text-xs text-stone-600">
                                        <thead className="bg-stone-50/80 text-[11px] uppercase tracking-wider text-stone-500 border-b border-stone-100 font-mono">
                                            <tr>
                                                <th className="px-6 py-3.5 font-semibold">Product Description</th>
                                                <th className="px-6 py-3.5 font-semibold text-center">Qty</th>
                                                <th className="px-6 py-3.5 font-semibold text-right">Unit Price</th>
                                                <th className="px-6 py-3.5 font-semibold text-right">Subtotal</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-stone-100">
                                            {order.items.map((item) => (
                                                <tr key={item.id} className="hover:bg-stone-50/40 transition-colors">
                                                    <td className="px-6 py-4">
                                                        <div className="font-semibold text-stone-900">
                                                            {item.product_name}
                                                        </div>
                                                        {item.product_sku && (
                                                            <div className="text-[10px] font-mono text-stone-400 mt-0.5">
                                                                SKU: {item.product_sku}
                                                            </div>
                                                        )}
                                                    </td>
                                                    <td className="px-6 py-4 text-center font-medium text-stone-800">
                                                        {item.quantity}
                                                    </td>
                                                    <td className="px-6 py-4 text-right text-stone-600">
                                                        {item.unit_price_formatted}
                                                    </td>
                                                    <td className="px-6 py-4 text-right font-semibold text-stone-900">
                                                        {item.subtotal_formatted}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>

                                {/* Order Pricing Summary */}
                                <div className="p-6 bg-stone-50/60 border-t border-stone-100 space-y-2.5">
                                    <div className="flex justify-between text-xs text-stone-600">
                                        <span>Items Subtotal</span>
                                        <span className="font-medium text-stone-800">{order.subtotal_formatted}</span>
                                    </div>
                                    <div className="flex justify-between text-xs text-stone-600">
                                        <span>Courier Delivery Charge</span>
                                        <span className="font-medium text-stone-800">{order.delivery_charge_formatted}</span>
                                    </div>
                                    <div className="pt-2.5 border-t border-stone-200 flex justify-between text-sm font-semibold text-stone-900">
                                        <span>Order Total (LKR)</span>
                                        <span className="font-serif text-base text-stone-900">{order.total_amount_formatted}</span>
                                    </div>
                                </div>
                            </div>

                            {/* Customer Notes (if provided) */}
                            {order.notes && (
                                <div className="bg-white border border-stone-200/80 rounded-2xl p-5 shadow-xs space-y-2">
                                    <div className="flex items-center gap-2 text-stone-700">
                                        <Icons.FileCheck className="w-4 h-4" />
                                        <h3 className="font-serif text-sm font-semibold">Special Delivery Instructions</h3>
                                    </div>
                                    <p className="text-xs text-stone-600 bg-stone-50 p-3 rounded-xl border border-stone-100">
                                        {order.notes}
                                    </p>
                                </div>
                            )}

                            {/* Cancellation Notice if Cancelled */}
                            {order.status === 'cancelled' && (
                                <div className="p-5 bg-rose-50 border border-rose-200 rounded-2xl text-xs text-rose-900 space-y-2">
                                    <div className="flex items-center gap-2 font-semibold">
                                        <Icons.AlertCircle className="w-4 h-4 text-rose-600" />
                                        <span>Order Cancelled</span>
                                    </div>
                                    <p className="text-rose-700">
                                        This order was cancelled prior to courier dispatch. No Cash on Delivery balance is due. If you wish to re-order, you can add items back to your cart from our catalog.
                                    </p>
                                </div>
                            )}
                        </div>

                        {/* Right Column: Delivery Address, Payment & Courier Tracking */}
                        <div className="space-y-6">
                            {/* Shipping Address Card */}
                            <div className="bg-white border border-stone-200/80 rounded-2xl p-5 shadow-xs space-y-4">
                                <div className="flex items-center gap-2 pb-3 border-b border-stone-100">
                                    <Icons.MapPin className="w-4 h-4 text-stone-700" />
                                    <h3 className="font-serif text-sm font-semibold text-stone-900">
                                        Delivery Address
                                    </h3>
                                </div>

                                <div className="space-y-1.5 text-xs text-stone-600">
                                    <p className="font-semibold text-stone-900 text-sm">
                                        {order.shipping.customer_name}
                                    </p>
                                    <p className="flex items-center gap-1.5 text-stone-600">
                                        <Icons.Phone className="w-3.5 h-3.5 text-stone-400" />
                                        <span>{order.shipping.customer_phone}</span>
                                    </p>
                                    {order.shipping.customer_email && (
                                        <p className="flex items-center gap-1.5 text-stone-600">
                                            <Icons.Mail className="w-3.5 h-3.5 text-stone-400" />
                                            <span>{order.shipping.customer_email}</span>
                                        </p>
                                    )}
                                    <div className="pt-2 text-stone-700 leading-relaxed">
                                        <p>{order.shipping.shipping_address}</p>
                                        <p>
                                            {order.shipping.city}, {order.shipping.district}
                                            {order.shipping.postal_code ? ` — ${order.shipping.postal_code}` : ''}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            {/* Payment Status Card */}
                            <div className="bg-white border border-stone-200/80 rounded-2xl p-5 shadow-xs space-y-4">
                                <div className="flex items-center gap-2 pb-3 border-b border-stone-100">
                                    <Icons.CreditCard className="w-4 h-4 text-stone-700" />
                                    <h3 className="font-serif text-sm font-semibold text-stone-900">
                                        Payment Information
                                    </h3>
                                </div>

                                <div className="space-y-3 text-xs">
                                    <div className="flex justify-between items-center">
                                        <span className="text-stone-500">Method</span>
                                        <span className="font-semibold text-stone-900 bg-stone-100 px-2 py-0.5 rounded font-mono text-[11px]">
                                            {order.payment_method === 'COD' ? 'Cash on Delivery (COD)' : order.payment_method}
                                        </span>
                                    </div>

                                    <div className="flex justify-between items-center">
                                        <span className="text-stone-500">Payment Status</span>
                                        <span className="font-medium text-stone-800">
                                            {order.payment_status}
                                        </span>
                                    </div>

                                    {order.status !== 'cancelled' && order.status !== 'refused' && order.status !== 'returned' && (
                                        <div className="p-3 bg-amber-50/70 border border-amber-200/60 rounded-xl text-[11px] text-amber-900 leading-relaxed">
                                            Please keep exact cash ready upon delivery. Our authorized courier will hand over the receipt and parcels upon collection.
                                        </div>
                                    )}
                                </div>
                            </div>

                            {/* Courier Dispatch Card */}
                            <div className="bg-white border border-stone-200/80 rounded-2xl p-5 shadow-xs space-y-4">
                                <div className="flex items-center gap-2 pb-3 border-b border-stone-100">
                                    <Icons.Truck className="w-4 h-4 text-stone-700" />
                                    <h3 className="font-serif text-sm font-semibold text-stone-900">
                                        Courier Tracking
                                    </h3>
                                </div>

                                {order.delivery ? (
                                    <div className="space-y-3 text-xs text-stone-600">
                                        <div className="flex justify-between items-center">
                                            <span className="text-stone-500">Courier Partner</span>
                                            <span className="font-semibold text-stone-900">
                                                {order.delivery.courier_name || 'Standard Courier'}
                                            </span>
                                        </div>

                                        {order.delivery.tracking_number && (
                                            <div className="flex justify-between items-center">
                                                <span className="text-stone-500">Waybill / Tracking No.</span>
                                                <span className="font-mono font-semibold text-stone-900 bg-stone-100 px-2 py-0.5 rounded text-[11px]">
                                                    {order.delivery.tracking_number}
                                                </span>
                                            </div>
                                        )}

                                        {order.delivery.dispatched_at && (
                                            <div className="flex justify-between items-center">
                                                <span className="text-stone-500">Dispatched</span>
                                                <span className="text-stone-700 font-medium">
                                                    {order.delivery.dispatched_at}
                                                </span>
                                            </div>
                                        )}

                                        {order.delivery.delivered_at && (
                                            <div className="flex justify-between items-center">
                                                <span className="text-stone-500">Delivered</span>
                                                <span className="text-emerald-700 font-semibold">
                                                    {order.delivery.delivered_at}
                                                </span>
                                            </div>
                                        )}
                                    </div>
                                ) : (
                                    <div className="p-4 bg-stone-50 rounded-xl text-center text-xs text-stone-500 space-y-1">
                                        <p className="font-medium text-stone-700">Awaiting Courier Allocation</p>
                                        <p className="text-[11px] text-stone-400">
                                            Waybill and courier partner details will appear once the package is dispatched from our fulfillment hub.
                                        </p>
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {/* Safe Cancellation Modal */}
            {showCancelModal && (
                <div className="fixed inset-0 z-50 bg-stone-900/60 backdrop-blur-xs flex items-center justify-center p-4">
                    <div className="bg-white rounded-2xl max-w-lg w-full p-6 sm:p-8 shadow-xl border border-stone-200 space-y-5 animate-in fade-in zoom-in-95 duration-150">
                        <div className="flex items-center justify-between pb-3 border-b border-stone-100">
                            <div className="flex items-center gap-2.5 text-rose-700">
                                <Icons.AlertCircle className="w-5 h-5 text-rose-600 shrink-0" />
                                <h3 className="font-serif text-lg font-semibold text-stone-900">
                                    Cancel Order #{order.order_number}
                                </h3>
                            </div>
                            <button
                                type="button"
                                onClick={() => setShowCancelModal(false)}
                                className="text-stone-400 hover:text-stone-600 cursor-pointer"
                            >
                                <Icons.XCircle className="w-5 h-5" />
                            </button>
                        </div>

                        <p className="text-xs text-stone-600 leading-relaxed">
                            Are you sure you want to cancel this order? Cancelling will immediately return reserved botanical cosmetic stock to our inventory and invalidate COD delivery. This action cannot be reversed.
                        </p>

                        <div className="space-y-3">
                            <label className="block text-xs font-semibold text-stone-700">
                                Please select a reason for cancellation:
                            </label>
                            <select
                                value={cancelReasonCategory}
                                onChange={(e) => setCancelReasonCategory(e.target.value)}
                                className="w-full text-xs bg-stone-50 border border-stone-200 rounded-xl px-3 py-2 text-stone-800 focus:outline-hidden focus:ring-1 focus:ring-stone-800"
                            >
                                <option value="Changed mind / No longer needed">Changed mind / No longer needed</option>
                                <option value="Placed order by mistake">Placed order by mistake</option>
                                <option value="Need to change delivery address or phone">Need to change delivery address or phone</option>
                                <option value="Need to modify products or quantity">Need to modify products or quantity</option>
                                <option value="Delivery timeframe too long">Delivery timeframe too long</option>
                                <option value="Other reason">Other reason</option>
                            </select>

                            <textarea
                                value={customReason}
                                onChange={(e) => setCustomReason(e.target.value)}
                                placeholder="Additional details (optional)..."
                                rows={2}
                                className="w-full text-xs bg-stone-50 border border-stone-200 rounded-xl p-3 text-stone-800 placeholder-stone-400 focus:outline-hidden focus:ring-1 focus:ring-stone-800"
                            />
                        </div>

                        <div className="flex items-center justify-end gap-3 pt-3 border-t border-stone-100">
                            <button
                                type="button"
                                disabled={isCancelling}
                                onClick={() => setShowCancelModal(false)}
                                className="px-4 py-2 text-xs font-medium text-stone-600 hover:text-stone-900 bg-stone-100 hover:bg-stone-200 rounded-xl transition-colors cursor-pointer"
                            >
                                Keep Order
                            </button>

                            <button
                                type="button"
                                disabled={isCancelling}
                                onClick={handleConfirmCancel}
                                className="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold text-white bg-rose-600 hover:bg-rose-700 rounded-xl transition-colors shadow-xs cursor-pointer disabled:opacity-50"
                            >
                                {isCancelling && <Icons.RefreshCw className="w-3.5 h-3.5 animate-spin" />}
                                <span>{isCancelling ? 'Cancelling...' : 'Confirm Cancellation'}</span>
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </PublicLayout>
    );
}
