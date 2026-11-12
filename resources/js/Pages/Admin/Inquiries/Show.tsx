import AdminLayout from '@/Layouts/AdminLayout';
import InquiryPriorityBadge from '@/Components/InquiryPriorityBadge';
import InquiryStatusBadge from '@/Components/InquiryStatusBadge';
import * as Icons from '@/Components/Icons';
import { PageProps } from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import React, { useState } from 'react';

interface InquiryDetail {
    id: number;
    inquiry_number: string;
    name: string;
    email: string;
    phone: string | null;
    subject: string;
    message: string;
    status: string;
    priority: string;
    assigned_to: number | null;
    assigned_staff: {
        id: number;
        name: string;
        email: string;
    } | null;
    resolved_at: string | null;
    resolved_by: {
        id: number;
        name: string;
    } | null;
    notification_status: string;
    created_at: string | null;
    updated_at: string | null;
}

interface CustomerDossier {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    status: string;
    member_since: string | null;
    orders_count: number;
    total_spent: number;
}

interface InquiryNote {
    id: number;
    note: string;
    created_at: string | null;
    author: {
        id: number;
        name: string;
        role: string;
    } | null;
}

interface EligibleStaffMember {
    id: number;
    name: string;
    role: string;
}

interface Props {
    inquiry: InquiryDetail;
    customerDossier: CustomerDossier | null;
    notes: InquiryNote[];
    eligibleStaff: EligibleStaffMember[];
    statuses: string[];
    priorities: string[];
}

