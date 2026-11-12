import { useTranslation } from '@/hooks/useTranslation';
import PublicLayout from '@/Layouts/PublicLayout';
import { Head, Link } from '@inertiajs/react';
import React from 'react';

interface OrderItem {
    id: number;
    product_name: string;
    product_sku: string | null;
    unit_price: number | string;
    quantity: number;
    subtotal: number | string;
}

interface Order {
    id: number;
    order_number: string;
    customer_name: string;
    customer_phone: string;
    customer_email: string | null;
    shipping_address: string;
    district: string;
    city: string;
    postal_code: string | null;
    subtotal: number | string;
    delivery_charge: number | string;
    total_amount: number | string;
    payment_method: string;
    status: string;
    tracking_token: string;
    placed_at: string;
    items: OrderItem[];
}

interface Props {
    order: Order;
}

export default function CheckoutConfirmation({ order }: Props) {
    const { t } = useTranslation();

    const subtotalFormatted = Number(order.subtotal).toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
    const deliveryFormatted = Number(order.delivery_charge).toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
    const totalFormatted = Number(order.total_amount).toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

    return (
        <PublicLayout>
            <Head title={`${t('order_confirmation_title', 'Order Confirmed')} | ${order.order_number}`} />

            <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12 sm:py-16">
                {/* Success Card Header */}
                <div className="bg-white rounded-3xl border border-stone-200/80 p-8 sm:p-12 text-center shadow-xs">
                    <div className="w-16 h-16 rounded-full bg-emerald-100 text-emerald-800 flex items-center justify-center text-2xl mx-auto mb-5">
                        ✓
                    </div>
                    <span className="text-xs uppercase tracking-[0.2em] font-semibold text-emerald-800 block mb-1">
                        Order Received Successfully
                    </span>
                    <h1 className="font-serif text-3xl sm:text-4xl text-stone-900 font-medium">
                        {t('order_confirmation_title', 'Thank You For Your Order')}
                    </h1>
                    <p className="text-xs sm:text-sm text-stone-500 mt-2 max-w-lg mx-auto leading-relaxed">
                        {t(
                            'order_confirmation_desc',
                            'Thank you for shopping with Aura Luxe. Your order has been placed and is being prepared for dispatch.'
                        )}
                    </p>

                    {/* Order Number & Status Highlight */}
                    <div className="mt-8 p-6 bg-stone-50 rounded-2xl border border-stone-200/60 inline-flex flex-col sm:flex-row items-center gap-6 text-xs">
                        <div>
                            <span className="text-stone-400 block text-[11px] uppercase tracking-wider">
                                {t('order_number', 'Order Number')}
                            </span>
                            <span className="font-mono text-base font-bold text-stone-900 select-all">
                                {order.order_number}
                            </span>
                        </div>
                        <div className="hidden sm:block w-px h-8 bg-stone-300" />
                        <div>
                            <span className="text-stone-400 block text-[11px] uppercase tracking-wider">
                                {t('order_status', 'Status')}
                            </span>
                            <span className="inline-block mt-0.5 px-3 py-1 bg-amber-100 text-amber-900 font-semibold uppercase tracking-wider rounded-full text-[10px]">
                                {t('status_placed', 'Order Placed')}
                            </span>
                        </div>
                        <div className="hidden sm:block w-px h-8 bg-stone-300" />
                        <div>
                            <span className="text-stone-400 block text-[11px] uppercase tracking-wider">
                                {t('payment_method', 'Payment')}
                            </span>
                            <span className="font-semibold text-emerald-800">
                                💵 Cash on Delivery
                            </span>
                        </div>
                    </div>
                </div>

                {/* Details Breakdown */}
                <div className="mt-8 grid grid-cols-1 md:grid-cols-2 gap-8">
                    {/* Delivery Information */}
                    <div className="bg-white rounded-2xl border border-stone-200 p-6 sm:p-8 shadow-xs">
                        <h2 className="font-serif text-lg font-medium text-stone-900 mb-4 border-b border-stone-100 pb-3">
                            {t('shipping_to', 'Delivery Information')}
                        </h2>
                        <dl className="space-y-3 text-xs">
                            <div>
                                <dt className="text-stone-400">{t('customer_name', 'Customer Name')}</dt>
                                <dd className="font-semibold text-stone-900 mt-0.5">{order.customer_name}</dd>
                            </div>
                            <div>
                                <dt className="text-stone-400">{t('phone', 'Phone Number')}</dt>
                                <dd className="font-mono text-stone-900 mt-0.5">{order.customer_phone}</dd>
                            </div>
                            {order.customer_email && (
                                <div>
                                    <dt className="text-stone-400">Email</dt>
                                    <dd className="text-stone-900 mt-0.5">{order.customer_email}</dd>
                                </div>
                            )}
                            <div>
                                <dt className="text-stone-400">{t('delivery_address', 'Address')}</dt>
                                <dd className="text-stone-900 mt-0.5 leading-relaxed">
                                    {order.shipping_address}, {order.city}, {order.district}
                                    {order.postal_code ? ` - ${order.postal_code}` : ''}
                                </dd>
                            </div>
                        </dl>
                    </div>

                    {/* Order Financials & Items */}
                    <div className="bg-white rounded-2xl border border-stone-200 p-6 sm:p-8 shadow-xs flex flex-col justify-between">
                        <div>
                            <h2 className="font-serif text-lg font-medium text-stone-900 mb-4 border-b border-stone-100 pb-3">
                                {t('items_ordered', 'Ordered Products')}
                            </h2>
                            <div className="divide-y divide-stone-100 max-h-48 overflow-y-auto pr-1">
                                {order.items.map((item) => (
                                    <div key={item.id} className="py-2.5 flex justify-between text-xs">
                                        <div>
                                            <span className="font-medium text-stone-900 block">{item.product_name}</span>
                                            <span className="text-[11px] text-stone-500">
                                                Qty: {item.quantity} × LKR {Number(item.unit_price).toFixed(2)}
                                            </span>
                                        </div>
                                        <span className="font-semibold text-stone-900 shrink-0 ml-2">
                                            LKR {Number(item.subtotal).toFixed(2)}
                                        </span>
                                    </div>
                                ))}
                            </div>
                        </div>

                        {/* Financial summary */}
                        <div className="border-t border-stone-200 pt-4 mt-6 space-y-2 text-xs text-stone-600">
                            <div className="flex justify-between">
                                <span>{t('subtotal', 'Subtotal')}</span>
                                <span className="font-semibold text-stone-900">LKR {subtotalFormatted}</span>
                            </div>
                            <div className="flex justify-between">
                                <span>{t('delivery_charge', 'Delivery Fee')}</span>
                                <span className="font-semibold text-stone-900">LKR {deliveryFormatted}</span>
                            </div>
                            <div className="border-t border-stone-200 pt-2 flex justify-between items-baseline text-stone-900">
                                <span className="font-semibold text-sm">{t('total', 'Total Amount')}</span>
                                <span className="font-serif text-2xl font-bold text-stone-900">
                                    LKR {totalFormatted}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Actions */}
                <div className="mt-10 flex flex-wrap items-center justify-center gap-4">
                    <Link
                        href={`/track?order_number=${order.order_number}&phone=${order.customer_phone}`}
                        className="px-6 py-3.5 bg-stone-900 hover:bg-rose-900 text-white text-xs uppercase tracking-[0.2em] font-semibold rounded-xl shadow-md transition-colors"
                    >
                        {t('track_your_order', 'Track Order Status')}
                    </Link>
                    <Link
                        href="/products"
                        className="px-6 py-3.5 border border-stone-300 hover:bg-stone-50 text-stone-800 text-xs uppercase tracking-[0.15em] font-semibold rounded-xl transition-colors"
                    >
                        {t('continue_shopping', 'Continue Shopping')}
                    </Link>
                </div>
            </div>
        </PublicLayout>
    );
}

