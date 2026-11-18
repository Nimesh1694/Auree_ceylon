import React, { useState } from 'react';
import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link, router } from '@inertiajs/react';
import ReadAnalyticsModal from './ReadAnalyticsModal';

interface Actor {
    id: number;
    name: string;
    email: string;
}

interface Analytics {
    eligible_agents_count: number;
    active_reads_count: number;
    read_percentage: number;
    total_all_time_reads: number;
}

interface Announcement {
    id: number;
    title_en: string;
    title_si: string | null;
    target_audience: 'all' | 'high_level_agent' | 'low_level_agent';
    priority: 'normal' | 'important' | 'urgent';
    status: 'draft' | 'scheduled' | 'published' | 'archived';
    archived_reason: 'manual' | 'system_expiry' | null;
    is_pinned: boolean;
    pin_order: number;
    scheduled_at: string | null;
    published_at: string | null;
    expires_at: string | null;
    created_at: string;
    creator: Actor;
    scheduler: Actor | null;
    publisher: Actor | null;
    archiver: Actor | null;
    analytics?: Analytics;
}

interface Props {
    announcements: {
        data: Announcement[];
        links: any[];
        current_page: number;
        last_page: number;
        total: number;
    };
    currentTab: string;
    counts: {
        published: number;
        scheduled: number;
        draft: number;
        archived: number;
    };
    filters: {
        tab: string;
        audience: string;
        search: string;
    };
}

