import React from 'react';
import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link, useForm } from '@inertiajs/react';

interface Props {
    announcement: {
        id: number;
        title_en: string;
        title_si: string | null;
        content_en: string;
        content_si: string | null;
        target_audience: 'all' | 'high_level_agent' | 'low_level_agent';
        priority: 'normal' | 'important' | 'urgent';
        status: 'draft' | 'scheduled' | 'published' | 'archived';
        is_pinned: boolean;
        pin_order: number;
        scheduled_at: string | null;
        published_at: string | null;
        expires_at: string | null;
        creator?: { id: number; name: string };
        scheduler?: { id: number; name: string };
        publisher?: { id: number; name: string };
    };
}

export default function Edit({ announcement }: Props) {
    const isPublishedOrArchived = inArray(announcement.status, ['published', 'archived']);

    function inArray(needle: string, haystack: string[]): boolean {
        return haystack.includes(needle);
    }

    const { data, setData, put, processing, errors } = useForm({
        title_en: announcement.title_en,
        title_si: announcement.title_si || '',
        content_en: announcement.content_en,
        content_si: announcement.content_si || '',
        target_audience: announcement.target_audience,
        priority: announcement.priority,
        is_pinned: Boolean(announcement.is_pinned),
        pin_order: announcement.pin_order || 0,
        expires_at: announcement.expires_at ? announcement.expires_at.substring(0, 16) : '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        put(`/admin/agents/announcements/${announcement.id}`);
    };

    return (
        <AdminLayout>
            <Head title={`Edit Announcement #${announcement.id}`} />

            <div className="max-w-4xl mx-auto space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between border-b border-stone-200 dark:border-stone-800 pb-4">
                    <div>
                        <Link
                            href="/admin/agents/announcements"
                            className="text-xs text-rose-600 dark:text-rose-400 hover:underline"
                        >
                            ← Back to Announcements
                        </Link>
                        <h1 className="text-xl font-serif font-bold text-stone-900 dark:text-white mt-1">
                            Edit Announcement #{announcement.id}
                        </h1>
                        <div className="flex items-center gap-2 mt-1 text-xs text-stone-500">
                            <span>Status: <strong className="uppercase">{announcement.status}</strong></span>
                            {announcement.creator && <span>• Created by {announcement.creator.name}</span>}
                            {announcement.scheduler && <span>• Scheduled by {announcement.scheduler.name}</span>}
                            {announcement.publisher && <span>• Published by {announcement.publisher.name}</span>}
                        </div>
                    </div>
                </div>

                {isPublishedOrArchived && (
                    <div className="p-3 bg-amber-50 dark:bg-amber-900/30 border border-amber-200 dark:border-amber-800 rounded-lg text-xs text-amber-800 dark:text-amber-200">
                        <strong>Published Record Notice:</strong> Target audience is strictly immutable to preserve historical recipient tracking. Typo corrections, Sinhala translations, priorities, and expiration dates may still be modified.
                    </div>
                )}

                <form
                    onSubmit={submit}
                    className="space-y-6 bg-white dark:bg-stone-900 p-6 rounded-xl border border-stone-200 dark:border-stone-800 shadow-xs"
                >
                    {/* Bilingual Title Fields */}
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label className="block text-xs font-semibold text-stone-800 dark:text-stone-200 mb-1">
                                Title (English) <span className="text-rose-600">*</span>
                            </label>
                            <input
                                type="text"
                                required
                                maxLength={255}
                                value={data.title_en}
                                onChange={(e) => setData('title_en', e.target.value)}
                                className="w-full text-xs px-3 py-2 border rounded-md bg-stone-50 dark:bg-stone-800 dark:border-stone-700 text-stone-900 dark:text-white"
                            />
                            {errors.title_en && <p className="text-rose-500 text-[11px] mt-1">{errors.title_en}</p>}
                        </div>

                        <div>
                            <label className="block text-xs font-semibold text-stone-800 dark:text-stone-200 mb-1">
                                Title (Sinhala) <span className="text-stone-400 font-normal">(Optional)</span>
                            </label>
                            <input
                                type="text"
                                maxLength={255}
                                value={data.title_si}
                                onChange={(e) => setData('title_si', e.target.value)}
                                className="w-full text-xs px-3 py-2 border rounded-md bg-stone-50 dark:bg-stone-800 dark:border-stone-700 text-stone-900 dark:text-white"
                            />
                            {errors.title_si && <p className="text-rose-500 text-[11px] mt-1">{errors.title_si}</p>}
                        </div>
                    </div>

                    {/* Bilingual Content Fields */}
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label className="block text-xs font-semibold text-stone-800 dark:text-stone-200 mb-1">
                                Content (English) <span className="text-rose-600">*</span>
                            </label>
                            <textarea
                                rows={8}
                                required
                                value={data.content_en}
                                onChange={(e) => setData('content_en', e.target.value)}
                                className="w-full text-xs px-3 py-2 border rounded-md bg-stone-50 dark:bg-stone-800 dark:border-stone-700 text-stone-900 dark:text-white font-mono"
                            />
                            {errors.content_en && <p className="text-rose-500 text-[11px] mt-1">{errors.content_en}</p>}
                        </div>

                        <div>
                            <label className="block text-xs font-semibold text-stone-800 dark:text-stone-200 mb-1">
                                Content (Sinhala) <span className="text-stone-400 font-normal">(Optional)</span>
                            </label>
                            <textarea
                                rows={8}
                                value={data.content_si}
                                onChange={(e) => setData('content_si', e.target.value)}
                                className="w-full text-xs px-3 py-2 border rounded-md bg-stone-50 dark:bg-stone-800 dark:border-stone-700 text-stone-900 dark:text-white font-mono"
                            />
                            {errors.content_si && <p className="text-rose-500 text-[11px] mt-1">{errors.content_si}</p>}
                        </div>
                    </div>

                    {/* Target Audience & Priority */}
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6 pt-2 border-t border-stone-200 dark:border-stone-800">
                        <div>
                            <label className="block text-xs font-semibold text-stone-800 dark:text-stone-200 mb-2">
                                Target Audience {isPublishedOrArchived && <span className="text-amber-600">(Locked)</span>}
                            </label>
                            <div className="space-y-2 text-xs">
                                {[
                                    { value: 'all', label: 'All Agents (Both HLA & LLA)' },
                                    { value: 'high_level_agent', label: 'High-Level Agents (HLA) Only' },
                                    { value: 'low_level_agent', label: 'Low-Level Agents (LLA) Only' },
                                ].map((aud) => (
                                    <label key={aud.value} className={`flex items-center gap-2 ${isPublishedOrArchived ? 'opacity-60 cursor-not-allowed' : 'cursor-pointer'}`}>
                                        <input
                                            type="radio"
                                            name="target_audience"
                                            value={aud.value}
                                            disabled={isPublishedOrArchived}
                                            checked={data.target_audience === aud.value}
                                            onChange={(e) => setData('target_audience', e.target.value as any)}
                                            className="text-rose-600 focus:ring-rose-500"
                                        />
                                        <span className="text-stone-700 dark:text-stone-300">{aud.label}</span>
                                    </label>
                                ))}
                            </div>
                        </div>

                        <div>
                            <label className="block text-xs font-semibold text-stone-800 dark:text-stone-200 mb-2">
                                Priority Level
                            </label>
                            <select
                                value={data.priority}
                                onChange={(e) => setData('priority', e.target.value as any)}
                                className="w-full text-xs px-3 py-2 border rounded-md bg-stone-50 dark:bg-stone-800 dark:border-stone-700 text-stone-900 dark:text-white"
                            >
                                <option value="normal">Normal</option>
                                <option value="important">Important</option>
                                <option value="urgent">Urgent</option>
                            </select>
                        </div>
                    </div>

                    {/* Pinning & Expiration */}
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4 pt-4 border-t border-stone-200 dark:border-stone-800">
                        <div className="flex items-center gap-2 pt-6">
                            <input
                                type="checkbox"
                                id="is_pinned"
                                checked={data.is_pinned}
                                onChange={(e) => setData('is_pinned', e.target.checked)}
                                className="rounded text-rose-600 focus:ring-rose-500"
                            />
                            <label htmlFor="is_pinned" className="text-xs font-semibold text-stone-800 dark:text-stone-200 cursor-pointer">
                                📌 Pin Announcement
                            </label>
                        </div>

                        <div>
                            <label className="block text-xs font-semibold text-stone-800 dark:text-stone-200 mb-1">
                                Pin Order
                            </label>
                            <input
                                type="number"
                                min={0}
                                max={65535}
                                disabled={!data.is_pinned}
                                value={data.pin_order}
                                onChange={(e) => setData('pin_order', parseInt(e.target.value) || 0)}
                                className="w-full text-xs px-3 py-2 border rounded-md bg-stone-50 dark:bg-stone-800 dark:border-stone-700 disabled:opacity-50 text-stone-900 dark:text-white"
                            />
                        </div>

                        <div>
                            <label className="block text-xs font-semibold text-stone-800 dark:text-stone-200 mb-1">
                                Expiration Date & Time
                            </label>
                            <input
                                type="datetime-local"
                                value={data.expires_at}
                                onChange={(e) => setData('expires_at', e.target.value)}
                                className="w-full text-xs px-3 py-2 border rounded-md bg-stone-50 dark:bg-stone-800 dark:border-stone-700 text-stone-900 dark:text-white"
                            />
                        </div>
                    </div>

                    {/* Actions */}
                    <div className="flex items-center justify-end gap-3 pt-4 border-t border-stone-200 dark:border-stone-800">
                        <Link
                            href="/admin/agents/announcements"
                            className="px-4 py-2 border rounded-md text-xs font-medium text-stone-700 dark:text-stone-300 hover:bg-stone-50 dark:hover:bg-stone-800"
                        >
                            Cancel
                        </Link>
                        <button
                            type="submit"
                            disabled={processing}
                            className="px-5 py-2 bg-rose-600 hover:bg-rose-500 text-white rounded-md text-xs font-bold shadow-xs transition-colors"
                        >
                            {processing ? 'Saving...' : 'Update Announcement'}
                        </button>
                    </div>
                </form>
            </div>
        </AdminLayout>
    );
}

