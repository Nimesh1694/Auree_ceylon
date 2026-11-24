import AdminLayout from '@/Layouts/AdminLayout';
import { Banner, ExperienceReviews, ExperienceVideo, WhatsAppSettings } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import React, { useState } from 'react';

interface Props {
    banners: Banner[];
    filters: {
        placement?: string;
        status?: string;
        search?: string;
    };
    counts: {
        total: number;
        active: number;
        scheduled: number;
        hero_slider: number;
        sub_banners: number;
        trust_badges?: number;
    };
    experienceMode?: 'reviews' | 'video';
    experienceVideo?: ExperienceVideo;
    experienceReviews?: ExperienceReviews;
    whatsappSettings?: WhatsAppSettings;
}

function getEmbedUrl(url?: string): string | null {
    if (!url) return null;
    const ytMatch = url.match(/(?:youtu\.be\/|youtube\.com\/(?:embed\/|v\/|watch\?v=|watch\?.+&v=))([\w-]{11})/);
    if (ytMatch) {
        return `https://www.youtube-nocookie.com/embed/${ytMatch[1]}?autoplay=0&mute=1&controls=0&rel=0&modestbranding=1&iv_load_policy=3&disablekb=1&fs=0&vq=large`;
    }
    const vimeoMatch = url.match(/vimeo\.com\/(?:channels\/(?:\w+\/)?|groups\/(?:[^\/]*)\/videos\/|album\/(?:\d+)\/video\/|video\/|)(\d+)/);
    if (vimeoMatch) {
        return `https://player.vimeo.com/video/${vimeoMatch[1]}?background=1&autoplay=0&muted=1&controls=0&title=0&byline=0&portrait=0&quality=480p`;
    }
    return url;
}

