import React, { useState } from 'react';
import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function Create() {
    const [publishMode, setPublishMode] = useState<'draft' | 'publish_now' | 'schedule'>('draft');

    const { data, setData, post, processing, errors } = useForm({
        title_en: '',
        title_si: '',
        content_en: '',
        content_si: '',
        target_audience: 'all',
        priority: 'normal',
        is_pinned: false,
        pin_order: 0,
        expires_at: '',
        publish_action: 'draft',
        scheduled_at: '',
    });

    const handleSubmit = (action: 'draft' | 'publish_now' | 'schedule') => {
        setData('publish_action', action);
        post('/admin/agents/announcements');
    };

    return (
        <AdminLayout>
            <Head title="Create Agent Announcement" />

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
                            New Agent Broadcast Announcement
                        </h1>
                    </div>
                </div>

                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        handleSubmit(publishMode);
                    }}
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
                                placeholder="e.g. Q4 Sales Target Bonus Announcement"
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
                                placeholder="e.g. Q4 විකුණුම් ඉලක්ක ප්‍රසාද දීමනා නිවේදනය"
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
                                placeholder="Write the announcement details in English..."
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
                                placeholder="සිංහල අන්තර්ගතය මෙහි ඇතුළත් කරන්න..."
                                className="w-full text-xs px-3 py-2 border rounded-md bg-stone-50 dark:bg-stone-800 dark:border-stone-700 text-stone-900 dark:text-white font-mono"
                            />
                            {errors.content_si && <p className="text-rose-500 text-[11px] mt-1">{errors.content_si}</p>}
                        </div>
                    </div>

                    {/* Target Audience & Priority */}
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6 pt-2 border-t border-stone-200 dark:border-stone-800">
                        <div>
                            <label className="block text-xs font-semibold text-stone-800 dark:text-stone-200 mb-2">
                                Target Audience <span className="text-rose-600">*</span>
                            </label>
                            <div className="space-y-2 text-xs">
                                {[
                                    { value: 'all', label: 'All Agents (Both HLA & LLA)' },
                                    { value: 'high_level_agent', label: 'High-Level Agents (HLA) Only' },
                                    { value: 'low_level_agent', label: 'Low-Level Agents (LLA) Only' },
                                ].map((aud) => (
                                    <label key={aud.value} className="flex items-center gap-2 cursor-pointer">
                                        <input
                                            type="radio"
                                            name="target_audience"
                                            value={aud.value}
                                            checked={data.target_audience === aud.value}
                                            onChange={(e) => setData('target_audience', e.target.value)}
                                            className="text-rose-600 focus:ring-rose-500"
                                        />
                                        <span className="text-stone-700 dark:text-stone-300">{aud.label}</span>
                                    </label>
                                ))}
                            </div>
                            {errors.target_audience && <p className="text-rose-500 text-[11px] mt-1">{errors.target_audience}</p>}
                        </div>

                        <div>
                            <label className="block text-xs font-semibold text-stone-800 dark:text-stone-200 mb-2">
                                Priority Level <span className="text-rose-600">*</span>
                            </label>
                            <select
                                value={data.priority}
                                onChange={(e) => setData('priority', e.target.value as any)}
                                className="w-full text-xs px-3 py-2 border rounded-md bg-stone-50 dark:bg-stone-800 dark:border-stone-700 text-stone-900 dark:text-white"
                            >
                                <option value="normal">Normal (Standard feed update)</option>
                                <option value="important">Important (Highlighted in feed)</option>
                                <option value="urgent">Urgent (Triggers mandatory acknowledgement modal)</option>
                            </select>
                            <p className="text-[11px] text-stone-400 mt-1">
                                Urgent broadcasts prompt an acknowledgement dialog on login until confirmed.
                            </p>
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
                                📌 Pin Announcement to Feed Top
                            </label>
                        </div>

                        <div>
                            <label className="block text-xs font-semibold text-stone-800 dark:text-stone-200 mb-1">
                                Pin Order (Lower = higher priority)
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
                                Expiration Date & Time <span className="text-stone-400 font-normal">(Optional)</span>
                            </label>
                            <input
                                type="datetime-local"
                                value={data.expires_at}
                                onChange={(e) => setData('expires_at', e.target.value)}
                                className="w-full text-xs px-3 py-2 border rounded-md bg-stone-50 dark:bg-stone-800 dark:border-stone-700 text-stone-900 dark:text-white"
                            />
                        </div>
                    </div>

                    {/* Publication Mode Selector */}
                    <div className="p-4 rounded-lg bg-stone-50 dark:bg-stone-800 border border-stone-200 dark:border-stone-700 space-y-3">
                        <span className="text-xs font-bold text-stone-800 dark:text-stone-200 block">
                            Publication Action
                        </span>
                        <div className="flex flex-wrap gap-4 text-xs">
                            <label className="flex items-center gap-2 cursor-pointer">
                                <input
                                    type="radio"
                                    name="publish_mode"
                                    checked={publishMode === 'draft'}
                                    onChange={() => {
                                        setPublishMode('draft');
                                        setData('publish_action', 'draft');
                                    }}
                                    className="text-rose-600 focus:ring-rose-500"
                                />
                                <span>Save as Draft</span>
                            </label>

                            <label className="flex items-center gap-2 cursor-pointer">
                                <input
                                    type="radio"
                                    name="publish_mode"
                                    checked={publishMode === 'publish_now'}
                                    onChange={() => {
                                        setPublishMode('publish_now');
                                        setData('publish_action', 'publish_now');
                                    }}
                                    className="text-rose-600 focus:ring-rose-500"
                                />
                                <span className="font-semibold text-emerald-600 dark:text-emerald-400">
                                    Publish Immediately
                                </span>
                            </label>

                            <label className="flex items-center gap-2 cursor-pointer">
                                <input
                                    type="radio"
                                    name="publish_mode"
                                    checked={publishMode === 'schedule'}
                                    onChange={() => {
                                        setPublishMode('schedule');
                                        setData('publish_action', 'schedule');
                                    }}
                                    className="text-rose-600 focus:ring-rose-500"
                                />
                                <span className="font-semibold text-amber-600 dark:text-amber-400">
                                    Schedule for Later
                                </span>
                            </label>
                        </div>

                        {publishMode === 'schedule' && (
                            <div className="pt-2 max-w-sm">
                                <label className="block text-xs font-semibold text-stone-700 dark:text-stone-300 mb-1">
                                    Scheduled Release Time (Asia/Colombo) <span className="text-rose-600">*</span>
                                </label>
                                <input
                                    type="datetime-local"
                                    required
                                    value={data.scheduled_at}
                                    onChange={(e) => setData('scheduled_at', e.target.value)}
                                    className="w-full text-xs px-3 py-2 border rounded-md bg-white dark:bg-stone-900 dark:border-stone-700 text-stone-900 dark:text-white"
                                />
                                {errors.scheduled_at && <p className="text-rose-500 text-[11px] mt-1">{errors.scheduled_at}</p>}
                            </div>
                        )}
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
                            className={`px-5 py-2 rounded-md text-xs font-bold text-white shadow-xs transition-colors ${
                                publishMode === 'publish_now'
                                    ? 'bg-emerald-600 hover:bg-emerald-500'
                                    : publishMode === 'schedule'
                                    ? 'bg-amber-600 hover:bg-amber-500'
                                    : 'bg-rose-600 hover:bg-rose-500'
                            }`}
                        >
                            {processing
                                ? 'Saving...'
                                : publishMode === 'publish_now'
                                ? 'Publish Announcement Now'
                                : publishMode === 'schedule'
                                ? 'Schedule Announcement'
                                : 'Save as Draft'}
                        </button>
                    </div>
                </form>
            </div>
        </AdminLayout>
    );
}

