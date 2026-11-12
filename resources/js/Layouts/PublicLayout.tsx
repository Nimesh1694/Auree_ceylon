import { useTranslation } from '@/hooks/useTranslation';
import { PageProps } from '@/types';
import { Link, router, usePage } from '@inertiajs/react';
import React, { PropsWithChildren, useState } from 'react';

export default function PublicLayout({ children }: PropsWithChildren) {
    const { auth, locale, cartCount, flash } = usePage<PageProps>().props;
    const { t } = useTranslation();
    const [mobileMenuOpen, setMobileMenuOpen] = useState(false);

    const switchLocale = (newLocale: string) => {
        if (newLocale === locale) return;
        router.post(route('locale.switch'), { locale: newLocale }, {
            preserveScroll: true,
        });
    };

    return (
        <div className="min-h-screen bg-[#FAF8F5] text-stone-800 flex flex-col font-sans selection:bg-rose-100 selection:text-rose-900">
            {/* Top Announcement Bar */}
            <div className="bg-stone-900 text-stone-200 text-xs py-2 px-4 text-center tracking-wider flex items-center justify-center gap-2">
                <span className="inline-block w-2 h-2 rounded-full bg-emerald-400"></span>
                <span>
                    {t('free_delivery_note', 'Islandwide Cash on Delivery (COD) Available • Pay when you receive')}
                </span>
            </div>

            {/* Flash Messages */}
            {flash.success && (
                <div className="bg-emerald-50 border-b border-emerald-200 text-emerald-800 text-sm py-2.5 px-4 text-center">
                    {flash.success}
                </div>
            )}
            {flash.error && (
                <div className="bg-rose-50 border-b border-rose-200 text-rose-800 text-sm py-2.5 px-4 text-center">
                    {flash.error}
                </div>
            )}

            {/* Main Navigation Header */}
            <header className="sticky top-0 z-40 bg-white/90 backdrop-blur-md border-b border-stone-200 shadow-xs">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between">
                    {/* Brand Logo */}
                    <div className="flex items-center gap-8">
                        <Link href="/" className="group flex flex-col">
                            <span className="font-serif text-2xl tracking-[0.2em] font-semibold text-stone-900 uppercase group-hover:text-rose-900 transition-colors">
                                Aura Luxe
                            </span>
                            <span className="text-[10px] tracking-[0.3em] uppercase text-stone-600 -mt-0.5">
                                Botanical Cosmetics
                            </span>
                        </Link>

                        {/* Desktop Navigation Links */}
                        <nav className="hidden md:flex items-center space-x-7 text-sm font-medium tracking-wide text-stone-700">
                            <Link href="/" className="hover:text-rose-800 transition-colors">
                                {t('home', 'Home')}
                            </Link>
                            <Link href="/products" className="hover:text-rose-800 transition-colors">
                                {t('shop', 'Shop')}
                            </Link>
                            <Link href="/categories" className="hover:text-rose-800 transition-colors">
                                {t('categories', 'Categories')}
                            </Link>
                            <Link href="/about" className="hover:text-rose-800 transition-colors">
                                {t('about_us', 'About')}
                            </Link>
                            <Link href="/track" className="hover:text-rose-800 transition-colors">
                                {t('track_order', 'Track Order')}
                            </Link>
                        </nav>
                    </div>

                    {/* Right Utilities: Language Switcher, Cart, Auth */}
                    <div className="flex items-center space-x-4">
                        {/* Language Switcher */}
                        <div className="inline-flex rounded-full border border-stone-200 bg-stone-50 p-0.5 text-xs font-medium">
                            <button
                                type="button"
                                onClick={() => switchLocale('en')}
                                className={`px-2.5 py-1 rounded-full transition-all cursor-pointer ${
                                    locale === 'en'
                                        ? 'bg-stone-900 text-white shadow-xs'
                                        : 'text-stone-600 hover:text-stone-900'
                                }`}
                            >
                                English
                            </button>
                            <button
                                type="button"
                                onClick={() => switchLocale('si')}
                                className={`px-2.5 py-1 rounded-full transition-all cursor-pointer ${
                                    locale === 'si'
                                        ? 'bg-stone-900 text-white shadow-xs'
                                        : 'text-stone-600 hover:text-stone-900'
                                }`}
                            >
                                සිංහල
                            </button>
                        </div>

                        {/* Cart Button */}
                        <Link
                            href="/cart"
                            className="relative p-2 text-stone-700 hover:text-rose-800 transition-colors"
                            aria-label="Shopping Cart"
                        >
                            <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                            </svg>
                            {cartCount > 0 && (
                                <span className="absolute -top-1 -right-1 bg-rose-700 text-white text-[10px] font-bold rounded-full w-5 h-5 flex items-center justify-center">
                                    {cartCount}
                                </span>
                            )}
                        </Link>

                        {/* Auth / Account */}
                        {auth.user ? (
                            <div className="flex items-center gap-2">
                                <Link
                                    href={
                                        auth.user.role === 'customer'
                                            ? '/dashboard'
                                            : auth.user.role.includes('agent')
                                            ? '/agent/dashboard'
                                            : '/admin/dashboard'
                                    }
                                    className="hidden sm:inline-block text-xs uppercase tracking-wider font-semibold px-3 py-1.5 border border-stone-800 text-stone-900 rounded-md hover:bg-stone-900 hover:text-white transition-colors"
                                >
                                    {auth.user.name.split(' ')[0]}
                                </Link>
                                <Link
                                    href="/logout"
                                    method="post"
                                    as="button"
                                    className="text-xs text-stone-600 hover:text-stone-900 underline"
                                >
                                    {t('logout', 'Logout')}
                                </Link>
                            </div>
                        ) : (
                            <div className="hidden sm:flex items-center space-x-2 text-xs uppercase tracking-wider font-medium">
                                <Link
                                    href="/login"
                                    className="px-3 py-1.5 text-stone-700 hover:text-rose-900"
                                >
                                    {t('login', 'Login')}
                                </Link>
                                <Link
                                    href="/register"
                                    className="px-3.5 py-1.5 bg-stone-900 text-white rounded-md hover:bg-stone-800 transition-colors shadow-xs"
                                >
                                    {t('register', 'Register')}
                                </Link>
                            </div>
                        )}

                        {/* Mobile Menu Toggle Button */}
                        <button
                            type="button"
                            onClick={() => setMobileMenuOpen(!mobileMenuOpen)}
                            className="md:hidden p-2 text-stone-700 hover:text-stone-900"
                            aria-label="Toggle menu"
                        >
                            <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                {mobileMenuOpen ? (
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12" />
                                ) : (
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 6h16M4 12h16M4 18h16" />
                                )}
                            </svg>
                        </button>
                    </div>
                </div>

                {/* Mobile Dropdown Menu */}
                {mobileMenuOpen && (
                    <div className="md:hidden bg-white border-b border-stone-200 px-4 pt-2 pb-6 space-y-3">
                        <Link href="/" className="block py-2 text-stone-800 font-medium">
                            {t('home', 'Home')}
                        </Link>
                        <Link href="/products" className="block py-2 text-stone-800 font-medium">
                            {t('shop', 'Shop')}
                        </Link>
                        <Link href="/categories" className="block py-2 text-stone-800 font-medium">
                            {t('categories', 'Categories')}
                        </Link>
                        <Link href="/about" className="block py-2 text-stone-800 font-medium">
                            {t('about_us', 'About Us')}
                        </Link>
                        <Link href="/track" className="block py-2 text-stone-800 font-medium">
                            {t('track_order', 'Track Order')}
                        </Link>
                        <div className="pt-3 border-t border-stone-100 flex items-center justify-between">
                            <div className="inline-flex rounded-full border border-stone-200 bg-stone-50 p-0.5 text-xs font-medium">
                                <button
                                    type="button"
                                    onClick={() => switchLocale('en')}
                                    className={`px-2.5 py-1 rounded-full transition-all cursor-pointer ${
                                        locale === 'en'
                                            ? 'bg-stone-900 text-white shadow-xs'
                                            : 'text-stone-600 hover:text-stone-900'
                                    }`}
                                >
                                    English
                                </button>
                                <button
                                    type="button"
                                    onClick={() => switchLocale('si')}
                                    className={`px-2.5 py-1 rounded-full transition-all cursor-pointer ${
                                        locale === 'si'
                                            ? 'bg-stone-900 text-white shadow-xs'
                                            : 'text-stone-600 hover:text-stone-900'
                                    }`}
                                >
                                    සිංහල
                                </button>
                            </div>
                            {!auth.user ? (
                                <>
                                    <Link href="/login" className="text-sm font-medium text-stone-700">
                                        {t('login', 'Login')}
                                    </Link>
                                    <Link href="/register" className="text-sm font-medium text-rose-800">
                                        {t('register', 'Register')}
                                    </Link>
                                </>
                            ) : (
                                <Link
                                    href="/logout"
                                    method="post"
                                    as="button"
                                    className="text-sm font-medium text-rose-800"
                                >
                                    {t('logout', 'Logout')}
                                </Link>
                            )}
                        </div>
                    </div>
                )}
            </header>

            {/* Page Content */}
            <main className="flex-1">{children}</main>

            {/* Footer */}
            <footer className="bg-stone-900 text-stone-300 mt-20 border-t border-stone-800">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 grid grid-cols-1 md:grid-cols-4 gap-10">
                    {/* Brand column */}
                    <div className="space-y-4">
                        <h3 className="font-serif text-xl tracking-[0.2em] font-semibold text-white uppercase">
                            Aura Luxe
                        </h3>
                        <p className="text-xs text-stone-400 leading-relaxed">
                            {t(
                                'hero_subtitle',
                                'Discover luxury botanical skincare formulated for radiant, healthy skin. Delivered right to your doorstep with Cash on Delivery.'
                            )}
                        </p>
                        <div className="flex items-center space-x-2 text-xs text-stone-400">
                            <span>🇱🇰 Proudly formulated in Sri Lanka</span>
                        </div>
                    </div>

                    {/* Quick links */}
                    <div>
                        <h4 className="text-xs font-semibold text-white uppercase tracking-wider mb-4">
                            {t('shop', 'Shop')}
                        </h4>
                        <ul className="space-y-2.5 text-xs text-stone-400">
                            <li><Link href="/products" className="hover:text-white transition-colors">{t('all_categories', 'All Products')}</Link></li>
                            <li><Link href="/products?filter=featured" className="hover:text-white transition-colors">{t('featured_products', 'Featured')}</Link></li>
                            <li><Link href="/products?filter=bestseller" className="hover:text-white transition-colors">{t('best_sellers', 'Best Sellers')}</Link></li>
                            <li><Link href="/track" className="hover:text-white transition-colors">{t('track_order', 'Track Order')}</Link></li>
                        </ul>
                    </div>

                    {/* Information / Policies */}
                    <div>
                        <h4 className="text-xs font-semibold text-white uppercase tracking-wider mb-4">
                            Policies
                        </h4>
                        <ul className="space-y-2.5 text-xs text-stone-400">
                            <li><Link href="/about" className="hover:text-white transition-colors">{t('about_us', 'About Us')}</Link></li>
                            <li><Link href="/return-policy" className="hover:text-white transition-colors">Return & Refund Policy</Link></li>
                            <li><Link href="/privacy-policy" className="hover:text-white transition-colors">Privacy Policy</Link></li>
                            <li><Link href="/terms" className="hover:text-white transition-colors">Terms of Service</Link></li>
                        </ul>
                    </div>

                    {/* Contact & Support */}
                    <div>
                        <h4 className="text-xs font-semibold text-white uppercase tracking-wider mb-4">
                            {t('contact_us', 'Contact Us')}
                        </h4>
                        <p className="text-xs text-stone-400 leading-relaxed mb-2">
                            Customer Care Hotline:<br />
                            <span className="text-stone-200 font-medium">+94 11 234 5678</span>
                        </p>
                        <p className="text-xs text-stone-400">
                            Email: <span className="text-stone-200">care@auraluxe.lk</span>
                        </p>
                        <div className="mt-4 pt-4 border-t border-stone-800 text-[11px] text-stone-400">
                            Payment: <strong className="text-emerald-400">Cash on Delivery (COD)</strong>
                        </div>
                    </div>
                </div>

                <div className="border-t border-stone-800 py-6 text-center text-xs text-stone-400">
                    © {new Date().getFullYear()} Aura Luxe Cosmetics (Pvt) Ltd. All rights reserved.
                </div>
            </footer>
        </div>
    );
}

