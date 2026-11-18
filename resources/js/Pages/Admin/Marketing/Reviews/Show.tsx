import React from 'react';
import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { PageProps } from '@/types';

interface ReviewDetail {
    id: number;
    product_id: number;
    customer_id: number;
    order_id: number;
    rating: number;
    title: string;
    comment: string;
    status: 'pending' | 'approved' | 'rejected';
    moderated_by: number | null;
    moderated_at: string | null;
    rejection_reason: string | null;
    created_at: string;
    product: {
        id: number;
        name_en: string;
        name_si?: string;
        slug: string;
        price: string;
        rating_cache: string;
        reviews_count: number;
    };
    customer: {
        id: number;
        name: string;
        email: string;
    };
    order: {
        id: number;
        order_number: string;
        status: string;
        total_amount: string;
        created_at: string;
        items: Array<{
            id: number;
            product_id: number;
            quantity: number;
            price: string;
            product?: {
                id: number;
                name_en: string;
                sku: string;
            };
        }>;
    };
    moderator?: {
        id: number;
        name: string;
    } | null;
}

interface Props {
    review: ReviewDetail;
}

export default function ReviewShow({ review }: Props) {
    const { auth } = usePage<PageProps>().props;
    const permissions = auth.permissions ?? [];
    const isOwner = Boolean(auth.is_owner || auth.user?.role === 'owner');
    const canModerate = isOwner || permissions.includes('reviews.moderate');
    const canDelete = isOwner;

    const handleApprove = () => {
        if (!confirm('Approve this review and synchronize product rating cache?')) return;
        router.patch(route('admin.reviews.approve', review.id), { status: 'approved' });
    };

    const handleReject = () => {
        const reason = prompt('Please enter a rejection reason (5-255 characters):');
        if (!reason || reason.trim().length < 5) {
            alert('Rejection requires a valid reason of at least 5 characters.');
            return;
        }
        router.patch(route('admin.reviews.reject', review.id), {
            status: 'rejected',
            rejection_reason: reason.trim(),
        });
    };

    const handleDelete = () => {
        if (!confirm('Permanent Deletion: Are you sure you want to permanently delete this review?')) return;
        router.delete(route('admin.reviews.destroy', review.id));
    };

    return (
        <AdminLayout>
            <Head title={`Review #${review.id} - ${review.product?.name_en}`} />

            <div className="p-6 max-w-5xl mx-auto space-y-6">
                <div className="flex items-center justify-between">
                    <div className="flex items-center space-x-3">
                        <Link
                            href={route('admin.reviews.index')}
                            className="p-2 border rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800"
                        >
                            ←
                        </Link>
                        <div>
                            <h1 className="text-xl font-bold text-gray-900 dark:text-white">
                                Review #{review.id} Detail
                            </h1>
                            <p className="text-sm text-gray-500">
                                Submitted on {new Date(review.created_at).toLocaleString()}
                            </p>
                        </div>
                    </div>

                    <div className="flex items-center space-x-2">
                        {canModerate && review.status !== 'approved' && (
                            <button
                                onClick={handleApprove}
                                className="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-lg"
                            >
                                Approve Review
                            </button>
                        )}
                        {canModerate && review.status !== 'rejected' && (
                            <button
                                onClick={handleReject}
                                className="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white text-sm font-semibold rounded-lg"
                            >
                                Reject Review
                            </button>
                        )}
                        {canDelete && (
                            <button
                                onClick={handleDelete}
                                className="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white text-sm font-semibold rounded-lg"
                            >
                                Permanently Delete
                            </button>
                        )}
                    </div>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                    {/* Left 2 Cols: Review Content */}
                    <div className="md:col-span-2 space-y-6">
                        <div className="bg-white dark:bg-gray-800 rounded-xl shadow p-6 border border-gray-200 dark:border-gray-700 space-y-4">
                            <div className="flex items-center justify-between">
                                <span className={`px-2.5 py-1 rounded-full text-xs font-bold uppercase ${
                                    review.status === 'approved'
                                        ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300'
                                        : review.status === 'rejected'
                                        ? 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300'
                                        : 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300'
                                }`}>
                                    Status: {review.status}
                                </span>
                                <div className="flex items-center text-amber-400 text-lg">
                                    {'★'.repeat(review.rating)}
                                    {'☆'.repeat(5 - review.rating)}
                                    <span className="ml-2 text-sm font-semibold text-gray-800 dark:text-gray-200">
                                        {review.rating} / 5 Stars
                                    </span>
                                </div>
                            </div>

                            <div>
                                <h3 className="text-lg font-bold text-gray-900 dark:text-white">
                                    {review.title}
                                </h3>
                                <p className="mt-2 text-gray-700 dark:text-gray-300 whitespace-pre-line leading-relaxed">
                                    {review.comment}
                                </p>
                            </div>

                            {review.status === 'rejected' && review.rejection_reason && (
                                <div className="p-3 bg-rose-50 dark:bg-rose-950/30 border border-rose-200 dark:border-rose-900 rounded-lg text-rose-800 dark:text-rose-300 text-sm">
                                    <span className="font-semibold">Administrative Rejection Reason:</span> {review.rejection_reason}
                                </div>
                            )}

                            {review.moderator && (
                                <div className="text-xs text-gray-500 pt-2 border-t dark:border-gray-700">
                                    Moderated by <span className="font-medium text-gray-700 dark:text-gray-300">{review.moderator.name}</span> on {new Date(review.moderated_at!).toLocaleString()}
                                </div>
                            )}
                        </div>

                        {/* Order Context */}
                        <div className="bg-white dark:bg-gray-800 rounded-xl shadow p-6 border border-gray-200 dark:border-gray-700 space-y-3">
                            <h4 className="text-sm font-bold uppercase tracking-wider text-gray-500">
                                Verified Order Evidence
                            </h4>
                            <div className="text-sm">
                                <p><span className="font-medium text-gray-700 dark:text-gray-300">Order ID:</span> #{review.order?.id}</p>
                                <p><span className="font-medium text-gray-700 dark:text-gray-300">Order Status:</span> <span className="capitalize font-semibold text-emerald-600">{review.order?.status}</span></p>
                            </div>
                        </div>
                    </div>

                    {/* Right Col: Product & Customer */}
                    <div className="space-y-6">
                        <div className="bg-white dark:bg-gray-800 rounded-xl shadow p-6 border border-gray-200 dark:border-gray-700 space-y-4">
                            <h4 className="text-sm font-bold uppercase tracking-wider text-gray-500">
                                Target Product
                            </h4>
                            <div>
                                <div className="font-bold text-gray-900 dark:text-white">
                                    {review.product?.name_en}
                                </div>
                                <div className="text-xs text-gray-500 mt-1">
                                    Price: LKR {review.product?.price}
                                </div>
                                <div className="mt-3 p-2 bg-gray-50 dark:bg-gray-900 rounded text-xs space-y-1">
                                    <div><span className="font-medium">Live Rating:</span> ★ {review.product?.rating_cache}</div>
                                    <div><span className="font-medium">Approved Reviews:</span> {review.product?.reviews_count}</div>
                                </div>
                            </div>
                        </div>

                        <div className="bg-white dark:bg-gray-800 rounded-xl shadow p-6 border border-gray-200 dark:border-gray-700 space-y-4">
                            <h4 className="text-sm font-bold uppercase tracking-wider text-gray-500">
                                Customer Profile
                            </h4>
                            <div className="text-sm space-y-1">
                                <div className="font-medium text-gray-900 dark:text-white">
                                    {review.customer?.name}
                                </div>
                                <div className="text-xs text-gray-500">
                                    {review.customer?.email}
                                </div>
                                <div className="pt-2">
                                    <span className="inline-block px-2 py-0.5 rounded text-xs bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300 font-semibold">
                                        ✓ Verified Purchaser
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}

