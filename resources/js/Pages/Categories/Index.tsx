import { useTranslation } from '@/hooks/useTranslation';
import PublicLayout from '@/Layouts/PublicLayout';
import { Category } from '@/types';
import { Head, Link } from '@inertiajs/react';
import React from 'react';

interface Props {
    categories: (Category & { products_count?: number })[];
}

export default function CategoriesIndex({ categories }: Props) {
    const { t, locale } = useTranslation();

    return (
        <PublicLayout>
            <Head title={`${t('categories', 'Categories')} | ${t('app_name', 'Aura Luxe')}`} />

            <div className="bg-[#F5EFE6] border-b border-stone-200/80 py-12 lg:py-16">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
                    <span className="text-xs uppercase tracking-[0.2em] font-semibold text-rose-900 block mb-2">
                        {t('app_name', 'Aura Luxe Collections')}
                    </span>
                    <h1 className="font-serif text-3xl sm:text-4xl text-stone-900 font-medium">
                        {locale === 'si' ? 'නිෂ්පාදන කාණ්ඩ එකතුව' : 'Botanical Formulations by Category'}
                    </h1>
                    <p className="text-xs sm:text-sm text-stone-600 mt-3 max-w-lg mx-auto">
                        Explore our complete botanical rituals, each crafted with active botanical extracts.
                    </p>
                </div>
            </div>

            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-14">
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-8">
                    {categories.map((cat) => (
                        <Link
                            key={cat.id}
                            href={`/products?category=${cat.slug}`}
                            className="group bg-white rounded-2xl border border-stone-200/80 hover:border-rose-300 p-8 shadow-xs hover:shadow-md transition-all flex flex-col justify-between"
                        >
                            <div>
                                <div className="w-12 h-12 rounded-xl bg-stone-100 flex items-center justify-center text-rose-800 mb-6 group-hover:scale-110 transition-transform">
                                    🌸
                                </div>
                                <h2 className="font-serif text-xl font-medium text-stone-900 group-hover:text-rose-900 transition-colors">
                                    {locale === 'si' && cat.name_si ? cat.name_si : cat.name_en}
                                </h2>
                                <p className="text-xs text-stone-500 mt-2.5 leading-relaxed">
                                    {locale === 'si' && cat.description_si
                                        ? cat.description_si
                                        : cat.description_en}
                                </p>
                            </div>

                            <div className="mt-6 pt-4 border-t border-stone-100 flex items-center justify-between text-xs">
                                <span className="text-stone-400">
                                    {cat.products_count ?? 0} {t('shop', 'products')}
                                </span>
                                <span className="font-semibold text-rose-900 uppercase tracking-wider group-hover:translate-x-1 transition-transform">
                                    Browse →
                                </span>
                            </div>
                        </Link>
                    ))}
                </div>
            </div>
        </PublicLayout>
    );
}

