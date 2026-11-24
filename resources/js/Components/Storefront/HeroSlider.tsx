import { useTranslation } from '@/hooks/useTranslation';
import { Banner } from '@/types';
import { Link } from '@inertiajs/react';
import React, { useEffect, useRef, useState } from 'react';

interface Props {
    banners?: Banner[];
}

export default function HeroSlider({ banners = [] }: Props) {
    const { locale } = useTranslation();
    const [currentIndex, setCurrentIndex] = useState(0);
    const [isPaused, setIsPaused] = useState(false);
    const touchStartX = useRef<number | null>(null);
    const touchEndX = useRef<number | null>(null);

    // Default fallback slides matching Spa Ceylon festive luxury aesthetic
    const fallbackSlides: Banner[] = [
        {
            id: 991,
            title_en: 'ONLINE FESTIVE GALLERY IS NOW LIVE',
            title_si: 'රාජකීය උත්සව ගැලරිය දැන් විවෘතයි',
            subtitle_en: 'Step into our immersive Ceylon botanical wonderland. Exclusive festive gift sets and limited royal formulations.',
            subtitle_si: 'සුවිශේෂී රාජකීය ආයුර්වේද සත්කාර සහ තිළිණ පෙට්ටි එකතුව අදම ලබාගන්න.',
            cta_text_en: 'EXPERIENCE NOW',
            cta_text_si: 'දැන්ම නරඹන්න',
            cta_url: '/products',
            placement: 'hero_slider',
            theme_style: 'festive_rose',
            is_active: true,
            sort_order: 1,
        },
        {
            id: 992,
            title_en: 'ROYAL CEYLON RADIANCE RITUALS',
            title_si: 'රාජකීය ශ්‍රී ලාංකික සත්කාර චාරිත්‍ර',
            subtitle_en: 'Hand-blended with organic central highland botanicals, saffron, and wild rose petals for eternal luminous glow.',
            subtitle_si: 'ස්වභාවික කුංකුම සහ රෝස සාරයෙන් සමට ලබාදෙන අසමසම දීප්තිමත් රැකවරණය.',
            cta_text_en: 'SHOP THE COLLECTION',
            cta_text_si: 'එකතුව බලන්න',
            cta_url: '/products',
            placement: 'hero_slider',
            theme_style: 'royal_gold',
            is_active: true,
            sort_order: 2,
        },
    ];

    const activeSlides = banners.length > 0 ? banners : fallbackSlides;

    // Automatic slide swapper (5.5s timer)
    useEffect(() => {
        if (activeSlides.length <= 1 || isPaused) return;

        const timer = setInterval(() => {
            setCurrentIndex((prev) => (prev + 1) % activeSlides.length);
        }, 5500);

        return () => clearInterval(timer);
    }, [activeSlides.length, isPaused]);

    const handlePrev = (e: React.MouseEvent) => {
        e.preventDefault();
        setCurrentIndex((prev) => (prev - 1 + activeSlides.length) % activeSlides.length);
    };

    const handleNext = (e: React.MouseEvent) => {
        e.preventDefault();
        setCurrentIndex((prev) => (prev + 1) % activeSlides.length);
    };

    // Mobile touch swipe handling
    const handleTouchStart = (e: React.TouchEvent) => {
        touchStartX.current = e.targetTouches[0].clientX;
    };

    const handleTouchMove = (e: React.TouchEvent) => {
        touchEndX.current = e.targetTouches[0].clientX;
    };

    const handleTouchEnd = () => {
        if (!touchStartX.current || !touchEndX.current) return;
        const diff = touchStartX.current - touchEndX.current;
        // Require at least 50px swipe distance
        if (diff > 50) {
            // Swiped left -> Next slide
            setCurrentIndex((prev) => (prev + 1) % activeSlides.length);
        } else if (diff < -50) {
            // Swiped right -> Previous slide
            setCurrentIndex((prev) => (prev - 1 + activeSlides.length) % activeSlides.length);
        }
        touchStartX.current = null;
        touchEndX.current = null;
    };

    const currentSlide = activeSlides[currentIndex] || activeSlides[0];

    const title = locale === 'si' && currentSlide.title_si ? currentSlide.title_si : currentSlide.title_en;
    const subtitle = locale === 'si' && currentSlide.subtitle_si ? currentSlide.subtitle_si : currentSlide.subtitle_en;
    const ctaText = locale === 'si' && currentSlide.cta_text_si ? currentSlide.cta_text_si : currentSlide.cta_text_en || 'EXPERIENCE NOW';

    // Theme style backdrop gradients
    const getThemeBackdrop = (theme: string) => {
        switch (theme) {
            case 'festive_rose':
                return 'bg-gradient-to-r from-[#2B0E14] via-[#4A1525] to-[#7A1C36]';
            case 'royal_gold':
                return 'bg-gradient-to-r from-[#1A1612] via-[#2D2218] to-[#4A3824]';
            case 'dark_botanical':
                return 'bg-gradient-to-r from-[#0E1A14] via-[#132A1F] to-[#1C3E2F]';
            case 'ivory_cream':
                return 'bg-gradient-to-r from-[#211E1A] via-[#332E27] to-[#453F36]';
            default:
                return 'bg-gradient-to-r from-[#2B0E14] via-[#4A1525] to-[#7A1C36]';
        }
    };

    return (
        <section
            aria-label="Storefront Hero Festive Slider"
            className="relative w-full overflow-hidden select-none bg-[#141210]"
            onMouseEnter={() => setIsPaused(true)}
            onMouseLeave={() => setIsPaused(false)}
            onTouchStart={handleTouchStart}
            onTouchMove={handleTouchMove}
            onTouchEnd={handleTouchEnd}
        >
            {/* Slide container (Spa Ceylon wide cinematic aspect) */}
            <div className={`relative min-h-[460px] sm:min-h-[520px] lg:min-h-[580px] w-full flex items-center transition-colors duration-1000 ${getThemeBackdrop(currentSlide.theme_style)}`}>
                {/* Background image if set */}
                {currentSlide.image_url && (
                    <div className="absolute inset-0 z-0">
                        <img
                            src={currentSlide.image_url}
                            alt={title}
                            className="w-full h-full object-cover object-center brightness-90 transition-transform duration-1000 ease-out"
                        />
                        <div className="absolute inset-0 bg-gradient-to-r from-[#141210]/90 via-[#141210]/50 to-transparent"></div>
                    </div>
                )}

                {/* Decorative festive overlay effects (Spa Ceylon Festive Pavilion) */}
                <div className="absolute inset-0 pointer-events-none overflow-hidden z-0 opacity-40">
                    <div className="absolute top-1/2 left-1/4 -translate-y-1/2 w-96 h-96 rounded-full bg-rose-500/20 blur-3xl"></div>
                    <div className="absolute top-1/4 right-1/4 w-80 h-80 rounded-full bg-amber-500/15 blur-3xl"></div>
                    {/* Subtle decorative lotus filigree vectors */}
                    <svg className="absolute -bottom-10 -right-10 w-96 h-96 text-white/5" viewBox="0 0 100 100" fill="currentColor">
                        <path d="M50 0 C60 30 80 40 100 50 C80 60 60 70 50 100 C40 70 20 60 0 50 C20 40 40 30 50 0 Z" />
                    </svg>
                </div>

                {/* Slide Content */}
                <div className="relative z-10 max-w-7xl mx-auto px-6 sm:px-10 lg:px-16 w-full py-16 sm:py-24">
                    <div className="max-w-2xl text-left">
                        {/* Top Royal Pill */}
                        <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/10 backdrop-blur-md border border-white/20 text-[#FAF8F5] text-[10px] sm:text-[11px] font-mono uppercase tracking-[0.25em] mb-4 shadow-xs">
                            <span className="text-amber-300">👑</span>
                            <span>{locale === 'si' ? 'රාජකීය උත්සව ආයුර්වේදය' : 'Spa Ceylon Festive Luxe'}</span>
                        </div>

                        {/* Title (Bold Serif Festive Style) */}
                        <h2 className="font-serif text-3xl sm:text-5xl lg:text-6xl font-normal tracking-wide text-white leading-[1.12] drop-shadow-sm uppercase">
                            {title}
                        </h2>

                        {/* Subtitle */}
                        {subtitle && (
                            <p className="mt-4 sm:mt-6 text-sm sm:text-base lg:text-lg text-[#F2E8DC] font-light leading-relaxed max-w-xl drop-shadow-xs">
                                {subtitle}
                            </p>
                        )}

                        {/* CTA Button (Spa Ceylon Glowing Pill) */}
                        <div className="mt-8 sm:mt-10 flex items-center gap-4">
                            <Link
                                href={currentSlide.cta_url || '/products'}
                                className="px-8 sm:px-10 py-3.5 sm:py-4 bg-gradient-to-r from-rose-800 to-rose-950 hover:from-rose-700 hover:to-rose-900 text-white text-xs sm:text-sm uppercase tracking-[0.25em] font-semibold rounded-full border border-rose-400/50 shadow-[0_4px_24px_rgba(225,29,72,0.35)] hover:shadow-[0_6px_32px_rgba(225,29,72,0.5)] transition-all duration-300 min-h-[48px] inline-flex items-center justify-center cursor-pointer group"
                            >
                                <span>{ctaText}</span>
                                <span className="ml-2 group-hover:translate-x-1 transition-transform">→</span>
                            </Link>

                            <Link
                                href="/products"
                                className="hidden sm:inline-flex px-6 py-4 text-xs uppercase tracking-[0.18em] font-medium text-white/80 hover:text-white transition-colors"
                            >
                                {locale === 'si' ? 'සියලුම සත්කාර' : 'View Catalog'}
                            </Link>
                        </div>
                    </div>
                </div>

                {/* Left Navigation Arrow (<) */}
                {activeSlides.length > 1 && (
                    <button
                        type="button"
                        onClick={handlePrev}
                        aria-label="Previous slide"
                        className="absolute left-3 sm:left-6 top-1/2 -translate-y-1/2 z-20 w-11 h-11 sm:w-13 sm:h-13 rounded-full bg-black/40 hover:bg-black/70 text-white/80 hover:text-white border border-white/20 backdrop-blur-md flex items-center justify-center transition-all duration-200 cursor-pointer shadow-md focus:outline-none focus:ring-2 focus:ring-amber-400/50"
                    >
                        <svg className="w-5 h-5 sm:w-6 sm:h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 19l-7-7 7-7" />
                        </svg>
                    </button>
                )}

                {/* Right Navigation Arrow (>) */}
                {activeSlides.length > 1 && (
                    <button
                        type="button"
                        onClick={handleNext}
                        aria-label="Next slide"
                        className="absolute right-3 sm:right-6 top-1/2 -translate-y-1/2 z-20 w-11 h-11 sm:w-13 sm:h-13 rounded-full bg-black/40 hover:bg-black/70 text-white/80 hover:text-white border border-white/20 backdrop-blur-md flex items-center justify-center transition-all duration-200 cursor-pointer shadow-md focus:outline-none focus:ring-2 focus:ring-amber-400/50"
                    >
                        <svg className="w-5 h-5 sm:w-6 sm:h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </button>
                )}

                {/* Bottom Pagination Dots (• •) */}
                {activeSlides.length > 1 && (
                    <div className="absolute bottom-5 sm:bottom-7 left-1/2 -translate-x-1/2 z-20 flex items-center gap-2.5 bg-black/30 backdrop-blur-md px-3.5 py-1.5 rounded-full border border-white/10">
                        {activeSlides.map((_, idx) => (
                            <button
                                key={idx}
                                type="button"
                                onClick={() => setCurrentIndex(idx)}
                                aria-label={`Go to slide ${idx + 1}`}
                                className={`transition-all duration-300 rounded-full cursor-pointer ${
                                    currentIndex === idx
                                        ? 'w-7 h-2 bg-white shadow-xs'
                                        : 'w-2 h-2 bg-white/40 hover:bg-white/70'
                                }`}
                            />
                        ))}
                    </div>
                )}
            </div>
        </section>
    );
}

