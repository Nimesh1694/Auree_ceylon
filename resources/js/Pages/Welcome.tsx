import ProductCard from '@/Components/ProductCard';
import HeroSlider from '@/Components/Storefront/HeroSlider';
import SubBanners from '@/Components/Storefront/SubBanners';
import { useTranslation } from '@/hooks/useTranslation';
import PublicLayout from '@/Layouts/PublicLayout';
import { Banner, Category, ExperienceReviews, ExperienceVideo, Product } from '@/types';
import { Head, Link } from '@inertiajs/react';
import React, { useMemo, useState } from 'react';

interface Props {
    categories: Category[];
    allProducts?: Product[];
    featuredProducts: Product[];
    bestSellers: Product[];
    newArrivals: Product[];
    heroBanners?: Banner[];
    subBanner1?: Banner | null;
    subBanner2?: Banner | null;
    trustBadges?: Banner[];
    experienceMode?: 'reviews' | 'video';
    experienceVideo?: ExperienceVideo;
    experienceReviews?: ExperienceReviews;
}

function getEmbedUrl(url?: string, shouldAutoplay: boolean = true): string | null {
    if (!url) return null;
    const ytMatch = url.match(/(?:youtu\.be\/|youtube\.com\/(?:embed\/|v\/|watch\?v=|watch\?.+&v=))([\w-]{11})/);
    if (ytMatch) {
        const id = ytMatch[1];
        const auto = shouldAutoplay ? 'autoplay=1' : 'autoplay=0';
        return `https://www.youtube-nocookie.com/embed/${id}?${auto}&mute=1&controls=0&playsinline=1&loop=1&playlist=${id}&rel=0&modestbranding=1&iv_load_policy=3&disablekb=1&fs=0&cc_load_policy=0&vq=large&enablejsapi=1`;
    }
    const vimeoMatch = url.match(/vimeo\.com\/(?:channels\/(?:\w+\/)?|groups\/(?:[^\/]*)\/videos\/|album\/(?:\d+)\/video\/|video\/|)(\d+)/);
    if (vimeoMatch) {
        const id = vimeoMatch[1];
        const auto = shouldAutoplay ? 'autoplay=1' : 'autoplay=0';
        return `https://player.vimeo.com/video/${id}?background=1&${auto}&muted=1&loop=1&autopause=0&controls=0&title=0&byline=0&portrait=0&playsinline=1&quality=480p`;
    }
    return url;
}

