import React, { useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import * as Icons from '@/Components/Icons';

interface PaginatedData<T> {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
}

interface AchievementAwardItem {
    id: number;
    period_key: string;
    status: 'active' | 'corrected' | 'revoked';
    is_discretionary: boolean;
    awarded_at: string;
    revoked_at: string | null;
    revocation_reason: string | null;
    achievement: {
        id: number;
        code: string;
        name: string;
        badge_tier: string;
        badge_icon: string;
        category: string;
    };
    version: {
        version: number;
        threshold_value: string;
    };
    agent: {
        id: number;
        name: string;
        email: string;
        role: string;
    };
    awarder?: {
        name: string;
        email: string;
    };
    revoker?: {
        name: string;
        email: string;
    };
}

interface Props {
    awards: PaginatedData<AchievementAwardItem>;
    filters: {
        status?: string;
        category?: string;
        search?: string;
    };
    can: {
        award: boolean;
        revoke: boolean;
    };
}

export default function AchievementAwardsIndex({ awards, filters, can }: Props) {
    const [search, setSearch] = useState(filters.search || '');
    const [selectedStatus, setSelectedStatus] = useState(filters.status || '');
    const [activeModal, setActiveModal] = useState<'revoke' | 'reinstate' | null>(null);
    const [targetAward, setTargetAward] = useState<AchievementAwardItem | null>(null);

    const handleFilterChange = (status: string) => {
        setSelectedStatus(status);
        router.get(
            '/admin/agents/achievements/awards',
            { search, status: status || undefined },
            { preserveState: true, replace: true }
        );
    };

    const handleSearchSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(
            '/admin/agents/achievements/awards',
            { search: search || undefined, status: selectedStatus || undefined },
            { preserveState: true, replace: true }
        );
    };

    // Revocation Form
    const revokeForm = useForm({
        revocation_reason: '',
    });

    const handleRevokeSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!targetAward) return;
        revokeForm.post(`/admin/agents/achievements/awards/${targetAward.id}/revoke`, {
            onSuccess: () => {
                setActiveModal(null);
                setTargetAward(null);
                revokeForm.reset();
            },
        });
    };

    // Reinstatement Form
    const reinstateForm = useForm({
        reinstatement_reason: '',
    });

    const handleReinstateSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!targetAward) return;
        reinstateForm.post(`/admin/agents/achievements/awards/${targetAward.id}/reinstate`, {
            onSuccess: () => {
                setActiveModal(null);
                setTargetAward(null);
                reinstateForm.reset();
            },
        });
    };

    const getStatusBadge = (status: string) => {
        switch (status) {
            case 'active':
                return <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800"><Icons.CheckCircle2 className="w-3 h-3 mr-1" /> Active</span>;
            case 'corrected':
                return <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800"><Icons.Clock className="w-3 h-3 mr-1" /> Corrected</span>;
            case 'revoked':
                return <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-rose-100 text-rose-800"><Icons.AlertCircle className="w-3 h-3 mr-1" /> Revoked</span>;
            default:
                return null;
        }
    };

    return (
        <AdminLayout>
            <Head title="Issued Achievement Awards & Revocation Console" />

            <div className="py-6 space-y-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                {/* Header */}
                <div className="flex flex-col md:flex-row md:items-center md:justify-between border-b pb-4 gap-4">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900 flex items-center">
                            <Icons.Award className="w-7 h-7 mr-2 text-indigo-600" />
                            Issued Achievement Awards & Honors
                        </h1>
                        <p className="mt-1 text-sm text-gray-500">
                            Authoritative historical record of earned, corrected, and revoked achievement awards.
                        </p>
                    </div>

                    <div className="flex items-center space-x-3">
                        <a
                            href="/admin/agents/achievements"
                            className="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50"
                        >
                            View Nominations Queue
                        </a>
                    </div>
                </div>

                {/* Filters & Search */}
                <div className="bg-white p-4 rounded-lg shadow-sm border border-gray-200 flex flex-col md:flex-row items-center justify-between gap-4">
                    <form onSubmit={handleSearchSubmit} className="flex-1 w-full flex items-center gap-2">
                        <div className="relative flex-1">
                            <Icons.Search className="w-4 h-4 text-gray-400 absolute left-3 top-3" />
                            <input
                                type="text"
                                placeholder="Search by recipient name or email..."
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                className="w-full pl-9 pr-4 py-2 border border-gray-300 rounded-md text-sm focus:ring-indigo-500 focus:border-indigo-500"
                            />
                        </div>
                        <button
                            type="submit"
                            className="px-4 py-2 bg-gray-100 text-gray-700 rounded-md text-sm font-medium hover:bg-gray-200"
                        >
                            Search
                        </button>
                    </form>

                    <div className="flex items-center gap-2 w-full md:w-auto">
                        <Icons.Filter className="w-4 h-4 text-gray-500" />
                        <select
                            value={selectedStatus}
                            onChange={(e) => handleFilterChange(e.target.value)}
                            className="border border-gray-300 rounded-md text-sm py-2 px-3 focus:ring-indigo-500 focus:border-indigo-500"
                        >
                            <option value="">All Statuses</option>
                            <option value="active">Active Honors</option>
                            <option value="corrected">Corrected (Refunds)</option>
                            <option value="revoked">Revoked (Executive)</option>
                        </select>
                    </div>
                </div>

                {/* Awards Table */}
                <div className="bg-white shadow overflow-hidden sm:rounded-md border border-gray-200">
                    {awards.data.length === 0 ? (
                        <div className="p-12 text-center text-gray-500">
                            <Icons.Award className="w-12 h-12 mx-auto text-gray-300 mb-3" />
                            <p className="text-base font-medium">No achievement awards found.</p>
                            <p className="text-sm text-gray-400 mt-1">Issued achievement awards will be logged here.</p>
                        </div>
                    ) : (
                        <ul className="divide-y divide-gray-200">
                            {awards.data.map((award) => (
                                <li key={award.id} className="p-4 sm:p-6 hover:bg-gray-50 transition">
                                    <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                                        <div className="flex items-start space-x-4">
                                            <div className="p-3 bg-indigo-50 rounded-lg text-indigo-600">
                                                <Icons.Award className="w-6 h-6" />
                                            </div>
                                            <div>
                                                <div className="flex items-center gap-2">
                                                    <h3 className="text-base font-semibold text-gray-900">
                                                        {award.achievement.name}
                                                    </h3>
                                                    {getStatusBadge(award.status)}
                                                    <span className="text-xs font-mono uppercase bg-gray-100 text-gray-600 px-2 py-0.5 rounded">
                                                        {award.achievement.badge_tier}
                                                    </span>
                                                    <span className="text-xs font-medium text-gray-500">
                                                        v{award.version.version}
                                                    </span>
                                                    {award.is_discretionary && (
                                                        <span className="text-xs font-semibold bg-purple-100 text-purple-800 px-2 py-0.5 rounded">
                                                            Discretionary
                                                        </span>
                                                    )}
                                                </div>
                                                <div className="mt-1 text-sm text-gray-600 flex flex-wrap items-center gap-x-4 gap-y-1">
                                                    <span><strong>Recipient:</strong> {award.agent.name} ({award.agent.email})</span>
                                                    <span><strong>Period:</strong> {award.period_key}</span>
                                                    <span><strong>Awarded:</strong> {new Date(award.awarded_at).toLocaleDateString()}</span>
                                                </div>
                                                {award.status === 'revoked' && award.revocation_reason && (
                                                    <div className="mt-2 text-xs text-rose-700 bg-rose-50 p-2 rounded border border-rose-100">
                                                        <strong>Revocation Rationale:</strong> {award.revocation_reason}
                                                    </div>
                                                )}
                                            </div>
                                        </div>

                                        {/* Actions for Owner */}
                                        {can.revoke && (
                                            <div className="flex items-center space-x-2 self-end sm:self-center">
                                                {(award.status === 'active' || award.status === 'corrected') && (
                                                    <button
                                                        onClick={() => {
                                                            setTargetAward(award);
                                                            setActiveModal('revoke');
                                                        }}
                                                        className="inline-flex items-center px-3 py-1.5 border border-rose-300 text-xs font-medium rounded text-rose-700 bg-rose-50 hover:bg-rose-100 shadow-sm"
                                                    >
                                                        <Icons.AlertCircle className="w-3.5 h-3.5 mr-1" />
                                                        Revoke Award
                                                    </button>
                                                )}
                                                {award.status === 'revoked' && (
                                                    <button
                                                        onClick={() => {
                                                            setTargetAward(award);
                                                            setActiveModal('reinstate');
                                                        }}
                                                        className="inline-flex items-center px-3 py-1.5 border border-emerald-300 text-xs font-medium rounded text-emerald-700 bg-emerald-50 hover:bg-emerald-100 shadow-sm"
                                                    >
                                                        <Icons.RotateCcw className="w-3.5 h-3.5 mr-1" />
                                                        Reinstate Award
                                                    </button>
                                                )}
                                            </div>
                                        )}
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>

                {/* Revoke Modal */}
                {activeModal === 'revoke' && targetAward && (
                    <div className="fixed inset-0 z-50 overflow-y-auto bg-black bg-opacity-50 flex items-center justify-center p-4">
                        <div className="bg-white rounded-lg max-w-md w-full p-6 shadow-xl">
                            <h3 className="text-lg font-bold text-gray-900 mb-2">Revoke Achievement Award</h3>
                            <p className="text-sm text-gray-500 mb-4">
                                You are about to permanently revoke <strong>{targetAward.achievement.name}</strong> from <strong>{targetAward.agent.name}</strong>.
                            </p>
                            <form onSubmit={handleRevokeSubmit} className="space-y-4">
                                <div>
                                    <label className="block text-xs font-medium text-gray-700 mb-1">
                                        Mandatory Revocation Reason (Min 20 chars)
                                    </label>
                                    <textarea
                                        rows={3}
                                        required
                                        value={revokeForm.data.revocation_reason}
                                        onChange={(e) => revokeForm.setData('revocation_reason', e.target.value)}
                                        placeholder="Detailed explanation of compliance or ethical violation..."
                                        className="w-full text-sm border border-gray-300 rounded-md p-2 focus:ring-rose-500 focus:border-rose-500"
                                    />
                                </div>
                                <div className="flex justify-end space-x-2">
                                    <button
                                        type="button"
                                        onClick={() => setActiveModal(null)}
                                        className="px-4 py-2 border rounded-md text-sm text-gray-700 hover:bg-gray-50"
                                    >
                                        Cancel
                                    </button>
                                    <button
                                        type="submit"
                                        disabled={revokeForm.processing || revokeForm.data.revocation_reason.length < 20}
                                        className="px-4 py-2 bg-rose-600 text-white rounded-md text-sm font-medium hover:bg-rose-700 disabled:opacity-50"
                                    >
                                        {revokeForm.processing ? 'Revoking...' : 'Confirm Revocation'}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                )}

                {/* Reinstate Modal */}
                {activeModal === 'reinstate' && targetAward && (
                    <div className="fixed inset-0 z-50 overflow-y-auto bg-black bg-opacity-50 flex items-center justify-center p-4">
                        <div className="bg-white rounded-lg max-w-md w-full p-6 shadow-xl">
                            <h3 className="text-lg font-bold text-gray-900 mb-2">Reinstate Achievement Award</h3>
                            <p className="text-sm text-gray-500 mb-4">
                                Reinstate <strong>{targetAward.achievement.name}</strong> for <strong>{targetAward.agent.name}</strong>.
                            </p>
                            <form onSubmit={handleReinstateSubmit} className="space-y-4">
                                <div>
                                    <label className="block text-xs font-medium text-gray-700 mb-1">
                                        Reinstatement Justification (Min 20 chars)
                                    </label>
                                    <textarea
                                        rows={3}
                                        required
                                        value={reinstateForm.data.reinstatement_reason}
                                        onChange={(e) => reinstateForm.setData('reinstatement_reason', e.target.value)}
                                        placeholder="Reason for executive reinstatement upon review..."
                                        className="w-full text-sm border border-gray-300 rounded-md p-2 focus:ring-emerald-500 focus:border-emerald-500"
                                    />
                                </div>
                                <div className="flex justify-end space-x-2">
                                    <button
                                        type="button"
                                        onClick={() => setActiveModal(null)}
                                        className="px-4 py-2 border rounded-md text-sm text-gray-700 hover:bg-gray-50"
                                    >
                                        Cancel
                                    </button>
                                    <button
                                        type="submit"
                                        disabled={reinstateForm.processing || reinstateForm.data.reinstatement_reason.length < 20}
                                        className="px-4 py-2 bg-emerald-600 text-white rounded-md text-sm font-medium hover:bg-emerald-700 disabled:opacity-50"
                                    >
                                        {reinstateForm.processing ? 'Reinstating...' : 'Confirm Reinstatement'}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                )}
            </div>
        </AdminLayout>
    );
}
