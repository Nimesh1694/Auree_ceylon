import React, { useEffect, useState } from 'react';

interface ReadRecord {
    id: number;
    read_at: string;
    agent: {
        id: number;
        name: string;
        email: string;
        role: string;
    };
}

interface AnalyticsData {
    eligible_agents_count: number;
    active_reads_count: number;
    read_percentage: number;
    total_all_time_reads: number;
}

interface Props {
    announcementId: number | null;
    announcementTitle: string;
    isOpen: boolean;
    onClose: () => void;
}

export default function ReadAnalyticsModal({ announcementId, announcementTitle, isOpen, onClose }: Props) {
    const [loading, setLoading] = useState(false);
    const [analytics, setAnalytics] = useState<AnalyticsData | null>(null);
    const [reads, setReads] = useState<ReadRecord[]>([]);
    const [page, setPage] = useState(1);
    const [totalPages, setTotalPages] = useState(1);

    useEffect(() => {
        if (!isOpen || !announcementId) return;

        setLoading(true);
        fetch(`/admin/agents/announcements/${announcementId}/reads?page=${page}`, {
            headers: {
                Accept: 'application/json',
            },
        })
            .then((res) => res.json())
            .then((data) => {
                setAnalytics(data.analytics);
                setReads(data.reads.data || []);
                setTotalPages(data.reads.last_page || 1);
            })
            .catch((err) => console.error('Failed to load read analytics', err))
            .finally(() => setLoading(false));
    }, [isOpen, announcementId, page]);

    if (!isOpen) return null;

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-xs p-4">
            <div className="bg-white dark:bg-stone-900 border border-stone-200 dark:border-stone-800 rounded-xl shadow-2xl max-w-3xl w-full max-h-[90vh] flex flex-col overflow-hidden">
                {/* Header */}
                <div className="px-6 py-4 border-b border-stone-200 dark:border-stone-800 flex items-center justify-between bg-stone-50 dark:bg-stone-800/50">
                    <div>
                        <h3 className="text-base font-semibold text-stone-900 dark:text-white">
                            Read Analytics & Engagement
                        </h3>
                        <p className="text-xs text-stone-500 dark:text-stone-400 truncate max-w-lg">
                            {announcementTitle}
                        </p>
                    </div>
                    <button
                        onClick={onClose}
                        className="text-stone-400 hover:text-stone-600 dark:hover:text-stone-200 text-sm font-semibold p-1"
                    >
                        ✕
                    </button>
                </div>

                {/* Content */}
                <div className="p-6 overflow-y-auto flex-1 space-y-6">
                    {loading && !analytics ? (
                        <div className="py-12 text-center text-sm text-stone-500">Loading analytics...</div>
                    ) : analytics ? (
                        <>
                            {/* Stats Cards */}
                            <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                <div className="p-4 rounded-lg bg-stone-50 dark:bg-stone-800/60 border border-stone-200 dark:border-stone-700">
                                    <span className="text-xs text-stone-500 uppercase tracking-wider font-medium">
                                        Active Eligible Agents
                                    </span>
                                    <div className="text-2xl font-bold text-stone-900 dark:text-white mt-1">
                                        {analytics.eligible_agents_count}
                                    </div>
                                    <span className="text-[11px] text-stone-400">Current target network</span>
                                </div>

                                <div className="p-4 rounded-lg bg-stone-50 dark:bg-stone-800/60 border border-stone-200 dark:border-stone-700">
                                    <span className="text-xs text-stone-500 uppercase tracking-wider font-medium">
                                        Active Agent Reads
                                    </span>
                                    <div className="text-2xl font-bold text-emerald-600 dark:text-emerald-400 mt-1">
                                        {analytics.active_reads_count}
                                    </div>
                                    <span className="text-[11px] text-stone-400">Unique active readers</span>
                                </div>

                                <div className="p-4 rounded-lg bg-stone-50 dark:bg-stone-800/60 border border-stone-200 dark:border-stone-700">
                                    <span className="text-xs text-stone-500 uppercase tracking-wider font-medium">
                                        Active Read Rate
                                    </span>
                                    <div className="text-2xl font-bold text-rose-600 dark:text-rose-400 mt-1">
                                        {analytics.read_percentage}%
                                    </div>
                                    <div className="w-full bg-stone-200 dark:bg-stone-700 rounded-full h-1.5 mt-2">
                                        <div
                                            className="bg-rose-600 h-1.5 rounded-full transition-all"
                                            style={{ width: `${Math.min(analytics.read_percentage, 100)}%` }}
                                        />
                                    </div>
                                </div>
                            </div>

                            {analytics.total_all_time_reads > analytics.active_reads_count && (
                                <p className="text-xs text-stone-500 dark:text-stone-400 italic">
                                    * Note: {analytics.total_all_time_reads} total historical read receipts recorded (includes {analytics.total_all_time_reads - analytics.active_reads_count} former/deactivated agent accounts).
                                </p>
                            )}

                            {/* Recipient Table */}
                            <div>
                                <h4 className="text-sm font-semibold text-stone-900 dark:text-white mb-3">
                                    Acknowledged Recipients ({reads.length})
                                </h4>
                                {reads.length === 0 ? (
                                    <p className="text-xs text-stone-400 italic py-4">No agents have acknowledged this announcement yet.</p>
                                ) : (
                                    <div className="border border-stone-200 dark:border-stone-700 rounded-lg overflow-hidden">
                                        <table className="w-full text-xs text-left">
                                            <thead className="bg-stone-100 dark:bg-stone-800 text-stone-600 dark:text-stone-300 font-semibold uppercase">
                                                <tr>
                                                    <th className="px-4 py-2.5">Agent</th>
                                                    <th className="px-4 py-2.5">Role</th>
                                                    <th className="px-4 py-2.5">Read Timestamp</th>
                                                </tr>
                                            </thead>
                                            <tbody className="divide-y divide-stone-200 dark:divide-stone-700">
                                                {reads.map((r) => (
                                                    <tr key={r.id} className="hover:bg-stone-50 dark:hover:bg-stone-800/50">
                                                        <td className="px-4 py-2.5">
                                                            <div className="font-medium text-stone-900 dark:text-white">
                                                                {r.agent.name}
                                                            </div>
                                                            <div className="text-[11px] text-stone-400">
                                                                {r.agent.email}
                                                            </div>
                                                        </td>
                                                        <td className="px-4 py-2.5">
                                                            <span className="px-2 py-0.5 rounded text-[10px] font-mono uppercase bg-stone-200 dark:bg-stone-700 text-stone-700 dark:text-stone-300">
                                                                {r.agent.role === 'high_level_agent' ? 'HLA' : 'LLA'}
                                                            </span>
                                                        </td>
                                                        <td className="px-4 py-2.5 text-stone-600 dark:text-stone-300">
                                                            {new Date(r.read_at).toLocaleString()}
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                )}

                                {/* Pagination */}
                                {totalPages > 1 && (
                                    <div className="flex justify-between items-center pt-3 text-xs">
                                        <button
                                            disabled={page <= 1}
                                            onClick={() => setPage((p) => Math.max(p - 1, 1))}
                                            className="px-3 py-1 border rounded disabled:opacity-50"
                                        >
                                            Previous
                                        </button>
                                        <span className="text-stone-500">Page {page} of {totalPages}</span>
                                        <button
                                            disabled={page >= totalPages}
                                            onClick={() => setPage((p) => Math.min(p + 1, totalPages))}
                                            className="px-3 py-1 border rounded disabled:opacity-50"
                                        >
                                            Next
                                        </button>
                                    </div>
                                )}
                            </div>
                        </>
                    ) : null}
                </div>

                {/* Footer */}
                <div className="px-6 py-3 border-t border-stone-200 dark:border-stone-800 bg-stone-50 dark:bg-stone-800/50 flex justify-end">
                    <button
                        onClick={onClose}
                        className="px-4 py-1.5 text-xs font-semibold rounded bg-stone-200 dark:bg-stone-700 text-stone-800 dark:text-stone-200 hover:bg-stone-300 dark:hover:bg-stone-600"
                    >
                        Close
                    </button>
                </div>
            </div>
        </div>
    );
}

