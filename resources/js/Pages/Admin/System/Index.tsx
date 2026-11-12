import AdminLayout from '@/Layouts/AdminLayout';
import { AuditLog } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import React, { useState } from 'react';

interface Diagnostics {
    php_version: string;
    laravel_version: string;
    environment: string;
    database_connection: string;
    database_engine: string;
    cache_driver: string;
    queue_driver: string;
    session_driver: string;
    active_sessions: number;
    total_users: number;
    total_audit_events: number;
    is_down_for_maintenance: boolean;
    server_time: string;
}

interface Props {
    diagnostics: Diagnostics;
    recentLogs: AuditLog[];
    canManageMaintenance: boolean;
}

export default function SystemIndex({ diagnostics, recentLogs, canManageMaintenance }: Props) {
    const [maintenanceModalOpen, setMaintenanceModalOpen] = useState(false);
    const maintenanceForm = useForm({
        secret: 'aurabypass2026',
    });

    const handleToggleMaintenance = (e: React.FormEvent) => {
        e.preventDefault();
        maintenanceForm.post('/admin/system/maintenance', {
            onSuccess: () => setMaintenanceModalOpen(false),
        });
    };

    return (
        <AdminLayout>
            <Head title="System Health & Technical Diagnostics | Administration" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white dark:bg-[#0F172A] p-6 rounded-2xl border border-slate-200/80 dark:border-slate-800/80 shadow-xs transition-colors">
                    <div>
                        <h1 className="text-xl font-serif font-bold text-slate-900 dark:text-white">
                            Technical System Diagnostics
                        </h1>
                        <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
                            Platform runtime metrics, server health, active browser sessions, and security audit log.
                        </p>
                    </div>

                    <div className="flex items-center gap-3">
                        <Link
                            href="/admin/system/logs"
                            className="inline-flex items-center px-4 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 rounded-xl text-xs font-semibold tracking-wide transition-colors border border-slate-200 dark:border-slate-700"
                        >
                            View All Audit Logs →
                        </Link>

                        {canManageMaintenance && (
                            <button
                                onClick={() => setMaintenanceModalOpen(true)}
                                className={`inline-flex items-center px-4 py-2 rounded-xl text-xs font-semibold tracking-wide transition-colors text-white cursor-pointer ${
                                    diagnostics.is_down_for_maintenance
                                        ? 'bg-emerald-600 hover:bg-emerald-700'
                                        : 'bg-amber-600 hover:bg-amber-700'
                                }`}
                            >
                                {diagnostics.is_down_for_maintenance ? 'Disable Maintenance Mode' : 'Enable Maintenance Mode'}
                            </button>
                        )}
                    </div>
                </div>

                {/* Diagnostics Cards Grid */}
                <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
                    <div className="bg-white dark:bg-[#0F172A] p-5 rounded-2xl border border-slate-200/80 dark:border-slate-800/80 shadow-xs transition-colors">
                        <span className="text-[11px] font-semibold text-slate-400 dark:text-slate-500 uppercase tracking-wider block">
                            PHP Runtime
                        </span>
                        <div className="text-base font-bold text-slate-900 dark:text-white mt-1 font-mono">
                            PHP {diagnostics.php_version}
                        </div>
                        <span className="text-[10px] text-emerald-600 dark:text-emerald-400 font-medium">
                            Laravel v{diagnostics.laravel_version}
                        </span>
                    </div>

                    <div className="bg-white dark:bg-[#0F172A] p-5 rounded-2xl border border-slate-200/80 dark:border-slate-800/80 shadow-xs transition-colors">
                        <span className="text-[11px] font-semibold text-slate-400 dark:text-slate-500 uppercase tracking-wider block">
                            Database Engine
                        </span>
                        <div className="text-base font-bold text-slate-900 dark:text-white mt-1 uppercase font-mono">
                            {diagnostics.database_engine}
                        </div>
                        <span className="text-[10px] text-slate-500 dark:text-slate-400">
                            Connection: {diagnostics.database_connection}
                        </span>
                    </div>

                    <div className="bg-white dark:bg-[#0F172A] p-5 rounded-2xl border border-slate-200/80 dark:border-slate-800/80 shadow-xs transition-colors">
                        <span className="text-[11px] font-semibold text-slate-400 dark:text-slate-500 uppercase tracking-wider block">
                            Active User Sessions
                        </span>
                        <div className="text-base font-bold text-slate-900 dark:text-white mt-1">
                            {diagnostics.active_sessions} Sessions
                        </div>
                        <span className="text-[10px] text-slate-500 dark:text-slate-400">
                            Driver: {diagnostics.session_driver}
                        </span>
                    </div>

                    <div className="bg-white dark:bg-[#0F172A] p-5 rounded-2xl border border-slate-200/80 dark:border-slate-800/80 shadow-xs transition-colors">
                        <span className="text-[11px] font-semibold text-slate-400 dark:text-slate-500 uppercase tracking-wider block">
                            Maintenance Status
                        </span>
                        <div className="text-base font-bold mt-1">
                            {diagnostics.is_down_for_maintenance ? (
                                <span className="text-amber-600 dark:text-amber-400">Active (Down)</span>
                            ) : (
                                <span className="text-emerald-600 dark:text-emerald-400">Online & Live</span>
                            )}
                        </div>
                        <span className="text-[10px] text-slate-500 dark:text-slate-400">
                            Environment: {diagnostics.environment}
                        </span>
                    </div>
                </div>

                {/* Audit Logs Table Preview */}
                <div className="bg-white dark:bg-[#0F172A] rounded-2xl border border-slate-200/80 dark:border-slate-800/80 shadow-xs overflow-hidden transition-colors">
                    <div className="px-6 py-4 border-b border-slate-100 dark:border-slate-800/80 flex items-center justify-between">
                        <h2 className="text-sm font-serif font-bold text-slate-900 dark:text-white">
                            Recent Security Audit Trail
                        </h2>
                        <Link
                            href="/admin/system/logs"
                            className="text-xs text-blue-600 dark:text-blue-400 hover:underline font-semibold"
                        >
                            View Full History ({diagnostics.total_audit_events} events) →
                        </Link>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-slate-100 dark:divide-slate-800/80 text-xs text-left bg-white dark:bg-[#0F172A]">
                            <thead className="bg-slate-50 dark:bg-slate-900/60 text-slate-500 dark:text-slate-400 uppercase tracking-wider font-semibold text-[11px]">
                                <tr>
                                    <th className="px-6 py-3.5 text-left">Timestamp</th>
                                    <th className="px-6 py-3.5 text-left">Action</th>
                                    <th className="px-6 py-3.5 text-left">Actor</th>
                                    <th className="px-6 py-3.5 text-left">IP Address</th>
                                    <th className="px-6 py-3.5 text-left">Details</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 dark:divide-slate-800/60 bg-white dark:bg-[#0F172A]">
                                {recentLogs.map((log) => (
                                    <tr key={log.id} className="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition-colors">
                                        <td className="px-6 py-3.5 text-slate-500 dark:text-slate-400 whitespace-nowrap">
                                            {new Date(log.created_at).toLocaleString()}
                                        </td>
                                        <td className="px-6 py-3.5">
                                            <span className="font-mono font-semibold text-slate-800 dark:text-slate-200 bg-slate-100 dark:bg-slate-800 border border-slate-200/60 dark:border-slate-700/60 px-2 py-0.5 rounded-md text-[11px]">
                                                {log.action}
                                            </span>
                                        </td>
                                        <td className="px-6 py-3.5 font-medium text-slate-900 dark:text-white">
                                            {log.user ? (
                                                <span>
                                                    {log.user.name}{' '}
                                                    <span className="text-slate-400 dark:text-slate-500 font-mono text-[10px]">
                                                        ({log.user.role})
                                                    </span>
                                                </span>
                                            ) : (
                                                <span className="text-slate-400 dark:text-slate-500 italic">System / Guest</span>
                                            )}
                                        </td>
                                        <td className="px-6 py-3.5 text-slate-600 dark:text-slate-300 font-mono text-[11px]">
                                            {log.ip_address ?? '—'}
                                        </td>
                                        <td className="px-6 py-3.5 text-slate-600 dark:text-slate-400 font-mono text-[10px] max-w-xs truncate">
                                            {log.new_values ? JSON.stringify(log.new_values) : '—'}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>

                {/* Maintenance Toggle Modal */}
                {maintenanceModalOpen && (
                    <div className="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 dark:bg-black/80 backdrop-blur-xs flex items-center justify-center p-4">
                        <div className="bg-white dark:bg-[#0F172A] rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-200/80 dark:border-slate-800 text-slate-900 dark:text-white">
                            <h2 className="text-base font-serif font-bold text-slate-900 dark:text-white mb-2">
                                {diagnostics.is_down_for_maintenance ? 'Disable Maintenance Mode' : 'Enable Maintenance Mode'}
                            </h2>

                            <p className="text-xs text-slate-600 dark:text-slate-300 mb-4">
                                {diagnostics.is_down_for_maintenance
                                    ? 'Disabling maintenance mode will make the storefront and portals immediately available to all customers and agents.'
                                    : 'Enabling maintenance mode will prevent customers and agents from accessing the storefront. Administrators can still access the site using the secret bypass cookie.'}
                            </p>

                            {!diagnostics.is_down_for_maintenance && (
                                <div className="mb-4">
                                    <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                        Bypass Secret Key
                                    </label>
                                    <input
                                        type="text"
                                        value={maintenanceForm.data.secret}
                                        onChange={(e) => maintenanceForm.setData('secret', e.target.value)}
                                        className="w-full text-xs rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white font-mono"
                                    />
                                    <p className="text-[10px] text-slate-400 dark:text-slate-500 mt-1">
                                        Navigate to <span className="font-mono">http://127.0.0.1:8000/{maintenanceForm.data.secret}</span> to bypass maintenance mode.
                                    </p>
                                </div>
                            )}

                            <div className="flex items-center justify-end gap-3 pt-3">
                                <button
                                    type="button"
                                    onClick={() => setMaintenanceModalOpen(false)}
                                    className="px-4 py-2 text-xs font-semibold rounded-xl border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors cursor-pointer"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="button"
                                    onClick={handleToggleMaintenance}
                                    disabled={maintenanceForm.processing}
                                    className={`px-5 py-2 text-xs font-semibold rounded-xl text-white transition-colors cursor-pointer ${
                                        diagnostics.is_down_for_maintenance
                                            ? 'bg-emerald-600 hover:bg-emerald-700'
                                            : 'bg-amber-600 hover:bg-amber-700'
                                    }`}
                                >
                                    {maintenanceForm.processing
                                        ? 'Toggling...'
                                        : diagnostics.is_down_for_maintenance
                                        ? 'Bring System Live'
                                        : 'Activate Maintenance'}
                                </button>
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </AdminLayout>
    );
}

