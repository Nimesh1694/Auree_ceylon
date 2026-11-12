import * as Icons from '@/Components/Icons';
import AdminLayout from '@/Layouts/AdminLayout';
import { PaginatedData, RolePermissionRequest } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import React, { useState } from 'react';

interface Props {
    requests: PaginatedData<RolePermissionRequest>;
    currentStatus: string;
    isOwner: boolean;
}

export default function PermissionRequests({ requests, currentStatus, isOwner }: Props) {
    const [actionModal, setActionModal] = useState<{
        type: 'approve' | 'reject';
        request: RolePermissionRequest;
    } | null>(null);

    const { data, setData, post, processing, reset, errors } = useForm({
        review_notes: '',
    });

    const handleOpenAction = (type: 'approve' | 'reject', req: RolePermissionRequest) => {
        reset();
        setActionModal({ type, request: req });
    };

    const handleConfirmAction = (e: React.FormEvent) => {
        e.preventDefault();
        if (!actionModal) return;

        const url = `/admin/roles/permission-requests/${actionModal.request.id}/${actionModal.type}`;
        post(url, {
            onSuccess: () => setActionModal(null),
        });
    };

    const filterStatus = (status: string) => {
        router.get('/admin/roles/permission-requests', { status }, { preserveState: true });
    };

    return (
        <AdminLayout>
            <Head title="Restricted Permission Requests | Governance" />

            <div className="space-y-6">
                {/* Header Banner */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white dark:bg-[#0F172A] p-6 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-xs transition-colors duration-200">
                    <div>
                        <div className="flex items-center gap-2 mb-2">
                            <Link
                                href="/admin/roles"
                                className="inline-flex items-center gap-1.5 text-xs text-blue-600 dark:text-blue-400 hover:underline font-semibold"
                            >
                                <Icons.ArrowLeft className="w-3.5 h-3.5" />
                                <span>Back to Roles Matrix</span>
                            </Link>
                        </div>
                        <h1 className="text-xl font-bold text-slate-900 dark:text-white tracking-tight">
                            Restricted Permission Requests
                        </h1>
                        <p className="text-xs text-slate-500 dark:text-slate-400 mt-1 max-w-2xl">
                            Two-Person Integrity Governance: Sensitive financial capabilities requested by Super Admin or operational staff must be reviewed and explicitly authorized by the Business Owner.
                        </p>
                    </div>

                    {/* Filter Tabs */}
                    <div className="flex items-center gap-1.5 bg-slate-100 dark:bg-slate-900 p-1.5 rounded-xl border border-slate-200 dark:border-slate-800 text-xs shrink-0">
                        {['all', 'pending', 'approved', 'rejected'].map((s) => (
                            <button
                                key={s}
                                onClick={() => filterStatus(s)}
                                className={`px-3 py-1.5 rounded-lg font-semibold capitalize transition-all cursor-pointer ${
                                    currentStatus === s
                                        ? 'bg-white dark:bg-[#0F172A] text-slate-900 dark:text-white shadow-xs'
                                        : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
                                }`}
                            >
                                {s}
                            </button>
                        ))}
                    </div>
                </div>

                {/* Requests Table */}
                <div className="bg-white dark:bg-[#0F172A] rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-xs overflow-hidden transition-colors duration-200">
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-slate-200 dark:divide-slate-800 text-xs">
                            <thead className="bg-slate-50/80 dark:bg-slate-900/60 text-slate-600 dark:text-slate-400 uppercase tracking-wider font-semibold">
                                <tr>
                                    <th className="px-6 py-3.5 text-left">Role & Capability</th>
                                    <th className="px-6 py-3.5 text-left">Requested By & Purpose</th>
                                    <th className="px-6 py-3.5 text-left">Status</th>
                                    <th className="px-6 py-3.5 text-left">Review Outcome</th>
                                    <th className="px-6 py-3.5 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 dark:divide-slate-800/60">
                                {requests.data.length > 0 ? (
                                    requests.data.map((req) => (
                                        <tr
                                            key={req.id}
                                            className="hover:bg-slate-50/60 dark:hover:bg-slate-800/40 transition-colors"
                                        >
                                            <td className="px-6 py-4">
                                                <div className="font-semibold text-slate-900 dark:text-white text-sm">
                                                    {req.role?.display_name ?? 'Unknown Role'}
                                                </div>
                                                <div className="mt-1 flex items-center gap-1.5 flex-wrap">
                                                    <span className="font-mono text-[11px] text-slate-700 dark:text-slate-300 bg-slate-100 dark:bg-slate-800 px-2 py-0.5 rounded-md border border-slate-200 dark:border-slate-700">
                                                        {req.permission?.name}
                                                    </span>
                                                    {req.permission?.is_financial_restricted && (
                                                        <span className="text-[10px] font-bold text-amber-600 dark:text-amber-400 bg-amber-500/10 px-2 py-0.5 rounded-md border border-amber-500/30">
                                                            🔒 Financial Restricted
                                                        </span>
                                                    )}
                                                </div>
                                            </td>

                                            <td className="px-6 py-4">
                                                <div className="font-medium text-slate-800 dark:text-slate-200">
                                                    {req.requester?.name ?? 'Super Admin'}
                                                </div>
                                                <div className="text-[11px] text-slate-400 dark:text-slate-500">
                                                    {new Date(req.created_at).toLocaleString()}
                                                </div>
                                                <p className="text-[11px] text-slate-600 dark:text-slate-400 mt-1 italic max-w-sm">
                                                    "{req.reason}"
                                                </p>
                                            </td>

                                            <td className="px-6 py-4">
                                                {req.status === 'pending' && (
                                                    <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/30">
                                                        ⏳ Pending Review
                                                    </span>
                                                )}
                                                {req.status === 'approved' && (
                                                    <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30">
                                                        ✓ Authorized
                                                    </span>
                                                )}
                                                {req.status === 'rejected' && (
                                                    <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/30">
                                                        ✕ Rejected
                                                    </span>
                                                )}
                                            </td>

                                            <td className="px-6 py-4">
                                                {req.reviewer ? (
                                                    <div>
                                                        <div className="font-medium text-slate-800 dark:text-slate-200">
                                                            {req.reviewer.name}
                                                        </div>
                                                        <div className="text-[10px] text-slate-400 dark:text-slate-500">
                                                            {req.reviewed_at
                                                                ? new Date(req.reviewed_at).toLocaleDateString()
                                                                : ''}
                                                        </div>
                                                        {req.review_notes && (
                                                            <p className="text-[11px] text-slate-600 dark:text-slate-400 mt-0.5">
                                                                {req.review_notes}
                                                            </p>
                                                        )}
                                                    </div>
                                                ) : (
                                                    <span className="text-slate-400 dark:text-slate-500 italic text-[11px]">
                                                        Awaiting Owner decision
                                                    </span>
                                                )}
                                            </td>

                                            <td className="px-6 py-4 text-right whitespace-nowrap">
                                                {req.status === 'pending' && isOwner ? (
                                                    <div className="flex items-center justify-end gap-2">
                                                        <button
                                                            onClick={() => handleOpenAction('approve', req)}
                                                            className="px-3 py-1.5 text-xs font-semibold rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white transition-all shadow-xs shadow-emerald-500/20 cursor-pointer"
                                                        >
                                                            Authorize
                                                        </button>
                                                        <button
                                                            onClick={() => handleOpenAction('reject', req)}
                                                            className="px-3 py-1.5 text-xs font-semibold rounded-xl bg-rose-500/10 hover:bg-rose-500/20 text-rose-600 dark:text-rose-400 border border-rose-500/20 transition-all cursor-pointer"
                                                        >
                                                            Reject
                                                        </button>
                                                    </div>
                                                ) : (
                                                    <span className="text-slate-400 dark:text-slate-500 text-[11px]">—</span>
                                                )}
                                            </td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td
                                            colSpan={5}
                                            className="px-6 py-12 text-center text-slate-400 dark:text-slate-500 text-xs"
                                        >
                                            No permission change requests found for status '{currentStatus}'.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>

                {/* Review Action Modal */}
                {actionModal && (
                    <div className="fixed inset-0 z-50 overflow-y-auto bg-slate-900/70 backdrop-blur-xs flex items-center justify-center p-4">
                        <div className="bg-white dark:bg-[#0F172A] rounded-2xl max-w-lg w-full p-6 shadow-2xl border border-slate-200 dark:border-slate-800 transition-colors">
                            <div className="flex items-center justify-between pb-3 border-b border-slate-200 dark:border-slate-800 mb-4">
                                <h2 className="text-base font-bold text-slate-900 dark:text-white">
                                    {actionModal.type === 'approve'
                                        ? 'Authorize Restricted Financial Permission'
                                        : 'Reject Permission Request'}
                                </h2>
                                <button
                                    onClick={() => setActionModal(null)}
                                    className="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 text-lg font-bold"
                                >
                                    ✕
                                </button>
                            </div>

                            <p className="text-xs text-slate-600 dark:text-slate-400 mb-4">
                                {actionModal.type === 'approve'
                                    ? `As the Business Owner, you are authorizing the restricted permission '${actionModal.request.permission?.display_name}' to be assigned to role '${actionModal.request.role?.display_name}'.`
                                    : `Provide the reason for rejecting the requested capability '${actionModal.request.permission?.display_name}' for role '${actionModal.request.role?.display_name}'.`}
                            </p>

                            <form onSubmit={handleConfirmAction} className="space-y-4">
                                <div>
                                    <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                        Review Notes {actionModal.type === 'reject' && '*'}
                                    </label>
                                    <textarea
                                        value={data.review_notes}
                                        onChange={(e) => setData('review_notes', e.target.value)}
                                        rows={3}
                                        required={actionModal.type === 'reject'}
                                        placeholder={
                                            actionModal.type === 'approve'
                                                ? 'Optional authorization notes...'
                                                : 'State the reason for rejecting this capability...'
                                        }
                                        className="w-full text-xs rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white p-2.5 focus:ring-2 focus:ring-blue-500/30 focus:border-blue-500"
                                    />
                                    {errors.review_notes && (
                                        <p className="text-rose-500 text-[11px] mt-1">{errors.review_notes}</p>
                                    )}
                                </div>

                                <div className="flex items-center justify-end gap-3 pt-3 border-t border-slate-200 dark:border-slate-800">
                                    <button
                                        type="button"
                                        onClick={() => setActionModal(null)}
                                        className="px-4 py-2 text-xs font-semibold rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300 transition-colors"
                                    >
                                        Cancel
                                    </button>
                                    <button
                                        type="submit"
                                        disabled={processing}
                                        className={`px-5 py-2 text-xs font-semibold rounded-xl text-white transition-all shadow-xs cursor-pointer ${
                                            actionModal.type === 'approve'
                                                ? 'bg-emerald-600 hover:bg-emerald-700 shadow-emerald-500/20'
                                                : 'bg-rose-600 hover:bg-rose-700 shadow-rose-500/20'
                                        }`}
                                    >
                                        {processing
                                            ? 'Processing...'
                                            : actionModal.type === 'approve'
                                            ? 'Confirm Authorization'
                                            : 'Confirm Rejection'}
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
