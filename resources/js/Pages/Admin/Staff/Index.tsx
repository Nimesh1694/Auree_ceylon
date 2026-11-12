import * as Icons from '@/Components/Icons';
import AdminLayout from '@/Layouts/AdminLayout';
import { PageProps, Role, User } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/react';
import React, { useMemo, useState } from 'react';

interface Props {
    staff: User[];
    availableRoles: Role[];
    isOwner: boolean;
    isTechnicalAuthority?: boolean;
    canManageStaff: boolean;
}

export default function StaffIndex({
    staff,
    availableRoles,
    isOwner,
    isTechnicalAuthority,
    canManageStaff,
}: Props) {
    const { auth } = usePage<PageProps>().props;
    const currentUserId = auth.user?.id;

    // Modals
    const [createModalOpen, setCreateModalOpen] = useState(false);
    const [editUser, setEditUser] = useState<User | null>(null);

    // Search and filters
    const [searchQuery, setSearchQuery] = useState('');
    const [selectedRole, setSelectedRole] = useState('all');
    const [selectedStatus, setSelectedStatus] = useState('all');

    // Forms
    const createForm = useForm<{
        name: string;
        email: string;
        phone: string;
        role_id: number | string;
        password: string;
    }>({
        name: '',
        email: '',
        phone: '',
        role_id: availableRoles[0]?.id ?? '',
        password: '',
    });

    const editForm = useForm<{
        name: string;
        email: string;
        phone: string;
        role_id: number | string;
        password: string;
    }>({
        name: '',
        email: '',
        phone: '',
        role_id: '',
        password: '',
    });

    // KPI Metrics calculation
    const metrics = useMemo(() => {
        const total = staff.length;
        const active = staff.filter((s) => s.status === 'active').length;
        const suspended = staff.filter((s) => s.status === 'suspended').length;
        const mfaEnabled = staff.filter(
            (s) => Boolean((s as unknown as { two_factor_confirmed_at?: string })?.two_factor_confirmed_at)
        ).length;
        const mfaRate = total > 0 ? Math.round((mfaEnabled / total) * 100) : 0;

        return { total, active, suspended, mfaEnabled, mfaRate };
    }, [staff]);

    // Filtered staff list
    const filteredStaff = useMemo(() => {
        return staff.filter((u) => {
            const matchesSearch =
                searchQuery.trim() === '' ||
                u.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
                u.email.toLowerCase().includes(searchQuery.toLowerCase()) ||
                (u.phone && u.phone.includes(searchQuery.trim()));

            const roleSlug = typeof u.role === 'object' ? (u.role as { value: string }).value : String(u.role);
            const matchesRole = selectedRole === 'all' || roleSlug === selectedRole;
            const matchesStatus = selectedStatus === 'all' || u.status === selectedStatus;

            return matchesSearch && matchesRole && matchesStatus;
        });
    }, [staff, searchQuery, selectedRole, selectedStatus]);

    const openEditModal = (u: User) => {
        setEditUser(u);
        editForm.setData({
            name: u.name,
            email: u.email,
            phone: u.phone ?? '',
            role_id: (u as unknown as { role_id?: number }).role_id ?? '',
            password: '',
        });
    };

    const handleCreateSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        createForm.post('/admin/staff', {
            onSuccess: () => {
                createForm.reset();
                setCreateModalOpen(false);
            },
        });
    };

    const handleEditSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!editUser) return;
        editForm.put(`/admin/staff/${editUser.id}`, {
            onSuccess: () => setEditUser(null),
        });
    };

    const handleToggleStatus = (u: User) => {
        if (
            u.role === 'owner' ||
            u.protected_authority === 'business' ||
            u.role === 'super_admin' ||
            u.protected_authority === 'technical'
        ) {
            return;
        }
        const action = u.status === 'active' ? 'suspend' : 'reactivate';
        if (confirm(`Are you sure you want to ${action} staff member '${u.name}'?`)) {
            useForm().post(`/admin/staff/${u.id}/status`);
        }
    };

    const handleKillSessions = (u: User) => {
        const isTargetOwner = u.role === 'owner' || u.protected_authority === 'business';
        const isTargetSuperAdmin =
            u.role === 'super_admin' || u.role === 'it_manager' || u.protected_authority === 'technical';
        if (isTargetOwner && !isOwner) return;
        if (isTargetSuperAdmin && u.id !== currentUserId) return;

        if (
            confirm(
                `Force logout all active sessions for '${u.name}'? The user will be required to re-authenticate.`
            )
        ) {
            useForm().post(`/admin/staff/${u.id}/sessions`);
        }
    };

    const handleResetMfa = (u: User) => {
        const isTargetOwner = u.role === 'owner' || u.protected_authority === 'business';
        const isTargetSuperAdmin =
            u.role === 'super_admin' || u.role === 'it_manager' || u.protected_authority === 'technical';
        if (isTargetOwner && !isOwner) return;
        if (isTargetSuperAdmin && u.id !== currentUserId) return;

        if (
            confirm(
                `Reset Two-Factor Authentication for '${u.name}'? They will be required to reconfigure 2FA on their next login.`
            )
        ) {
            useForm().post(`/admin/staff/${u.id}/reset-mfa`);
        }
    };

    return (
        <AdminLayout>
            <Head title="Staff & Access Management | Technical Administration" />

            <div className="space-y-6">
                {/* Header Banner */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white dark:bg-[#0F172A] p-6 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-xs transition-colors duration-200">
                    <div>
                        <div className="flex items-center gap-2">
                            <span className="p-2 rounded-xl bg-blue-500/10 text-blue-600 dark:text-blue-400">
                                <Icons.Users className="w-5 h-5" />
                            </span>
                            <h1 className="text-xl font-bold text-slate-900 dark:text-white tracking-tight">
                                Staff & Access Management
                            </h1>
                        </div>
                        <p className="text-xs text-slate-500 dark:text-slate-400 mt-1.5 max-w-2xl">
                            Provision and govern administrative staff accounts, role assignments, security sessions, and two-factor MFA credentials across the enterprise platform.
                        </p>
                    </div>

                    {canManageStaff && (
                        <button
                            onClick={() => setCreateModalOpen(true)}
                            className="inline-flex items-center gap-2 px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-semibold shadow-xs shadow-blue-500/20 hover:shadow-md hover:shadow-blue-500/30 transition-all cursor-pointer shrink-0"
                        >
                            <Icons.Plus className="w-4 h-4 text-white" />
                            <span>Add Staff Member</span>
                        </button>
                    )}
                </div>

                {/* KPI Metrics Cards */}
                <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <div className="bg-white dark:bg-[#0F172A] p-4 rounded-xl border border-slate-200/80 dark:border-slate-800 shadow-2xs">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-medium text-slate-500 dark:text-slate-400">Total Staff</span>
                            <span className="p-1.5 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300">
                                <Icons.Users className="w-4 h-4" />
                            </span>
                        </div>
                        <div className="text-2xl font-bold text-slate-900 dark:text-white mt-2">
                            {metrics.total}
                        </div>
                        <p className="text-[11px] text-slate-400 dark:text-slate-500 mt-0.5">
                            Accounts provisioned
                        </p>
                    </div>

                    <div className="bg-white dark:bg-[#0F172A] p-4 rounded-xl border border-slate-200/80 dark:border-slate-800 shadow-2xs">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-medium text-slate-500 dark:text-slate-400">Active Accounts</span>
                            <span className="p-1.5 rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                                <Icons.CheckCircle2 className="w-4 h-4" />
                            </span>
                        </div>
                        <div className="text-2xl font-bold text-emerald-600 dark:text-emerald-400 mt-2">
                            {metrics.active}
                        </div>
                        <p className="text-[11px] text-slate-400 dark:text-slate-500 mt-0.5">
                            Fully authorized & online
                        </p>
                    </div>

                    <div className="bg-white dark:bg-[#0F172A] p-4 rounded-xl border border-slate-200/80 dark:border-slate-800 shadow-2xs">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-medium text-slate-500 dark:text-slate-400">Suspended</span>
                            <span className="p-1.5 rounded-lg bg-rose-500/10 text-rose-600 dark:text-rose-400">
                                <Icons.AlertCircle className="w-4 h-4" />
                            </span>
                        </div>
                        <div className="text-2xl font-bold text-rose-600 dark:text-rose-400 mt-2">
                            {metrics.suspended}
                        </div>
                        <p className="text-[11px] text-slate-400 dark:text-slate-500 mt-0.5">
                            Access revoked / locked
                        </p>
                    </div>

                    <div className="bg-white dark:bg-[#0F172A] p-4 rounded-xl border border-slate-200/80 dark:border-slate-800 shadow-2xs">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-medium text-slate-500 dark:text-slate-400">2FA Compliance</span>
                            <span className="p-1.5 rounded-lg bg-blue-500/10 text-blue-600 dark:text-blue-400">
                                <Icons.Lock className="w-4 h-4" />
                            </span>
                        </div>
                        <div className="text-2xl font-bold text-blue-600 dark:text-blue-400 mt-2">
                            {metrics.mfaRate}%
                        </div>
                        <p className="text-[11px] text-slate-400 dark:text-slate-500 mt-0.5">
                            {metrics.mfaEnabled} of {metrics.total} staff enforced
                        </p>
                    </div>
                </div>

                {/* Filter and Search Bar */}
                <div className="bg-white dark:bg-[#0F172A] p-4 rounded-xl border border-slate-200/80 dark:border-slate-800 shadow-2xs flex flex-col sm:flex-row gap-3 items-center justify-between">
                    <div className="relative w-full sm:w-80">
                        <Icons.Search className="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
                        <input
                            type="text"
                            placeholder="Search by name, email, or phone..."
                            value={searchQuery}
                            onChange={(e) => setSearchQuery(e.target.value)}
                            className="w-full pl-9 pr-3 py-2 text-xs rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500/30 focus:border-blue-500 transition-all"
                        />
                    </div>

                    <div className="flex items-center gap-3 w-full sm:w-auto">
                        <select
                            value={selectedRole}
                            onChange={(e) => setSelectedRole(e.target.value)}
                            className="text-xs rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 py-2 px-3 focus:outline-none focus:ring-2 focus:ring-blue-500/30 focus:border-blue-500"
                        >
                            <option value="all">All Roles</option>
                            <option value="owner">Business Owner</option>
                            <option value="super_admin">Super Admin</option>
                            <option value="accountant">Accountant</option>
                            <option value="marketing_manager">Marketing Manager</option>
                            <option value="operations_manager">Operations Manager</option>
                            <option value="product_inventory_manager">Product & Inventory Manager</option>
                            <option value="agent_manager">Agent Manager</option>
                        </select>

                        <select
                            value={selectedStatus}
                            onChange={(e) => setSelectedStatus(e.target.value)}
                            className="text-xs rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 py-2 px-3 focus:outline-none focus:ring-2 focus:ring-blue-500/30 focus:border-blue-500"
                        >
                            <option value="all">All Statuses</option>
                            <option value="active">Active</option>
                            <option value="suspended">Suspended</option>
                        </select>

                        {(searchQuery || selectedRole !== 'all' || selectedStatus !== 'all') && (
                            <button
                                onClick={() => {
                                    setSearchQuery('');
                                    setSelectedRole('all');
                                    setSelectedStatus('all');
                                }}
                                className="text-xs text-blue-600 dark:text-blue-400 hover:underline px-2 cursor-pointer font-medium"
                            >
                                Reset
                            </button>
                        )}
                    </div>
                </div>

                {/* Staff Table */}
                <div className="bg-white dark:bg-[#0F172A] rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-xs overflow-hidden transition-colors duration-200">
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-slate-200 dark:divide-slate-800 text-xs">
                            <thead className="bg-slate-50/80 dark:bg-slate-900/60 text-slate-600 dark:text-slate-400 uppercase tracking-wider font-semibold">
                                <tr>
                                    <th className="px-6 py-3.5 text-left">Staff Member</th>
                                    <th className="px-6 py-3.5 text-left">Role & Authority</th>
                                    <th className="px-6 py-3.5 text-left">Status</th>
                                    <th className="px-6 py-3.5 text-left">2FA MFA</th>
                                    <th className="px-6 py-3.5 text-right">Security & Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 dark:divide-slate-800/60">
                                {filteredStaff.length > 0 ? (
                                    filteredStaff.map((u) => {
                                        const isTargetOwner =
                                            u.role === 'owner' || u.protected_authority === 'business';
                                        const isTargetSuperAdmin =
                                            u.role === 'super_admin' ||
                                            u.role === 'it_manager' ||
                                            u.protected_authority === 'technical';
                                        const isSelf = u.id === currentUserId;

                                        return (
                                            <tr
                                                key={u.id}
                                                className="hover:bg-slate-50/60 dark:hover:bg-slate-800/40 transition-colors"
                                            >
                                                {/* Staff Info */}
                                                <td className="px-6 py-4">
                                                    <div className="flex items-center gap-3">
                                                        <div className="w-9 h-9 rounded-xl bg-blue-500/10 text-blue-600 dark:text-blue-400 font-bold flex items-center justify-center shrink-0 border border-blue-500/20 text-xs">
                                                            {u.name.charAt(0).toUpperCase()}
                                                        </div>
                                                        <div>
                                                            <div className="flex items-center gap-2">
                                                                <span className="font-semibold text-slate-900 dark:text-white text-sm">
                                                                    {u.name}
                                                                </span>
                                                                {isSelf && (
                                                                    <span className="px-2 py-0.5 rounded-md text-[10px] font-bold bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-500/20">
                                                                        You
                                                                    </span>
                                                                )}
                                                            </div>
                                                            <div className="text-[11px] text-slate-500 dark:text-slate-400">
                                                                {u.email}
                                                            </div>
                                                            {u.phone && (
                                                                <div className="text-[11px] text-slate-400 dark:text-slate-500 font-mono">
                                                                    {u.phone}
                                                                </div>
                                                            )}
                                                        </div>
                                                    </div>
                                                </td>

                                                {/* Role & Authority */}
                                                <td className="px-6 py-4">
                                                    <div className="flex flex-col gap-1 items-start">
                                                        <span
                                                            className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-semibold border uppercase tracking-wider ${
                                                                isTargetOwner
                                                                    ? 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/30 font-bold'
                                                                    : isTargetSuperAdmin
                                                                    ? 'bg-blue-500/10 text-blue-600 dark:text-blue-400 border-blue-500/30 font-bold'
                                                                    : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700'
                                                            }`}
                                                        >
                                                            {String(u.role).replace('_', ' ')}
                                                        </span>
                                                        {isTargetOwner && (
                                                            <span className="text-[10px] font-semibold text-amber-600 dark:text-amber-400 inline-flex items-center gap-1">
                                                                👑 Protected Business Authority
                                                            </span>
                                                        )}
                                                        {isTargetSuperAdmin && (
                                                            <span className="text-[10px] font-semibold text-blue-600 dark:text-blue-400 inline-flex items-center gap-1">
                                                                ⚡ Protected Technical Authority
                                                            </span>
                                                        )}
                                                    </div>
                                                </td>

                                                {/* Status */}
                                                <td className="px-6 py-4">
                                                    <span
                                                        className={`inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-bold ${
                                                            u.status === 'active'
                                                                ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30'
                                                                : 'bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/30'
                                                        }`}
                                                    >
                                                        <span
                                                            className={`w-1.5 h-1.5 rounded-full ${
                                                                u.status === 'active' ? 'bg-emerald-500' : 'bg-rose-500'
                                                            }`}
                                                        />
                                                        {u.status === 'active' ? 'Active' : 'Suspended'}
                                                    </span>
                                                </td>

                                                {/* 2FA MFA Status */}
                                                <td className="px-6 py-4">
                                                    {(u as unknown as { two_factor_confirmed_at?: string })
                                                        ?.two_factor_confirmed_at ? (
                                                        <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                                                            <Icons.Lock className="w-3 h-3 text-emerald-500" />
                                                            <span>Enforced</span>
                                                        </span>
                                                    ) : (
                                                        <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-semibold bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20">
                                                            <Icons.AlertCircle className="w-3 h-3 text-amber-500" />
                                                            <span>Not Configured</span>
                                                        </span>
                                                    )}
                                                </td>

                                                {/* Security & Actions */}
                                                <td className="px-6 py-4 text-right space-x-1.5 whitespace-nowrap">
                                                    {isTargetOwner && !isOwner ? (
                                                        <span className="text-[11px] font-bold text-amber-600 dark:text-amber-400 bg-amber-500/10 px-2.5 py-1 rounded-lg border border-amber-500/20 inline-flex items-center gap-1">
                                                            🛡️ Protected Owner
                                                        </span>
                                                    ) : isTargetSuperAdmin && !isSelf ? (
                                                        <span className="text-[11px] font-bold text-blue-600 dark:text-blue-400 bg-blue-500/10 px-2.5 py-1 rounded-lg border border-blue-500/20 inline-flex items-center gap-1">
                                                            ⚡ Protected Admin
                                                        </span>
                                                    ) : (
                                                        <>
                                                            <button
                                                                onClick={() => openEditModal(u)}
                                                                className="px-2.5 py-1 text-xs font-semibold rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 transition-colors cursor-pointer"
                                                            >
                                                                Edit
                                                            </button>

                                                            {!isTargetOwner && !isTargetSuperAdmin && (
                                                                <button
                                                                    onClick={() => handleToggleStatus(u)}
                                                                    className={`px-2.5 py-1 text-xs font-semibold rounded-lg transition-colors cursor-pointer ${
                                                                        u.status === 'active'
                                                                            ? 'bg-amber-500/10 hover:bg-amber-500/20 text-amber-600 dark:text-amber-400 border border-amber-500/20'
                                                                            : 'bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20'
                                                                    }`}
                                                                >
                                                                    {u.status === 'active' ? 'Suspend' : 'Activate'}
                                                                </button>
                                                            )}

                                                            {(!isTargetSuperAdmin || isSelf) &&
                                                                (!isTargetOwner || isOwner) && (
                                                                    <>
                                                                        <button
                                                                            onClick={() => handleKillSessions(u)}
                                                                            title="Terminate all active browser sessions for this user"
                                                                            className="px-2.5 py-1 text-xs font-semibold rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 transition-colors cursor-pointer"
                                                                        >
                                                                            Kill Sessions
                                                                        </button>
                                                                        <button
                                                                            onClick={() => handleResetMfa(u)}
                                                                            title="Reset Two-Factor Authentication"
                                                                            className="px-2.5 py-1 text-xs font-semibold rounded-lg bg-rose-500/10 hover:bg-rose-500/20 text-rose-600 dark:text-rose-400 border border-rose-500/20 transition-colors cursor-pointer"
                                                                        >
                                                                            Reset 2FA
                                                                        </button>
                                                                    </>
                                                                )}
                                                        </>
                                                    )}
                                                </td>
                                            </tr>
                                        );
                                    })
                                ) : (
                                    <tr>
                                        <td
                                            colSpan={5}
                                            className="px-6 py-12 text-center text-slate-400 dark:text-slate-500 text-xs"
                                        >
                                            No staff accounts match the specified criteria.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>

                {/* Create Staff Modal */}
                {createModalOpen && (
                    <div className="fixed inset-0 z-50 overflow-y-auto bg-slate-900/70 backdrop-blur-xs flex items-center justify-center p-4">
                        <div className="bg-white dark:bg-[#0F172A] rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-200 dark:border-slate-800 transition-colors">
                            <div className="flex items-center justify-between pb-3 border-b border-slate-200 dark:border-slate-800 mb-4">
                                <h2 className="text-base font-bold text-slate-900 dark:text-white">
                                    Create New Staff Account
                                </h2>
                                <button
                                    onClick={() => setCreateModalOpen(false)}
                                    className="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 text-lg font-bold"
                                >
                                    ✕
                                </button>
                            </div>

                            <form onSubmit={handleCreateSubmit} className="space-y-4">
                                <div>
                                    <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                        Full Name *
                                    </label>
                                    <input
                                        type="text"
                                        value={createForm.data.name}
                                        onChange={(e) => createForm.setData('name', e.target.value)}
                                        required
                                        className="w-full text-xs rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white p-2.5 focus:ring-2 focus:ring-blue-500/30 focus:border-blue-500"
                                    />
                                    {createForm.errors.name && (
                                        <p className="text-rose-500 text-[11px] mt-1">{createForm.errors.name}</p>
                                    )}
                                </div>

                                <div>
                                    <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                        Email Address *
                                    </label>
                                    <input
                                        type="email"
                                        value={createForm.data.email}
                                        onChange={(e) => createForm.setData('email', e.target.value)}
                                        required
                                        className="w-full text-xs rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white p-2.5 focus:ring-2 focus:ring-blue-500/30 focus:border-blue-500"
                                    />
                                    {createForm.errors.email && (
                                        <p className="text-rose-500 text-[11px] mt-1">{createForm.errors.email}</p>
                                    )}
                                </div>

                                <div>
                                    <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                        Phone Number *
                                    </label>
                                    <input
                                        type="text"
                                        value={createForm.data.phone}
                                        onChange={(e) => createForm.setData('phone', e.target.value)}
                                        placeholder="0771234567"
                                        required
                                        className="w-full text-xs rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white p-2.5 focus:ring-2 focus:ring-blue-500/30 focus:border-blue-500 font-mono"
                                    />
                                    {createForm.errors.phone && (
                                        <p className="text-rose-500 text-[11px] mt-1">{createForm.errors.phone}</p>
                                    )}
                                </div>

                                <div>
                                    <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                        Assign Role *
                                    </label>
                                    <select
                                        value={createForm.data.role_id}
                                        onChange={(e) => createForm.setData('role_id', e.target.value)}
                                        className="w-full text-xs rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white p-2.5 focus:ring-2 focus:ring-blue-500/30 focus:border-blue-500"
                                    >
                                        {availableRoles.map((r) => (
                                            <option key={r.id} value={r.id}>
                                                {r.display_name} ({r.name})
                                            </option>
                                        ))}
                                    </select>
                                </div>

                                <div>
                                    <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                        Temporary Password *
                                    </label>
                                    <input
                                        type="password"
                                        value={createForm.data.password}
                                        onChange={(e) => createForm.setData('password', e.target.value)}
                                        placeholder="Minimum 8 characters"
                                        required
                                        className="w-full text-xs rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white p-2.5 focus:ring-2 focus:ring-blue-500/30 focus:border-blue-500"
                                    />
                                    {createForm.errors.password && (
                                        <p className="text-rose-500 text-[11px] mt-1">{createForm.errors.password}</p>
                                    )}
                                </div>

                                <div className="flex items-center justify-end gap-3 pt-3 border-t border-slate-200 dark:border-slate-800">
                                    <button
                                        type="button"
                                        onClick={() => setCreateModalOpen(false)}
                                        className="px-4 py-2 text-xs font-semibold rounded-xl border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                                    >
                                        Cancel
                                    </button>
                                    <button
                                        type="submit"
                                        disabled={createForm.processing}
                                        className="px-5 py-2 text-xs font-semibold rounded-xl bg-blue-600 hover:bg-blue-700 text-white transition-all shadow-xs shadow-blue-500/20 disabled:opacity-50 cursor-pointer"
                                    >
                                        {createForm.processing ? 'Creating...' : 'Create Staff Member'}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                )}

                {/* Edit Staff Modal */}
                {editUser && (
                    <div className="fixed inset-0 z-50 overflow-y-auto bg-slate-900/70 backdrop-blur-xs flex items-center justify-center p-4">
                        <div className="bg-white dark:bg-[#0F172A] rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-200 dark:border-slate-800 transition-colors">
                            <div className="flex items-center justify-between pb-3 border-b border-slate-200 dark:border-slate-800 mb-4">
                                <h2 className="text-base font-bold text-slate-900 dark:text-white">
                                    Edit Staff Member: {editUser.name}
                                </h2>
                                <button
                                    onClick={() => setEditUser(null)}
                                    className="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 text-lg font-bold"
                                >
                                    ✕
                                </button>
                            </div>

                            <form onSubmit={handleEditSubmit} className="space-y-4">
                                <div>
                                    <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                        Full Name *
                                    </label>
                                    <input
                                        type="text"
                                        value={editForm.data.name}
                                        onChange={(e) => editForm.setData('name', e.target.value)}
                                        required
                                        className="w-full text-xs rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white p-2.5 focus:ring-2 focus:ring-blue-500/30 focus:border-blue-500"
                                    />
                                </div>

                                <div>
                                    <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                        Email Address *
                                    </label>
                                    <input
                                        type="email"
                                        value={editForm.data.email}
                                        onChange={(e) => editForm.setData('email', e.target.value)}
                                        required
                                        className="w-full text-xs rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white p-2.5 focus:ring-2 focus:ring-blue-500/30 focus:border-blue-500"
                                    />
                                </div>

                                <div>
                                    <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                        Phone Number *
                                    </label>
                                    <input
                                        type="text"
                                        value={editForm.data.phone}
                                        onChange={(e) => editForm.setData('phone', e.target.value)}
                                        required
                                        className="w-full text-xs rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white p-2.5 focus:ring-2 focus:ring-blue-500/30 focus:border-blue-500 font-mono"
                                    />
                                </div>

                                <div>
                                    <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                        Assigned Role *
                                    </label>
                                    <select
                                        value={editForm.data.role_id}
                                        disabled={editUser.role === 'owner'}
                                        onChange={(e) => editForm.setData('role_id', e.target.value)}
                                        className="w-full text-xs rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white p-2.5 focus:ring-2 focus:ring-blue-500/30 focus:border-blue-500 disabled:opacity-50"
                                    >
                                        {availableRoles.map((r) => (
                                            <option key={r.id} value={r.id}>
                                                {r.display_name} ({r.name})
                                            </option>
                                        ))}
                                    </select>
                                    {editUser.role === 'owner' && (
                                        <p className="text-[11px] text-amber-600 dark:text-amber-400 mt-1">
                                            The Business Owner role is immutable and cannot be altered.
                                        </p>
                                    )}
                                </div>

                                <div>
                                    <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                        New Password (leave blank to keep current)
                                    </label>
                                    <input
                                        type="password"
                                        value={editForm.data.password}
                                        onChange={(e) => editForm.setData('password', e.target.value)}
                                        placeholder="••••••••"
                                        className="w-full text-xs rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white p-2.5 focus:ring-2 focus:ring-blue-500/30 focus:border-blue-500"
                                    />
                                </div>

                                <div className="flex items-center justify-end gap-3 pt-3 border-t border-slate-200 dark:border-slate-800">
                                    <button
                                        type="button"
                                        onClick={() => setEditUser(null)}
                                        className="px-4 py-2 text-xs font-semibold rounded-xl border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                                    >
                                        Cancel
                                    </button>
                                    <button
                                        type="submit"
                                        disabled={editForm.processing}
                                        className="px-5 py-2 text-xs font-semibold rounded-xl bg-blue-600 hover:bg-blue-700 text-white transition-all shadow-xs shadow-blue-500/20 disabled:opacity-50 cursor-pointer"
                                    >
                                        {editForm.processing ? 'Saving...' : 'Update Staff Member'}
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