export default function CustomerInquiryShow({
    inquiry,
    customerDossier,
    notes,
    eligibleStaff,
    statuses,
    priorities,
}: Props) {
    const { auth, flash } = usePage<PageProps>().props;
    const currentUserId = auth?.user?.id;

    // Allowed State Machine Transitions
    const allowedTransitions: Record<string, string[]> = {
        new: ['in_progress', 'closed'],
        in_progress: ['waiting_customer', 'resolved', 'closed'],
        waiting_customer: ['in_progress', 'resolved', 'closed'],
        resolved: ['in_progress', 'closed'],
        closed: ['in_progress'],
    };

    const nextAllowedStates = allowedTransitions[inquiry.status] ?? [];

    // Status Form
    const [selectedStatus, setSelectedStatus] = useState<string>(inquiry.status);
    const [showReopenModal, setShowReopenModal] = useState<boolean>(false);
    const statusForm = useForm({
        status: inquiry.status,
        reason: '',
    });

    // Priority Form
    const priorityForm = useForm({
        priority: inquiry.priority,
    });

    // Assignment Form
    const assignForm = useForm({
        staff_user_id: inquiry.assigned_to ? String(inquiry.assigned_to) : '',
    });

    // Note Form (Append-only)
    const noteForm = useForm({
        note: '',
    });

    const formatDate = (dateString: string | null) => {
        if (!dateString) return '—';
        return new Date(dateString).toLocaleDateString('en-GB', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    };

    const formatCurrency = (amount: number) => {
        return new Intl.NumberFormat('en-LK', {
            style: 'currency',
            currency: 'LKR',
            minimumFractionDigits: 2,
        }).format(amount);
    };

    const formatRole = (role?: string) => {
        if (!role) return 'Staff';
        return role
            .replace(/_/g, ' ')
            .replace(/\b\w/g, (c) => c.toUpperCase());
    };

    // Handle Status Change Submission
    const handleStatusSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        const targetStatus = selectedStatus;

        if (targetStatus === inquiry.status) {
            return;
        }

        // If reopening from closed, require modal/reason
        if (inquiry.status === 'closed' && targetStatus === 'in_progress' && !statusForm.data.reason.trim()) {
            setShowReopenModal(true);
            return;
        }

        statusForm.setData('status', targetStatus);
        statusForm.patch(`/admin/inquiries/${inquiry.id}/status`, {
            preserveScroll: true,
            onSuccess: () => {
                setShowReopenModal(false);
                statusForm.reset('reason');
            },
        });
    };

    const handleReopenModalConfirm = (e: React.FormEvent) => {
        e.preventDefault();
        if (!statusForm.data.reason.trim()) return;

        statusForm.setData('status', 'in_progress');
        statusForm.patch(`/admin/inquiries/${inquiry.id}/status`, {
            preserveScroll: true,
            onSuccess: () => {
                setShowReopenModal(false);
                setSelectedStatus('in_progress');
                statusForm.reset('reason');
            },
        });
    };

    // Handle Priority Submission
    const handlePrioritySubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (priorityForm.data.priority === inquiry.priority) return;

        priorityForm.patch(`/admin/inquiries/${inquiry.id}/priority`, {
            preserveScroll: true,
        });
    };

    // Handle Staff Assignment Submission
    const handleAssignSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        assignForm.patch(`/admin/inquiries/${inquiry.id}/assign`, {
            preserveScroll: true,
        });
    };

    // Quick Assign to Me
    const handleAssignToMe = () => {
        if (!currentUserId) return;
        const previousStaffId = inquiry.assigned_to ? String(inquiry.assigned_to) : '';
        assignForm.setData('staff_user_id', String(currentUserId));
        assignForm.patch(`/admin/inquiries/${inquiry.id}/assign`, {
            preserveScroll: true,
            onError: () => {
                assignForm.setData('staff_user_id', previousStaffId);
            },
        });
    };

    // Handle Append-Only Note Submission
    const handleNoteSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!noteForm.data.note.trim()) return;

        noteForm.post(`/admin/inquiries/${inquiry.id}/notes`, {
            preserveScroll: true,
            onSuccess: () => {
                noteForm.reset('note');
            },
        });
    };

    const isCurrentStaffAssigned = currentUserId && inquiry.assigned_to === currentUserId;
    const canAssignToMe = currentUserId && !isCurrentStaffAssigned && eligibleStaff.some((s) => s.id === currentUserId);

    return (
        <AdminLayout>
            <Head title={`Inquiry ${inquiry.inquiry_number} | Aura Luxe Admin`} />

            <div className="space-y-6">
                {/* Flash Messages */}
                {flash?.success && (
                    <div className="p-4 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800/60 text-emerald-800 dark:text-emerald-300 text-xs flex items-center justify-between">
                        <div className="flex items-center gap-2">
                            <Icons.CheckCircle2 className="w-4 h-4 text-emerald-600 dark:text-emerald-400 shrink-0" />
                            <span>{flash.success}</span>
                        </div>
                    </div>
                )}
                {flash?.error && (
                    <div className="p-4 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800/60 text-rose-800 dark:text-rose-300 text-xs flex items-center justify-between">
                        <div className="flex items-center gap-2">
                            <Icons.AlertCircle className="w-4 h-4 text-rose-600 dark:text-rose-400 shrink-0" />
                            <span>{flash.error}</span>
                        </div>
                    </div>
                )}

                {/* Breadcrumb & Header Bar */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <div className="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400 mb-1">
                            <Link
                                href="/admin/inquiries"
                                className="hover:text-amber-600 dark:hover:text-amber-400 flex items-center gap-1 transition-colors"
                            >
                                <Icons.ChevronLeft className="w-3.5 h-3.5" />
                                Back to Inquiries
                            </Link>
                            <span>/</span>
                            <span className="font-mono text-slate-700 dark:text-slate-300">
                                {inquiry.inquiry_number}
                            </span>
                        </div>
                        <h1 className="font-serif text-2xl text-slate-900 dark:text-white font-semibold flex items-center gap-2.5">
                            <span>Inquiry {inquiry.inquiry_number}</span>
                        </h1>
                        <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
                            Submitted on {formatDate(inquiry.created_at)} · Last activity {formatDate(inquiry.updated_at)}
                        </p>
                    </div>

                    <div className="flex items-center gap-2">
                        <InquiryPriorityBadge priority={inquiry.priority} />
                        <InquiryStatusBadge status={inquiry.status} />
                    </div>
                </div>

                {/* Main 2-Column Grid */}
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {/* Left Column (2 Cols) — Inquiry Content & Notes Thread */}
                    <div className="lg:col-span-2 space-y-6">
                        {/* Customer Inquiry Message Card */}
                        <div className="bg-white dark:bg-[#0F172A] p-6 rounded-2xl border border-slate-200/80 dark:border-slate-800/80 shadow-xs space-y-5">
                            {/* Contact Header */}
                            <div className="flex flex-wrap items-center justify-between gap-3 pb-4 border-b border-slate-100 dark:border-slate-800">
                                <div>
                                    <h2 className="text-base font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                                        <Icons.User className="w-4 h-4 text-amber-600 dark:text-amber-400" />
                                        {inquiry.name}
                                    </h2>
                                    <div className="flex flex-wrap items-center gap-3 text-xs text-slate-500 dark:text-slate-400 mt-1">
                                        <a
                                            href={`mailto:${inquiry.email}`}
                                            className="hover:text-amber-600 dark:hover:text-amber-400 flex items-center gap-1"
                                        >
                                            <Icons.Mail className="w-3.5 h-3.5" />
                                            {inquiry.email}
                                        </a>
                                        {inquiry.phone && (
                                            <a
                                                href={`tel:${inquiry.phone}`}
                                                className="hover:text-amber-600 dark:hover:text-amber-400 flex items-center gap-1"
                                            >
                                                <Icons.Phone className="w-3.5 h-3.5" />
                                                {inquiry.phone}
                                            </a>
                                        )}
                                    </div>
                                </div>

                                <div className="text-right">
                                    <span className="text-[11px] text-slate-400 block">Notification Status</span>
                                    <span
                                        className={`inline-block px-2 py-0.5 rounded text-[11px] font-medium mt-0.5 ${
                                            inquiry.notification_status === 'sent'
                                                ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400'
                                                : inquiry.notification_status === 'failed'
                                                ? 'bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-400'
                                                : 'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-400'
                                        }`}
                                    >
                                        {inquiry.notification_status.toUpperCase()}
                                    </span>
                                </div>
                            </div>

                            {/* Subject */}
                            <div>
                                <span className="text-[11px] uppercase tracking-wider text-slate-400 block font-medium">
                                    Subject
                                </span>
                                <h3 className="text-base font-semibold text-slate-900 dark:text-white mt-1">
                                    {inquiry.subject}
                                </h3>
                            </div>

                            {/* Message Body */}
                            <div>
                                <span className="text-[11px] uppercase tracking-wider text-slate-400 block font-medium">
                                    Message
                                </span>
                                <div className="mt-2 p-4 rounded-xl bg-slate-50 dark:bg-slate-900/60 border border-slate-100 dark:border-slate-800 text-xs text-slate-800 dark:text-slate-200 leading-relaxed whitespace-pre-wrap font-sans">
                                    {inquiry.message}
                                </div>
                            </div>
                        </div>

                        {/* Internal Notes Thread (Append-Only) */}
                        <div className="bg-white dark:bg-[#0F172A] p-6 rounded-2xl border border-slate-200/80 dark:border-slate-800/80 shadow-xs space-y-6">
                            <div className="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800">
                                <div>
                                    <h2 className="text-base font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                                        <Icons.CheckSquare className="w-4 h-4 text-amber-600 dark:text-amber-400" />
                                        Internal Staff Notes
                                        <span className="text-xs font-normal text-slate-400 bg-slate-100 dark:bg-slate-800 px-2 py-0.5 rounded-full">
                                            {notes.length}
                                        </span>
                                    </h2>
                                    <p className="text-[11px] text-slate-400 mt-1">
                                        Notes are immutable audit records and cannot be edited or deleted once appended.
                                    </p>
                                </div>
                            </div>

                            {/* Notes List */}
                            <div className="space-y-4">
                                {notes.length > 0 ? (
                                    notes.map((note) => (
                                        <div
                                            key={note.id}
                                            className="p-4 rounded-xl border border-slate-100 dark:border-slate-800/80 bg-slate-50/50 dark:bg-slate-900/40 space-y-2"
                                        >
                                            <div className="flex items-center justify-between text-xs">
                                                <div className="flex items-center gap-2">
                                                    <span className="font-medium text-slate-900 dark:text-white">
                                                        {note.author?.name ?? 'System Staff'}
                                                    </span>
                                                    {note.author?.role && (
                                                        <span className="px-1.5 py-0.2 rounded text-[10px] font-medium bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-400">
                                                            {formatRole(note.author.role)}
                                                        </span>
                                                    )}
                                                </div>
                                                <span className="text-[11px] text-slate-400 flex items-center gap-1">
                                                    <Icons.Clock className="w-3 h-3" />
                                                    {formatDate(note.created_at)}
                                                </span>
                                            </div>
                                            <p className="text-xs text-slate-700 dark:text-slate-300 whitespace-pre-wrap leading-relaxed">
                                                {note.note}
                                            </p>
                                        </div>
                                    ))
                                ) : (
                                    <div className="py-6 text-center text-slate-400 text-xs">
                                        No internal notes have been recorded for this inquiry yet.
                                    </div>
                                )}
                            </div>

                            {/* Append-Only Note Form */}
                            <form onSubmit={handleNoteSubmit} className="pt-4 border-t border-slate-100 dark:border-slate-800 space-y-3">
                                <div>
                                    <label
                                        htmlFor="note-input"
                                        className="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1"
                                    >
                                        Append Internal Note
                                    </label>
                                    <textarea
                                        id="note-input"
                                        rows={3}
                                        value={noteForm.data.note}
                                        onChange={(e) => noteForm.setData('note', e.target.value)}
                                        placeholder="Add an internal observation, customer call summary, or coordination notes..."
                                        maxLength={5000}
                                        className="w-full p-3 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200 placeholder-slate-400 focus:outline-none focus:ring-1 focus:ring-amber-500 focus:border-amber-500"
                                    />
                                    {noteForm.errors.note && (
                                        <p className="text-[11px] text-rose-600 dark:text-rose-400 mt-1">
                                            {noteForm.errors.note}
                                        </p>
                                    )}
                                </div>

                                <div className="flex items-center justify-between text-[11px] text-slate-400">
                                    <span>{noteForm.data.note.length} / 5,000 characters</span>
                                    <button
                                        type="submit"
                                        disabled={noteForm.processing || !noteForm.data.note.trim()}
                                        className="px-4 py-2 rounded-xl bg-amber-600 hover:bg-amber-700 text-white font-medium text-xs transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-1.5"
                                    >
                                        {noteForm.processing ? (
                                            <>
                                                <Icons.RefreshCw className="w-3.5 h-3.5 animate-spin" />
                                                Appending Note...
                                            </>
                                        ) : (
                                            <>
                                                <Icons.Save className="w-3.5 h-3.5" />
                                                Append Note
                                            </>
                                        )}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    {/* Right Column (1 Col) — Status, Priority, Staff Assignment, Customer Dossier */}
                    <div className="space-y-6">
                        {/* Status Management Card */}
                        <div className="bg-white dark:bg-[#0F172A] p-5 rounded-2xl border border-slate-200/80 dark:border-slate-800/80 shadow-xs space-y-4">
                            <h3 className="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                Lifecycle Status
                            </h3>

                            <div className="flex items-center justify-between">
                                <span className="text-xs text-slate-600 dark:text-slate-400">Current Status</span>
                                <InquiryStatusBadge status={inquiry.status} />
                            </div>

                            {/* Resolution metadata if present */}
                            {(inquiry.resolved_at || inquiry.resolved_by) && (
                                <div className="p-3 rounded-xl bg-slate-50 dark:bg-slate-900/50 border border-slate-100 dark:border-slate-800 text-[11px] space-y-1">
                                    {inquiry.resolved_by && (
                                        <div className="text-slate-600 dark:text-slate-400">
                                            Resolved by: <span className="font-medium text-slate-800 dark:text-slate-200">{inquiry.resolved_by.name}</span>
                                        </div>
                                    )}
                                    {inquiry.resolved_at && (
                                        <div className="text-slate-600 dark:text-slate-400">
                                            Resolved on: <span className="text-slate-800 dark:text-slate-200">{formatDate(inquiry.resolved_at)}</span>
                                        </div>
                                    )}
                                </div>
                            )}

                            {/* State Transition Selector */}
                            <form onSubmit={handleStatusSubmit} className="space-y-3 pt-2 border-t border-slate-100 dark:border-slate-800">
                                <div>
                                    <label className="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">
                                        Transition Status
                                    </label>
                                    <select
                                        value={selectedStatus}
                                        onChange={(e) => setSelectedStatus(e.target.value)}
                                        className="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-1 focus:ring-amber-500 focus:border-amber-500"
                                    >
                                        <option value={inquiry.status} disabled>
                                            {inquiry.status.replace(/_/g, ' ').toUpperCase()} (Current)
                                        </option>
                                        {nextAllowedStates.map((st) => (
                                            <option key={st} value={st}>
                                                Move to {st.replace(/_/g, ' ').toUpperCase()}
                                            </option>
                                        ))}
                                    </select>
                                    {statusForm.errors.status && (
                                        <p className="text-[11px] text-rose-600 dark:text-rose-400 mt-1">
                                            {statusForm.errors.status}
                                        </p>
                                    )}
                                </div>

                                <button
                                    type="submit"
                                    disabled={
                                        statusForm.processing ||
                                        selectedStatus === inquiry.status ||
                                        nextAllowedStates.length === 0
                                    }
                                    className="w-full py-2 px-3 rounded-xl bg-slate-900 hover:bg-slate-800 dark:bg-amber-600 dark:hover:bg-amber-700 text-white font-medium text-xs transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
                                >
                                    {statusForm.processing ? 'Updating Status...' : 'Apply Status Transition'}
                                </button>
                            </form>
                        </div>

                        {/* Priority Management Card */}
                        <div className="bg-white dark:bg-[#0F172A] p-5 rounded-2xl border border-slate-200/80 dark:border-slate-800/80 shadow-xs space-y-4">
                            <h3 className="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                Priority Level
                            </h3>

                            <div className="flex items-center justify-between">
                                <span className="text-xs text-slate-600 dark:text-slate-400">Current Priority</span>
                                <InquiryPriorityBadge priority={inquiry.priority} />
                            </div>

                            <form onSubmit={handlePrioritySubmit} className="space-y-3 pt-2 border-t border-slate-100 dark:border-slate-800">
                                <div>
                                    <label className="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">
                                        Update Priority
                                    </label>
                                    <select
                                        value={priorityForm.data.priority}
                                        onChange={(e) => priorityForm.setData('priority', e.target.value)}
                                        className="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-1 focus:ring-amber-500 focus:border-amber-500"
                                    >
                                        {priorities.map((p) => (
                                            <option key={p} value={p}>
                                                {p.charAt(0).toUpperCase() + p.slice(1)}
                                            </option>
                                        ))}
                                    </select>
                                    {priorityForm.errors.priority && (
                                        <p className="text-[11px] text-rose-600 dark:text-rose-400 mt-1">
                                            {priorityForm.errors.priority}
                                        </p>
                                    )}
                                </div>

                                <button
                                    type="submit"
                                    disabled={priorityForm.processing || priorityForm.data.priority === inquiry.priority}
                                    className="w-full py-2 px-3 rounded-xl border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 font-medium text-xs transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
                                >
                                    {priorityForm.processing ? 'Updating Priority...' : 'Update Priority'}
                                </button>
                            </form>
                        </div>

                        {/* Staff Assignment Card */}
                        <div className="bg-white dark:bg-[#0F172A] p-5 rounded-2xl border border-slate-200/80 dark:border-slate-800/80 shadow-xs space-y-4">
                            <h3 className="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                Staff Assignment
                            </h3>

                            <div className="flex items-center justify-between text-xs">
                                <span className="text-slate-600 dark:text-slate-400">Assigned Staff</span>
                                <span className="font-medium text-slate-900 dark:text-white">
                                    {inquiry.assigned_staff?.name ?? 'Unassigned'}
                                </span>
                            </div>

                            <form onSubmit={handleAssignSubmit} className="space-y-3 pt-2 border-t border-slate-100 dark:border-slate-800">
                                <div>
                                    <label className="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">
                                        Assignee
                                    </label>
                                    <select
                                        value={assignForm.data.staff_user_id}
                                        onChange={(e) => assignForm.setData('staff_user_id', e.target.value)}
                                        className="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-1 focus:ring-amber-500 focus:border-amber-500"
                                    >
                                        <option value="">Unassigned</option>
                                        {eligibleStaff.map((staff) => (
                                            <option key={staff.id} value={staff.id}>
                                                {staff.name} ({formatRole(staff.role)})
                                            </option>
                                        ))}
                                    </select>
                                    {assignForm.errors.staff_user_id && (
                                        <p className="text-[11px] text-rose-600 dark:text-rose-400 mt-1">
                                            {assignForm.errors.staff_user_id}
                                        </p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <button
                                        type="submit"
                                        disabled={assignForm.processing}
                                        className="w-full py-2 px-3 rounded-xl border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 font-medium text-xs transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
                                    >
                                        {assignForm.processing ? 'Updating Assignment...' : 'Save Assignment'}
                                    </button>

                                    {canAssignToMe && (
                                        <button
                                            type="button"
                                            onClick={handleAssignToMe}
                                            disabled={assignForm.processing}
                                            className="w-full py-1.5 px-3 rounded-xl text-amber-600 dark:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-950/40 text-xs font-medium transition-colors"
                                        >
                                            Assign to Me
                                        </button>
                                    )}
                                </div>
                            </form>
                        </div>

                        {/* Customer Dossier Card */}
                        <div className="bg-white dark:bg-[#0F172A] p-5 rounded-2xl border border-slate-200/80 dark:border-slate-800/80 shadow-xs space-y-4">
                            <h3 className="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                Customer Account Dossier
                            </h3>

                            {customerDossier ? (
                                <div className="space-y-3 text-xs">
                                    <div className="flex items-center justify-between pb-2 border-b border-slate-100 dark:border-slate-800">
                                        <span className="text-slate-500 dark:text-slate-400">Account Status</span>
                                        <span
                                            className={`px-2 py-0.5 rounded text-[10px] font-semibold ${
                                                customerDossier.status === 'active'
                                                    ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400'
                                                    : 'bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-400'
                                            }`}
                                        >
                                            {customerDossier.status.toUpperCase()}
                                        </span>
                                    </div>

                                    <div className="flex items-center justify-between">
                                        <span className="text-slate-500 dark:text-slate-400">Member Since</span>
                                        <span className="text-slate-800 dark:text-slate-200">
                                            {formatDate(customerDossier.member_since)}
                                        </span>
                                    </div>

                                    <div className="flex items-center justify-between">
                                        <span className="text-slate-500 dark:text-slate-400">Completed Orders</span>
                                        <span className="font-semibold text-slate-900 dark:text-white">
                                            {customerDossier.orders_count}
                                        </span>
                                    </div>

                                    <div className="flex items-center justify-between">
                                        <span className="text-slate-500 dark:text-slate-400">Lifetime Spend</span>
                                        <span className="font-semibold text-emerald-600 dark:text-emerald-400">
                                            {formatCurrency(customerDossier.total_spent)}
                                        </span>
                                    </div>

                                    <div className="pt-2">
                                        <Link
                                            href={`/admin/customers/${customerDossier.id}`}
                                            className="w-full inline-flex items-center justify-center gap-1.5 py-2 px-3 rounded-xl border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 font-medium text-xs transition-colors"
                                        >
                                            View Customer Profile
                                            <Icons.ChevronRight className="w-3.5 h-3.5" />
                                        </Link>
                                    </div>
                                </div>
                            ) : (
                                <div className="p-3 rounded-xl bg-slate-50 dark:bg-slate-900/50 border border-slate-100 dark:border-slate-800 text-xs text-slate-500 dark:text-slate-400 space-y-1">
                                    <div className="font-medium text-slate-700 dark:text-slate-300">
                                        Guest Submission
                                    </div>
                                    <p className="text-[11px] leading-relaxed">
                                        This inquiry was submitted by an unregistered visitor. No storefront customer account is currently linked.
                                    </p>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>

            {/* Mandatory Reopening Modal for Closed Inquiries */}
            {showReopenModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
                    <div className="bg-white dark:bg-[#0F172A] w-full max-w-md rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xl p-6 space-y-4">
                        <div className="flex items-center gap-3">
                            <div className="w-10 h-10 rounded-full bg-amber-50 dark:bg-amber-950/50 flex items-center justify-center text-amber-600 dark:text-amber-400 shrink-0">
                                <Icons.AlertCircle className="w-5 h-5" />
                            </div>
                            <div>
                                <h3 className="font-serif text-base font-semibold text-slate-900 dark:text-white">
                                    Reopen Closed Inquiry
                                </h3>
                                <p className="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                    Inquiry #{inquiry.inquiry_number}
                                </p>
                            </div>
                        </div>

                        <p className="text-xs text-slate-600 dark:text-slate-300 leading-relaxed">
                            Reopening a closed inquiry requires a mandatory justification note. This explanation will be permanently recorded in the audit trail.
                        </p>

                        <form onSubmit={handleReopenModalConfirm} className="space-y-3">
                            {statusForm.errors.status && (
                                <div className="p-3 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800/60 text-rose-700 dark:text-rose-300 text-xs flex items-center gap-2">
                                    <Icons.AlertCircle className="w-4 h-4 shrink-0" />
                                    <span>{statusForm.errors.status}</span>
                                </div>
                            )}

                            <div>
                                <label className="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">
                                    Reopening Justification <span className="text-rose-500">*</span>
                                </label>
                                <textarea
                                    rows={3}
                                    value={statusForm.data.reason}
                                    onChange={(e) => statusForm.setData('reason', e.target.value)}
                                    placeholder="State why this closed inquiry is being reopened..."
                                    className="w-full p-2.5 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200 placeholder-slate-400 focus:outline-none focus:ring-1 focus:ring-amber-500 focus:border-amber-500"
                                    required
                                />
                                {statusForm.errors.reason && (
                                    <p className="text-[11px] text-rose-600 dark:text-rose-400 mt-1">
                                        {statusForm.errors.reason}
                                    </p>
                                )}
                            </div>

                            <div className="flex items-center justify-end gap-2 pt-2">
                                <button
                                    type="button"
                                    onClick={() => {
                                        setShowReopenModal(false);
                                        setSelectedStatus(inquiry.status);
                                    }}
                                    className="px-3 py-1.5 text-xs text-slate-600 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200 font-medium"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={statusForm.processing || !statusForm.data.reason.trim()}
                                    className="px-4 py-1.5 text-xs rounded-xl bg-amber-600 hover:bg-amber-700 text-white font-medium transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
                                >
                                    {statusForm.processing ? 'Reopening...' : 'Confirm Reopen'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AdminLayout>
    );
}