export default function Index({ announcements, currentTab, counts, filters }: Props) {
    const [selectedAnnouncement, setSelectedAnnouncement] = useState<Announcement | null>(null);
    const [scheduleModalOpen, setScheduleModalOpen] = useState(false);
    const [schedulingAnnouncement, setSchedulingAnnouncement] = useState<Announcement | null>(null);
    const [scheduledAtInput, setScheduledAtInput] = useState('');
    const [search, setSearch] = useState(filters.search || '');
    const [audience, setAudience] = useState(filters.audience || 'all_audiences');

    const handleFilter = (tab = currentTab, aud = audience, q = search) => {
        router.get(
            '/admin/agents/announcements',
            { tab, audience: aud, search: q },
            { preserveState: true, replace: true }
        );
    };

    const handlePublishNow = (announcement: Announcement) => {
        if (confirm(`Publish "${announcement.title_en}" immediately to active agents?`)) {
            router.post(`/admin/agents/announcements/${announcement.id}/publish`);
        }
    };

    const handleCancelSchedule = (announcement: Announcement) => {
        if (confirm(`Cancel scheduled broadcast for "${announcement.title_en}"? It will return to draft.`)) {
            router.post(`/admin/agents/announcements/${announcement.id}/cancel-schedule`);
        }
    };

    const handleArchive = (announcement: Announcement) => {
        if (confirm(`Archive "${announcement.title_en}"? It will be removed from agent feeds.`)) {
            router.post(`/admin/agents/announcements/${announcement.id}/archive`);
        }
    };

    const handleDeleteDraft = (announcement: Announcement) => {
        if (confirm(`Permanently delete draft "${announcement.title_en}"?`)) {
            router.delete(`/admin/agents/announcements/${announcement.id}`);
        }
    };

    const submitSchedule = (e: React.FormEvent) => {
        e.preventDefault();
        if (!schedulingAnnouncement || !scheduledAtInput) return;

        router.post(
            `/admin/agents/announcements/${schedulingAnnouncement.id}/schedule`,
            { scheduled_at: scheduledAtInput },
            {
                onSuccess: () => {
                    setScheduleModalOpen(false);
                    setSchedulingAnnouncement(null);
                    setScheduledAtInput('');
                },
            }
        );
    };

    return (
        <AdminLayout>
            <Head title="Agent Announcements Broadcasts" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                    <div>
                        <h1 className="text-xl font-serif font-bold text-stone-900 dark:text-white">
                            Agent Broadcast Announcements
                        </h1>
                        <p className="text-xs text-stone-500 dark:text-stone-400">
                            Broadcast targets, updates, and guidelines directly to High-Level and Low-Level Agents.
                        </p>
                    </div>
                    <Link
                        href="/admin/agents/announcements/create"
                        className="px-4 py-2 bg-rose-600 hover:bg-rose-500 text-white rounded-lg text-xs font-semibold shadow-xs flex items-center gap-1.5 transition-colors"
                    >
                        <span>+ Create Announcement</span>
                    </Link>
                </div>

                {/* Tabs */}
                <div className="border-b border-stone-200 dark:border-stone-800 flex gap-6 text-xs font-medium">
                    {[
                        { key: 'published', label: 'Published', count: counts.published },
                        { key: 'scheduled', label: 'Scheduled', count: counts.scheduled },
                        { key: 'draft', label: 'Drafts', count: counts.draft },
                        { key: 'archived', label: 'Archived', count: counts.archived },
                    ].map((t) => (
                        <button
                            key={t.key}
                            onClick={() => handleFilter(t.key)}
                            className={`pb-3 border-b-2 flex items-center gap-1.5 cursor-pointer transition-colors ${
                                currentTab === t.key
                                    ? 'border-rose-600 text-rose-600 dark:text-rose-400 font-bold'
                                    : 'border-transparent text-stone-500 hover:text-stone-800 dark:hover:text-stone-200'
                            }`}
                        >
                            <span>{t.label}</span>
                            <span className="px-1.5 py-0.5 rounded-full text-[10px] bg-stone-100 dark:bg-stone-800 text-stone-600 dark:text-stone-300">
                                {t.count}
                            </span>
                        </button>
                    ))}
                </div>

                {/* Filters */}
                <div className="flex flex-col sm:flex-row items-center justify-between gap-3 bg-white dark:bg-stone-900 p-3 rounded-lg border border-stone-200 dark:border-stone-800 text-xs">
                    <div className="flex items-center gap-3 w-full sm:w-auto">
                        <input
                            type="text"
                            placeholder="Search title or content..."
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            onKeyDown={(e) => e.key === 'Enter' && handleFilter(currentTab, audience, search)}
                            className="px-3 py-1.5 border border-stone-300 dark:border-stone-700 rounded-md bg-stone-50 dark:bg-stone-800 text-stone-900 dark:text-white w-full sm:w-64"
                        />
                        <button
                            onClick={() => handleFilter(currentTab, audience, search)}
                            className="px-3 py-1.5 bg-stone-200 dark:bg-stone-700 hover:bg-stone-300 rounded-md font-medium text-stone-800 dark:text-stone-200"
                        >
                            Search
                        </button>
                    </div>

                    <div className="flex items-center gap-2 w-full sm:w-auto justify-end">
                        <label className="text-stone-500">Audience:</label>
                        <select
                            value={audience}
                            onChange={(e) => {
                                setAudience(e.target.value);
                                handleFilter(currentTab, e.target.value, search);
                            }}
                            className="px-3 py-1.5 border border-stone-300 dark:border-stone-700 rounded-md bg-stone-50 dark:bg-stone-800 text-stone-900 dark:text-white"
                        >
                            <option value="all_audiences">All Audiences</option>
                            <option value="all">Both HLA & LLA</option>
                            <option value="high_level_agent">High-Level Only</option>
                            <option value="low_level_agent">Low-Level Only</option>
                        </select>
                    </div>
                </div>

                {/* Table */}
                <div className="bg-white dark:bg-stone-900 border border-stone-200 dark:border-stone-800 rounded-xl overflow-hidden shadow-xs">
                    {announcements.data.length === 0 ? (
                        <div className="py-12 text-center text-sm text-stone-500 dark:text-stone-400">
                            No announcements found in {currentTab}.
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-xs text-left">
                                <thead className="bg-stone-50 dark:bg-stone-800 text-stone-600 dark:text-stone-300 uppercase tracking-wider font-semibold border-b border-stone-200 dark:border-stone-700">
                                    <tr>
                                        <th className="px-4 py-3">Title & Priority</th>
                                        <th className="px-4 py-3">Target Audience</th>
                                        <th className="px-4 py-3">Lifecycle & Timing</th>
                                        <th className="px-4 py-3">Attribution</th>
                                        {(currentTab === 'published' || currentTab === 'archived') && (
                                            <th className="px-4 py-3">Read Engagement</th>
                                        )}
                                        <th className="px-4 py-3 text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-stone-200 dark:divide-stone-800">
                                    {announcements.data.map((item) => (
                                        <tr key={item.id} className="hover:bg-stone-50/50 dark:hover:bg-stone-800/50">
                                            {/* Title & Priority */}
                                            <td className="px-4 py-3.5">
                                                <div className="flex items-center gap-2">
                                                    {item.is_pinned && (
                                                        <span className="text-amber-500 font-bold" title={`Pinned (Order: ${item.pin_order})`}>
                                                            📌
                                                        </span>
                                                    )}
                                                    <span className="font-semibold text-stone-900 dark:text-white">
                                                        {item.title_en}
                                                    </span>
                                                    {item.title_si && (
                                                        <span className="text-[10px] px-1 py-0.2 rounded bg-stone-100 dark:bg-stone-800 text-stone-500 border border-stone-200 dark:border-stone-700">
                                                            SI
                                                        </span>
                                                    )}
                                                </div>
                                                <div className="mt-1 flex items-center gap-1.5">
                                                    <span
                                                        className={`px-2 py-0.5 rounded text-[10px] font-semibold uppercase ${
                                                            item.priority === 'urgent'
                                                                ? 'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300 border border-rose-200 dark:border-rose-800'
                                                                : item.priority === 'important'
                                                                ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300 border border-amber-200 dark:border-amber-800'
                                                                : 'bg-stone-100 text-stone-600 dark:bg-stone-800 dark:text-stone-300'
                                                        }`}
                                                    >
                                                        {item.priority}
                                                    </span>
                                                </div>
                                            </td>

                                            {/* Target Audience */}
                                            <td className="px-4 py-3.5">
                                                <span className="px-2 py-0.5 rounded text-[10px] font-mono uppercase bg-stone-100 dark:bg-stone-800 text-stone-700 dark:text-stone-300 border border-stone-200 dark:border-stone-700">
                                                    {item.target_audience === 'all'
                                                        ? 'All Agents'
                                                        : item.target_audience === 'high_level_agent'
                                                        ? 'HLA Only'
                                                        : 'LLA Only'}
                                                </span>
                                            </td>

                                            {/* Timing */}
                                            <td className="px-4 py-3.5 space-y-0.5 text-stone-600 dark:text-stone-400">
                                                {item.published_at && (
                                                    <div>Published: {new Date(item.published_at).toLocaleString()}</div>
                                                )}
                                                {item.scheduled_at && (
                                                    <div className="text-amber-600 dark:text-amber-400 font-medium">
                                                        Scheduled: {new Date(item.scheduled_at).toLocaleString()}
                                                    </div>
                                                )}
                                                {item.expires_at && (
                                                    <div className="text-rose-500 text-[11px]">
                                                        Expires: {new Date(item.expires_at).toLocaleString()}
                                                    </div>
                                                )}
                                                {!item.published_at && !item.scheduled_at && (
                                                    <div>Created: {new Date(item.created_at).toLocaleDateString()}</div>
                                                )}
                                            </td>

                                            {/* Attribution */}
                                            <td className="px-4 py-3.5 space-y-0.5 text-[11px] text-stone-500 dark:text-stone-400">
                                                <div>Author: <strong className="text-stone-700 dark:text-stone-300">{item.creator?.name}</strong></div>
                                                {item.scheduler && (
                                                    <div>Scheduled by: <strong className="text-stone-700 dark:text-stone-300">{item.scheduler.name}</strong></div>
                                                )}
                                                {item.publisher && (
                                                    <div>Published by: <strong className="text-stone-700 dark:text-stone-300">{item.publisher.name}</strong></div>
                                                )}
                                                {!item.publisher && item.status === 'published' && (
                                                    <div>Published by: <em className="text-stone-400">Automated Scheduler</em></div>
                                                )}
                                                {item.status === 'archived' && (
                                                    <div>
                                                        Archived: <span className="font-semibold text-stone-600 dark:text-stone-300">
                                                            {item.archived_reason === 'system_expiry' ? 'System (Expired)' : item.archiver?.name || 'Manual'}
                                                        </span>
                                                    </div>
                                                )}
                                            </td>

                                            {/* Read Engagement */}
                                            {(currentTab === 'published' || currentTab === 'archived') && (
                                                <td className="px-4 py-3.5">
                                                    {item.analytics ? (
                                                        <div className="space-y-1">
                                                            <div className="flex items-center justify-between text-[11px]">
                                                                <span className="font-semibold text-stone-800 dark:text-stone-200">
                                                                    {item.analytics.read_percentage}%
                                                                </span>
                                                                <span className="text-stone-400">
                                                                    {item.analytics.active_reads_count} / {item.analytics.eligible_agents_count}
                                                                </span>
                                                            </div>
                                                            <div className="w-24 bg-stone-200 dark:bg-stone-700 h-1.5 rounded-full overflow-hidden">
                                                                <div
                                                                    className="bg-emerald-500 h-1.5 rounded-full"
                                                                    style={{ width: `${Math.min(item.analytics.read_percentage, 100)}%` }}
                                                                />
                                                            </div>
                                                            <button
                                                                onClick={() => setSelectedAnnouncement(item)}
                                                                className="text-[11px] text-rose-600 dark:text-rose-400 hover:underline cursor-pointer block"
                                                            >
                                                                View Recipients ↗
                                                            </button>
                                                        </div>
                                                    ) : (
                                                        <span className="text-stone-400">—</span>
                                                    )}
                                                </td>
                                            )}

                                            {/* Actions */}
                                            <td className="px-4 py-3.5 text-right space-x-2">
                                                {item.status === 'draft' && (
                                                    <>
                                                        <Link
                                                            href={`/admin/agents/announcements/${item.id}/edit`}
                                                            className="text-stone-600 dark:text-stone-300 hover:text-stone-900 font-medium"
                                                        >
                                                            Edit
                                                        </Link>
                                                        <button
                                                            onClick={() => handlePublishNow(item)}
                                                            className="text-emerald-600 dark:text-emerald-400 hover:underline font-semibold"
                                                        >
                                                            Publish
                                                        </button>
                                                        <button
                                                            onClick={() => {
                                                                setSchedulingAnnouncement(item);
                                                                setScheduleModalOpen(true);
                                                            }}
                                                            className="text-amber-600 dark:text-amber-400 hover:underline font-semibold"
                                                        >
                                                            Schedule
                                                        </button>
                                                        <button
                                                            onClick={() => handleDeleteDraft(item)}
                                                            className="text-rose-600 dark:text-rose-400 hover:underline"
                                                        >
                                                            Delete
                                                        </button>
                                                    </>
                                                )}

                                                {item.status === 'scheduled' && (
                                                    <>
                                                        <Link
                                                            href={`/admin/agents/announcements/${item.id}/edit`}
                                                            className="text-stone-600 dark:text-stone-300 hover:text-stone-900 font-medium"
                                                        >
                                                            Edit
                                                        </Link>
                                                        <button
                                                            onClick={() => handlePublishNow(item)}
                                                            className="text-emerald-600 dark:text-emerald-400 hover:underline font-semibold"
                                                        >
                                                            Publish Now
                                                        </button>
                                                        <button
                                                            onClick={() => handleCancelSchedule(item)}
                                                            className="text-amber-600 dark:text-amber-400 hover:underline font-semibold"
                                                        >
                                                            Cancel Schedule
                                                        </button>
                                                    </>
                                                )}

                                                {item.status === 'published' && (
                                                    <>
                                                        <Link
                                                            href={`/admin/agents/announcements/${item.id}/edit`}
                                                            className="text-stone-600 dark:text-stone-300 hover:text-stone-900 font-medium"
                                                        >
                                                            Edit
                                                        </Link>
                                                        <button
                                                            onClick={() => handleArchive(item)}
                                                            className="text-rose-600 dark:text-rose-400 hover:underline font-semibold"
                                                        >
                                                            Archive
                                                        </button>
                                                    </>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>

                {/* Schedule Modal */}
                {scheduleModalOpen && (
                    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4">
                        <div className="bg-white dark:bg-stone-900 border border-stone-200 dark:border-stone-800 rounded-xl p-6 max-w-md w-full shadow-2xl">
                            <h3 className="text-base font-semibold text-stone-900 dark:text-white mb-2">
                                Schedule Publication
                            </h3>
                            <p className="text-xs text-stone-500 mb-4">
                                Select date and time to automatically broadcast &ldquo;{schedulingAnnouncement?.title_en}&rdquo;.
                            </p>
                            <form onSubmit={submitSchedule} className="space-y-4">
                                <div>
                                    <label className="block text-xs font-medium text-stone-700 dark:text-stone-300 mb-1">
                                        Publication Date & Time (Asia/Colombo)
                                    </label>
                                    <input
                                        type="datetime-local"
                                        required
                                        value={scheduledAtInput}
                                        onChange={(e) => setScheduledAtInput(e.target.value)}
                                        className="w-full text-xs px-3 py-2 border rounded-md bg-stone-50 dark:bg-stone-800 dark:border-stone-700 text-stone-900 dark:text-white"
                                    />
                                </div>
                                <div className="flex justify-end gap-2 pt-2">
                                    <button
                                        type="button"
                                        onClick={() => setScheduleModalOpen(false)}
                                        className="px-3 py-1.5 border rounded text-xs text-stone-600 dark:text-stone-300"
                                    >
                                        Cancel
                                    </button>
                                    <button
                                        type="submit"
                                        className="px-4 py-1.5 bg-amber-600 hover:bg-amber-500 text-white rounded text-xs font-semibold"
                                    >
                                        Confirm Schedule
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                )}

                {/* Read Analytics Modal */}
                {selectedAnnouncement && (
                    <ReadAnalyticsModal
                        announcementId={selectedAnnouncement.id}
                        announcementTitle={selectedAnnouncement.title_en}
                        isOpen={Boolean(selectedAnnouncement)}
                        onClose={() => setSelectedAnnouncement(null)}
                    />
                )}
            </div>
        </AdminLayout>
    );
}

