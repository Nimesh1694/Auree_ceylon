import { useTranslation } from '@/hooks/useTranslation';
import PublicLayout from '@/Layouts/PublicLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import React from 'react';

interface CartItem {
    product_id: number;
    name_en: string;
    name_si: string;
    unit_price: number;
    quantity: number;
    subtotal: number;
}

interface CartDetails {
    items: CartItem[];
    item_count: number;
    subtotal: number;
    delivery_charge: number;
    total: number;
}

interface Props {
    cart: CartDetails;
    districts: string[];
    savedAddresses?: Array<{
        id: number;
        label: string;
        recipient_name: string;
        phone: string;
        alternative_phone?: string | null;
        address_line_1: string;
        address_line_2?: string | null;
        city: string;
        district: string;
        postal_code?: string | null;
        delivery_instructions?: string | null;
        is_default: boolean;
    }>;
    prefill: {
        name: string;
        phone: string;
        email: string;
    };
}

export default function CheckoutIndex({ cart, districts, savedAddresses = [], prefill }: Props) {
    const { t, locale } = useTranslation();

    const defaultAddress = savedAddresses.find((a) => a.is_default) || savedAddresses[0];

    const { data, setData, post, processing, errors } = useForm({
        customer_name: defaultAddress?.recipient_name || prefill.name || '',
        customer_phone: defaultAddress?.phone || prefill.phone || '',
        customer_email: prefill.email || '',
        shipping_address: defaultAddress
            ? (defaultAddress.address_line_2
                ? `${defaultAddress.address_line_1}, ${defaultAddress.address_line_2}`
                : defaultAddress.address_line_1)
            : '',
        district: defaultAddress?.district || 'Colombo',
        city: defaultAddress?.city || '',
        postal_code: defaultAddress?.postal_code || '',
        notes: defaultAddress?.delivery_instructions || '',
        save_address: false,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/checkout');
    };

    return (
        <PublicLayout>
            <Head title={`${t('checkout', 'Checkout')} | ${t('app_name', 'Aura Luxe')}`} />

            <div className="bg-[#F5EFE6] border-b border-stone-200/80 py-10">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <span className="text-xs uppercase tracking-[0.2em] font-semibold text-rose-900 block mb-1">
                        {t('app_name', 'Aura Luxe Cosmetics')}
                    </span>
                    <h1 className="font-serif text-3xl sm:text-4xl text-stone-900 font-medium">
                        {t('checkout', 'Cash on Delivery Checkout')}
                    </h1>
                </div>
            </div>

            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 sm:py-14">
                <form onSubmit={handleSubmit} className="grid grid-cols-1 lg:grid-cols-3 gap-10">
                    {/* Left: Shipping & Customer Details Form */}
                    <div className="lg:col-span-2 space-y-8">
                        {/* Delivery Address Card */}
                        <div className="bg-white rounded-2xl border border-stone-200 p-6 sm:p-8 shadow-xs space-y-6">
                            <div className="border-b border-stone-100 pb-4">
                                <h2 className="font-serif text-xl font-medium text-stone-900">
                                    {t('delivery_details', 'Recipient & Delivery Address')}
                                </h2>
                                <p className="text-xs text-stone-500 mt-1">
                                    Please provide accurate details so our courier can deliver your order promptly.
                                </p>
                            </div>

                            {/* Saved Address Quick Selector (Authenticated Customers) */}
                            {savedAddresses.length > 0 && (
                                <div className="space-y-3 pb-6 border-b border-stone-100">
                                    <div className="flex items-center justify-between">
                                        <label className="text-xs font-semibold text-stone-900">
                                            Deliver to Saved Address
                                        </label>
                                        <Link
                                            href="/customer/addresses"
                                            className="text-[11px] text-amber-700 hover:text-amber-800 font-medium"
                                        >
                                            Manage Address Book →
                                        </Link>
                                    </div>
                                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                        {savedAddresses.map((addr) => {
                                            const isSelected =
                                                data.customer_name === addr.recipient_name &&
                                                data.customer_phone === addr.phone &&
                                                data.city === addr.city;

                                            return (
                                                <button
                                                    key={addr.id}
                                                    type="button"
                                                    onClick={() => {
                                                        setData((prev) => ({
                                                            ...prev,
                                                            customer_name: addr.recipient_name,
                                                            customer_phone: addr.phone,
                                                            shipping_address: addr.address_line_2
                                                                ? `${addr.address_line_1}, ${addr.address_line_2}`
                                                                : addr.address_line_1,
                                                            district: addr.district,
                                                            city: addr.city,
                                                            postal_code: addr.postal_code || '',
                                                            notes: addr.delivery_instructions || prev.notes,
                                                        }));
                                                    }}
                                                    className={`text-left p-3.5 rounded-xl border transition-all cursor-pointer ${
                                                        isSelected
                                                            ? 'border-stone-900 bg-stone-50 ring-1 ring-stone-900'
                                                            : 'border-stone-200 hover:border-stone-300 bg-white'
                                                    }`}
                                                >
                                                    <div className="flex items-center justify-between mb-1">
                                                        <span className="text-xs font-bold text-stone-900 font-mono uppercase">
                                                            {addr.label}
                                                        </span>
                                                        {addr.is_default && (
                                                            <span className="text-[10px] font-semibold bg-amber-100 text-amber-800 px-1.5 py-0.2 rounded font-mono">
                                                                Default
                                                            </span>
                                                        )}
                                                    </div>
                                                    <div className="text-xs font-medium text-stone-800">
                                                        {addr.recipient_name}
                                                    </div>
                                                    <div className="text-[11px] text-stone-500 line-clamp-1">
                                                        {addr.address_line_1}, {addr.city}
                                                    </div>
                                                    <div className="text-[11px] text-stone-500 font-mono mt-0.5">
                                                        {addr.phone}
                                                    </div>
                                                </button>
                                            );
                                        })}
                                    </div>
                                </div>
                            )}

                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                {/* Name */}
                                <div className="sm:col-span-2">
                                    <label className="text-xs font-semibold text-stone-800 block mb-1">
                                        {t('customer_name', 'Full Name')} <span className="text-rose-600">*</span>
                                    </label>
                                    <input
                                        type="text"
                                        required
                                        value={data.customer_name}
                                        onChange={(e) => setData('customer_name', e.target.value)}
                                        placeholder="e.g. Kasun Fernando"
                                        className="w-full text-xs rounded-lg border-stone-300 focus:border-rose-900 focus:ring-rose-900 py-2.5"
                                    />
                                    {errors.customer_name && (
                                        <p className="text-xs text-rose-600 mt-1">{errors.customer_name}</p>
                                    )}
                                </div>

                                {/* Phone */}
                                <div>
                                    <label className="text-xs font-semibold text-stone-800 block mb-1">
                                        {t('phone', 'Phone Number')} <span className="text-rose-600">*</span>
                                    </label>
                                    <input
                                        type="tel"
                                        required
                                        value={data.customer_phone}
                                        onChange={(e) => setData('customer_phone', e.target.value)}
                                        placeholder="0771234567"
                                        className="w-full text-xs rounded-lg border-stone-300 focus:border-rose-900 focus:ring-rose-900 py-2.5"
                                    />
                                    <span className="text-[10px] text-stone-400 block mt-0.5">
                                        Courier will call this number before delivery.
                                    </span>
                                    {errors.customer_phone && (
                                        <p className="text-xs text-rose-600 mt-1">{errors.customer_phone}</p>
                                    )}
                                </div>

                                {/* Email (optional) */}
                                <div>
                                    <label className="text-xs font-semibold text-stone-800 block mb-1">
                                        {t('email_optional', 'Email Address (Optional)')}
                                    </label>
                                    <input
                                        type="email"
                                        value={data.customer_email}
                                        onChange={(e) => setData('customer_email', e.target.value)}
                                        placeholder="name@example.com"
                                        className="w-full text-xs rounded-lg border-stone-300 focus:border-rose-900 focus:ring-rose-900 py-2.5"
                                    />
                                    {errors.customer_email && (
                                        <p className="text-xs text-rose-600 mt-1">{errors.customer_email}</p>
                                    )}
                                </div>

                                {/* Shipping Address */}
                                <div className="sm:col-span-2">
                                    <label className="text-xs font-semibold text-stone-800 block mb-1">
                                        {t('delivery_address', 'Delivery Street Address')}{' '}
                                        <span className="text-rose-600">*</span>
                                    </label>
                                    <textarea
                                        required
                                        rows={3}
                                        value={data.shipping_address}
                                        onChange={(e) => setData('shipping_address', e.target.value)}
                                        placeholder="House No, Road / Street name, Area"
                                        className="w-full text-xs rounded-lg border-stone-300 focus:border-rose-900 focus:ring-rose-900"
                                    />
                                    {errors.shipping_address && (
                                        <p className="text-xs text-rose-600 mt-1">{errors.shipping_address}</p>
                                    )}
                                </div>

                                {/* District */}
                                <div>
                                    <label className="text-xs font-semibold text-stone-800 block mb-1">
                                        {t('district', 'District')} <span className="text-rose-600">*</span>
                                    </label>
                                    <select
                                        required
                                        value={data.district}
                                        onChange={(e) => setData('district', e.target.value)}
                                        aria-label={t('district', 'District')}
                                        className="w-full text-xs rounded-lg border-stone-300 focus:border-rose-900 focus:ring-rose-900 py-2.5"
                                    >
                                        {districts.map((d) => (
                                            <option key={d} value={d}>
                                                {d}
                                            </option>
                                        ))}
                                    </select>
                                    {errors.district && (
                                        <p className="text-xs text-rose-600 mt-1">{errors.district}</p>
                                    )}
                                </div>

                                {/* City */}
                                <div>
                                    <label className="text-xs font-semibold text-stone-800 block mb-1">
                                        {t('city', 'City / Town')} <span className="text-rose-600">*</span>
                                    </label>
                                    <input
                                        type="text"
                                        required
                                        value={data.city}
                                        onChange={(e) => setData('city', e.target.value)}
                                        placeholder="e.g. Nugegoda, Kandy, Galle"
                                        className="w-full text-xs rounded-lg border-stone-300 focus:border-rose-900 focus:ring-rose-900 py-2.5"
                                    />
                                    {errors.city && (
                                        <p className="text-xs text-rose-600 mt-1">{errors.city}</p>
                                    )}
                                </div>

                                {/* Postal Code */}
                                <div>
                                    <label className="text-xs font-semibold text-stone-800 block mb-1">
                                        {t('postal_code', 'Postal Code (Optional)')}
                                    </label>
                                    <input
                                        type="text"
                                        value={data.postal_code}
                                        onChange={(e) => setData('postal_code', e.target.value)}
                                        placeholder="e.g. 10250"
                                        className="w-full text-xs rounded-lg border-stone-300 focus:border-rose-900 focus:ring-rose-900 py-2.5"
                                    />
                                </div>

                                {/* Notes */}
                                <div>
                                    <label className="text-xs font-semibold text-stone-800 block mb-1">
                                        {t('order_notes', 'Special Notes (Optional)')}
                                    </label>
                                    <input
                                        type="text"
                                        value={data.notes}
                                        onChange={(e) => setData('notes', e.target.value)}
                                        placeholder="e.g. Near supermarket, leave at security"
                                        className="w-full text-xs rounded-lg border-stone-300 focus:border-rose-900 focus:ring-rose-900 py-2.5"
                                    />
                                </div>

                                {/* Save Address Checkbox (Optional for Authenticated Customers, Unchecked by Default) */}
                                {prefill && (prefill.name || prefill.email) && (
                                    <div className="sm:col-span-2 pt-4 border-t border-stone-100">
                                        <label className="flex items-center gap-2.5 cursor-pointer text-xs font-medium text-stone-800">
                                            <input
                                                type="checkbox"
                                                checked={data.save_address}
                                                onChange={(e) => setData('save_address', e.target.checked)}
                                                className="rounded border-stone-300 text-stone-900 focus:ring-stone-900"
                                            />
                                            <span>Save this address to my Address Book for future orders</span>
                                        </label>
                                        <span className="text-[11px] text-stone-400 block ml-6 mt-0.5">
                                            Quickly select this address on future purchases from your customer account (up to 10 saved addresses).
                                        </span>
                                    </div>
                                )}
                            </div>
                        </div>

                        {/* Payment Method Banner */}
                        <div className="bg-white rounded-2xl border border-stone-200 p-6 sm:p-8 shadow-xs">
                            <h3 className="font-serif text-lg font-medium text-stone-900 mb-2">
                                {t('payment_method', 'Payment Method')}
                            </h3>
                            <div className="p-4 rounded-xl border-2 border-stone-900 bg-stone-50 flex items-center justify-between">
                                <div className="flex items-center gap-3">
                                    <span className="text-xl">💵</span>
                                    <div>
                                        <strong className="text-xs font-semibold text-stone-900 block">
                                            {t('cash_on_delivery', 'Cash on Delivery (COD)')}
                                        </strong>
                                        <span className="text-[11px] text-stone-500">
                                            Pay in cash upon physical receipt of package from the courier.
                                        </span>
                                    </div>
                                </div>
                                <span className="text-xs font-bold text-emerald-800 uppercase tracking-wider bg-emerald-100 px-2.5 py-1 rounded-full">
                                    Selected
                                </span>
                            </div>
                        </div>
                    </div>

                    {/* Right: Order Summary Sidebar */}
                    <div className="space-y-6">
                        <div className="bg-white rounded-2xl border border-stone-200 p-6 sm:p-8 shadow-xs space-y-6">
                            <h2 className="font-serif text-xl font-medium text-stone-900">
                                {t('order_summary', 'Order Summary')}
                            </h2>

                            {/* Items List */}
                            <div className="divide-y divide-stone-100 max-h-64 overflow-y-auto pr-1">
                                {cart.items.map((item) => (
                                    <div key={item.product_id} className="py-2.5 flex justify-between text-xs">
                                        <div>
                                            <span className="font-medium text-stone-900 block line-clamp-1">
                                                {locale === 'si' && item.name_si ? item.name_si : item.name_en}
                                            </span>
                                            <span className="text-[11px] text-stone-500">
                                                Qty: {item.quantity} × LKR {item.unit_price.toFixed(2)}
                                            </span>
                                        </div>
                                        <span className="font-semibold text-stone-900 shrink-0 ml-2">
                                            LKR {item.subtotal.toFixed(2)}
                                        </span>
                                    </div>
                                ))}
                            </div>

                            {/* Totals */}
                            <div className="border-t border-stone-200 pt-4 space-y-2.5 text-xs text-stone-600">
                                <div className="flex justify-between">
                                    <span>{t('subtotal', 'Subtotal')}</span>
                                    <span className="font-semibold text-stone-900">
                                        LKR {cart.subtotal.toFixed(2)}
                                    </span>
                                </div>
                                <div className="flex justify-between">
                                    <span>{t('delivery_charge', 'Delivery Fee')}</span>
                                    <span className="font-semibold text-stone-900">
                                        LKR {cart.delivery_charge.toFixed(2)}
                                    </span>
                                </div>
                                <div className="border-t border-stone-200 pt-3 flex justify-between items-baseline text-stone-900">
                                    <span className="text-sm font-semibold">{t('total', 'Total Payable')}</span>
                                    <span className="font-serif text-2xl font-bold text-stone-900">
                                        LKR {cart.total.toFixed(2)}
                                    </span>
                                </div>
                            </div>

                            {/* Submit Order Button */}
                            <button
                                type="submit"
                                disabled={processing}
                                className="w-full py-4 bg-stone-900 hover:bg-rose-900 disabled:bg-stone-400 text-white text-xs uppercase tracking-[0.2em] font-semibold rounded-xl shadow-md transition-all cursor-pointer disabled:cursor-not-allowed"
                            >
                                {processing ? 'Placing Order...' : t('place_order', 'Confirm Order (COD)')}
                            </button>

                            <p className="text-[11px] text-stone-400 text-center leading-relaxed">
                                By placing this order, you agree to our Terms and Cash on Delivery policy.
                            </p>
                        </div>

                        <div className="text-center">
                            <Link
                                href="/cart"
                                className="text-xs text-stone-500 hover:text-stone-900 underline"
                            >
                                ← Modify Cart Items
                            </Link>
                        </div>
                    </div>
                </form>
            </div>
        </PublicLayout>
    );
}

