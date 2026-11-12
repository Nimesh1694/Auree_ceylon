import React, { useState } from 'react';
import { Head, Link, useForm, router, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { PageProps, User, AuditLog, HierarchyChangeRequest } from '@/types';
import {
    ArrowLeft,
    User as UserIcon,
    Shield,
    CheckCircle2,
    AlertCircle,
    FileCheck,
    GitBranch,
    CreditCard,
    History,
    ChevronRight,
    ArrowUpCircle,
    Users,
} from '@/Components/Icons';

interface ShowProps extends PageProps {
    agent: User;
    upline: User[];
    downline: User[];
    changeRequests: HierarchyChangeRequest[];
    auditLogs: AuditLog[];
    availableParents: {
        id: number;
        name: string;
        referral_code: string;
        role: string;
    }[];
    canViewFullFinancials?: boolean;
    canViewFullKyc?: boolean;
}

export default function Show({
    agent,
    upline,
    downline,
    changeRequests,
    auditLogs,
    availableParents,
    canViewFullFinancials = true,
    canViewFullKyc = true,
}: ShowProps) {
    const { flash, auth } = usePage<PageProps>().props;
    const isOwner = Boolean(auth.is_owner || auth.user.role === 'owner');
    const permissions = auth.permissions ?? [];
    const canManageKyc = isOwner || ['it_manager', 'accountant', 'agent_manager'].includes(auth.user.role) || permissions.includes('agents.kyc.verify');
    const canManageStatus = isOwner || ['it_manager', 'agent_manager'].includes(auth.user.role) || permissions.includes('agents.status.update');
    const canRequestHierarchyMove = isOwner || ['it_manager', 'agent_manager'].includes(auth.user.role) || permissions.includes('agents.hierarchy.request');
    const profile = agent.agent_profile;

    // KYC form
    const {
        data: kycData,
        setData: setKycData,
        post: submitKyc,
        processing: kycProcessing,
    } = useForm({
        kyc_status: 'verified',
        kyc_notes: '',
    });

    // Status form (suspend/reactivate)
    const {
        data: statusData,
        setData: setStatusData,
        post: submitStatus,
        processing: statusProcessing,
    } = useForm({
        status: agent.status === 'active' ? 'suspended' : 'active',
        reason: '',
    });

    // Hierarchy move request form
    const {
        data: moveData,
        setData: setMoveData,
        post: submitMoveRequest,
        processing: moveProcessing,
        reset: resetMoveForm,
    } = useForm({
        requested_parent_id: '',
        reason: '',
    });

    const handleKycSubmit = (status: 'verified' | 'rejected') => {
        kycData.kyc_status = status;
        submitKyc(route('admin.agents.kyc.update', agent.id));
    };

    const handleStatusSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        submitStatus(route('admin.agents.status.update', agent.id));
    };

    const handleMoveSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        submitMoveRequest(route('admin.agents.hierarchy.request', agent.id), {
            onSuccess: () => resetMoveForm(),
        });
    };

    const handleReviewMove = (requestId: number, action: 'approve' | 'reject') => {
        const notes = prompt(`Please enter any notes for this ${action} decision:`, '');
        if (notes === null) return;

        router.post(route('admin.agents.hierarchy.review', requestId), {
            action,
            review_notes: notes,
        });
    };

    return (
        <AdminLayout>
            <Head title={`Agent: ${agent.name}`} />

            <div className="max-w-6xl mx-auto space-y-6">
                {/* Header */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <Link
                            href={route('admin.agents.index')}
                            className="text-xs font-semibold text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-white flex items-center gap-1 mb-2 transition-colors"
                        >
                            <ArrowLeft className="w-3.5 h-3.5" /> Back to Agent Directory
                        </Link>
                        <div className="flex items-center gap-3">
                            <h1 className="text-2xl font-bold text-slate-900 dark:text-white tracking-tight">{agent.name}</h1>
                            <span className="font-mono text-xs px-2.5 py-1 bg-slate-100 dark:bg-slate-800 text-slate-800 dark:text-slate-200 rounded-lg font-bold">
                                {agent.referral_code}
                            </span>
                            <span
                                className={`px-2.5 py-0.5 rounded-full text-xs font-semibold capitalize ${
                                    agent.status === 'active'
                                        ? 'bg-emerald-100 dark:bg-emerald-950/60 text-emerald-800 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800'
                                        : agent.status === 'pending'
                                        ? 'bg-amber-100 dark:bg-amber-950/60 text-amber-800 dark:text-amber-300 border border-amber-200 dark:border-amber-800'
                                        : 'bg-rose-100 dark:bg-rose-950/60 text-rose-800 dark:text-rose-300 border border-rose-200 dark:border-rose-800'
                                }`}
                            >
                                {agent.status}
                            </span>
                        </div>
                    </div>

                    {agent.status === 'pending' && (
                        <div className="flex items-center gap-2">
                            <button
                                type="button"
                                onClick={() => router.post(route('admin.agents.approve', agent.id))}
                                className="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-semibold transition-colors cursor-pointer"
                            >
                                Approve Application
                            </button>
                        </div>
                    )}
                </div>

                {/* Flash Messages */}
                {flash?.success && (
                    <div className="p-4 bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-300 rounded-2xl flex items-center gap-3">
                        <CheckCircle2 className="w-5 h-5 text-emerald-600 dark:text-emerald-400 shrink-0" />
                        <p className="text-sm font-medium">{flash.success}</p>
                    </div>
                )}
                {flash?.error && (
                    <div className="p-4 bg-rose-50 dark:bg-rose-950/50 border border-rose-200 dark:border-rose-800 text-rose-800 dark:text-rose-300 rounded-2xl flex items-center gap-3">
                        <AlertCircle className="w-5 h-5 text-rose-600 dark:text-rose-400 shrink-0" />
                        <p className="text-sm font-medium">{flash.error}</p>
                    </div>
                )}

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {/* Left: Profile & Banking Information */}
                    <div className="lg:col-span-2 space-y-6">
                        {/* Profile Details */}
                        <div className="bg-white dark:bg-[#0F172A] rounded-2xl border border-slate-200/80 dark:border-slate-800/80 p-6 shadow-xs space-y-4">
                            <h2 className="text-base font-semibold text-slate-900 dark:text-white flex items-center gap-2 pb-3 border-b border-slate-100 dark:border-slate-800/80">
                                <UserIcon className="w-5 h-5 text-emerald-600 dark:text-emerald-400" />
                                Agent Identity & Contact Records
                            </h2>

                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                                <div>
                                    <span className="text-xs text-slate-400 dark:text-slate-500 uppercase tracking-wider font-semibold">National Identity (NIC)</span>
                                    <p className="font-mono font-bold text-slate-900 dark:text-white mt-0.5 flex items-center gap-1.5">
                                        {profile?.nic || 'Not provided'}
                                        {profile?.nic && profile.nic.startsWith('*') && (
                                            <span className="text-[10px] font-sans font-normal text-amber-700 dark:text-amber-400 bg-amber-50 dark:bg-amber-950/50 border border-amber-200 dark:border-amber-800 px-1.5 py-0.5 rounded">
                                                Masked
                                            </span>
                                        )}
                                    </p>
                                </div>
                                <div>
                                    <span className="text-xs text-slate-400 dark:text-slate-500 uppercase tracking-wider font-semibold">Network Role Tier</span>
                                    <p className="font-medium text-slate-900 dark:text-white capitalize mt-0.5">
                                        {agent.role.replace(/_/g, ' ')}
                                    </p>
                                </div>
                                <div>
                                    <span className="text-xs text-slate-400 dark:text-slate-500 uppercase tracking-wider font-semibold">Contact Phone</span>
                                    <p className="font-mono font-medium text-slate-900 dark:text-white mt-0.5">{agent.phone}</p>
                                </div>
                                <div>
                                    <span className="text-xs text-slate-400 dark:text-slate-500 uppercase tracking-wider font-semibold">Email Address</span>
                                    <p className="font-medium text-slate-900 dark:text-white mt-0.5">{agent.email}</p>
                                </div>
                                {profile?.business_name && (
                                    <div className="sm:col-span-2">
                                        <span className="text-xs text-slate-400 dark:text-slate-500 uppercase tracking-wider font-semibold">Business / Salon Name</span>
                                        <p className="font-medium text-slate-900 dark:text-white mt-0.5">{profile.business_name}</p>
                                    </div>
                                )}
                                <div className="sm:col-span-2">
                                    <span className="text-xs text-slate-400 dark:text-slate-500 uppercase tracking-wider font-semibold">Registered Postal Address</span>
                                    <p className="font-medium text-slate-900 dark:text-slate-200 mt-0.5">{profile?.address || 'Not provided'}</p>
                                </div>
                            </div>
                        </div>

                        {/* Bank Details */}
                        <div className="bg-white dark:bg-[#0F172A] rounded-2xl border border-slate-200/80 dark:border-slate-800/80 p-6 shadow-xs space-y-4">
                            <h2 className="text-base font-semibold text-slate-900 dark:text-white flex items-center gap-2 pb-3 border-b border-slate-100 dark:border-slate-800/80">
                                <CreditCard className="w-5 h-5 text-emerald-600 dark:text-emerald-400" />
                                Bank Account for Commissions
                            </h2>

                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                                <div>
                                    <span className="text-xs text-slate-400 dark:text-slate-500 uppercase tracking-wider font-semibold">Bank Name</span>
                                    <p className="font-semibold text-slate-900 dark:text-white mt-0.5">{profile?.bank_name || '—'}</p>
                                </div>
                                <div>
                                    <span className="text-xs text-slate-400 dark:text-slate-500 uppercase tracking-wider font-semibold">Branch</span>
                                    <p className="font-medium text-slate-900 dark:text-white mt-0.5">{profile?.bank_branch || '—'}</p>
                                </div>
                                <div>
                                    <span className="text-xs text-slate-400 dark:text-slate-500 uppercase tracking-wider font-semibold">Account Number</span>
                                    <p className="font-mono font-bold text-slate-900 dark:text-white mt-0.5 flex items-center gap-1.5">
                                        {profile?.bank_account_number || '—'}
                                        {profile?.bank_account_number && profile.bank_account_number.startsWith('*') && (
                                            <span className="text-[10px] font-sans font-normal text-amber-700 dark:text-amber-400 bg-amber-50 dark:bg-amber-950/50 border border-amber-200 dark:border-amber-800 px-1.5 py-0.5 rounded">
                                                Masked
                                            </span>
                                        )}
                                    </p>
                                </div>
                                <div>
                                    <span className="text-xs text-slate-400 dark:text-slate-500 uppercase tracking-wider font-semibold">Account Holder Name</span>
                                    <p className="font-medium text-slate-900 dark:text-white mt-0.5">{profile?.bank_account_name || '—'}</p>
                                </div>
                            </div>
                        </div>

                        {/* Network Hierarchy Inspection */}
                        <div className="bg-white dark:bg-[#0F172A] rounded-2xl border border-slate-200/80 dark:border-slate-800/80 p-6 shadow-xs space-y-4">
                            <h2 className="text-base font-semibold text-slate-900 dark:text-white flex items-center gap-2 pb-3 border-b border-slate-100 dark:border-slate-800/80">
                                <GitBranch className="w-5 h-5 text-emerald-600 dark:text-emerald-400" />
                                Network Hierarchy Position
                            </h2>

                            {/* Upline */}
                            <div>
                                <h3 className="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-2 flex items-center gap-1.5">
                                    <ArrowUpCircle className="w-4 h-4 text-slate-400" />
                                    Upline Path to Brand Root
                                </h3>
                                {upline.length === 0 ? (
                                    <p className="text-xs text-slate-500 dark:text-slate-400 italic">No upline sponsor. This agent is direct to headquarters.</p>
                                ) : (
                                    <div className="flex flex-wrap items-center gap-2 text-xs">
                                        <span className="px-2.5 py-1 bg-emerald-600 dark:bg-emerald-500 text-white rounded-lg font-bold">This Agent</span>
                                        {upline.map((anc) => (
                                            <React.Fragment key={anc.id}>
                                                <ChevronRight className="w-3.5 h-3.5 text-slate-400" />
                                                <Link
                                                    href={route('admin.agents.show', anc.id)}
                                                    className="px-2.5 py-1 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 rounded-lg font-medium transition-colors"
                                                >
                                                    {anc.name} ({anc.referral_code})
                                                </Link>
                                            </React.Fragment>
                                        ))}
                                    </div>
                                )}
                            </div>

                            {/* Downline */}
                            <div className="pt-3 border-t border-slate-100 dark:border-slate-800/80">
                                <h3 className="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-2 flex items-center gap-1.5">
                                    <Users className="w-4 h-4 text-slate-400" />
                                    Direct Downline & Team ({downline.length} members)
                                </h3>
                                {downline.length === 0 ? (
                                    <p className="text-xs text-slate-500 dark:text-slate-400 italic">No downline team registered under this agent.</p>
                                ) : (
                                    <div className="space-y-1.5 max-h-48 overflow-y-auto pr-2 custom-scrollbar">
                                        {downline.map((desc) => (
                                             <div key={desc.id} className="flex items-center justify-between p-2.5 bg-slate-50 dark:bg-slate-800/60 rounded-xl text-xs">
                                                <div>
                                                    <Link href={route('admin.agents.show', desc.id)} className="font-semibold text-slate-900 dark:text-white hover:underline">
                                                        {desc.name}
                                                    </Link>
                                                    <span className="text-slate-400 dark:text-slate-500 font-mono ml-2">({desc.referral_code})</span>
                                                </div>
                                                <span className="capitalize px-2 py-0.5 rounded text-[10px] bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300 font-medium">
                                                    {desc.status}
                                                </span>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </div>
                        </div>

                        {/* Audit Log Timeline */}
                        <div className="bg-white dark:bg-[#0F172A] rounded-2xl border border-slate-200/80 dark:border-slate-800/80 p-6 shadow-xs space-y-4">
                            <h2 className="text-base font-semibold text-slate-900 dark:text-white flex items-center gap-2 pb-3 border-b border-slate-100 dark:border-slate-800/80">
                                <History className="w-5 h-5 text-emerald-600 dark:text-emerald-400" />
                                Agent Activity & Audit Trail
                            </h2>

                            {auditLogs.length === 0 ? (
                                <p className="text-xs text-slate-500 dark:text-slate-400 italic">No audit records logged yet.</p>
                            ) : (
                                <div className="space-y-2 text-xs divide-y divide-slate-100 dark:divide-slate-800/60">
                                    {auditLogs.map((log) => (
                                        <div key={log.id} className="pt-2 first:pt-0">
                                            <div className="flex items-center justify-between text-slate-500 dark:text-slate-400">
                                                <span className="font-mono font-bold text-slate-800 dark:text-slate-200">{log.action}</span>
                                                <span>{new Date(log.created_at).toLocaleString()}</span>
                                            </div>
                                            {log.new_values && (
                                                <p className="text-slate-600 dark:text-slate-300 mt-1 font-mono text-[11px] bg-slate-50 dark:bg-slate-800/60 p-2.5 rounded-xl">
                                                    {JSON.stringify(log.new_values)}
                                                </p>
                                            )}
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Right: Management Controls & Workflows */}
                    <div className="lg:col-span-1 space-y-6">
                        {/* KYC Review Card */}
                        {canManageKyc && (
                            <div className="bg-white dark:bg-[#0F172A] rounded-2xl border border-slate-200/80 dark:border-slate-800/80 p-5 shadow-xs space-y-4">
                                <h3 className="text-sm font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                                    <FileCheck className="w-4 h-4 text-emerald-600 dark:text-emerald-400" />
                                    KYC Compliance Verification
                                </h3>

                                <div className="p-3 bg-slate-50 dark:bg-slate-800/60 rounded-xl text-xs space-y-1">
                                    <span className="text-slate-500 dark:text-slate-400">Current KYC Status:</span>
                                    <p className="font-bold capitalize text-slate-800 dark:text-slate-200">
                                        {profile?.kyc_status || 'Pending Verification'}
                                    </p>
                                    {profile?.kyc_notes && (
                                        <p className="text-slate-600 dark:text-slate-400 italic mt-1">"{profile.kyc_notes}"</p>
                                    )}
                                </div>

                                <div className="space-y-3">
                                    <div>
                                        <label className="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">
                                            Review Feedback Notes
                                        </label>
                                        <textarea
                                            rows={2}
                                            value={kycData.kyc_notes}
                                            onChange={(e) => setKycData('kyc_notes', e.target.value)}
                                            placeholder="Add notes for the agent or records..."
                                            className="w-full px-3 py-2 border border-slate-300 dark:border-slate-700 rounded-xl text-xs bg-white dark:bg-[#1E293B] text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500"
                                        />
                                    </div>

                                    <div className="grid grid-cols-2 gap-2">
                                        <button
                                            type="button"
                                            disabled={kycProcessing}
                                            onClick={() => handleKycSubmit('verified')}
                                            className="w-full py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-semibold transition-colors cursor-pointer"
                                        >
                                            Verify KYC
                                        </button>
                                        <button
                                            type="button"
                                            disabled={kycProcessing}
                                            onClick={() => handleKycSubmit('rejected')}
                                            className="w-full py-2 bg-rose-100 dark:bg-rose-950/60 hover:bg-rose-200 text-rose-800 dark:text-rose-300 rounded-xl text-xs font-semibold transition-colors cursor-pointer"
                                        >
                                            Reject KYC
                                        </button>
                                    </div>
                                </div>
                            </div>
                        )}

                        {/* Account Status Control */}
                        {canManageStatus && (
                            <div className="bg-white dark:bg-[#0F172A] rounded-2xl border border-slate-200/80 dark:border-slate-800/80 p-5 shadow-xs space-y-4">
                                <h3 className="text-sm font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                                    <Shield className="w-4 h-4 text-emerald-600 dark:text-emerald-400" />
                                    Account Access Control
                                </h3>

                                <form onSubmit={handleStatusSubmit} className="space-y-3">
                                    <div>
                                        <label className="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">
                                            Target Status
                                        </label>
                                        <select
                                            value={statusData.status}
                                            onChange={(e) => setStatusData('status', e.target.value)}
                                            className="w-full px-3 py-2 border border-slate-300 dark:border-slate-700 rounded-xl text-xs bg-white dark:bg-[#1E293B] text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500"
                                        >
                                            <option value="active">Active</option>
                                            <option value="suspended">Suspended</option>
                                        </select>
                                    </div>

                                    <div>
                                        <label className="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">
                                            Reason for status change
                                        </label>
                                        <input
                                            type="text"
                                            value={statusData.reason}
                                            onChange={(e) => setStatusData('reason', e.target.value)}
                                            placeholder="Reason..."
                                            className="w-full px-3 py-2 border border-slate-300 dark:border-slate-700 rounded-xl text-xs bg-white dark:bg-[#1E293B] text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500"
                                        />
                                    </div>

                                    <button
                                        type="submit"
                                        disabled={statusProcessing}
                                        className="w-full py-2 bg-slate-900 dark:bg-blue-600 hover:bg-slate-800 dark:hover:bg-blue-500 text-white rounded-xl text-xs font-semibold transition-colors cursor-pointer"
                                    >
                                        Update Account Status
                                    </button>
                                </form>
                            </div>
                        )}

                        {/* Hierarchy Move Request & Execution */}
                        {canRequestHierarchyMove && (
                            <div className="bg-white dark:bg-[#0F172A] rounded-2xl border border-slate-200/80 dark:border-slate-800/80 p-5 shadow-xs space-y-4">
                                <h3 className="text-sm font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                                    <GitBranch className="w-4 h-4 text-emerald-600 dark:text-emerald-400" />
                                    Hierarchy Position Move
                                </h3>
                                <p className="text-[11px] text-slate-500 dark:text-slate-400">
                                    Moves this agent and their subtree to a new sponsor. Cycle detection prevents invalid parent loops. Only Executive Owner can execute approvals.
                                </p>

                                <form onSubmit={handleMoveSubmit} className="space-y-3">
                                    <div>
                                        <label className="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">
                                            New Sponsor (Parent)
                                        </label>
                                        <select
                                            value={moveData.requested_parent_id}
                                            onChange={(e) => setMoveData('requested_parent_id', e.target.value)}
                                            className="w-full px-3 py-2 border border-slate-300 dark:border-slate-700 rounded-xl text-xs bg-white dark:bg-[#1E293B] text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500"
                                        >
                                            <option value="">-- Promote to Top Level (No Sponsor) --</option>
                                            {availableParents.map((p) => (
                                                <option key={p.id} value={p.id}>
                                                    {p.name} ({p.referral_code})
                                                </option>
                                            ))}
                                        </select>
                                    </div>

                                    <div>
                                        <label className="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">
                                            Operational Justification *
                                        </label>
                                        <textarea
                                            rows={2}
                                            value={moveData.reason}
                                            onChange={(e) => setMoveData('reason', e.target.value)}
                                            placeholder="Why is this hierarchy movement needed?"
                                            className="w-full px-3 py-2 border border-slate-300 dark:border-slate-700 rounded-xl text-xs bg-white dark:bg-[#1E293B] text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500"
                                            required
                                        />
                                    </div>

                                    <button
                                        type="submit"
                                        disabled={moveProcessing}
                                        className="w-full py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-xl text-xs font-semibold transition-colors cursor-pointer"
                                    >
                                        Submit Hierarchy Move Request
                                    </button>
                                </form>

                                {/* Pending & Historical Change Requests */}
                                {changeRequests.length > 0 && (
                                    <div className="pt-3 border-t border-slate-100 dark:border-slate-800/80 space-y-2">
                                        <h4 className="text-xs font-semibold text-slate-700 dark:text-slate-300">Movement History</h4>
                                        {changeRequests.map((req) => (
                                            <div key={req.id} className="p-3 bg-slate-50 dark:bg-slate-800/60 rounded-xl text-xs space-y-1.5">
                                                <div className="flex items-center justify-between">
                                                    <span className="font-bold text-slate-800 dark:text-slate-200">
                                                        Target: {req.requested_parent ? req.requested_parent.name : 'Top Level'}
                                                    </span>
                                                    <span
                                                        className={`px-2 py-0.5 rounded text-[10px] font-bold uppercase ${
                                                            req.status === 'approved'
                                                                ? 'bg-emerald-100 dark:bg-emerald-950/60 text-emerald-800 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800'
                                                                : req.status === 'rejected'
                                                                ? 'bg-rose-100 dark:bg-rose-950/60 text-rose-800 dark:text-rose-300 border border-rose-200 dark:border-rose-800'
                                                                : 'bg-amber-100 dark:bg-amber-950/60 text-amber-800 dark:text-amber-300 border border-amber-200 dark:border-amber-800'
                                                        }`}
                                                    >
                                                        {req.status}
                                                    </span>
                                                </div>
                                                <p className="text-slate-600 dark:text-slate-400">Reason: {req.reason}</p>
                                                {req.review_notes && (
                                                    <p className="text-slate-500 dark:text-slate-400 italic">Review notes: {req.review_notes}</p>
                                                )}

                                                {req.status === 'pending' && isOwner && (
                                                    <div className="grid grid-cols-2 gap-2 pt-2">
                                                        <button
                                                            type="button"
                                                            onClick={() => handleReviewMove(req.id, 'approve')}
                                                            className="py-1 bg-emerald-600 text-white rounded-lg text-[11px] font-bold cursor-pointer hover:bg-emerald-500"
                                                        >
                                                            Owner Approve
                                                        </button>
                                                        <button
                                                            type="button"
                                                            onClick={() => handleReviewMove(req.id, 'reject')}
                                                            className="py-1 bg-rose-100 dark:bg-rose-950/60 text-rose-800 dark:text-rose-300 rounded-lg text-[11px] font-bold cursor-pointer hover:bg-rose-200"
                                                        >
                                                            Owner Reject
                                                        </button>
                                                    </div>
                                                )}
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
