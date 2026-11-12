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
    const [minPrice, setMinPrice] = useState(typeof safeFilters.min_price === 'string' ? safeFilters.min_price : '');
    const [maxPrice, setMaxPrice] = useState(typeof safeFilters.max_price === 'string' ? safeFilters.max_price : '');
    const [inStock, setInStock] = useState(safeFilters.in_stock ? true : false);
    const [selectedSort, setSelectedSort] = useState(
        typeof safeFilters.sort === 'string' ? safeFilters.sort : 'newest'
    );

    const handleFilterSubmit = (e?: React.FormEvent) => {
        if (e) e.preventDefault();
        router.get(
            '/products',
            {
                category: safeFilters.category,
                filter: typeof safeFilters.filter === 'string' ? safeFilters.filter : undefined,
                search: search || undefined,
                min_price: minPrice || undefined,
                max_price: maxPrice || undefined,
                in_stock: inStock ? '1' : undefined,
                sort: selectedSort !== 'newest' ? selectedSort : undefined,
            },
            { preserveState: true }
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
            { preserveState: true }
        );
    };

    const clearAllFilters = () => {
        router.get('/products');
    };

    const activeCategory = categories.find((c) => c.slug === safeFilters.category);

    return (
        <PublicLayout>
            <Head title={`${t('shop', 'Shop')} | ${t('app_name', 'Aura Luxe')}`} />

            {/* Header banner */}
            <div className="bg-[#F5EFE6] border-b border-stone-200/80 py-10 lg:py-14">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <span className="text-xs uppercase tracking-[0.2em] font-semibold text-rose-900 block mb-1">
                        {t('app_name', 'Aura Luxe Cosmetics')}
                    </span>
                    <h1 className="font-serif text-3xl sm:text-4xl text-stone-900 font-medium">
                        {activeCategory
                            ? locale === 'si' && activeCategory.name_si
                                ? activeCategory.name_si
                                : activeCategory.name_en
                            : t('all_categories', 'All Products')}
                    </h1>
                    <p className="text-xs sm:text-sm text-stone-600 mt-2 max-w-xl">
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
            </div>

            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
                <div className="flex flex-col lg:flex-row gap-8">
                    {/* Filters Sidebar */}
                    <aside className="w-full lg:w-64 shrink-0 space-y-6">
                        {/* Search Bar */}
                        <div className="bg-white p-5 rounded-xl border border-stone-200 shadow-xs">
                            <h3 className="text-xs font-semibold uppercase tracking-wider text-stone-900 mb-3">
                                {t('search_placeholder', 'Search')}
                            </h3>
                            <form onSubmit={handleFilterSubmit} className="flex gap-2">
                                <input
                                    type="text"
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    placeholder={t('search_placeholder', 'Search products...')}
                                    className="w-full text-xs rounded-lg border-stone-300 focus:border-rose-900 focus:ring-rose-900"
                                />
                                <button
                                    type="submit"
                                    className="px-3 py-2 bg-stone-900 hover:bg-rose-900 text-white rounded-lg text-xs font-semibold transition-colors cursor-pointer"
                                >
                                    🔍
                                </button>
                            </form>
                        </div>

                        {/* Categories List */}
                        <div className="bg-white p-5 rounded-xl border border-stone-200 shadow-xs">
                            <div className="flex items-center justify-between mb-3">
                                <h3 className="text-xs font-semibold uppercase tracking-wider text-stone-900">
                                    {t('categories', 'Categories')}
                                </h3>
                                {safeFilters.category && (
                                    <Link
                                        href="/products"
                                        className="text-[11px] text-rose-800 hover:underline"
                                    >
                                        {t('clear_filters', 'Clear')}
                                    </Link>
                                )}
                            </div>
                            <ul className="space-y-1.5 text-xs">
                                <li>
                                    <Link
                                        href="/products"
                                        className={`block py-1.5 px-2 rounded-md transition-colors ${
                                            !safeFilters.category
                                                ? 'bg-stone-900 text-white font-medium'
                                                : 'text-stone-600 hover:bg-stone-50'
                                        }`}
                                    >
                                        {t('all_categories', 'All Products')}
                                    </Link>
                                </li>
                                {categories.map((cat) => (
                                    <li key={cat.id}>
                                        <Link
                                            href={`/products?category=${cat.slug}`}
                                            className={`flex items-center justify-between py-1.5 px-2 rounded-md transition-colors ${
                                                safeFilters.category === cat.slug
                                                    ? 'bg-stone-900 text-white font-medium'
                                                    : 'text-stone-600 hover:bg-stone-50'
                                            }`}
                                        >
                                            <span>
                                                {locale === 'si' && cat.name_si ? cat.name_si : cat.name_en}
                                            </span>
                                            {cat.products_count !== undefined && (
                                                <span className="text-[10px] opacity-70">
                                                    ({cat.products_count})
                                                </span>
                                            )}
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        </div>

                        {/* Price & Stock Filter */}
                        <div className="bg-white p-5 rounded-xl border border-stone-200 shadow-xs space-y-4">
                            <h3 className="text-xs font-semibold uppercase tracking-wider text-stone-900">
                                {t('filter_by_price', 'Price & Availability')}
                            </h3>

                            <div className="grid grid-cols-2 gap-2 text-xs">
                                <div>
                                    <label className="text-[10px] text-stone-500 block mb-1">
                                        {t('min_price', 'Min (LKR)')}
                                    </label>
                                    <input
                                        type="number"
                                        value={minPrice}
                                        onChange={(e) => setMinPrice(e.target.value)}
                                        placeholder="0"
                                        className="w-full text-xs rounded-lg border-stone-300 focus:border-rose-900 focus:ring-rose-900"
                                    />
                                </div>
                                <div>
                                    <label className="text-[10px] text-stone-500 block mb-1">
                                        {t('max_price', 'Max (LKR)')}
                                    </label>
                                    <input
                                        type="number"
                                        value={maxPrice}
                                        onChange={(e) => setMaxPrice(e.target.value)}
                                        placeholder="10000"
                                        className="w-full text-xs rounded-lg border-stone-300 focus:border-rose-900 focus:ring-rose-900"
                                    />
                                </div>
                            </div>

                            <label className="flex items-center gap-2 text-xs text-stone-700 cursor-pointer">
                                <input
                                    type="checkbox"
                                    checked={inStock}
                                    onChange={(e) => setInStock(e.target.checked)}
                                    className="rounded border-stone-300 text-rose-900 focus:ring-rose-900"
                                />
                                <span>{t('in_stock_only', 'In Stock Only')}</span>
                            </label>

                            <div className="flex gap-2 pt-2">
                                <button
                                    type="button"
                                    onClick={() => handleFilterSubmit()}
                                    className="flex-1 py-2 bg-stone-900 hover:bg-rose-900 text-white rounded-lg text-xs font-semibold transition-colors cursor-pointer"
                                >
                                    {t('filter', 'Apply')}
                                </button>
                                <button
                                    type="button"
                                    onClick={clearAllFilters}
                                    className="px-3 py-2 border border-stone-300 hover:bg-stone-50 text-stone-600 rounded-lg text-xs font-medium transition-colors cursor-pointer"
                                >
                                    {t('clear_filters', 'Reset')}
                                </button>
                            </div>
                        </div>
                    </aside>

                    {/* Main Products Grid */}
                    <div className="flex-1">
                        {/* Top Sort & Count Bar */}
                        <div className="bg-white p-4 rounded-xl border border-stone-200 mb-6 flex flex-col sm:flex-row items-center justify-between gap-4 shadow-xs">
                            <span className="text-xs text-stone-500">
                                {t('showing_products', 'Showing')}{' '}
                                <strong className="text-stone-900 font-medium">
                                    {products.total}
                                </strong>{' '}
                                {t('shop', 'products')}
                            </span>

                            {/* Sort Dropdown */}
                            <div className="flex items-center gap-2 text-xs w-full sm:w-auto">
                                <label className="text-stone-500 shrink-0">
                                    {t('sort_by', 'Sort by')}:
                                </label>
                                <select
                                    value={selectedSort}
                                    onChange={(e) => handleSortChange(e.target.value)}
                                    aria-label={t('sort_by', 'Sort products')}
                                    className="text-xs rounded-lg border-stone-300 focus:border-rose-900 focus:ring-rose-900 py-1.5 pl-3 pr-8 w-full sm:w-auto"
                                >
                                    <option value="newest">{t('newest', 'Newest Arrivals')}</option>
                                    <option value="price_low_high">
                                        {t('price_low_high', 'Price: Low to High')}
                                    </option>
                                    <option value="price_high_low">
                                        {t('price_high_low', 'Price: High to Low')}
                                    </option>
                                    <option value="name_asc">{t('name_asc', 'Name: A to Z')}</option>
                                </select>
                            </div>
                        </div>

                        {/* Products Grid */}
                        {products.data.length > 0 ? (
                            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                                {products.data.map((product) => (
                                    <ProductCard key={product.id} product={product} />
                                ))}
                            </div>
                        ) : (
                            <div className="bg-white rounded-xl border border-stone-200 p-12 text-center my-6">
                                <svg
                                    className="w-12 h-12 text-stone-300 mx-auto mb-3"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        strokeWidth="1.5"
                                        d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"
                                    />
                                </svg>
                                <h3 className="font-serif text-lg text-stone-800 font-medium mb-1">
                                    {t('no_products_found', 'No products found')}
                                </h3>
                                <p className="text-xs text-stone-500 mb-4">
                                    Try clearing some of your search filters or browse other categories.
                                </p>
                                <button
                                    onClick={clearAllFilters}
                                    className="px-4 py-2 bg-stone-900 text-white text-xs font-semibold uppercase tracking-wider rounded-lg hover:bg-rose-900 transition-colors cursor-pointer"
                                >
                                    {t('clear_filters', 'Clear All Filters')}
                                </button>
                            </div>
                        )}

                        {/* Pagination Links */}
                        {products.last_page > 1 && (
                            <div className="mt-10 flex items-center justify-center gap-1.5">
                                {products.links.map((link, i) => (
                                    <Link
                                        key={i}
                                        href={link.url ?? '#'}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                        className={`px-3 py-1.5 rounded-md text-xs font-medium transition-colors ${
                                            link.active
                                                ? 'bg-stone-900 text-white shadow-xs'
                                                : link.url
                                                ? 'bg-white border border-stone-200 text-stone-700 hover:bg-stone-50'
                                                : 'text-stone-400 cursor-not-allowed'
                                        }`}
                                    />
                                ))}
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </PublicLayout>
    );
}

 