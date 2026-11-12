import { useTranslation } from '@/hooks/useTranslation';
import { Product } from '@/types';
import { Link, router } from '@inertiajs/react';
import React, { useState } from 'react';

interface Props {
    product: Product;
}

export default function ProductCard({ product }: Props) {
    const { t, locale } = useTranslation();
    const [imageError, setImageError] = useState(false);

    const handleQuickAdd = (e: React.MouseEvent) => {
        e.preventDefault();
        e.stopPropagation();
        if (product.stock_quantity <= 0) return;
        router.post('/cart/add', { product_id: product.id, quantity: 1 }, { preserveScroll: true });
    };

    const name = locale === 'si' && product.name_si ? product.name_si : product.name_en;
    const shortDesc = locale === 'si' && product.short_description_si
        ? product.short_description_si
        : product.short_description_en;

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

    return (
        <div className="group bg-white rounded-xl border border-stone-200/80 hover:border-rose-300/80 shadow-xs hover:shadow-md transition-all duration-300 flex flex-col overflow-hidden">
            {/* Image container */}
            <div className="relative aspect-square bg-stone-100 overflow-hidden flex items-center justify-center">
                {(!imageError && (product.primary_image?.path || product.images?.[0]?.path)) ? (
                    <img
                        src={`/storage/${product.primary_image?.path || product.images?.[0]?.path}`}
                        alt={name}
                        onError={() => setImageError(true)}
                        className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                    />
                ) : (
                    /* Fallback elegant beauty illustration */
                    <div className="w-full h-full p-6 flex flex-col items-center justify-center text-stone-400 group-hover:scale-105 transition-transform duration-500">
                        <svg className="w-16 h-16 text-rose-300/70" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                        </svg>
                        <span className="text-[10px] uppercase tracking-widest text-stone-400 mt-2 font-mono">
                            {product.sku ?? 'AURA LUXE'}
                        </span>
                    </div>
                )}

                {/* Status Badges */}
                <div className="absolute top-3 left-3 flex flex-col gap-1.5">
                    {product.is_best_seller && (
                        <span className="bg-rose-900 text-rose-100 text-[10px] font-semibold uppercase tracking-wider px-2.5 py-0.5 rounded-full shadow-xs">
                            {t('best_sellers', 'Best Seller')}
                        </span>
                    )}
                    {product.is_new_arrival && (
                        <span className="bg-amber-800 text-amber-100 text-[10px] font-semibold uppercase tracking-wider px-2.5 py-0.5 rounded-full shadow-xs">
                            {t('new_arrivals', 'New Arrival')}
                        </span>
                    )}
                </div>

                {!inStock && (
                    <div className="absolute inset-0 bg-stone-900/60 backdrop-blur-[2px] flex items-center justify-center">
                        <span className="bg-white text-stone-900 text-xs font-bold uppercase tracking-wider px-3 py-1 rounded">
                            {t('out_of_stock', 'Out of Stock')}
                        </span>
                    </div>
                )}
            </div>

            {/* Product Meta & Details */}
            <div className="p-5 flex-1 flex flex-col justify-between">
                <div>
                    {product.category && (
                        <span className="text-[11px] font-medium text-rose-800 tracking-wider uppercase block mb-1">
                            {locale === 'si' && product.category.name_si
                                ? product.category.name_si
                                : product.category.name_en}
                        </span>
                    )}

                    <Link
                        href={`/products/${product.slug}`}
                        className="font-serif text-base font-medium text-stone-900 group-hover:text-rose-900 transition-colors line-clamp-1"
                    >
                        {name}
                    </Link>

                    {shortDesc && (
                        <p className="text-xs text-stone-500 mt-1.5 line-clamp-2 leading-relaxed">
                            {shortDesc}
                        </p>
                    )}
                </div>

                {/* Price and Cart Action */}
                <div className="mt-4 pt-3 border-t border-stone-100 flex items-center justify-between">
                    <div>
                        <div className="flex items-baseline gap-2">
                            <span className="font-semibold text-stone-900 text-sm">
                                LKR {priceFormatted}
                            </span>
                            {comparePriceFormatted && (
                                <span className="text-xs text-stone-400 line-through">
                                    LKR {comparePriceFormatted}
                                </span>
                            )}
                        </div>
                        <span className="text-[10px] text-emerald-700 font-medium block">
                            {t('cash_on_delivery', 'Cash on Delivery')}
                        </span>
                    </div>

                    {inStock ? (
                        <button
                            type="button"
                            onClick={handleQuickAdd}
                            className="text-xs uppercase tracking-wider font-semibold px-3.5 py-1.5 bg-stone-900 hover:bg-rose-900 text-white rounded-lg transition-colors shadow-xs cursor-pointer"
                        >
                            {t('add_to_cart', 'Add')}
                        </button>
                    ) : (
                        <Link
                            href={`/products/${product.slug}`}
                            className="text-xs uppercase tracking-wider font-semibold px-3 py-1.5 bg-stone-200 text-stone-500 rounded-lg cursor-not-allowed"
                        >
                            {t('out_of_stock', 'Out of Stock')}
                        </Link>
                    )}
                </div>
            </div>
        </div>
    );
}

