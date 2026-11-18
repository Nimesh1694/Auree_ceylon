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

interface Props {
    categories: CategoryItem[];
    products: ProductItem[];
    isOwner: boolean;
}

export default function CreateCoupon({ categories, products, isOwner }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        code: '',
        name: '',
        type: 'percentage',
        value: '',
        min_order_subtotal: '',
        max_discount_amount: '',
        is_unlimited_cap: false,
        usage_limit_total: '',
        usage_limit_per_customer: '1',
        is_active: true,
        is_public: true,
        applies_to: 'all',
        allow_agent_referral: true,
        starts_at: '',
        expires_at: '',
        category_ids: [] as number[],
        product_ids: [] as number[],
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/admin/coupons');
    };

    return (
        <AdminLayout>
            <Head title="Create New Coupon" />

            <div className="py-6 px-4 sm:px-6 lg:px-8 max-w-4xl mx-auto">
                <div className="mb-6 flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">Create Marketing Coupon</h1>
                        <p className="text-sm text-gray-600 mt-1">
                            Configure discount parameters, eligibility limits, and authority boundaries.
                        </p>
                    </div>
                    <Link
                        href="/admin/coupons"
                        className="text-sm font-medium text-gray-600 hover:text-gray-900"
                    >
                        &larr; Back to List
                    </Link>
                </div>

                <form onSubmit={handleSubmit} className="bg-white shadow-sm border border-gray-200 rounded-lg p-6 space-y-6">
                    {/* General Information */}
                    <div>
                        <h2 className="text-base font-semibold text-gray-900 border-b pb-2 mb-4">Basic Information</h2>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700">Coupon Code *</label>
                                <input
                                    type="text"
                                    value={data.code}
                                    onChange={(e) => setData('code', e.target.value.toUpperCase())}
                                    placeholder="e.g. WELCOME10"
                                    required
                                    className="mt-1 block w-full border-gray-300 rounded-md shadow-sm font-mono uppercase focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                />
                                {errors.code && <p className="mt-1 text-xs text-red-600">{errors.code}</p>}
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700">Internal Campaign Name *</label>
                                <input
                                    type="text"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    placeholder="e.g. 10% Welcome Discount"
                                    required
                                    className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                />
                                {errors.name && <p className="mt-1 text-xs text-red-600">{errors.name}</p>}
                            </div>
                        </div>
                    </div>

                    {/* Discount Configuration */}
                    <div>
                        <h2 className="text-base font-semibold text-gray-900 border-b pb-2 mb-4">Discount Mechanics</h2>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700">Discount Type *</label>
                                <select
                                    value={data.type}
                                    onChange={(e) => setData('type', e.target.value as any)}
                                    className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                >
                                    <option value="percentage">Percentage Discount (%)</option>
                                    <option value="fixed_amount">Fixed Amount Discount (LKR)</option>
                                    <option value="free_shipping">Free Shipping</option>
                                </select>
                                {errors.type && <p className="mt-1 text-xs text-red-600">{errors.type}</p>}
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700">
                                    {data.type === 'percentage' ? 'Percentage Rate (%) *' : (data.type === 'fixed_amount' ? 'Fixed Amount (LKR) *' : 'Value (N/A for Free Shipping)')}
                                </label>
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    disabled={data.type === 'free_shipping'}
                                    value={data.value}
                                    onChange={(e) => setData('value', e.target.value)}
                                    placeholder={data.type === 'percentage' ? '15' : '500.00'}
                                    required={data.type !== 'free_shipping'}
                                    className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm disabled:bg-gray-100"
                                />
                                {errors.value && <p className="mt-1 text-xs text-red-600">{errors.value}</p>}
                            </div>
                        </div>

                        {data.type === 'percentage' && (
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
                                            placeholder="5000.00 (Leave blank for default LKR 5,000)"
                                            className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm disabled:bg-gray-100"
                                        />
                                        <p className="text-xs text-gray-500 mt-1">
                                            Caps exceeding LKR 5,000.00 strictly require Project Owner authorization.
                                        </p>
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
                                {!isOwner && (
                                    <p className="text-xs text-amber-700 bg-amber-50 p-2 rounded">
                                        Notice: As a non-owner staff member, unlimited caps or caps &gt; LKR 5,000.00 cannot be saved.
                                    </p>
                                )}
                            </div>
                        )}
                    </div>

                    {/* Eligibility & Restrictions */}
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
                                    placeholder="0.00"
                                    className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                />
                                {errors.min_order_subtotal && <p className="mt-1 text-xs text-red-600">{errors.min_order_subtotal}</p>}
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
                                {errors.usage_limit_total && <p className="mt-1 text-xs text-red-600">{errors.usage_limit_total}</p>}
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700">Usage Limit Per Customer</label>
                                <input
                                    type="number"
                                    min="1"
                                    value={data.usage_limit_per_customer}
                                    onChange={(e) => setData('usage_limit_per_customer', e.target.value)}
                                    placeholder="1"
                                    className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                />
                                {errors.usage_limit_per_customer && <p className="mt-1 text-xs text-red-600">{errors.usage_limit_per_customer}</p>}
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
                                {errors.expires_at && <p className="mt-1 text-xs text-red-600">{errors.expires_at}</p>}
                            </div>
                        </div>
                    </div>

                    {/* Catalog Scope */}
                    <div>
                        <h2 className="text-base font-semibold text-gray-900 border-b pb-2 mb-4">Catalog Scope</h2>
                        <div className="space-y-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700">Applies To *</label>
                                <select
                                    value={data.applies_to}
                                    onChange={(e) => setData('applies_to', e.target.value as any)}
                                    className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                >
                                    <option value="all">Entire Catalog (All Products)</option>
                                    <option value="specific_categories">Specific Categories Only</option>
                                    <option value="specific_products">Specific Products Only</option>
                                </select>
                            </div>

                            {data.applies_to === 'specific_categories' && (
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">Select Categories</label>
                                    <select
                                        multiple
                                        value={data.category_ids.map(String)}
                                        onChange={(e) => {
                                            const selected = Array.from(e.target.selectedOptions, (option) => Number(option.value));
                                            setData('category_ids', selected);
                                        }}
                                        className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm h-32"
                                    >
                                        {categories.map((c) => (
                                            <option key={c.id} value={c.id}>{c.name_en}</option>
                                        ))}
                                    </select>
                                </div>
                            )}

                            {data.applies_to === 'specific_products' && (
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">Select Products</label>
                                    <select
                                        multiple
                                        value={data.product_ids.map(String)}
                                        onChange={(e) => {
                                            const selected = Array.from(e.target.selectedOptions, (option) => Number(option.value));
                                            setData('product_ids', selected);
                                        }}
                                        className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm h-32"
                                    >
                                        {products.map((p) => (
                                            <option key={p.id} value={p.id}>{p.name_en} ({p.sku})</option>
                                        ))}
                                    </select>
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Stacking & Policy */}
                    <div>
                        <h2 className="text-base font-semibold text-gray-900 border-b pb-2 mb-4">Policies</h2>
                        <div className="space-y-3">
                            <div className="flex items-center gap-2">
                                <input
                                    type="checkbox"
                                    id="allow_agent_referral"
                                    checked={data.allow_agent_referral}
                                    onChange={(e) => setData('allow_agent_referral', e.target.checked)}
                                    className="h-4 w-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500"
                                />
                                <label htmlFor="allow_agent_referral" className="text-sm text-gray-900">
                                    Allow stacking with Agent Referral Code (Eligible public coupons)
                                </label>
                            </div>

                            <div className="flex items-center gap-2">
                                <input
                                    type="checkbox"
                                    id="is_public"
                                    checked={data.is_public}
                                    onChange={(e) => setData('is_public', e.target.checked)}
                                    className="h-4 w-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500"
                                />
                                <label htmlFor="is_public" className="text-sm text-gray-900">
                                    Public Coupon (Visible on promotions banner)
                                </label>
                            </div>

                            <div className="flex items-center gap-2">
                                <input
                                    type="checkbox"
                                    id="is_active"
                                    checked={data.is_active}
                                    onChange={(e) => setData('is_active', e.target.checked)}
                                    className="h-4 w-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500"
                                />
                                <label htmlFor="is_active" className="text-sm text-gray-900">
                                    Activate immediately upon creation
                                </label>
                            </div>
                        </div>
                    </div>

                    <div className="flex justify-end gap-3 pt-4 border-t">
                        <Link
                            href="/admin/coupons"
                            className="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50"
                        >
                            Cancel
                        </Link>
                        <button
                            type="submit"
                            disabled={processing}
                            className="px-4 py-2 bg-indigo-600 text-white rounded-md text-sm font-medium hover:bg-indigo-700 disabled:opacity-50"
                        >
                            {processing ? 'Saving...' : 'Create Coupon'}
                        </button>
                    </div>
                </form>
            </div>
        </AdminLayout>
    );
}

