import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import * as Icons from '@/Components/Icons';
import { AuditLog, PaginatedData } from '@/types';

interface Props {
    logs: PaginatedData<AuditLog>;
    filters: {
        action?: string;
        search?: string;
        date_from?: string;
        date_to?: string;
    };
    availableActions?: string[];
}

export default function SystemLogs({ logs, filters, availableActions = [] }: Props) {
    const [search, setSearch] = useState<string>(filters.search ?? '');
    const [action, setAction] = useState<string>(filters.action ?? '');
    const [dateFrom, setDateFrom] = useState<string>(filters.date_from ?? '');
    const [dateTo, setDateTo] = useState<string>(filters.date_to ?? '');
    const [selectedLog, setSelectedLog] = useState<AuditLog | null>(null);
    const [activeTab, setActiveTab] = useState<'visual' | 'json'>('visual');

    // Handle Search & Filter Submission
    const handleFilterSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(
            '/admin/system/logs',
            {
                search: search || undefined,
                action: action || undefined,
                date_from: dateFrom || undefined,
                date_to: dateTo || undefined,
            },
            { preserveState: true }
        );
    };

    // Clear All Filters
    const handleClearFilters = () => {
        setSearch('');
        setAction('');
        setDateFrom('');
        setDateTo('');
        router.get('/admin/system/logs');
    };

    // Role Badge Color Mapping
    const getRoleBadge = (role?: string) => {
        switch (role) {
            case 'owner':
                return 'bg-rose-100 text-rose-800 border-rose-200 dark:bg-rose-950/60 dark:text-rose-300 dark:border-rose-900';
            case 'super_admin':
            case 'it_manager':
                return 'bg-indigo-100 text-indigo-800 border-indigo-200 dark:bg-indigo-950/60 dark:text-indigo-300 dark:border-indigo-900';
            case 'accountant':
                return 'bg-emerald-100 text-emerald-800 border-emerald-200 dark:bg-emerald-950/60 dark:text-emerald-300 dark:border-emerald-900';
            case 'operations_manager':
                return 'bg-blue-100 text-blue-800 border-blue-200 dark:bg-blue-950/60 dark:text-blue-300 dark:border-blue-900';
            case 'marketing_manager':
                return 'bg-purple-100 text-purple-800 border-purple-200 dark:bg-purple-950/60 dark:text-purple-300 dark:border-purple-900';
            case 'agent_manager':
                return 'bg-cyan-100 text-cyan-800 border-cyan-200 dark:bg-cyan-950/60 dark:text-cyan-300 dark:border-cyan-900';
            case 'high_level_agent':
                return 'bg-amber-100 text-amber-800 border-amber-200 dark:bg-amber-950/60 dark:text-amber-300 dark:border-amber-900';
            case 'low_level_agent':
                return 'bg-slate-100 text-slate-800 border-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700';
            default:
                return 'bg-stone-100 text-stone-700 border-stone-200 dark:bg-stone-800 dark:text-stone-300 dark:border-stone-700';
        }
    };

    // Action Badge Color Mapping
    const getActionBadge = (actionName: string) => {
        if (actionName.includes('payout') || actionName.includes('approved')) {
            return 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/50 dark:text-emerald-300 dark:border-emerald-800';
        }
        if (actionName.includes('reject') || actionName.includes('reversed') || actionName.includes('fail') || actionName.includes('loss')) {
            return 'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-950/50 dark:text-rose-300 dark:border-rose-800';
        }
        if (actionName.includes('order') || actionName.includes('status')) {
            return 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-950/50 dark:text-blue-300 dark:border-blue-800';
        }
        if (actionName.includes('stock') || actionName.includes('inventory')) {
            return 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/50 dark:text-amber-300 dark:border-amber-800';
        }
        if (actionName.includes('financial') || actionName.includes('commission')) {
            return 'bg-purple-50 text-purple-700 border-purple-200 dark:bg-purple-950/50 dark:text-purple-300 dark:border-purple-800';
        }
        return 'bg-stone-100 text-stone-700 border-stone-200 dark:bg-stone-800 dark:text-stone-300 dark:border-stone-700';
    };

    // Formatted Relative Time
    const getRelativeTime = (dateStr: string) => {
        const now = new Date();
        const date = new Date(dateStr);
        const diffMs = now.getTime() - date.getTime();
        const diffMins = Math.floor(diffMs / (1000 * 60));
        const diffHours = Math.floor(diffMs / (1000 * 60 * 60));
        const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));

        if (diffMins < 1) return 'Just now';
        if (diffMins < 60) return `${diffMins}m ago`;
        if (diffHours < 24) return `${diffHours}h ago`;
        if (diffDays === 1) return 'Yesterday';
        if (diffDays < 30) return `${diffDays}d ago`;
        return date.toLocaleDateString();
    };

    // Format Entity Name
    const formatEntity = (type?: string | null, id?: number | string | null) => {
        if (!type && !id) return 'System Service';
        const cleanType = type ? type.split('\\').pop() : 'Entity';
        return `${cleanType} #${id ?? '—'}`;
    };

    // Quick Highlight from New Values
    const getQuickContext = (newValues?: Record<string, unknown> | null) => {
        if (!newValues) return null;
        if (newValues.status) return `Status: ${String(newValues.status)}`;
        if (newValues.order_number) return `Order: ${String(newValues.order_number)}`;
        if (newValues.actual_courier_fee !== undefined) return `Courier Fee: LKR ${Number(newValues.actual_courier_fee).toFixed(2)}`;
        if (newValues.calculated_amount !== undefined) return `Amount: LKR ${Number(newValues.calculated_amount).toFixed(2)}`;
        if (newValues.reversal_reason) return `Reason: ${String(newValues.reversal_reason)}`;
        if (newValues.perspective) return `Perspective: ${String(newValues.perspective)}`;
        return null;
    };

    const hasActiveFilters = Boolean(filters.search || filters.action || filters.date_from || filters.date_to);

    return (
        <AdminLayout>
            <Head title="Enterprise Audit Logs & Security Trail" />

            <div className="space-y-6 pb-12">
                {/* =========================================================================
                    HEADER
                   ========================================================================= */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white dark:bg-[#0F172A] p-6 rounded-2xl border border-slate-200/80 dark:border-slate-800/80 shadow-xs transition-colors">
                    <div>
                        <div className="flex items-center gap-2.5">
                            <span className="p-2 rounded-xl bg-rose-50 text-rose-700 dark:bg-rose-950/60 dark:text-rose-400 border border-rose-100 dark:border-rose-900/40">
                                <Icons.History className="w-5 h-5" />
                            </span>
                            <div>
                                <h1 className="text-xl sm:text-2xl font-serif font-bold text-slate-900 dark:text-white tracking-tight">
                                    Enterprise Audit Logs
                                </h1>
                                <p className="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                    Chronological, tamper-evident audit ledger capturing user operations, security events, financial changes, and state transitions.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div className="flex items-center gap-3">
                        <span className="text-xs font-semibold px-3 py-1.5 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700">
                            Total Entries: <strong>{logs.total.toLocaleString()}</strong>
                        </span>
                    </div>
                </div>

                {/* =========================================================================
                    FILTER & SEARCH TOOLBAR
                   ========================================================================= */}
                <div className="bg-white dark:bg-[#0F172A] p-5 rounded-2xl border border-slate-200/80 dark:border-slate-800/80 shadow-xs space-y-4 transition-colors">
                    <form onSubmit={handleFilterSubmit} className="grid grid-cols-1 sm:grid-cols-12 gap-3 items-end">
                        {/* Text Search */}
                        <div className="sm:col-span-4">
                            <label htmlFor="search" className="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">
                                Search Actor, Action, IP, Entity
                            </label>
                            <div className="relative">
                                <Icons.Search className="w-4 h-4 text-slate-400 absolute left-3 top-2.5 pointer-events-none" />
                                <input
                                    id="search"
                                    type="text"
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    placeholder="Search by name, email, action, IP..."
                                    className="w-full pl-9 text-xs bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-2 text-slate-800 dark:text-slate-200 focus:ring-rose-500 focus:border-rose-500"
                                />
                            </div>
                        </div>

                        {/* Action Filter */}
                        <div className="sm:col-span-3">
                            <label htmlFor="action" className="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">
                                Filter by Action Type
                            </label>
                            <select
                                id="action"
                                value={action}
                                onChange={(e) => setAction(e.target.value)}
                                className="w-full text-xs bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-2 text-slate-800 dark:text-slate-200 focus:ring-rose-500 focus:border-rose-500"
                            >
                                <option value="">All Audit Actions</option>
                                {availableActions.map((act) => (
                                    <option key={act} value={act}>
                                        {act}
                                    </option>
                                ))}
                            </select>
                        </div>

                        {/* Date From */}
                        <div className="sm:col-span-2">
                            <label htmlFor="date_from" className="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">
                                From Date
                            </label>
                            <input
                                id="date_from"
                                type="date"
                                value={dateFrom}
                                onChange={(e) => setDateFrom(e.target.value)}
                                className="w-full text-xs bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-2 text-slate-800 dark:text-slate-200 focus:ring-rose-500 focus:border-rose-500"
                            />
                        </div>

                        {/* Date To */}
                        <div className="sm:col-span-2">
                            <label htmlFor="date_to" className="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">
                                To Date
                            </label>
                            <input
                                id="date_to"
                                type="date"
                                value={dateTo}
                                onChange={(e) => setDateTo(e.target.value)}
                                className="w-full text-xs bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-2 text-slate-800 dark:text-slate-200 focus:ring-rose-500 focus:border-rose-500"
                            />
                        </div>

                        {/* Buttons */}
                        <div className="sm:col-span-1 flex items-center gap-1.5">
                            <button
                                type="submit"
                                className="w-full inline-flex items-center justify-center p-2 bg-slate-900 hover:bg-slate-800 dark:bg-rose-700 dark:hover:bg-rose-800 text-white rounded-xl text-xs font-semibold shadow-xs transition-colors cursor-pointer"
                                title="Apply Filters"
                            >
                                <Icons.Filter className="w-4 h-4" />
                            </button>
                            {hasActiveFilters && (
                                <button
                                    type="button"
                                    onClick={handleClearFilters}
                                    className="p-2 border border-slate-300 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-300 rounded-xl text-xs transition-colors cursor-pointer"
                                    title="Clear All Filters"
                                >
                                    ✕
                                </button>
                            )}
                        </div>
                    </form>
                </div>

                {/* =========================================================================
                    AUDIT LOGS TABLE
                   ========================================================================= */}
                <div className="bg-white dark:bg-[#0F172A] rounded-2xl border border-slate-200/80 dark:border-slate-800/80 shadow-xs overflow-hidden transition-colors">
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-slate-100 dark:divide-slate-800/80 text-xs text-left bg-white dark:bg-[#0F172A]">
                            <thead className="bg-slate-50 dark:bg-slate-900/60 text-slate-500 dark:text-slate-400 uppercase tracking-wider font-semibold text-[11px]">
                                <tr>
                                    <th className="px-6 py-3.5">Actor (කවුද කළේ)</th>
                                    <th className="px-6 py-3.5">Action & Activity (මොකක්ද කළේ)</th>
                                    <th className="px-6 py-3.5">Date & Time (දිනය සහ වේලාව)</th>
                                    <th className="px-6 py-3.5">IP Address</th>
                                    <th className="px-6 py-3.5 text-right">Details</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 dark:divide-slate-800/60 bg-white dark:bg-[#0F172A]">
                                {logs.data.length > 0 ? (
                                    logs.data.map((log) => {
                                        const quickContext = getQuickContext(log.new_values);
                                        return (
                                            <tr key={log.id} className="bg-white dark:bg-[#0F172A] hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition-colors">
                                                {/* 1. Actor (Who) */}
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <div className="flex items-center gap-3">
                                                        <div className="w-9 h-9 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 flex items-center justify-center font-bold text-xs shrink-0 border border-slate-200 dark:border-slate-700">
                                                            {log.user?.name ? log.user.name.charAt(0).toUpperCase() : 'S'}
                                                        </div>
                                                        <div>
                                                            <div className="font-semibold text-slate-900 dark:text-white text-xs">
                                                                {log.user?.name ?? 'System Automated Job'}
                                                            </div>
                                                            <div className="flex items-center gap-1.5 mt-0.5">
                                                                {log.user?.role ? (
                                                                    <span className={`px-2 py-0.2 rounded-full text-[10px] font-semibold border ${getRoleBadge(log.user.role)}`}>
                                                                        {log.user.role}
                                                                    </span>
                                                                ) : (
                                                                    <span className="px-2 py-0.2 rounded-full text-[10px] font-semibold bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400">
                                                                        System
                                                                    </span>
                                                                )}
                                                                {log.user?.email && (
                                                                    <span className="text-[11px] text-slate-400 font-mono hidden sm:inline">
                                                                        {log.user.email}
                                                                    </span>
                                                                )}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>

                                                {/* 2. Action & Activity (What) */}
                                                <td className="px-6 py-4">
                                                    <div>
                                                        <div className="flex items-center gap-2">
                                                            <span className={`font-mono font-semibold px-2.5 py-0.5 rounded-lg text-[11px] border ${getActionBadge(log.action)}`}>
                                                                {log.action}
                                                            </span>
                                                        </div>
                                                        <div className="text-[11px] text-slate-500 dark:text-slate-400 mt-1 flex items-center gap-2">
                                                            <span className="font-medium text-slate-700 dark:text-slate-300">
                                                                {formatEntity(log.auditable_type, log.auditable_id)}
                                                            </span>
                                                            {quickContext && (
                                                                <span className="text-slate-400 dark:text-slate-500 italic">
                                                                    &bull; {quickContext}
                                                                </span>
                                                            )}
                                                        </div>
                                                    </div>
                                                </td>

                                                {/* 3. Date & Time (When) */}
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <div className="text-slate-900 dark:text-white font-medium text-xs">
                                                        {new Date(log.created_at).toLocaleDateString('en-US', {
                                                            month: 'short',
                                                            day: 'numeric',
                                                            year: 'numeric',
                                                        })}
                                                        <span className="text-slate-500 dark:text-slate-400 ml-1.5 font-mono text-[11px]">
                                                            {new Date(log.created_at).toLocaleTimeString('en-US', {
                                                                hour: '2-digit',
                                                                minute: '2-digit',
                                                                second: '2-digit',
                                                            })}
                                                        </span>
                                                    </div>
                                                    <div className="text-[10px] text-slate-400 dark:text-slate-500 mt-0.5 font-medium">
                                                        {getRelativeTime(log.created_at)}
                                                    </div>
                                                </td>

                                                {/* 4. IP Address */}
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <span className="font-mono text-slate-700 dark:text-slate-300 bg-slate-100 dark:bg-slate-800 px-2 py-0.5 rounded-md text-[11px] border border-slate-200 dark:border-slate-700">
                                                        {log.ip_address ?? '127.0.0.1'}
                                                    </span>
                                                </td>

                                                {/* 5. Details Button */}
                                                <td className="px-6 py-4 text-right whitespace-nowrap">
                                                    <button
                                                        type="button"
                                                        onClick={() => {
                                                            setSelectedLog(log);
                                                            setActiveTab('visual');
                                                        }}
                                                        className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold text-slate-700 dark:text-slate-300 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 hover:text-slate-900 dark:hover:bg-slate-700 dark:hover:text-white border border-slate-200 dark:border-slate-700 transition-colors cursor-pointer"
                                                    >
                                                        <Icons.Eye className="w-3.5 h-3.5" />
                                                        <span>Inspect</span>
                                                    </button>
                                                </td>
                                            </tr>
                                        );
                                    })
                                ) : (
                                    <tr>
                                        <td colSpan={5} className="px-6 py-12 text-center text-slate-400 dark:text-slate-500 text-xs">
                                            No audit log entries matching criteria.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Pagination */}
                    {logs.last_page > 1 && (
                        <div className="p-4 border-t border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/40 flex flex-col sm:flex-row items-center justify-between gap-3 transition-colors">
                            <span className="text-xs text-slate-500 dark:text-slate-400">
                                Showing <strong>{logs.from ?? 0}</strong> to <strong>{logs.to ?? 0}</strong> of <strong>{logs.total}</strong> entries
                            </span>
                            <div className="flex items-center gap-1">
                                {logs.links.map((link, i) => (
                                    <Link
                                        key={i}
                                        href={link.url ?? '#'}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                        className={`px-3 py-1.5 rounded-lg text-xs font-medium transition-colors ${
                                            link.active
                                                ? 'bg-rose-700 text-white shadow-xs font-bold'
                                                : link.url
                                                ? 'bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700'
                                                : 'text-slate-400 dark:text-slate-600 cursor-not-allowed pointer-events-none'
                                        }`}
                                    />
                                ))}
                            </div>
                        </div>
                    )}
                </div>

                {/* =========================================================================
                    INSPECT DETAILS MODAL
                   ========================================================================= */}
                {selectedLog && (
                    <div className="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 dark:bg-black/80 backdrop-blur-xs flex items-center justify-center p-4">
                        <div className="bg-white dark:bg-[#0F172A] rounded-3xl max-w-2xl w-full p-6 shadow-2xl border border-slate-200/80 dark:border-slate-800 space-y-5 animate-in fade-in-50 zoom-in-95 duration-200 text-slate-900 dark:text-white">
                            {/* Modal Header */}
                            <div className="flex items-start justify-between border-b border-slate-100 dark:border-slate-800 pb-4">
                                <div>
                                    <div className="flex items-center gap-2">
                                        <span className={`font-mono font-bold px-2.5 py-0.5 rounded-lg text-xs border ${getActionBadge(selectedLog.action)}`}>
                                            {selectedLog.action}
                                        </span>
                                        <span className="text-xs text-slate-400 dark:text-slate-500 font-mono">
                                            Log #{selectedLog.id}
                                        </span>
                                    </div>
                                    <h2 className="text-base font-serif font-bold text-slate-900 dark:text-white mt-1">
                                        Audit Event Details
                                    </h2>
                                </div>
                                <button
                                    type="button"
                                    onClick={() => setSelectedLog(null)}
                                    className="p-1.5 rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors cursor-pointer"
                                >
                                    ✕
                                </button>
                            </div>

                            {/* Event Metadata Grid */}
                            <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs bg-slate-50 dark:bg-slate-800/50 p-4 rounded-2xl border border-slate-200/60 dark:border-slate-700/60">
                                <div>
                                    <span className="text-[10px] text-slate-400 dark:text-slate-500 uppercase font-semibold block">Actor</span>
                                    <span className="font-bold text-slate-800 dark:text-slate-200 mt-0.5 block truncate">
                                        {selectedLog.user?.name ?? 'System'}
                                    </span>
                                    <span className="text-[10px] text-slate-500 dark:text-slate-400 block">
                                        {selectedLog.user?.role ?? 'automated'}
                                    </span>
                                </div>
                                <div>
                                    <span className="text-[10px] text-slate-400 dark:text-slate-500 uppercase font-semibold block">IP Address</span>
                                    <span className="font-mono text-slate-800 dark:text-slate-200 mt-0.5 block">
                                        {selectedLog.ip_address ?? '127.0.0.1'}
                                    </span>
                                </div>
                                <div>
                                    <span className="text-[10px] text-slate-400 dark:text-slate-500 uppercase font-semibold block">Entity</span>
                                    <span className="font-medium text-slate-800 dark:text-slate-200 mt-0.5 block truncate">
                                        {formatEntity(selectedLog.auditable_type, selectedLog.auditable_id)}
                                    </span>
                                </div>
                                <div>
                                    <span className="text-[10px] text-slate-400 dark:text-slate-500 uppercase font-semibold block">Timestamp</span>
                                    <span className="text-slate-800 dark:text-slate-200 mt-0.5 block text-[11px]">
                                        {new Date(selectedLog.created_at).toLocaleString()}
                                    </span>
                                </div>
                            </div>

                            {/* Tab Switcher */}
                            <div className="flex items-center gap-2 border-b border-slate-100 dark:border-slate-800 pb-2">
                                <button
                                    type="button"
                                    onClick={() => setActiveTab('visual')}
                                    className={`px-3 py-1.5 rounded-xl text-xs font-semibold transition-colors cursor-pointer ${
                                        activeTab === 'visual'
                                            ? 'bg-slate-900 text-white dark:bg-rose-700'
                                            : 'text-slate-500 hover:text-slate-800 dark:hover:text-slate-200'
                                    }`}
                                >
                                    Changes & Values
                                </button>
                                <button
                                    type="button"
                                    onClick={() => setActiveTab('json')}
                                    className={`px-3 py-1.5 rounded-xl text-xs font-semibold transition-colors cursor-pointer ${
                                        activeTab === 'json'
                                            ? 'bg-slate-900 text-white dark:bg-rose-700'
                                            : 'text-slate-500 hover:text-slate-800 dark:hover:text-slate-200'
                                    }`}
                                >
                                    Raw JSON Payload
                                </button>
                            </div>

                            {/* Modal Body */}
                            <div className="max-h-[50vh] overflow-y-auto space-y-4 text-xs pr-1">
                                {activeTab === 'visual' ? (
                                    <div className="space-y-4">
                                        {/* If both old and new exist, show comparison */}
                                        {selectedLog.old_values && (
                                            <div className="space-y-1.5">
                                                <span className="font-semibold text-rose-700 dark:text-rose-400 block text-xs">
                                                    Previous Values (Before Change):
                                                </span>
                                                <div className="bg-rose-50/50 dark:bg-rose-950/20 border border-rose-200/60 dark:border-rose-900/40 rounded-xl p-3">
                                                    <pre className="font-mono text-[11px] text-rose-900 dark:text-rose-200 whitespace-pre-wrap overflow-x-auto">
                                                        {JSON.stringify(selectedLog.old_values, null, 2)}
                                                    </pre>
                                                </div>
                                            </div>
                                        )}

                                        <div className="space-y-1.5">
                                            <span className="font-semibold text-emerald-700 dark:text-emerald-400 block text-xs">
                                                New / Recorded State:
                                            </span>
                                            <div className="bg-emerald-50/50 dark:bg-emerald-950/20 border border-emerald-200/60 dark:border-emerald-900/40 rounded-xl p-3">
                                                <pre className="font-mono text-[11px] text-emerald-900 dark:text-emerald-200 whitespace-pre-wrap overflow-x-auto">
                                                    {selectedLog.new_values ? JSON.stringify(selectedLog.new_values, null, 2) : 'No values recorded'}
                                                </pre>
                                            </div>
                                        </div>

                                        {selectedLog.user_agent && (
                                            <div className="pt-2 text-[11px] text-slate-400 dark:text-slate-500">
                                                User Agent: <span className="font-mono text-slate-600 dark:text-slate-400">{selectedLog.user_agent}</span>
                                            </div>
                                        )}
                                    </div>
                                ) : (
                                    <div className="bg-slate-900 dark:bg-black/60 text-slate-100 p-4 rounded-2xl font-mono text-[11px] overflow-x-auto border border-slate-800">
                                        <pre>{JSON.stringify(selectedLog, null, 2)}</pre>
                                    </div>
                                )}
                            </div>

                            {/* Modal Footer */}
                            <div className="pt-3 border-t border-slate-100 dark:border-slate-800 flex justify-end">
                                <button
                                    type="button"
                                    onClick={() => setSelectedLog(null)}
                                    className="px-5 py-2 text-xs font-semibold rounded-xl bg-slate-900 dark:bg-rose-700 text-white hover:bg-slate-800 transition-colors cursor-pointer"
                                >
                                    Close Inspection
                                </button>
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </AdminLayout>
    );
}
