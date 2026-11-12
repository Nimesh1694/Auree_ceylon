import React from 'react';
import { Head, useForm, usePage } from '@inertiajs/react';
import AgentLayout from '@/Layouts/AgentLayout';
import { PageProps, User } from '@/types';
import {
    User as UserIcon,
    Building2,
    MapPin,
    CreditCard,
    ShieldCheck,
    AlertCircle,
    CheckCircle2,
    Lock,
    Save,
} from '@/Components/Icons';

interface ProfileProps extends PageProps {
    agent: User;
}

export default function Profile({ agent }: ProfileProps) {
    const { flash } = usePage<PageProps>().props;
    const profile = agent.agent_profile;
    const parent = agent.parent;

    const { data, setData, put, processing, errors, recentlySuccessful } = useForm({
        name: agent.name || '',
        phone: agent.phone || '',
        business_name: profile?.business_name || '',
        address: profile?.address || '',
        bank_name: profile?.bank_name || '',
        bank_branch: profile?.bank_branch || '',
        bank_account_number: profile?.bank_account_number || '',
        bank_account_name: profile?.bank_account_name || '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        put(route('agent.profile.update'));
    };

    const getKycBadge = (status?: string) => {
        switch (status) {
            case 'verified':
                return (
                    <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 border border-emerald-200">
                        <CheckCircle2 className="w-3.5 h-3.5" />
                        Verified (KYC Completed)
                    </span>
                );
            case 'rejected':
                return (
                    <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-800 border border-red-200">
                        <AlertCircle className="w-3.5 h-3.5" />
                        Rejected (Action Required)
                    </span>
                );
            default:
                return (
                    <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-amber-100 text-amber-800 border border-amber-200">
                        <AlertCircle className="w-3.5 h-3.5" />
                        Pending Verification
                    </span>
                );
        }
    };

    return (
        <AgentLayout>
            <Head title="Agent Profile & KYC" />

            <div className="max-w-5xl mx-auto space-y-6">
                {/* Header */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold text-slate-900 tracking-tight">Agent Profile & KYC</h1>
                        <p className="text-sm text-slate-500 mt-1">
                            Manage your personal contact details, verified bank account, and KYC verification records.
                        </p>
                    </div>
                    <div>
                        {getKycBadge(profile?.kyc_status)}
                    </div>
                </div>

                {/* Flash Success Message */}
                {flash?.success && (
                    <div className="p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl flex items-center gap-3">
                        <CheckCircle2 className="w-5 h-5 text-emerald-600 flex-shrink-0" />
                        <p className="text-sm font-medium">{flash.success}</p>
                    </div>
                )}

                {/* KYC Status Guidance Notice */}
                {profile?.kyc_status !== 'verified' && (
                    <div className="p-4 bg-amber-50 border border-amber-200 rounded-xl flex items-start gap-3">
                        <AlertCircle className="w-5 h-5 text-amber-600 flex-shrink-0 mt-0.5" />
                        <div className="text-sm text-amber-800">
                            <p className="font-semibold">KYC Verification Under Review</p>
                            <p className="mt-1">
                                {profile?.kyc_notes
                                    ? `Reviewer feedback: "${profile.kyc_notes}"`
                                    : 'Your identity and banking credentials are being reviewed by management. Ensure your bank details accurately match your legal name.'}
                            </p>
                        </div>
                    </div>
                )}

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {/* Read-Only Account Snapshot */}
                    <div className="lg:col-span-1 space-y-6">
                        <div className="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm">
                            <h2 className="text-sm font-semibold uppercase tracking-wider text-slate-500 mb-4 flex items-center gap-2">
                                <Lock className="w-4 h-4 text-slate-400" />
                                Account Details (System)
                            </h2>

                            <div className="space-y-4 text-sm">
                                <div>
                                    <span className="text-xs text-slate-400 uppercase">Referral Code</span>
                                    <p className="font-mono font-bold text-slate-900 mt-0.5">{agent.referral_code}</p>
                                </div>

                                <div>
                                    <span className="text-xs text-slate-400 uppercase">Network Tier / Role</span>
                                    <p className="font-medium text-slate-800 capitalize mt-0.5">
                                        {agent.role.replace(/_/g, ' ')}
                                    </p>
                                </div>

                                <div>
                                    <span className="text-xs text-slate-400 uppercase">Account Status</span>
                                    <span className="inline-block mt-0.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 capitalize">
                                        {agent.status}
                                    </span>
                                </div>

                                <div>
                                    <span className="text-xs text-slate-400 uppercase">Registered Email</span>
                                    <p className="font-medium text-slate-800 mt-0.5">{agent.email}</p>
                                </div>

                                <div>
                                    <span className="text-xs text-slate-400 uppercase">National Identity (NIC)</span>
                                    <p className="font-mono font-medium text-slate-800 mt-0.5">
                                        {profile?.nic || 'Not recorded'}
                                    </p>
                                </div>

                                <div className="pt-3 border-t border-slate-100">
                                    <span className="text-xs text-slate-400 uppercase">Direct Sponsor (Upline)</span>
                                    {parent ? (
                                        <div className="mt-1 p-2 bg-slate-50 rounded-lg">
                                            <p className="font-medium text-slate-900">{parent.name}</p>
                                            <p className="text-xs font-mono text-slate-500">Ref: {parent.referral_code}</p>
                                        </div>
                                    ) : (
                                        <p className="text-xs text-slate-500 mt-1 italic">Top-level direct brand agent</p>
                                    )}
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Editable Profile & Banking Details Form */}
                    <div className="lg:col-span-2">
                        <form onSubmit={handleSubmit} className="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm space-y-6">
                            {/* Personal Info */}
                            <div>
                                <h2 className="text-base font-semibold text-slate-900 flex items-center gap-2 pb-3 border-b border-slate-100">
                                    <UserIcon className="w-5 h-5 text-emerald-600" />
                                    Personal & Business Contact
                                </h2>

                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-4">
                                    <div>
                                        <label className="block text-xs font-medium text-slate-700 mb-1">
                                            Full Legal Name *
                                        </label>
                                        <input
                                            type="text"
                                            value={data.name}
                                            onChange={(e) => setData('name', e.target.value)}
                                            className="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                            required
                                        />
                                        {errors.name && <p className="text-xs text-red-600 mt-1">{errors.name}</p>}
                                    </div>

                                    <div>
                                        <label className="block text-xs font-medium text-slate-700 mb-1">
                                            Contact Phone *
                                        </label>
                                        <input
                                            type="text"
                                            value={data.phone}
                                            onChange={(e) => setData('phone', e.target.value)}
                                            className="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                            required
                                        />
                                        {errors.phone && <p className="text-xs text-red-600 mt-1">{errors.phone}</p>}
                                    </div>

                                    <div className="sm:col-span-2">
                                        <label className="block text-xs font-medium text-slate-700 mb-1">
                                            Business / Salon Name (Optional)
                                        </label>
                                        <div className="relative">
                                            <Building2 className="w-4 h-4 text-slate-400 absolute left-3 top-3" />
                                            <input
                                                type="text"
                                                value={data.business_name}
                                                onChange={(e) => setData('business_name', e.target.value)}
                                                placeholder="e.g. Aura Beauty Studio"
                                                className="w-full pl-9 pr-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                            />
                                        </div>
                                        {errors.business_name && <p className="text-xs text-red-600 mt-1">{errors.business_name}</p>}
                                    </div>

                                    <div className="sm:col-span-2">
                                        <label className="block text-xs font-medium text-slate-700 mb-1">
                                            Registered Postal Address *
                                        </label>
                                        <div className="relative">
                                            <MapPin className="w-4 h-4 text-slate-400 absolute left-3 top-3" />
                                            <textarea
                                                rows={2}
                                                value={data.address}
                                                onChange={(e) => setData('address', e.target.value)}
                                                className="w-full pl-9 pr-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                                required
                                            />
                                        </div>
                                        {errors.address && <p className="text-xs text-red-600 mt-1">{errors.address}</p>}
                                    </div>
                                </div>
                            </div>

                            {/* Banking Details */}
                            <div>
                                <h2 className="text-base font-semibold text-slate-900 flex items-center gap-2 pb-3 border-b border-slate-100">
                                    <CreditCard className="w-5 h-5 text-emerald-600" />
                                    Bank Account Information (For Future Commissions)
                                </h2>
                                <p className="text-xs text-slate-500 mt-1 mb-4">
                                    Please ensure your bank account name exactly matches your national identity document to avoid payout disbursement delays.
                                </p>

                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <label className="block text-xs font-medium text-slate-700 mb-1">
                                            Bank Name *
                                        </label>
                                        <input
                                            type="text"
                                            value={data.bank_name}
                                            onChange={(e) => setData('bank_name', e.target.value)}
                                            placeholder="e.g. Commercial Bank of Ceylon"
                                            className="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                            required
                                        />
                                        {errors.bank_name && <p className="text-xs text-red-600 mt-1">{errors.bank_name}</p>}
                                    </div>

                                    <div>
                                        <label className="block text-xs font-medium text-slate-700 mb-1">
                                            Branch *
                                        </label>
                                        <input
                                            type="text"
                                            value={data.bank_branch}
                                            onChange={(e) => setData('bank_branch', e.target.value)}
                                            placeholder="e.g. Colombo 07"
                                            className="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                            required
                                        />
                                        {errors.bank_branch && <p className="text-xs text-red-600 mt-1">{errors.bank_branch}</p>}
                                    </div>

                                    <div>
                                        <label className="block text-xs font-medium text-slate-700 mb-1">
                                            Account Number *
                                        </label>
                                        <input
                                            type="text"
                                            value={data.bank_account_number}
                                            onChange={(e) => setData('bank_account_number', e.target.value)}
                                            placeholder="e.g. 1000123456"
                                            className="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 font-mono"
                                            required
                                        />
                                        {errors.bank_account_number && <p className="text-xs text-red-600 mt-1">{errors.bank_account_number}</p>}
                                    </div>

                                    <div>
                                        <label className="block text-xs font-medium text-slate-700 mb-1">
                                            Account Holder Name *
                                        </label>
                                        <input
                                            type="text"
                                            value={data.bank_account_name}
                                            onChange={(e) => setData('bank_account_name', e.target.value)}
                                            placeholder="Name as in Bank Passbook"
                                            className="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                            required
                                        />
                                        {errors.bank_account_name && <p className="text-xs text-red-600 mt-1">{errors.bank_account_name}</p>}
                                    </div>
                                </div>
                            </div>

                            {/* Submit Button */}
                            <div className="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                                {recentlySuccessful && (
                                    <span className="text-xs font-medium text-emerald-600 flex items-center gap-1">
                                        <CheckCircle2 className="w-4 h-4" /> Changes saved
                                    </span>
                                )}
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="inline-flex items-center gap-2 px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-sm font-semibold shadow-sm transition-colors disabled:opacity-50"
                                >
                                    <Save className="w-4 h-4" />
                                    {processing ? 'Saving...' : 'Save Profile Changes'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </AgentLayout>
    );
}
