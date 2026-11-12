import { useTranslation } from '@/hooks/useTranslation';
import PublicLayout from '@/Layouts/PublicLayout';
import { Head, useForm } from '@inertiajs/react';
import React from 'react';

interface OrderItem {
    id: number;
    product_name: string;
    unit_price: number | string;
    quantity: number;
    subtotal: number | string;
}

interface Delivery {
    id: number;
    courier_name: string | null;
    tracking_number: string | null;
    status: string;
    dispatched_at: string | null;
    delivered_at: string | null;
}

interface Order {
    id: number;
    order_number: string;
    customer_name: string;
    customer_phone: string;
    shipping_address: string;
    district: string;
    city: string;
    subtotal: number | string;
    delivery_charge: number | string;
    total_amount: number | string;
    payment_method: string;
    status: string;
    placed_at: string;
    items: OrderItem[];
    delivery?: Delivery | null;
}

interface Props {
    searched?: boolean;
    order?: Order | null;
    searchedOrderNumber?: string;
    searchedPhone?: string;
}

export default function TrackingIndex({
    searched = false,
    order = null,
    searchedOrderNumber = '',
    searchedPhone = '',
}: Props) {
    const { t } = useTranslation();

    // Check URL parameters for prefill
    const searchParams = typeof window !== 'undefined' ? new URLSearchParams(window.location.search) : null;
    const initialOrderNumber = searchedOrderNumber || searchParams?.get('order_number') || '';
    const initialPhone = searchedPhone || searchParams?.get('phone') || '';

    const { data, setData, post, processing, errors } = useForm({
        order_number: initialOrderNumber,
        phone: initialPhone,
    });

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        post('/track', { preserveScroll: true });
    };

    // Status step progression
    const steps = [
        { key: 'placed', label: t('status_placed', 'Placed') },
        { key: 'confirmed', label: t('status_confirmed', 'Confirmed') },
        { key: 'packed', label: t('status_packed', 'Packed') },
        { key: 'shipped', label: t('status_shipped', 'Shipped') },
        { key: 'delivered', label: t('status_delivered', 'Delivered') },
        { key: 'cash_collected', label: t('status_cash_collected', 'Cash Collected') },
    ];

    const currentStatusIndex = order
        ? steps.findIndex((s) => s.key === order.status)
        : -1;

    return (
        <PublicLayout>
            <Head title={`${t('track_order', 'Track Order')} | ${t('app_name', 'Aura Luxe')}`} />

            <div className="bg-[#F5EFE6] border-b border-stone-200/80 py-10 lg:py-14">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
                    <span className="text-xs uppercase tracking-[0.2em] font-semibold text-rose-900 block mb-1">
                        {t('app_name', 'Aura Luxe Cosmetics')}
                    </span>
                    <h1 className="font-serif text-3xl sm:text-4xl text-stone-900 font-medium">
                        {t('track_your_order', 'Track Your Order')}
                    </h1>
                    <p className="text-xs sm:text-sm text-stone-600 mt-2 max-w-md mx-auto">
                        {t(
                            'enter_order_and_phone',
                            'Enter your Order Number and Delivery Phone Number to track package status.'
                        )}
                    </p>
                </div>
            </div>

            <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-10 sm:py-14 space-y-10">
                {/* Search Form Card */}
                <div className="bg-white rounded-2xl border border-stone-200 p-6 sm:p-8 shadow-xs max-w-xl mx-auto">
                    <form onSubmit={handleSearch} className="space-y-4">
                        <div>
                            <label className="text-xs font-semibold text-stone-800 block mb-1">
                                {t('order_number', 'Order Number')} <span className="text-rose-600">*</span>
                            </label>
                            <input
                                type="text"
                                required
                                value={data.order_number}
                                onChange={(e) => setData('order_number', e.target.value)}
                                placeholder="e.g. ORD-20261102-ABCDE"
                                className="w-full text-xs font-mono rounded-lg border-stone-300 focus:border-rose-900 focus:ring-rose-900 py-2.5"
                            />
                            {errors.order_number && (
                                <p className="text-xs text-rose-600 mt-1">{errors.order_number}</p>
                            )}
                        </div>

                        <div>
                            <label className="text-xs font-semibold text-stone-800 block mb-1">
                                {t('phone_number', 'Delivery Phone Number')} <span className="text-rose-600">*</span>
                            </label>
                            <input
                                type="tel"
                                required
                                value={data.phone}
                                onChange={(e) => setData('phone', e.target.value)}
                                placeholder="0771234567"
                                className="w-full text-xs font-mono rounded-lg border-stone-300 focus:border-rose-900 focus:ring-rose-900 py-2.5"
                            />
                            <span className="text-[10px] text-stone-400 block mt-0.5">
                                For security, order details are only visible with matching phone verification.
                            </span>
                            {errors.phone && (
                                <p className="text-xs text-rose-600 mt-1">{errors.phone}</p>
                            )}
                        </div>

                        <button
                            type="submit"
                            disabled={processing}
                            className="w-full py-3.5 bg-stone-900 hover:bg-rose-900 disabled:bg-stone-300 text-white text-xs uppercase tracking-[0.2em] font-semibold rounded-xl shadow-md transition-all cursor-pointer disabled:cursor-not-allowed"
                        >
                            {processing ? 'Checking...' : t('track_btn', 'Check Status')}
                        </button>
                    </form>
                </div>

                {/* Search Results */}
                {searched && (
                    <div>
                        {order ? (
                            <div className="bg-white rounded-3xl border border-stone-200/80 p-6 sm:p-10 shadow-xs space-y-8">
                                <div className="border-b border-stone-100 pb-6 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                                    <div>
                                        <span className="text-xs font-mono uppercase tracking-wider text-stone-400 block">
                                            {t('order_number', 'Order Number')}
                                        </span>
                                        <h2 className="font-serif text-2xl font-bold text-stone-900">
                                            {order.order_number}
                                        </h2>
                                        <span className="text-xs text-stone-500 block mt-0.5">
                                            Recipient: {order.customer_name} ({order.customer_phone})
                                        </span>
                                    </div>
                                    <div className="text-right">
                                        <span className="inline-block px-3.5 py-1.5 bg-amber-100 text-amber-900 rounded-full text-xs font-semibold uppercase tracking-wider">
                                            Status: {order.status.replace('_', ' ')}
                                        </span>
                                    </div>
                                </div>

                                {/* Status Progression Bar */}
                                {order.status !== 'cancelled' && order.status !== 'returned' ? (
                                    <div>
                                        <h3 className="text-xs font-semibold uppercase tracking-wider text-stone-400 mb-6">
                                            Delivery Progress
                                        </h3>
                                        <div className="grid grid-cols-2 sm:grid-cols-6 gap-3">
                                            {steps.map((step, idx) => {
                                                const isCompleted = currentStatusIndex >= idx;
                                                const isCurrent = currentStatusIndex === idx;
                                                return (
                                                    <div
                                                        key={step.key}
                                                        className={`p-3 rounded-xl border text-center transition-all ${
                                                            isCurrent
                                                                ? 'bg-rose-900 text-white border-rose-900 shadow-xs font-semibold'
                                                                : isCompleted
                                                                ? 'bg-emerald-50 text-emerald-800 border-emerald-200'
                                                                : 'bg-stone-50 text-stone-400 border-stone-200 opacity-60'
                                                        }`}
                                                    >
                                                        <span className="block text-[10px] mb-1">
                                                            {isCompleted ? '✓' : idx + 1}
                                                        </span>
                                                        <span className="text-xs block font-medium">
                                                            {step.label}
                                                        </span>
                                                    </div>
                                                );
                                            })}
                                        </div>
                                    </div>
                                ) : (
                                    <div className="p-4 bg-rose-50 border border-rose-200 rounded-xl text-rose-800 text-xs">
                                        This order has been {order.status}. Please contact customer care for support.
                                    </div>
                                )}

                                {/* Courier & Delivery Information */}
                                {order.delivery && (order.delivery.courier_name || order.delivery.tracking_number) && (
                                    <div className="p-4 bg-stone-50 rounded-xl border border-stone-200 text-xs space-y-1 text-stone-700">
                                        <h4 className="font-semibold text-stone-900 uppercase tracking-wider text-[11px]">
                                            Courier Tracking Details
                                        </h4>
                                        {order.delivery.courier_name && (
                                            <p>Courier Service: <strong>{order.delivery.courier_name}</strong></p>
                                        )}
                                        {order.delivery.tracking_number && (
                                            <p>Tracking Code: <code className="font-mono">{order.delivery.tracking_number}</code></p>
                                        )}
                                    </div>
                                )}

                                {/* Items Snapshot */}
                                <div>
                                    <h3 className="text-xs font-semibold uppercase tracking-wider text-stone-400 mb-3">
                                        {t('items_ordered', 'Items')}
                                    </h3>
                                    <div className="divide-y divide-stone-100 border border-stone-200 rounded-xl overflow-hidden bg-white">
                                        {order.items.map((item) => (
                                            <div
                                                key={item.id}
                                                className="p-3.5 flex justify-between items-center text-xs"
                                            >
                                                <div>
                                                    <span className="font-medium text-stone-900 block">
                                                        {item.product_name}
                                                    </span>
                                                    <span className="text-stone-500">
                                                        Qty: {item.quantity} × LKR {Number(item.unit_price).toFixed(2)}
                                                    </span>
                                                </div>
                                                <span className="font-semibold text-stone-900">
                                                    LKR {Number(item.subtotal).toFixed(2)}
                                                </span>
                                            </div>
                                        ))}
                                    </div>
                                </div>

                                {/* Total Summary */}
                                <div className="flex justify-between items-center pt-4 border-t border-stone-200 text-xs">
                                    <span className="text-stone-500">
                                        Payment Method: <strong>Cash on Delivery (COD)</strong>
                                    </span>
                                    <span className="font-serif text-lg font-bold text-stone-900">
                                        Total: LKR {Number(order.total_amount).toFixed(2)}
                                    </span>
                                </div>
                            </div>
                        ) : (
                            <div className="bg-white rounded-2xl border border-stone-200 p-8 text-center max-w-md mx-auto shadow-xs">
                                <div className="text-3xl mb-3">🔍</div>
                                <h3 className="font-serif text-lg font-medium text-stone-900 mb-1">
                                    {t('no_order_found', 'No Order Found')}
                                </h3>
                                <p className="text-xs text-stone-500 leading-relaxed">
                                    We couldn't find an order matching that Order Number and Phone Number. Please check the digits and try again.
                                </p>
                            </div>
                        )}
                    </div>
                )}
            </div>
        </PublicLayout>
    );
}

