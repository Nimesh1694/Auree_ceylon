import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import * as Icons from '@/Components/Icons';
import { PageProps } from '@/types';

export interface AuditLogItem {
    id: number;
    user_id: number | null;
    action: string;
    auditable_type: string | null;
    auditable_id: number | null;
    old_values: Record<string, unknown> | null;
    new_values: Record<string, unknown> | null;
    ip_address: string | null;
    created_at: string;
    user?: {
        id: number;
        name: string;
        email: string;
        role: string;
    } | null;
}

export interface PaginatedLogs {
    data: AuditLogItem[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
    links: {
        url: string | null;
        label: string;
        active: boolean;
    }[];
}

interface Props extends PageProps {
    logs: PaginatedLogs;
    filters: {
        action: string;
        search: string;
        date_from: string;
        date_to: string;
    };
    availableActions: string[];
}

export default function Activity({ logs, filters, availableActions }: Props) {
    const [action, setAction] = useState(filters.action || '');
    const [search, setSearch] = useState(filters.search || '');
    const [dateFrom, setDateFrom] = useState(filters.date_from || '');
    const [dateTo, setDateTo] = useState(filters.date_to || '');

    const handleFilterSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(
            '/admin/agents/activity',
            { action, search, date_from: dateFrom, date_to: dateTo },
            { preserveState: true, preserveScroll: true }
        );
    };

    const handleReset = () => {
        setAction('');
        setSearch('');
        setDateFrom('');
        setDateTo('');
        router.get('/admin/agents/activity');
    };

    const getActionBadge = (act: string) => {
        if (act.includes('approved') || act.includes('verified')) {
            return 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 border-emerald-500/30';
        }
        if (act.includes('rejected') || act.includes('revoked') || act.includes('suspended')) {
            return 'bg-rose-500/10 text-rose-700 dark:text-rose-300 border-rose-500/30';
        }
        if (act.includes('requested') || act.includes('move')) {
            return 'bg-amber-500/10 text-amber-700 dark:text-amber-300 border-amber-500/30';
        }
        if (act.includes('registered') || act.includes('created')) {
            return 'bg-blue-500/10 text-blue-700 dark:text-blue-300 border-blue-500/30';
        }
        return 'bg-slate-500/10 text-slate-700 dark:text-slate-300 border-slate-500/30';
    };

    const formatActionName = (act: string) => {
        return act.replace(/_/g, ' ').toUpperCase();
    };

