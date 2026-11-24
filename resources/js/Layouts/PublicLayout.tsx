import { useRecentlyViewed } from '@/hooks/useRecentlyViewed';
import { useTranslation } from '@/hooks/useTranslation';
import { CartDetails, CartItemDetail, PageProps, UpsellProduct } from '@/types';
import { Link, router, usePage } from '@inertiajs/react';
import React, { PropsWithChildren, useEffect, useState } from 'react';

export default function PublicLayout({ children }: PropsWithChildren) {
    const { auth, locale, cartCount = 0, flash, cartDetails, upsellProducts, whatsapp } = usePage<PageProps>().props;
    const { url } = usePage();
    const { t } = useTranslation();
    const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
    const [searchOpen, setSearchOpen] = useState(false);

    // Dynamic WhatsApp Support Contact
    const activeWhatsappNumber = whatsapp?.number || (import.meta.env.VITE_WHATSAPP_NUMBER as string) || '94772345678';
    const activeWhatsappMessage = whatsapp?.message || 'Hello Aura Luxe, I would like to inquire about your products.';
    const isWhatsappEnabled = whatsapp ? whatsapp.is_enabled : true;
    const [cartDrawerOpen, setCartDrawerOpen] = useState(() => {
        if (typeof window !== 'undefined') {
            const params = new URLSearchParams(window.location.search);
            const b = params.get('bag');
            return b === 'open' || b === '1' || b === 'single' || b === 'unlocked' || b === 'empty';
        }
        return false;
    });
    const [searchQuery, setSearchQuery] = useState('');
    const [announcementIndex, setAnnouncementIndex] = useState(0);

    // Toast and upsell states for slide-over drawer
    const [toastMessage, setToastMessage] = useState<string | null>(null);
    const [toastVisible, setToastVisible] = useState(false);
    const [currentUpsellIndex, setCurrentUpsellIndex] = useState(0);
    const [updatingProductId, setUpdatingProductId] = useState<number | null>(null);

    const showToast = (message: string) => {
        setToastMessage(message);
        setToastVisible(true);
        setTimeout(() => {
            setToastVisible(false);
        }, 2600);
    };

    // Close on Escape key
    useEffect(() => {
        const handleKeyDown = (e: KeyboardEvent) => {
            if (e.key === 'Escape') setCartDrawerOpen(false);
        };
        window.addEventListener('keydown', handleKeyDown);
        return () => window.removeEventListener('keydown', handleKeyDown);
    }, []);

    // Cart metrics calculation
    const defaultCartDetails: CartDetails = {
        items: [],
        item_count: 0,
        subtotal: 0,
        delivery_charge: 0,
        total: 0,
        has_out_of_stock: false,
    };

    // Quick demo simulation presets matching user's prototype (?bag=single, ?bag=unlocked)
    const searchParams = typeof window !== 'undefined' ? new URLSearchParams(window.location.search) : null;
    const bagParam = searchParams?.get('bag');
    const isDemoSingle = bagParam === 'single' || bagParam === 'demo-single';
    const isDemoUnlocked = bagParam === 'unlocked' || bagParam === 'demo-unlocked';

    const hasRealCartItems = Boolean(cartDetails && cartDetails.items && cartDetails.items.length > 0);

    const effectiveCart: CartDetails = hasRealCartItems ? cartDetails! : (isDemoUnlocked ? {
        items: [
            {
                product_id: 4,
                name_en: 'Saffron & Royal Lotus Rejuvenating Night Elixir',
                slug: 'saffron-royal-lotus-rejuvenating-night-elixir',
                unit_price: 3450,
                quantity: 1,
                available_stock: 10,
                is_over_stock: false,
                subtotal: 3450,
                image: 'products/saffron_royal_lotus_night_elixir.png',
                category_name_en: 'Facial Serums & Elixirs',
            },
            {
                product_id: 3,
                name_en: 'Hydra-Glow Botanical Day Barrier Cream',
                slug: 'hydra-glow-botanical-day-cream',
                unit_price: 2850,
                quantity: 1,
                available_stock: 10,
                is_over_stock: false,
                subtotal: 2850,
                image: 'products/5HBcHkRZ1I9lwRnMhD7TrsM0dIHPWVQ20aOGynRh.png',
                category_name_en: 'Moisturizers & Creams',
            }
        ],
        item_count: 2,
        subtotal: 6300,
        delivery_charge: 0,
        total: 6300,
        has_out_of_stock: false,
    } : isDemoSingle ? {
        items: [
            {
                product_id: 4,
                name_en: 'Saffron & Royal Lotus Rejuvenating Night Elixir',
                slug: 'saffron-royal-lotus-rejuvenating-night-elixir',
                unit_price: 3450,
                quantity: 1,
                available_stock: 10,
                is_over_stock: false,
                subtotal: 3450,
                image: 'products/saffron_royal_lotus_night_elixir.png',
                category_name_en: 'Facial Serums & Elixirs',
            }
        ],
        item_count: 1,
        subtotal: 3450,
        delivery_charge: 350,
        total: 3800,
        has_out_of_stock: false,
    } : (cartDetails || defaultCartDetails));

    const currentItemCount = effectiveCart.item_count || cartCount;
    const FREE_SHIPPING_THRESHOLD = 5000;
    const cartSubtotal = Number(effectiveCart.subtotal || 0);
    const remainingForFree = Math.max(0, FREE_SHIPPING_THRESHOLD - cartSubtotal);
    const isFreeShipping = cartSubtotal >= FREE_SHIPPING_THRESHOLD && currentItemCount > 0;
    const shippingFee = currentItemCount === 0 ? 0 : (isFreeShipping ? 0 : (Number(effectiveCart.delivery_charge) || 350));
    const estimatedTotal = cartSubtotal + shippingFee;
    const progressPercent = Math.min(100, Math.round((cartSubtotal / FREE_SHIPPING_THRESHOLD) * 100));

    const formatLKR = (amount: number) => `LKR ${Number(amount || 0).toLocaleString('en-US')}`;

    const getImageUrl = (path?: string | null) => {
        if (!path) return null;
        if (path.startsWith('http://') || path.startsWith('https://')) return path;
        if (path.startsWith('/storage/')) return path;
        if (path.startsWith('storage/')) return '/' + path;
        return `/storage/${path.replace(/^\/+/, '')}`;
    };

    // Upsell recommendation pool
    const availableUpsells = (upsellProducts || []).filter(
        (up: UpsellProduct) => !effectiveCart.items.some((item: CartItemDetail) => item.product_id === up.id)
    );
    const pool = availableUpsells.length > 0 ? availableUpsells : (upsellProducts || []);
    const currentUpsell = pool.length > 0 ? pool[currentUpsellIndex % pool.length] : null;

    const cycleUpsell = (direction: number) => {
        if (pool.length === 0) return;
        setCurrentUpsellIndex((prev) => (prev + direction + pool.length) % pool.length);
    };

    const handleUpdateQty = (productId: number, newQty: number) => {
        if (updatingProductId !== null) return;
        setUpdatingProductId(productId);

        if (newQty <= 0) {
            router.delete(`/cart/remove/${productId}`, {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    showToast('Item removed from your bag');
                },
                onError: () => {
                    showToast('Failed to remove item');
                },
                onFinish: () => {
                    setUpdatingProductId(null);
                },
            });
            return;
        }

        router.patch(
            '/cart/update',
            { product_id: productId, quantity: newQty },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    showToast('Bag updated');
                },
                onError: () => {
                    showToast('Failed to update bag');
                },
                onFinish: () => {
                    setUpdatingProductId(null);
                },
            }
        );
    };

    const handleRemoveItem = (productId: number, productName: string) => {
        if (updatingProductId !== null) return;
        setUpdatingProductId(productId);

        router.delete(`/cart/remove/${productId}`, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                showToast(`Removed "${productName}" from your bag`);
            },
            onError: () => {
                showToast('Failed to remove item');
            },
            onFinish: () => {
                setUpdatingProductId(null);
            },
        });
    };

    const handleAddUpsell = (product: { id: number; name: string }) => {
        if (updatingProductId !== null) return;
        setUpdatingProductId(product.id);

        router.post('/cart/add', { product_id: product.id, quantity: 1 }, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                showToast(`Added "${product.name}" to your bag`);
            },
            onError: () => {
                showToast('Failed to add item to bag');
            },
            onFinish: () => {
                setUpdatingProductId(null);
            },
        });
    };

    // Navigation items with dynamic active state & hover tracking
    const navItems = [
        { href: '/', label: t('home', 'Home'), isHome: true },
        { href: '/products', label: t('shop', 'Shop All'), isShop: true },
        { href: '/categories', label: t('categories', 'Collections') },
        { href: '/about', label: t('about_us', 'Our Heritage') },
    ];

    const isLinkActive = (item: (typeof navItems)[0]) => {
        if (item.isHome) {
            return url === '/' || url === '';
        }
        if (item.isShop) {
            return url.startsWith('/products');
        }
        return url.startsWith(item.href);
    };

    // Global recently viewed lifecycle & cross-tab sync
    useRecentlyViewed(auth?.user);

    // Rotating luxury top announcements (Spa Ceylon style)
    const announcements = [
        '🌿 Royal Ceylon Botanicals • 100% Vegetarian & Cruelty-Free Formulations',
        '🚚 Islandwide Cash on Delivery (COD) Guaranteed • Pay Securely At Your Doorstep',
        '✨ Free Islandwide Express Delivery On All Luxury Orders Over LKR 5,000',
    ];

    useEffect(() => {
        const timer = setInterval(() => {
            setAnnouncementIndex((prev) => (prev + 1) % announcements.length);
        }, 4500);
        return () => clearInterval(timer);
    }, [announcements.length]);

    const switchLocale = (newLocale: string) => {
        if (newLocale === locale) return;
        router.post(route('locale.switch'), { locale: newLocale }, {
            preserveScroll: true,
        });
    };

    const handleSearchSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!searchQuery.trim()) return;
        setSearchOpen(false);
        router.get('/products', { search: searchQuery.trim() });
    };

    const quickSearchTags = [
        { label: 'Saffron Serum', query: 'Saffron' },
        { label: 'True Turmeric', query: 'Turmeric' },
        { label: 'Bakuchiol Night Cream', query: 'Bakuchiol' },
        { label: 'Rose Water Tonic', query: 'Rose' },
        { label: 'Gotu Kola Balm', query: 'Gotu Kola' },
    ];

    return (
        <div className="min-h-screen bg-[#FAF8F5] text-stone-800 flex flex-col font-sans selection:bg-amber-100 selection:text-amber-900">
            {/* 1. Top Announcement Bar & Sleek Track Order Button */}
            <div className="bg-[#141210] text-[#E7D6C4] text-[11px] sm:text-xs py-2 px-4 border-b border-amber-900/30">
                <div className="max-w-7xl mx-auto flex items-center justify-between gap-4">
                    {/* Left: Brand Guarantee Tag */}
                    <div className="hidden md:flex items-center gap-2 text-[11px] text-[#C2B19F] font-serif tracking-wider">
                        <span className="text-amber-400">🌿</span>
                        <span>Royal Ceylon Botanicals</span>
                    </div>

                    {/* Rotating Ticker Center */}
                    <div className="flex-1 text-center transition-opacity duration-500 font-medium tracking-wide">
                        <span>{announcements[announcementIndex]}</span>
                    </div>

                    {/* Right: Sleek Track Order Button */}
                    <div className="flex items-center">
                        <Link
                            href="/track"
                            className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-amber-950/80 hover:bg-amber-900 border border-amber-700/50 text-[#FAF8F5] hover:text-white text-[11px] font-medium tracking-wide transition-all shadow-xs group"
                        >
                            <svg className="w-3.5 h-3.5 text-amber-300 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z" />
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0" />
                            </svg>
                            <span>Track Order</span>
                        </Link>
                    </div>
                </div>
            </div>

            {/* Flash Messages */}
            {flash.success && (
                <div className="bg-emerald-50 border-b border-emerald-200 text-emerald-900 text-xs py-2.5 px-4 text-center font-medium">
                    ✓ {flash.success}
                </div>
            )}
            {flash.error && (
                <div className="bg-rose-50 border-b border-rose-200 text-rose-900 text-xs py-2.5 px-4 text-center font-medium">
                    ⚠ {flash.error}
                </div>
            )}

            {/* 3. Main Luxury Navigation Header */}
            <header className="sticky top-0 z-40 bg-white/95 backdrop-blur-md border-b border-stone-200/90 shadow-xs">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 sm:h-24 flex items-center justify-between gap-4">
                    {/* Left: Desktop Navigation Links (Pop-up color smoothly follows cursor) */}
                    <nav className="hidden lg:flex items-center space-x-7 text-xs uppercase tracking-[0.18em] font-semibold text-stone-700">
                        {navItems.map((item) => {
                            const active = isLinkActive(item);
                            return (
                                <Link
                                    key={item.href}
                                    href={item.href}
                                    className={`relative py-1.5 group transition-all duration-300 cursor-pointer ${
                                        active
                                            ? 'text-amber-900 font-bold'
                                            : 'text-stone-700 hover:text-amber-900 font-semibold'
                                    }`}
                                >
                                    <span className="inline-block transition-transform duration-200 group-hover:-translate-y-0.5">
                                        {item.label}
                                    </span>
                                    {/* Animated underline indicator: expands and pops under cursor */}
                                    <span
                                        className={`absolute bottom-0 left-0 h-[2px] bg-amber-900 transition-all duration-300 ease-out rounded-full ${
                                            active ? 'w-full' : 'w-0 group-hover:w-full'
                                        }`}
                                    />
                                </Link>
                            );
                        })}
                    </nav>

                    {/* Mobile Hamburger Button (Left on Mobile) */}
                    <div className="flex items-center lg:hidden">
                        <button
                            type="button"
                            onClick={() => setMobileMenuOpen(true)}
                            className="p-2 text-stone-800 hover:text-stone-900 min-h-[44px] min-w-[44px] flex items-center justify-center cursor-pointer"
                            aria-label="Open menu"
                        >
                            <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M4 6h16M4 12h16M4 18h16" />
                            </svg>
                        </button>
                    </div>

                    {/* Center: Brand Identity (Spa Ceylon luxury serif style) */}
                    <Link href="/" className="group flex flex-col items-center text-center">
                        <span className="font-serif text-2xl sm:text-3xl lg:text-4xl tracking-[0.24em] font-semibold text-stone-900 uppercase group-hover:text-amber-900 transition-colors">
                            Aura Luxe
                        </span>
                        <span className="text-[9px] sm:text-[10px] tracking-[0.38em] uppercase text-amber-800 font-semibold -mt-0.5">
                            Royal Ceylon Botanicals
                        </span>
                    </Link>

                    {/* Right Utilities: Search, Account, Bag */}
                    <div className="flex items-center space-x-1 sm:space-x-3">
                        {/* Search Trigger Button */}
                        <button
                            type="button"
                            onClick={() => setSearchOpen(true)}
                            className="p-2 text-stone-700 hover:text-amber-900 min-h-[44px] min-w-[44px] flex items-center justify-center cursor-pointer transition-colors"
                            aria-label="Search collection"
                        >
                            <svg className="w-5 h-5 sm:w-6 sm:h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </button>

                        {/* Account & Auth Controls */}
                        {auth.user ? (
                            <div className="hidden sm:flex items-center gap-2">
                                <Link
                                    href={
                                        auth.user.role === 'customer'
                                            ? '/dashboard'
                                            : auth.user.role.includes('agent')
                                            ? '/agent/dashboard'
                                            : '/admin/dashboard'
                                    }
                                    className="inline-flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wider px-3.5 py-2 border border-stone-300 rounded-lg text-stone-800 hover:border-amber-900 hover:bg-[#FAF6F0] hover:text-amber-900 transition-all min-h-[42px]"
                                >
                                    <svg className="w-4 h-4 text-amber-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                    </svg>
                                    <span>{auth.user.name.split(' ')[0]}</span>
                                </Link>
                                <Link
                                    href="/logout"
                                    method="post"
                                    as="button"
                                    className="p-2 text-stone-400 hover:text-rose-700 transition-colors min-h-[42px] cursor-pointer"
                                    title={t('logout', 'Sign Out')}
                                >
                                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                    </svg>
                                </Link>
                            </div>
                        ) : (
                            <div className="hidden sm:flex items-center gap-2">
                                <Link
                                    href="/login"
                                    className="text-xs font-semibold uppercase tracking-wider px-3 py-2 text-stone-700 hover:text-amber-900 transition-colors min-h-[42px] inline-flex items-center"
                                >
                                    {t('login', 'Sign In')}
                                </Link>

                                {/* PROMINENT REGISTER BUTTON */}
                                <Link
                                    href="/register"
                                    className="text-xs font-semibold uppercase tracking-wider px-4 py-2 bg-[#181614] hover:bg-amber-900 text-[#FAF8F5] rounded-lg shadow-xs hover:shadow-md transition-all duration-300 min-h-[42px] inline-flex items-center justify-center gap-1.5 cursor-pointer"
                                >
                                    <span>{t('register', 'Register')}</span>
                                    <span className="text-amber-300 font-normal">✨</span>
                                </Link>
                            </div>
                        )}

                        {/* Shopping Bag Trigger Button */}
                        <button
                            type="button"
                            onClick={() => setCartDrawerOpen(true)}
                            className="relative p-2 text-stone-800 hover:text-amber-900 transition-colors min-h-[44px] min-w-[44px] flex items-center justify-center cursor-pointer"
                            aria-label="View Shopping Bag"
                        >
                            <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                            </svg>
                            {cartCount > 0 && (
                                <span className="absolute top-1.5 right-1.5 bg-amber-900 text-white text-[10px] font-bold rounded-full w-5 h-5 flex items-center justify-center shadow-xs">
                                    {cartCount}
                                </span>
                            )}
                        </button>
                    </div>
                </div>
            </header>

            {/* 4. Interactive Slide-down Search Modal */}
            {searchOpen && (
                <div className="fixed inset-0 z-50 bg-stone-900/60 backdrop-blur-xs flex items-start justify-center p-4 pt-20 animate-fade-in">
                    <div className="bg-white rounded-3xl max-w-2xl w-full p-6 sm:p-8 shadow-2xl border border-stone-200 space-y-6">
                        <div className="flex items-center justify-between pb-3 border-b border-stone-100">
                            <span className="text-xs uppercase tracking-[0.2em] font-semibold text-amber-900">
                                Botanical Discovery
                            </span>
                            <button
                                type="button"
                                onClick={() => setSearchOpen(false)}
                                className="text-stone-400 hover:text-stone-700 min-h-[44px] min-w-[44px] flex items-center justify-center text-lg cursor-pointer"
                            >
                                ✕
                            </button>
                        </div>

                        <form onSubmit={handleSearchSubmit} className="relative">
                            <input
                                type="text"
                                autoFocus
                                value={searchQuery}
                                onChange={(e) => setSearchQuery(e.target.value)}
                                placeholder="Search by botanical ingredient, formula, or skin concern..."
                                className="w-full text-sm sm:text-base pl-12 pr-4 py-3.5 rounded-2xl border-stone-300 focus:border-amber-900 focus:ring-amber-900 shadow-inner"
                            />
                            <svg className="w-6 h-6 text-stone-400 absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </form>

                        <div>
                            <span className="text-[11px] font-semibold uppercase tracking-wider text-stone-500 block mb-3">
                                Popular Botanical Searches
                            </span>
                            <div className="flex flex-wrap gap-2">
                                {quickSearchTags.map((tag) => (
                                    <button
                                        key={tag.label}
                                        type="button"
                                        onClick={() => {
                                            setSearchOpen(false);
                                            router.get('/products', { search: tag.query });
                                        }}
                                        className="text-xs font-medium px-3.5 py-1.5 rounded-full bg-stone-100 hover:bg-amber-100 hover:text-amber-900 text-stone-700 transition-colors cursor-pointer"
                                    >
                                        🌿 {tag.label}
                                    </button>
                                ))}
                            </div>
                        </div>
                    </div>
                </div>
            )}

            {/* 5. Luxury Slide-Over Shopping Bag Drawer (Royal Ceylon Botanicals) */}
            <div
                className={`fixed inset-0 z-50 flex justify-end overflow-hidden transition-opacity duration-300 ${
                    cartDrawerOpen ? 'opacity-100 pointer-events-auto' : 'opacity-0 pointer-events-none'
                }`}
                aria-modal="true"
                role="dialog"
                aria-labelledby="drawer-title"
            >
                {/* Backdrop */}
                <div
                    onClick={() => setCartDrawerOpen(false)}
                    className="fixed inset-0 bg-espresso/45 backdrop-blur-[2px] transition-opacity cursor-pointer"
                />

                {/* Drawer Container (Full width on mobile, max 460px on tablet/desktop) */}
                <aside
                    className={`relative min-w-0 w-full sm:w-[460px] max-w-full bg-ivory h-full shadow-2xl flex flex-col justify-between overflow-x-hidden border-l border-sand transition-transform duration-300 ease-out z-10 ${
                        cartDrawerOpen ? 'translate-x-0' : 'translate-x-full'
                    }`}
                >
                    {/* 1) STICKY TOP HEADER */}
                    <div className="bg-ivory z-10 shrink-0 border-b border-sand/80">
                        {/* Title Bar */}
                        <div className="px-4 sm:px-6 py-4 flex items-center justify-between">
                            <div className="flex items-center gap-2 sm:gap-2.5">
                                {/* Luxury Shopping Bag Icon */}
                                <svg className="w-5 h-5 text-terracotta" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.75">
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M6 7h12l1 13H5L6 7z" />
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M9 7V5a3 3 0 0 1 6 0v2" />
                                </svg>
                                <h2 id="drawer-title" className="font-serif text-lg sm:text-[22px] text-espresso font-normal tracking-tight">
                                    Your Botanical Bag <span className="text-terracotta font-normal">({currentItemCount})</span>
                                </h2>
                            </div>
                            <button
                                type="button"
                                onClick={() => setCartDrawerOpen(false)}
                                aria-label="Close shopping bag"
                                className="p-2 -mr-2 text-stone-500 hover:text-espresso hover:bg-cream rounded-full transition-colors cursor-pointer"
                            >
                                <svg className="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.75">
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    {/* 2) SCROLLABLE MIDDLE AREA: PRODUCT ITEMS + COMPLETE YOUR RITUAL UPSELL */}
                    <div className="flex-1 overflow-y-auto botanical-scroll px-4 sm:px-6 py-4 sm:py-5 space-y-5 sm:space-y-6">
                        {/* Cart Items Container */}
                        <div className="space-y-4">
                            {effectiveCart.items.length === 0 ? (
                                <div className="bg-cream/60 border border-sand rounded-2xl p-6 text-center my-2">
                                    <div className="w-12 h-12 rounded-full bg-ivory border border-sand mx-auto flex items-center justify-center text-terracotta mb-3">
                                        <svg className="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5">
                                            <path strokeLinecap="round" strokeLinejoin="round" d="M12 21c-4.5-3-7-7.5-7-12 0-2.5 2-4.5 4.5-4.5 1.5 0 2.5.8 2.5.8s1-.8 2.5-.8C17 4.5 19 6.5 19 9c0 4.5-2.5 9-7 12z" />
                                        </svg>
                                    </div>
                                    <h3 className="font-serif text-lg text-espresso mb-1">Your botanical bag is empty</h3>
                                    <p className="text-xs text-stone-500 max-w-[260px] mx-auto leading-relaxed mb-4">
                                        Explore our cold-pressed Ceylon elixirs and aromatic night rituals crafted in small batches.
                                    </p>
                                    {pool.length > 0 ? (
                                        <button
                                            type="button"
                                            onClick={() => handleAddUpsell(pool[0])}
                                            className="inline-flex items-center gap-1.5 bg-terracotta hover:bg-terracottaHover text-white text-xs font-semibold px-4 py-2.5 rounded-full transition-colors cursor-pointer shadow-xs"
                                        >
                                            <span>+ Add {pool[0].name.split('&')[0].trim()}</span>
                                        </button>
                                    ) : (
                                        <Link
                                            href="/products"
                                            onClick={() => setCartDrawerOpen(false)}
                                            className="inline-flex items-center gap-1.5 bg-terracotta hover:bg-terracottaHover text-white text-xs font-semibold px-4 py-2.5 rounded-full transition-colors shadow-xs"
                                        >
                                            <span>Explore Botanical Collection</span>
                                        </Link>
                                    )}
                                </div>
                            ) : (
                                effectiveCart.items.map((item: CartItemDetail) => (
                                    <div
                                        key={item.product_id}
                                        className="group bg-white/80 border border-sand rounded-2xl p-3.5 flex gap-3.5 transition-all hover:border-terracotta/30 shadow-[0_2px_10px_-4px_rgba(28,25,23,0.04)]"
                                    >
                                        {/* Botanical Product Thumbnail */}
                                        <div className="w-20 h-20 rounded-xl overflow-hidden shrink-0 border border-sand/80 bg-cream flex items-center justify-center relative">
                                            {item.image ? (
                                                <img
                                                    src={getImageUrl(item.image) || ''}
                                                    alt={item.name_en}
                                                    className="w-full h-full object-cover"
                                                    onError={(e) => {
                                                        const img = e.currentTarget;
                                                        img.style.display = 'none';
                                                        const fallback = img.parentElement?.querySelector('.botanical-fallback');
                                                        if (fallback) fallback.classList.remove('hidden');
                                                    }}
                                                />
                                            ) : null}
                                            <div className={`w-full h-full ${item.image ? 'botanical-fallback hidden' : ''}`}>
                                                <svg viewBox="0 0 80 80" className="w-full h-full">
                                                    <rect width="80" height="80" fill="#F4EAE0" />
                                                    <path d="M22 58 C14 46, 18 32, 30 28 C28 40, 26 50, 22 58 Z" fill="#9A4E22" opacity="0.16" />
                                                    <path d="M58 56 C66 44, 62 30, 50 26 C52 38, 54 48, 58 56 Z" fill="#9A4E22" opacity="0.16" />
                                                    <rect x="35" y="13" width="10" height="7" rx="4" fill="#1C1917" />
                                                    <rect x="33" y="19" width="14" height="5" rx="1" fill="#C49A6C" />
                                                    <rect x="28" y="25" width="24" height="41" rx="5" fill="#9A4E22" />
                                                    <rect x="30" y="34" width="20" height="23" rx="1.5" fill="#FDFBF7" />
                                                    <line x1="34" y1="40" x2="46" y2="40" stroke="#1C1917" strokeWidth="1.2" />
                                                    <line x1="36" y1="44" x2="44" y2="44" stroke="#9A4E22" strokeWidth="1" />
                                                    <circle cx="40" cy="51" r="2" fill="#9A4E22" opacity="0.6" />
                                                </svg>
                                            </div>
                                        </div>

                                        {/* Product Details & Controls */}
                                        <div className="flex-1 min-w-0 flex flex-col justify-between">
                                            <div className="flex items-start justify-between gap-2">
                                                <div>
                                                    <Link
                                                        href={`/products/${item.slug}`}
                                                        onClick={() => setCartDrawerOpen(false)}
                                                        className="font-serif text-[17px] leading-snug text-espresso font-semibold line-clamp-2 hover:text-terracotta transition-colors"
                                                    >
                                                        {item.name_en}
                                                    </Link>
                                                    <p className="text-[11px] text-stone-500 mt-0.5">
                                                        {item.category_name_en || 'Royal Ceylon Botanical'}
                                                    </p>
                                                </div>

                                                {/* Remove Item Button */}
                                                <button
                                                    type="button"
                                                    onClick={() => handleRemoveItem(item.product_id, item.name_en)}
                                                    aria-label={`Remove ${item.name_en}`}
                                                    disabled={updatingProductId === item.product_id}
                                                    className="text-stone-400 hover:text-red-700 p-1 -mr-1 -mt-1 rounded-lg hover:bg-cream transition-colors shrink-0 cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed"
                                                    title="Remove item"
                                                >
                                                    <svg className="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.75">
                                                        <path strokeLinecap="round" strokeLinejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    </svg>
                                                </button>
                                            </div>

                                            {/* Bottom Row: Quantity Stepper + Live Line Price */}
                                            <div className="flex items-center justify-between mt-3">
                                                {/* Interactive - / + Quantity Pill */}
                                                <div className={`inline-flex items-center bg-cream border border-sand rounded-full p-0.5 transition-opacity ${updatingProductId === item.product_id ? 'opacity-60' : ''}`}>
                                                    <button
                                                        type="button"
                                                        onClick={() => handleUpdateQty(item.product_id, item.quantity - 1)}
                                                        aria-label="Decrease quantity"
                                                        disabled={updatingProductId === item.product_id}
                                                        className="w-7 h-7 rounded-full flex items-center justify-center text-espresso hover:bg-ivory active:scale-95 transition-all font-medium text-sm cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed"
                                                    >
                                                        −
                                                    </button>
                                                    <span className="w-7 text-center text-xs font-bold text-espresso select-none">
                                                        {item.quantity}
                                                    </span>
                                                    <button
                                                        type="button"
                                                        onClick={() => handleUpdateQty(item.product_id, item.quantity + 1)}
                                                        aria-label="Increase quantity"
                                                        disabled={updatingProductId === item.product_id || (item.available_stock !== undefined && item.quantity >= item.available_stock)}
                                                        className="w-7 h-7 rounded-full flex items-center justify-center text-espresso hover:bg-ivory active:scale-95 transition-all font-medium text-sm cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed"
                                                    >
                                                        +
                                                    </button>
                                                </div>

                                                {/* Live Price */}
                                                <div className="text-right">
                                                    <span className="text-sm font-bold text-espresso font-sans tabular-nums">{formatLKR(item.subtotal)}</span>
                                                    {item.quantity > 1 && (
                                                        <span className="block text-[10px] text-stone-500 font-sans tabular-nums">
                                                            {formatLKR(item.unit_price)} each
                                                        </span>
                                                    )}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                ))
                            )}
                        </div>

                        {/* 3) COMPLETE YOUR RITUAL / FREQUENTLY PAIRED WITH UPSELL */}
                        {currentUpsell && (
                            <div className="pt-2">
                                <div className="flex items-center justify-between mb-3">
                                    <div>
                                        <h3 className="font-serif text-base text-espresso font-medium">Complete Your Ritual</h3>
                                        <p className="text-[11px] text-stone-500">
                                            {!isFreeShipping && currentItemCount > 0 && currentUpsell.price >= remainingForFree ? (
                                                <span className="text-terracotta font-semibold">Add this to unlock FREE Islandwide Delivery!</span>
                                            ) : (
                                                'Frequently paired for radiant Ceylon glow'
                                            )}
                                        </p>
                                    </div>
                                    {pool.length > 1 && (
                                        <div className="flex items-center gap-1">
                                            <button
                                                type="button"
                                                onClick={() => cycleUpsell(-1)}
                                                aria-label="Previous recommendation"
                                                className="w-7 h-7 rounded-full border border-sand bg-cream/70 hover:bg-cream flex items-center justify-center text-espresso transition-colors cursor-pointer"
                                            >
                                                <svg className="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                                                    <path strokeLinecap="round" strokeLinejoin="round" d="M15 19l-7-7 7-7" />
                                                </svg>
                                            </button>
                                            <button
                                                type="button"
                                                onClick={() => cycleUpsell(1)}
                                                aria-label="Next recommendation"
                                                className="w-7 h-7 rounded-full border border-sand bg-cream/70 hover:bg-cream flex items-center justify-center text-espresso transition-colors cursor-pointer"
                                            >
                                                <svg className="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                                                    <path strokeLinecap="round" strokeLinejoin="round" d="M9 5l7 7-7 7" />
                                                </svg>
                                            </button>
                                        </div>
                                    )}
                                </div>

                                {/* Compact Luxury Upsell Card */}
                                <div className="bg-cream/80 border border-sand rounded-2xl p-3.5 transition-all hover:border-terracotta/40">
                                    <div className="flex items-center gap-3">
                                        <div className="w-14 h-14 rounded-xl overflow-hidden shrink-0 border border-sand bg-ivory flex items-center justify-center relative">
                                            {currentUpsell.image ? (
                                                <img
                                                    src={getImageUrl(currentUpsell.image) || ''}
                                                    alt={currentUpsell.name}
                                                    className="w-full h-full object-cover"
                                                    onError={(e) => {
                                                        const img = e.currentTarget;
                                                        img.style.display = 'none';
                                                        const fallback = img.parentElement?.querySelector('.botanical-fallback');
                                                        if (fallback) fallback.classList.remove('hidden');
                                                    }}
                                                />
                                            ) : null}
                                            <div className={`w-full h-full ${currentUpsell.image ? 'botanical-fallback hidden' : ''}`}>
                                                <svg viewBox="0 0 80 80" className="w-full h-full">
                                                    <rect width="80" height="80" fill="#F4EAE0" />
                                                    <path d="M22 58 C14 46, 18 32, 30 28 C28 40, 26 50, 22 58 Z" fill="#9A4E22" opacity="0.16" />
                                                    <rect x="35" y="13" width="10" height="7" rx="4" fill="#1C1917" />
                                                    <rect x="28" y="25" width="24" height="41" rx="5" fill="#9A4E22" />
                                                    <line x1="34" y1="40" x2="46" y2="40" stroke="#1C1917" strokeWidth="1.2" />
                                                </svg>
                                            </div>
                                        </div>
                                        <div className="flex-1 min-w-0">
                                            <span className="inline-block text-[9px] uppercase tracking-widest font-bold text-terracotta bg-ivory px-2 py-0.5 rounded-full border border-sand mb-1">
                                                {currentUpsell.badge}
                                            </span>
                                            <h4 className="font-serif text-xs sm:text-[13px] text-espresso font-medium truncate">
                                                {currentUpsell.name}
                                            </h4>
                                            <div className="flex items-center justify-between mt-1.5 gap-2">
                                                <span className="text-xs font-bold text-espresso">{formatLKR(currentUpsell.price)}</span>
                                                <button
                                                    type="button"
                                                    onClick={() => handleAddUpsell(currentUpsell)}
                                                    className="bg-ivory hover:bg-espresso text-espresso hover:text-ivory border border-espresso/30 hover:border-espresso px-3 py-1 rounded-full text-[11px] font-bold transition-all active:scale-95 shrink-0 cursor-pointer"
                                                >
                                                    + Add
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        )}
                    </div>

                    {/* 4) STICKY FOOTER: SUBTOTAL, DELIVERY FEE, CTAs & TRUST BADGES */}
                    <div className={`bg-ivory border-t border-sand px-4 sm:px-6 pt-3.5 sm:pt-4 pb-4 sm:pb-5 shrink-0 shadow-[0_-8px_24px_-12px_rgba(28,25,23,0.08)] ${currentItemCount === 0 ? 'opacity-50 pointer-events-none' : ''}`}>
                        {/* Compact & Simple Free Delivery Progress Meter */}
                        {currentItemCount > 0 && (
                            <div className="mb-3 pb-3 border-b border-sand/70">
                                <div className="flex items-center justify-between text-xs mb-1.5">
                                    <div className="flex items-center gap-1.5 font-medium">
                                        {isFreeShipping ? (
                                            <span className="text-sage font-semibold flex items-center gap-1 text-[11px] sm:text-xs">
                                                <span>🌿</span> Free Islandwide Delivery Unlocked!
                                            </span>
                                        ) : (
                                            <span className="text-stone-700 text-[11px] sm:text-xs">
                                                Add <strong className="text-terracotta font-bold">{formatLKR(remainingForFree)}</strong> for <strong className="text-espresso">Free Delivery</strong>
                                            </span>
                                        )}
                                    </div>
                                    <span className="text-[10px] text-stone-400 font-mono">
                                        {isFreeShipping ? '100%' : `${progressPercent}%`}
                                    </span>
                                </div>
                                <div className="w-full h-1.5 bg-sand rounded-full overflow-hidden">
                                    <div
                                        className={`h-full rounded-full transition-all duration-500 ease-out ${
                                            isFreeShipping ? 'unlocked-bar' : 'bg-terracotta'
                                        }`}
                                        style={{ width: `${progressPercent}%` }}
                                    />
                                </div>
                            </div>
                        )}

                        {/* Price Breakdown */}
                        <div className="space-y-1.5 mb-4 text-xs font-sans">
                            <div className="flex justify-between text-stone-500">
                                <span>Subtotal</span>
                                <span className="font-semibold text-espresso tabular-nums">{formatLKR(cartSubtotal)}</span>
                            </div>
                            <div className="flex justify-between text-stone-500">
                                <span>Islandwide Delivery (COD)</span>
                                <span>
                                    {isFreeShipping ? (
                                        <span className="text-sage font-bold">FREE</span>
                                    ) : currentItemCount === 0 ? (
                                        'LKR 0'
                                    ) : (
                                        <span className="font-semibold text-espresso tabular-nums">{formatLKR(shippingFee)}</span>
                                    )}
                                </span>
                            </div>
                            <div className="flex justify-between items-baseline pt-2 border-t border-sand/70">
                                <span className="text-sm font-semibold text-espresso">Estimated Total</span>
                                <div className="text-right">
                                    <span className="font-sans text-lg font-bold text-espresso tabular-nums">{formatLKR(estimatedTotal)}</span>
                                    <span className="block text-[10px] text-stone-500">Pay in cash upon delivery</span>
                                </div>
                            </div>
                        </div>

                        {/* Checkout Action Button */}
                        <div>
                            <Link
                                href="/checkout"
                                onClick={() => setCartDrawerOpen(false)}
                                className="w-full bg-espresso hover:bg-terracotta active:scale-[0.99] text-ivory py-3.5 px-5 rounded-full text-xs font-bold tracking-[0.14em] uppercase transition-all duration-200 flex items-center justify-center gap-2 shadow-sm cursor-pointer"
                            >
                                <span>Checkout</span>
                                <svg className="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3" />
                                </svg>
                            </Link>
                        </div>

                        {/* Trust Badges Footer */}
                        <div className="mt-3.5 pt-2.5 flex items-center justify-center flex-wrap gap-x-3 gap-y-1 text-[11px] text-stone-500">
                            <span className="inline-flex items-center gap-1">
                                <span className="text-sage font-bold">✓</span> COD Islandwide
                            </span>
                            <span className="text-sand">•</span>
                            <span className="inline-flex items-center gap-1">
                                <span className="text-sage font-bold">✓</span> 2–4 Days Delivery
                            </span>
                            <span className="text-sand">•</span>
                            <span className="inline-flex items-center gap-1">
                                <span className="text-sage font-bold">✓</span> 100% Authentic
                            </span>
                        </div>
                    </div>

                    {/* Inline Toast Notification Overlay inside Drawer */}
                    <div
                        className={`pointer-events-none absolute bottom-44 left-5 right-5 bg-espresso text-ivory px-4 py-3 rounded-xl shadow-xl border border-terracotta/40 flex items-center justify-between gap-3 transition-all duration-300 z-30 ${
                            toastVisible ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-2'
                        }`}
                    >
                        <div className="flex items-center gap-2.5">
                            <span className="w-6 h-6 rounded-full bg-sage text-white flex items-center justify-center text-xs shrink-0">✓</span>
                            <p className="text-xs font-medium leading-snug">{toastMessage || 'Cart updated'}</p>
                        </div>
                    </div>
                </aside>
            </div>

            {/* 6. Mobile Off-Canvas Navigation Drawer */}
            {mobileMenuOpen && (
                <div className="fixed inset-0 z-50 overflow-hidden lg:hidden">
                    <div
                        className="absolute inset-0 bg-stone-900/60 backdrop-blur-xs transition-opacity"
                        onClick={() => setMobileMenuOpen(false)}
                    />

                    <div className="fixed inset-y-0 left-0 max-w-full flex pr-12">
                        <div className="w-screen max-w-xs bg-white shadow-2xl flex flex-col justify-between border-r border-stone-200">
                            {/* Drawer Top */}
                            <div>
                                <div className="p-6 border-b border-stone-200 flex items-center justify-between">
                                    <div className="flex flex-col">
                                        <span className="font-serif text-xl tracking-[0.2em] font-semibold text-stone-900 uppercase">
                                            Aura Luxe
                                        </span>
                                        <span className="text-[8px] tracking-[0.3em] uppercase text-amber-800 font-semibold">
                                            Royal Ceylon Botanicals
                                        </span>
                                    </div>
                                    <button
                                        type="button"
                                        onClick={() => setMobileMenuOpen(false)}
                                        className="p-2 text-stone-400 hover:text-stone-700 min-h-[44px] min-w-[44px] flex items-center justify-center text-lg"
                                    >
                                        ✕
                                    </button>
                                </div>

                                {/* Navigation Links */}
                                <nav className="p-5 space-y-1 text-sm font-medium text-stone-800 divide-y divide-stone-100">
                                    <Link
                                        href="/"
                                        onClick={() => setMobileMenuOpen(false)}
                                        className="block py-3 hover:text-amber-900 transition-colors"
                                    >
                                        {t('home', 'Home')}
                                    </Link>
                                    <Link
                                        href="/products"
                                        onClick={() => setMobileMenuOpen(false)}
                                        className="block py-3 hover:text-amber-900 transition-colors"
                                    >
                                        {t('shop', 'Shop All Products')}
                                    </Link>
                                    <Link
                                        href="/categories"
                                        onClick={() => setMobileMenuOpen(false)}
                                        className="block py-3 hover:text-amber-900 transition-colors"
                                    >
                                        {t('categories', 'Botanical Collections')}
                                    </Link>
                                    <Link
                                        href="/about"
                                        onClick={() => setMobileMenuOpen(false)}
                                        className="block py-3 hover:text-amber-900 transition-colors"
                                    >
                                        {t('about_us', 'Our Royal Heritage')}
                                    </Link>
                                    <Link
                                        href="/track"
                                        onClick={() => setMobileMenuOpen(false)}
                                        className="block py-3 hover:text-amber-900 transition-colors"
                                    >
                                        {t('track_order', 'Track Order')}
                                    </Link>
                                </nav>
                            </div>

                            {/* Drawer Bottom (Account / Help) */}
                            <div className="p-5 border-t border-stone-200 bg-stone-50 space-y-3">
                                {auth.user ? (
                                    <div className="space-y-2">
                                        <Link
                                            href={
                                                auth.user.role === 'customer'
                                                    ? '/dashboard'
                                                    : auth.user.role.includes('agent')
                                                    ? '/agent/dashboard'
                                                    : '/admin/dashboard'
                                            }
                                            onClick={() => setMobileMenuOpen(false)}
                                            className="w-full text-center py-2.5 px-4 bg-stone-900 text-white rounded-xl text-xs font-semibold uppercase tracking-wider block"
                                        >
                                            My Account ({auth.user.name.split(' ')[0]})
                                        </Link>
                                        <Link
                                            href="/logout"
                                            method="post"
                                            as="button"
                                            className="w-full text-center text-xs text-rose-700 font-medium py-1.5 block cursor-pointer"
                                        >
                                            {t('logout', 'Sign Out')}
                                        </Link>
                                    </div>
                                ) : (
                                    <div className="grid grid-cols-2 gap-2">
                                        <Link
                                            href="/login"
                                            onClick={() => setMobileMenuOpen(false)}
                                            className="text-center py-2.5 px-3 rounded-xl border border-stone-300 text-stone-800 text-xs font-semibold uppercase"
                                        >
                                            {t('login', 'Sign In')}
                                        </Link>
                                        <Link
                                            href="/register"
                                            onClick={() => setMobileMenuOpen(false)}
                                            className="text-center py-2.5 px-3 rounded-xl bg-stone-900 text-white text-xs font-semibold uppercase"
                                        >
                                            {t('register', 'Register')}
                                        </Link>
                                    </div>
                                )}

                                <p className="text-[11px] text-stone-500 text-center pt-2">
                                    Concierge Hotline: <strong className="text-stone-800">+94 11 234 5678</strong>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            )}

            {/* 7. Main Page Content */}
            <main className="flex-1">{children}</main>

            {/* 8. Luxury Dark Botanical Footer (Spa Ceylon royal aesthetic) */}
            <footer className="bg-[#121110] text-stone-300 border-t border-amber-950/80 mt-20">

                {/* Brand & Navigation Columns */}
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-10 lg:gap-12">
                    {/* Brand Story */}
                    <div className="space-y-4">
                        <div>
                            <span className="font-serif text-2xl tracking-[0.25em] font-semibold text-white uppercase block">
                                Aura Luxe
                            </span>
                            <span className="text-[9px] tracking-[0.4em] uppercase text-amber-400 font-semibold block mt-0.5">
                                Royal Ceylon Botanicals
                            </span>
                        </div>
                        <p className="text-xs text-stone-400 leading-relaxed font-light">
                            Crafted in Sri Lanka using indigenous Ayurveda science and the finest organically cultivated botanicals. Designed to awaken your skin's natural radiance.
                        </p>
                    </div>

                    {/* Quick Collections */}
                    <div>
                        <h4 className="text-xs font-semibold text-amber-400 uppercase tracking-widest mb-4">
                            Botanical Collections
                        </h4>
                        <ul className="space-y-2.5 text-xs text-stone-400">
                            <li><Link href="/products?filter=bestseller" className="hover:text-white transition-colors">Customer Favorites</Link></li>
                            <li><Link href="/products" className="hover:text-white transition-colors">Facial Radiance Serums</Link></li>
                            <li><Link href="/products" className="hover:text-white transition-colors">Overnight Repair Creams</Link></li>
                            <li><Link href="/products" className="hover:text-white transition-colors">Purifying Clay Masques</Link></li>
                            <li><Link href="/products" className="hover:text-white transition-colors">Curated Gift Rituals</Link></li>
                        </ul>
                    </div>

                    {/* Customer Concierge */}
                    <div>
                        <h4 className="text-xs font-semibold text-amber-400 uppercase tracking-widest mb-4">
                            Concierge & Support
                        </h4>
                        <ul className="space-y-2.5 text-xs text-stone-400">
                            <li><Link href="/track" className="hover:text-white transition-colors">Track Your Order</Link></li>
                            <li><Link href="/about" className="hover:text-white transition-colors">Our Royal Heritage</Link></li>
                            <li><Link href="/return-policy" className="hover:text-white transition-colors">Return & Refund Policy</Link></li>
                            <li><Link href="/privacy-policy" className="hover:text-white transition-colors">Privacy Policy</Link></li>
                            <li><Link href="/terms" className="hover:text-white transition-colors">Terms of Service</Link></li>
                            <li><Link href="/agent/register" className="text-amber-300 hover:underline">Become a Brand Ambassador →</Link></li>
                        </ul>
                    </div>

                    {/* Contact & Islandwide Delivery */}
                    <div>
                        <h4 className="text-xs font-semibold text-amber-400 uppercase tracking-widest mb-4">
                            Islandwide Dispatch
                        </h4>
                        <div className="text-xs text-stone-400 space-y-2 leading-relaxed">
                            <p>
                                Client Care Line:<br />
                                <a href="tel:+94112345678" className="text-white font-medium hover:text-amber-300">+94 11 234 5678</a>
                            </p>
                            <p>
                                Email Inquiries:<br />
                                <span className="text-stone-300">concierge@auraluxe.lk</span>
                            </p>
                            <div className="pt-2">
                                <span className="text-[11px] font-semibold text-stone-300 block mb-1">
                                    Courier Partners:
                                </span>
                                <span className="text-[11px] text-stone-400">
                                    Pronto • Domex • Certis Lanka Express
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Bottom Bar */}
                <div className="border-t border-amber-950/60 py-6 bg-[#0E0D0C]">
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-between gap-4 text-xs text-stone-500 text-center sm:text-left">
                        <div>
                            © {new Date().getFullYear()} Aura Luxe Cosmetics (Pvt) Ltd. All rights reserved. Made in Sri Lanka.
                        </div>
                        <div className="flex items-center gap-4 text-stone-400 text-xs">
                            <span>100% Cash on Delivery</span>
                            <span>•</span>
                            <span>Cruelty-Free</span>
                            <span>•</span>
                            <span>Natural Ayurveda</span>
                        </div>
                    </div>
                </div>
            </footer>

            {/* Floating WhatsApp Quick Connect Button for all client-facing pages */}
            {isWhatsappEnabled && (
                <aside aria-label="Customer Support WhatsApp">
                    <a
                        href={`https://wa.me/${activeWhatsappNumber}?text=${encodeURIComponent(activeWhatsappMessage)}`}
                        target="_blank"
                        rel="noopener noreferrer"
                        aria-label="Chat with Aura Luxe on WhatsApp"
                        title="Chat with Aura Luxe Concierge on WhatsApp"
                        className="fixed bottom-6 right-6 z-40 group flex items-center gap-2.5 bg-[#25D366] hover:bg-[#20BD5A] text-white p-3.5 rounded-full shadow-[0_4px_20px_rgba(37,211,102,0.4)] hover:shadow-[0_6px_25px_rgba(37,211,102,0.6)] transition-all duration-300 hover:scale-105 active:scale-95 focus:outline-hidden focus:ring-4 focus:ring-emerald-300/50 cursor-pointer"
                    >
                        {/* Official WhatsApp Logo SVG */}
                        <div className="relative flex items-center justify-center">
                            <svg
                                className="w-7 h-7 sm:w-8 sm:h-8 fill-white shrink-0 drop-shadow-xs"
                                viewBox="0 0 24 24"
                                aria-hidden="true"
                            >
                                <path d="M17.472 14.382c-.301-.15-1.78-.878-2.056-.979-.275-.1-.475-.15-.675.15-.2.3-.775.98-1.025 1.28-.25.3-.45.325-.75.175-.3-.15-1.267-.467-2.414-1.489-.893-.797-1.496-1.782-1.671-2.082-.175-.3-.019-.462.131-.611.135-.134.3-.35.45-.525.15-.175.2-.3.3-.5.1-.2.05-.375-.025-.525-.075-.15-.675-1.625-.925-2.225-.244-.584-.493-.505-.675-.514-.175-.009-.375-.01-.575-.01-.2 0-.525.075-.8.375s-1.05 1.025-1.05 2.5 1.075 2.9 1.225 3.1c.15.2 2.115 3.23 5.124 4.53.716.31 1.275.495 1.71.634.72.23 1.375.197 1.892.12.578-.086 1.78-.728 2.03-1.43.25-.702.25-1.303.175-1.428-.075-.125-.275-.2-.575-.35zM12.04 2C6.51 2 2.02 6.49 2.02 12.02c0 1.98.58 3.86 1.68 5.46L2 22l4.67-1.63c1.54 1.02 3.36 1.57 5.37 1.57 5.53 0 10.02-4.49 10.02-10.02S17.57 2 12.04 2zm0 18.25c-1.75 0-3.41-.5-4.84-1.44l-.35-.23-2.78.97.99-2.7-.23-.37c-1.02-1.48-1.56-3.23-1.56-5.06 0-4.54 3.7-8.24 8.24-8.24 4.54 0 8.24 3.7 8.24 8.24 0 4.54-3.7 8.24-8.24 8.24z"/>
                            </svg>
                            {/* Live Concierge Active Beacon */}
                            <span className="absolute -top-1 -right-1 flex h-3 w-3">
                                <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-200 opacity-80"></span>
                                <span className="relative inline-flex rounded-full h-3 w-3 bg-white border border-emerald-500"></span>
                            </span>
                        </div>

                        {/* Expandable Label on Desktop Hover */}
                        <span className="max-w-0 overflow-hidden whitespace-nowrap group-hover:max-w-xs transition-all duration-300 ease-in-out font-sans text-xs font-semibold tracking-wide pr-0 group-hover:pr-2">
                            Chat on WhatsApp
                        </span>
                    </a>
                </aside>
            )}
        </div>
    );
}
