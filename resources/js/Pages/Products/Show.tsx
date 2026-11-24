import ProductCard from '@/Components/ProductCard';
import RecentlyViewedProducts from '@/Components/RecentlyViewedProducts';
import { useRecentlyViewed } from '@/hooks/useRecentlyViewed';
import { useTranslation } from '@/hooks/useTranslation';
import PublicLayout from '@/Layouts/PublicLayout';
import { Product } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import React, { useEffect, useState } from 'react';

interface Props {
    product: Product;
    relatedProducts: Product[];
}

export default function ProductShow({ product, relatedProducts }: Props) {
    const { t, locale } = useTranslation();
    const { auth } = usePage().props as any;
    const { recordGuestView } = useRecentlyViewed(auth?.user);

    const [quantity, setQuantity] = useState(1);
    const [adding, setAdding] = useState(false);
    const [lightboxIndex, setLightboxIndex] = useState<number | null>(null);
    const [imageError, setImageError] = useState(false);

    useEffect(() => {
        const handleKeyDown = (e: KeyboardEvent) => {
            if (lightboxIndex === null || !product.images || product.images.length === 0) return;
            if (e.key === 'Escape') {
                setLightboxIndex(null);
            } else if (e.key === 'ArrowLeft') {
                setLightboxIndex((prev) =>
                    prev === null || prev === 0 ? product.images!.length - 1 : prev - 1
                );
            } else if (e.key === 'ArrowRight') {
                setLightboxIndex((prev) =>
                    prev === null || prev === product.images!.length - 1 ? 0 : prev + 1
                );
            }
        };

        if (lightboxIndex !== null) {
            document.body.style.overflow = 'hidden';
            window.addEventListener('keydown', handleKeyDown);
        } else {
            document.body.style.overflow = '';
        }

        return () => {
            document.body.style.overflow = '';
            window.removeEventListener('keydown', handleKeyDown);
        };
    }, [lightboxIndex, product.images]);

    // Accordions state (Spa Ceylon style right-column collapsible sections)
    const [accordions, setAccordions] = useState<{
        desc: boolean;
        directions: boolean;
    }>({
        desc: true,
        directions: false,
    });

    const toggleAccordion = (key: 'desc' | 'directions') => {
        setAccordions((prev) => ({
            ...prev,
            [key]: !prev[key],
        }));
    };

    // Review form state
    const [showReviewModal, setShowReviewModal] = useState(false);
    const [reviewRating, setReviewRating] = useState(5);
    const [reviewOrderId, setReviewOrderId] = useState('');
    const [reviewTitle, setReviewTitle] = useState('');
    const [reviewComment, setReviewComment] = useState('');
    const [reviewSubmitting, setReviewSubmitting] = useState(false);
    const [reviewSuccessMessage, setReviewSuccessMessage] = useState<string | null>(null);
    const [reviewErrorMessage, setReviewErrorMessage] = useState<string | null>(null);

    // Back-in-stock state
    const [bisEmail, setBisEmail] = useState(auth?.user?.email ?? '');
    const [bisLoading, setBisLoading] = useState(false);
    const [bisSuccess, setBisSuccess] = useState<string | null>(null);
    const [bisError, setBisError] = useState<string | null>(null);

    useEffect(() => {
        if (!auth?.user) {
            recordGuestView(product.id);
        }
    }, [product.id, auth?.user?.id]);

    const name = locale === 'si' && product.name_si ? product.name_si : product.name_en;
    const desc = locale === 'si' && product.description_si ? product.description_si : product.description_en;
    const shortDesc = locale === 'si' && product.short_description_si
        ? product.short_description_si
        : product.short_description_en;
    const directions = locale === 'si' && product.directions_si ? product.directions_si : product.directions_en;

    const inStock = product.stock_quantity > 0;
    const numericPrice = Number(product.price);
    const priceFormatted = numericPrice.toLocaleString('en-US', {
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

    const handleBackInStockSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (bisLoading) return;

        setBisLoading(true);
        setBisError(null);
        setBisSuccess(null);

        router.post(
            '/back-in-stock/subscribe',
            {
                product_id: product.id,
                email: bisEmail,
                locale,
            },
            {
                preserveScroll: true,
                onSuccess: (page) => {
                    setBisLoading(false);
                    const flash = (page.props as any)?.flash;
                    if (flash?.success) {
                        setBisSuccess(flash.success);
                    } else {
                        setBisSuccess(t('back_in_stock_subscribed_success', "You're subscribed! We'll email you when this item is back in stock."));
                    }
                },
                onError: (errors) => {
                    setBisLoading(false);
                    setBisError(errors.email || errors.product_id || t('error_occurred', 'Failed to subscribe. Please try again.'));
                },
            }
        );
    };

    const handleReviewSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (reviewSubmitting) return;

        setReviewSubmitting(true);
        setReviewErrorMessage(null);
        setReviewSuccessMessage(null);

        router.post(
            `/products/${product.id}/reviews`,
            {
                order_id: reviewOrderId,
                rating: reviewRating,
                title: reviewTitle,
                comment: reviewComment,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setReviewSubmitting(false);
                    setReviewSuccessMessage('Thank you! Your review has been submitted for moderation.');
                    setReviewTitle('');
                    setReviewComment('');
                    setReviewOrderId('');
                },
                onError: (errors) => {
                    setReviewSubmitting(false);
                    const firstError = Object.values(errors)[0] as string;
                    setReviewErrorMessage(firstError || 'Failed to submit review. Please ensure you have a delivered order for this product.');
                },
            }
        );
    };

    const approvedReviews = product.approved_reviews ?? [];
    const reviewCount = product.reviews_count ?? approvedReviews.length;
    const ratingValue = product.rating_cache ? Number(product.rating_cache) : null;

    return (
        <PublicLayout>
            <Head title={`${name} | ${t('app_name', 'Aura Luxe')}`} />

            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-10">
                {/* Clean Luxury Breadcrumbs */}
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

                {/* Main 2-Column Product Section */}
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-10 lg:gap-16 items-start">
                                {/* Left: Product Images Collage Grid Showcase */ }
                                <div>
                        {(!imageError && product.images && product.images.length > 0) ? (
                                <div className="grid grid-cols-2 gap-3 sm:gap-4">
                                    {product.images.slice(0, 6).map((img, idx) => {
                                        const total = Math.min(product.images!.length, 6);
                                        // If only 1 image, span 2 columns
                                        // If odd total (e.g. 3 or 5 images) and it's the last image, span 2 columns
                                        const isSpanned = total === 1 || (total % 2 !== 0 && idx === total - 1);

                                        return (
                                            <div
                                                key={img.id}
                                                onClick={() => setLightboxIndex(idx)}
                                                className={`${isSpanned ? 'col-span-2 aspect-[4/3] sm:aspect-[16/10]' : 'aspect-square'
                                                    } bg-stone-50/50 rounded-lg border border-stone-200/80 overflow-hidden relative shadow-xs group cursor-zoom-in flex items-center justify-center transition-all hover:border-stone-400`}
                                            >
                                                <img
                                                    src={`/storage/${img.path}`}
                                                    alt={img.alt_text ?? `${name} photo ${idx + 1}`}
                                                    className="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"
                                                />

                                                {/* Minimalist Badges on First Image */}
                                                {idx === 0 && (
                                                    <div className="absolute top-3 left-3 flex flex-col gap-1.5 z-10 pointer-events-none">
                                                        {product.is_best_seller && (
                                                            <span className="bg-stone-900 text-stone-100 text-[10px] font-semibold uppercase tracking-wider px-2.5 py-1 rounded-sm shadow-xs">
                                                                {t('best_sellers', 'Best Seller')}
                                                            </span>
                                                        )}
                                                        {product.is_new_arrival && (
                                                            <span className="bg-amber-900/90 text-amber-50 text-[10px] font-semibold uppercase tracking-wider px-2.5 py-1 rounded-sm shadow-xs">
                                                                {t('new_arrivals', 'New Arrival')}
                                                            </span>
                                                        )}
                                                    </div>
                                                )}

                                                {/* Hover Zoom Icon & Full-Size Hint */}
                                                <div className="absolute inset-0 bg-stone-900/15 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center pointer-events-none">
                                                    <span className="inline-flex items-center space-x-1.5 bg-white/95 text-stone-900 text-[11px] font-medium tracking-wide uppercase px-3 py-1.5 rounded-full shadow-lg transform scale-95 group-hover:scale-100 transition-transform">
                                                        <svg className="w-3.5 h-3.5 text-stone-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v6m3-3H7" />
                                                        </svg>
                                                        <span>View Full Size</span>
                                                    </span>
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>
                            ) : (
                                /* Fallback when no image is uploaded */
                                <div className="aspect-square bg-stone-50/50 rounded-lg border border-stone-200/80 overflow-hidden relative shadow-xs flex items-center justify-center">
                                    <div className="p-8 flex flex-col items-center justify-center text-center">
                                        <svg className="w-28 h-28 text-stone-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                                        </svg>
                                        <span className="text-xs uppercase tracking-widest text-stone-400 font-mono mt-4">
                                            AURA LUXE BOTANICAL
                                        </span>
                                    </div>
                                    </div>
                            )}
                        </div>

                        {/* Right: Product Details, Accordions, & Action */}
                        <div className="space-y-6 lg:pl-4">
                            {/* Brand & Category Subhead */}
                            <div>
                                <div className="text-[11px] uppercase tracking-[0.25em] font-semibold text-stone-500 mb-2">
                                    AURA LUXE SRI LANKA
                                    {product.category && (
                                        <>
                                            <span className="mx-2 text-stone-300">•</span>
                                            <span>
                                                {locale === 'si' && product.category.name_si
                                                    ? product.category.name_si
                                                    : product.category.name_en}
                                            </span>
                                        </>
                                    )}
                                </div>

                                {/* Product Title (Cormorant Garamond Serif) */}
                                <h1 className="font-serif text-3xl sm:text-4xl text-stone-900 font-normal tracking-tight leading-tight">
                                    {name}
                                </h1>
                            </div>

                            {/* Price Block */}
                            <div className="pt-1">
                                <div className="flex items-baseline gap-3">
                                    <span className="font-serif text-2xl sm:text-3xl font-medium text-stone-900 tracking-tight">
                                        Rs {priceFormatted}
                                    </span>
                                    {comparePriceFormatted && (
                                        <span className="font-sans text-sm text-stone-400 line-through">
                                            Rs {comparePriceFormatted}
                                        </span>
                                    )}
                                </div>
                            </div>

                            {/* Sensory Botanical Short Description */}
                            <p className="text-sm text-stone-600 leading-relaxed font-sans pt-1">
                                {shortDesc ?? desc ?? '100% Natural formulae, to refresh & invigorate your senses, promoting calming & soothing energies.'}
                            </p>

                            {/* Spa Ceylon Style Collapsible Accordions */}
                            <div className="border-t border-b border-stone-200 divide-y divide-stone-200">
                                {/* Accordion 1: Detailed Description */}
                                <div>
                                    <button
                                        type="button"
                                        onClick={() => toggleAccordion('desc')}
                                        className="w-full py-4 flex items-center justify-between text-left text-xs uppercase tracking-[0.18em] font-medium text-stone-900 hover:text-stone-600 transition-colors cursor-pointer"
                                        aria-expanded={accordions.desc}
                                    >
                                        <div className="flex items-center space-x-2.5">
                                            <svg className="w-4 h-4 text-stone-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                            </svg>
                                            <span>DETAILED DESCRIPTION</span>
                                        </div>
                                        <svg
                                            className={`w-4 h-4 text-stone-500 transition-transform duration-200 ${accordions.desc ? 'rotate-180' : ''
                                                }`}
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M19 9l-7 7-7-7" />
                                        </svg>
                                    </button>
                                    {accordions.desc && (
                                        <div className="pb-5 pt-1 text-sm text-stone-600 leading-relaxed font-sans prose prose-stone max-w-none">
                                            <p>{desc ?? 'Specially formulated with natural botanical extracts for luxurious daily restoration.'}</p>
                                        </div>
                                    )}
                                </div>

                                {/* Accordion 2: How to Use */}
                                <div>
                                    <button
                                        type="button"
                                        onClick={() => toggleAccordion('directions')}
                                        className="w-full py-4 flex items-center justify-between text-left text-xs uppercase tracking-[0.18em] font-medium text-stone-900 hover:text-stone-600 transition-colors cursor-pointer"
                                        aria-expanded={accordions.directions}
                                    >
                                        <div className="flex items-center space-x-2.5">
                                            <svg className="w-4 h-4 text-stone-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <circle cx="12" cy="12" r="9" strokeWidth="1.5" />
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M9.09 9a3 3 0 015.83 1c0 2-3 3-3 3m.08 4h.01" />
                                            </svg>
                                            <span>HOW TO USE</span>
                                        </div>
                                        <svg
                                            className={`w-4 h-4 text-stone-500 transition-transform duration-200 ${accordions.directions ? 'rotate-180' : ''
                                                }`}
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M19 9l-7 7-7-7" />
                                        </svg>
                                    </button>
                                    {accordions.directions && (
                                        <div className="pb-5 pt-1 text-sm text-stone-600 leading-relaxed font-sans">
                                            <p>
                                                {directions ??
                                                    'Apply gently onto clean skin or pulse points. Breathe deeply to inhale the aromatic natural essences. For optimal results, use daily as part of your morning or evening wellness ritual.'}
                                            </p>
                                        </div>
                                    )}
                                </div>
                            </div>

                            {/* Purchasing & Add To Cart Section */}
                            {inStock ? (
                                <form onSubmit={handleAddToCart} className="space-y-4 pt-2">
                                    <div>
                                        <label className="text-[11px] uppercase tracking-widest font-semibold text-stone-800 block mb-2 font-sans">
                                            QUANTITY
                                        </label>
                                        <div className="flex items-center border border-stone-300 w-36 justify-between px-2 py-2 bg-white rounded-none">
                                            <button
                                                type="button"
                                                disabled={!inStock || quantity <= 1}
                                                onClick={() => handleQuantityChange(-1)}
                                                className="px-2 py-1 text-stone-600 hover:text-stone-900 text-base font-medium disabled:opacity-30 cursor-pointer"
                                                aria-label="Decrease quantity"
                                            >
                                                −
                                            </button>
                                            <span className="text-sm font-medium text-stone-900 font-sans">
                                                {quantity}
                                            </span>
                                            <button
                                                type="button"
                                                disabled={!inStock || quantity >= product.stock_quantity}
                                                onClick={() => handleQuantityChange(1)}
                                                className="px-2 py-1 text-stone-600 hover:text-stone-900 text-base font-medium disabled:opacity-30 cursor-pointer"
                                                aria-label="Increase quantity"
                                            >
                                                +
                                            </button>
                                        </div>
                                    </div>

                                    {/* Full-Width Black Add to Cart Button */}
                                    <button
                                        type="submit"
                                        disabled={!inStock || adding}
                                        className="w-full py-4 px-8 bg-stone-900 hover:bg-black text-white text-xs uppercase tracking-[0.25em] font-semibold transition-all duration-200 cursor-pointer disabled:bg-stone-300 disabled:cursor-not-allowed shadow-sm"
                                    >
                                        {adding ? 'ADDING TO BAG...' : 'ADD TO CART'}
                                    </button>

                                    <div className="flex items-center justify-between text-xs text-stone-500 pt-1">
                                        <span className="flex items-center gap-1.5">
                                            <span className="w-2 h-2 rounded-full bg-emerald-500"></span>
                                            In Stock ({product.stock_quantity} available)
                                        </span>
                                        <span>✓ Cash on Delivery Available</span>
                                    </div>
                                </form>
                            ) : (
                                /* Back in Stock Alert */
                                <div className="mt-6 p-6 bg-stone-50 border border-stone-200 rounded-md">
                                    <div className="flex items-center gap-2 mb-2">
                                        <span className="text-amber-700 text-sm">🔔</span>
                                        <h3 className="font-serif text-base font-semibold text-stone-900">
                                            {t('back_in_stock_title', 'Notify Me When Available')}
                                        </h3>
                                    </div>
                                    <p className="text-xs text-stone-600 mb-4 leading-relaxed">
                                        {t('back_in_stock_subtitle', 'Get an email alert as soon as this item is back in stock.')}
                                    </p>

                                    {bisSuccess ? (
                                        <div className="p-4 bg-emerald-50 border border-emerald-200 rounded text-emerald-800 text-xs font-medium">
                                            ✓ {bisSuccess}
                                        </div>
                                    ) : (
                                        <form onSubmit={handleBackInStockSubmit} className="space-y-3">
                                            {bisError && (
                                                <div className="p-3 bg-rose-50 border border-rose-200 rounded text-rose-700 text-xs">
                                                    {bisError}
                                                </div>
                                            )}

                                            {auth?.user && auth?.user?.email_verified_at ? (
                                                <div className="text-xs text-stone-600 bg-white p-3 rounded border border-stone-200">
                                                    <span className="text-stone-400">{t('back_in_stock_subscribing_as', 'Subscribing as')}: </span>
                                                    <strong className="text-stone-900">{auth.user.email}</strong>
                                                </div>
                                            ) : (
                                                <input
                                                    type="email"
                                                    required
                                                    value={bisEmail}
                                                    onChange={(e) => setBisEmail(e.target.value)}
                                                    placeholder={t('back_in_stock_email_placeholder', 'Enter your email address')}
                                                    className="w-full text-xs px-4 py-3 rounded border border-stone-300 focus:border-stone-900 focus:ring-1 focus:ring-stone-900 bg-white text-stone-900"
                                                />
                                            )}

                                            <button
                                                type="submit"
                                                disabled={bisLoading}
                                                className="w-full py-3.5 px-6 bg-stone-900 hover:bg-black disabled:bg-stone-400 text-white text-xs uppercase tracking-[0.2em] font-semibold transition-all cursor-pointer"
                                            >
                                                {bisLoading ? 'Subscribing...' : t('back_in_stock_btn', 'Notify Me')}
                                            </button>
                                        </form>
                                    )}
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Bottom Section: Customer Reviews (Centered Spa Ceylon Style) */}
                    <div className="mt-20 pt-16 border-t border-stone-200">
                        <div className="text-center max-w-2xl mx-auto space-y-4">
                            <h2 className="font-serif text-2xl sm:text-3xl text-stone-900 font-normal tracking-tight uppercase">
                                CUSTOMER REVIEWS
                            </h2>

                            <div className="flex items-center justify-center space-x-6 pt-2">
                                {/* Star Rating Display */}
                                <div className="flex flex-col items-center">
                                    <div className="flex items-center space-x-1 text-amber-500 text-lg">
                                        {[1, 2, 3, 4, 5].map((star) => (
                                            <span key={star}>
                                                {ratingValue && ratingValue >= star ? '★' : '☆'}
                                            </span>
                                        ))}
                                    </div>
                                    <span className="text-xs text-stone-500 font-serif italic mt-1">
                                        {reviewCount > 0
                                            ? `${ratingValue?.toFixed(1) ?? '5.0'} out of 5 (${reviewCount} ${reviewCount === 1 ? 'review' : 'reviews'})`
                                            : 'Be the first to write a review'}
                                    </span>
                                </div>

                                <div className="h-8 w-px bg-stone-300"></div>

                                {/* Write A Review Button */}
                                <button
                                    type="button"
                                    onClick={() => setShowReviewModal(!showReviewModal)}
                                    className="px-8 py-3 bg-stone-900 hover:bg-black text-white text-xs uppercase tracking-widest font-semibold rounded-full shadow-sm transition-all cursor-pointer"
                                >
                                    WRITE A REVIEW
                                </button>
                            </div>
                        </div>

                        {/* Expandable Review Submission Drawer/Form */}
                        {showReviewModal && (
                            <div className="max-w-xl mx-auto mt-8 p-6 bg-stone-50 border border-stone-200 rounded-lg shadow-xs">
                                <div className="flex items-center justify-between pb-4 border-b border-stone-200">
                                    <h3 className="font-serif text-lg font-medium text-stone-900">
                                        Write a Verified Customer Review
                                    </h3>
                                    <button
                                        type="button"
                                        onClick={() => setShowReviewModal(false)}
                                        className="text-stone-400 hover:text-stone-600 text-sm cursor-pointer"
                                    >
                                        ✕
                                    </button>
                                </div>

                                {reviewSuccessMessage ? (
                                    <div className="mt-4 p-4 bg-emerald-50 border border-emerald-200 rounded text-emerald-800 text-xs font-medium">
                                        ✓ {reviewSuccessMessage}
                                    </div>
                                ) : auth?.user ? (
                                    <form onSubmit={handleReviewSubmit} className="mt-4 space-y-4">
                                        {reviewErrorMessage && (
                                            <div className="p-3 bg-rose-50 border border-rose-200 rounded text-rose-700 text-xs">
                                                {reviewErrorMessage}
                                            </div>
                                        )}

                                        {/* Rating Stepper */}
                                        <div>
                                            <label className="block text-xs font-semibold uppercase tracking-wider text-stone-700 mb-1">
                                                Rating
                                            </label>
                                            <div className="flex items-center space-x-2 text-2xl text-amber-500 cursor-pointer">
                                                {[1, 2, 3, 4, 5].map((star) => (
                                                    <button
                                                        key={star}
                                                        type="button"
                                                        onClick={() => setReviewRating(star)}
                                                        className="focus:outline-none"
                                                    >
                                                        {star <= reviewRating ? '★' : '☆'}
                                                    </button>
                                                ))}
                                                <span className="text-xs text-stone-500 ml-2 font-mono">
                                                    ({reviewRating} of 5)
                                                </span>
                                            </div>
                                        </div>

                                        {/* Order ID requirement for verified reviews */}
                                        <div>
                                            <label className="block text-xs font-semibold uppercase tracking-wider text-stone-700 mb-1">
                                                Delivered Order Number / Order ID *
                                            </label>
                                            <input
                                                type="text"
                                                required
                                                value={reviewOrderId}
                                                onChange={(e) => setReviewOrderId(e.target.value)}
                                                placeholder="Enter your order number or numeric ID"
                                                className="w-full text-xs px-3.5 py-2.5 rounded border border-stone-300 focus:border-stone-900 focus:ring-1 focus:ring-stone-900 bg-white"
                                            />
                                            <span className="text-[11px] text-stone-400 mt-1 block">
                                                For quality assurance, reviews are restricted to verified purchasers of delivered orders.
                                            </span>
                                        </div>

                                        <div>
                                            <label className="block text-xs font-semibold uppercase tracking-wider text-stone-700 mb-1">
                                                Review Headline *
                                            </label>
                                            <input
                                                type="text"
                                                required
                                                maxLength={150}
                                                value={reviewTitle}
                                                onChange={(e) => setReviewTitle(e.target.value)}
                                                placeholder="e.g. Luxurious texture and deeply calming"
                                                className="w-full text-xs px-3.5 py-2.5 rounded border border-stone-300 focus:border-stone-900 focus:ring-1 focus:ring-stone-900 bg-white"
                                            />
                                        </div>

                                        <div>
                                            <label className="block text-xs font-semibold uppercase tracking-wider text-stone-700 mb-1">
                                                Your Experience & Thoughts *
                                            </label>
                                            <textarea
                                                required
                                                rows={4}
                                                maxLength={5000}
                                                value={reviewComment}
                                                onChange={(e) => setReviewComment(e.target.value)}
                                                placeholder="Describe how this botanical formulation worked for your skincare or daily wellness..."
                                                className="w-full text-xs px-3.5 py-2.5 rounded border border-stone-300 focus:border-stone-900 focus:ring-1 focus:ring-stone-900 bg-white"
                                            />
                                        </div>

                                        <button
                                            type="submit"
                                            disabled={reviewSubmitting}
                                            className="w-full py-3 bg-stone-900 hover:bg-black text-white text-xs uppercase tracking-widest font-semibold rounded transition-all cursor-pointer"
                                        >
                                            {reviewSubmitting ? 'Submitting for Moderation...' : 'Submit Review'}
                                        </button>
                                    </form>
                                ) : (
                                    <div className="mt-4 text-center py-6 text-xs text-stone-600 space-y-3">
                                        <p>Please log in to submit a verified product review.</p>
                                        <Link
                                            href="/login"
                                            className="inline-block px-6 py-2.5 bg-stone-900 text-white rounded text-xs uppercase tracking-wider font-semibold hover:bg-black transition-colors"
                                        >
                                            Sign In
                                        </Link>
                                    </div>
                                )}
                            </div>
                        )}

                        {/* Approved Reviews Feed (if any exist) */}
                        {approvedReviews.length > 0 && (
                            <div className="max-w-3xl mx-auto mt-12 divide-y divide-stone-200">
                                {approvedReviews.map((rev) => (
                                    <div key={rev.id} className="py-6 space-y-2">
                                        <div className="flex items-center justify-between">
                                            <div className="flex items-center space-x-2">
                                                <div className="flex text-amber-500 text-sm">
                                                    {[1, 2, 3, 4, 5].map((s) => (
                                                        <span key={s}>{s <= rev.rating ? '★' : '☆'}</span>
                                                    ))}
                                                </div>
                                                <span className="font-semibold text-xs text-stone-900">
                                                    {rev.title}
                                                </span>
                                            </div>
                                            <span className="text-[11px] text-stone-400">
                                                {new Date(rev.created_at).toLocaleDateString()}
                                            </span>
                                        </div>
                                        <p className="text-xs text-stone-600 leading-relaxed font-sans">
                                            {rev.comment}
                                        </p>
                                        <div className="text-[11px] text-stone-500 flex items-center space-x-1.5 pt-1">
                                            <span className="font-medium text-stone-700">
                                                {rev.customer?.name ?? 'Verified Customer'}
                                            </span>
                                            <span>•</span>
                                            <span className="text-emerald-700">✓ Verified Buyer</span>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>

                    {/* Related Products: "Complete Your Ritual" */}
                    {relatedProducts.length > 0 && (
                        <div className="mt-20 pt-16 border-t border-stone-200">
                            <div className="text-center mb-10">
                                <span className="text-xs uppercase tracking-[0.25em] text-stone-500 font-semibold block mb-2">
                                    {t('related_products', 'COMPLETE YOUR RITUAL')}
                                </span>
                                <h2 className="font-serif text-3xl text-stone-900 font-normal">
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

                    {/* Recently Viewed Products */}
                    <div className="mt-16">
                        <RecentlyViewedProducts currentProductId={product.id} />
                    </div>
                </div>

                {/* Full-Size Lightbox Modal */}
                {lightboxIndex !== null && product.images && product.images[lightboxIndex] && (
                    <div
                        className="fixed inset-0 z-50 bg-black/95 backdrop-blur-md flex items-center justify-center p-4 sm:p-8 animate-in fade-in duration-200"
                        onClick={() => setLightboxIndex(null)}
                    >
                        {/* Top Controls: Counter & Close */}
                        <div className="absolute top-5 inset-x-5 flex items-center justify-between z-10 text-white">
                            <span className="text-xs font-mono uppercase tracking-widest bg-white/10 px-3.5 py-1.5 rounded-full border border-white/20">
                                {lightboxIndex + 1} / {product.images.length}
                            </span>
                            <button
                                type="button"
                                onClick={(e) => {
                                    e.stopPropagation();
                                    setLightboxIndex(null);
                                }}
                                className="w-10 h-10 rounded-full bg-white/10 hover:bg-white/20 flex items-center justify-center text-white transition-colors cursor-pointer text-base"
                                title="Close (Esc)"
                            >
                                ✕
                            </button>
                        </div>

                        {/* Previous Image Button */}
                        {product.images.length > 1 && (
                            <button
                                type="button"
                                onClick={(e) => {
                                    e.stopPropagation();
                                    setLightboxIndex((prev) =>
                                        prev === null || prev === 0 ? product.images!.length - 1 : prev - 1
                                    );
                                }}
                                className="absolute left-4 sm:left-8 top-1/2 -translate-y-1/2 w-12 h-12 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center text-3xl transition-all cursor-pointer z-10"
                                title="Previous (Left Arrow)"
                            >
                                ‹
                            </button>
                        )}

                        {/* Full-size Image Container */}
                        <div
                            className="max-w-5xl max-h-[85vh] w-full h-full flex items-center justify-center"
                            onClick={(e) => e.stopPropagation()}
                        >
                            <img
                                src={`/storage/${product.images[lightboxIndex].path}`}
                                alt={product.images[lightboxIndex].alt_text ?? name}
                                className="max-w-full max-h-[85vh] object-contain rounded shadow-2xl transition-all select-none"
                            />
                        </div>

                        {/* Next Image Button */}
                        {product.images.length > 1 && (
                            <button
                                type="button"
                                onClick={(e) => {
                                    e.stopPropagation();
                                    setLightboxIndex((prev) =>
                                        prev === null || prev === product.images!.length - 1 ? 0 : prev + 1
                                    );
                                }}
                                className="absolute right-4 sm:right-8 top-1/2 -translate-y-1/2 w-12 h-12 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center text-3xl transition-all cursor-pointer z-10"
                                title="Next (Right Arrow)"
                            >
                                ›
                            </button>
                        )}

                        {/* Bottom Helper Hint */}
                        <div className="absolute bottom-4 inset-x-0 text-center text-stone-400 text-[11px] pointer-events-none">
                            Use keyboard arrow keys to navigate • Esc or click anywhere to exit
                        </div>
                    </div>
                )}
        </PublicLayout>
    );
}