export default function Welcome({
    categories,
    allProducts = [],
    featuredProducts,
    bestSellers,
    newArrivals,
    heroBanners,
    subBanner1,
    subBanner2,
    trustBadges = [],
    experienceMode = 'reviews',
    experienceVideo,
    experienceReviews,
}: Props) {
    const { t, locale } = useTranslation();
    const [selectedCategory, setSelectedCategory] = useState<string>('all');

    // Experience Video Autoplay & In-View Detection (Autoplay only when in viewport, Pause when not, 480p)
    const isAutoplayEnabled = experienceVideo?.autoplay !== false;
    const videoContainerRef = React.useRef<HTMLDivElement | null>(null);
    const html5VideoRef = React.useRef<HTMLVideoElement | null>(null);
    const iframeRef = React.useRef<HTMLIFrameElement | null>(null);
    const [hasCustomerSeen, setHasCustomerSeen] = useState(false);

    const sendIframeCommand = (command: 'playVideo' | 'pauseVideo') => {
        if (!iframeRef.current || !iframeRef.current.contentWindow) return;
        try {
            // YouTube Player API postMessage
            iframeRef.current.contentWindow.postMessage(
                JSON.stringify({ event: 'command', func: command, args: '' }),
                '*'
            );
            if (command === 'playVideo') {
                // Enforce 480p (large) quality
                iframeRef.current.contentWindow.postMessage(
                    JSON.stringify({ event: 'command', func: 'setPlaybackQuality', args: ['large'] }),
                    '*'
                );
            }
            // Vimeo Player API postMessage
            const vimeoMethod = command === 'playVideo' ? 'play' : 'pause';
            iframeRef.current.contentWindow.postMessage(
                JSON.stringify({ method: vimeoMethod }),
                '*'
            );
        } catch {
            // Cross-origin safe fallback
        }
    };

    React.useEffect(() => {
        if (experienceMode !== 'video' || !isAutoplayEnabled) return;

        const observer = new IntersectionObserver(
            (entries) => {
                const entry = entries[0];
                if (entry && entry.isIntersecting) {
                    setHasCustomerSeen(true);
                    if (html5VideoRef.current) {
                        html5VideoRef.current.play().catch(() => {});
                    }
                    sendIframeCommand('playVideo');
                } else if (entry && !entry.isIntersecting) {
                    if (html5VideoRef.current) {
                        html5VideoRef.current.pause();
                    }
                    sendIframeCommand('pauseVideo');
                }
            },
            { threshold: 0.15 }
        );

        if (videoContainerRef.current) {
            observer.observe(videoContainerRef.current);
        }

        return () => observer.disconnect();
    }, [experienceMode, isAutoplayEnabled, hasCustomerSeen]);

    // Default fallback trust seals if none configured
    const defaultTrustBadges: Banner[] = [
        {
            id: 971,
            title_en: '100% Ceylon Botanicals',
            title_si: '100% පිරිසිදු ශාකසාර',
            subtitle_en: 'No parabens, sulfates or alcohol',
            subtitle_si: 'හානිකර රසායනික ද්‍රව්‍ය නැත',
            theme_style: '🌿',
            placement: 'trust_badge',
            is_active: true,
            sort_order: 1,
        },
        {
            id: 972,
            title_en: 'Islandwide COD Delivery',
            title_si: 'දිවයින පුරා COD බෙදාහැරීම',
            subtitle_en: 'Pay at your doorstep across Sri Lanka',
            subtitle_si: 'භාණ්ඩ ලැබුණු පසු මුදල් ගෙවන්න',
            theme_style: '🚚',
            placement: 'trust_badge',
            is_active: true,
            sort_order: 2,
        },
        {
            id: 973,
            title_en: 'Royal Palace Formula',
            title_si: 'රාජකීය සූත්‍රය',
            subtitle_en: 'Ancient Ceylon beauty science',
            subtitle_si: 'පාරම්පරික ආයුර්වේද විද්‍යාව',
            theme_style: '👑',
            placement: 'trust_badge',
            is_active: true,
            sort_order: 3,
        },
        {
            id: 974,
            title_en: '100% Cruelty-Free',
            title_si: 'සත්ව හිංසනයෙන් තොරයි',
            subtitle_en: 'Never tested on animals & vegetarian',
            subtitle_si: 'සත්ත්ව පරීක්ෂණ නොමැත',
            theme_style: '🐰',
            placement: 'trust_badge',
            is_active: true,
            sort_order: 4,
        },
    ];

    const activeTrustBadges = trustBadges && trustBadges.length > 0 ? trustBadges : defaultTrustBadges;

    // Combine all active products to allow smart client-side filtering by category
    const productList = useMemo(() => {
        if (allProducts && allProducts.length > 0) {
            return allProducts;
        }
        const map = new Map<number, Product>();
        [...featuredProducts, ...bestSellers, ...newArrivals].forEach((p) => {
            if (!map.has(p.id)) map.set(p.id, p);
        });
        return Array.from(map.values());
    }, [allProducts, featuredProducts, bestSellers, newArrivals]);

    // Filter products based on selected category (managed in /admin/categories)
    const filteredProducts = useMemo(() => {
        if (selectedCategory === 'all') {
            return productList.slice(0, 8);
        }
        return productList.filter((product) => product.category?.slug === selectedCategory);
    }, [selectedCategory, productList]);

    return (
        <PublicLayout>
            <Head title={t('app_name', 'AURA LUXE | Royal Ceylon Botanicals & Luxury Ayurveda Skincare')} />

            {/* 1. TOP SWAPPABLE HERO FESTIVE SLIDER (Spa Ceylon Hero Gallery) */}
            <HeroSlider banners={heroBanners} />

            {/* 2. SUB-BANNERS (2 Promotional Cards from Spa Ceylon Homepage) */}
            <SubBanners subBanner1={subBanner1} subBanner2={subBanner2} />

            {/* 3. DYNAMIC BOTANICAL FORMULATIONS (Fully Managed by Managers via /admin/categories) */}
            <section id="categories-section" className="py-16 sm:py-20 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div className="text-center max-w-2xl mx-auto mb-10">
                    <span className="text-[11px] uppercase tracking-[0.24em] text-amber-900 font-sans font-semibold block mb-2">
                        {locale === 'si' ? 'සුවිශේෂී සත්කාර' : 'Botanical Formulations'}
                    </span>
                    <h2 className="font-serif text-2xl sm:text-4xl text-stone-900 font-medium tracking-tight">
                        {locale === 'si' ? 'රාජකීය රූපලාවණ්‍ය එකතුව' : 'Curated Botanical Formulations'}
                    </h2>
                    <p className="mt-3 text-xs sm:text-sm text-stone-500 font-light">
                        {locale === 'si'
                            ? 'සමේ සෞඛ්‍යය හා ප්‍රබෝධය උදෙසා විශේෂඥව සැකසූ පිරිසිදු ශාකසාර එකතුවෙන් තෝරන්න.'
                            : 'Explore our ayurvedic skincare rituals by botanical collection, handcrafted in Sri Lanka.'}
                    </p>
                </div>

                {/* Filter Pills - Fully dynamic from Admin Manager Categories */}
                <div className="flex items-center justify-center gap-2 sm:gap-3 flex-wrap mb-12">
                    <button
                        type="button"
                        onClick={() => setSelectedCategory('all')}
                        className={`px-4 sm:px-5 py-2.5 rounded-full text-xs uppercase tracking-[0.14em] font-medium transition-all duration-300 min-h-[44px] cursor-pointer ${
                            selectedCategory === 'all'
                                ? 'bg-[#1A1816] text-[#FAF8F5] shadow-md border border-amber-900/40'
                                : 'bg-white text-stone-700 border border-[#EAE3D9] hover:border-amber-700/60 hover:bg-[#FAF6F0]'
                        }`}
                    >
                        {locale === 'si' ? 'සියලුම සත්කාර' : 'All Formulations'}
                    </button>

                    {categories.map((cat) => {
                        const isSelected = selectedCategory === cat.slug;
                        const catName = locale === 'si' && cat.name_si ? cat.name_si : cat.name_en;
                        return (
                            <button
                                key={cat.id}
                                type="button"
                                onClick={() => setSelectedCategory(cat.slug)}
                                className={`px-4 sm:px-5 py-2.5 rounded-full text-xs uppercase tracking-[0.14em] font-medium transition-all duration-300 min-h-[44px] cursor-pointer ${
                                    isSelected
                                        ? 'bg-[#1A1816] text-[#FAF8F5] shadow-md border border-amber-900/40'
                                        : 'bg-white text-stone-700 border border-[#EAE3D9] hover:border-amber-700/60 hover:bg-[#FAF6F0]'
                                }`}
                            >
                                <span>{catName}</span>
                                {cat.products_count !== undefined && (
                                    <span className="ml-1.5 text-[11px] opacity-70">
                                        ({cat.products_count})
                                    </span>
                                )}
                            </button>
                        );
                    })}
                </div>

                {/* Filtered Products Grid */}
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 sm:gap-7">
                    {filteredProducts.slice(0, 8).map((product) => (
                        <ProductCard key={product.id} product={product} />
                    ))}
                </div>

                {filteredProducts.length === 0 && (
                    <div className="text-center py-12 bg-white rounded-2xl border border-dashed border-stone-300">
                        <p className="text-sm text-stone-500 font-serif">
                            {locale === 'si' ? 'මෙම ප්‍රවර්ගය සඳහා තවමත් නිෂ්පාදන ඇතුළත් කර නොමැත.' : 'No products found in this category yet.'}
                        </p>
                        <button
                            type="button"
                            onClick={() => setSelectedCategory('all')}
                            className="mt-3 text-xs text-amber-900 font-semibold underline underline-offset-4 cursor-pointer"
                        >
                            {locale === 'si' ? 'සියලුම සත්කාර බලන්න' : 'View All Formulations'}
                        </button>
                    </div>
                )}
            </section>

            {/* 4. CURATED COLLECTIONS / EDITORIAL TILES */}
            {categories.length > 0 && (
                <section className="py-16 sm:py-20 bg-[#F7F2EB] border-y border-[#EAE0D3]">
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        <div className="flex flex-col sm:flex-row sm:items-end justify-between mb-10 gap-4">
                            <div>
                                <span className="text-[11px] uppercase tracking-[0.22em] text-amber-900 font-sans font-semibold block mb-1">
                                    {locale === 'si' ? 'රාජකීය එකතු' : 'Royal Ranges'}
                                </span>
                                <h2 className="font-serif text-2xl sm:text-3xl text-stone-900 font-medium">
                                    {locale === 'si' ? 'සුවිශේෂී රූපලාවණ්‍ය එකතුව' : 'Curated Botanical Collections'}
                                </h2>
                            </div>
                            <Link
                                href="/categories"
                                className="text-xs uppercase tracking-[0.16em] text-stone-700 hover:text-amber-900 font-semibold flex items-center gap-1.5 transition-colors self-start sm:self-auto"
                            >
                                <span>{t('view_all', 'View All Collections')}</span>
                                <span>→</span>
                            </Link>
                        </div>

                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                            {categories.map((cat, idx) => (
                                <Link
                                    key={cat.id}
                                    href={`/categories/${cat.slug}`}
                                    className="group relative rounded-2xl bg-white p-6 sm:p-8 border border-[#E8DFC0]/70 hover:border-amber-700/50 shadow-xs hover:shadow-lg transition-all duration-300 flex flex-col justify-between overflow-hidden"
                                >
                                    <div className="absolute top-0 right-0 w-24 h-24 bg-amber-50 rounded-bl-full -z-0 opacity-40 group-hover:opacity-80 transition-opacity"></div>

                                    <div className="relative z-10">
                                        <div className="text-2xl mb-4">
                                            {idx === 0 ? '🌸' : idx === 1 ? '✨' : '🌿'}
                                        </div>
                                        <h3 className="font-serif text-xl text-stone-900 group-hover:text-amber-900 font-medium transition-colors">
                                            {locale === 'si' && cat.name_si ? cat.name_si : cat.name_en}
                                        </h3>
                                        <p className="text-xs text-stone-500 mt-2.5 line-clamp-2 leading-relaxed font-light">
                                            {locale === 'si' && cat.description_si
                                                ? cat.description_si
                                                : cat.description_en || 'Pure botanical wellness handcrafted with Sri Lankan heritage extracts.'}
                                        </p>
                                    </div>

                                    <div className="relative z-10 mt-6 pt-4 border-t border-stone-100 flex items-center justify-between">
                                        <span className="text-[11px] text-amber-900 font-semibold tracking-[0.16em] uppercase group-hover:translate-x-1 transition-transform inline-block">
                                            Explore Collection →
                                        </span>
                                    </div>
                                </Link>
                            ))}
                        </div>
                    </div>
                </section>
            )}

            {/* 5. BEST SELLERS SECTION */}
            {bestSellers.length > 0 && (
                <section className="py-16 sm:py-20 bg-white border-t border-[#EAE3D9]">
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        <div className="flex flex-col sm:flex-row sm:items-end justify-between mb-10 gap-4">
                            <div>
                                <span className="text-[11px] uppercase tracking-[0.22em] text-amber-900 font-sans font-semibold block mb-1">
                                    {locale === 'si' ? 'ජනප්‍රියතම තේරීම්' : 'Beloved Formulations'}
                                </span>
                                <h2 className="font-serif text-2xl sm:text-3xl text-stone-900 font-medium">
                                    {locale === 'si' ? 'වැඩිපුරම අලෙවි වන නිෂ්පාදන' : 'Most Loved by Sri Lanka'}
                                </h2>
                            </div>
                            <Link
                                href="/products"
                                className="text-xs uppercase tracking-[0.16em] text-stone-700 hover:text-amber-900 font-semibold flex items-center gap-1.5 transition-colors self-start sm:self-auto"
                            >
                                <span>{t('view_all', 'View All Best Sellers')}</span>
                                <span>→</span>
                            </Link>
                        </div>

                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                            {bestSellers.slice(0, 4).map((product) => (
                                <ProductCard key={product.id} product={product} />
                            ))}
                        </div>
                    </div>
                </section>
            )}

            {/* 7. STOREFRONT EXPERIENCE SECTION (Mode 1: Verified Reviews vs Mode 2: Featured Video) */}
            <section id="experience-section" className="py-16 sm:py-24 bg-[#FAF6F0] border-t border-[#EAE0D3]">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    {experienceMode === 'video' ? (
                        /* OPTION 2: FEATURED BRAND VIDEO */
                        <div>
                            <div className="text-center max-w-3xl mx-auto mb-10 sm:mb-12">
                                <span className="text-[11px] uppercase tracking-[0.24em] text-amber-900 font-sans font-semibold block mb-2">
                                    {locale === 'si' && experienceVideo?.eyebrow_si ? experienceVideo.eyebrow_si : (experienceVideo?.eyebrow_en || 'THE ROYAL CEYLON RITUAL')}
                                </span>
                                <h2 className="font-serif text-2xl sm:text-4xl text-stone-900 font-medium tracking-tight">
                                    {locale === 'si' && experienceVideo?.title_si ? experienceVideo.title_si : (experienceVideo?.title_en || 'Behind the Royal Ceylon Botanical Science')}
                                </h2>
                                {(experienceVideo?.description_en || experienceVideo?.description_si) && (
                                    <p className="mt-3.5 text-xs sm:text-sm text-stone-600 font-light max-w-2xl mx-auto leading-relaxed">
                                        {locale === 'si' && experienceVideo.description_si ? experienceVideo.description_si : experienceVideo.description_en}
                                    </p>
                                )}
                            </div>

                            {/* Luxury 16:9 Ambient Video (Silent Background Reel, Zero Icons, Zero Buttons) */}
                            <div ref={videoContainerRef} className="max-w-4xl mx-auto">
                                <div className="relative w-full aspect-video rounded-3xl overflow-hidden shadow-2xl border-2 border-[#EAE0D3] bg-stone-950 pointer-events-none select-none">
                                    {experienceVideo?.video_type === 'mp4' || (!getEmbedUrl(experienceVideo?.video_url) && experienceVideo?.video_url?.endsWith('.mp4')) ? (
                                        <video
                                            ref={html5VideoRef}
                                            src={experienceVideo?.video_url}
                                            poster={experienceVideo?.poster_image ? (experienceVideo.poster_image.startsWith('/') ? experienceVideo.poster_image : `/storage/${experienceVideo.poster_image}`) : undefined}
                                            autoPlay={isAutoplayEnabled}
                                            muted
                                            loop
                                            playsInline
                                            className="w-full h-full object-cover pointer-events-none"
                                        />
                                    ) : (
                                        <div className="absolute inset-0 flex items-center justify-center overflow-hidden pointer-events-none">
                                            <iframe
                                                ref={iframeRef}
                                                key="ambient-video-player"
                                                src={getEmbedUrl(experienceVideo?.video_url, isAutoplayEnabled) || 'https://www.youtube-nocookie.com/embed/2e_Z2WzY9s4?autoplay=1&mute=1&controls=0&playsinline=1&loop=1&playlist=2e_Z2WzY9s4&rel=0&modestbranding=1&iv_load_policy=3&disablekb=1&fs=0&vq=large&enablejsapi=1'}
                                                title="Royal Ceylon Botanical Experience"
                                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                                loading="lazy"
                                                className="w-[118%] h-[118%] max-w-none border-0 pointer-events-none select-none"
                                                tabIndex={-1}
                                            />
                                        </div>
                                    )}
                                </div>

                                {/* Optional CTA Button */}
                                {(experienceVideo?.cta_text_en || experienceVideo?.cta_text_si) && (
                                    <div className="mt-8 sm:mt-10 text-center">
                                        <Link
                                            href={experienceVideo?.cta_url || '/products'}
                                            className="inline-flex items-center gap-2 bg-[#1C1917] hover:bg-[#9A4E22] text-[#FDFBF7] px-8 py-3.5 rounded-full text-xs font-semibold uppercase tracking-widest transition-all shadow-md active:scale-95"
                                        >
                                            <span>{locale === 'si' && experienceVideo.cta_text_si ? experienceVideo.cta_text_si : experienceVideo.cta_text_en}</span>
                                            <span>→</span>
                                        </Link>
                                    </div>
                                )}
                            </div>
                        </div>
                    ) : (
                        /* OPTION 1: USER COMMENTS & VERIFIED REVIEWS */
                        <div>
                            <div className="text-center max-w-2xl mx-auto mb-12">
                                <span className="text-[11px] uppercase tracking-[0.22em] text-amber-900 font-sans font-semibold block mb-2">
                                    {locale === 'si' && experienceReviews?.eyebrow_si ? experienceReviews.eyebrow_si : (experienceReviews?.eyebrow_en || 'Verified Ceylon Experiences')}
                                </span>
                                <h2 className="font-serif text-2xl sm:text-3xl text-stone-900 font-medium">
                                    {locale === 'si' && experienceReviews?.title_si ? experienceReviews.title_si : (experienceReviews?.title_en || 'Loved by Discerning Women & Men Across Sri Lanka')}
                                </h2>
                            </div>

                            <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                                {(experienceReviews?.items || [
                                    {
                                        name: 'Nipuni Senanayake',
                                        location: 'Kandy • Verified Buyer',
                                        rating: 5,
                                        comment: '"The Saffron Radiance Serum has transformed my skin texture completely. The authentic herbal aroma reminds me of royal Sri Lankan spas. Plus, COD courier delivered to Kandy in just 2 days!"',
                                    },
                                    {
                                        name: 'Dr. Hansi Fernando',
                                        location: 'Colombo 07 • Verified Buyer',
                                        rating: 5,
                                        comment: '"AURA LUXE products feel genuinely luxurious. No sticky residue or harsh synthetic perfume. Knowing it’s 100% vegetarian and free from parabens gives me total peace of mind."',
                                    },
                                    {
                                        name: 'Malik Jayawardena',
                                        location: 'Galle • Verified Buyer',
                                        rating: 5,
                                        comment: '"The Cash on Delivery service was flawless. The package arrived in regal packaging with botanical care notes. Truly on par with international luxury brands."',
                                    },
                                ]).map((rev, idx) => (
                                    <div key={idx} className="bg-white rounded-2xl p-6 border border-[#EAE3D9] shadow-xs flex flex-col justify-between">
                                        <div>
                                            <div className="text-amber-600 text-sm mb-3">{'★'.repeat(rev.rating || 5)}</div>
                                            <p className="text-xs sm:text-sm text-stone-700 leading-relaxed font-serif italic">
                                                {rev.comment}
                                            </p>
                                        </div>
                                        <div className="mt-5 pt-4 border-t border-stone-100 flex items-center justify-between text-xs">
                                            <span className="font-medium text-stone-900">{rev.name}</span>
                                            <span className="text-stone-400 font-sans text-[10px]">{rev.location}</span>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            </section>

            {/* 8. BRAND PHILOSOPHY / THE ROYAL BOTANICAL PROMISE */}
            <section className="bg-[#181614] text-[#FAF8F5] border-t border-amber-900/40 py-16 sm:py-20">
                <div className="max-w-4xl mx-auto px-4 text-center">
                    <div className="w-16 h-16 mx-auto rounded-full bg-amber-950/80 border border-amber-700/50 flex items-center justify-center mb-6">
                        <span className="text-3xl">👑</span>
                    </div>

                    <span className="text-[11px] uppercase tracking-[0.24em] text-amber-400 font-sans font-semibold block mb-3">
                        {locale === 'si' ? 'අපගේ රාජකීය ප්‍රතිඥාව' : 'The Aura Luxe Royal Promise'}
                    </span>

                    <h2 className="font-serif text-2xl sm:text-4xl text-[#FAF8F5] font-medium leading-relaxed">
                        {locale === 'si'
                            ? 'සමේ ස්වභාවික සුවය හා දීප්තිය උදෙසා කැපවූ පිරිසිදු ලාංකික ශාකසාර විද්‍යාව'
                            : 'Nourishing botanical science, ethically crafted for true transcendent radiance.'}
                    </h2>

                    <p className="mt-5 text-xs sm:text-sm text-[#C9BFB5] max-w-2xl mx-auto leading-relaxed font-light">
                        {locale === 'si'
                            ? 'කිසිදු හානිකර පැරබෙන්, සල්ෆේට් හෝ කෘත්‍රිම සුවඳකාරක නොමැතිව, තිරසාර ලෙස ලබාගත් ශ්‍රී ලාංකික ස්වභාවික ශාකසාරයන්ගෙන් පමණක් අපගේ නිෂ්පාදන සකස් කර ඇත.'
                            : 'Every elixir is ethically formulated in Sri Lanka with wild-crafted herbs, organic virgin coconut oil, and cold-pressed botanical seeds. Honoring centuries of island tradition while safeguarding your skin’s vitality.'}
                    </p>

                    <div className="mt-8 flex items-center justify-center gap-6 text-[11px] text-amber-300/80 font-sans font-semibold uppercase tracking-[0.18em]">
                        <span>✓ Paraben-Free</span>
                        <span>•</span>
                        <span>✓ 100% Vegetarian</span>
                        <span>•</span>
                        <span>✓ Islandwide COD</span>
                    </div>
                </div>
            </section>
        </PublicLayout>
    );
}
