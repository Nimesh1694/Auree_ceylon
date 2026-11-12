import ProductCard from '@/Components/ProductCard';
import { useTranslation } from '@/hooks/useTranslation';
import PublicLayout from '@/Layouts/PublicLayout';
import { Category, Product } from '@/types';
import { Head, Link } from '@inertiajs/react';
import React from 'react';

interface Props {
    categories: Category[];
    featuredProducts: Product[];
    bestSellers: Product[];
    newArrivals: Product[];
}

export default function Welcome({ categories, featuredProducts, bestSellers, newArrivals }: Props) {
    const { t, locale } = useTranslation();

    return (
        <PublicLayout>
            <Head title={t('app_name', 'Aura Luxe Cosmetics | Luxury Botanical Skincare')} />

            {/* Hero Section */}
            <section className="relative overflow-hidden bg-gradient-to-b from-[#F5EFE6] via-[#FAF8F5] to-[#FAF8F5] border-b border-stone-200/60 py-20 lg:py-28">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="max-w-2xl">
                        <span className="inline-block text-xs uppercase tracking-[0.25em] text-rose-900 font-semibold mb-3">
                            {locale === 'si' ? 'ශ්‍රී ලාංකික උසස් ප්‍රමිතියේ රූපලාවණ්‍ය' : 'Pure Botanical Formulations'}
                        </span>
                        <h1 className="font-serif text-4xl sm:text-5xl lg:text-6xl font-medium tracking-tight text-stone-900 leading-[1.15]">
                            {t('hero_title', 'Pure Elegance, Natural Radiance')}
                        </h1>
                        <p className="mt-5 text-base sm:text-lg text-stone-600 leading-relaxed font-light">
                            {t(
                                'hero_subtitle',
                                'Discover luxury botanical skincare formulated for radiant, healthy skin. Delivered right to your doorstep with Cash on Delivery.'
                            )}
                        </p>

                        <div className="mt-8 flex flex-wrap items-center gap-4">
                            <Link
                                href="/products"
                                className="px-7 py-3.5 bg-stone-900 hover:bg-rose-900 text-white text-xs uppercase tracking-[0.2em] font-semibold rounded-lg shadow-md transition-all duration-200"
                            >
                                {t('shop_now', 'Shop Collection')}
                            </Link>
                            <Link
                                href="/about"
                                className="px-6 py-3.5 border border-stone-300 hover:border-stone-900 text-stone-800 text-xs uppercase tracking-[0.15em] font-medium rounded-lg transition-colors"
                            >
                                {t('about_us', 'Our Story')}
                            </Link>
                        </div>

                        {/* Trust Highlights */}
                        <div className="mt-12 pt-8 border-t border-stone-300/60 grid grid-cols-3 gap-6 text-xs text-stone-600">
                            <div>
                                <span className="block font-semibold text-stone-900 text-sm">100% COD</span>
                                <span className="text-[11px] text-stone-500">Pay when received</span>
                            </div>
                            <div>
                                <span className="block font-semibold text-stone-900 text-sm">Cruelty-Free</span>
                                <span className="text-[11px] text-stone-500">Clean botanicals</span>
                            </div>
                            <div>
                                <span className="block font-semibold text-stone-900 text-sm">Islandwide</span>
                                <span className="text-[11px] text-stone-500">Fast 2-4 day delivery</span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            {/* Featured Categories */}
            {categories.length > 0 && (
                <section className="py-16 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="flex items-end justify-between mb-8">
                        <div>
                            <span className="text-xs uppercase tracking-widest text-rose-900 font-semibold block mb-1">
                                {t('categories', 'Explore Categories')}
                            </span>
                            <h2 className="font-serif text-2xl sm:text-3xl text-stone-900 font-medium">
                                {locale === 'si' ? 'අපගේ ප්‍රධාන කාණ්ඩ' : 'Curated Collections'}
                            </h2>
                        </div>
                        <Link
                            href="/categories"
                            className="text-xs uppercase tracking-wider text-stone-600 hover:text-stone-900 font-semibold underline underline-offset-4"
                        >
                            {t('view_all', 'View All')} →
                        </Link>
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                        {categories.map((cat) => (
                            <Link
                                key={cat.id}
                                href={`/categories/${cat.slug}`}
                                className="group relative rounded-xl border border-stone-200/80 bg-white p-6 hover:border-rose-300 hover:shadow-md transition-all flex flex-col justify-between"
                            >
                                <div>
                                    <h3 className="font-serif text-lg text-stone-900 group-hover:text-rose-900 font-medium transition-colors">
                                        {locale === 'si' && cat.name_si ? cat.name_si : cat.name_en}
                                    </h3>
                                    <p className="text-xs text-stone-500 mt-2 line-clamp-2 leading-relaxed">
                                        {locale === 'si' && cat.description_si
                                            ? cat.description_si
                                            : cat.description_en}
                                    </p>
                                </div>
                                <span className="text-xs text-rose-800 font-semibold tracking-wider uppercase mt-4 block">
                                    Explore Collection →
                                </span>
                            </Link>
                        ))}
                    </div>
                </section>
            )}

            {/* Best Sellers Section */}
            {bestSellers.length > 0 && (
                <section className="py-16 bg-white border-y border-stone-200/60">
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        <div className="text-center max-w-xl mx-auto mb-12">
                            <span className="text-xs uppercase tracking-widest text-rose-900 font-semibold block mb-1">
                                {t('best_sellers', 'Customer Favorites')}
                            </span>
                            <h2 className="font-serif text-3xl text-stone-900 font-medium">
                                {locale === 'si' ? 'වැඩිපුරම අලෙවි වන නිෂ්පාදන' : 'Most Loved by You'}
                            </h2>
                        </div>

                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                            {bestSellers.map((product) => (
                                <ProductCard key={product.id} product={product} />
                            ))}
                        </div>
                    </div>
                </section>
            )}

            {/* New Arrivals Section */}
            {newArrivals.length > 0 && (
                <section className="py-16 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="flex items-end justify-between mb-8">
                        <div>
                            <span className="text-xs uppercase tracking-widest text-rose-900 font-semibold block mb-1">
                                {t('new_arrivals', 'Just Arrived')}
                            </span>
                            <h2 className="font-serif text-2xl sm:text-3xl text-stone-900 font-medium">
                                {locale === 'si' ? 'නව නිෂ්පාදන එකතුව' : 'Fresh Formulations'}
                            </h2>
                        </div>
                        <Link
                            href="/products"
                            className="text-xs uppercase tracking-wider text-stone-600 hover:text-stone-900 font-semibold underline underline-offset-4"
                        >
                            {t('view_all', 'View All')} →
                        </Link>
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                        {newArrivals.map((product) => (
                            <ProductCard key={product.id} product={product} />
                        ))}
                    </div>
                </section>
            )}

            {/* Brand Philosophy Banner */}
            <section className="bg-[#FAF3EB] border-t border-stone-200/70 py-16">
                <div className="max-w-4xl mx-auto px-4 text-center">
                    <span className="text-xs uppercase tracking-[0.25em] text-rose-900 font-semibold block mb-2">
                        {locale === 'si' ? 'අපගේ පොරොන්දුව' : 'The Aura Luxe Promise'}
                    </span>
                    <h2 className="font-serif text-3xl text-stone-900 font-medium leading-relaxed">
                        {locale === 'si'
                            ? 'සමේ සෞඛ්‍යය සහ දීප්තිය රැකගැනීමට ස්වභාවික ශාකසාරයන්ගේ බලය'
                            : 'Nourishing botanical science, thoughtfully crafted for true radiance.'}
                    </h2>
                    <p className="mt-4 text-sm text-stone-600 max-w-2xl mx-auto leading-relaxed">
                        {locale === 'si'
                            ? 'කිසිදු හානිකර රසායනික ද්‍රව්‍යයකින් තොරව, උසස්ම ප්‍රමිතියේ ස්වභාවික සාරයන්ගෙන් පමණක් අපගේ නිෂ්පාදන සකස් කර ඇත. දිවයින පුරා බෙදාහැරීම සහිතව මුදල් ගෙවීමේ පහසුව.'
                            : 'Free from parabens, sulfates, and artificial fragrances. Formulated with ethically harvested Ceylon green tea, gotu kola, and wild rose petals.'}
                    </p>
                </div>
            </section>
        </PublicLayout>
    );
}
