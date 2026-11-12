import AdminLayout from '@/Layouts/AdminLayout';
import InquiryPriorityBadge from '@/Components/InquiryPriorityBadge';
import InquiryStatusBadge from '@/Components/InquiryStatusBadge';
import * as Icons from '@/Components/Icons';
import { PaginatedData } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import React, { useState } from 'react';

interface InquiryListItem {
    id: number;
    inquiry_number: string;
    customer_id: number | null;
    name: string;
    email: string;
    phone: string | null;
    subject: string;
    status: string;
    priority: string;
    notification_status: string;
    created_at: string | null;
    assigned_staff: {
        id: number;
        name: string;
        email: string;
    } | null;
}

interface Props {
    inquiries: PaginatedData<InquiryListItem>;
    filters: {
        search?: string;
        status?: string;
        priority?: string;
        assigned_to?: string;
    };
    stats: {
        total: number;
        new: number;
        in_progress: number;
        waiting_customer: number;
        resolved: number;
        closed: number;
        urgent_open: number;
    };
    eligibleStaff: Array<{
        id: number;
        name: string;
        role: string;
    }>;
}

export default function CustomerInquiryIndex({ inquiries, filters, stats, eligibleStaff }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [status, setStatus] = useState(filters.status ?? '');
    const [priority, setPriority] = useState(filters.priority ?? '');
    const [assignedTo, setAssignedTo] = useState(filters.assigned_to ?? '');
    const [isFiltering, setIsFiltering] = useState(false);

    const handleFilter = (e: React.FormEvent) => {
        e.preventDefault();
        if (isFiltering) return;

        router.get(
            '/admin/inquiries',
            {
                search: search || undefined,
                status: status || undefined,
                priority: priority || undefined,
                assigned_to: assignedTo || undefined,
            },
            {
                preserveState: true,
                onStart: () => setIsFiltering(true),
                onFinish: () => setIsFiltering(false),
            }
        );
    };

    const handleClear = () => {
        if (isFiltering) return;
        setSearch('');
        setStatus('');
        setPriority('');
        setAssignedTo('');
        router.get(
            '/admin/inquiries',
            {},
            {
                onStart: () => setIsFiltering(true),
                onFinish: () => setIsFiltering(false),
            }
        );
    };

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

    const hasActiveFilters = Boolean(
        filters.search || filters.status || filters.priority || filters.assigned_to
    );

    const openQueueCount = (stats.new ?? 0) + (stats.in_progress ?? 0);

    return (
        <AdminLayout>
            <Head title="Customer Inquiries | Aura Luxe Admin" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h1 className="font-serif text-2xl text-slate-900 dark:text-white font-semibold flex items-center gap-2">
                            <Icons.Mail className="w-6 h-6 text-amber-600 dark:text-amber-400" />
                            Customer Inquiries
                        </h1>
                        <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
                            Review customer inquiries, track resolution lifecycle, assign responsible staff, and record append-only internal notes.
                        </p>
                    </div>
                </div>

                {/* Quick Metric Cards */}
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 text-xs">
                    {/* Total Inquiries */}
                    <div className="bg-white dark:bg-[#0F172A] p-4 rounded-2xl border border-slate-200/80 dark:border-slate-800/80 shadow-xs">
                        <span className="text-slate-500 dark:text-slate-400 block font-medium">Total Inquiries</span>
                        <span className="font-serif text-2xl font-bold text-slate-900 dark:text-white mt-1 block">
                            {stats.total.toLocaleString()}
                        </span>
                        <span className="text-[11px] text-slate-400 mt-0.5 block">Lifetime customer inquiries</span>
                    </div>

                    {/* Active Open Queue */}
                    <div className="bg-white dark:bg-[#0F172A] p-4 rounded-2xl border border-slate-200/80 dark:border-slate-800/80 shadow-xs">
                        <span className="text-amber-600 dark:text-amber-400 block font-medium">Active Open Queue</span>
                        <span className="font-serif text-2xl font-bold text-amber-700 dark:text-amber-300 mt-1 block">
                            {openQueueCount.toLocaleString()}
                        </span>
                        <span className="text-[11px] text-slate-400 mt-0.5 block">
                            {stats.new} new · {stats.in_progress} in progress
                        </span>
                    </div>

                    {/* Waiting on Customer */}
                    <div className="bg-white dark:bg-[#0F172A] p-4 rounded-2xl border border-slate-200/80 dark:border-slate-800/80 shadow-xs">
                        <span className="text-purple-600 dark:text-purple-400 block font-medium">Waiting on Customer</span>
                        <span className="font-serif text-2xl font-bold text-purple-700 dark:text-purple-300 mt-1 block">
                            {stats.waiting_customer.toLocaleString()}
                        </span>
                        <span className="text-[11px] text-slate-400 mt-0.5 block">Pending customer response</span>
                    </div>

                    {/* Urgent Open */}
                    <div className="bg-white dark:bg-[#0F172A] p-4 rounded-2xl border border-slate-200/80 dark:border-slate-800/80 shadow-xs">
                        <span className="text-rose-600 dark:text-rose-400 block font-medium">Urgent Open</span>
                        <span className="font-serif text-2xl font-bold text-rose-700 dark:text-rose-300 mt-1 block">
                            {stats.urgent_open.toLocaleString()}
                        </span>
                        <span className="text-[11px] text-slate-400 mt-0.5 block">Priority triage attention</span>
                    </div>
                </div>

                {/* Filter Toolbar */}
                <div className="bg-white dark:bg-[#0F172A] p-4 rounded-2xl border border-slate-200/80 dark:border-slate-800/80 shadow-xs">
                    <form onSubmit={handleFilter} className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                        {/* Search Input */}
                        <div className="lg:col-span-2 relative">
                            <Icons.Search className="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" />
                            <input
                                type="text"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Search #, customer, email, phone..."
                                className="w-full pl-9 pr-3 py-2 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200 placeholder-slate-400 focus:outline-none focus:ring-1 focus:ring-amber-500 focus:border-amber-500"
                            />
                        </div>

                        {/* Status Filter */}
                        <div>
                            <select
                                value={status}
                                onChange={(e) => setStatus(e.target.value)}
                                className="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-1 focus:ring-amber-500 focus:border-amber-500"
                            >
                                <option value="">All Statuses</option>
                                <option value="new">New</option>
                                <option value="in_progress">In Progress</option>
                                <option value="waiting_customer">Waiting on Customer</option>
                                <option value="resolved">Resolved</option>
                                <option value="closed">Closed</option>
                            </select>
                        </div>

                        {/* Priority Filter */}
                        <div>
                            <select
                                value={priority}
                                onChange={(e) => setPriority(e.target.value)}
                                className="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-1 focus:ring-amber-500 focus:border-amber-500"
                            >
                                <option value="">All Priorities</option>
                                <option value="urgent">Urgent</option>
                                <option value="high">High</option>
                                <option value="normal">Normal</option>
                                <option value="low">Low</option>
                            </select>
                        </div>

                        {/* Assigned Staff Filter */}
                        <div>
                            <select
                                value={assignedTo}
                                onChange={(e) => setAssignedTo(e.target.value)}
                                className="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-1 focus:ring-amber-500 focus:border-amber-500"
                            >
                                <option value="">All Staff</option>
                                <option value="unassigned">Unassigned</option>
                                {eligibleStaff.map((staff) => (
                                    <option key={staff.id} value={staff.id}>
                                        {staff.name}
                                    </option>
                                ))}
                            </select>
                        </div>

                        {/* Action Buttons */}
                        <div className="lg:col-span-5 flex items-center justify-end gap-2 pt-1 border-t border-slate-100 dark:border-slate-800">
                            {hasActiveFilters && (
                                <button
                                    type="button"
                                    onClick={handleClear}
                                    disabled={isFiltering}
                                    className="px-3 py-1.5 text-xs font-medium text-slate-600 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200 disabled:opacity-50 disabled:cursor-not-allowed"
                                >
                                    Reset Filters
                                </button>
                            )}
                            <button
                                type="submit"
                                disabled={isFiltering}
                                className="inline-flex items-center gap-1.5 px-4 py-1.5 text-xs font-medium rounded-xl bg-amber-600 hover:bg-amber-700 text-white transition-colors disabled:opacity-60 disabled:cursor-not-allowed"
                            >
                                {isFiltering ? (
                                    <>
                                        <Icons.RefreshCw className="w-3.5 h-3.5 animate-spin" />
                                        Filtering...
                                    </>
                                ) : (
                                    'Apply Filters'
                                )}
                            </button>
                        </div>
                    </form>
                </div>

                {/* Inquiries Table */}
                <div className={`bg-white dark:bg-[#0F172A] rounded-2xl border border-slate-200/80 dark:border-slate-800/80 shadow-xs overflow-hidden transition-opacity ${isFiltering ? 'opacity-60 pointer-events-none' : ''}`}>
                    <div className="overflow-x-auto">
                        <table className="w-full text-left border-collapse text-xs">
                            <thead>
                                <tr className="border-b border-slate-200/80 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/50 text-slate-500 dark:text-slate-400 font-medium">
                                    <th className="py-3 px-4">Inquiry</th>
                                    <th className="py-3 px-4">Customer</th>
                                    <th className="py-3 px-4">Subject</th>
                                    <th className="py-3 px-4">Priority</th>
                                    <th className="py-3 px-4">Status</th>
                                    <th className="py-3 px-4">Assigned To</th>
                                    <th className="py-3 px-4 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 dark:divide-slate-800/60">
                                {inquiries.data.length > 0 ? (
                                    inquiries.data.map((inquiry) => (
                                        <tr
                                            key={inquiry.id}
                                            className="hover:bg-slate-50/60 dark:hover:bg-slate-800/30 transition-colors"
                                        >
                                            {/* Inquiry # and Date */}
                                            <td className="py-3 px-4">
                                                <div className="font-mono font-medium text-slate-900 dark:text-white">
                                                    {inquiry.inquiry_number}
                                                </div>
                                                <div className="text-[11px] text-slate-400 mt-0.5">
                                                    {formatDate(inquiry.created_at)}
                                                </div>
                                            </td>

                                            {/* Customer Details */}
                                            <td className="py-3 px-4">
                                                <div className="font-medium text-slate-800 dark:text-slate-200 flex items-center gap-1.5">
                                                    {inquiry.name}
                                                    {inquiry.customer_id ? (
                                                        <span className="px-1.5 py-0.2 rounded text-[10px] font-semibold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400">
                                                            Registered
                                                        </span>
                                                    ) : (
                                                        <span className="px-1.5 py-0.2 rounded text-[10px] font-normal bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                                                            Guest
                                                        </span>
                                                    )}
                                                </div>
                                                <div className="text-[11px] text-slate-500 dark:text-slate-400">
                                                    {inquiry.email}
                                                </div>
                                                {inquiry.phone && (
                                                    <div className="text-[11px] text-slate-400">
                                                        {inquiry.phone}
                                                    </div>
                                                )}
                                            </td>

                                            {/* Subject */}
                                            <td className="py-3 px-4 max-w-xs">
                                                <div className="font-medium text-slate-800 dark:text-slate-200 truncate">
                                                    {inquiry.subject}
                                                </div>
                                            </td>

                                            {/* Priority */}
                                            <td className="py-3 px-4">
                                                <InquiryPriorityBadge priority={inquiry.priority} />
                                            </td>

                                            {/* Status */}
                                            <td className="py-3 px-4">
                                                <InquiryStatusBadge status={inquiry.status} />
                                            </td>

                                            {/* Assigned Staff */}
                                            <td className="py-3 px-4">
                                                {inquiry.assigned_staff ? (
                                                    <div className="flex items-center gap-1.5 text-slate-700 dark:text-slate-300">
                                                        <Icons.User className="w-3.5 h-3.5 text-slate-400" />
                                                        <span className="font-medium">{inquiry.assigned_staff.name}</span>
                                                    </div>
                                                ) : (
                                                    <span className="text-slate-400 italic">Unassigned</span>
                                                )}
                                            </td>

                                            {/* Actions */}
                                            <td className="py-3 px-4 text-right">
                                                <Link
                                                    href={`/admin/inquiries/${inquiry.id}`}
                                                    className="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 font-medium transition-colors"
                                                >
                                                    View Details
                                                    <Icons.ChevronRight className="w-3.5 h-3.5 text-slate-400" />
                                                </Link>
                                            </td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td colSpan={7} className="py-12 text-center">
                                            <div className="flex flex-col items-center justify-center text-slate-400">
                                                <Icons.Mail className="w-10 h-10 stroke-1 mb-2 text-slate-300 dark:text-slate-600" />
                                                <span className="font-medium text-slate-600 dark:text-slate-400">
                                                    No customer inquiries found.
                                                </span>
                                                <p className="text-slate-400 text-[11px] mt-1 max-w-sm">
                                                    {hasActiveFilters
                                                        ? 'Try clearing or adjusting your search filters to find what you are looking for.'
                                                        : 'New inquiries submitted via the customer portal will appear here for staff triage.'}
                                                </p>
                                                {hasActiveFilters && (
                                                    <button
                                                        type="button"
                                                        onClick={handleClear}
                                                        className="mt-3 text-amber-600 dark:text-amber-400 hover:underline text-xs font-medium"
                                                    >
                                                        Clear all filters
                                                    </button>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Pagination */}
                    {inquiries.links && inquiries.links.length > 3 && (
                        <div className="p-4 border-t border-slate-100 dark:border-slate-800/80 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs">
                            <span className="text-slate-500 dark:text-slate-400">
                                Showing {inquiries.from ?? 0} to {inquiries.to ?? 0} of {inquiries.total} inquiries
                            </span>
                            <div className="flex flex-wrap gap-1">
                                {inquiries.links.map((link, i) =>
                                    link.url ? (
                                        <Link
                                            key={i}
                                            href={link.url}
                                            preserveScroll
                                            dangerouslySetInnerHTML={{ __html: link.label }}
                                            className={`px-3 py-1.5 rounded-xl border text-xs transition-colors ${
                                                link.active
                                                    ? 'bg-amber-600 border-amber-600 text-white font-semibold'
                                                    : 'border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800'
                                            }`}
                                        />
                                    ) : (
                                        <span
                                            key={i}
                                            aria-disabled="true"
                                            dangerouslySetInnerHTML={{ __html: link.label }}
                                            className="px-3 py-1.5 rounded-xl border border-transparent text-xs text-slate-300 dark:text-slate-600 cursor-not-allowed select-none"
                                        />
                                    )
                                )}
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </AdminLayout>
    );
}

