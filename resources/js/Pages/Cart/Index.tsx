import { useTranslation } from '@/hooks/useTranslation';
import PublicLayout from '@/Layouts/PublicLayout';
import { Head, Link, router } from '@inertiajs/react';
import React from 'react';

interface CartItem {
    product_id: number;
    name_en: string;
    name_si: string;
    slug: string;
    sku: string | null;
    unit_price: number;
    compare_at_price: number | null;
    quantity: number;
    available_stock: number;
    is_over_stock: boolean;
    subtotal: number;
    image: string | null;
    category_name_en: string | null;
    category_name_si: string | null;
}

interface CartDetails {
    items: CartItem[];
    item_count: number;
    subtotal: number;
    delivery_charge: number;
    total: number;
    has_out_of_stock: boolean;
}

interface Props {
    cart: CartDetails;
}

export default function CartIndex({ cart }: Props) {
    const { t, locale } = useTranslation();

    const handleQuantityUpdate = (productId: number, newQty: number) => {
        router.patch(
            '/cart/update',
            { product_id: productId, quantity: newQty },
            { preserveScroll: true }
        );
    };

    const handleRemove = (productId: number) => {
        router.delete(`/cart/remove/${productId}`, { preserveScroll: true });
    };

    const handleClearCart = () => {
        if (confirm('Are you sure you want to empty your shopping cart?')) {
            router.delete('/cart/clear', { preserveScroll: true });
        }
    };

    const isEmpty = cart.items.length === 0;

    return (
        <PublicLayout>
            <Head title={`${t('shopping_cart', 'Shopping Cart')} | ${t('app_name', 'Aura Luxe')}`} />

            <div className="bg-[#F5EFE6] border-b border-stone-200/80 py-10">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <span className="text-xs uppercase tracking-[0.2em] font-semibold text-rose-900 block mb-1">
                        {t('app_name', 'Aura Luxe Cosmetics')}
                    </span>
                    <h1 className="font-serif text-3xl sm:text-4xl text-stone-900 font-medium">
                        {t('shopping_cart', 'Shopping Bag')}
                    </h1>
                </div>
            </div>

            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 sm:py-14">
                {isEmpty ? (
                    <div className="bg-white rounded-2xl border border-stone-200 p-12 text-center max-w-lg mx-auto shadow-xs">
                        <div className="w-16 h-16 rounded-full bg-stone-100 flex items-center justify-center text-stone-400 mx-auto mb-4">
                            <svg className="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                            </svg>
                        </div>
                        <h2 className="font-serif text-2xl text-stone-900 font-medium mb-2">
                            {t('cart_empty_title', 'Your bag is empty')}
                        </h2>
                        <p className="text-xs text-stone-500 mb-6 leading-relaxed">
                            {t(
                                'cart_empty_desc',
                                'Explore our luxury botanical formulas and find your glow today.'
                            )}
                        </p>
                        <Link
                            href="/products"
                            className="inline-block px-7 py-3.5 bg-stone-900 hover:bg-rose-900 text-white text-xs uppercase tracking-[0.2em] font-semibold rounded-xl shadow-md transition-colors"
                        >
                            {t('continue_shopping', 'Browse Products')}
                        </Link>
                    </div>
                ) : (
                    <div className="grid grid-cols-1 lg:grid-cols-3 gap-10">
                        {/* Cart Items Table */}
                        <div className="lg:col-span-2 space-y-4">
                            {cart.has_out_of_stock && (
                                <div className="p-4 bg-amber-50 border border-amber-200 rounded-xl text-amber-800 text-xs flex items-center gap-3">
                                    <span>⚠️</span>
                                    <span>
                                        One or more items in your cart exceed available stock. Please reduce the quantity before proceeding to checkout.
                                    </span>
                                </div>
                            )}

                            <div className="bg-white rounded-2xl border border-stone-200 overflow-hidden shadow-xs divide-y divide-stone-100">
                                {cart.items.map((item) => {
                                    const name = locale === 'si' && item.name_si ? item.name_si : item.name_en;
                                    return (
                                        <div
                                            key={item.product_id}
                                            className="p-5 sm:p-6 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4"
                                        >
                                            {/* Thumbnail & Title */}
                                            <div className="flex items-center gap-4 flex-1">
                                                <div className="w-16 h-16 rounded-xl bg-stone-100 border border-stone-200 flex items-center justify-center shrink-0 text-stone-400">
                                                    🌸
                                                </div>
                                                <div>
                                                    <Link
                                                        href={`/products/${item.slug}`}
                                                        className="font-serif text-base font-medium text-stone-900 hover:text-rose-900 transition-colors line-clamp-1"
                                                    >
                                                        {name}
                                                    </Link>
                                                    <span className="text-xs text-stone-400 font-mono block">
                                                        LKR {item.unit_price.toFixed(2)}
                                                    </span>
                                                    {item.is_over_stock && (
                                                        <span className="text-[11px] text-rose-600 font-medium block mt-0.5">
                                                            Only {item.available_stock} in stock
                                                        </span>
                                                    )}
                                                </div>
                                            </div>

                                            {/* Quantity and Actions */}
                                            <div className="flex items-center justify-between sm:justify-end gap-6 w-full sm:w-auto">
                                                {/* Counter */}
                                                <div className="flex items-center border border-stone-300 rounded-lg bg-stone-50 text-xs">
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            handleQuantityUpdate(
                                                                item.product_id,
                                                                item.quantity - 1
                                                            )
                                                        }
                                                        className="px-2.5 py-1.5 text-stone-600 hover:text-stone-900 cursor-pointer"
                                                    >
                                                        −
                                                    </button>
                                                    <span className="w-8 text-center font-semibold text-stone-900">
                                                        {item.quantity}
                                                    </span>
                                                    <button
                                                        type="button"
                                                        disabled={item.quantity >= item.available_stock}
                                                        onClick={() =>
                                                            handleQuantityUpdate(
                                                                item.product_id,
                                                                item.quantity + 1
                                                            )
                                                        }
                                                        className="px-2.5 py-1.5 text-stone-600 hover:text-stone-900 disabled:opacity-30 cursor-pointer"
                                                    >
                                                        +
                                                    </button>
                                                </div>

                                                {/* Line total */}
                                                <span className="font-semibold text-stone-900 text-sm min-w-[90px] text-right">
                                                    LKR {item.subtotal.toFixed(2)}
                                                </span>

                                                {/* Delete button */}
                                                <button
                                                    type="button"
                                                    onClick={() => handleRemove(item.product_id)}
                                                    className="text-stone-400 hover:text-rose-700 text-sm p-1 cursor-pointer transition-colors"
                                                    aria-label="Remove item"
                                                >
                                                    ✕
                                                </button>
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>

                            <div className="flex items-center justify-between pt-2">
                                <Link
                                    href="/products"
                                    className="text-xs font-semibold text-stone-600 hover:text-stone-900 uppercase tracking-wider underline underline-offset-4"
                                >
                                    ← {t('continue_shopping', 'Continue Shopping')}
                                </Link>
                                <button
                                    type="button"
                                    onClick={handleClearCart}
                                    className="text-xs text-rose-700 hover:text-rose-900 font-medium cursor-pointer"
                                >
                                    {t('clear_cart', 'Clear Bag')}
                                </button>
                            </div>
                        </div>

                        {/* Order Summary Box */}
                        <div className="bg-white rounded-2xl border border-stone-200 p-6 sm:p-8 shadow-xs h-fit space-y-6">
                            <h2 className="font-serif text-xl font-medium text-stone-900">
                                {t('order_summary', 'Order Summary')}
                            </h2>

                            <div className="space-y-3 text-xs text-stone-600">
                                <div className="flex justify-between">
                                    <span>{t('subtotal', 'Subtotal')}</span>
                                    <span className="font-semibold text-stone-900 text-sm">
                                        LKR {cart.subtotal.toFixed(2)}
                                    </span>
                                </div>
                                <div className="flex justify-between items-center">
                                    <span>{t('delivery_charge', 'Delivery Fee (Islandwide COD)')}</span>
                                    <span className="font-semibold text-stone-900">
                                        LKR {cart.delivery_charge.toFixed(2)}
                                    </span>
                                </div>
                                <div className="pt-3 border-t border-stone-200 flex justify-between items-baseline text-stone-900">
                                    <span className="text-sm font-semibold">{t('total', 'Total')}</span>
                                    <span className="font-serif text-2xl font-semibold text-stone-900">
                                        LKR {cart.total.toFixed(2)}
                                    </span>
                                </div>
                            </div>

                            <div className="p-3 bg-emerald-50 rounded-xl border border-emerald-100 text-emerald-800 text-xs">
                                <strong>💵 Cash on Delivery (COD)</strong>
                                <p className="text-[11px] text-emerald-700 mt-0.5">
                                    Pay in cash securely to the courier when your package arrives.
                                </p>
                            </div>

                            <Link
                                href="/checkout"
                                className={`w-full block py-4 text-center text-xs uppercase tracking-[0.2em] font-semibold rounded-xl shadow-md transition-all ${
                                    cart.has_out_of_stock
                                        ? 'bg-stone-300 text-stone-500 cursor-not-allowed pointer-events-none'
                                        : 'bg-stone-900 hover:bg-rose-900 text-white cursor-pointer'
                                }`}
                            >
                                {t('proceed_to_checkout', 'Proceed to Checkout')}
                            </Link>
                        </div>
                    </div>
                )}
            </div>
        </PublicLayout>
    );
}

