import { useTranslation } from '@/hooks/useTranslation';
import PublicLayout from '@/Layouts/PublicLayout';
import { Head, Link } from '@inertiajs/react';
import React from 'react';

export default function About() {
    const { t, locale } = useTranslation();

    return (
        <PublicLayout>
            <Head title={`${t('about_us', 'About Us')} | ${t('app_name', 'Aura Luxe')}`} />

            <div className="bg-[#F5EFE6] border-b border-stone-200/80 py-16">
                <div className="max-w-4xl mx-auto px-4 text-center">
                    <span className="text-xs uppercase tracking-[0.25em] font-semibold text-rose-900 block mb-2">
                        {t('app_name', 'Aura Luxe Cosmetics')}
                    </span>
                    <h1 className="font-serif text-4xl sm:text-5xl text-stone-900 font-medium tracking-tight">
                        {locale === 'si' ? 'අපගේ අරමුණ සහ ස්වභාවික සාරය' : 'Crafted with Ceylon Botanicals'}
                    </h1>
                    <p className="text-sm sm:text-base text-stone-600 mt-4 leading-relaxed max-w-2xl mx-auto">
                        Founded with a passion for clean beauty and active tropical botanicals, Aura Luxe formulates high-performance skincare made for real radiance.
                    </p>
                </div>
            </div>

            <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-16 space-y-16 text-stone-700 leading-relaxed text-sm">
                <section className="grid grid-cols-1 md:grid-cols-2 gap-10 items-center">
                    <div>
                        <span className="text-xs font-semibold uppercase tracking-wider text-rose-800 block mb-2">
                            Our Philosophy
                        </span>
                        <h2 className="font-serif text-2xl text-stone-900 font-medium mb-4">
                            Beauty Powered by Nature & Integrity
                        </h2>
                        <p className="text-xs sm:text-sm text-stone-600 mb-4 leading-relaxed">
                            Every serum, moisturizer, and lipstick we formulate begins with ethically harvested Ceylon green tea, wild rose extracts, and active vitamins. We exclude parabens, harsh sulfates, synthetic phthalates, and unnecessary fillers.
                        </p>
                        <p className="text-xs sm:text-sm text-stone-600 leading-relaxed">
                            Our formulas are dermatologist-tested and adapted for humid tropical climates, absorbing weightlessly without sticky residues.
                        </p>
                    </div>
                    <div className="bg-stone-100 rounded-2xl border border-stone-200 p-8 text-center flex flex-col items-center justify-center aspect-square">
                        <span className="text-6xl mb-4">🌿</span>
                        <h3 className="font-serif text-xl font-medium text-stone-900 mb-2">100% Cruelty-Free</h3>
                        <p className="text-xs text-stone-500 max-w-xs leading-relaxed">
                            Never tested on animals. Ethically crafted with love in Sri Lanka.
                        </p>
                    </div>
                </section>

                <section className="bg-white rounded-2xl border border-stone-200 p-8 sm:p-12 shadow-xs">
                    <h2 className="font-serif text-2xl text-stone-900 font-medium mb-6 text-center">
                        Why Customers Trust Aura Luxe
                    </h2>
                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-8 text-center">
                        <div>
                            <div className="w-12 h-12 rounded-full bg-stone-50 text-stone-800 flex items-center justify-center text-xl mx-auto mb-3">
                                💵
                            </div>
                            <h3 className="font-semibold text-stone-900 text-sm mb-1">Cash on Delivery</h3>
                            <p className="text-xs text-stone-500 leading-relaxed">
                                Pay securely in cash to the delivery courier when your order reaches your hands.
                            </p>
                        </div>
                        <div>
                            <div className="w-12 h-12 rounded-full bg-stone-50 text-stone-800 flex items-center justify-center text-xl mx-auto mb-3">
                                🚚
                            </div>
                            <h3 className="font-semibold text-stone-900 text-sm mb-1">Islandwide Network</h3>
                            <p className="text-xs text-stone-500 leading-relaxed">
                                Fast dispatch across all 25 districts with reliable courier partners.
                            </p>
                        </div>
                        <div>
                            <div className="w-12 h-12 rounded-full bg-stone-50 text-stone-800 flex items-center justify-center text-xl mx-auto mb-3">
                                🛡️
                            </div>
                            <h3 className="font-semibold text-stone-900 text-sm mb-1">7-Day Return Window</h3>
                            <p className="text-xs text-stone-500 leading-relaxed">
                                Hassle-free exchanges or returns if products arrive damaged or sealed defective.
                            </p>
                        </div>
                    </div>
                </section>

                <div className="text-center pt-6">
                    <Link
                        href="/products"
                        className="inline-block px-8 py-4 bg-stone-900 hover:bg-rose-900 text-white text-xs uppercase tracking-[0.2em] font-semibold rounded-xl shadow-md transition-colors"
                    >
                        {t('shop_now', 'Explore Our Products')}
                    </Link>
                </div>
            </div>
        </PublicLayout>
    );
}

