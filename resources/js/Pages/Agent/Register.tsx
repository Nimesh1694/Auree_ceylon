import PublicLayout from '@/Layouts/PublicLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import React from 'react';

interface Props {
    initialReferralCode: string;
    sponsorName?: string | null;
}

export default function AgentRegister({ initialReferralCode, sponsorName }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        email: '',
        phone: '',
        password: '',
        password_confirmation: '',
        referral_code: initialReferralCode,
        nic: '',
        address: '',
        business_name: '',
        bank_name: '',
        bank_branch: '',
        bank_account_number: '',
        bank_account_name: '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/agent/register');
    };

    return (
        <PublicLayout>
            <Head title="Join Our Multi-Level Agent Network | Aura Luxe" />

            <div className="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-10 sm:py-16">
                <div className="text-center space-y-3 mb-8">
                    <span className="text-[11px] font-semibold tracking-[0.2em] uppercase text-rose-800 bg-rose-50 px-3 py-1 rounded-full border border-rose-200">
                        Agent Network Application
                    </span>
                    <h1 className="font-serif text-3xl sm:text-4xl text-stone-900 font-medium">
                        Become an Aura Luxe Brand Agent
                    </h1>
                    <p className="text-stone-600 text-xs sm:text-sm max-w-lg mx-auto leading-relaxed">
                        Join our botanical beauty distribution network. Earn commissions on verified product sales, build your downline team, and manage orders.
                    </p>
                </div>

                <div className="bg-white rounded-2xl border border-stone-200 shadow-sm p-6 sm:p-10 text-xs space-y-6">
                    {/* Notice */}
                    <div className="p-4 bg-amber-50 rounded-xl border border-amber-200 text-amber-900 space-y-1">
                        <strong className="block font-semibold">Important Registration Notice:</strong>
                        <p className="text-[11px] leading-relaxed">
                            All agent applications require an active sponsor referral and executive management approval. Once approved, your account will be activated, and you will receive your unique referral code.
                        </p>
                    </div>

                    <form onSubmit={handleSubmit} className="space-y-6">
                        {/* 1. Sponsor / Referral Code */}
                        <div className="space-y-3 pb-4 border-b border-stone-100">
                            <h2 className="font-serif text-sm font-semibold text-stone-900 uppercase tracking-wider text-[11px]">
                                1. Sponsor Invitation
                            </h2>
                            <div>
                                <label className="block font-medium text-stone-700">Sponsor Referral Code *</label>
                                <input
                                    type="text"
                                    value={data.referral_code}
                                    onChange={(e) => setData('referral_code', e.target.value.toUpperCase())}
                                    placeholder="e.g. AGT-12345"
                                    className="mt-1 w-full rounded-lg border-stone-300 text-xs font-mono uppercase"
                                    required
                                />
                                {sponsorName && (
                                    <p className="text-[11px] text-emerald-700 font-medium mt-1">
                                        ✓ Invited by: {sponsorName}
                                    </p>
                                )}
                                {errors.referral_code && (
                                    <p className="text-rose-600 text-[11px] mt-1">{errors.referral_code}</p>
                                )}
                            </div>
                        </div>

                        {/* 2. Personal & Contact Information */}
                        <div className="space-y-4 pb-4 border-b border-stone-100">
                            <h2 className="font-serif text-sm font-semibold text-stone-900 uppercase tracking-wider text-[11px]">
                                2. Personal Information
                            </h2>

                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label className="block font-medium text-stone-700">Full Name *</label>
                                    <input
                                        type="text"
                                        value={data.name}
                                        onChange={(e) => setData('name', e.target.value)}
                                        placeholder="Kasun Jayawardena"
                                        className="mt-1 w-full rounded-lg border-stone-300 text-xs"
                                        required
                                    />
                                    {errors.name && <p className="text-rose-600 text-[11px] mt-1">{errors.name}</p>}
                                </div>

                                <div>
                                    <label className="block font-medium text-stone-700">National Identity Card (NIC) *</label>
                                    <input
                                        type="text"
                                        value={data.nic}
                                        onChange={(e) => setData('nic', e.target.value)}
                                        placeholder="199512345678 or 951234567V"
                                        className="mt-1 w-full rounded-lg border-stone-300 text-xs font-mono"
                                        required
                                    />
                                    {errors.nic && <p className="text-rose-600 text-[11px] mt-1">{errors.nic}</p>}
                                </div>
                            </div>

                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label className="block font-medium text-stone-700">Email Address *</label>
                                    <input
                                        type="email"
                                        value={data.email}
                                        onChange={(e) => setData('email', e.target.value)}
                                        placeholder="kasun@example.com"
                                        className="mt-1 w-full rounded-lg border-stone-300 text-xs"
                                        required
                                    />
                                    {errors.email && <p className="text-rose-600 text-[11px] mt-1">{errors.email}</p>}
                                </div>

                                <div>
                                    <label className="block font-medium text-stone-700">Mobile Phone Number *</label>
                                    <input
                                        type="text"
                                        value={data.phone}
                                        onChange={(e) => setData('phone', e.target.value)}
                                        placeholder="0771234567"
                                        className="mt-1 w-full rounded-lg border-stone-300 text-xs font-mono"
                                        required
                                    />
                                    {errors.phone && <p className="text-rose-600 text-[11px] mt-1">{errors.phone}</p>}
                                </div>
                            </div>

                            <div>
                                <label className="block font-medium text-stone-700">Residential / Business Address *</label>
                                <textarea
                                    rows={2}
                                    value={data.address}
                                    onChange={(e) => setData('address', e.target.value)}
                                    placeholder="No 24, Temple Road, Colombo 03"
                                    className="mt-1 w-full rounded-lg border-stone-300 text-xs"
                                    required
                                />
                                {errors.address && <p className="text-rose-600 text-[11px] mt-1">{errors.address}</p>}
                            </div>

                            <div>
                                <label className="block font-medium text-stone-700">Trading / Business Name (Optional)</label>
                                <input
                                    type="text"
                                    value={data.business_name}
                                    onChange={(e) => setData('business_name', e.target.value)}
                                    placeholder="Kasun Beauty Studio"
                                    className="mt-1 w-full rounded-lg border-stone-300 text-xs"
                                />
                            </div>
                        </div>

                        {/* 3. Bank Account & KYC */}
                        <div className="space-y-4 pb-4 border-b border-stone-100">
                            <h2 className="font-serif text-sm font-semibold text-stone-900 uppercase tracking-wider text-[11px]">
                                3. Bank Account for Commission Payouts
                            </h2>

                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label className="block font-medium text-stone-700">Bank Name *</label>
                                    <input
                                        type="text"
                                        value={data.bank_name}
                                        onChange={(e) => setData('bank_name', e.target.value)}
                                        placeholder="Commercial Bank of Ceylon"
                                        className="mt-1 w-full rounded-lg border-stone-300 text-xs"
                                        required
                                    />
                                    {errors.bank_name && <p className="text-rose-600 text-[11px] mt-1">{errors.bank_name}</p>}
                                </div>

                                <div>
                                    <label className="block font-medium text-stone-700">Branch Name *</label>
                                    <input
                                        type="text"
                                        value={data.bank_branch}
                                        onChange={(e) => setData('bank_branch', e.target.value)}
                                        placeholder="Kollupitiya Branch"
                                        className="mt-1 w-full rounded-lg border-stone-300 text-xs"
                                        required
                                    />
                                    {errors.bank_branch && <p className="text-rose-600 text-[11px] mt-1">{errors.bank_branch}</p>}
                                </div>
                            </div>

                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label className="block font-medium text-stone-700">Account Holder Name *</label>
                                    <input
                                        type="text"
                                        value={data.bank_account_name}
                                        onChange={(e) => setData('bank_account_name', e.target.value)}
                                        placeholder="K. M. Jayawardena"
                                        className="mt-1 w-full rounded-lg border-stone-300 text-xs"
                                        required
                                    />
                                    {errors.bank_account_name && <p className="text-rose-600 text-[11px] mt-1">{errors.bank_account_name}</p>}
                                </div>

                                <div>
                                    <label className="block font-medium text-stone-700">Account Number *</label>
                                    <input
                                        type="text"
                                        value={data.bank_account_number}
                                        onChange={(e) => setData('bank_account_number', e.target.value)}
                                        placeholder="8001234567"
                                        className="mt-1 w-full rounded-lg border-stone-300 text-xs font-mono"
                                        required
                                    />
                                    {errors.bank_account_number && <p className="text-rose-600 text-[11px] mt-1">{errors.bank_account_number}</p>}
                                </div>
                            </div>
                        </div>

                        {/* 4. Portal Account Security */}
                        <div className="space-y-4">
                            <h2 className="font-serif text-sm font-semibold text-stone-900 uppercase tracking-wider text-[11px]">
                                4. Portal Account Credentials
                            </h2>

                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label className="block font-medium text-stone-700">Create Password *</label>
                                    <input
                                        type="password"
                                        value={data.password}
                                        onChange={(e) => setData('password', e.target.value)}
                                        className="mt-1 w-full rounded-lg border-stone-300 text-xs"
                                        placeholder="Minimum 8 characters"
                                        required
                                    />
                                    {errors.password && <p className="text-rose-600 text-[11px] mt-1">{errors.password}</p>}
                                </div>

                                <div>
                                    <label className="block font-medium text-stone-700">Confirm Password *</label>
                                    <input
                                        type="password"
                                        value={data.password_confirmation}
                                        onChange={(e) => setData('password_confirmation', e.target.value)}
                                        className="mt-1 w-full rounded-lg border-stone-300 text-xs"
                                        placeholder="Re-enter password"
                                        required
                                    />
                                </div>
                            </div>
                        </div>

                        <div className="pt-4 border-t border-stone-100 flex items-center justify-between">
                            <Link href="/login" className="text-stone-500 hover:text-stone-900 underline text-xs">
                                Already registered? Sign In
                            </Link>

                            <button
                                type="submit"
                                disabled={processing}
                                className="bg-rose-900 text-white font-semibold px-6 py-2.5 rounded-lg hover:bg-rose-800 disabled:opacity-50 text-xs transition-colors shadow-xs"
                            >
                                {processing ? 'Submitting Application...' : 'Submit Agent Application'}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </PublicLayout>
    );
}

