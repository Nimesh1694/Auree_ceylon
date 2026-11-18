import React, { useState } from 'react';
import AgentLayout from '@/Layouts/AgentLayout';
import { Head, router } from '@inertiajs/react';
import UrgentAnnouncementModal, { UrgentAnnouncement } from '@/Components/Agent/UrgentAnnouncementModal';

interface Announcement {
    id: number;
    title_en: string;
    title_si: string | null;
    localized_title: string;
    content_en: string;
    content_si: string | null;
    localized_content: string;
    priority: 'normal' | 'important' | 'urgent';
    is_pinned: boolean;
    pin_order: number;
    published_at: string;
    expires_at: string | null;
    is_read: boolean;
    read_at: string | null;
}

interface Props {
    announcements: {
        data: Announcement[];
        links: any[];
        current_page: number;
        last_page: number;
        total: number;
    };
    unreadCount: number;
    urgentAnnouncements: UrgentAnnouncement[];
}

export default function Index({ announcements, unreadCount: initialUnreadCount, urgentAnnouncements: initialUrgent }: Props) {
    const [items, setItems] = useState<Announcement[]>(announcements.data);
    const [unreadCount, setUnreadCount] = useState(initialUnreadCount);
    const [urgentList, setUrgentList] = useState<UrgentAnnouncement[]>(initialUrgent);
    const [markingReadId, setMarkingReadId] = useState<number | null>(null);

    const handleMarkRead = async (announcementId: number) => {
        setMarkingReadId(announcementId);
        try {
            const res = await fetch(`/agent/announcements/${announcementId}/read`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
                },
            });

            if (res.ok) {
                const data = await res.json();
                setItems((prev) =>
                    prev.map((item) =>
                        item.id === announcementId
                            ? { ...item, is_read: true, read_at: new Date().toISOString() }
                            : item
                    )
                );
                setUnreadCount(data.unread_count ?? Math.max(unreadCount - 1, 0));
            }
        } catch (err) {
            console.error('Failed to mark announcement as read', err);
        } finally {
            setMarkingReadId(null);
        }
    };

    const handleUrgentAcknowledged = (announcementId: number, newUnreadCount: number) => {
        setItems((prev) =>
            prev.map((item) =>
                item.id === announcementId
                    ? { ...item, is_read: true, read_at: new Date().toISOString() }
                    : item
            )
        );
        setUnreadCount(newUnreadCount);
        setUrgentList((prev) => prev.filter((u) => u.id !== announcementId));
    };

    return (
        <AgentLayout>
            <Head title="Company Announcements" />

            {/* Anti-Fatigue Urgent Modal */}
            <UrgentAnnouncementModal
                announcements={urgentList}
                onAcknowledged={handleUrgentAcknowledged}
            />

            <div className="max-w-5xl mx-auto space-y-6">
                {/* Header */}
                <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 border-b border-stone-200 pb-4">
                    <div>
                        <h1 className="text-xl font-serif font-bold text-stone-900 flex items-center gap-2">
                            <span>📢 Announcements & Official Updates</span>
                            {unreadCount > 0 && (
                                <span className="px-2 py-0.5 rounded-full bg-rose-600 text-white text-xs font-mono font-bold">
                                    {unreadCount} unread
                                </span>
                            )}
                        </h1>
                        <p className="text-xs text-stone-500 mt-0.5">
                            Official operational guidelines, achievement notices, targets, and executive updates.
                        </p>
                    </div>
                </div>

                {/* Announcement Cards Feed */}
                {items.length === 0 ? (
                    <div className="bg-white rounded-xl border border-stone-200 p-12 text-center text-stone-500 text-sm">
                        <span className="text-3xl block mb-2">📭</span>
                        No active announcements at this time. Check back later for official broadcasts.
                    </div>
                ) : (
                    <div className="space-y-4">
                        {items.map((announcement) => (
                            <div
                                key={announcement.id}
                                className={`rounded-xl border transition-all overflow-hidden ${
                                    announcement.is_pinned
                                        ? 'border-amber-300 bg-amber-50/20 shadow-xs ring-1 ring-amber-200'
                                        : !announcement.is_read
                                        ? 'border-rose-300 bg-white shadow-xs'
                                        : 'border-stone-200 bg-white/70'
                                }`}
                            >
                                {/* Card Header */}
                                <div className="p-5 pb-3 flex items-start justify-between gap-4">
                                    <div className="space-y-1.5 flex-1">
                                        <div className="flex flex-wrap items-center gap-2">
                                            {announcement.is_pinned && (
                                                <span className="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800 border border-amber-300 flex items-center gap-1">
                                                    📌 Pinned
                                                </span>
                                            )}

                                            <span
                                                className={`px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider ${
                                                    announcement.priority === 'urgent'
                                                        ? 'bg-rose-100 text-rose-700 border border-rose-300'
                                                        : announcement.priority === 'important'
                                                        ? 'bg-amber-100 text-amber-800 border border-amber-300'
                                                        : 'bg-stone-100 text-stone-600 border border-stone-200'
                                                }`}
                                            >
                                                {announcement.priority}
                                            </span>

                                            {!announcement.is_read && (
                                                <span className="flex items-center gap-1 text-[11px] font-bold text-rose-600">
                                                    <span className="w-2 h-2 rounded-full bg-rose-600 animate-pulse" />
                                                    Unread
                                                </span>
                                            )}
                                        </div>

                                        <h2 className="text-base font-bold text-stone-900 leading-snug">
                                            {announcement.localized_title}
                                        </h2>
                                    </div>

                                    {/* Action button: Mark Read */}
                                    <div>
                                        {!announcement.is_read ? (
                                            <button
                                                onClick={() => handleMarkRead(announcement.id)}
                                                disabled={markingReadId === announcement.id}
                                                className="px-3 py-1.5 bg-rose-600 hover:bg-rose-500 disabled:opacity-50 text-white rounded-lg text-xs font-semibold shadow-xs transition-colors cursor-pointer whitespace-nowrap"
                                            >
                                                {markingReadId === announcement.id ? 'Marking...' : 'Mark as Read'}
                                            </button>
                                        ) : (
                                            <span className="text-[11px] text-stone-400 font-medium whitespace-nowrap">
                                                ✓ Read {announcement.read_at && new Date(announcement.read_at).toLocaleDateString()}
                                            </span>
                                        )}
                                    </div>
                                </div>

                                {/* Content Body — Safe text with whitespace-pre-line to prevent XSS */}
                                <div className="px-5 py-3 text-stone-700 text-xs sm:text-sm leading-relaxed whitespace-pre-line break-words border-t border-stone-100">
                                    {announcement.localized_content}
                                </div>

                                {/* Card Footer */}
                                <div className="px-5 py-2.5 bg-stone-50/80 border-t border-stone-100 text-[11px] text-stone-400 flex flex-wrap items-center justify-between gap-2">
                                    <span>
                                        Published: {new Date(announcement.published_at).toLocaleString()}
                                    </span>
                                    {announcement.expires_at && (
                                        <span className="text-amber-700">
                                            Valid until: {new Date(announcement.expires_at).toLocaleDateString()}
                                        </span>
                                    )}
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </AgentLayout>
    );
}