export default function BannerIndex({
    banners = [],
    filters = {},
    counts,
    experienceMode = 'reviews',
    experienceVideo,
    experienceReviews,
    whatsappSettings,
}: Props) {
    const [selectedPlacement, setSelectedPlacement] = useState(filters.placement || 'all');
    const [selectedStatus, setSelectedStatus] = useState(filters.status || 'all');
    const [searchQuery, setSearchQuery] = useState(filters.search || '');

    // Floating WhatsApp Support Settings (Marketing Manager Control)
    const whatsappForm = useForm({
        whatsapp_number: whatsappSettings?.number || '94772345678',
        whatsapp_message: whatsappSettings?.message || 'Hello Aura Luxe, I would like to inquire about your products.',
        is_enabled: whatsappSettings?.is_enabled ?? true,
    });

    const handleWhatsappSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        whatsappForm.post('/admin/banners/whatsapp', {
            preserveScroll: true,
        });
    };

    // Storefront Experience Section State (Option 1: Reviews vs Option 2: Video)
    const currentExperienceMode = experienceMode;
    const [videoConfigOpen, setVideoConfigOpen] = useState(false);

    const videoForm = useForm<{
        mode: 'reviews' | 'video';
        video_url: string;
        video_type: 'youtube' | 'vimeo' | 'mp4';
        video_eyebrow_en: string;
        video_eyebrow_si: string;
        video_title_en: string;
        video_title_si: string;
        video_description_en: string;
        video_description_si: string;
        video_cta_text_en: string;
        video_cta_text_si: string;
        video_cta_url: string;
        video_autoplay: boolean;
        poster: File | null;
    }>({
        mode: experienceMode || 'reviews',
        video_url: experienceVideo?.video_url || 'https://www.youtube.com/watch?v=2e_Z2WzY9s4',
        video_type: (experienceVideo?.video_type as 'youtube' | 'vimeo' | 'mp4') || 'youtube',
        video_eyebrow_en: experienceVideo?.eyebrow_en || 'THE ROYAL CEYLON RITUAL',
        video_eyebrow_si: experienceVideo?.eyebrow_si || 'රාජකීය ශාකසාර අත්දැකීම',
        video_title_en: experienceVideo?.title_en || 'Behind the Royal Ceylon Botanical Science',
        video_title_si: experienceVideo?.title_si || 'අපගේ රාජකීය ශාකසාර රූපලාවණ්‍ය ක්‍රමවේදය',
        video_description_en: experienceVideo?.description_en || 'Immerse yourself in our centuries-old cold-pressed distillation rituals, sustainably harvested from Sri Lanka’s lush botanical reserves.',
        video_description_si: experienceVideo?.description_si || 'ශ්‍රී ලංකාවේ සාරවත් ඖෂධ උයන්වලින් තිරසාර ලෙස ලබාගත් පිරිසිදු ස්වභාවික සාරයෙන් අප නිපදවන සත්කාර නරඹන්න.',
        video_cta_text_en: experienceVideo?.cta_text_en || 'Explore The Botanical Rituals',
        video_cta_text_si: experienceVideo?.cta_text_si || 'රාජකීය එකතුව නරඹන්න',
        video_cta_url: experienceVideo?.cta_url || '/products',
        video_autoplay: experienceVideo?.autoplay !== false,
        poster: null,
    });

    const handleSwapExperienceMode = (newMode: 'reviews' | 'video') => {
        if (newMode === currentExperienceMode) return;
        router.post('/admin/banners/experience', {
            mode: newMode,
        }, {
            preserveScroll: true,
        });
    };

    const handleVideoSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        videoForm.post('/admin/banners/experience', {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                setVideoConfigOpen(false);
            },
        });
    };

    // Modal state for create / edit
    const [modalOpen, setModalOpen] = useState(false);
    const [editingBanner, setEditingBanner] = useState<Banner | null>(null);

    // Form handler for create/edit
    const form = useForm<{
        title_en: string;
        title_si: string;
        subtitle_en: string;
        subtitle_si: string;
        cta_text_en: string;
        cta_text_si: string;
        cta_url: string;
        placement: 'hero_slider' | 'sub_banner_1' | 'sub_banner_2' | 'trust_badge';
        theme_style: string;
        starts_at: string;
        ends_at: string;
        is_active: boolean;
        sort_order: number;
        image: File | null;
        _method?: string;
    }>({
        title_en: '',
        title_si: '',
        subtitle_en: '',
        subtitle_si: '',
        cta_text_en: 'EXPERIENCE NOW',
        cta_text_si: 'දැන්ම නරඹන්න',
        cta_url: '/products',
        placement: 'hero_slider',
        theme_style: 'festive_rose',
        starts_at: '',
        ends_at: '',
        is_active: true,
        sort_order: 1,
        image: null,
    });

    const formatForInput = (dateStr?: string | null) => {
        if (!dateStr) return '';
        const d = new Date(dateStr);
        if (isNaN(d.getTime())) return '';
        const pad = (n: number) => (n < 10 ? '0' + n : n);
        return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
    };

    const openCreateModal = () => {
        setEditingBanner(null);
        form.reset();
        form.setData({
            title_en: '',
            title_si: '',
            subtitle_en: '',
            subtitle_si: '',
            cta_text_en: 'EXPERIENCE NOW',
            cta_text_si: 'දැන්ම නරඹන්න',
            cta_url: '/products',
            placement: 'hero_slider',
            theme_style: 'festive_rose',
            starts_at: '',
            ends_at: '',
            is_active: true,
            sort_order: (banners.length || 0) + 1,
            image: null,
        });
        setModalOpen(true);
    };

    const openCreateTrustBadgeModal = () => {
        setEditingBanner(null);
        form.reset();
        form.setData({
            title_en: '',
            title_si: '',
            subtitle_en: '',
            subtitle_si: '',
            cta_text_en: 'LEARN MORE',
            cta_text_si: 'වැඩිදුර තොරතුරු',
            cta_url: '/about',
            placement: 'trust_badge',
            theme_style: '🌿',
            starts_at: '',
            ends_at: '',
            is_active: true,
            sort_order: (banners.filter(b => b.placement === 'trust_badge').length || 0) + 1,
            image: null,
        });
        setModalOpen(true);
    };

    const openEditModal = (b: Banner) => {
        setEditingBanner(b);
        form.setData({
            title_en: b.title_en || '',
            title_si: b.title_si || '',
            subtitle_en: b.subtitle_en || '',
            subtitle_si: b.subtitle_si || '',
            cta_text_en: b.cta_text_en || (b.placement === 'trust_badge' ? 'LEARN MORE' : 'EXPERIENCE NOW'),
            cta_text_si: b.cta_text_si || (b.placement === 'trust_badge' ? 'වැඩිදුර තොරතුරු' : 'දැන්ම නරඹන්න'),
            cta_url: b.cta_url || (b.placement === 'trust_badge' ? '/about' : '/products'),
            placement: b.placement as 'hero_slider' | 'sub_banner_1' | 'sub_banner_2' | 'trust_badge',
            theme_style: b.theme_style || (b.placement === 'trust_badge' ? '🌿' : 'festive_rose'),
            starts_at: formatForInput(b.starts_at),
            ends_at: formatForInput(b.ends_at),
            is_active: b.is_active,
            sort_order: b.sort_order || 1,
            image: null,
        });
        setModalOpen(true);
    };

    const handleFormSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (editingBanner) {
            form.transform((data) => ({
                ...data,
                _method: 'PUT',
            }));
            form.post(`/admin/banners/${editingBanner.id}`, {
                preserveScroll: true,
                onSuccess: () => {
                    setModalOpen(false);
                    form.reset();
                },
            });
        } else {
            form.post('/admin/banners', {
                preserveScroll: true,
                onSuccess: () => {
                    setModalOpen(false);
                    form.reset();
                },
            });
        }
    };

    const handleToggle = (id: number) => {
        router.patch(`/admin/banners/${id}/toggle`, {}, { preserveScroll: true });
    };

    const handleDelete = (id: number) => {
        if (!confirm('Are you sure you want to permanently delete this banner?')) return;
        router.delete(`/admin/banners/${id}`, { preserveScroll: true });
    };

    const handleFilterChange = (placement: string, status: string, search: string) => {
        router.get(
            '/admin/banners',
            {
                placement: placement !== 'all' ? placement : undefined,
                status: status !== 'all' ? status : undefined,
                search: search || undefined,
            },
            { preserveState: true }
        );
    };

    // Helper for status badge
    const renderStatusBadge = (banner: Banner) => {
        const s = banner.schedule_status;
        if (s === 'active') {
            return (
                <span className="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-300/60">
                    <span className="w-1.5 h-1.5 rounded-full bg-emerald-600 animate-pulse"></span>
                    Live Now
                </span>
            );
        }
        if (s === 'scheduled') {
            return (
                <span className="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-blue-100 text-blue-800 dark:bg-blue-950/60 dark:text-blue-300 border border-blue-300/60">
                    <span className="w-1.5 h-1.5 rounded-full bg-blue-600"></span>
                    Scheduled
                </span>
            );
        }
        if (s === 'expired') {
            return (
                <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-medium bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400 border border-slate-300/60">
                    Expired
                </span>
            );
        }
        return (
            <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-medium bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300 border border-amber-300/60">
                Draft / Inactive
            </span>
        );
    };

    const getPlacementName = (placement: string) => {
        switch (placement) {
            case 'hero_slider':
                return 'Hero Swapper (Top Gallery)';
            case 'sub_banner_1':
                return 'Sub-Banner 1 (Bottom Left)';
            case 'sub_banner_2':
                return 'Sub-Banner 2 (Bottom Right)';
            case 'trust_badge':
                return 'Trust Badge (Assurance Seal)';
            default:
                return placement;
        }
    };

    return (
        <AdminLayout>
            <Head title="Storefront Banners & Promotions | Marketing Management" />

            <div className="space-y-6">
                {/* 1. Page Header & Actions */}
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <div className="flex items-center gap-2">
                            <span className="text-xl">✨</span>
                            <h1 className="text-2xl font-bold text-slate-900 dark:text-slate-100 tracking-tight">
                                Storefront Banners & Scheduling
                            </h1>
                        </div>
                        <p className="text-sm text-slate-500 dark:text-slate-400 mt-1">
                            Curate, re-order, and schedule the swappable luxury hero gallery, promotional cards, and royal trust badges (Spa Ceylon style).
                        </p>
                    </div>

                    <div className="flex items-center gap-2.5 flex-wrap">
                        <button
                            type="button"
                            onClick={openCreateTrustBadgeModal}
                            className="inline-flex items-center justify-center gap-2 px-3.5 py-2.5 bg-amber-100 hover:bg-amber-200 text-amber-900 dark:bg-amber-950/50 dark:hover:bg-amber-900/60 dark:text-amber-300 text-xs uppercase tracking-wider font-semibold rounded-lg border border-amber-300/60 transition-colors cursor-pointer min-h-[44px]"
                        >
                            <span>🌿</span>
                            <span>+ Add Trust Seal</span>
                        </button>
                        <button
                            type="button"
                            onClick={openCreateModal}
                            className="inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-rose-700 hover:bg-rose-800 text-white text-xs uppercase tracking-wider font-semibold rounded-lg shadow-sm transition-colors cursor-pointer min-h-[44px]"
                        >
                            <span className="text-base font-light">+</span>
                            <span>Add New Banner</span>
                        </button>
                    </div>
                </div>

                {/* 2. KPI Summary Cards */}
                <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
                    <div className="p-4 bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-2xs">
                        <span className="text-xs text-slate-500 dark:text-slate-400 font-medium block">Total Items</span>
                        <div className="flex items-baseline gap-2 mt-1">
                            <span className="text-2xl font-bold text-slate-900 dark:text-slate-100">{counts?.total || banners.length}</span>
                            <span className="text-xs text-slate-400">banners & seals</span>
                        </div>
                    </div>

                    <div className="p-4 bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-2xs">
                        <span className="text-xs text-emerald-600 dark:text-emerald-400 font-medium flex items-center gap-1.5">
                            <span className="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                            Live on Storefront
                        </span>
                        <div className="flex items-baseline gap-2 mt-1">
                            <span className="text-2xl font-bold text-emerald-700 dark:text-emerald-300">{counts?.active || 0}</span>
                            <span className="text-xs text-emerald-600/70">currently live</span>
                        </div>
                    </div>

                    <div className="p-4 bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-2xs">
                        <span className="text-xs text-blue-600 dark:text-blue-400 font-medium block">Scheduled Future</span>
                        <div className="flex items-baseline gap-2 mt-1">
                            <span className="text-2xl font-bold text-blue-700 dark:text-blue-300">{counts?.scheduled || 0}</span>
                            <span className="text-xs text-blue-500/70">queued</span>
                        </div>
                    </div>

                    <div className="p-4 bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-2xs">
                        <span className="text-xs text-slate-500 dark:text-slate-400 font-medium block">Hero Slides</span>
                        <div className="flex items-baseline gap-2 mt-1">
                            <span className="text-2xl font-bold text-slate-900 dark:text-slate-100">{counts?.hero_slider || 0}</span>
                            <span className="text-xs text-slate-400">in swapper</span>
                        </div>
                    </div>

                    <div className="p-4 bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-2xs col-span-2 sm:col-span-1">
                        <span className="text-xs text-amber-700 dark:text-amber-400 font-medium flex items-center gap-1">
                            <span>🌿</span>
                            <span>Trust Seals</span>
                        </span>
                        <div className="flex items-baseline gap-2 mt-1">
                            <span className="text-2xl font-bold text-amber-800 dark:text-amber-300">{counts?.trust_badges || 0}</span>
                            <span className="text-xs text-amber-600/70">assurance strip</span>
                        </div>
                    </div>
                </div>

                {/* 2.5 Storefront Experience Section Switcher (Comments vs Video) */}
                <div className="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-5 sm:p-6 shadow-sm">
                    <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-5 border-b border-slate-100 dark:border-slate-800">
                        <div>
                            <div className="flex items-center gap-2">
                                <span className="text-xl">🎭</span>
                                <h2 className="text-lg font-bold text-slate-900 dark:text-slate-100 tracking-tight">
                                    Storefront Brand Experience (Homepage Section 7)
                                </h2>
                                <span className="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-rose-100 dark:bg-rose-950/60 text-rose-800 dark:text-rose-300">
                                    Marketing Manager Control
                                </span>
                            </div>
                            <p className="text-xs text-slate-500 dark:text-slate-400 mt-1 max-w-2xl">
                                Curate whether customers see verified buyer feedback or an immersive brand ritual video.
                                <strong className="text-slate-700 dark:text-slate-200 ml-1">Only one option is active at any time.</strong>
                            </p>
                        </div>

                        {/* Quick Dual Switch Pill */}
                        <div className="inline-flex items-center p-1 bg-slate-100 dark:bg-slate-800 rounded-full border border-slate-200 dark:border-slate-700 self-start sm:self-auto">
                            <button
                                type="button"
                                onClick={() => handleSwapExperienceMode('reviews')}
                                className={`flex items-center gap-2 px-3.5 py-1.5 rounded-full text-xs font-semibold transition-all cursor-pointer ${
                                    currentExperienceMode === 'reviews'
                                        ? 'bg-rose-900 text-white shadow-xs'
                                        : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200'
                                }`}
                            >
                                <span>💬</span>
                                <span>Customer Comments</span>
                                {currentExperienceMode === 'reviews' && (
                                    <span className="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                                )}
                            </button>
                            <button
                                type="button"
                                onClick={() => handleSwapExperienceMode('video')}
                                className={`flex items-center gap-2 px-3.5 py-1.5 rounded-full text-xs font-semibold transition-all cursor-pointer ${
                                    currentExperienceMode === 'video'
                                        ? 'bg-rose-900 text-white shadow-xs'
                                        : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200'
                                }`}
                            >
                                <span>🎬</span>
                                <span>Brand Video</span>
                                {currentExperienceMode === 'video' && (
                                    <span className="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                                )}
                            </button>
                        </div>
                    </div>

                    {/* Two Options Comparison Cards */}
                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-5 mt-5">
                        {/* Option 1: Verified Customer Comments */}
                        <div
                            className={`rounded-xl border p-5 transition-all flex flex-col justify-between ${
                                currentExperienceMode === 'reviews'
                                    ? 'border-emerald-500/80 bg-emerald-50/30 dark:bg-emerald-950/20 shadow-xs ring-1 ring-emerald-500/20'
                                    : 'border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/40 opacity-75 hover:opacity-100'
                            }`}
                        >
                            <div>
                                <div className="flex items-center justify-between mb-3">
                                    <div className="flex items-center gap-2">
                                        <span className="text-lg">💬</span>
                                        <h3 className="text-sm font-bold text-slate-900 dark:text-slate-100">
                                            Option 1: Customer Reviews & Comments
                                        </h3>
                                    </div>
                                    {currentExperienceMode === 'reviews' ? (
                                        <span className="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-300/60">
                                            <span className="w-1.5 h-1.5 rounded-full bg-emerald-600 animate-pulse"></span>
                                            Active on Storefront
                                        </span>
                                    ) : (
                                        <span className="text-[11px] text-slate-500 font-medium">Inactive</span>
                                    )}
                                </div>
                                <p className="text-xs text-slate-600 dark:text-slate-400 leading-relaxed mb-4">
                                    Presents 3 verified Ceylon buyer testimonial cards with 5-star ratings, buyer locations (e.g., Kandy, Colombo 07, Galle), and real customer quotes.
                                </p>

                                {/* Mini Reviews Mockup */}
                                <div className="space-y-2 bg-white dark:bg-slate-900 p-3 rounded-lg border border-slate-200/80 dark:border-slate-800 text-[11px]">
                                    <div className="text-[10px] uppercase tracking-wider text-rose-700 font-bold">
                                        {experienceReviews?.eyebrow_en || 'VERIFIED CEYLON EXPERIENCES'}
                                    </div>
                                    <div className="font-serif font-semibold text-slate-800 dark:text-slate-200 text-xs">
                                        {experienceReviews?.title_en || 'Loved by Discerning Women & Men Across Sri Lanka'}
                                    </div>
                                    <div className="grid grid-cols-3 gap-2 pt-1 text-[10px] text-slate-500">
                                        <div className="p-2 rounded bg-slate-50 dark:bg-slate-800 border border-slate-100 dark:border-slate-700 truncate">
                                            <div className="text-amber-500">★★★★★</div>
                                            <div className="font-medium text-slate-800 dark:text-slate-200 truncate mt-0.5">Nipuni S.</div>
                                            <div className="text-[9px] text-slate-400 truncate">Kandy</div>
                                        </div>
                                        <div className="p-2 rounded bg-slate-50 dark:bg-slate-800 border border-slate-100 dark:border-slate-700 truncate">
                                            <div className="text-amber-500">★★★★★</div>
                                            <div className="font-medium text-slate-800 dark:text-slate-200 truncate mt-0.5">Dr. Hansi F.</div>
                                            <div className="text-[9px] text-slate-400 truncate">Colombo 07</div>
                                        </div>
                                        <div className="p-2 rounded bg-slate-50 dark:bg-slate-800 border border-slate-100 dark:border-slate-700 truncate">
                                            <div className="text-amber-500">★★★★★</div>
                                            <div className="font-medium text-slate-800 dark:text-slate-200 truncate mt-0.5">Malik J.</div>
                                            <div className="text-[9px] text-slate-400 truncate">Galle</div>
                                        </div>
                                    </div>
                                </div>


                            </div>

                            <div className="mt-4 pt-3 border-t border-slate-200/60 dark:border-slate-800/80 flex items-center justify-between">
                                <span className="text-[11px] text-slate-500">
                                    {currentExperienceMode === 'reviews' ? 'Currently displayed on homepage' : 'Click to display on homepage'}
                                </span>
                                {currentExperienceMode === 'reviews' ? (
                                    <span className="text-xs font-semibold text-emerald-700 dark:text-emerald-400 flex items-center gap-1">
                                        <span>✓</span> Active Mode
                                    </span>
                                ) : (
                                    <button
                                        type="button"
                                        onClick={() => handleSwapExperienceMode('reviews')}
                                        className="px-3.5 py-1.5 bg-slate-900 hover:bg-rose-900 text-white text-xs font-semibold rounded-lg transition-colors cursor-pointer"
                                    >
                                        Activate Reviews Option
                                    </button>
                                )}
                            </div>
                        </div>

                        {/* Option 2: Featured Brand / Ritual Video */}
                        <div
                            className={`rounded-xl border p-5 transition-all flex flex-col justify-between ${
                                currentExperienceMode === 'video'
                                    ? 'border-emerald-500/80 bg-emerald-50/30 dark:bg-emerald-950/20 shadow-xs ring-1 ring-emerald-500/20'
                                    : 'border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/40 opacity-75 hover:opacity-100'
                            }`}
                        >
                            <div>
                                <div className="flex items-center justify-between mb-3">
                                    <div className="flex items-center gap-2">
                                        <span className="text-lg">🎬</span>
                                        <h3 className="text-sm font-bold text-slate-900 dark:text-slate-100">
                                            Option 2: Featured Brand / Ritual Video
                                        </h3>
                                    </div>
                                    {currentExperienceMode === 'video' ? (
                                        <span className="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-300/60">
                                            <span className="w-1.5 h-1.5 rounded-full bg-emerald-600 animate-pulse"></span>
                                            Active on Storefront
                                        </span>
                                    ) : (
                                        <span className="text-[11px] text-slate-500 font-medium">Inactive</span>
                                    )}
                                </div>
                                <p className="text-xs text-slate-600 dark:text-slate-400 leading-relaxed mb-4">
                                    Replaces comment cards with a 16:9 cinematic video player (YouTube, Vimeo, or MP4) highlighting botanical rituals, cold-pressed extraction, or behind-the-scenes craft.
                                </p>

                                {/* Mini Video Mockup */}
                                <div className="bg-white dark:bg-slate-900 p-3 rounded-lg border border-slate-200/80 dark:border-slate-800 text-[11px] space-y-2">
                                    <div className="flex items-center justify-between">
                                        <span className="text-[10px] uppercase tracking-wider text-rose-700 font-bold truncate">
                                            {experienceVideo?.eyebrow_en || 'THE ROYAL CEYLON RITUAL'}
                                        </span>
                                        <span className="text-[9px] uppercase px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 font-mono text-slate-600">
                                            {experienceVideo?.video_type || 'youtube'}
                                        </span>
                                    </div>
                                    <div className="font-serif font-semibold text-slate-800 dark:text-slate-200 text-xs truncate">
                                        {experienceVideo?.title_en || 'Behind the Royal Ceylon Botanical Science'}
                                    </div>
                                    <div className="relative aspect-video rounded-md overflow-hidden bg-slate-900 flex items-center justify-center border border-slate-800">
                                        <div className="w-9 h-9 rounded-full bg-rose-700/90 text-white flex items-center justify-center shadow-lg">
                                            <svg className="w-4 h-4 ml-0.5 fill-current" viewBox="0 0 24 24">
                                                <path d="M8 5v14l11-7z" />
                                            </svg>
                                        </div>
                                        <div className="absolute bottom-1.5 left-2 right-2 flex justify-between text-[9px] text-white/70">
                                            <span className="truncate max-w-[200px]">{experienceVideo?.video_url}</span>
                                            <span className="font-medium text-emerald-300 flex items-center gap-1">
                                                {experienceVideo?.autoplay !== false ? '⚡ Auto-plays on scroll' : '16:9 Luxury Frame'}
                                            </span>
                                        </div>
                                    </div>
                                </div>


                            </div>

                            <div className="mt-4 pt-3 border-t border-slate-200/60 dark:border-slate-800/80 flex items-center justify-between gap-2 flex-wrap">
                                <button
                                    type="button"
                                    onClick={() => setVideoConfigOpen(!videoConfigOpen)}
                                    className="px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300 text-xs font-semibold transition-colors cursor-pointer flex items-center gap-1.5"
                                >
                                    <span>⚙️</span>
                                    <span>{videoConfigOpen ? 'Hide Video Settings' : 'Configure Video & URL'}</span>
                                </button>

                                {currentExperienceMode === 'video' ? (
                                    <span className="text-xs font-semibold text-emerald-700 dark:text-emerald-400 flex items-center gap-1">
                                        <span>✓</span> Active Mode
                                    </span>
                                ) : (
                                    <button
                                        type="button"
                                        onClick={() => handleSwapExperienceMode('video')}
                                        className="px-3.5 py-1.5 bg-rose-900 hover:bg-rose-800 text-white text-xs font-semibold rounded-lg transition-colors cursor-pointer"
                                    >
                                        Activate Video Option
                                    </button>
                                )}
                            </div>
                        </div>
                    </div>

                    {/* Expandable Video Settings & Live Preview Panel */}
                    {videoConfigOpen && (
                        <div className="mt-5 p-5 bg-slate-50 dark:bg-slate-800/60 rounded-xl border border-slate-200 dark:border-slate-700 space-y-4">
                            <div className="flex items-center justify-between pb-3 border-b border-slate-200 dark:border-slate-700">
                                <div>
                                    <h4 className="text-sm font-bold text-slate-900 dark:text-slate-100 flex items-center gap-1.5">
                                        <span>🎬</span>
                                        <span>Configure Featured Brand Video</span>
                                    </h4>
                                    <p className="text-xs text-slate-500 mt-0.5">
                                        Paste a YouTube, Vimeo, or direct MP4 link and set custom title, description, and CTA.
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    onClick={() => setVideoConfigOpen(false)}
                                    className="text-slate-400 hover:text-slate-600 text-xs font-semibold cursor-pointer"
                                >
                                    ✕ Close
                                </button>
                            </div>

                            <form onSubmit={handleVideoSubmit} className="space-y-4">
                                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div className="md:col-span-2">
                                        <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                            Video URL (YouTube / Vimeo / MP4) *
                                        </label>
                                        <input
                                            type="url"
                                            required
                                            value={videoForm.data.video_url}
                                            onChange={(e) => videoForm.setData('video_url', e.target.value)}
                                            placeholder="https://www.youtube.com/watch?v=..."
                                            className="w-full text-xs px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100"
                                        />
                                        <span className="text-[11px] text-slate-500 mt-1 block">
                                            Paste standard YouTube URL (e.g. youtube.com/watch?v=ID or youtu.be/ID). The system automatically embeds it cleanly.
                                        </span>
                                    </div>

                                    <div>
                                        <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                            Video Provider / Format
                                        </label>
                                        <select
                                            value={videoForm.data.video_type}
                                            onChange={(e) => videoForm.setData('video_type', e.target.value as 'youtube' | 'vimeo' | 'mp4')}
                                            className="w-full text-xs px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 cursor-pointer"
                                        >
                                            <option value="youtube">YouTube Embed</option>
                                            <option value="vimeo">Vimeo Embed</option>
                                            <option value="mp4">Direct MP4 Video</option>
                                        </select>
                                    </div>
                                </div>



                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                            Eyebrow Badge (English)
                                        </label>
                                        <input
                                            type="text"
                                            value={videoForm.data.video_eyebrow_en}
                                            onChange={(e) => videoForm.setData('video_eyebrow_en', e.target.value)}
                                            placeholder="THE ROYAL CEYLON RITUAL"
                                            className="w-full text-xs px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100"
                                        />
                                    </div>
                                    <div>
                                        <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                            Eyebrow Badge (Sinhala)
                                        </label>
                                        <input
                                            type="text"
                                            value={videoForm.data.video_eyebrow_si}
                                            onChange={(e) => videoForm.setData('video_eyebrow_si', e.target.value)}
                                            placeholder="රාජකීය ශාකසාර අත්දැකීම"
                                            className="w-full text-xs px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100"
                                        />
                                    </div>
                                </div>



                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                            Section Headline (English) *
                                        </label>
                                        <input
                                            type="text"
                                            required
                                            value={videoForm.data.video_title_en}
                                            onChange={(e) => videoForm.setData('video_title_en', e.target.value)}
                                            placeholder="Behind the Royal Ceylon Botanical Science"
                                            className="w-full text-xs px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100"
                                        />
                                    </div>
                                    <div>
                                        <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                            Section Headline (Sinhala)
                                        </label>
                                        <input
                                            type="text"
                                            value={videoForm.data.video_title_si}
                                            onChange={(e) => videoForm.setData('video_title_si', e.target.value)}
                                            placeholder="අපගේ රාජකීය ශාකසාර රූපලාවණ්‍ය ක්‍රමවේදය"
                                            className="w-full text-xs px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100"
                                        />
                                    </div>
                                </div>



                                <div>
                                    <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                        Description / Editorial Context (English)
                                    </label>
                                    <textarea
                                        rows={2}
                                        value={videoForm.data.video_description_en}
                                        onChange={(e) => videoForm.setData('video_description_en', e.target.value)}
                                        placeholder="Describe the ritual, botanical sourcing, or craft depicted in the video..."
                                        className="w-full text-xs px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100"
                                    />
                                </div>

                                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div>
                                        <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                            CTA Button Label (English)
                                        </label>
                                        <input
                                            type="text"
                                            value={videoForm.data.video_cta_text_en}
                                            onChange={(e) => videoForm.setData('video_cta_text_en', e.target.value)}
                                            placeholder="Explore The Botanical Rituals"
                                            className="w-full text-xs px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100"
                                        />
                                    </div>
                                    <div>
                                        <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                            CTA Button Label (Sinhala)
                                        </label>
                                        <input
                                            type="text"
                                            value={videoForm.data.video_cta_text_si}
                                            onChange={(e) => videoForm.setData('video_cta_text_si', e.target.value)}
                                            placeholder="රාජකීය එකතුව නරඹන්න"
                                            className="w-full text-xs px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100"
                                        />
                                    </div>
                                    <div>
                                        <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                            CTA Destination Link
                                        </label>
                                        <input
                                            type="text"
                                            value={videoForm.data.video_cta_url}
                                            onChange={(e) => videoForm.setData('video_cta_url', e.target.value)}
                                            placeholder="/products"
                                            className="w-full text-xs px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100"
                                        />
                                    </div>
                                </div>

                                {/* Autoplay Setting */}
                                <div className="flex items-center gap-2.5 p-3 rounded-lg bg-emerald-50/70 dark:bg-emerald-950/20 border border-emerald-200/70 dark:border-emerald-800/40">
                                    <input
                                        type="checkbox"
                                        id="video_autoplay"
                                        checked={videoForm.data.video_autoplay}
                                        onChange={(e) => videoForm.setData('video_autoplay', e.target.checked)}
                                        className="w-4 h-4 rounded text-emerald-600 focus:ring-emerald-500 border-slate-300 cursor-pointer"
                                    />
                                    <label htmlFor="video_autoplay" className="text-xs font-semibold text-emerald-950 dark:text-emerald-200 cursor-pointer flex items-center gap-1.5 flex-wrap">
                                        <span>⚡ Silent ambient auto-play (Without audio, zero icons, zero buttons)</span>
                                        <span className="text-[11px] font-normal text-emerald-700 dark:text-emerald-400">(Plays cleanly as a background reel without sound, progress bars, or YouTube buttons)</span>
                                    </label>
                                </div>



                                {/* Live Video Embed Tester */}
                                {videoForm.data.video_url && (
                                    <div className="pt-2">
                                        <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
                                            Live Player Preview
                                        </label>
                                        <div className="relative aspect-video max-w-xl rounded-xl overflow-hidden shadow-md bg-black border border-slate-700">
                                            {videoForm.data.video_type === 'mp4' ? (
                                                <video
                                                    src={videoForm.data.video_url}
                                                    controls
                                                    className="w-full h-full object-cover"
                                                />
                                            ) : (
                                                <iframe
                                                    src={getEmbedUrl(videoForm.data.video_url) || ''}
                                                    title="Video Preview"
                                                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                                    allowFullScreen
                                                    className="w-full h-full border-0"
                                                />
                                            )}
                                        </div>
                                    </div>
                                )}

                                <div className="flex items-center justify-end gap-3 pt-3 border-t border-slate-200 dark:border-slate-700">
                                    <button
                                        type="button"
                                        onClick={() => setVideoConfigOpen(false)}
                                        className="px-4 py-2 border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-300 text-xs font-semibold rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 cursor-pointer"
                                    >
                                        Cancel
                                    </button>
                                    <button
                                        type="submit"
                                        disabled={videoForm.processing}
                                        className="px-5 py-2 bg-rose-800 hover:bg-rose-900 text-white text-xs font-semibold rounded-lg shadow-sm transition-colors cursor-pointer flex items-center gap-2"
                                    >
                                        {videoForm.processing && (
                                            <svg className="animate-spin h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24">
                                                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                                                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                        )}
                                        <span>Save Video Details</span>
                                    </button>
                                </div>
                            </form>
                        </div>
                    )}
                </div>

                {/* 2.6 Floating WhatsApp Client Concierge Settings (Marketing Manager Control) */}
                <div className="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-5 sm:p-6 shadow-sm">
                    <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-5 border-b border-slate-100 dark:border-slate-800">
                        <div>
                            <div className="flex items-center gap-2">
                                <div className="w-8 h-8 rounded-full bg-[#25D366] text-white flex items-center justify-center shrink-0 shadow-xs">
                                    <svg className="w-4 h-4 fill-white" viewBox="0 0 24 24">
                                        <path d="M17.472 14.382c-.301-.15-1.78-.878-2.056-.979-.275-.1-.475-.15-.675.15-.2.3-.775.98-1.025 1.28-.25.3-.45.325-.75.175-.3-.15-1.267-.467-2.414-1.489-.893-.797-1.496-1.782-1.671-2.082-.175-.3-.019-.462.131-.611.135-.134.3-.35.45-.525.15-.175.2-.3.3-.5.1-.2.05-.375-.025-.525-.075-.15-.675-1.625-.925-2.225-.244-.584-.493-.505-.675-.514-.175-.009-.375-.01-.575-.01-.2 0-.525.075-.8.375s-1.05 1.025-1.05 2.5 1.075 2.9 1.225 3.1c.15.2 2.115 3.23 5.124 4.53.716.31 1.275.495 1.71.634.72.23 1.375.197 1.892.12.578-.086 1.78-.728 2.03-1.43.25-.702.25-1.303.175-1.428-.075-.125-.275-.2-.575-.35zM12.04 2C6.51 2 2.02 6.49 2.02 12.02c0 1.98.58 3.86 1.68 5.46L2 22l4.67-1.63c1.54 1.02 3.36 1.57 5.37 1.57 5.53 0 10.02-4.49 10.02-10.02S17.57 2 12.04 2zm0 18.25c-1.75 0-3.41-.5-4.84-1.44l-.35-.23-2.78.97.99-2.7-.23-.37c-1.02-1.48-1.56-3.23-1.56-5.06 0-4.54 3.7-8.24 8.24-8.24 4.54 0 8.24 3.7 8.24 8.24 0 4.54-3.7 8.24-8.24 8.24z"/>
                                    </svg>
                                </div>
                                <h3 className="text-base font-bold text-slate-900 dark:text-slate-100 tracking-tight">
                                    WhatsApp Customer Care & Sales Hotline
                                </h3>
                                <span className="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-emerald-100 dark:bg-emerald-950/60 text-emerald-800 dark:text-emerald-300">
                                    Marketing Manager Control
                                </span>
                            </div>
                            <p className="text-xs text-slate-500 dark:text-slate-400 mt-1 max-w-2xl">
                                Configure the official WhatsApp support phone number and automated pre-filled message anytime. Changes reflect immediately across all client-facing pages on the storefront.
                            </p>
                        </div>

                        {/* Status Beacon */}
                        <div className="flex items-center gap-2">
                            <span className={`inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold ${
                                whatsappForm.data.is_enabled
                                    ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-300/60'
                                    : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400 border border-slate-300/60'
                            }`}>
                                <span className={`w-2 h-2 rounded-full ${whatsappForm.data.is_enabled ? 'bg-emerald-500 animate-pulse' : 'bg-slate-400'}`}></span>
                                <span>{whatsappForm.data.is_enabled ? 'Active on Storefront' : 'Disabled'}</span>
                            </span>
                        </div>
                    </div>

                    <form onSubmit={handleWhatsappSubmit} className="mt-5 space-y-4">
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div>
                                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                    WhatsApp Phone Number <span className="text-rose-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    required
                                    value={whatsappForm.data.whatsapp_number}
                                    onChange={(e) => whatsappForm.setData('whatsapp_number', e.target.value)}
                                    placeholder="e.g. 0771234567 or 94771234567"
                                    className="w-full text-xs font-mono px-3 py-2.5 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:border-emerald-600 focus:ring-emerald-600"
                                />
                                <span className="text-[11px] text-slate-500 dark:text-slate-400 block mt-1">
                                    Enter Sri Lankan mobile format (e.g. <code>0771234567</code>) or full international digits (e.g. <code>94771234567</code>).
                                </span>
                                {whatsappForm.errors.whatsapp_number && (
                                    <p className="text-xs text-rose-600 mt-1">{whatsappForm.errors.whatsapp_number}</p>
                                )}
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                    Storefront Floating Icon Visibility
                                </label>
                                <div className="pt-2 flex items-center gap-3">
                                    <label className="inline-flex items-center gap-2 cursor-pointer">
                                        <input
                                            type="checkbox"
                                            checked={whatsappForm.data.is_enabled}
                                            onChange={(e) => whatsappForm.setData('is_enabled', e.target.checked)}
                                            className="w-4 h-4 rounded text-emerald-600 focus:ring-emerald-500 border-slate-300 cursor-pointer"
                                        />
                                        <span className="text-xs font-medium text-slate-700 dark:text-slate-300">
                                            Enable floating WhatsApp button across all client pages
                                        </span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div>
                            <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                Pre-filled Client Greeting Message
                            </label>
                            <textarea
                                rows={2}
                                value={whatsappForm.data.whatsapp_message}
                                onChange={(e) => whatsappForm.setData('whatsapp_message', e.target.value)}
                                placeholder="Hello Aura Luxe, I would like to inquire about your products."
                                className="w-full text-xs px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:border-emerald-600 focus:ring-emerald-600"
                            />
                            <span className="text-[11px] text-slate-500 dark:text-slate-400 block mt-0.5">
                                When a customer clicks the WhatsApp button, this message is automatically typed in their WhatsApp chat.
                            </span>
                            {whatsappForm.errors.whatsapp_message && (
                                <p className="text-xs text-rose-600 mt-1">{whatsappForm.errors.whatsapp_message}</p>
                            )}
                        </div>

                        <div className="flex items-center justify-between gap-3 pt-3 border-t border-slate-200/80 dark:border-slate-800 flex-wrap">
                            <a
                                href={`https://wa.me/${whatsappForm.data.whatsapp_number.replace(/[^0-9]/g, '')}?text=${encodeURIComponent(whatsappForm.data.whatsapp_message)}`}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-emerald-300 hover:bg-emerald-50 dark:border-emerald-800 dark:hover:bg-emerald-950/40 text-emerald-800 dark:text-emerald-300 text-xs font-semibold transition-colors cursor-pointer"
                            >
                                <span>💬</span>
                                <span>Test WhatsApp Link (Opens Live Chat)</span>
                            </a>

                            <button
                                type="submit"
                                disabled={whatsappForm.processing}
                                className="inline-flex items-center gap-2 px-5 py-2.5 bg-emerald-700 hover:bg-emerald-800 disabled:opacity-50 text-white text-xs font-semibold rounded-lg shadow-sm transition-all cursor-pointer"
                            >
                                {whatsappForm.processing && (
                                    <svg className="animate-spin h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24">
                                        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                                        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                )}
                                <span>Save WhatsApp Settings</span>
                            </button>
                        </div>
                    </form>
                </div>

                {/* 3. Filter and Search Controls */}
                <div className="p-4 bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-2xs space-y-4">
                    <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
                        {/* Placement Filter Pills */}
                        <div className="flex items-center gap-1.5 overflow-x-auto pb-1 sm:pb-0">
                            {[
                                { id: 'all', label: 'All Items' },
                                { id: 'hero_slider', label: 'Hero Slider' },
                                { id: 'sub_banner_1', label: 'Sub-Banner 1 (Left)' },
                                { id: 'sub_banner_2', label: 'Sub-Banner 2 (Right)' },
                                { id: 'trust_badge', label: '🌿 Trust Seals (Badges)' },
                            ].map((tab) => (
                                <button
                                    key={tab.id}
                                    type="button"
                                    onClick={() => {
                                        setSelectedPlacement(tab.id);
                                        handleFilterChange(tab.id, selectedStatus, searchQuery);
                                    }}
                                    className={`px-3 py-1.5 rounded-lg text-xs font-medium whitespace-nowrap transition-colors cursor-pointer min-h-[36px] ${
                                        selectedPlacement === tab.id
                                            ? 'bg-rose-900 text-white'
                                            : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700'
                                    }`}
                                >
                                    {tab.label}
                                </button>
                            ))}
                        </div>

                        {/* Search Input */}
                        <form
                            onSubmit={(e) => {
                                e.preventDefault();
                                handleFilterChange(selectedPlacement, selectedStatus, searchQuery);
                            }}
                            className="flex items-center gap-2 w-full md:w-72"
                        >
                            <input
                                type="text"
                                placeholder="Search by title..."
                                value={searchQuery}
                                onChange={(e) => setSearchQuery(e.target.value)}
                                className="w-full text-xs px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-1 focus:ring-rose-500"
                            />
                            <button
                                type="submit"
                                className="px-3 py-2 bg-slate-800 hover:bg-slate-900 text-white text-xs font-semibold rounded-lg cursor-pointer"
                            >
                                Search
                            </button>
                        </form>
                    </div>

                    {/* Status Sub-Filters */}
                    <div className="flex items-center gap-2 pt-2 border-t border-slate-100 dark:border-slate-800 text-xs text-slate-500">
                        <span className="font-medium text-slate-700 dark:text-slate-300">Status:</span>
                        {['all', 'active', 'scheduled', 'expired', 'draft'].map((st) => (
                            <button
                                key={st}
                                type="button"
                                onClick={() => {
                                    setSelectedStatus(st);
                                    handleFilterChange(selectedPlacement, st, searchQuery);
                                }}
                                className={`capitalize hover:underline ${
                                    selectedStatus === st ? 'text-rose-700 dark:text-rose-400 font-bold underline' : ''
                                }`}
                            >
                                {st}
                            </button>
                        ))}
                    </div>
                </div>

                {/* 4. Banner List / Cards */}
                <div className="space-y-4">
                    {banners.map((b) => (
                        <div
                            key={b.id}
                            className="p-5 bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-2xs hover:shadow-md transition-all flex flex-col lg:flex-row items-start lg:items-center justify-between gap-6"
                        >
                            {/* Left: Thumbnail Preview & Metadata */}
                            <div className="flex items-start gap-4 min-w-0 flex-1">
                                {/* Visual Preview Thumbnail */}
                                <div
                                    className={`w-28 h-20 sm:w-36 sm:h-24 rounded-xl shrink-0 overflow-hidden flex items-center justify-center border border-slate-200 dark:border-slate-700 relative ${
                                        b.placement === 'trust_badge'
                                            ? 'bg-amber-50/80 dark:bg-amber-950/40'
                                            : b.theme_style === 'festive_rose'
                                            ? 'bg-gradient-to-r from-[#3B121C] to-[#6A1B30]'
                                            : b.theme_style === 'royal_gold'
                                            ? 'bg-gradient-to-r from-[#2B1F14] to-[#4D3A24]'
                                            : b.theme_style === 'dark_botanical'
                                            ? 'bg-gradient-to-r from-[#14231A] to-[#203D2F]'
                                            : 'bg-gradient-to-r from-[#29241E] to-[#423C32]'
                                    }`}
                                >
                                    {b.image_url ? (
                                        <img src={b.image_url} alt={b.title_en} className="w-full h-full object-cover" />
                                    ) : b.placement === 'trust_badge' ? (
                                        <div className="text-center p-2">
                                            <span className="text-3xl block">{b.theme_style || '🌿'}</span>
                                            <span className="text-[9px] uppercase tracking-wider text-amber-900 dark:text-amber-400 font-semibold block mt-1">
                                                Trust Seal
                                            </span>
                                        </div>
                                    ) : (
                                        <div className="text-center p-2 text-white/80">
                                            <span className="text-xl">✨</span>
                                            <span className="block text-[8px] font-mono uppercase tracking-widest mt-0.5 truncate">
                                                {b.placement}
                                            </span>
                                        </div>
                                    )}

                                    {/* Order badge */}
                                    <div className="absolute top-1 left-1 bg-black/60 backdrop-blur-xs text-white text-[9px] font-mono px-1.5 py-0.5 rounded">
                                        #{b.sort_order}
                                    </div>
                                </div>



                                {/* Text Details */}
                                <div className="min-w-0 flex-1">
                                    <div className="flex items-center gap-2 flex-wrap mb-1">
                                        <span className={`text-[10px] font-mono uppercase tracking-wider px-2 py-0.5 rounded font-semibold ${
                                            b.placement === 'trust_badge'
                                                ? 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300'
                                                : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300'
                                        }`}>
                                            {getPlacementName(b.placement)}
                                        </span>
                                        {renderStatusBadge(b)}
                                    </div>

                                    <h3 className="font-serif text-base sm:text-lg font-bold text-slate-900 dark:text-slate-100 truncate">
                                        {b.title_en}
                                    </h3>

                                    {b.title_si && (
                                        <p className="text-xs text-slate-500 dark:text-slate-400 truncate mt-0.5 font-light">
                                            {b.title_si}
                                        </p>
                                    )}

                                    {b.subtitle_en && (
                                        <p className="text-xs text-slate-600 dark:text-slate-300 line-clamp-1 mt-1 font-light">
                                            {b.subtitle_en}
                                        </p>
                                    )}

                                    {/* Schedule Timestamps */}
                                    <div className="mt-2.5 flex items-center gap-4 text-[11px] text-slate-500 dark:text-slate-400 flex-wrap font-mono">
                                        <span>
                                            Starts: {b.starts_at ? new Date(b.starts_at).toLocaleString() : 'Immediate'}
                                        </span>
                                        <span>•</span>
                                        <span>
                                            Ends: {b.ends_at ? new Date(b.ends_at).toLocaleString() : 'Never expires'}
                                        </span>
                                        {b.placement !== 'trust_badge' && (
                                            <>
                                                <span>•</span>
                                                <span className="text-rose-700 dark:text-rose-400">
                                                    Link: {b.cta_url || '/products'}
                                                </span>
                                            </>
                                        )}
                                    </div>
                                </div>


                            </div>

                            {/* Right: Actions */}
                            <div className="flex items-center gap-2.5 shrink-0 self-end lg:self-center w-full lg:w-auto justify-end border-t lg:border-t-0 pt-3 lg:pt-0 border-slate-100 dark:border-slate-800">
                                {/* Toggle Active Button */}
                                <button
                                    type="button"
                                    onClick={() => handleToggle(b.id)}
                                    className={`px-3 py-1.5 text-xs font-semibold rounded-lg transition-colors cursor-pointer min-h-[38px] ${
                                        b.is_active
                                            ? 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 hover:bg-emerald-100'
                                            : 'bg-slate-100 dark:bg-slate-800 text-slate-600 hover:bg-slate-200'
                                    }`}
                                >
                                    {b.is_active ? 'Active' : 'Disabled'}
                                </button>

                                {/* Edit Button */}
                                <button
                                    type="button"
                                    onClick={() => openEditModal(b)}
                                    className="px-3.5 py-1.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 text-xs font-semibold rounded-lg transition-colors cursor-pointer min-h-[38px]"
                                >
                                    {b.placement === 'trust_badge' ? 'Edit Badge' : 'Edit Schedule'}
                                </button>

                                {/* Delete Button */}
                                <button
                                    type="button"
                                    onClick={() => handleDelete(b.id)}
                                    className="px-3 py-1.5 bg-rose-50 hover:bg-rose-100 dark:bg-rose-950/40 dark:hover:bg-rose-950/70 text-rose-700 dark:text-rose-300 text-xs font-semibold rounded-lg transition-colors cursor-pointer min-h-[38px]"
                                >
                                    Delete
                                </button>
                            </div>
                        </div>
                    ))}

                    {banners.length === 0 && (
                        <div className="p-12 bg-white dark:bg-slate-900 rounded-2xl border border-dashed border-slate-300 dark:border-slate-700 text-center">
                            <span className="text-4xl block mb-2">✨</span>
                            <h3 className="font-serif text-lg font-bold text-slate-800 dark:text-slate-200">No Banners Found</h3>
                            <p className="text-xs text-slate-500 mt-1 max-w-sm mx-auto">
                                No banners match the selected filters. Click below to add a new storefront banner or trust badge.
                            </p>
                            <div className="flex items-center justify-center gap-3 mt-4">
                                <button
                                    type="button"
                                    onClick={openCreateTrustBadgeModal}
                                    className="px-4 py-2 bg-amber-800 text-white text-xs font-semibold rounded-lg cursor-pointer"
                                >
                                    + Add Trust Badge
                                </button>
                                <button
                                    type="button"
                                    onClick={openCreateModal}
                                    className="px-4 py-2 bg-rose-800 text-white text-xs font-semibold rounded-lg cursor-pointer"
                                >
                                    + Add Hero Banner
                                </button>
                            </div>
                        </div>
                    )}
                </div>
            </div>

            {/* 5. CREATE / EDIT BANNER MODAL */}
            {modalOpen && (
                <div className="fixed inset-0 z-50 overflow-y-auto bg-black/60 backdrop-blur-xs flex items-center justify-center p-4">
                    <div className="bg-white dark:bg-slate-900 rounded-2xl max-w-2xl w-full border border-slate-200 dark:border-slate-800 shadow-2xl overflow-hidden my-8">
                        {/* Modal Header */}
                        <div className="px-6 py-4 bg-slate-50 dark:bg-slate-800/60 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
                            <div className="flex items-center gap-2">
                                <span className="text-lg">{form.data.placement === 'trust_badge' ? '🌿' : '✨'}</span>
                                <h2 className="text-lg font-bold text-slate-900 dark:text-slate-100">
                                    {editingBanner
                                        ? (form.data.placement === 'trust_badge' ? 'Edit Trust Badge' : 'Edit Banner & Schedule')
                                        : (form.data.placement === 'trust_badge' ? 'Add New Trust Badge' : 'Create New Storefront Banner')}
                                </h2>
                            </div>
                            <button
                                type="button"
                                onClick={() => setModalOpen(false)}
                                className="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 text-xl font-bold p-1 cursor-pointer"
                            >
                                ✕
                            </button>
                        </div>

                        {/* Modal Form */}
                        <form onSubmit={handleFormSubmit} className="p-6 space-y-5 max-h-[80vh] overflow-y-auto custom-scrollbar">
                            {/* Placement & Theme Style */}
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
                                        Banner / Element Placement *
                                    </label>
                                    <select
                                        value={form.data.placement}
                                        onChange={(e) => {
                                            const p = e.target.value as any;
                                            form.setData('placement', p);
                                            if (p === 'trust_badge' && !form.data.theme_style.includes('🌿')) {
                                                form.setData('theme_style', '🌿');
                                            }
                                        }}
                                        className="w-full text-xs px-3 py-2.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:ring-1 focus:ring-rose-500"
                                    >
                                        <option value="hero_slider">Hero Swapper (Top Gallery Slider)</option>
                                        <option value="sub_banner_1">Sub-Banner 1 (Bottom Left Promotional Card)</option>
                                        <option value="sub_banner_2">Sub-Banner 2 (Bottom Right Product Spotlight)</option>
                                        <option value="trust_badge">Trust Badge / Assurance Seal (Storefront Strip)</option>
                                    </select>
                                </div>

                                {form.data.placement === 'trust_badge' ? (
                                    <div>
                                        <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
                                            Badge Icon / Emoji Symbol *
                                        </label>
                                        <div className="flex items-center gap-1.5 mb-2 flex-wrap">
                                            {['🌿', '🚚', '👑', '🐰', '✨', '🔬', '💚', '🌸', '💧', '🛡️'].map((emoji) => (
                                                <button
                                                    key={emoji}
                                                    type="button"
                                                    onClick={() => form.setData('theme_style', emoji)}
                                                    className={`w-7 h-7 rounded-lg text-base flex items-center justify-center border transition-all cursor-pointer ${
                                                        form.data.theme_style === emoji
                                                            ? 'border-amber-600 bg-amber-50 dark:bg-amber-950/60 ring-2 ring-amber-500 scale-105'
                                                            : 'border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800'
                                                    }`}
                                                >
                                                    {emoji}
                                                </button>
                                            ))}
                                        </div>
                                        <input
                                            type="text"
                                            placeholder="Enter emoji or icon character"
                                            value={form.data.theme_style}
                                            onChange={(e) => form.setData('theme_style', e.target.value)}
                                            className="w-full text-xs px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 font-mono"
                                        />
                                    </div>
                                ) : (
                                    <div>
                                        <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
                                            Backdrop Theme Style
                                        </label>
                                        <select
                                            value={form.data.theme_style}
                                            onChange={(e) => form.setData('theme_style', e.target.value)}
                                            className="w-full text-xs px-3 py-2.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:ring-1 focus:ring-rose-500"
                                        >
                                            <option value="festive_rose">Festive Rose (Spa Ceylon Deep Crimson)</option>
                                            <option value="royal_gold">Royal Gold & Amber</option>
                                            <option value="dark_botanical">Dark Botanical Forest</option>
                                            <option value="ivory_cream">Warm Ivory & Sand</option>
                                        </select>
                                    </div>
                                )}
                            </div>

                            {/* Title EN & Title SI */}
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
                                        {form.data.placement === 'trust_badge' ? 'Badge Title (English) *' : 'Title (English) *'}
                                    </label>
                                    <input
                                        type="text"
                                        required
                                        placeholder={form.data.placement === 'trust_badge' ? 'e.g. 100% Ceylon Botanicals' : 'e.g. ONLINE FESTIVE GALLERY IS NOW LIVE'}
                                        value={form.data.title_en}
                                        onChange={(e) => form.setData('title_en', e.target.value)}
                                        className="w-full text-xs px-3 py-2.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:ring-1 focus:ring-rose-500"
                                    />
                                    {form.errors.title_en && (
                                        <span className="text-[11px] text-rose-600 mt-1 block">{form.errors.title_en}</span>
                                    )}
                                </div>

                                <div>
                                    <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
                                        {form.data.placement === 'trust_badge' ? 'Badge Title (Sinhala / සිංහල)' : 'Title (Sinhala / සිංහල)'}
                                    </label>
                                    <input
                                        type="text"
                                        placeholder={form.data.placement === 'trust_badge' ? 'e.g. 100% ලංකාවේ ශාකසාර' : 'e.g. රාජකීය උත්සව ගැලරිය දැන් විවෘතයි'}
                                        value={form.data.title_si}
                                        onChange={(e) => form.setData('title_si', e.target.value)}
                                        className="w-full text-xs px-3 py-2.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:ring-1 focus:ring-rose-500"
                                    />
                                </div>
                            </div>

                            {/* Subtitle EN & SI */}
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
                                        {form.data.placement === 'trust_badge' ? 'Badge Description (English)' : 'Subtitle / Tagline (English)'}
                                    </label>
                                    <textarea
                                        rows={2}
                                        placeholder={form.data.placement === 'trust_badge' ? 'e.g. No parabens, sulfates or alcohol' : 'e.g. Step into our immersive Ceylon botanical wonderland...'}
                                        value={form.data.subtitle_en}
                                        onChange={(e) => form.setData('subtitle_en', e.target.value)}
                                        className="w-full text-xs px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:ring-1 focus:ring-rose-500"
                                    />
                                </div>

                                <div>
                                    <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
                                        {form.data.placement === 'trust_badge' ? 'Badge Description (Sinhala / සිංහල)' : 'Subtitle (Sinhala / සිංහල)'}
                                    </label>
                                    <textarea
                                        rows={2}
                                        placeholder={form.data.placement === 'trust_badge' ? 'e.g. පැරබන් සහ සල්ෆේට් රහිතයි' : 'e.g. සුවිශේෂී රාජකීය ආයුර්වේද සත්කාර එකතුව...'}
                                        value={form.data.subtitle_si}
                                        onChange={(e) => form.setData('subtitle_si', e.target.value)}
                                        className="w-full text-xs px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:ring-1 focus:ring-rose-500"
                                    />
                                </div>
                            </div>

                            {/* CTA Text & Link (Only show if not trust_badge, or optional for trust_badge) */}
                            <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                <div>
                                    <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
                                        CTA Button (EN)
                                    </label>
                                    <input
                                        type="text"
                                        placeholder="EXPERIENCE NOW"
                                        value={form.data.cta_text_en}
                                        onChange={(e) => form.setData('cta_text_en', e.target.value)}
                                        className="w-full text-xs px-3 py-2.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:ring-1 focus:ring-rose-500"
                                    />
                                </div>

                                <div>
                                    <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
                                        CTA Button (SI)
                                    </label>
                                    <input
                                        type="text"
                                        placeholder="දැන්ම නරඹන්න"
                                        value={form.data.cta_text_si}
                                        onChange={(e) => form.setData('cta_text_si', e.target.value)}
                                        className="w-full text-xs px-3 py-2.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:ring-1 focus:ring-rose-500"
                                    />
                                </div>

                                <div>
                                    <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
                                        Target URL
                                    </label>
                                    <input
                                        type="text"
                                        placeholder="/products"
                                        value={form.data.cta_url}
                                        onChange={(e) => form.setData('cta_url', e.target.value)}
                                        className="w-full text-xs px-3 py-2.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:ring-1 focus:ring-rose-500"
                                    />
                                </div>
                            </div>

                            {/* Scheduling Section: Starts At & Ends At */}
                            <div className="p-4 bg-amber-50/60 dark:bg-amber-950/20 rounded-xl border border-amber-200 dark:border-amber-900/40 space-y-3">
                                <div className="flex items-center gap-2">
                                    <span className="text-amber-700 dark:text-amber-400 font-bold text-xs uppercase tracking-wider">
                                        ⏰ Campaign Scheduling
                                    </span>
                                    <span className="text-[11px] text-slate-500">(Leave empty to run immediately with no expiration)</span>
                                </div>

                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <label className="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">
                                            Start Date & Time (Schedule Launch)
                                        </label>
                                        <input
                                            type="datetime-local"
                                            value={form.data.starts_at}
                                            onChange={(e) => form.setData('starts_at', e.target.value)}
                                            className="w-full text-xs px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100"
                                        />
                                    </div>

                                    <div>
                                        <label className="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">
                                            End Date & Time (Expiry)
                                        </label>
                                        <input
                                            type="datetime-local"
                                            value={form.data.ends_at}
                                            onChange={(e) => form.setData('ends_at', e.target.value)}
                                            className="w-full text-xs px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100"
                                        />
                                        {form.errors.ends_at && (
                                            <span className="text-[11px] text-rose-600 mt-1 block">{form.errors.ends_at}</span>
                                        )}
                                    </div>
                                </div>


                            </div>

                            {/* Image Upload & Sort Order */}
                            <div className="grid grid-cols-1 sm:grid-cols-3 gap-4 items-center">
                                <div className="sm:col-span-2">
                                    <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
                                        {form.data.placement === 'trust_badge' ? 'Upload Custom Badge Icon (Optional)' : 'Upload Custom Banner Artwork (Optional)'}
                                    </label>
                                    <input
                                        type="file"
                                        accept="image/*"
                                        onChange={(e) => form.setData('image', e.target.files?.[0] || null)}
                                        className="w-full text-xs file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-slate-100 dark:file:bg-slate-800 file:text-slate-700 dark:file:text-slate-300 text-slate-500 cursor-pointer"
                                    />
                                    {form.errors.image && (
                                        <span className="text-[11px] text-rose-600 mt-1 block">{form.errors.image}</span>
                                    )}
                                </div>

                                <div>
                                    <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
                                        Display Order
                                    </label>
                                    <input
                                        type="number"
                                        min="1"
                                        value={form.data.sort_order}
                                        onChange={(e) => form.setData('sort_order', parseInt(e.target.value) || 1)}
                                        className="w-full text-xs px-3 py-2.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100"
                                    />
                                </div>
                            </div>

                            {/* Is Active Checkbox */}
                            <div className="flex items-center gap-2 pt-2">
                                <input
                                    type="checkbox"
                                    id="is_active"
                                    checked={form.data.is_active}
                                    onChange={(e) => form.setData('is_active', e.target.checked)}
                                    className="w-4 h-4 rounded text-rose-600 focus:ring-rose-500 border-slate-300 cursor-pointer"
                                />
                                <label htmlFor="is_active" className="text-xs font-semibold text-slate-700 dark:text-slate-300 cursor-pointer">
                                    {form.data.placement === 'trust_badge' ? 'Badge is Live & Active (Uncheck to hide from storefront)' : 'Banner is Active (Uncheck to keep as draft)'}
                                </label>
                            </div>

                            {/* Modal Footer Actions */}
                            <div className="pt-4 border-t border-slate-200 dark:border-slate-800 flex items-center justify-end gap-3">
                                <button
                                    type="button"
                                    onClick={() => setModalOpen(false)}
                                    className="px-4 py-2.5 border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-300 text-xs font-semibold rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 cursor-pointer min-h-[44px]"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={form.processing}
                                    className="px-6 py-2.5 bg-rose-800 hover:bg-rose-900 text-white text-xs font-semibold rounded-lg shadow-sm transition-colors cursor-pointer min-h-[44px] flex items-center gap-2"
                                >
                                    {form.processing && (
                                        <svg className="animate-spin h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24">
                                            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                                            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                    )}
                                    <span>
                                        {editingBanner
                                            ? (form.data.placement === 'trust_badge' ? 'Save Badge Changes' : 'Save Changes & Schedule')
                                            : (form.data.placement === 'trust_badge' ? 'Publish Trust Badge' : 'Create & Schedule Banner')}
                                    </span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AdminLayout>
    );
}
