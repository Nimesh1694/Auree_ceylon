import React from 'react';
import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link, router } from '@inertiajs/react';

interface UsageRecord {
    id: number;
    customer_email: string;
    customer_phone: string;
    discount_amount: string;
    shipping_discount_amount: string;
    created_at: string;
    restored_at: string | null;
    order?: {
        id: number;
        order_number: string;
        total_amount: string;
    };
    customer?: {
        id: number;
        name: string;
        email: string;
    };
}

interface CouponDetails {
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
    times_used: number;
    is_active: boolean;
    is_public: boolean;
    applies_to: 'all' | 'specific_categories' | 'specific_products';
    allow_agent_referral: boolean;
    starts_at: string | null;
    expires_at: string | null;
    created_at: string;
    categories?: { id: number; name_en: string }[];
    products?: { id: number; name_en: string; sku: string }[];
    creator?: { id: number; name: string; email: string };
    usages?: UsageRecord[];
}

interface Props {
    coupon: CouponDetails;
    canEdit: boolean;
    canToggle: boolean;
    canDelete: boolean;
}

export default function ShowCoupon({ coupon, canEdit, canToggle, canDelete }: Props) {
    const handleToggle = () => {
        router.patch(`/admin/coupons/${coupon.id}/toggle`, {}, { preserveScroll: true });
    };

    const handleDelete = () => {
        if (confirm(`Are you sure you want to permanently delete coupon '${coupon.code}'?`)) {
            router.delete(`/admin/coupons/${coupon.id}`);
        }
    };

    return (
        <AdminLayout>
            <Head title={`Coupon: ${coupon.code}`} />

            <div className="py-6 px-4 sm:px-6 lg:px-8 max-w-6xl mx-auto space-y-6">
                <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div>
                        <div className="flex items-center gap-3">
                            <h1 className="text-3xl font-bold font-mono text-gray-900">{coupon.code}</h1>
                            <span className={`px-2.5 py-0.5 rounded-full text-xs font-semibold ${
                                coupon.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
                            }`}>
                                {coupon.is_active ? 'Active' : 'Inactive'}
                            </span>
                        </div>
                        <p className="text-sm text-gray-600 mt-1">{coupon.name}</p>
                    </div>

                    <div className="flex items-center gap-3">
                        <Link
                            href="/admin/coupons"
                            className="px-3 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50"
                        >
                            &larr; All Coupons
                        </Link>
                        {canToggle && (
                            <button
                                onClick={handleToggle}
                                className={`px-3 py-2 rounded-md text-sm font-medium ${
                                    coupon.is_active
                                        ? 'bg-amber-100 text-amber-800 hover:bg-amber-200'
                                        : 'bg-green-100 text-green-800 hover:bg-green-200'
                                }`}
                            >
                                {coupon.is_active ? 'Deactivate' : 'Activate'}
                            </button>
                        )}
                        {canEdit && (
                            <Link
                                href={`/admin/coupons/${coupon.id}/edit`}
                                className="px-3 py-2 bg-indigo-600 text-white rounded-md text-sm font-medium hover:bg-indigo-700"
                            >
                                Edit Rules
                            </Link>
                        )}
                        {canDelete && (
                            <button
                                onClick={handleDelete}
                                className="px-3 py-2 bg-red-600 text-white rounded-md text-sm font-medium hover:bg-red-700"
                            >
                                Delete
                            </button>
                        )}
                    </div>
                </div>

                {/* Key Metrics */}
                <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div className="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
                        <div className="text-xs font-semibold text-gray-500 uppercase">Discount Rate</div>
                        <div className="text-xl font-bold text-gray-900 mt-1">
                            {coupon.type === 'percentage' && `${coupon.value}% Off`}
                            {coupon.type === 'fixed_amount' && `LKR ${Number(coupon.value).toFixed(2)}`}
                            {coupon.type === 'free_shipping' && 'Free Shipping'}
                        </div>
                    </div>

                    <div className="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
                        <div className="text-xs font-semibold text-gray-500 uppercase">Usage Quota</div>
                        <div className="text-xl font-bold text-gray-900 mt-1">
                            {coupon.times_used} <span className="text-sm font-normal text-gray-500">/ {coupon.usage_limit_total ?? '∞'}</span>
                        </div>
                    </div>

                    <div className="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
                        <div className="text-xs font-semibold text-gray-500 uppercase">Percentage Cap</div>
                        <div className="text-xl font-bold text-gray-900 mt-1">
                            {coupon.type === 'percentage' ? (
                                coupon.is_unlimited_cap ? (
                                    <span className="text-red-600 text-sm">Unlimited</span>
                                ) : (
                                    `LKR ${coupon.max_discount_amount ? Number(coupon.max_discount_amount).toFixed(2) : '5,000.00'}`
                                )
                            ) : (
                                <span className="text-gray-400 text-sm">N/A</span>
                            )}
                        </div>
                    </div>

                    <div className="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
                        <div className="text-xs font-semibold text-gray-500 uppercase">Min Order Subtotal</div>
                        <div className="text-xl font-bold text-gray-900 mt-1">
                            LKR {Number(coupon.min_order_subtotal).toFixed(2)}
                        </div>
                    </div>
                </div>

                {/* Scope & Restrictions */}
                <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6 space-y-4">
                    <h2 className="text-base font-semibold text-gray-900 border-b pb-2">Policy & Restrictions</h2>
                    <dl className="grid grid-cols-1 md:grid-cols-2 gap-x-4 gap-y-3 text-sm">
                        <div>
                            <dt className="text-gray-500">Applies To:</dt>
                            <dd className="font-medium text-gray-900 capitalize">{coupon.applies_to.replace('_', ' ')}</dd>
                        </div>
                        <div>
                            <dt className="text-gray-500">Agent Referral Stacking:</dt>
                            <dd className="font-medium text-gray-900">{coupon.allow_agent_referral ? 'Allowed (Compatible)' : 'Restricted (Not Allowed)'}</dd>
                        </div>
                        <div>
                            <dt className="text-gray-500">Public Promo Banner:</dt>
                            <dd className="font-medium text-gray-900">{coupon.is_public ? 'Public' : 'Hidden / Code Only'}</dd>
                        </div>
                        <div>
                            <dt className="text-gray-500">Usage Limit Per Customer:</dt>
                            <dd className="font-medium text-gray-900">{coupon.usage_limit_per_customer ? `${coupon.usage_limit_per_customer} redemption(s)` : 'Unlimited'}</dd>
                        </div>
                        <div>
                            <dt className="text-gray-500">Starts At:</dt>
                            <dd className="font-medium text-gray-900">{coupon.starts_at ? new Date(coupon.starts_at).toLocaleString() : 'Immediate'}</dd>
                        </div>
                        <div>
                            <dt className="text-gray-500">Expires At:</dt>
                            <dd className="font-medium text-gray-900">{coupon.expires_at ? new Date(coupon.expires_at).toLocaleString() : 'No expiration'}</dd>
                        </div>
                    </dl>
                </div>

                {/* Audit Redemption History */}
                <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <h2 className="text-base font-semibold text-gray-900 border-b pb-2 mb-4">
                        Financial Audit Trail & Usage Redemptions
                    </h2>

                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-200 text-sm">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Order #</th>
                                    <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
                                    <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Product Discount</th>
                                    <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Shipping Discount</th>
                                    <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                    <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-200">
                                {coupon.usages && coupon.usages.length > 0 ? (
                                    coupon.usages.map((u) => (
                                        <tr key={u.id} className="hover:bg-gray-50">
                                            <td className="px-4 py-2 font-mono font-medium text-indigo-600">
                                                {u.order?.order_number ?? `#${u.id}`}
                                            </td>
                                            <td className="px-4 py-2 text-xs">
                                                <div>{u.customer?.name ?? u.customer_email}</div>
                                                <div className="text-gray-500">{u.customer_phone}</div>
                                            </td>
                                            <td className="px-4 py-2 font-medium text-emerald-700">
                                                LKR {Number(u.discount_amount).toFixed(2)}
                                            </td>
                                            <td className="px-4 py-2 font-medium text-blue-700">
                                                LKR {Number(u.shipping_discount_amount).toFixed(2)}
                                            </td>
                                            <td className="px-4 py-2 text-xs text-gray-500">
                                                {new Date(u.created_at).toLocaleString()}
                                            </td>
                                            <td className="px-4 py-2">
                                                {u.restored_at ? (
                                                    <span className="px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-800">
                                                        Restored
                                                    </span>
                                                ) : (
                                                    <span className="px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">
                                                        Redeemed
                                                    </span>
                                                )}
                                            </td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td colSpan={6} className="px-4 py-6 text-center text-gray-500 text-sm">
                                            No redemptions recorded for this coupon yet.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}

