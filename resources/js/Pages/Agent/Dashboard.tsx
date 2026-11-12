import AgentLayout from '@/Layouts/AgentLayout';
import { Order, User } from '@/types';
import { Head, Link } from '@inertiajs/react';
import React, { useState } from 'react';

interface Props {
    agent: User;
    stats: {
        own_orders_count: number;
        own_orders_sales: number;
        referral_orders_count: number;
        referral_orders_sales: number;
        direct_downline_count: number;
        total_downline_count: number;
        team_orders_count: number;
        team_orders_sales: number;
        wallet_balance: number;
        kyc_status: string;
    };
    recentOrders: Order[];
}

export default function AgentDashboard({ agent, stats, recentOrders }: Props) {
    const [copied, setCopied] = useState(false);
    const referralCode = agent.referral_code ?? '';
    const referralLink = typeof window !== 'undefined'
        ? `${window.location.origin}?ref=${referralCode}`
        : `?ref=${referralCode}`;

    const handleCopy = () => {
        if (typeof navigator !== 'undefined') {
            navigator.clipboard.writeText(referralLink);
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        }
    };

    return (
        <AgentLayout>
            <Head title="Agent Portal Dashboard | Aura Luxe" />

            <div className="space-y-8">
                {/* KYC & Activation Alert if Pending */}
                {stats.kyc_status !== 'verified' && (
                    <div className="p-4 bg-amber-50 rounded-xl border border-amber-200 text-xs text-amber-900 flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-xs">
                        <div>
                            <span className="font-semibold block">KYC Verification Status: {stats.kyc_status.toUpperCase()}</span>
                            <span className="text-[11px] text-amber-800">
                                {stats.kyc_status === 'pending'
                                    ? 'Your identity and bank account verification is currently under review by compliance.'
                                    : 'Your KYC submission was rejected. Please review your details and re-submit.'}
                            </span>
                        </div>
                        <Link
                            href="/agent/profile"
                            className="bg-amber-900 text-white px-3.5 py-1.5 rounded-lg text-xs font-semibold hover:bg-amber-800 whitespace-nowrap text-center"
                        >
                            Update Profile &rarr;
                        </Link>
                    </div>
                )}

                {/* Welcome & Referral Link Banner */}
                <div className="bg-white p-6 rounded-2xl border border-stone-200 shadow-xs flex flex-col lg:flex-row lg:items-center justify-between gap-6">
                    <div className="space-y-1">
                        <span className="text-[11px] text-rose-800 uppercase tracking-widest font-semibold block">
                            Aura Luxe Brand Ambassador
                        </span>
                        <h1 className="font-serif text-2xl sm:text-3xl font-semibold text-stone-900">
                            Welcome back, {agent.name}
                        </h1>
                        <p className="text-stone-500 text-xs">
                            Sponsor: {agent.parent ? `${agent.parent.name} (${agent.parent.referral_code})` : 'Direct / Founder Root'}
                        </p>
                    </div>

                    <div className="p-4 bg-stone-50 rounded-xl border border-stone-200/80 space-y-2 text-xs max-w-md w-full">
                        <div className="flex items-center justify-between">
                            <span className="text-[11px] font-semibold text-stone-600 uppercase">Your Customer Referral Link</span>
                            <span className="font-mono text-[11px] font-bold text-rose-900">{referralCode}</span>
                        </div>
                        <div className="flex items-center gap-2">
                            <input
                                type="text"
                                readOnly
                                value={referralLink}
                                className="flex-1 bg-white border border-stone-200 rounded-lg text-xs font-mono px-3 py-1.5 text-stone-700 select-all"
                            />
                            <button
                                type="button"
                                onClick={handleCopy}
                                className="bg-stone-900 hover:bg-stone-800 text-white font-semibold px-3 py-1.5 rounded-lg text-xs transition-colors whitespace-nowrap"
                            >
                                {copied ? '✓ Copied' : 'Copy Link'}
                            </button>
                        </div>
                    </div>
                </div>

                {/* Performance Metrics Grid */}
                <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                    {/* 1. Own Placed Orders */}
                    <div className="bg-white p-5 rounded-xl border border-stone-200 shadow-xs">
                        <span className="text-[11px] text-stone-500 uppercase tracking-wider font-semibold block">
                            Orders You Placed
                        </span>
                        <div className="mt-2 flex items-baseline justify-between">
                            <span className="text-2xl font-bold font-mono text-stone-900">{stats.own_orders_count}</span>
                            <span className="text-xs font-mono font-medium text-stone-600">
                                LKR {stats.own_orders_sales.toLocaleString('en-US', { minimumFractionDigits: 2 })}
                            </span>
                        </div>
                        <span className="text-[10px] text-stone-400 block mt-1">Placed directly on behalf of customers</span>
                    </div>

                    {/* 2. Customer Referral Orders */}
                    <div className="bg-white p-5 rounded-xl border border-rose-200 bg-rose-50/20 shadow-xs">
                        <span className="text-[11px] text-rose-800 uppercase tracking-wider font-semibold block">
                            Referral Customer Orders
                        </span>
                        <div className="mt-2 flex items-baseline justify-between">
                            <span className="text-2xl font-bold font-mono text-rose-900">{stats.referral_orders_count}</span>
                            <span className="text-xs font-mono font-medium text-rose-800">
                                LKR {stats.referral_orders_sales.toLocaleString('en-US', { minimumFractionDigits: 2 })}
                            </span>
                        </div>
                        <span className="text-[10px] text-rose-600 block mt-1">Customers who ordered via your link</span>
                    </div>

                    {/* 3. Downline Team Growth */}
                    <div className="bg-white p-5 rounded-xl border border-stone-200 shadow-xs">
                        <span className="text-[11px] text-stone-500 uppercase tracking-wider font-semibold block">
                            Downline Network
                        </span>
                        <div className="mt-2 flex items-baseline justify-between">
                            <span className="text-2xl font-bold font-mono text-stone-900">{stats.total_downline_count}</span>
                            <span className="text-xs font-medium text-stone-600">
                                ({stats.direct_downline_count} direct)
                            </span>
                        </div>
                        <Link href="/agent/team" className="text-[10px] text-rose-900 hover:underline block mt-1 font-medium">
                            View network tree &rarr;
                        </Link>
                    </div>

                    {/* 4. Wallet Balance Preview */}
                    <div className="bg-white p-5 rounded-xl border border-stone-900 bg-stone-900 text-white shadow-xs">
                        <span className="text-[11px] text-stone-400 uppercase tracking-wider font-semibold block">
                            Wallet Balance
                        </span>
                        <div className="mt-2">
                            <span className="text-xl font-bold font-mono text-emerald-300">
                                LKR {Number(stats.wallet_balance).toLocaleString('en-US', { minimumFractionDigits: 2 })}
                            </span>
                        </div>
                        <span className="text-[10px] text-stone-400 block mt-1">Available balance</span>
                    </div>
                </div>

                {/* Split Action: Place Order & Recent Placed Orders */}
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {/* Quick Order Action Box */}
                    <div className="bg-gradient-to-br from-rose-900 to-stone-900 text-white p-6 rounded-2xl shadow-sm flex flex-col justify-between">
                        <div className="space-y-3">
                            <span className="text-[10px] bg-rose-800 text-rose-200 uppercase font-semibold px-2 py-0.5 rounded tracking-wider">
                                Order On Behalf of Customer
                            </span>
                            <h2 className="font-serif text-xl font-medium">
                                Direct Customer Assisted Checkout
                            </h2>
                            <p className="text-xs text-rose-100/80 leading-relaxed">
                                Take customer orders directly over WhatsApp, phone, or in person. Enter delivery address, choose products, and place cash-on-delivery orders attributed to you.
                            </p>
                        </div>
                        <div className="mt-6">
                            <Link
                                href="/agent/orders/create"
                                className="w-full bg-white text-stone-900 font-semibold text-xs py-2.5 px-4 rounded-xl text-center block hover:bg-stone-100 transition-colors shadow-xs"
                            >
                                + Place New Customer Order
                            </Link>
                        </div>
                    </div>

                    {/* Recent Placed Orders List (2 cols) */}
                    <div className="lg:col-span-2 bg-white rounded-2xl border border-stone-200 shadow-xs overflow-hidden">
                        <div className="px-6 py-4 border-b border-stone-100 flex items-center justify-between">
                            <h2 className="font-serif text-base font-semibold text-stone-900">
                                Recent Orders Placed By You
                            </h2>
                            <Link href="/agent/orders" className="text-xs text-rose-900 hover:underline font-medium">
                                View All Orders &rarr;
                            </Link>
                        </div>

                        <div className="divide-y divide-stone-100 text-xs">
                            {recentOrders.length === 0 ? (
                                <p className="p-8 text-center text-stone-400 text-xs">
                                    You have not placed any orders yet. Use the "Place Customer Order" button to get started!
                                </p>
                            ) : (
                                recentOrders.map((ord) => (
                                    <div key={ord.id} className="p-4 flex items-center justify-between hover:bg-stone-50 transition-colors">
                                        <div>
                                            <span className="font-mono text-xs font-bold text-rose-900 block">
                                                #{ord.order_number}
                                            </span>
                                            <span className="text-stone-800 font-medium block mt-0.5">
                                                {ord.customer_name} &bull; {ord.city}, {ord.district}
                                            </span>
                                            <span className="text-[10px] text-stone-400 block mt-0.5">
                                                {new Date(ord.placed_at).toLocaleDateString()}
                                            </span>
                                        </div>
                                        <div className="text-right">
                                            <span className="font-mono text-xs font-semibold text-stone-900 block">
                                                LKR {Number(ord.total_amount).toLocaleString('en-US', { minimumFractionDigits: 2 })}
                                            </span>
                                            <span className="inline-block mt-1 text-[10px] font-semibold uppercase px-2 py-0.5 rounded-full bg-stone-100 text-stone-700">
                                                {ord.status.replace('_', ' ')}
                                            </span>
                                        </div>
                                    </div>
                                ))
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </AgentLayout>
    );
}

