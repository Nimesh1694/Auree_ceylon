import React, { useEffect, useState } from 'react';
import { Link, usePage } from '@inertiajs/react';
import axios from 'axios';
import { useTranslation } from '@/hooks/useTranslation';
import { Product } from '@/types';

interface Props {
    currentProductId?: number;
}

interface RecentlyViewedEntry {
    id: number;
    product_id: number;
    viewed_at: string;
    product: Product;
}

export default function RecentlyViewedProducts({ currentProductId }: Props) {
    const { t, locale } = useTranslation();
    const { auth } = usePage().props as any;
    const [products, setProducts] = useState<Product[]>([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        let isMounted = true;

        const loadRecentlyViewed = async () => {
            setLoading(true);
            try {
                if (auth?.user) {
                    // Authenticated customer: fetch from backend endpoint
                    const response = await axios.get('/customer/recently-viewed', {
                        params: currentProductId ? { exclude_product_id: currentProductId } : {},
                    });
                    if (isMounted && response.data?.success && Array.isArray(response.data.data)) {
                        const items: Product[] = response.data.data
                            .map((entry: RecentlyViewedEntry) => entry.product)
                            .filter(Boolean);
                        setProducts(items);
                    }
                } else {
                    // Guest browsing: load from localStorage
                    const raw = localStorage.getItem('aura_recently_viewed');
                    if (raw) {
                        const guestItems: Array<{ product_id: number; viewed_at: string }> = JSON.parse(raw);
                        if (Array.isArray(guestItems) && guestItems.length > 0) {
                            const candidateIds = guestItems
                                .map((i) => i.product_id)
                                .filter((id) => id !== currentProductId)
                                .slice(0, 8);

                            if (candidateIds.length > 0) {
                                // Fetch product cards for guest items
                                const res = await axios.get('/api/products/batch', {
                                    params: { ids: candidateIds },
                                }).catch(() => null);

                                if (isMounted && res?.data?.products) {
                                    setProducts(res.data.products);
                                }
                            }
                        }
                    }
                }
            } catch {
                // Fail-open: ignore errors
            } finally {
                if (isMounted) {
                    setLoading(false);
                }
            }
        };

        loadRecentlyViewed();

        return () => {
            isMounted = false;
        };
    }, [auth?.user?.id, currentProductId]);

    if (loading || products.length === 0) {
        return null;
    }

    return (
        <section className="mt-16 pt-12 border-t border-stone-200">
            <div className="flex items-center justify-between mb-8">
                <div>
                    <span className="text-xs font-semibold tracking-wider uppercase text-rose-800">
                        {t('your_history', 'Browsing History')}
                    </span>
                    <h2 className="font-serif text-2xl sm:text-3xl text-stone-900 font-medium tracking-tight mt-1">
                        {t('recently_viewed', 'Recently Viewed Products')}
                    </h2>
                </div>
            </div>

            <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-6">
                {products.map((p) => {
                    const name = locale === 'si' && p.name_si ? p.name_si : p.name_en;
                    const priceFormatted = Number(p.price).toLocaleString('en-US', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2,
                    });
                    const imageUrl = p.primary_image?.path
                        ? `/storage/${p.primary_image.path}`
                        : '/images/product-placeholder.webp';

                    return (
                        <div
                            key={p.id}
                            className="group flex flex-col bg-white rounded-2xl border border-stone-200 p-3 shadow-xs hover:shadow-md transition-shadow"
                        >
                            <Link href={`/products/${p.slug}`} className="block relative aspect-square overflow-hidden rounded-xl bg-stone-50">
                                <img
                                    src={imageUrl}
                                    alt={name}
                                    className="object-cover w-full h-full group-hover:scale-105 transition-transform duration-300"
                                    loading="lazy"
                                />
                                {p.stock_quantity <= 0 && (
                                    <span className="absolute top-2 right-2 bg-rose-900/90 text-white text-[10px] uppercase font-semibold px-2 py-0.5 rounded-full">
                                        {t('out_of_stock', 'Out of Stock')}
                                    </span>
                                )}
                            </Link>

                            <div className="mt-3 flex-1 flex flex-col justify-between">
                                <div>
                                    {p.category && (
                                        <span className="text-[10px] uppercase tracking-wider font-semibold text-rose-800">
                                            {locale === 'si' && p.category.name_si ? p.category.name_si : p.category.name_en}
                                        </span>
                                    )}
                                    <Link href={`/products/${p.slug}`}>
                                        <h3 className="text-sm font-medium text-stone-900 hover:text-rose-900 line-clamp-1 mt-0.5">
                                            {name}
                                        </h3>
                                    </Link>
                                </div>

                                <div className="mt-2 pt-2 border-t border-stone-100 flex items-center justify-between">
                                    <span className="font-serif text-sm font-semibold text-stone-900">
                                        LKR {priceFormatted}
                                    </span>
                                </div>
                            </div>
                        </div>
                    );
                })}
            </div>
        </section>
    );
}

