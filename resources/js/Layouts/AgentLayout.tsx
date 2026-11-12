import { PageProps } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import React, { PropsWithChildren, useState } from 'react';

export default function AgentLayout({ children }: PropsWithChildren) {
    const { auth, flash } = usePage<PageProps>().props;
    const [copied, setCopied] = useState(false);

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

    return (
        <div className="min-h-screen bg-stone-100 flex flex-col font-sans">
            {/* Top Bar */}
            <header className="bg-stone-900 text-white border-b border-stone-800">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
                    <div className="flex items-center space-x-4">
                        <Link href="/agent/dashboard" className="font-serif text-lg tracking-wider font-semibold text-rose-300">
                            Aura Luxe Agent Portal
                        </Link>
                        <span className="text-xs px-2 py-0.5 rounded bg-amber-900/50 text-amber-200 border border-amber-700/50 uppercase font-mono">
                            {auth.user?.role === 'high_level_agent' ? 'High-Level' : 'Agent'}
                        </span>
                    </div>

                    <div className="flex items-center space-x-6 text-xs">
                        <span className="text-stone-300 font-medium">
                            {auth.user?.name}
                        </span>
                        <Link href="/" target="_blank" className="text-stone-400 hover:text-white underline">
                            Storefront ↗
                        </Link>
                        <Link href="/logout" method="post" as="button" className="text-rose-400 hover:text-rose-300">
                            Logout
                        </Link>
                    </div>
                </div>
            </header>

            {/* Referral Link & Wallet Preview Ribbon */}
            <div className="bg-rose-950/90 text-rose-100 border-b border-rose-900/60 py-2.5 px-4 text-xs">
                <div className="max-w-7xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-3">
                    <div className="flex items-center gap-2">
                        <span className="text-rose-300 font-semibold uppercase tracking-wider">Your Referral Link:</span>
                        <code className="bg-rose-900/80 px-2 py-0.5 rounded text-amber-200 font-mono text-[11px] select-all">
                            {referralLink}
                        </code>
                        <button
                            onClick={copyReferralLink}
                            className="bg-rose-800 hover:bg-rose-700 text-white px-2.5 py-0.5 rounded text-[11px] font-medium transition-colors cursor-pointer"
                        >
                            {copied ? '✓ Copied' : 'Copy'}
                        </button>
                    </div>

                    <div className="flex items-center gap-4 text-[11px]">
                        <span>
                            Wallet Balance: <strong className="text-emerald-300">LKR {Number(auth.user?.agent_profile?.wallet_balance ?? 0).toFixed(2)}</strong>
                        </span>
                        <span>
                            KYC: <strong className="text-amber-300 uppercase">{auth.user?.agent_profile?.kyc_status ?? 'Pending'}</strong>
                        </span>
                    </div>
                </div>
            </div>

            {/* Navigation tabs */}
            <nav className="bg-white border-b border-stone-200 shadow-xs">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex space-x-6 text-xs font-medium tracking-wide overflow-x-auto">
                    <Link href="/agent/dashboard" className="py-3 text-stone-700 hover:text-stone-900 border-b-2 border-transparent hover:border-stone-900 whitespace-nowrap">
                        Dashboard
                    </Link>
                    <Link href="/agent/orders/create" className="py-3 text-stone-700 hover:text-stone-900 border-b-2 border-transparent hover:border-stone-900 whitespace-nowrap">
                        + Place Customer Order
                    </Link>
                    <Link href="/agent/orders" className="py-3 text-stone-700 hover:text-stone-900 border-b-2 border-transparent hover:border-stone-900 whitespace-nowrap">
                        My Placed Orders
                    </Link>
                    <Link href="/agent/commissions" className="py-3 text-stone-700 hover:text-stone-900 border-b-2 border-transparent hover:border-stone-900 whitespace-nowrap">
                        My Commissions
                    </Link>
                    <Link href="/agent/wallet" className="py-3 text-stone-700 hover:text-stone-900 border-b-2 border-transparent hover:border-stone-900 whitespace-nowrap">
                        Wallet & Payouts
                    </Link>
                    <Link href="/agent/wallet/statements" className="py-3 text-stone-700 hover:text-stone-900 border-b-2 border-transparent hover:border-stone-900 whitespace-nowrap">
                        Monthly Statements
                    </Link>
                    <Link href="/agent/team" className="py-3 text-stone-700 hover:text-stone-900 border-b-2 border-transparent hover:border-stone-900 whitespace-nowrap">
                        My Downline Network
                    </Link>
                    <Link href="/agent/profile" className="py-3 text-stone-700 hover:text-stone-900 border-b-2 border-transparent hover:border-stone-900 whitespace-nowrap">
                        Profile & KYC
                    </Link>
                </div>
            </nav>

            {/* Flash Messages */}
            {flash.success && (
                <div className="bg-emerald-50 border-b border-emerald-200 text-emerald-800 text-sm py-2 px-4 text-center">
                    {flash.success}
                </div>
            )}
            {flash.error && (
                <div className="bg-rose-50 border-b border-rose-200 text-rose-800 text-sm py-2 px-4 text-center">
                    {flash.error}
                </div>
            )}

            {/* Main Content */}
            <main className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 flex-1 w-full">
                {children}
            </main>
        </div>
    );
}

