import ProductCard from '@/Components/ProductCard';
import { useTranslation } from '@/hooks/useTranslation';
import PublicLayout from '@/Layouts/PublicLayout';
import { Category, Product } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import React, { useState } from 'react';

interface PaginatedData<T> {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
}

interface FiltersState {
    category?: string;
    search?: string;
    filter?: string;
    min_price?: string;
    max_price?: string;
    in_stock?: boolean | string;
    sort?: string;
}

interface Props {
    products: PaginatedData<Product>;
    categories: (Category & { products_count?: number })[];
    filters?: FiltersState | unknown[];
}

export default function ProductsIndex({ products, categories, filters }: Props) {
    const { t, locale } = useTranslation();

    const safeFilters: FiltersState = (filters && !Array.isArray(filters) ? filters : {}) as FiltersState;

    const [search, setSearch] = useState(typeof safeFilters.search === 'string' ? safeFilters.search : '');
    const [inStock, setInStock] = useState(Boolean(safeFilters.in_stock));
    const [selectedSort, setSelectedSort] = useState(
        typeof safeFilters.sort === 'string' ? safeFilters.sort : 'newest'
    );

    const activeCategory = categories.find((c) => c.slug === safeFilters.category);

    const handleCategorySelect = (categorySlug?: string) => {
        router.get(
            '/products',
            {
                category: categorySlug || undefined,
                search: search || undefined,
                in_stock: inStock ? '1' : undefined,
                sort: selectedSort !== 'newest' ? selectedSort : undefined,
            },
            { preserveState: true, preserveScroll: true }
        );
    };

    const handleFilterSelect = (filterName: string) => {
        const isAlreadySelected = safeFilters.filter === filterName;
        router.get(
            '/products',
            {
                filter: isAlreadySelected ? undefined : filterName,
                search: search || undefined,
                in_stock: inStock ? '1' : undefined,
                sort: selectedSort !== 'newest' ? selectedSort : undefined,
            },
            { preserveState: true, preserveScroll: true }
        );
    };

    const handleSearchSubmit = (e?: React.FormEvent) => {
        if (e) e.preventDefault();
        router.get(
            '/products',
            {
                category: safeFilters.category,
                filter: typeof safeFilters.filter === 'string' ? safeFilters.filter : undefined,
                search: search.trim() || undefined,
                in_stock: inStock ? '1' : undefined,
                sort: selectedSort !== 'newest' ? selectedSort : undefined,
            },
            { preserveState: true, preserveScroll: true }
        );
    };

    const handleSortChange = (newSort: string) => {
        setSelectedSort(newSort);
        router.get(
            '/products',
            {
                ...safeFilters,
                sort: newSort !== 'newest' ? newSort : undefined,
            },
            { preserveState: true, preserveScroll: true }
        );
    };

    const handleInStockToggle = (checked: boolean) => {
        setInStock(checked);
        router.get(
            '/products',
            {
                ...safeFilters,
                in_stock: checked ? '1' : undefined,
            },
            { preserveState: true, preserveScroll: true }
        );
    };

    const clearAllFilters = () => {
        setSearch('');
        setInStock(false);
        setSelectedSort('newest');
        router.get('/products', {}, { preserveState: true, preserveScroll: true });
    };

    const pageHeading = activeCategory
        ? locale === 'si' && activeCategory.name_si
            ? activeCategory.name_si
            : activeCategory.name_en
        : t('shop_all_formulations', 'Shop All Formulations');

    return (
        <PublicLayout>
            <Head title={`${pageHeading} | ${t('app_name', 'AURA LUXE — Shop')}`} />

            {/* 1. MINIMAL LUXURY HERO BANNER */}
            <section id="top" className="bg-[#F5EFE6] border-b border-[#EAE2D6] py-12 sm:py-16 px-4 sm:px-8">
                <div className="max-w-7xl mx-auto">
                    <p className="text-[11px] font-semibold tracking-[0.24em] uppercase text-[#9A4E22] mb-2 font-sans">
                        {t('app_name', 'AURA LUXE COSMETICS')}
                    </p>
                    <h1 id="hero-heading" className="font-serif text-4xl sm:text-5xl font-semibold text-[#1C1917] tracking-tight">
                        {pageHeading}
                    </h1>
                    <p className="mt-3 max-w-xl text-sm text-[#1C1917]/75 leading-relaxed font-light">
                        {activeCategory
                            ? locale === 'si' && activeCategory.description_si
                                ? activeCategory.description_si
                                : activeCategory.description_en
                            : t(
                                  'hero_subtitle',
                                  'Discover luxury botanical skincare formulated for radiant, healthy skin. Delivered right to your doorstep with Cash on Delivery.'
                              )}
                    </p>
                </div>
            </section>

            {/* 2. SIMPLE FILTER TOOLBAR & PRODUCT GRID */}
            <main id="shop" className="max-w-7xl mx-auto px-4 sm:px-8 py-8 sm:py-12">
                {/* 2A. Minimalist Category Navigation Pills */}
                <div className="border-b border-[#EAE2D6] pb-5 mb-6">
                    <div className="flex items-center gap-2 overflow-x-auto pb-1 [scrollbar-width:none] [-ms-overflow-style:none] [&::-webkit-scrollbar]:hidden">
                        <button
                            type="button"
                            onClick={() => handleCategorySelect('')}
                            className={`whitespace-nowrap px-4 py-2 rounded-full text-xs font-semibold tracking-wide transition cursor-pointer min-h-[38px] ${
                                !safeFilters.category && !safeFilters.filter
                                    ? 'bg-[#1C1917] text-white shadow-xs'
                                    : 'bg-white text-[#1C1917] border border-[#EAE2D6] hover:border-[#A67C52] hover:bg-[#FAF7F2]'
                            }`}
                        >
                            {t('all_categories', 'All Formulations')}
                        </button>

                        {/* Best Sellers Pill - Filter within Shop Page */}
                        <button
                            type="button"
                            onClick={() => handleFilterSelect('bestseller')}
                            className={`whitespace-nowrap px-4 py-2 rounded-full text-xs font-medium tracking-wide transition cursor-pointer min-h-[38px] ${
                                safeFilters.filter === 'bestseller'
                                    ? 'bg-[#1C1917] text-[#D4A843] shadow-xs font-semibold border border-[#1C1917]'
                                    : 'bg-white text-[#1C1917] border border-[#EAE2D6] hover:border-[#A67C52] hover:bg-[#FAF7F2]'
                            }`}
                        >
                            <span className="text-[#D4A843] mr-1 font-bold">★</span>
                            <span>{t('best_sellers', 'Best Sellers')}</span>
                        </button>

                        {/* New Arrivals Pill */}
                        <button
                            type="button"
                            onClick={() => handleFilterSelect('new_arrival')}
                            className={`whitespace-nowrap px-4 py-2 rounded-full text-xs font-medium tracking-wide transition cursor-pointer min-h-[38px] ${
                                safeFilters.filter === 'new_arrival'
                                    ? 'bg-[#1C1917] text-emerald-300 shadow-xs font-semibold border border-[#1C1917]'
                                    : 'bg-white text-[#1C1917] border border-[#EAE2D6] hover:border-[#A67C52] hover:bg-[#FAF7F2]'
                            }`}
                        >
                            <span className="text-emerald-700 mr-1">🌿</span>
                            <span>{t('new_arrivals', 'New Arrivals')}</span>
                        </button>

                        {categories.map((cat) => {
                            const isSelected = safeFilters.category === cat.slug;
                            const catName = locale === 'si' && cat.name_si ? cat.name_si : cat.name_en;
                            return (
                                <button
                                    key={cat.id}
                                    type="button"
                                    onClick={() => handleCategorySelect(cat.slug)}
                                    className={`whitespace-nowrap px-4 py-2 rounded-full text-xs font-medium tracking-wide transition cursor-pointer min-h-[38px] ${
                                        isSelected
                                            ? 'bg-[#1C1917] text-white shadow-xs font-semibold'
                                            : 'bg-white text-[#1C1917] border border-[#EAE2D6] hover:border-[#A67C52] hover:bg-[#FAF7F2]'
                                    }`}
                                >
                                    <span>{catName}</span>
                                    {cat.products_count !== undefined && (
                                        <span className={`ml-1 text-[11px] ${isSelected ? 'text-amber-200' : 'text-stone-400'}`}>
                                            ({cat.products_count})
                                        </span>
                                    )}
                                </button>
                            );
                        })}
                    </div>
                </div>

                {/* 2B. Dedicated Clean Toolbar Row (Search, Sort, In-Stock, Count) */}
                <div className="bg-[#FAF7F2] rounded-2xl border border-[#EAE2D6] p-3.5 mb-8 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    {/* Left: Product Count & Active Filter Reset */}
                    <div className="flex items-center gap-3 flex-wrap">
                        <span className="text-xs font-medium text-stone-600">
                            Showing <strong className="text-[#1C1917] font-semibold">{products.total}</strong> {products.total === 1 ? 'formulation' : 'formulations'}
                        </span>

                        {activeCategory && (
                            <span className="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-[#1C1917] text-[#FAF7F2]">
                                <span>{locale === 'si' && activeCategory.name_si ? activeCategory.name_si : activeCategory.name_en}</span>
                                <button
                                    type="button"
                                    onClick={() => handleCategorySelect('')}
                                    className="text-[#D4A843] hover:text-white cursor-pointer ml-0.5 text-xs"
                                    title="Clear category"
                                >
                                    ✕
                                </button>
                            </span>
                        )}

                        {safeFilters.filter === 'bestseller' && (
                            <span className="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-[#1C1917] text-[#D4A843]">
                                <span>★ Best Sellers</span>
                                <button
                                    type="button"
                                    onClick={() => handleFilterSelect('bestseller')}
                                    className="text-stone-300 hover:text-white cursor-pointer ml-0.5 text-xs"
                                    title="Clear filter"
                                >
                                    ✕
                                </button>
                            </span>
                        )}

                        {safeFilters.filter === 'new_arrival' && (
                            <span className="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-900 text-emerald-200">
                                <span>🌿 New Arrivals</span>
                                <button
                                    type="button"
                                    onClick={() => handleFilterSelect('new_arrival')}
                                    className="text-stone-300 hover:text-white cursor-pointer ml-0.5 text-xs"
                                    title="Clear filter"
                                >
                                    ✕
                                </button>
                            </span>
                        )}

                        {(safeFilters.category || safeFilters.filter || safeFilters.search || safeFilters.in_stock) && (
                            <>
                                <span className="text-stone-300 hidden sm:inline">•</span>
                                <button
                                    type="button"
                                    onClick={clearAllFilters}
                                    className="text-xs text-[#8B3A2B] hover:text-[#52121C] font-semibold underline underline-offset-4 cursor-pointer"
                                >
                                    {t('clear_all', 'Reset Filters')}
                                </button>
                            </>
                        )}
                    </div>

                    {/* Right: Clean Controls in One Neat Row */}
                    <div className="flex items-center gap-3 flex-wrap sm:flex-nowrap justify-start md:justify-end">
                        {/* Category Type Filter Dropdown */}
                        <div className="relative shrink-0">
                            <select
                                value={safeFilters.category || (safeFilters.filter ? `filter:${safeFilters.filter}` : '')}
                                onChange={(e) => {
                                    const val = e.target.value;
                                    if (val.startsWith('filter:')) {
                                        handleFilterSelect(val.replace('filter:', ''));
                                    } else {
                                        handleCategorySelect(val);
                                    }
                                }}
                                aria-label={t('category', 'Category Filter')}
                                className="bg-white border border-[#EAE2D6] rounded-full py-2 pl-3.5 pr-8 text-xs font-medium text-[#1C1917] focus:outline-none focus:border-[#8B3A2B] focus:ring-0 cursor-pointer appearance-none"
                            >
                                <option value="">{t('all_categories', 'Category: All')}</option>
                                <option value="filter:bestseller">★ {t('best_sellers', 'Best Sellers')}</option>
                                <option value="filter:new_arrival">🌿 {t('new_arrivals', 'New Arrivals')}</option>
                                {categories.map((cat) => {
                                    const catName = locale === 'si' && cat.name_si ? cat.name_si : cat.name_en;
                                    return (
                                        <option key={cat.id} value={cat.slug}>
                                            {catName} {cat.products_count !== undefined ? `(${cat.products_count})` : ''}
                                        </option>
                                    );
                                })}
                            </select>
                            <svg className="w-3 h-3 text-stone-400 absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </div>

                        {/* Inline Search */}
                        <form onSubmit={handleSearchSubmit} className="relative flex-1 sm:w-52">
                            <input
                                type="text"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder={t('search_placeholder', 'Search products...')}
                                className="w-full bg-white border border-[#EAE2D6] rounded-full py-2 pl-3.5 pr-8 text-xs text-[#1C1917] focus:outline-none focus:border-[#8B3A2B] focus:ring-0"
                            />
                            {search ? (
                                <button
                                    type="button"
                                    onClick={() => {
                                        setSearch('');
                                        router.get(
                                            '/products',
                                            { ...safeFilters, search: undefined },
                                            { preserveState: true, preserveScroll: true }
                                        );
                                    }}
                                    className="absolute right-2.5 top-1/2 -translate-y-1/2 text-stone-400 hover:text-stone-700 text-xs cursor-pointer"
                                >
                                    ✕
                                </button>
                            ) : (
                                <button
                                    type="submit"
                                    aria-label="Search"
                                    className="absolute right-2.5 top-1/2 -translate-y-1/2 text-[#A67C52] hover:text-[#8B3A2B] cursor-pointer"
                                >
                                    <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z" />
                                    </svg>
                                </button>
                            )}
                        </form>

                        {/* Sort By Dropdown */}
                        <div className="relative shrink-0">
                            <select
                                value={selectedSort}
                                onChange={(e) => handleSortChange(e.target.value)}
                                aria-label={t('sort_by', 'Sort By')}
                                className="bg-white border border-[#EAE2D6] rounded-full py-2 pl-3.5 pr-8 text-xs font-medium text-[#1C1917] focus:outline-none focus:border-[#8B3A2B] focus:ring-0 cursor-pointer appearance-none"
                            >
                                <option value="newest">{t('newest', 'Sort: Newest')}</option>
                                <option value="price_low_high">{t('price_low_high', 'Price: Low to High')}</option>
                                <option value="price_high_low">{t('price_high_low', 'Price: High to Low')}</option>
                                <option value="name_asc">{t('name_asc', 'Name: A to Z')}</option>
                            </select>
                            <svg className="w-3 h-3 text-stone-400 absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </div>

                        {/* In Stock Only Checkbox */}
                        <label className="inline-flex items-center gap-2 bg-white border border-[#EAE2D6] rounded-full py-2 px-3.5 text-xs font-medium text-[#1C1917] cursor-pointer select-none hover:border-[#A67C52] transition shrink-0 min-h-[38px]">
                            <input
                                type="checkbox"
                                checked={inStock}
                                onChange={(e) => handleInStockToggle(e.target.checked)}
                                className="accent-[#1C1917] rounded text-[#1C1917] focus:ring-0 cursor-pointer"
                            />
                            <span>{t('in_stock_only', 'In Stock Only')}</span>
                        </label>
                    </div>
                </div>

                {/* 3. Luxury 4-Column Responsive Product Grid (4 cards per row) */}
                {products.data.length > 0 ? (
                    <div id="product-grid" className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                        {products.data.map((product) => (
                            <ProductCard key={product.id} product={product} />
                        ))}
                    </div>
                ) : (
                    /* 4. Empty State */
                    <div id="empty-state" className="text-center py-16">
                        <p className="font-serif text-2xl text-[#1C1917]">
                            {t('no_products_found', 'No botanical formulations match your filter.')}
                        </p>
                        <button
                            type="button"
                            onClick={clearAllFilters}
                            className="mt-4 px-5 py-2.5 rounded-full bg-[#1C1917] text-white text-xs font-semibold uppercase tracking-widest hover:bg-[#8B3A2B] transition cursor-pointer"
                        >
                            {t('clear_filters', 'Reset Filters')}
                        </button>
                    </div>
                )}

                {/* 5. Pagination Links */}
                {products.last_page > 1 && (
                    <div className="mt-12 flex items-center justify-center gap-2">
                        {products.links.map((link, i) => (
                            <Link
                                key={i}
                                href={link.url ?? '#'}
                                preserveScroll
                                preserveState
                                dangerouslySetInnerHTML={{ __html: link.label }}
                                className={`px-4 py-2 rounded-full text-xs font-medium transition cursor-pointer ${
                                    link.active
                                        ? 'bg-[#1C1917] text-white shadow-xs'
                                        : link.url
                                        ? 'bg-white border border-[#EAE2D6] text-[#1C1917] hover:border-[#A67C52]'
                                        : 'text-stone-400 cursor-not-allowed border border-transparent'
                                }`}
                            />
                        ))}
                    </div>
                )}
            </main>
        </PublicLayout>
    );
}
