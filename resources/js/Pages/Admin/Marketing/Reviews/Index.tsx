import React, { useState } from 'react';
import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { PageProps } from '@/types';

interface ReviewItem {
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
        rating_cache: string;
        reviews_count: number;
    };
    customer: {
        id: number;
        name: string;
        email: string;
    };
    moderator?: {
        id: number;
        name: string;
    } | null;
}

interface Props {
    reviews: {
        data: ReviewItem[];
        links: any[];
        total: number;
        current_page: number;
        last_page: number;
    };
    filters: {
        status?: string;
        search?: string;
    };
    counts: {
        all: number;
        pending: number;
        approved: number;
        rejected: number;
    };
}

export default function ReviewIndex({ reviews, filters, counts }: Props) {
    const { auth } = usePage<PageProps>().props;
    const permissions = auth.permissions ?? [];
    const isOwner = Boolean(auth.is_owner || auth.user?.role === 'owner');
    const canModerate = isOwner || permissions.includes('reviews.moderate');
    const canDelete = isOwner;

    const [search, setSearch] = useState(filters.search || '');
    const [statusFilter, setStatusFilter] = useState(filters.status || '');
    const [rejectingReviewId, setRejectingReviewId] = useState<number | null>(null);
    const [rejectionReason, setRejectionReason] = useState('');
    const [processingId, setProcessingId] = useState<number | null>(null);

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(route('admin.reviews.index'), {
            status: statusFilter || undefined,
            search: search || undefined,
        }, { preserveState: true });
    };

    const handleTabChange = (status: string) => {
        setStatusFilter(status);
        router.get(route('admin.reviews.index'), {
            status: status || undefined,
            search: search || undefined,
        }, { preserveState: true });
    };

    const handleApprove = (reviewId: number) => {
        if (!confirm('Are you sure you want to approve this review? The product rating cache will be updated.')) return;
        setProcessingId(reviewId);
        router.patch(route('admin.reviews.approve', reviewId), {
            status: 'approved',
        }, {
            onFinish: () => setProcessingId(null),
        });
    };

    const handleRejectSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!rejectingReviewId) return;
        if (rejectionReason.trim().length < 5) {
            alert('Rejection reason must be at least 5 characters.');
            return;
        }

        setProcessingId(rejectingReviewId);
        router.patch(route('admin.reviews.reject', rejectingReviewId), {
            status: 'rejected',
            rejection_reason: rejectionReason,
        }, {
            onSuccess: () => {
                setRejectingReviewId(null);
                setRejectionReason('');
            },
            onFinish: () => setProcessingId(null),
        });
    };

    const handleDelete = (reviewId: number) => {
        if (!confirm('Permanent Deletion: Are you sure you want to permanently delete this review? This action cannot be undone.')) return;
        setProcessingId(reviewId);
        router.delete(route('admin.reviews.destroy', reviewId), {
            onFinish: () => setProcessingId(null),
        });
    };

    const renderStars = (rating: number) => {
        return (
            <div className="flex items-center text-amber-400">
                {[1, 2, 3, 4, 5].map((star) => (
                    <span key={star} className={star <= rating ? 'text-amber-400' : 'text-gray-300 dark:text-gray-600'}>
                        ★
                    </span>
                ))}
                <span className="ml-1 text-xs font-semibold text-gray-700 dark:text-gray-300">
                    {rating}.0
                </span>
            </div>
        );
    };

    return (
        <AdminLayout>
            <Head title="Product Reviews Moderation" />

            <div className="p-6 space-y-6">
                <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900 dark:text-white">
                            Product Reviews & Ratings
                        </h1>
                        <p className="text-sm text-gray-600 dark:text-gray-400">
                            Verified purchaser feedback moderation and catalog rating synchronization
                        </p>
                    </div>
                </div>

                {/* Tabs */}
                <div className="flex border-b border-gray-200 dark:border-gray-700 space-x-4">
                    {[
                        { key: '', label: 'All Reviews', count: counts.all },
                        { key: 'pending', label: 'Pending Moderation', count: counts.pending },
                        { key: 'approved', label: 'Approved', count: counts.approved },
                        { key: 'rejected', label: 'Rejected', count: counts.rejected },
                    ].map((tab) => (
                        <button
                            key={tab.key}
                            onClick={() => handleTabChange(tab.key)}
                            className={`pb-3 px-2 text-sm font-medium border-b-2 transition-colors flex items-center gap-2 ${
                                statusFilter === tab.key
                                    ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400 dark:border-indigo-400 font-semibold'
                                    : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'
                            }`}
                        >
                            <span>{tab.label}</span>
                            <span className="px-2 py-0.5 text-xs rounded-full bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300">
                                {tab.count}
                            </span>
                        </button>
                    ))}
                </div>

                {/* Search Bar */}
                <form onSubmit={handleSearch} className="flex gap-2">
                    <input
                        type="text"
                        placeholder="Search by product, customer, title..."
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        className="px-4 py-2 border rounded-lg text-sm bg-white dark:bg-gray-800 dark:border-gray-700 dark:text-white flex-1 max-w-md"
                    />
                    <button
                        type="submit"
                        className="px-4 py-2 bg-gray-900 text-white dark:bg-gray-700 rounded-lg text-sm hover:bg-gray-800 transition"
                    >
                        Filter
                    </button>
                </form>

                {/* Reviews List */}
                <div className="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-gray-50 dark:bg-gray-900/50 text-gray-700 dark:text-gray-300 border-b border-gray-200 dark:border-gray-700">
                                <tr>
                                    <th className="py-3 px-4">Product</th>
                                    <th className="py-3 px-4">Customer</th>
                                    <th className="py-3 px-4">Rating & Review</th>
                                    <th className="py-3 px-4">Status</th>
                                    <th className="py-3 px-4">Date</th>
                                    <th className="py-3 px-4 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-200 dark:divide-gray-700 text-gray-600 dark:text-gray-300">
                                {reviews.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={6} className="py-8 text-center text-gray-500">
                                            No reviews found matching the selected criteria.
                                        </td>
                                    </tr>
                                ) : (
                                    reviews.data.map((r) => (
                                        <tr key={r.id} className="hover:bg-gray-50/50 dark:hover:bg-gray-700/50">
                                            <td className="py-3 px-4">
                                                <div className="font-medium text-gray-900 dark:text-white">
                                                    {r.product?.name_en}
                                                </div>
                                                <div className="text-xs text-gray-500">
                                                    Current: ★ {r.product?.rating_cache} ({r.product?.reviews_count} reviews)
                                                </div>
                                            </td>
                                            <td className="py-3 px-4">
                                                <div className="font-medium text-gray-900 dark:text-white">
                                                    {r.customer?.name}
                                                </div>
                                                <div className="text-xs text-gray-500">
                                                    {r.customer?.email}
                                                </div>
                                                <span className="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300">
                                                    ✓ Verified Purchaser
                                                </span>
                                            </td>
                                            <td className="py-3 px-4 max-w-xs">
                                                {renderStars(r.rating)}
                                                <div className="font-semibold text-gray-900 dark:text-white text-xs mt-1">
                                                    {r.title}
                                                </div>
                                                <p className="text-xs text-gray-600 dark:text-gray-400 line-clamp-2">
                                                    {r.comment}
                                                </p>
                                            </td>
                                            <td className="py-3 px-4">
                                                <span className={`inline-block px-2 py-1 rounded-full text-xs font-semibold ${
                                                    r.status === 'approved'
                                                        ? 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300'
                                                        : r.status === 'rejected'
                                                        ? 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300'
                                                        : 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300'
                                                }`}>
                                                    {r.status.toUpperCase()}
                                                </span>
                                                {r.rejection_reason && (
                                                    <div className="text-[11px] text-rose-600 dark:text-rose-400 mt-1 max-w-xs">
                                                        Reason: {r.rejection_reason}
                                                    </div>
                                                )}
                                            </td>
                                            <td className="py-3 px-4 text-xs text-gray-500">
                                                {new Date(r.created_at).toLocaleDateString()}
                                            </td>
                                            <td className="py-3 px-4 text-right space-x-2">
                                                <Link
                                                    href={route('admin.reviews.show', r.id)}
                                                    className="px-2 py-1 text-xs font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded dark:bg-gray-700 dark:text-gray-200"
                                                >
                                                    View
                                                </Link>

                                                {canModerate && r.status !== 'approved' && (
                                                    <button
                                                        onClick={() => handleApprove(r.id)}
                                                        disabled={processingId === r.id}
                                                        className="px-2 py-1 text-xs font-medium text-emerald-700 bg-emerald-50 hover:bg-emerald-100 rounded dark:bg-emerald-950/40 dark:text-emerald-300"
                                                    >
                                                        Approve
                                                    </button>
                                                )}

                                                {canModerate && r.status !== 'rejected' && (
                                                    <button
                                                        onClick={() => {
                                                            setRejectingReviewId(r.id);
                                                            setRejectionReason('');
                                                        }}
                                                        disabled={processingId === r.id}
                                                        className="px-2 py-1 text-xs font-medium text-amber-700 bg-amber-50 hover:bg-amber-100 rounded dark:bg-amber-950/40 dark:text-amber-300"
                                                    >
                                                        Reject
                                                    </button>
                                                )}

                                                {canDelete && (
                                                    <button
                                                        onClick={() => handleDelete(r.id)}
                                                        disabled={processingId === r.id}
                                                        className="px-2 py-1 text-xs font-medium text-rose-700 bg-rose-50 hover:bg-rose-100 rounded dark:bg-rose-950/40 dark:text-rose-300"
                                                    >
                                                        Delete
                                                    </button>
                                                )}
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>

                {/* Rejection Modal */}
                {rejectingReviewId && (
                    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                        <div className="bg-white dark:bg-gray-800 rounded-xl p-6 max-w-md w-full space-y-4">
                            <h3 className="text-lg font-bold text-gray-900 dark:text-white">
                                Reject Customer Review
                            </h3>
                            <p className="text-sm text-gray-600 dark:text-gray-400">
                                Please specify a clear administrative reason for rejecting this review.
                            </p>
                            <form onSubmit={handleRejectSubmit} className="space-y-4">
                                <textarea
                                    value={rejectionReason}
                                    onChange={(e) => setRejectionReason(e.target.value)}
                                    placeholder="Enter rejection reason (5-255 characters)..."
                                    required
                                    minLength={5}
                                    maxLength={255}
                                    rows={3}
                                    className="w-full px-3 py-2 border rounded-lg text-sm bg-white dark:bg-gray-900 dark:border-gray-700 dark:text-white"
                                />
                                <div className="flex justify-end gap-2">
                                    <button
                                        type="button"
                                        onClick={() => setRejectingReviewId(null)}
                                        className="px-4 py-2 border rounded-lg text-sm text-gray-700 dark:text-gray-300"
                                    >
                                        Cancel
                                    </button>
                                    <button
                                        type="submit"
                                        disabled={processingId === rejectingReviewId}
                                        className="px-4 py-2 bg-rose-600 text-white rounded-lg text-sm hover:bg-rose-700"
                                    >
                                        Confirm Rejection
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                )}
            </div>
        </AdminLayout>
    );
}

