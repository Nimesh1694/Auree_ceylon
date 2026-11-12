import React, { useState } from 'react';
import { Head, useForm, Link } from '@inertiajs/react';
import AgentLayout from '@/Layouts/AgentLayout';
import { PageProps } from '@/types';
import {
    ShoppingCart,
    Plus,
    Trash2,
    User,
    Phone,
    MapPin,
    AlertCircle,
    CheckCircle2,
    ArrowLeft,
    Truck,
} from '@/Components/Icons';

interface ProductItem {
    id: number;
    name_en: string;
    name_si: string;
    sku: string;
    price: string | number;
    stock_quantity: number;
}

interface CreateOrderProps extends PageProps {
    products: ProductItem[];
    districts: string[];
}

interface OrderItemEntry {
    product_id: number;
    quantity: number;
}

export default function CreateOrder({ products, districts }: CreateOrderProps) {
    const [selectedProductId, setSelectedProductId] = useState<number | ''>('');
    const [selectedQty, setSelectedQty] = useState<number>(1);

    const { data, setData, post, processing, errors } = useForm({
        customer_name: '',
        customer_phone: '',
        customer_email: '',
        shipping_address: '',
        district: districts[0] || 'Colombo',
        city: '',
        postal_code: '',
        notes: '',
        items: [] as OrderItemEntry[],
    });

    const deliveryFee = 350; // Standard Flat COD delivery fee in LKR

    const addItem = () => {
        if (!selectedProductId) return;
        const prod = products.find((p) => p.id === Number(selectedProductId));
        if (!prod) return;

        const existingIndex = data.items.findIndex((i) => i.product_id === prod.id);
        const maxQty = prod.stock_quantity;

        if (existingIndex > -1) {
            const updated = [...data.items];
            const newQty = Math.min(updated[existingIndex].quantity + selectedQty, maxQty);
            updated[existingIndex].quantity = newQty;
            setData('items', updated);
        } else {
            setData('items', [
                ...data.items,
                { product_id: prod.id, quantity: Math.min(selectedQty, maxQty) },
            ]);
        }

        setSelectedProductId('');
        setSelectedQty(1);
    };

    const updateItemQty = (productId: number, newQty: number) => {
        const prod = products.find((p) => p.id === productId);
        if (!prod) return;

        const maxQty = prod.stock_quantity;
        const validQty = Math.max(1, Math.min(newQty, maxQty));

        setData(
            'items',
            data.items.map((item) =>
                item.product_id === productId ? { ...item, quantity: validQty } : item
            )
        );
    };

    const removeItem = (productId: number) => {
        setData('items', data.items.filter((item) => item.product_id !== productId));
    };

    const subtotal = data.items.reduce((sum, item) => {
        const prod = products.find((p) => p.id === item.product_id);
        const price = prod ? Number(prod.price) : 0;
        return sum + price * item.quantity;
    }, 0);

    const total = data.items.length > 0 ? subtotal + deliveryFee : 0;

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('agent.orders.store'));
    };

    return (
        <AgentLayout>
            <Head title="Place Order for Customer" />

            <div className="max-w-5xl mx-auto space-y-6">
                {/* Header with back button */}
                <div className="flex items-center justify-between">
                    <div>
                        <div className="flex items-center gap-2 mb-1">
                            <Link
                                href={route('agent.orders.index')}
                                className="text-sm font-medium text-slate-500 hover:text-slate-800 flex items-center gap-1 transition-colors"
                            >
                                <ArrowLeft className="w-4 h-4" /> Back to Placed Orders
                            </Link>
                        </div>
                        <h1 className="text-2xl font-bold text-slate-900 tracking-tight">
                            Place Customer Order (Cash on Delivery)
                        </h1>
                        <p className="text-sm text-slate-500 mt-1">
                            Assist offline customers or clients by placing an order on their behalf with your agent attribution.
                        </p>
                    </div>
                </div>

                <form onSubmit={handleSubmit} className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {/* Left: Customer Information & Delivery Address */}
                    <div className="lg:col-span-2 space-y-6">
                        <div className="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm space-y-5">
                            <h2 className="text-base font-semibold text-slate-900 flex items-center gap-2 pb-3 border-b border-slate-100">
                                <User className="w-5 h-5 text-emerald-600" />
                                Customer Contact Information
                            </h2>

                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div className="sm:col-span-2">
                                    <label className="block text-xs font-medium text-slate-700 mb-1">
                                        Customer Full Name *
                                    </label>
                                    <input
                                        type="text"
                                        value={data.customer_name}
                                        onChange={(e) => setData('customer_name', e.target.value)}
                                        placeholder="e.g. Kasun Perera"
                                        className="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                        required
                                    />
                                    {errors.customer_name && (
                                        <p className="text-xs text-red-600 mt-1">{errors.customer_name}</p>
                                    )}
                                </div>

                                <div>
                                    <label className="block text-xs font-medium text-slate-700 mb-1">
                                        Customer Mobile Phone *
                                    </label>
                                    <div className="relative">
                                        <Phone className="w-4 h-4 text-slate-400 absolute left-3 top-3" />
                                        <input
                                            type="text"
                                            value={data.customer_phone}
                                            onChange={(e) => setData('customer_phone', e.target.value)}
                                            placeholder="077 123 4567"
                                            className="w-full pl-9 pr-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                            required
                                        />
                                    </div>
                                    {errors.customer_phone && (
                                        <p className="text-xs text-red-600 mt-1">{errors.customer_phone}</p>
                                    )}
                                </div>

                                <div>
                                    <label className="block text-xs font-medium text-slate-700 mb-1">
                                        Customer Email (Optional)
                                    </label>
                                    <input
                                        type="email"
                                        value={data.customer_email}
                                        onChange={(e) => setData('customer_email', e.target.value)}
                                        placeholder="kasun@example.com"
                                        className="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                    />
                                    {errors.customer_email && (
                                        <p className="text-xs text-red-600 mt-1">{errors.customer_email}</p>
                                    )}
                                </div>
                            </div>
                        </div>

                        {/* Delivery Address */}
                        <div className="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm space-y-5">
                            <h2 className="text-base font-semibold text-slate-900 flex items-center gap-2 pb-3 border-b border-slate-100">
                                <MapPin className="w-5 h-5 text-emerald-600" />
                                Delivery Destination
                            </h2>

                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div className="sm:col-span-2">
                                    <label className="block text-xs font-medium text-slate-700 mb-1">
                                        Delivery Street Address *
                                    </label>
                                    <textarea
                                        rows={2}
                                        value={data.shipping_address}
                                        onChange={(e) => setData('shipping_address', e.target.value)}
                                        placeholder="House number, street name, landmarks..."
                                        className="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                        required
                                    />
                                    {errors.shipping_address && (
                                        <p className="text-xs text-red-600 mt-1">{errors.shipping_address}</p>
                                    )}
                                </div>

                                <div>
                                    <label className="block text-xs font-medium text-slate-700 mb-1">
                                        District *
                                    </label>
                                    <select
                                        value={data.district}
                                        onChange={(e) => setData('district', e.target.value)}
                                        className="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                        required
                                    >
                                        {districts.map((d) => (
                                            <option key={d} value={d}>
                                                {d}
                                            </option>
                                        ))}
                                    </select>
                                    {errors.district && (
                                        <p className="text-xs text-red-600 mt-1">{errors.district}</p>
                                    )}
                                </div>

                                <div>
                                    <label className="block text-xs font-medium text-slate-700 mb-1">
                                        City / Town *
                                    </label>
                                    <input
                                        type="text"
                                        value={data.city}
                                        onChange={(e) => setData('city', e.target.value)}
                                        placeholder="e.g. Maharagama"
                                        className="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                        required
                                    />
                                    {errors.city && <p className="text-xs text-red-600 mt-1">{errors.city}</p>}
                                </div>

                                <div>
                                    <label className="block text-xs font-medium text-slate-700 mb-1">
                                        Postal Code (Optional)
                                    </label>
                                    <input
                                        type="text"
                                        value={data.postal_code}
                                        onChange={(e) => setData('postal_code', e.target.value)}
                                        placeholder="e.g. 10280"
                                        className="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                    />
                                </div>

                                <div>
                                    <label className="block text-xs font-medium text-slate-700 mb-1">
                                        Order Notes (Optional)
                                    </label>
                                    <input
                                        type="text"
                                        value={data.notes}
                                        onChange={(e) => setData('notes', e.target.value)}
                                        placeholder="Special courier delivery instructions"
                                        className="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                    />
                                </div>
                            </div>
                        </div>

                        {/* Product Selector Card */}
                        <div className="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm space-y-4">
                            <h2 className="text-base font-semibold text-slate-900 flex items-center gap-2 pb-3 border-b border-slate-100">
                                <ShoppingCart className="w-5 h-5 text-emerald-600" />
                                Add Cosmetics Products
                            </h2>

                            <div className="grid grid-cols-1 sm:grid-cols-12 gap-3 items-end">
                                <div className="sm:col-span-8">
                                    <label className="block text-xs font-medium text-slate-700 mb-1">
                                        Select In-Stock Item
                                    </label>
                                    <select
                                        value={selectedProductId}
                                        onChange={(e) => setSelectedProductId(e.target.value ? Number(e.target.value) : '')}
                                        className="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                    >
                                        <option value="">-- Choose a cosmetic product --</option>
                                        {products.map((p) => (
                                            <option key={p.id} value={p.id}>
                                                {p.name_en} ({p.sku}) — LKR {Number(p.price).toLocaleString()} [Stock: {p.stock_quantity}]
                                            </option>
                                        ))}
                                    </select>
                                </div>

                                <div className="sm:col-span-2">
                                    <label className="block text-xs font-medium text-slate-700 mb-1">Qty</label>
                                    <input
                                        type="number"
                                        min="1"
                                        max="99"
                                        value={selectedQty}
                                        onChange={(e) => setSelectedQty(Math.max(1, parseInt(e.target.value, 10) || 1))}
                                        className="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm text-center"
                                    />
                                </div>

                                <div className="sm:col-span-2">
                                    <button
                                        type="button"
                                        onClick={addItem}
                                        disabled={!selectedProductId}
                                        className="w-full inline-flex items-center justify-center gap-1.5 px-3 py-2 bg-slate-900 hover:bg-slate-800 disabled:opacity-50 text-white rounded-lg text-sm font-semibold transition-colors"
                                    >
                                        <Plus className="w-4 h-4" /> Add
                                    </button>
                                </div>
                            </div>

                            {errors.items && (
                                <p className="text-xs text-red-600 flex items-center gap-1 mt-2">
                                    <AlertCircle className="w-4 h-4" /> {errors.items}
                                </p>
                            )}
                        </div>
                    </div>

                    {/* Right: Order Summary & Placement Action */}
                    <div className="lg:col-span-1 space-y-6">
                        <div className="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm space-y-5 sticky top-6">
                            <h2 className="text-base font-semibold text-slate-900 pb-3 border-b border-slate-100">
                                Order Summary
                            </h2>

                            {/* Cart Items List */}
                            {data.items.length === 0 ? (
                                <div className="text-center py-8">
                                    <ShoppingCart className="w-10 h-10 text-slate-300 mx-auto mb-2" />
                                    <p className="text-xs text-slate-500">No products added yet.</p>
                                </div>
                            ) : (
                                <div className="space-y-3 divide-y divide-slate-100">
                                    {data.items.map((item) => {
                                        const prod = products.find((p) => p.id === item.product_id);
                                        if (!prod) return null;
                                        const linePrice = Number(prod.price) * item.quantity;

                                        return (
                                            <div key={item.product_id} className="pt-3 first:pt-0 flex items-start justify-between gap-2">
                                                <div className="flex-1 min-w-0">
                                                    <p className="text-xs font-semibold text-slate-900 truncate">
                                                        {prod.name_en}
                                                    </p>
                                                    <p className="text-[11px] text-slate-500">
                                                        LKR {Number(prod.price).toLocaleString()} × {item.quantity}
                                                    </p>
                                                    <div className="flex items-center gap-2 mt-1">
                                                        <button
                                                            type="button"
                                                            onClick={() => updateItemQty(item.product_id, item.quantity - 1)}
                                                            className="w-5 h-5 rounded bg-slate-100 hover:bg-slate-200 text-xs font-bold flex items-center justify-center text-slate-700"
                                                        >
                                                            -
                                                        </button>
                                                        <span className="text-xs font-semibold">{item.quantity}</span>
                                                        <button
                                                            type="button"
                                                            onClick={() => updateItemQty(item.product_id, item.quantity + 1)}
                                                            className="w-5 h-5 rounded bg-slate-100 hover:bg-slate-200 text-xs font-bold flex items-center justify-center text-slate-700"
                                                        >
                                                            +
                                                        </button>
                                                    </div>
                                                </div>

                                                <div className="text-right">
                                                    <p className="text-xs font-bold text-slate-900">
                                                        LKR {linePrice.toLocaleString()}
                                                    </p>
                                                    <button
                                                        type="button"
                                                        onClick={() => removeItem(item.product_id)}
                                                        className="text-slate-400 hover:text-red-500 text-xs mt-1 transition-colors"
                                                    >
                                                        <Trash2 className="w-3.5 h-3.5" />
                                                    </button>
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>
                            )}

                            {/* Financial totals */}
                            {data.items.length > 0 && (
                                <div className="space-y-2 pt-4 border-t border-slate-100 text-xs">
                                    <div className="flex justify-between text-slate-600">
                                        <span>Subtotal</span>
                                        <span className="font-semibold text-slate-900">LKR {subtotal.toLocaleString()}</span>
                                    </div>
                                    <div className="flex justify-between text-slate-600">
                                        <span className="flex items-center gap-1">
                                            <Truck className="w-3.5 h-3.5 text-slate-400" /> Standard Courier Delivery
                                        </span>
                                        <span className="font-semibold text-slate-900">LKR {deliveryFee.toLocaleString()}</span>
                                    </div>
                                    <div className="flex justify-between text-sm font-bold text-slate-900 pt-2 border-t border-slate-100">
                                        <span>Total (COD Due)</span>
                                        <span className="text-emerald-700">LKR {total.toLocaleString()}</span>
                                    </div>
                                </div>
                            )}

                            {/* Payment Method Notice */}
                            <div className="p-3 bg-emerald-50 border border-emerald-100 rounded-xl text-xs text-emerald-800">
                                <p className="font-semibold">Payment Term: Cash on Delivery (COD)</p>
                                <p className="mt-0.5 text-emerald-700">
                                    Customer pays the courier upon arrival at doorstep.
                                </p>
                            </div>

                            {/* Submit */}
                            <button
                                type="submit"
                                disabled={processing || data.items.length === 0}
                                className="w-full py-3 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 text-white rounded-xl text-sm font-bold shadow-sm transition-colors flex items-center justify-center gap-2"
                            >
                                <CheckCircle2 className="w-4 h-4" />
                                {processing ? 'Submitting Order...' : 'Confirm & Place Customer Order'}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </AgentLayout>
    );
}
