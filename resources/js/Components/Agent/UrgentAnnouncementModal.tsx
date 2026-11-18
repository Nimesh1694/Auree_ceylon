import React, { useState } from 'react';

export interface UrgentAnnouncement {
    id: number;
    title_en: string;
    title_si: string | null;
    localized_title: string;
    content_en: string;
    content_si: string | null;
    localized_content: string;
    published_at: string;
}

interface Props {
    announcements: UrgentAnnouncement[];
    onAcknowledged: (announcementId: number, newUnreadCount: number) => void;
}

export default function UrgentAnnouncementModal({ announcements, onAcknowledged }: Props) {
    const [currentIndex, setCurrentIndex] = useState(0);
    const [acknowledging, setAcknowledging] = useState(false);

    if (!announcements || announcements.length === 0 || currentIndex >= announcements.length) {
        return null;
    }

    const current = announcements[currentIndex];

    const handleAcknowledge = async () => {
        setAcknowledging(true);
        try {
            const res = await fetch(`/agent/announcements/${current.id}/read`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
                },
            });

            if (res.ok) {
                const data = await res.json();
                onAcknowledged(current.id, data.unread_count ?? 0);
                setCurrentIndex((prev) => prev + 1);
            } else {
                console.error('Failed to acknowledge announcement');
            }
        } catch (err) {
            console.error('Network error during acknowledgement', err);
        } finally {
            setAcknowledging(false);
        }
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-xs p-4">
            <div className="bg-white rounded-xl shadow-2xl border-2 border-rose-500 max-w-xl w-full overflow-hidden animate-in fade-in zoom-in-95 duration-200">
                {/* Header */}
                <div className="bg-rose-600 px-6 py-3.5 text-white flex items-center justify-between">
                    <div className="flex items-center gap-2">
                        <span className="text-xl">⚠️</span>
                        <h3 className="text-sm font-bold tracking-wide uppercase">
                            Urgent Announcement Required Acknowledgment
                        </h3>
                    </div>
                    {announcements.length > 1 && (
                        <span className="text-xs font-mono bg-rose-700 px-2 py-0.5 rounded-full">
                            {currentIndex + 1} of {announcements.length}
                        </span>
                    )}
                </div>

                {/* Body */}
                <div className="p-6 space-y-4 max-h-[70vh] overflow-y-auto">
                    <div>
                        <h4 className="text-lg font-bold text-stone-900">
                            {current.localized_title}
                        </h4>
                        <span className="text-[11px] text-stone-400">
                            Published: {new Date(current.published_at).toLocaleString()}
                        </span>
                    </div>

                    <div className="text-stone-700 text-sm leading-relaxed whitespace-pre-line break-words bg-stone-50 p-4 rounded-lg border border-stone-200">
                        {current.localized_content}
                    </div>

                    <p className="text-[11px] text-rose-600 font-medium">
                        * You must acknowledge this urgent broadcast before continuing to your agent portal.
                    </p>
                </div>

                {/* Footer */}
                <div className="px-6 py-4 bg-stone-100 border-t border-stone-200 flex justify-end">
                    <button
                        onClick={handleAcknowledge}
                        disabled={acknowledging}
                        className="px-5 py-2.5 bg-rose-600 hover:bg-rose-500 disabled:opacity-50 text-white rounded-lg text-xs font-bold shadow-md transition-colors cursor-pointer flex items-center gap-2"
                    >
                        <span>{acknowledging ? 'Recording...' : '✓ I Acknowledge & Understand'}</span>
                    </button>
                </div>
            </div>
        </div>
    );
}

