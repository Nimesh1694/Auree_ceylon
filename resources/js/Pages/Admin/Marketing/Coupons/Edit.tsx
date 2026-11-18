import React from 'react';
import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link, useForm } from '@inertiajs/react';

interface CategoryItem {
    id: number;
    name_en: string;
}

interface ProductItem {
    id: number;
    name_en: string;
    sku: string;
}

interface Coupon {
    id: number;
    code: string;
    name: string;
    type: 'percentage' | 'fixed_amount' | 'free_shipping';
    value: string;
    min_order_subtotal: string;
    max_discount_amount: string | null;
    is_unlimited_cap: boolean;
    usage_limit_total: number | null;
    usage_limit_per_customer: number | null;
    is_active: boolean;
    is_public: boolean;
    applies_to: 'all' | 'specific_categories' | 'specific_products';
    allow_agent_referral: boolean;
    starts_at: string | null;
    expires_at: string | null;
    categories?: { id: number }[];
    products?: { id: number }[];
}

interface Props {
    coupon: Coupon;
    categories: CategoryItem[];
    products: ProductItem[];
    isOwner: boolean;
}

export default function EditCoupon({ coupon, categories, products, isOwner }: Props) {
    const { data, setData, put, processing, errors } = useForm({
        name: coupon.name,
        value: coupon.value,
        min_order_subtotal: coupon.min_order_subtotal || '',
        max_discount_amount: coupon.max_discount_amount || '',
        is_unlimited_cap: coupon.is_unlimited_cap,
        usage_limit_total: coupon.usage_limit_total ? String(coupon.usage_limit_total) : '',
        usage_limit_per_customer: coupon.usage_limit_per_customer ? String(coupon.usage_limit_per_customer) : '',
        is_active: coupon.is_active,
        is_public: coupon.is_public,
        applies_to: coupon.applies_to,
        allow_agent_referral: coupon.allow_agent_referral,
        starts_at: coupon.starts_at ? coupon.starts_at.slice(0, 16) : '',
        expires_at: coupon.expires_at ? coupon.expires_at.slice(0, 16) : '',
        category_ids: coupon.categories ? coupon.categories.map((c) => c.id) : [],
        product_ids: coupon.products ? coupon.products.map((p) => p.id) : [],
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        put(`/admin/coupons/${coupon.id}`);
    };

    return (
        <AdminLayout>
            <Head title={`Edit Coupon: ${coupon.code}`} />

            <div className="py-6 px-4 sm:px-6 lg:px-8 max-w-4xl mx-auto">
                <div className="mb-6 flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">Edit Coupon: <span className="font-mono text-indigo-600">{coupon.code}</span></h1>
                        <p className="text-sm text-gray-600 mt-1">
                            Type: <strong className="capitalize">{coupon.type.replace('_', ' ')}</strong> (Coupon code cannot be changed once created)
                        </p>
                    </div>
                    <Link
                        href={`/admin/coupons/${coupon.id}`}
                        className="text-sm font-medium text-gray-600 hover:text-gray-900"
                    >
                        &larr; Back to Details
                    </Link>
                </div>

                <form onSubmit={handleSubmit} className="bg-white shadow-sm border border-gray-200 rounded-lg p-6 space-y-6">
                    <div>
                        <h2 className="text-base font-semibold text-gray-900 border-b pb-2 mb-4">Basic Information</h2>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700">Internal Campaign Name *</label>
                                <input
                                    type="text"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    required
                                    className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                />
                                {errors.name && <p className="mt-1 text-xs text-red-600">{errors.name}</p>}
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700">
                                    {coupon.type === 'percentage' ? 'Percentage Rate (%) *' : (coupon.type === 'fixed_amount' ? 'Fixed Amount (LKR) *' : 'Value (N/A for Free Shipping)')}
                                </label>
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    disabled={coupon.type === 'free_shipping'}
                                    value={data.value}
                                    onChange={(e) => setData('value', e.target.value)}
                                    required={coupon.type !== 'free_shipping'}
                                    className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm disabled:bg-gray-100"
                                />
                                {errors.value && <p className="mt-1 text-xs text-red-600">{errors.value}</p>}
                            </div>
                        </div>

                        {coupon.type === 'percentage' && (
                            <div className="mt-4 p-4 bg-gray-50 rounded-md border border-gray-200 space-y-3">
                                <div className="text-xs font-semibold text-gray-700 uppercase tracking-wide">
                                    Percentage Ceiling Protection (Default Cap: LKR 5,000.00)
                                </div>
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4 items-center">
                                    <div>
                                        <label className="block text-xs font-medium text-gray-700">Custom Maximum Discount Amount (LKR)</label>
                                        <input
                                            type="number"
                                            step="0.01"
                                            disabled={data.is_unlimited_cap}
                                            value={data.max_discount_amount}
                                            onChange={(e) => setData('max_discount_amount', e.target.value)}
                                            placeholder="5000.00"
                                            className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm disabled:bg-gray-100"
                                        />
                                    </div>

                                    <div className="flex items-center gap-2 pt-4">
                                        <input
                                            type="checkbox"
                                            id="is_unlimited_cap"
                                            checked={data.is_unlimited_cap}
                                            onChange={(e) => setData('is_unlimited_cap', e.target.checked)}
                                            disabled={!isOwner}
                                            className="h-4 w-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500 disabled:opacity-50"
                                        />
                                        <label htmlFor="is_unlimited_cap" className="text-sm text-gray-900 font-medium">
                                            Authorize Unlimited Cap (Owner Only)
                                        </label>
                                    </div>
                                </div>
                            </div>
                        )}
                    </div>

                    <div>
                        <h2 className="text-base font-semibold text-gray-900 border-b pb-2 mb-4">Restrictions & Limits</h2>
                        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700">Minimum Order Subtotal (LKR)</label>
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    value={data.min_order_subtotal}
                                    onChange={(e) => setData('min_order_subtotal', e.target.value)}
                                    className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                />
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700">Global Usage Limit</label>
                                <input
                                    type="number"
                                    min="1"
                                    value={data.usage_limit_total}
                                    onChange={(e) => setData('usage_limit_total', e.target.value)}
                                    placeholder="Leave empty for unlimited"
                                    className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                />
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700">Usage Limit Per Customer</label>
                                <input
                                    type="number"
                                    min="1"
                                    value={data.usage_limit_per_customer}
                                    onChange={(e) => setData('usage_limit_per_customer', e.target.value)}
                                    className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                />
                            </div>
                        </div>

                        <div className="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700">Starts At (Optional)</label>
                                <input
                                    type="datetime-local"
                                    value={data.starts_at}
                                    onChange={(e) => setData('starts_at', e.target.value)}
                                    className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                />
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700">Expires At (Optional)</label>
                                <input
                                    type="datetime-local"
                                    value={data.expires_at}
                                    onChange={(e) => setData('expires_at', e.target.value)}
                                    className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                />
                            </div>
                        </div>
                    </div>

                    <div className="flex justify-end gap-3 pt-4 border-t">
                        <Link
                            href={`/admin/coupons/${coupon.id}`}
                            className="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50"
                        >
                            Cancel
                        </Link>
                        <button
                            type="submit"
                            disabled={processing}
                            className="px-4 py-2 bg-indigo-600 text-white rounded-md text-sm font-medium hover:bg-indigo-700 disabled:opacity-50"
                        >
                            {processing ? 'Saving...' : 'Save Changes'}
                        </button>
                    </div>
                </form>
            </div>
        </AdminLayout>
    );
}

