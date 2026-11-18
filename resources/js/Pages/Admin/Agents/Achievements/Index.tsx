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

interface AchievementRequestItem {
    id: number;
    status: 'pending' | 'approved' | 'rejected' | 'expired';
    justification: string;
    period_key: string | null;
    expires_at: string | null;
    created_at: string;
    decision_notes: string | null;
    achievement: {
        id: number;
        code: string;
        name: string;
        badge_tier: string;
        badge_icon: string;
        category: string;
    };
    agent: {
        id: number;
        name: string;
        email: string;
        role: string;
    };
    requester: {
        id: number;
        name: string;
        email: string;
        role: string;
    };
    decider?: {
        id: number;
        name: string;
        email: string;
    };
}

interface Props {
    requests: PaginatedData<AchievementRequestItem>;
    filters: {
        status?: string;
        search?: string;
    };
    can: {
        request: boolean;
        award: boolean;
        revoke: boolean;
    };
}

export default function AchievementIndex({ requests, filters, can }: Props) {
    const [search, setSearch] = useState(filters.search || '');
    const [selectedStatus, setSelectedStatus] = useState(filters.status || '');
    const [activeModal, setActiveModal] = useState<'nominate' | 'approve' | 'reject' | null>(null);
    const [targetRequest, setTargetRequest] = useState<AchievementRequestItem | null>(null);

    // Filter handling
    const handleFilterChange = (status: string) => {
        setSelectedStatus(status);
        router.get(
            '/admin/agents/achievements',
            { search, status: status || undefined },
            { preserveState: true, replace: true }
        );
    };

    const handleSearchSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(
            '/admin/agents/achievements',
            { search: search || undefined, status: selectedStatus || undefined },
            { preserveState: true, replace: true }
        );
    };

    // Approval / Rejection form
    const decisionForm = useForm({
        decision_notes: '',
    });

    const handleApproveSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!targetRequest) return;
        decisionForm.post(`/admin/agents/achievements/requests/${targetRequest.id}/approve`, {
            onSuccess: () => {
                setActiveModal(null);
                setTargetRequest(null);
                decisionForm.reset();
            },
        });
    };

    const handleRejectSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!targetRequest) return;
        decisionForm.post(`/admin/agents/achievements/requests/${targetRequest.id}/reject`, {
            onSuccess: () => {
                setActiveModal(null);
                setTargetRequest(null);
                decisionForm.reset();
            },
        });
    };

    const getStatusBadge = (status: string) => {
        switch (status) {
            case 'approved':
                return <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800"><Icons.CheckCircle2 className="w-3 h-3 mr-1" /> Approved</span>;
            case 'rejected':
                return <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-rose-100 text-rose-800"><Icons.AlertCircle className="w-3 h-3 mr-1" /> Rejected</span>;
            case 'expired':
                return <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800"><Icons.Clock className="w-3 h-3 mr-1" /> Expired</span>;
            default:
                return <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800"><Icons.Clock className="w-3 h-3 mr-1" /> Pending</span>;
        }
    };

    return (
        <AdminLayout>
            <Head title="Agent Achievements & Honors Governance" />

            <div className="py-6 space-y-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                {/* Header & Tabs */}
                <div className="flex flex-col md:flex-row md:items-center md:justify-between border-b pb-4 gap-4">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900 flex items-center">
                            <Icons.Award className="w-7 h-7 mr-2 text-indigo-600" />
                            Agent Achievements & Honors
                        </h1>
                        <p className="mt-1 text-sm text-gray-500">
                            Manage field nominations, discretionary awards, governance reviews, and revocation controls.
                        </p>
                    </div>

                    <div className="flex items-center space-x-3">
                        <a
                            href="/admin/agents/achievements/awards"
                            className="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50"
                        >
                            View Issued Awards
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
                                placeholder="Search by agent name or email..."
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
                            <option value="pending">Pending Review</option>
                            <option value="approved">Approved</option>
                            <option value="rejected">Rejected</option>
                            <option value="expired">Expired (30d)</option>
                        </select>
                    </div>
                </div>

                {/* Requests Table */}
                <div className="bg-white shadow overflow-hidden sm:rounded-md border border-gray-200">
                    {requests.data.length === 0 ? (
                        <div className="p-12 text-center text-gray-500">
                            <Icons.Award className="w-12 h-12 mx-auto text-gray-300 mb-3" />
                            <p className="text-base font-medium">No achievement nominations found.</p>
                            <p className="text-sm text-gray-400 mt-1">Pending requests and nominations will appear here.</p>
                        </div>
                    ) : (
                        <ul className="divide-y divide-gray-200">
                            {requests.data.map((req) => (
                                <li key={req.id} className="p-4 sm:p-6 hover:bg-gray-50 transition">
                                    <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                                        <div className="flex items-start space-x-4">
                                            <div className="p-3 bg-indigo-50 rounded-lg text-indigo-600">
                                                <Icons.Award className="w-6 h-6" />
                                            </div>
                                            <div>
                                                <div className="flex items-center gap-2">
                                                    <h3 className="text-base font-semibold text-gray-900">
                                                        {req.achievement.name}
                                                    </h3>
                                                    {getStatusBadge(req.status)}
                                                    <span className="text-xs font-mono uppercase bg-gray-100 text-gray-600 px-2 py-0.5 rounded">
                                                        {req.achievement.badge_tier}
                                                    </span>
                                                </div>
                                                <div className="mt-1 text-sm text-gray-600 flex flex-wrap items-center gap-x-4 gap-y-1">
                                                    <span><strong>Nominee:</strong> {req.agent.name} ({req.agent.email})</span>
                                                    <span><strong>Nominated By:</strong> {req.requester.name}</span>
                                                    <span><strong>Submitted:</strong> {new Date(req.created_at).toLocaleDateString()}</span>
                                                </div>
                                                <div className="mt-2 text-xs text-gray-500 bg-gray-50 p-2 rounded border border-gray-100">
                                                    <strong>Justification:</strong> "{req.justification}"
                                                </div>
                                                {req.decision_notes && (
                                                    <div className="mt-1 text-xs text-indigo-700 bg-indigo-50/50 p-2 rounded">
                                                        <strong>Owner Note:</strong> {req.decision_notes}
                                                    </div>
                                                )}
                                            </div>
                                        </div>

                                        {/* Actions for Owner */}
                                        {can.award && req.status === 'pending' && (
                                            <div className="flex items-center space-x-2 self-end sm:self-center">
                                                <button
                                                    onClick={() => {
                                                        setTargetRequest(req);
                                                        setActiveModal('approve');
                                                    }}
                                                    className="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded text-white bg-emerald-600 hover:bg-emerald-700 shadow-sm"
                                                >
                                                    <Icons.Check className="w-3.5 h-3.5 mr-1" />
                                                    Approve
                                                </button>
                                                <button
                                                    onClick={() => {
                                                        setTargetRequest(req);
                                                        setActiveModal('reject');
                                                    }}
                                                    className="inline-flex items-center px-3 py-1.5 border border-gray-300 text-xs font-medium rounded text-gray-700 bg-white hover:bg-gray-50 shadow-sm"
                                                >
                                                    <Icons.X className="w-3.5 h-3.5 mr-1" />
                                                    Reject
                                                </button>
                                            </div>
                                        )}
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>

                {/* Approve Modal */}
                {activeModal === 'approve' && targetRequest && (
                    <div className="fixed inset-0 z-50 overflow-y-auto bg-black bg-opacity-50 flex items-center justify-center p-4">
                        <div className="bg-white rounded-lg max-w-md w-full p-6 shadow-xl">
                            <h3 className="text-lg font-bold text-gray-900 mb-2">Approve Achievement Nomination</h3>
                            <p className="text-sm text-gray-500 mb-4">
                                Are you sure you want to approve <strong>{targetRequest.achievement.name}</strong> for <strong>{targetRequest.agent.name}</strong>?
                            </p>
                            <form onSubmit={handleApproveSubmit} className="space-y-4">
                                <div>
                                    <label className="block text-xs font-medium text-gray-700 mb-1">
                                        Executive Decision Notes (Optional)
                                    </label>
                                    <textarea
                                        rows={3}
                                        value={decisionForm.data.decision_notes}
                                        onChange={(e) => decisionForm.setData('decision_notes', e.target.value)}
                                        placeholder="Add any formal notes or commendations..."
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
                                        disabled={decisionForm.processing}
                                        className="px-4 py-2 bg-emerald-600 text-white rounded-md text-sm font-medium hover:bg-emerald-700"
                                    >
                                        {decisionForm.processing ? 'Approving...' : 'Confirm Approval'}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                )}

                {/* Reject Modal */}
                {activeModal === 'reject' && targetRequest && (
                    <div className="fixed inset-0 z-50 overflow-y-auto bg-black bg-opacity-50 flex items-center justify-center p-4">
                        <div className="bg-white rounded-lg max-w-md w-full p-6 shadow-xl">
                            <h3 className="text-lg font-bold text-gray-900 mb-2">Reject Achievement Nomination</h3>
                            <p className="text-sm text-gray-500 mb-4">
                                Please provide a reason for rejecting the nomination for <strong>{targetRequest.agent.name}</strong>.
                            </p>
                            <form onSubmit={handleRejectSubmit} className="space-y-4">
                                <div>
                                    <label className="block text-xs font-medium text-gray-700 mb-1">
                                        Rejection Reason (Mandatory)
                                    </label>
                                    <textarea
                                        rows={3}
                                        required
                                        value={decisionForm.data.decision_notes}
                                        onChange={(e) => decisionForm.setData('decision_notes', e.target.value)}
                                        placeholder="Reason for declining this nomination..."
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
                                        disabled={decisionForm.processing || !decisionForm.data.decision_notes}
                                        className="px-4 py-2 bg-rose-600 text-white rounded-md text-sm font-medium hover:bg-rose-700"
                                    >
                                        {decisionForm.processing ? 'Rejecting...' : 'Confirm Rejection'}
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
