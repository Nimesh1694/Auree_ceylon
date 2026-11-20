import React, { useState } from 'react';
import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link, router } from '@inertiajs/react';

interface CouponItem {
    id: number;
    code: string;
    name: string;
    type: 'percentage' | 'fixed_amount' | 'free_shipping';
    value: string;
    min_order_subtotal: string;
    max_discount_amount: string | null;
    is_unlimited_cap: boolean;
    usage_limit_total: number | null;
    times_used: number;
    is_active: boolean;
    is_public: boolean;
    applies_to: 'all' | 'specific_categories' | 'specific_products';
    allow_agent_referral: boolean;
    starts_at: string | null;
    expires_at: string | null;
    usages_count?: number;
    creator?: {
        id: number;
        name: string;
    };
}

interface Props {
    coupons: {
        data: CouponItem[];
        links: any[];
        total: number;
    };
    filters: {
        search?: string;
        type?: string;
        status?: string;
    };
    canManage: boolean;
    isOwner: boolean;
}

export default function CouponIndex({ coupons, filters, canManage, isOwner }: Props) {
    const [search, setSearch] = useState(filters.search || '');
    const [type, setType] = useState(filters.type || '');
    const [status, setStatus] = useState(filters.status || '');

    const handleFilter = (e: React.FormEvent) => {
        e.preventDefault();
        router.get('/admin/coupons', { search, type, status }, { preserveState: true });
    };

    const handleToggle = (couponId: number) => {
        router.patch(`/admin/coupons/${couponId}/toggle`, {}, { preserveScroll: true });
    };

    return (
        <AdminLayout>
            <Head title="Marketing Coupons & Discounts" />

            <div className="py-6 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">
                <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">Coupons & Discounts</h1>
                        <p className="text-sm text-gray-600 mt-1">
                            Manage promotional discount codes, caps, restrictions, and audit usage trails.
                        </p>
                    </div>

                    {canManage && (
                        <Link
                            href="/admin/coupons/create"
                            className="inline-flex items-center justify-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700"
                        >
                            + Create Coupon
                        </Link>
                    )}
                </div>

                {/* Filters */}
                <div className="bg-white p-4 rounded-lg shadow-sm border border-gray-200 mb-6">
                    <form onSubmit={handleFilter} className="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div>
                            <label className="block text-xs font-semibold text-gray-600 uppercase mb-1">Search</label>
                            <input
                                type="text"
                                placeholder="Code or Name..."
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                className="w-full border-gray-300 rounded-md text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />
                        </div>

                        <div>
                            <label className="block text-xs font-semibold text-gray-600 uppercase mb-1">Discount Type</label>
                            <select
                                value={type}
                                onChange={(e) => setType(e.target.value)}
                                className="w-full border-gray-300 rounded-md text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                <option value="">All Types</option>
                                <option value="percentage">Percentage (%)</option>
                                <option value="fixed_amount">Fixed Amount (LKR)</option>
                                <option value="free_shipping">Free Shipping</option>
                            </select>
                        </div>

                        <div>
                            <label className="block text-xs font-semibold text-gray-600 uppercase mb-1">Status</label>
                            <select
                                value={status}
                                onChange={(e) => setStatus(e.target.value)}
                                className="w-full border-gray-300 rounded-md text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                <option value="">All Statuses</option>
                                <option value="active">Active Only</option>
                                <option value="inactive">Inactive Only</option>
                            </select>
                        </div>

                        <div className="flex items-end gap-2">
                            <button
                                type="submit"
                                className="px-4 py-2 bg-gray-800 text-white rounded-md text-sm font-medium hover:bg-gray-700"
                            >
                                Filter
                            </button>
                            <button
                                type="button"
                                onClick={() => {
                                    setSearch('');
                                    setType('');
                                    setStatus('');
                                    router.get('/admin/coupons');
                                }}
                                className="px-4 py-2 border border-gray-300 text-gray-700 rounded-md text-sm font-medium hover:bg-gray-50"
                            >
                                Reset
                            </button>
                        </div>
                    </form>
                </div>

                {/* Table */}
                <div className="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-200">
                        <thead className="bg-gray-50">
                            <tr>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Coupon Code</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Discount</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Scope / Cap</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Redemptions</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="bg-white divide-y divide-gray-200">
                            {coupons.data.length === 0 ? (
                                <tr>
                                    <td colSpan={6} className="px-6 py-8 text-center text-sm text-gray-500">
                                        No coupons found matching your criteria.
                                    </td>
                                </tr>
                            ) : (
                                coupons.data.map((coupon) => (
                                    <tr key={coupon.id} className="hover:bg-gray-50">
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <div className="font-semibold text-gray-900 font-mono text-base">{coupon.code}</div>
                                            <div className="text-xs text-gray-500">{coupon.name}</div>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {coupon.type === 'percentage' && (
                                                <span className="font-medium text-indigo-700">{coupon.value}% Off</span>
                                            )}
                                            {coupon.type === 'fixed_amount' && (
                                                <span className="font-medium text-emerald-700">LKR {Number(coupon.value).toFixed(2)} Off</span>
                                            )}
                                            {coupon.type === 'free_shipping' && (
                                                <span className="font-medium text-blue-700">Free Shipping</span>
                                            )}
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-xs text-gray-600">
                                            <div>Applies: <span className="font-medium capitalize">{coupon.applies_to.replace('_', ' ')}</span></div>
                                            {coupon.type === 'percentage' && (
                                                <div>Cap: {coupon.is_unlimited_cap ? (
                                                    <span className="text-red-600 font-semibold">Unlimited (Owner Auth)</span>
                                                ) : (
                                                    <span>LKR {coupon.max_discount_amount ? Number(coupon.max_discount_amount).toFixed(2) : '5,000.00 (Default)'}</span>
                                                )}</div>
                                            )}
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                            <span className="font-medium">{coupon.times_used}</span>
                                            {coupon.usage_limit_total ? (
                                                <span className="text-gray-500"> / {coupon.usage_limit_total}</span>
                                            ) : (
                                                <span className="text-gray-400"> / ∞</span>
                                            )}
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <span className={`inline-flex px-2 text-xs font-semibold leading-5 rounded-full ${
                                                coupon.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
                                            }`}>
                                                {coupon.is_active ? 'Active' : 'Inactive'}
                                            </span>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <div className="flex items-center justify-end gap-2">
                                                <Link
                                                    href={`/admin/coupons/${coupon.id}`}
                                                    className="text-indigo-600 hover:text-indigo-900 mr-2"
                                                >
                                                    View
                                                </Link>
                                                {canManage && (
                                                    <>
                                                        <Link
                                                            href={`/admin/coupons/${coupon.id}/edit`}
                                                            className="text-gray-600 hover:text-gray-900 mr-2"
                                                        >
                                                            Edit
                                                        </Link>
                                                        <button
                                                            onClick={() => handleToggle(coupon.id)}
                                                            className="text-amber-600 hover:text-amber-900"
                                                        >
                                                            {coupon.is_active ? 'Deactivate' : 'Activate'}
                                                        </button>
                                                    </>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}

