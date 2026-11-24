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
    const [isAdding, setIsAdding] = useState(false);

    const handleQuickAdd = (e: React.MouseEvent) => {
        e.preventDefault();
        e.stopPropagation();
        if (product.stock_quantity <= 0 || isAdding) return;

        setIsAdding(true);
        router.post(
            '/cart/add',
            { product_id: product.id, quantity: 1 },
            {
                preserveScroll: true,
                onFinish: () => {
                    setTimeout(() => setIsAdding(false), 500);
                },
            }
        );
    };

    const name = locale === 'si' && product.name_si ? product.name_si : product.name_en;
    const shortDesc = locale === 'si' && product.short_description_si
        ? product.short_description_si
        : product.short_description_en;

    const inStock = product.stock_quantity > 0;
    const priceFormatted = Number(product.price).toLocaleString('en-US', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
    });
    const comparePriceFormatted = product.compare_at_price
        ? Number(product.compare_at_price).toLocaleString('en-US', {
              minimumFractionDigits: 0,
              maximumFractionDigits: 2,
          })
        : null;

    // Derived discount percentage
    const discountPercent = product.compare_at_price && Number(product.compare_at_price) > Number(product.price)
        ? Math.round(((Number(product.compare_at_price) - Number(product.price)) / Number(product.compare_at_price)) * 100)
        : null;

    const categoryName = product.category
        ? (locale === 'si' && product.category.name_si ? product.category.name_si : product.category.name_en)
        : 'Royal Botanicals';

    return (
        <article className="bg-white rounded-3xl border border-[#EAE2D6] overflow-hidden flex flex-col justify-between transition-all duration-300 hover:-translate-y-1 hover:shadow-lg hover:border-[#A67C52]/60 group relative">
            <div>
                {/* Clean Image Frame with warm beige gradient */}
                <Link
                    href={`/products/${product.slug}`}
                    className="relative bg-gradient-to-b from-[#F5EFE6]/70 to-[#FAF7F2] pt-8 pb-6 px-6 aspect-[4/3] flex items-center justify-center overflow-hidden cursor-pointer block"
                >
                    {/* Top Badges: Tag on Left, Discount on Right */}
                    <div className="absolute top-4 left-4 right-4 flex items-center justify-between z-10 pointer-events-none">
                        <div>
                            {product.is_best_seller && (
                                <span className="px-3 py-1 rounded-full text-[10px] font-bold tracking-[0.15em] uppercase bg-[#1C1917] text-[#D4A843] shadow-xs">
                                    {t('best_sellers', 'Best Sellers')}
                                </span>
                            )}
                            {!product.is_best_seller && product.is_new_arrival && (
                                <span className="px-3 py-1 rounded-full text-[10px] font-bold tracking-[0.15em] uppercase bg-[#1E3F2B] text-[#FAF7F2] shadow-xs">
                                    {t('new_arrivals', 'New Arrivals')}
                                </span>
                            )}
                        </div>

                        {discountPercent && inStock && (
                            <span className="px-2.5 py-1 rounded-full text-[10px] font-bold bg-[#8B3A2B] text-white shadow-xs">
                                -{discountPercent}%
                            </span>
                        )}
                    </div>

                    {/* Product Artwork Image or SVG Fallback */}
                    {(!imageError && (product.primary_image?.path || product.images?.[0]?.path)) ? (
                        <img
                            src={`/storage/${product.primary_image?.path || product.images?.[0]?.path}`}
                            alt={name}
                            onError={() => setImageError(true)}
                            className="max-h-44 w-auto object-contain group-hover:scale-105 transition-transform duration-500 ease-out"
                            loading="lazy"
                        />
                    ) : (
                        /* Self-contained Luxury Botanical Flacon / Jar Artwork */
                        <div className="w-full h-full flex flex-col items-center justify-center text-center p-4 group-hover:scale-105 transition-transform duration-500">
                            <div className="w-16 h-16 rounded-full bg-white/80 border border-[#D4A843]/40 flex items-center justify-center shadow-xs mb-2">
                                <span className="text-2xl">🌿</span>
                            </div>
                            <span className="font-serif text-xs font-semibold tracking-[0.2em] text-[#1C1917] uppercase">
                                AURA LUXE
                            </span>
                            <span className="text-[9px] font-sans font-medium tracking-[0.16em] text-[#A67C52] mt-0.5 uppercase">
                                {product.sku ?? 'ROYAL APOTHECARY'}
                            </span>
                        </div>
                    )}

                    {/* Out of stock overlay */}
                    {!inStock && (
                        <div className="absolute inset-0 bg-[#1C1917]/75 backdrop-blur-[2px] flex items-center justify-center z-20">
                            <span className="bg-[#FAF7F2] text-[#1C1917] px-4 py-1.5 rounded-full text-xs font-sans font-semibold tracking-wider uppercase border border-[#EAE2D6]">
                                {t('out_of_stock', 'Out of Stock')}
                            </span>
                        </div>
                    )}
                </Link>

                {/* Minimal Card Content */}
                <div className="p-5 pb-3">
                    <div className="flex items-center justify-between gap-2 mb-1.5">
                        <span className="text-[10px] font-semibold tracking-[0.18em] uppercase text-[#9A4E22] font-sans">
                            {categoryName}
                        </span>
                        <span className="text-xs font-medium text-[#1C1917] flex items-center gap-1 font-sans">
                            <span className="text-[#D4A843]">★</span>
                            <span className="tabular-nums">4.9</span>
                        </span>
                    </div>

                    <Link
                        href={`/products/${product.slug}`}
                        className="font-serif text-xl sm:text-[22px] font-semibold text-[#1C1917] leading-snug tracking-[-0.01em] group-hover:text-[#9A4E22] transition-colors line-clamp-1 block"
                    >
                        {name}
                    </Link>

                    <p className="mt-1.5 text-xs text-[#1C1917]/70 line-clamp-2 leading-relaxed font-normal font-sans">
                        {shortDesc || (locale === 'si'
                            ? 'පිරිසිදු ශ්‍රී ලාංකික ශාකසාර හා අයුර්වේද සාරයෙන් පෝෂණය කළ උසස් සත්කාරය.'
                            : 'Formulated with organic Ceylon botanicals to nourish and restore skin radiance.')}
                    </p>
                </div>
            </div>

            {/* Price & Action Row */}
            <div className="px-5 pb-5 pt-3 border-t border-[#EAE2D6]/60 flex items-center justify-between gap-2">
                <div className="flex items-baseline gap-1.5 flex-wrap">
                    <span className="font-sans text-base sm:text-lg font-bold text-[#1C1917] tabular-nums tracking-tight whitespace-nowrap">
                        LKR {priceFormatted}
                    </span>
                    {comparePriceFormatted && (
                        <span className="font-sans text-[11px] text-stone-400 line-through tabular-nums whitespace-nowrap">
                            LKR {comparePriceFormatted}
                        </span>
                    )}
                </div>

                {inStock ? (
                    <button
                        type="button"
                        onClick={handleQuickAdd}
                        disabled={isAdding}
                        className="px-3.5 sm:px-4 py-2 rounded-full bg-[#1C1917] hover:bg-[#1E3F2B] text-white text-[10px] sm:text-[11px] font-semibold tracking-[0.12em] uppercase transition cursor-pointer min-h-[38px] inline-flex items-center justify-center shadow-xs shrink-0 whitespace-nowrap"
                    >
                        {isAdding ? (
                            <span className="flex items-center gap-1">
                                <svg className="animate-spin h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24">
                                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span>{t('adding', 'Adding...')}</span>
                            </span>
                        ) : (
                            <span>{t('add_to_bag', 'Add to Bag')}</span>
                        )}
                    </button>
                ) : (
                    <span className="text-[10px] font-semibold tracking-[0.12em] uppercase text-stone-400 px-2 py-1 shrink-0 whitespace-nowrap">
                        {t('sold_out', 'Sold Out')}
                    </span>
                )}
            </div>
        </article>
    );
}
