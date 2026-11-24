import { useTranslation } from '@/hooks/useTranslation';
import { Banner } from '@/types';
import { Link } from '@inertiajs/react';
import React from 'react';

interface Props {
    subBanner1?: Banner | null;
    subBanner2?: Banner | null;
}

export default function SubBanners({ subBanner1, subBanner2 }: Props) {
    const { locale } = useTranslation();

    // Default left banner (Community Wellness & Care)
    const b1 = subBanner1 || {
        id: 981,
        title_en: 'MAKE THEIR JOURNEY LIGHTER',
        title_si: 'සුවපත් දිවියකට ශාකසාර අත්වැලක්',
        subtitle_en: 'What if you could make one month of someone’s cancer journey a little easier?',
        subtitle_si: 'රෝගී සත්කාර සහ ප්‍රජා සුවතා වැඩසටහන සමඟ අත්වැල් බැඳගන්න.',
        cta_text_en: 'LEARN MORE',
        cta_text_si: 'වැඩිදුර විස්තර',
        cta_url: '/about',
        placement: 'sub_banner_1',
        theme_style: 'dark_botanical',
        is_active: true,
        sort_order: 1,
    };

    // Default right banner (Serum Spotlight & Radiance)
    const b2 = subBanner2 || {
        id: 982,
        title_en: 'READY, SET, GLOW',
        title_si: 'සමට නව දීප්තියක්',
        subtitle_en: 'WITH THE SERUM MADE FOR YOU. Infused with potent Ceylon antioxidants.',
        subtitle_si: 'ඔබේ සමට ගැළපෙනම ස්වභාවික සීරම් එකතුව.',
        cta_text_en: 'SHOP NOW',
        cta_text_si: 'මිලදී ගන්න',
        cta_url: '/products?search=Serum',
        placement: 'sub_banner_2',
        theme_style: 'ivory_cream',
        is_active: true,
        sort_order: 1,
    };

    const b1Title = locale === 'si' && b1.title_si ? b1.title_si : b1.title_en;
    const b1Subtitle = locale === 'si' && b1.subtitle_si ? b1.subtitle_si : b1.subtitle_en;
    const b1Cta = locale === 'si' && b1.cta_text_si ? b1.cta_text_si : b1.cta_text_en || 'LEARN MORE';

    const b2Title = locale === 'si' && b2.title_si ? b2.title_si : b2.title_en;
    const b2Subtitle = locale === 'si' && b2.subtitle_si ? b2.subtitle_si : b2.subtitle_en;
    const b2Cta = locale === 'si' && b2.cta_text_si ? b2.cta_text_si : b2.cta_text_en || 'SHOP NOW';

    return (
        <section aria-label="Featured Promotional Banners" className="w-full bg-[#FAF8F5] py-4 sm:py-6">
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-6">
                    {/* LEFT SUB-BANNER (Campaign / Wellness Initiative) */}
                    <div className="group relative rounded-2xl overflow-hidden bg-gradient-to-br from-[#1C1728] via-[#241F33] to-[#120F1D] text-white p-6 sm:p-8 min-h-[220px] sm:min-h-[240px] flex flex-col justify-between border border-purple-900/40 shadow-sm hover:shadow-lg transition-all duration-300">
                        {/* Custom background image if uploaded */}
                        {b1.image_url ? (
                            <img
                                src={b1.image_url}
                                alt={b1Title}
                                className="absolute inset-0 w-full h-full object-cover object-center group-hover:scale-105 transition-transform duration-700 ease-out z-0"
                            />
                        ) : (
                            /* Decorative initiative artwork */
                            <div className="absolute top-0 right-0 w-64 h-64 bg-purple-500/10 rounded-full blur-2xl pointer-events-none -mr-16 -mt-16"></div>
                        )}

                        <div className="relative z-10">
                            {/* Emblem & Tag */}
                            <div className="flex items-center gap-2 mb-3">
                                <span className="text-purple-300 text-lg">💜</span>
                                <span className="text-[10px] sm:text-[11px] font-mono tracking-[0.25em] uppercase text-purple-200/90 font-medium">
                                    {b1Title}
                                </span>
                            </div>

                            {/* Headline */}
                            <p className="font-serif text-lg sm:text-2xl font-medium text-[#FAF8F5] leading-snug max-w-md">
                                {b1Subtitle}
                            </p>
                        </div>

                        {/* CTA Link */}
                        <div className="relative z-10 mt-6 pt-4 border-t border-white/10">
                            <Link
                                href={b1.cta_url || '/about'}
                                className="inline-flex items-center gap-2 text-xs uppercase tracking-[0.18em] font-semibold text-purple-200 hover:text-white transition-colors group-hover:translate-x-1 duration-200"
                            >
                                <span>{b1Cta}</span>
                                <span>→</span>
                            </Link>
                        </div>
                    </div>

                    {/* RIGHT SUB-BANNER (Product Spotlight / Serum Collection) */}
                    <div className="group relative rounded-2xl overflow-hidden bg-gradient-to-br from-[#F8F3EA] via-[#F4EDE0] to-[#EAE0D0] text-stone-900 p-6 sm:p-8 min-h-[220px] sm:min-h-[240px] flex flex-col justify-between border border-[#E0D5C3] shadow-sm hover:shadow-lg transition-all duration-300">
                        {/* Custom background image if uploaded */}
                        {b2.image_url ? (
                            <img
                                src={b2.image_url}
                                alt={b2Title}
                                className="absolute inset-0 w-full h-full object-cover object-center group-hover:scale-105 transition-transform duration-700 ease-out z-0"
                            />
                        ) : (
                            /* Decorative serum artwork */
                            <div className="absolute right-4 bottom-4 flex items-center gap-2 opacity-80 group-hover:scale-105 transition-transform duration-700 ease-out">
                                <span className="text-4xl">✨</span>
                                <span className="text-4xl">🌿</span>
                            </div>
                        )}

                        <div className="relative z-10">
                            {/* Emblem & Tag */}
                            <span className="text-[10px] sm:text-[11px] font-mono tracking-[0.28em] uppercase text-amber-900/90 font-medium block mb-2">
                                {locale === 'si' ? 'සුවිශේෂී සීරම් එකතුව' : 'ROYAL SERUM ELIXIRS'}
                            </span>

                            {/* Title & Subtitle */}
                            <h3 className="font-serif text-xl sm:text-3xl font-medium text-stone-900 tracking-wide uppercase leading-tight">
                                {b2Title}
                            </h3>

                            <p className="mt-2 text-xs sm:text-sm text-stone-600 font-serif italic max-w-xs">
                                {b2Subtitle}
                            </p>
                        </div>

                        {/* CTA Link */}
                        <div className="relative z-10 mt-6 pt-4 border-t border-stone-300/60">
                            <Link
                                href={b2.cta_url || '/products'}
                                className="inline-flex items-center gap-2 text-xs uppercase tracking-[0.2em] font-bold text-amber-950 hover:text-rose-900 transition-colors group-hover:translate-x-1 duration-200"
                            >
                                <span className="underline underline-offset-4 decoration-amber-700/60">{b2Cta}</span>
                                <span>→</span>
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    );
}

