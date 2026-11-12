import * as Icons from '@/Components/Icons';
import AdminLayout from '@/Layouts/AdminLayout';
import { PageProps, Permission, Role } from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import React, { useMemo, useState } from 'react';

interface Props {
    roles: Role[];
    permissions: Permission[];
    permissionsByModule: Record<string, Permission[]>;
    pendingRequestsCount: number;
    canManageRoles: boolean;
}

export default function RolesIndex({
    roles,
    permissions,
    permissionsByModule,
    pendingRequestsCount,
    canManageRoles,
}: Props) {
    const { auth } = usePage<PageProps>().props;
    const isOwner = auth.is_owner || auth.user?.role === 'owner';

    const [modalOpen, setModalOpen] = useState(false);
    const [editingRole, setEditingRole] = useState<Role | null>(null);

    // Filters for Roles table
    const [searchQuery, setSearchQuery] = useState('');
    const [typeFilter, setTypeFilter] = useState<'all' | 'protected' | 'system' | 'custom'>('all');

    // Filter for inside the permission modal
    const [permSearch, setPermSearch] = useState('');

    const { data, setData, post, put, delete: destroy, processing, errors, reset } = useForm({
        name: '',
        display_name: '',
        description: '',
        permissions: [] as number[],
    });

    const openCreateModal = () => {
        setEditingRole(null);
        reset();
        setData({
            name: '',
            display_name: '',
            description: '',
            permissions: [],
        });
        setPermSearch('');
        setModalOpen(true);
    };

    const openEditModal = (role: Role) => {
        setEditingRole(role);
        setData({
            name: role.name,
            display_name: role.display_name,
            description: role.description ?? '',
            permissions: role.permissions?.map((p) => p.id) ?? [],
        });
        setPermSearch('');
        setModalOpen(true);
    };

    const handlePermissionToggle = (permId: number) => {
        if (data.permissions.includes(permId)) {
            setData('permissions', data.permissions.filter((id) => id !== permId));
        } else {
            setData('permissions', [...data.permissions, permId]);
        }
    };

    const handleSelectAllInModule = (modulePerms: Permission[]) => {
        const modulePermIds = modulePerms.map((p) => p.id);
        const allSelected = modulePermIds.every((id) => data.permissions.includes(id));

        if (allSelected) {
            setData(
                'permissions',
                data.permissions.filter((id) => !modulePermIds.includes(id))
            );
        } else {
            const newPerms = Array.from(new Set([...data.permissions, ...modulePermIds]));
            setData('permissions', newPerms);
        }
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (editingRole) {
            put(`/admin/roles/${editingRole.id}`, {
                onSuccess: () => setModalOpen(false),
            });
        } else {
            post('/admin/roles', {
                onSuccess: () => setModalOpen(false),
            });
        }
    };

    const handleDelete = (role: Role) => {
        if (role.is_system || role.is_protected) return;
        if (confirm(`Are you sure you want to permanently delete custom role '${role.display_name}'?`)) {
            destroy(`/admin/roles/${role.id}`);
        }
    };

    const moduleLabels: Record<string, string> = {
        users: 'Staff & User Accounts',
        roles: 'Role & Permission System',
        catalog: 'Catalog & Storefront',
        inventory: 'Inventory & Couriers',
        orders: 'Orders & Cash on Delivery',
        agents: 'Agent Network & KYC',
        commissions: 'Commission Governance',
        finance: 'Finance & Payouts',
        system: 'System Health & Settings',
    };

    // Filter roles
    const filteredRoles = useMemo(() => {
        return roles.filter((r) => {
            const matchesSearch =
                searchQuery.trim() === '' ||
                r.display_name.toLowerCase().includes(searchQuery.toLowerCase()) ||
                r.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
                (r.description && r.description.toLowerCase().includes(searchQuery.toLowerCase()));

            let matchesType = true;
            if (typeFilter === 'protected') matchesType = Boolean(r.is_protected);
            else if (typeFilter === 'system') matchesType = Boolean(r.is_system && !r.is_protected);
            else if (typeFilter === 'custom') matchesType = Boolean(!r.is_system && !r.is_protected);

            return matchesSearch && matchesType;
        });
    }, [roles, searchQuery, typeFilter]);

    return (
        <AdminLayout>
            <Head title="Roles & Access Control | Technical Administration" />

            <div className="space-y-6">
                {/* Header Banner */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white dark:bg-[#0F172A] p-6 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-xs transition-colors duration-200">
                    <div>
                        <div className="flex items-center gap-2">
                            <span className="p-2 rounded-xl bg-blue-500/10 text-blue-600 dark:text-blue-400">
                                <Icons.Shield className="w-5 h-5" />
                            </span>
                            <h1 className="text-xl font-bold text-slate-900 dark:text-white tracking-tight">
                                Roles & Access Control
                            </h1>
                        </div>
                        <p className="text-xs text-slate-500 dark:text-slate-400 mt-1.5 max-w-2xl">
                            Configure dynamic staff roles and granular capability permissions across the enterprise platform with strict two-person authorization boundaries.
                        </p>
                    </div>

                    <div className="flex items-center gap-3 shrink-0">
                        <Link
                            href="/admin/roles/permission-requests"
                            className="inline-flex items-center gap-2 px-3.5 py-2.5 rounded-xl text-xs font-semibold bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-700 transition-colors"
                        >
                            <Icons.CheckSquare className="w-4 h-4 text-slate-500 dark:text-slate-400" />
                            <span>Permission Requests</span>
                            {pendingRequestsCount > 0 && (
                                <span className="ml-1 px-2 py-0.5 rounded-full bg-amber-500 text-white text-[10px] font-bold">
                                    {pendingRequestsCount}
                                </span>
                            )}
                        </Link>

                        {canManageRoles && (
                            <button
                                onClick={openCreateModal}
                                className="inline-flex items-center gap-2 px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-semibold shadow-xs shadow-blue-500/20 hover:shadow-md hover:shadow-blue-500/30 transition-all cursor-pointer"
                            >
                                <Icons.Plus className="w-4 h-4 text-white" />
                                <span>Create Custom Role</span>
                            </button>
                        )}
                    </div>
                </div>

                {/* Filter and Search Bar */}
                <div className="bg-white dark:bg-[#0F172A] p-4 rounded-xl border border-slate-200/80 dark:border-slate-800 shadow-2xs flex flex-col sm:flex-row gap-3 items-center justify-between">
                    <div className="relative w-full sm:w-80">
                        <Icons.Search className="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
                        <input
                            type="text"
                            placeholder="Search roles by name or description..."
                            value={searchQuery}
                            onChange={(e) => setSearchQuery(e.target.value)}
                            className="w-full pl-9 pr-3 py-2 text-xs rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500/30 focus:border-blue-500 transition-all"
                        />
                    </div>

                    <div className="flex items-center gap-2">
                        {(['all', 'protected', 'system', 'custom'] as const).map((t) => (
                            <button
                                key={t}
                                onClick={() => setTypeFilter(t)}
                                className={`px-3 py-1.5 rounded-xl text-xs font-semibold capitalize transition-all cursor-pointer ${
                                    typeFilter === t
                                        ? 'bg-blue-600 text-white shadow-xs shadow-blue-500/20'
                                        : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 hover:bg-slate-200 dark:hover:bg-slate-700'
                                }`}
                            >
                                {t === 'all' ? 'All Roles' : t}
                            </button>
                        ))}
                    </div>
                </div>

                {/* Roles Table */}
                <div className="bg-white dark:bg-[#0F172A] rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-xs overflow-hidden transition-colors duration-200">
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-slate-200 dark:divide-slate-800 text-xs">
                            <thead className="bg-slate-50/80 dark:bg-slate-900/60 text-slate-600 dark:text-slate-400 uppercase tracking-wider font-semibold">
                                <tr>
                                    <th className="px-6 py-3.5 text-left">Role Name</th>
                                    <th className="px-6 py-3.5 text-left">Type & Security</th>
                                    <th className="px-6 py-3.5 text-left">Assigned Staff</th>
                                    <th className="px-6 py-3.5 text-left">Permissions Count</th>
                                    <th className="px-6 py-3.5 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 dark:divide-slate-800/60">
                                {filteredRoles.length > 0 ? (
                                    filteredRoles.map((role) => {
                                        const isProtectedOwner = role.is_protected && role.name === 'owner';
                                        const isProtectedSuperAdmin = role.is_protected && role.name === 'super_admin';

                                        return (
                                            <tr
                                                key={role.id}
                                                className="hover:bg-slate-50/60 dark:hover:bg-slate-800/40 transition-colors"
                                            >
                                                <td className="px-6 py-4">
                                                    <div className="font-semibold text-slate-900 dark:text-white text-sm">
                                                        {role.display_name}
                                                    </div>
                                                    <div className="text-[11px] font-mono text-slate-500 dark:text-slate-400">
                                                        {role.name}
                                                    </div>
                                                    {role.description && (
                                                        <p className="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5 max-w-md">
                                                            {role.description}
                                                        </p>
                                                    )}
                                                </td>

                                                <td className="px-6 py-4">
                                                    {isProtectedOwner ? (
                                                        <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/30">
                                                            👑 Protected Owner
                                                        </span>
                                                    ) : isProtectedSuperAdmin ? (
                                                        <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-500/30">
                                                            ⚡ Protected Admin
                                                        </span>
                                                    ) : role.is_system ? (
                                                        <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-semibold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700">
                                                            System Role
                                                        </span>
                                                    ) : (
                                                        <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30">
                                                            Dynamic Custom Role
                                                        </span>
                                                    )}
                                                </td>

                                                <td className="px-6 py-4 text-slate-700 dark:text-slate-300 font-medium">
                                                    {(role.users_count ?? 0) + (role.assigned_users_count ?? 0)} users
                                                </td>

                                                <td className="px-6 py-4">
                                                    <span className="font-semibold text-slate-900 dark:text-white">
                                                        {role.is_protected ? 'All (42)' : role.permissions?.length ?? 0}
                                                    </span>
                                                    <span className="text-slate-400"> / {permissions.length}</span>
                                                </td>

                                                <td className="px-6 py-4 text-right space-x-2 whitespace-nowrap">
                                                    {role.is_protected ? (
                                                        <span className="text-slate-400 dark:text-slate-500 italic text-[11px]">
                                                            Hardcoded Lock
                                                        </span>
                                                    ) : (
                                                        <>
                                                            <button
                                                                onClick={() => openEditModal(role)}
                                                                className="px-2.5 py-1 text-xs font-semibold rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 transition-colors cursor-pointer"
                                                            >
                                                                Edit Permissions
                                                            </button>

                                                            {!role.is_system && (
                                                                <button
                                                                    onClick={() => handleDelete(role)}
                                                                    disabled={(role.users_count ?? 0) > 0}
                                                                    className={`px-2.5 py-1 text-xs font-semibold rounded-lg transition-colors ${
                                                                        (role.users_count ?? 0) > 0
                                                                            ? 'bg-slate-100 dark:bg-slate-800 text-slate-400 cursor-not-allowed'
                                                                            : 'bg-rose-500/10 hover:bg-rose-500/20 text-rose-600 dark:text-rose-400 border border-rose-500/20 cursor-pointer'
                                                                    }`}
                                                                >
                                                                    Delete
                                                                </button>
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
                                            No roles found matching the specified filter.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>

                {/* Create / Edit Role Modal */}
                {modalOpen && (
                    <div className="fixed inset-0 z-50 overflow-y-auto bg-slate-900/70 backdrop-blur-xs flex items-center justify-center p-4">
                        <div className="bg-white dark:bg-[#0F172A] rounded-2xl max-w-3xl w-full max-h-[90vh] flex flex-col shadow-2xl border border-slate-200 dark:border-slate-800 transition-colors">
                            {/* Modal Header */}
                            <div className="px-6 py-4 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
                                <h2 className="text-base font-bold text-slate-900 dark:text-white">
                                    {editingRole
                                        ? `Edit Role: ${editingRole.display_name}`
                                        : 'Create New Custom Staff Role'}
                                </h2>
                                <button
                                    onClick={() => setModalOpen(false)}
                                    className="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 text-lg font-bold"
                                >
                                    ✕
                                </button>
                            </div>

                            {/* Modal Form */}
                            <form onSubmit={handleSubmit} className="flex-1 overflow-y-auto p-6 space-y-5">
                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                            Role Display Name *
                                        </label>
                                        <input
                                            type="text"
                                            value={data.display_name}
                                            onChange={(e) => setData('display_name', e.target.value)}
                                            placeholder="e.g. Order Dispatcher"
                                            required
                                            className="w-full text-xs rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white p-2.5 focus:ring-2 focus:ring-blue-500/30 focus:border-blue-500"
                                        />
                                        {errors.display_name && (
                                            <p className="text-rose-500 text-[11px] mt-1">{errors.display_name}</p>
                                        )}
                                    </div>

                                    <div>
                                        <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                            Role Slug / Code *
                                        </label>
                                        <input
                                            type="text"
                                            value={data.name}
                                            onChange={(e) => setData('name', e.target.value)}
                                            placeholder="e.g. order_dispatcher"
                                            disabled={!!editingRole}
                                            required
                                            className="w-full text-xs rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white p-2.5 focus:ring-2 focus:ring-blue-500/30 focus:border-blue-500 font-mono disabled:opacity-50"
                                        />
                                        {errors.name && (
                                            <p className="text-rose-500 text-[11px] mt-1">{errors.name}</p>
                                        )}
                                    </div>
                                </div>

                                <div>
                                    <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                        Description
                                    </label>
                                    <textarea
                                        value={data.description}
                                        onChange={(e) => setData('description', e.target.value)}
                                        rows={2}
                                        placeholder="Describe the responsibilities and scope of this role..."
                                        className="w-full text-xs rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white p-2.5 focus:ring-2 focus:ring-blue-500/30 focus:border-blue-500"
                                    />
                                </div>

                                {/* Permission Matrix */}
                                <div>
                                    <div className="flex items-center justify-between mb-2">
                                        <label className="text-xs font-bold text-slate-900 dark:text-white uppercase tracking-wider">
                                            Granular Permissions
                                        </label>
                                        <span className="text-[11px] text-slate-500 dark:text-slate-400">
                                            {data.permissions.length} selected
                                        </span>
                                    </div>

                                    {/* Permission search inside modal */}
                                    <div className="mb-3">
                                        <input
                                            type="text"
                                            placeholder="Filter permissions by keyword (e.g. order, payout, user)..."
                                            value={permSearch}
                                            onChange={(e) => setPermSearch(e.target.value)}
                                            className="w-full text-xs rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white px-3 py-2 focus:ring-2 focus:ring-blue-500/30 focus:border-blue-500"
                                        />
                                    </div>

                                    {!isOwner && (
                                        <div className="bg-amber-500/10 border border-amber-500/20 text-amber-600 dark:text-amber-400 text-[11px] p-3 rounded-xl mb-4 flex items-start gap-2">
                                            <span className="font-bold">ℹ️</span>
                                            <span>
                                                <strong>Owner Approval Policy:</strong> Permissions marked with 🔒 <em>Restricted</em> require explicit authorization from the Business Owner. If selected, an approval request will be submitted to the Owner.
                                            </span>
                                        </div>
                                    )}

                                    <div className="space-y-4">
                                        {Object.entries(permissionsByModule).map(([moduleKey, modulePerms]) => {
                                            const filteredPerms = modulePerms.filter(
                                                (p) =>
                                                    permSearch.trim() === '' ||
                                                    p.display_name.toLowerCase().includes(permSearch.toLowerCase()) ||
                                                    p.name.toLowerCase().includes(permSearch.toLowerCase())
                                            );

                                            if (filteredPerms.length === 0) return null;

                                            return (
                                                <div
                                                    key={moduleKey}
                                                    className="border border-slate-200 dark:border-slate-800 rounded-xl p-4 bg-slate-50/50 dark:bg-slate-900/40"
                                                >
                                                    <div className="flex items-center justify-between mb-3 border-b border-slate-200 dark:border-slate-800 pb-2">
                                                        <span className="font-semibold text-slate-800 dark:text-slate-200 text-xs uppercase tracking-wide">
                                                            {moduleLabels[moduleKey] ?? moduleKey}
                                                        </span>
                                                        <button
                                                            type="button"
                                                            onClick={() => handleSelectAllInModule(filteredPerms)}
                                                            className="text-[11px] text-blue-600 dark:text-blue-400 hover:underline font-semibold cursor-pointer"
                                                        >
                                                            Toggle Module
                                                        </button>
                                                    </div>

                                                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                                                        {filteredPerms.map((perm) => {
                                                            const isChecked = data.permissions.includes(perm.id);
                                                            return (
                                                                <label
                                                                    key={perm.id}
                                                                    className={`flex items-start gap-2.5 p-2 rounded-xl border transition-colors cursor-pointer ${
                                                                        isChecked
                                                                            ? 'bg-blue-500/10 border-blue-500/40 text-slate-900 dark:text-white'
                                                                            : 'bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700'
                                                                    }`}
                                                                >
                                                                    <input
                                                                        type="checkbox"
                                                                        checked={isChecked}
                                                                        onChange={() => handlePermissionToggle(perm.id)}
                                                                        className="mt-0.5 rounded border-slate-300 dark:border-slate-700 text-blue-600 focus:ring-blue-500"
                                                                    />
                                                                    <div className="flex-1 min-w-0">
                                                                        <div className="flex items-center gap-1.5 flex-wrap">
                                                                            <span className="text-xs font-medium text-slate-900 dark:text-slate-100">
                                                                                {perm.display_name}
                                                                            </span>
                                                                            {perm.is_financial_restricted && (
                                                                                <span className="px-1.5 py-0.2 rounded text-[9px] font-bold bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20">
                                                                                    🔒 Restricted
                                                                                </span>
                                                                            )}
                                                                        </div>
                                                                        <p className="text-[10px] text-slate-400 dark:text-slate-500 font-mono">
                                                                            {perm.name}
                                                                        </p>
                                                                    </div>
                                                                </label>
                                                            );
                                                        })}
                                                    </div>
                                                </div>
                                            );
                                        })}
                                    </div>
                                </div>

                                {/* Modal Actions */}
                                <div className="pt-4 border-t border-slate-200 dark:border-slate-800 flex items-center justify-end gap-3">
                                    <button
                                        type="button"
                                        onClick={() => setModalOpen(false)}
                                        className="px-4 py-2 text-xs font-semibold rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300 transition-colors"
                                    >
                                        Cancel
                                    </button>
                                    <button
                                        type="submit"
                                        disabled={processing}
                                        className="px-5 py-2 text-xs font-semibold rounded-xl bg-blue-600 hover:bg-blue-700 text-white transition-all shadow-xs shadow-blue-500/20 disabled:opacity-50 cursor-pointer"
                                    >
                                        {processing ? 'Saving...' : editingRole ? 'Update Role' : 'Create Role'}
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