    return (
        <AdminLayout>
            <Head title="Activity Audit Trail - Agent Network" />

            <div className="space-y-6">
                {/* Header Banner */}
                <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-gradient-to-r from-slate-900 via-slate-800 to-indigo-950 p-6 rounded-2xl border border-slate-700 shadow-xl text-white">
                    <div className="space-y-1">
                        <div className="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-amber-400">
                            <Icons.History className="w-4 h-4" />
                            <span>Operational Governance &amp; Audit Trail</span>
                        </div>
                        <h1 className="text-2xl font-bold tracking-tight text-white flex items-center gap-3">
                            Agent Activity Audit Trail
                            <span className="text-xs px-2.5 py-0.5 rounded-full border border-amber-400/40 bg-amber-500/10 text-amber-300 font-medium">
                                Immutable Logs
                            </span>
                        </h1>
                        <p className="text-sm text-slate-300 max-w-2xl">
                            Consolidated chronological timeline of agent network lifecycle events, status transitions, KYC evaluations, invitations, and hierarchy modifications.
                        </p>
                    </div>

                    <div className="flex items-center gap-3 shrink-0">
                        <Link
                            href="/admin/agents/reports"
                            className="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-xl border border-slate-600 bg-slate-800/80 hover:bg-slate-700 text-slate-200 transition-colors shadow-sm"
                        >
                            <Icons.Download className="w-4 h-4 text-slate-300" />
                            <span>Export Center</span>
                        </Link>
                    </div>
                </div>

                {/* Filter Bar */}
                <form
                    onSubmit={handleFilterSubmit}
                    className="bg-white dark:bg-slate-900 p-4 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4"
                >
                    <div className="flex flex-wrap items-center gap-3">
                        <select
                            value={action}
                            onChange={(e) => setAction(e.target.value)}
                            className="text-xs bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg px-2.5 py-1.5 text-slate-900 dark:text-white focus:outline-none focus:ring-1 focus:ring-amber-500"
                        >
                            <option value="">All Action Types</option>
                            {availableActions.map((act) => (
                                <option key={act} value={act}>
                                    {formatActionName(act)}
                                </option>
                            ))}
                        </select>

                        <div className="flex items-center gap-1.5 text-xs text-slate-500">
                            <span>From:</span>
                            <input
                                type="date"
                                value={dateFrom}
                                onChange={(e) => setDateFrom(e.target.value)}
                                className="text-xs bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg px-2 py-1 text-slate-900 dark:text-white"
                            />
                            <span>To:</span>
                            <input
                                type="date"
                                value={dateTo}
                                onChange={(e) => setDateTo(e.target.value)}
                                className="text-xs bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg px-2 py-1 text-slate-900 dark:text-white"
                            />
                        </div>
                    </div>

                    <div className="flex items-center gap-2">
                        <div className="relative">
                            <Icons.Search className="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
                            <input
                                type="text"
                                placeholder="Search by user or action..."
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                className="w-full md:w-56 pl-9 pr-3 py-1.5 text-xs bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-1 focus:ring-amber-500"
                            />
                        </div>

                        <button
                            type="submit"
                            className="px-3 py-1.5 text-xs font-semibold rounded-lg bg-slate-900 text-white dark:bg-white dark:text-slate-900"
                        >
                            Filter
                        </button>

                        {(action || search || dateFrom || dateTo) && (
                            <button
                                type="button"
                                onClick={handleReset}
                                className="px-2.5 py-1.5 text-xs text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200"
                            >
                                Reset
                            </button>
                        )}
                    </div>
                </form>

                {/* Activity Table */}
                <div className="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
                    {logs.data.length === 0 ? (
                        <div className="p-12 text-center space-y-3">
                            <Icons.History className="w-12 h-12 text-slate-300 dark:text-slate-600 mx-auto" />
                            <h3 className="text-base font-bold text-slate-900 dark:text-white">
                                No Activity Logs Found
                            </h3>
                            <p className="text-xs text-slate-500 dark:text-slate-400 max-w-sm mx-auto">
                                No agent lifecycle events match your active filters.
                            </p>
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-left border-collapse">
                                <thead>
                                    <tr className="border-b border-slate-200 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/40 text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                        <th className="py-3 px-4 w-40">Timestamp</th>
                                        <th className="py-3 px-4 w-44">Action Event</th>
                                        <th className="py-3 px-4">Initiator / Actor</th>
                                        <th className="py-3 px-4">Payload &amp; Context Details</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100 dark:divide-slate-800/60 text-xs text-slate-700 dark:text-slate-300 font-normal">
                                    {logs.data.map((item) => (
                                        <tr
                                            key={item.id}
                                            className="hover:bg-slate-50/80 dark:hover:bg-slate-800/30 transition-colors"
                                        >
                                            <td className="py-3.5 px-4 whitespace-nowrap text-slate-500 font-mono text-[11px] align-top">
                                                <div>{new Date(item.created_at).toLocaleDateString()}</div>
                                                <div className="text-[10px] text-slate-400">
                                                    {new Date(item.created_at).toLocaleTimeString()}
                                                </div>
                                            </td>

                                            <td className="py-3.5 px-4 align-top">
                                                <span
                                                    className={`inline-block px-2 py-0.5 rounded-full text-[10px] font-bold border tracking-wider uppercase ${getActionBadge(
                                                        item.action
                                                    )}`}
                                                >
                                                    {item.action}
                                                </span>
                                            </td>

                                            <td className="py-3.5 px-4 align-top">
                                                {item.user ? (
                                                    <div>
                                                        <div className="font-semibold text-slate-900 dark:text-white">
                                                            {item.user.name}
                                                        </div>
                                                        <div className="text-[11px] text-slate-400 font-mono">
                                                            {item.user.email} &bull; <span className="uppercase">{item.user.role}</span>
                                                        </div>
                                                    </div>
                                                ) : (
                                                    <span className="text-slate-400 italic">System Automation</span>
                                                )}
                                                {item.ip_address && (
                                                    <div className="text-[10px] text-slate-400 font-mono mt-0.5">
                                                        IP: {item.ip_address}
                                                    </div>
                                                )}
                                            </td>

                                            <td className="py-3.5 px-4 align-top">
                                                <div className="bg-slate-50 dark:bg-slate-950/60 p-2 rounded-lg border border-slate-100 dark:border-slate-800/60 font-mono text-[11px] max-w-xl overflow-x-auto">
                                                    {item.new_values && Object.keys(item.new_values).length > 0 ? (
                                                        <pre className="text-slate-700 dark:text-slate-300 whitespace-pre-wrap">
                                                            {JSON.stringify(item.new_values, null, 2)}
                                                        </pre>
                                                    ) : (
                                                        <span className="text-slate-400 italic">No metadata payload</span>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}

                    {/* Pagination */}
                    {logs.total > logs.data.length && (
                        <div className="p-4 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between text-xs text-slate-500">
                            <div>
                                Showing {logs.from} to {logs.to} of {logs.total} events
                            </div>
                            <div className="flex items-center gap-1">
                                {logs.links.map((link, idx) => (
                                    <Link
                                        key={idx}
                                        href={link.url || '#'}
                                        preserveScroll
                                        className={`px-2.5 py-1 rounded border text-xs ${
                                            link.active
                                                ? 'bg-slate-900 text-white border-slate-900 dark:bg-white dark:text-slate-900 font-bold'
                                                : link.url
                                                ? 'hover:bg-slate-100 dark:hover:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300'
                                                : 'opacity-40 cursor-not-allowed border-transparent'
                                        }`}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                    />
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </AdminLayout>
    );
}

