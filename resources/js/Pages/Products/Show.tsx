import ProductCard from '@/Components/ProductCard';
import { useTranslation } from '@/hooks/useTranslation';
import PublicLayout from '@/Layouts/PublicLayout';
import { Product } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import React, { useState } from 'react';

interface Props {
    product: Product;
    relatedProducts: Product[];
}

export default function ProductShow({ product, relatedProducts }: Props) {
    const { t, locale } = useTranslation();
    const [quantity, setQuantity] = useState(1);
    const [adding, setAdding] = useState(false);
    const [activeTab, setActiveTab] = useState<'desc' | 'ingredients' | 'directions'>('desc');
    const [selectedImageIndex, setSelectedImageIndex] = useState(0);
    const [imageError, setImageError] = useState(false);

    const name = locale === 'si' && product.name_si ? product.name_si : product.name_en;
    const desc = locale === 'si' && product.description_si ? product.description_si : product.description_en;
    const ingredients = locale === 'si' && product.ingredients_si ? product.ingredients_si : product.ingredients_en;
    const directions = locale === 'si' && product.directions_si ? product.directions_si : product.directions_en;

    const inStock = product.stock_quantity > 0;
    const priceFormatted = Number(product.price).toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
    const comparePriceFormatted = product.compare_at_price
        ? Number(product.compare_at_price).toLocaleString('en-US', {
              minimumFractionDigits: 2,
              maximumFractionDigits: 2,
          })
        : null;

    const handleQuantityChange = (delta: number) => {
        const next = quantity + delta;
        if (next >= 1 && next <= product.stock_quantity) {
            setQuantity(next);
        }
    };

    const handleAddToCart = (e: React.FormEvent) => {
        e.preventDefault();
        if (!inStock || adding) return;

        setAdding(true);
        router.post(
            '/cart/add',
            { product_id: product.id, quantity },
            {
                preserveScroll: true,
                onFinish: () => setAdding(false),
            }
        );
    };

    return (
        <PublicLayout>
            <Head title={`${name} | ${t('app_name', 'Aura Luxe')}`} />

            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12">
                {/* Breadcrumbs */}
                <nav className="flex items-center space-x-2 text-xs text-stone-500 mb-8 font-medium">
                    <Link href="/" className="hover:text-stone-900 transition-colors">
                        {t('home', 'Home')}
                    </Link>
                    <span>/</span>
                    <Link href="/products" className="hover:text-stone-900 transition-colors">
                        {t('shop', 'Shop')}
                    </Link>
                    {product.category && (
                        <>
                            <span>/</span>
                            <Link
                                href={`/products?category=${product.category.slug}`}
                                className="hover:text-stone-900 transition-colors"
                            >
                                {locale === 'si' && product.category.name_si
                                    ? product.category.name_si
                                    : product.category.name_en}
                            </Link>
                        </>
                    )}
                    <span>/</span>
                    <span className="text-stone-900 truncate max-w-xs">{name}</span>
                </nav>

                {/* Product Hero Details Grid */}
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-12 lg:gap-16">
                    {/* Left: Product Images Gallery */}
                    <div className="space-y-4">
                        <div className="aspect-square bg-white rounded-2xl border border-stone-200/80 overflow-hidden relative shadow-xs flex items-center justify-center">
                            {(!imageError && product.images && product.images.length > 0) ? (
                                <img
                                    src={`/storage/${product.images[selectedImageIndex]?.path || product.images[0]?.path}`}
                                    alt={name}
                                    onError={() => setImageError(true)}
                                    className="w-full h-full object-cover transition-opacity duration-300"
                                />
                            ) : (
                                <div className="p-8 flex flex-col items-center justify-center text-center">
                                    <svg className="w-32 h-32 text-rose-300/80" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                                    </svg>
                                    <span className="text-xs uppercase tracking-widest text-stone-400 font-mono mt-4">
                                        {product.sku ?? 'AURA LUXE BOTANICAL'}
                                    </span>
                                </div>
                            )}

                            {/* Badges */}
                            <div className="absolute top-4 left-4 flex flex-col gap-2">
                                {product.is_best_seller && (
                                    <span className="bg-rose-900 text-rose-100 text-[10px] font-semibold uppercase tracking-wider px-3 py-1 rounded-full shadow-xs">
                                        {t('best_sellers', 'Best Seller')}
                                    </span>
                                )}
                                {product.is_new_arrival && (
                                    <span className="bg-amber-800 text-amber-100 text-[10px] font-semibold uppercase tracking-wider px-3 py-1 rounded-full shadow-xs">
                                        {t('new_arrivals', 'New Arrival')}
                                    </span>
                                )}
                            </div>
                        </div>

                        {/* Image Thumbnails if multiple images exist */}
                        {product.images && product.images.length > 1 && (
                            <div className="flex gap-3 overflow-x-auto pb-2">
                                {product.images.map((img, idx) => (
                                    <button
                                        key={img.id}
                                        type="button"
                                        onClick={() => {
                                            setSelectedImageIndex(idx);
                                            setImageError(false);
                                        }}
                                        className={`w-20 h-20 rounded-lg overflow-hidden border-2 transition-all flex-shrink-0 ${
                                            selectedImageIndex === idx
                                                ? 'border-rose-900 shadow-xs'
                                                : 'border-stone-200 hover:border-stone-400 opacity-70 hover:opacity-100'
                                        }`}
                                    >
                                        <img
                                            src={`/storage/${img.path}`}
                                            alt={img.alt_text ?? `${name} thumbnail ${idx + 1}`}
                                            className="w-full h-full object-cover"
                                        />
                                    </button>
                                ))}
                            </div>
                        )}

                        {/* Trust highlights under image */}
                        <div className="grid grid-cols-3 gap-3 text-center py-4 bg-stone-50 rounded-xl border border-stone-200 text-xs text-stone-600">
                            <div>
                                <span className="font-semibold text-stone-900 block text-xs">Cash on Delivery</span>
                                <span className="text-[10px] text-stone-500">Pay upon delivery</span>
                            </div>
                            <div>
                                <span className="font-semibold text-stone-900 block text-xs">Pure Botanical</span>
                                <span className="text-[10px] text-stone-500">Paraben & Cruelty Free</span>
                            </div>
                            <div>
                                <span className="font-semibold text-stone-900 block text-xs">Fast Dispatch</span>
                                <span className="text-[10px] text-stone-500">2-4 working days</span>
                            </div>
                        </div>
                    </div>

                    {/* Right: Info & Actions */}
                    <div className="flex flex-col justify-between space-y-6">
                        <div>
                            {product.category && (
                                <Link
                                    href={`/products?category=${product.category.slug}`}
                                    className="text-xs uppercase tracking-[0.2em] font-semibold text-rose-800 hover:text-rose-900 block mb-2"
                                >
                                    {locale === 'si' && product.category.name_si
                                        ? product.category.name_si
                                        : product.category.name_en}
                                </Link>
                            )}

                            <h1 className="font-serif text-3xl sm:text-4xl text-stone-900 font-medium tracking-tight">
                                {name}
                            </h1>

                            {product.sku && (
                                <span className="text-xs text-stone-400 font-mono block mt-1">
                                    SKU: {product.sku}
                                </span>
                            )}

                            {/* Price Block */}
                            <div className="mt-5 pb-5 border-b border-stone-200">
                                <div className="flex items-baseline gap-3">
                                    <span className="font-serif text-3xl font-medium text-stone-900">
                                        LKR {priceFormatted}
                                    </span>
                                    {comparePriceFormatted && (
                                        <span className="text-sm text-stone-400 line-through">
                                            LKR {comparePriceFormatted}
                                        </span>
                                    )}
                                </div>
                                <span className="text-xs text-emerald-700 font-medium block mt-1">
                                    ✓ {t('cash_on_delivery', 'Cash on Delivery Available Islandwide')}
                                </span>
                            </div>

                            {/* Short Description */}
                            <p className="mt-5 text-sm text-stone-600 leading-relaxed">
                                {locale === 'si' && product.short_description_si
                                    ? product.short_description_si
                                    : product.short_description_en ?? desc}
                            </p>

                            {/* Stock Indicator */}
                            <div className="mt-6 flex items-center gap-2 text-xs">
                                <span
                                    className={`w-2.5 h-2.5 rounded-full ${
                                        inStock ? 'bg-emerald-500' : 'bg-rose-500'
                                    }`}
                                />
                                {inStock ? (
                                    <span className="text-emerald-800 font-medium">
                                        {t('in_stock', 'In Stock')} ({product.stock_quantity}{' '}
                                        {t('items_ordered', 'units available')})
                                    </span>
                                ) : (
                                    <span className="text-rose-800 font-medium">
                                        {t('out_of_stock', 'Temporarily Out of Stock')}
                                    </span>
                                )}
                            </div>

                            {/* Purchase Form */}
                            <form onSubmit={handleAddToCart} className="mt-8 space-y-4">
                                <div className="flex items-center gap-4">
                                    {/* Quantity Counter */}
                                    <div className="flex items-center border border-stone-300 rounded-xl bg-white shadow-xs">
                                        <button
                                            type="button"
                                            disabled={!inStock || quantity <= 1}
                                            onClick={() => handleQuantityChange(-1)}
                                            className="px-3.5 py-3 text-stone-600 hover:text-stone-900 disabled:opacity-30 cursor-pointer"
                                            aria-label="Decrease quantity"
                                        >
                                            −
                                        </button>
                                        <span className="w-10 text-center text-sm font-semibold text-stone-900">
                                            {quantity}
                                        </span>
                                        <button
                                            type="button"
                                            disabled={!inStock || quantity >= product.stock_quantity}
                                            onClick={() => handleQuantityChange(1)}
                                            className="px-3.5 py-3 text-stone-600 hover:text-stone-900 disabled:opacity-30 cursor-pointer"
                                            aria-label="Increase quantity"
                                        >
                                            +
                                        </button>
                                    </div>

                                    {/* Add to Cart Button */}
                                    <button
                                        type="submit"
                                        disabled={!inStock || adding}
                                        className="flex-1 py-3.5 px-6 bg-stone-900 hover:bg-rose-900 disabled:bg-stone-300 text-white text-xs uppercase tracking-[0.2em] font-semibold rounded-xl shadow-md transition-all cursor-pointer disabled:cursor-not-allowed"
                                    >
                                        {adding
                                            ? 'Adding...'
                                            : inStock
                                            ? t('add_to_cart', 'Add to Cart')
                                            : t('out_of_stock', 'Out of Stock')}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                {/* Tabs: Description, Ingredients, Directions */}
                <div className="mt-16 bg-white rounded-2xl border border-stone-200 p-8 shadow-xs">
                    <div className="border-b border-stone-200 flex space-x-8 text-xs font-semibold uppercase tracking-wider">
                        <button
                            type="button"
                            onClick={() => setActiveTab('desc')}
                            className={`pb-4 border-b-2 transition-colors cursor-pointer ${
                                activeTab === 'desc'
                                    ? 'border-stone-900 text-stone-900'
                                    : 'border-transparent text-stone-400 hover:text-stone-700'
                            }`}
                        >
                            Description
                        </button>
                        {ingredients && (
                            <button
                                type="button"
                                onClick={() => setActiveTab('ingredients')}
                                className={`pb-4 border-b-2 transition-colors cursor-pointer ${
                                    activeTab === 'ingredients'
                                        ? 'border-stone-900 text-stone-900'
                                        : 'border-transparent text-stone-400 hover:text-stone-700'
                                }`}
                            >
                                {t('ingredients', 'Key Ingredients')}
                            </button>
                        )}
                        {directions && (
                            <button
                                type="button"
                                onClick={() => setActiveTab('directions')}
                                className={`pb-4 border-b-2 transition-colors cursor-pointer ${
                                    activeTab === 'directions'
                                        ? 'border-stone-900 text-stone-900'
                                        : 'border-transparent text-stone-400 hover:text-stone-700'
                                }`}
                            >
                                {t('directions_for_use', 'Directions for Use')}
                            </button>
                        )}
                    </div>

                    <div className="pt-6 text-sm text-stone-600 leading-relaxed">
                        {activeTab === 'desc' && (
                            <div className="prose prose-stone max-w-none text-xs sm:text-sm">
                                <p>{desc ?? 'Pure botanical formulation crafted for daily beauty care.'}</p>
                            </div>
                        )}
                        {activeTab === 'ingredients' && (
                            <div className="bg-stone-50 p-5 rounded-xl border border-stone-200 text-xs sm:text-sm font-mono text-stone-700">
                                {ingredients}
                            </div>
                        )}
                        {activeTab === 'directions' && (
                            <div className="bg-stone-50 p-5 rounded-xl border border-stone-200 text-xs sm:text-sm text-stone-700">
                                {directions}
                            </div>
                        )}
                    </div>
                </div>

                {/* Related Products */}
                {relatedProducts.length > 0 && (
                    <div className="mt-20">
                        <div className="text-center mb-10">
                            <span className="text-xs uppercase tracking-widest text-rose-900 font-semibold block mb-1">
                                {t('related_products', 'Complete Your Ritual')}
                            </span>
                            <h2 className="font-serif text-3xl text-stone-900 font-medium">
                                {locale === 'si' ? 'ඔබ කැමති විය හැකි වෙනත් නිෂ්පාදන' : 'You May Also Love'}
                            </h2>
                        </div>
                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                            {relatedProducts.map((p) => (
                                <ProductCard key={p.id} product={p} />
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </PublicLayout>
    );
}

