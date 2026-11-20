import { PageProps } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import React, { PropsWithChildren, useState } from 'react';

export default function AgentLayout({ children }: PropsWithChildren) {
    const { auth, flash } = usePage<PageProps>().props;
    const { url } = usePage();
    const [copied, setCopied] = useState(false);
    const [mobileOpen, setMobileOpen] = useState(false);

    const referralCode = auth.user?.referral_code ?? '';
    const referralLink = typeof window !== 'undefined'
        ? `${window.location.origin}?ref=${referralCode}`
        : `?ref=${referralCode}`;

    const copyReferralLink = () => {
        if (typeof navigator !== 'undefined') {
            navigator.clipboard.writeText(referralLink);
            setCopied(true);
            setTimeout(() => setCopied(false), 2500);
        }
    };

    const isHighLevel = auth.user?.role === 'high_level_agent';
    const unreadCount = Number((auth as any).unread_announcements_count ?? 0);

    const navItems = [
        { label: 'Dashboard', href: '/agent/dashboard', icon: '📊' },
        { label: 'Performance Analytics', href: '/agent/analytics', icon: '📈' },
        { label: 'Announcements', href: '/agent/announcements', icon: '📢', badge: unreadCount },
        { label: 'Place Customer Order', href: '/agent/orders/create', icon: '➕' },
        { label: 'My Placed Orders', href: '/agent/orders', icon: '📦' },
        { label: 'My Commissions', href: '/agent/commissions', icon: '💰' },
        { label: 'Wallet & Payouts', href: '/agent/wallet', icon: '💳' },
        { label: 'Monthly Statements', href: '/agent/wallet/statements', icon: '📄' },
        { label: 'Achievements', href: '/agent/achievements', icon: '🏆' },
        { label: 'My Downline Network', href: '/agent/team', icon: '👥' },
        { label: 'Profile & KYC', href: '/agent/profile', icon: '👤' },
    ];

    const isItemActive = (href: string) => {
        if (href === '/agent/dashboard') {
            return url === '/agent/dashboard' || url === '/agent';
        }
        return url?.startsWith(href);
    };

    return (
        <div className="min-h-screen bg-stone-100 flex flex-col font-sans">
            {/* Top Bar */}
            <header className="bg-stone-900 text-white border-b border-stone-800 sticky top-0 z-30">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between gap-3">
                    {/* Left: Mobile Hamburger & Brand Title */}
                    <div className="flex items-center gap-3 min-w-0">
                        {/* Mobile Drawer Toggle Button */}
                        <button
                            type="button"
                            onClick={() => setMobileOpen(!mobileOpen)}
                            className="md:hidden p-2 -ml-2 text-stone-300 hover:text-white rounded-lg focus:outline-none focus:ring-2 focus:ring-rose-400 min-h-[44px] min-w-[44px] flex items-center justify-center cursor-pointer"
                            aria-label="Toggle agent navigation menu"
                        >
                            <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                {mobileOpen ? (
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12" />
                                ) : (
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 6h16M4 12h16M4 18h16" />
                                )}
                            </svg>
                        </button>

                        <Link href="/agent/dashboard" className="font-serif text-base sm:text-lg tracking-wider font-semibold text-rose-300 truncate">
                            Aura Luxe Agent
                        </Link>
                        <span className="text-[10px] sm:text-xs px-2 py-0.5 rounded bg-amber-900/50 text-amber-200 border border-amber-700/50 uppercase font-mono shrink-0">
                            {isHighLevel ? 'High-Level' : 'Agent'}
                        </span>
                    </div>

                    {/* Desktop Right Navigation Utilities */}
                    <div className="hidden md:flex items-center space-x-6 text-xs shrink-0">
                        <Link href="/agent/announcements" className="relative text-stone-300 hover:text-white flex items-center gap-1 font-medium transition-colors">
                            <span>📢 Announcements</span>
                            {unreadCount > 0 && (
                                <span className="px-1.5 py-0.2 rounded-full bg-rose-600 text-[10px] font-bold text-white leading-tight">
                                    {unreadCount}
                                </span>
                            )}
                        </Link>
                        <span className="text-stone-300 font-medium truncate max-w-[160px]">
                            {auth.user?.name}
                        </span>
                        <Link href="/" target="_blank" className="text-stone-400 hover:text-white underline transition-colors">
                            Storefront ↗
                        </Link>
                        <Link href="/logout" method="post" as="button" className="text-rose-400 hover:text-rose-300 font-medium transition-colors cursor-pointer">
                            Logout
                        </Link>
                    </div>

                    {/* Mobile Quick Utility Icons */}
                    <div className="flex md:hidden items-center gap-2 shrink-0">
                        <Link
                            href="/agent/announcements"
                            className="relative p-2 text-stone-300 hover:text-white min-h-[44px] min-w-[44px] flex items-center justify-center"
                            aria-label="Announcements"
                        >
                            <span className="text-base">📢</span>
                            {unreadCount > 0 && (
                                <span className="absolute top-1 right-1 px-1.5 py-0.2 rounded-full bg-rose-600 text-[9px] font-bold text-white leading-tight">
                                    {unreadCount}
                                </span>
                            )}
                        </Link>
                        <Link
                            href="/"
                            target="_blank"
                            className="p-2 text-stone-300 hover:text-white text-xs underline min-h-[44px] flex items-center"
                            title="Visit Public Storefront"
                        >
                            Store ↗
                        </Link>
                    </div>
                </div>
            </header>

            {/* Mobile Slide-over Drawer & Backdrop */}
            {mobileOpen && (
                <div className="fixed inset-0 z-50 md:hidden flex">
                    {/* Backdrop */}
                    <div
                        className="fixed inset-0 bg-stone-900/70 backdrop-blur-xs transition-opacity"
                        onClick={() => setMobileOpen(false)}
                        aria-hidden="true"
                    />

                    {/* Slide-over Content */}
                    <aside className="relative flex-1 flex flex-col max-w-xs w-full bg-white h-full shadow-2xl z-10">
                        {/* Drawer Header */}
                        <div className="p-4 bg-stone-900 text-white flex items-center justify-between border-b border-stone-800">
                            <div>
                                <div className="font-semibold text-sm text-rose-300">{auth.user?.name}</div>
                                <div className="text-[11px] text-stone-400 font-mono">
                                    {isHighLevel ? 'High-Level Agent (HLA)' : 'Low-Level Agent (LLA)'}
                                </div>
                            </div>
                            <button
                                type="button"
                                onClick={() => setMobileOpen(false)}
                                className="p-2 rounded-lg text-stone-400 hover:text-white min-h-[44px] min-w-[44px] flex items-center justify-center cursor-pointer"
                                aria-label="Close menu"
                            >
                                <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        {/* Mobile Wallet & KYC Card */}
                        <div className="p-3 bg-stone-50 border-b border-stone-200 text-xs flex justify-between items-center">
                            <div>
                                <span className="text-stone-500 block text-[10px] uppercase font-semibold">Wallet Balance</span>
                                <span className="font-bold text-emerald-700">LKR {Number(auth.user?.agent_profile?.wallet_balance ?? 0).toFixed(2)}</span>
                            </div>
                            <div className="text-right">
                                <span className="text-stone-500 block text-[10px] uppercase font-semibold">KYC Status</span>
                                <span className="font-bold text-amber-700 uppercase text-[11px]">{auth.user?.agent_profile?.kyc_status ?? 'Pending'}</span>
                            </div>
                        </div>

                        {/* Drawer Nav Items List */}
                        <nav className="flex-1 overflow-y-auto p-3 space-y-1">
                            {navItems.map((item) => {
                                const active = isItemActive(item.href);
                                return (
                                    <Link
                                        key={item.href}
                                        href={item.href}
                                        onClick={() => setMobileOpen(false)}
                                        className={`flex items-center justify-between px-3.5 py-3 rounded-xl text-xs font-medium transition-colors min-h-[44px] ${
                                            active
                                                ? 'bg-rose-50 text-rose-900 font-semibold border border-rose-200/80 shadow-2xs'
                                                : 'text-stone-700 hover:bg-stone-50 hover:text-stone-900'
                                        }`}
                                    >
                                        <div className="flex items-center gap-3">
                                            <span className="text-base">{item.icon}</span>
                                            <span>{item.label}</span>
                                        </div>
                                        {Boolean(item.badge && item.badge > 0) && (
                                            <span className="px-2 py-0.5 rounded-full bg-rose-600 text-[10px] font-bold text-white">
                                                {item.badge}
                                            </span>
                                        )}
                                    </Link>
                                );
                            })}
                        </nav>

                        {/* Drawer Footer Actions */}
                        <div className="p-4 border-t border-stone-200 bg-stone-50 space-y-2">
                            <Link
                                href="/"
                                target="_blank"
                                className="w-full flex items-center justify-center gap-2 px-3 py-2.5 rounded-xl border border-stone-300 text-stone-700 text-xs font-semibold hover:bg-white transition-colors min-h-[44px]"
                            >
                                <span>Visit Public Storefront</span>
                                <span>↗</span>
                            </Link>
                            <Link
                                href="/logout"
                                method="post"
                                as="button"
                                className="w-full flex items-center justify-center gap-2 px-3 py-2.5 rounded-xl bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 text-xs font-semibold transition-colors min-h-[44px] cursor-pointer"
                            >
                                <span>Sign Out</span>
                            </Link>
                        </div>
                    </aside>
                </div>
            )}

            {/* Referral Link & Wallet Preview Ribbon */}
            <div className="bg-rose-950/90 text-rose-100 border-b border-rose-900/60 py-2.5 px-4 text-xs">
                <div className="max-w-7xl mx-auto flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
                    <div className="flex flex-col sm:flex-row sm:items-center gap-2 min-w-0">
                        <span className="text-rose-300 font-semibold uppercase tracking-wider shrink-0 text-[11px]">Your Referral Link:</span>
                        <div className="flex items-center gap-1.5 min-w-0">
                            <code className="bg-rose-900/80 px-2 py-1 rounded text-amber-200 font-mono text-[11px] truncate block max-w-full select-all">
                                {referralLink}
                            </code>
                            <button
                                type="button"
                                onClick={copyReferralLink}
                                className="bg-rose-800 hover:bg-rose-700 text-white px-3 py-1 rounded text-[11px] font-medium transition-colors cursor-pointer shrink-0 min-h-[32px] sm:min-h-auto"
                            >
                                {copied ? '✓ Copied' : 'Copy'}
                            </button>
                        </div>
                    </div>

                    <div className="hidden sm:flex items-center gap-4 text-[11px] shrink-0">
                        <span>
                            Wallet Balance: <strong className="text-emerald-300">LKR {Number(auth.user?.agent_profile?.wallet_balance ?? 0).toFixed(2)}</strong>
                        </span>
                        <span>
                            KYC: <strong className="text-amber-300 uppercase">{auth.user?.agent_profile?.kyc_status ?? 'Pending'}</strong>
                        </span>
                    </div>
                </div>
            </div>

            {/* Desktop Navigation tabs (Hidden on mobile where drawer is active) */}
            <nav className="hidden md:block bg-white border-b border-stone-200 shadow-xs">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex space-x-6 text-xs font-medium tracking-wide overflow-x-auto">
                    {navItems.map((item) => {
                        const active = isItemActive(item.href);
                        return (
                            <Link
                                key={item.href}
                                href={item.href}
                                className={`py-3 whitespace-nowrap border-b-2 transition-colors flex items-center gap-1.5 ${
                                    active
                                        ? 'border-stone-900 text-stone-900 font-semibold'
                                        : 'border-transparent text-stone-700 hover:text-stone-900 hover:border-stone-900'
                                }`}
                            >
                                <span>{item.icon}</span>
                                <span>{item.label}</span>
                                {Boolean(item.badge && item.badge > 0) && (
                                    <span className="px-1.5 py-0.2 rounded-full bg-rose-600 text-[10px] font-bold text-white">
                                        {item.badge}
                                    </span>
                                )}
                            </Link>
                        );
                    })}
                </div>
            </nav>

            {/* Flash Messages */}
            {flash.success && (
                <div className="bg-emerald-50 border-b border-emerald-200 text-emerald-800 text-xs py-2.5 px-4 text-center font-medium">
                    {flash.success}
                </div>
            )}
            {flash.error && (
                <div className="bg-rose-50 border-b border-rose-200 text-rose-800 text-xs py-2.5 px-4 text-center font-medium">
                    {flash.error}
                </div>
            )}

            {/* Main Content */}
            <main className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8 flex-1 w-full min-w-0">
                {children}
            </main>
        </div>
    );
}

