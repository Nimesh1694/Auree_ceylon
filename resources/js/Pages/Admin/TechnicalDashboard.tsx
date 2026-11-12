import * as Icons from '@/Components/Icons';
import AdminLayout from '@/Layouts/AdminLayout';
import { AuditLog } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import React, { useState } from 'react';

interface ActiveSessionItem {
    id: string;
    user_id: number | null;
    ip_address: string | null;
    user_agent: string | null;
    last_activity: number;
    user_name: string | null;
    user_email: string | null;
    user_role: string | null;
    protected_authority: string | null;
}

interface Props {
    staffMetrics: {
        total_staff: number;
        active_staff: number;
        suspended_staff: number;
        mfa_enforced_staff: number;
        mfa_compliance_rate: number;
    };
    sessionTelemetry: {
        active_sessions: number;
        total_sessions: number;
        recent_sessions: ActiveSessionItem[];
    };
    securityEvents: {
        critical_24h: number;
        recent_logs: AuditLog[];
    };
    runtime: {
        php_version: string;
        laravel_version: string;
        environment: string;
        database_connection: string;
        database_engine: string;
        cache_driver: string;
        queue_driver: string;
        session_driver: string;
        is_down_for_maintenance: boolean;
        server_time: string;
        opcache_enabled: boolean;
    };
    storageMetrics: {
        db_name: string;
        table_count: number;
        db_size_mb: number;
        storage_files_count: number;
        storage_size_mb: number;
        log_size_mb: number;
    };
    accessControl: {
        total_roles: number;
        custom_roles: number;
        total_permissions: number;
        pending_requests: number;
    };
    isOwner: boolean;
    isSuperAdmin: boolean;
}

export default function TechnicalDashboard({
    staffMetrics,
    sessionTelemetry,
    securityEvents,
    runtime,
    storageMetrics,
    accessControl,
    isOwner,
    isSuperAdmin,
}: Props) {
    const { post: postCache, processing: cacheClearing } = useForm();
    const [selectedLog, setSelectedLog] = useState<AuditLog | null>(null);

    const handleClearCache = () => {
        if (confirm('Clear application cache, config, routes, and compiled views now?')) {
            postCache('/admin/system/cache/clear');
        }
    };

    // Helper to parse human-readable browser / OS from user agent
    const parseUserAgent = (ua: string | null) => {
        if (!ua) return { browser: 'Unknown', os: 'Unknown' };
        let browser = 'Browser';
        let os = 'OS';

        if (ua.includes('Windows')) os = 'Windows';
        else if (ua.includes('Macintosh') || ua.includes('Mac OS')) os = 'macOS';
        else if (ua.includes('Linux')) os = 'Linux';
        else if (ua.includes('Android')) os = 'Android';
        else if (ua.includes('iPhone') || ua.includes('iPad')) os = 'iOS';

        if (ua.includes('Edg/')) browser = 'Edge';
        else if (ua.includes('Chrome/')) browser = 'Chrome';
        else if (ua.includes('Safari/') && !ua.includes('Chrome/')) browser = 'Safari';
        else if (ua.includes('Firefox/')) browser = 'Firefox';

        return { browser, os };
    };

    // Format epoch activity time
    const formatLastActive = (epoch: number) => {
        const diffSeconds = Math.max(0, Math.floor(Date.now() / 1000 - epoch));
        if (diffSeconds < 60) return `${diffSeconds}s ago`;
        const diffMins = Math.floor(diffSeconds / 60);
        if (diffMins < 60) return `${diffMins}m ago`;
        const diffHours = Math.floor(diffMins / 60);
        return `${diffHours}h ago`;
    };

    return (
        <AdminLayout>
            <Head title="Technical Console | System Administration" />

            <div className="space-y-6">
                {/* Header Banner */}
                <div className="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 bg-white dark:bg-[#0F172A] p-6 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-xs transition-colors duration-200">
                    <div>
                        <div className="flex items-center gap-3">
                            <span className="p-2.5 rounded-xl bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-500/20">
                                <Icons.LayoutDashboard className="w-6 h-6" />
                            </span>
                            <div>
                                <div className="flex items-center gap-2">
                                    <h1 className="text-xl font-bold text-slate-900 dark:text-white tracking-tight">
                                        Technical Administration Console
                                    </h1>
                                    <span className="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-500/20 uppercase tracking-wider">
                                        Engineering Portal
                                    </span>
                                </div>
                                <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
                                    Server runtime telemetry, session activity, 2FA MFA enforcement, infrastructure diagnostics, and security audit log.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div className="flex items-center gap-2.5 flex-wrap shrink-0">
                        {/* Switch View button if Owner */}
                        {isOwner && (
                            <Link
                                href="/admin/dashboard?view=commercial"
                                className="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-semibold bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 transition-colors"
                            >
                                <Icons.TrendingUp className="w-3.5 h-3.5 text-slate-500" />
                                <span>Commercial View</span>
                            </Link>
                        )}

                        {/* One-click Cache Clearing */}
                        <button
                            onClick={handleClearCache}
                            disabled={cacheClearing}
                            className="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-semibold bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 transition-colors cursor-pointer disabled:opacity-50"
                            title="Clear configuration, route, view, and application cache"
                        >
                            <Icons.RefreshCw className={`w-3.5 h-3.5 text-slate-500 ${cacheClearing ? 'animate-spin' : ''}`} />
                            <span>{cacheClearing ? 'Clearing...' : 'Clear App Cache'}</span>
                        </button>

                        {/* Diagnostics & Health Shortcut */}
                        <Link
                            href="/admin/system/health"
                            className="inline-flex items-center gap-1.5 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-semibold shadow-xs shadow-blue-500/20 hover:shadow-md hover:shadow-blue-500/30 transition-all cursor-pointer"
                        >
                            <Icons.Settings className="w-3.5 h-3.5 text-white" />
                            <span>System Diagnostics</span>
                        </Link>
                    </div>
                </div>

                {/* 4 Primary Telemetry Stat Cards */}
                <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    {/* Active Sessions */}
                    <div className="bg-white dark:bg-[#0F172A] p-5 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-2xs">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-medium text-slate-500 dark:text-slate-400">
                                Active Sessions
                            </span>
                            <span className="p-2 rounded-xl bg-blue-500/10 text-blue-600 dark:text-blue-400">
                                <Icons.Activity className="w-4 h-4" />
                            </span>
                        </div>
                        <div className="text-2xl font-bold text-slate-900 dark:text-white mt-3">
                            {sessionTelemetry.active_sessions}
                        </div>
                        <div className="flex items-center justify-between mt-2 pt-2 border-t border-slate-100 dark:border-slate-800/60 text-[11px] text-slate-400 dark:text-slate-500">
                            <span>Online (last 2h)</span>
                            <span className="font-mono">{sessionTelemetry.total_sessions} total in DB</span>
                        </div>
                    </div>

                    {/* Staff Accounts */}
                    <div className="bg-white dark:bg-[#0F172A] p-5 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-2xs">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-medium text-slate-500 dark:text-slate-400">
                                Staff Accounts
                            </span>
                            <span className="p-2 rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                                <Icons.Users className="w-4 h-4" />
                            </span>
                        </div>
                        <div className="text-2xl font-bold text-slate-900 dark:text-white mt-3">
                            {staffMetrics.total_staff}
                        </div>
                        <div className="flex items-center justify-between mt-2 pt-2 border-t border-slate-100 dark:border-slate-800/60 text-[11px]">
                            <span className="text-emerald-600 dark:text-emerald-400 font-medium">
                                {staffMetrics.active_staff} Active
                            </span>
                            <span className="text-rose-500 dark:text-rose-400 font-medium">
                                {staffMetrics.suspended_staff} Suspended
                            </span>
                        </div>
                    </div>

                    {/* MFA Compliance */}
                    <div className="bg-white dark:bg-[#0F172A] p-5 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-2xs">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-medium text-slate-500 dark:text-slate-400">
                                2FA Compliance Rate
                            </span>
                            <span className="p-2 rounded-xl bg-indigo-500/10 text-indigo-600 dark:text-indigo-400">
                                <Icons.ShieldCheck className="w-4 h-4" />
                            </span>
                        </div>
                        <div className="text-2xl font-bold text-slate-900 dark:text-white mt-3">
                            {staffMetrics.mfa_compliance_rate}%
                        </div>
                        <div className="mt-2 pt-2 border-t border-slate-100 dark:border-slate-800/60">
                            <div className="w-full bg-slate-100 dark:bg-slate-800 h-1.5 rounded-full overflow-hidden">
                                <div
                                    className="bg-blue-600 h-1.5 rounded-full transition-all duration-500"
                                    style={{ width: `${staffMetrics.mfa_compliance_rate}%` }}
                                />
                            </div>
                            <span className="text-[11px] text-slate-400 dark:text-slate-500 mt-1 block">
                                {staffMetrics.mfa_enforced_staff} of {staffMetrics.total_staff} staff enforced
                            </span>
                        </div>
                    </div>

                    {/* Security Events 24h */}
                    <div className="bg-white dark:bg-[#0F172A] p-5 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-2xs">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-medium text-slate-500 dark:text-slate-400">
                                Security Events (24h)
                            </span>
                            <span className="p-2 rounded-xl bg-amber-500/10 text-amber-600 dark:text-amber-400">
                                <Icons.History className="w-4 h-4" />
                            </span>
                        </div>
                        <div className="text-2xl font-bold text-slate-900 dark:text-white mt-3">
                            {securityEvents.critical_24h}
                        </div>
                        <div className="flex items-center justify-between mt-2 pt-2 border-t border-slate-100 dark:border-slate-800/60 text-[11px] text-slate-400 dark:text-slate-500">
                            <span>High-severity events</span>
                            <Link href="/admin/system/logs" className="text-blue-600 dark:text-blue-400 hover:underline">
                                View Logs →
                            </Link>
                        </div>
                    </div>
                </div>

                {/* 2-Column Split: Runtime Status & Storage Metrics */}
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    {/* Runtime Status */}
                    <div className="bg-white dark:bg-[#0F172A] rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-xs p-6 transition-colors">
                        <div className="flex items-center justify-between pb-4 border-b border-slate-200/80 dark:border-slate-800">
                            <div className="flex items-center gap-2">
                                <span className="p-2 rounded-xl bg-blue-500/10 text-blue-600 dark:text-blue-400">
                                    <Icons.Settings className="w-4 h-4" />
                                </span>
                                <h3 className="text-sm font-bold text-slate-900 dark:text-white">
                                    Runtime & Environment Diagnostics
                                </h3>
                            </div>
                            <span
                                className={`inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-bold ${
                                    runtime.is_down_for_maintenance
                                        ? 'bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20'
                                        : 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20'
                                }`}
                            >
                                <span
                                    className={`w-1.5 h-1.5 rounded-full ${
                                        runtime.is_down_for_maintenance ? 'bg-rose-500' : 'bg-emerald-500'
                                    }`}
                                />
                                {runtime.is_down_for_maintenance ? 'Maintenance Active' : 'Storefront Live'}
                            </span>
                        </div>

                        <div className="grid grid-cols-2 gap-4 mt-4 text-xs">
                            <div className="p-3 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200/60 dark:border-slate-800">
                                <span className="text-[11px] text-slate-400 dark:text-slate-500">PHP Version</span>
                                <div className="font-semibold text-slate-900 dark:text-white mt-1">
                                    {runtime.php_version}
                                </div>
                            </div>

                            <div className="p-3 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200/60 dark:border-slate-800">
                                <span className="text-[11px] text-slate-400 dark:text-slate-500">Laravel Framework</span>
                                <div className="font-semibold text-slate-900 dark:text-white mt-1">
                                    v{runtime.laravel_version} ({runtime.environment})
                                </div>
                            </div>

                            <div className="p-3 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200/60 dark:border-slate-800">
                                <span className="text-[11px] text-slate-400 dark:text-slate-500">Database Driver</span>
                                <div className="font-semibold text-slate-900 dark:text-white mt-1 font-mono">
                                    {runtime.database_engine} ({runtime.database_connection})
                                </div>
                            </div>

                            <div className="p-3 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200/60 dark:border-slate-800">
                                <span className="text-[11px] text-slate-400 dark:text-slate-500">Cache / Session</span>
                                <div className="font-semibold text-slate-900 dark:text-white mt-1 font-mono">
                                    {runtime.cache_driver} / {runtime.session_driver}
                                </div>
                            </div>
                        </div>

                        <div className="flex items-center justify-between mt-4 pt-3 border-t border-slate-100 dark:border-slate-800 text-[11px] text-slate-400 dark:text-slate-500">
                            <span>OPcache: {runtime.opcache_enabled ? 'Enabled' : 'Disabled'}</span>
                            <span className="font-mono">Server Time: {new Date(runtime.server_time).toLocaleTimeString()}</span>
                        </div>
                    </div>

                    {/* Storage & Database Metrics */}
                    <div className="bg-white dark:bg-[#0F172A] rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-xs p-6 transition-colors">
                        <div className="flex items-center justify-between pb-4 border-b border-slate-200/80 dark:border-slate-800">
                            <div className="flex items-center gap-2">
                                <span className="p-2 rounded-xl bg-blue-500/10 text-blue-600 dark:text-blue-400">
                                    <Icons.Boxes className="w-4 h-4" />
                                </span>
                                <h3 className="text-sm font-bold text-slate-900 dark:text-white">
                                    Storage & Database Telemetry
                                </h3>
                            </div>
                            <span className="text-xs text-slate-400 font-mono">
                                {storageMetrics.db_name}
                            </span>
                        </div>

                        <div className="grid grid-cols-3 gap-3 mt-4 text-xs">
                            <div className="p-3 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200/60 dark:border-slate-800 text-center">
                                <span className="text-[11px] text-slate-400 dark:text-slate-500">Database Size</span>
                                <div className="text-base font-bold text-slate-900 dark:text-white mt-1">
                                    {storageMetrics.db_size_mb} MB
                                </div>
                                <span className="text-[10px] text-slate-400 block mt-0.5">
                                    {storageMetrics.table_count} tables
                                </span>
                            </div>

                            <div className="p-3 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200/60 dark:border-slate-800 text-center">
                                <span className="text-[11px] text-slate-400 dark:text-slate-500">Uploads Storage</span>
                                <div className="text-base font-bold text-slate-900 dark:text-white mt-1">
                                    {storageMetrics.storage_size_mb} MB
                                </div>
                                <span className="text-[10px] text-slate-400 block mt-0.5">
                                    {storageMetrics.storage_files_count} files
                                </span>
                            </div>

                            <div className="p-3 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200/60 dark:border-slate-800 text-center">
                                <span className="text-[11px] text-slate-400 dark:text-slate-500">Log Footprint</span>
                                <div className="text-base font-bold text-slate-900 dark:text-white mt-1">
                                    {storageMetrics.log_size_mb} MB
                                </div>
                                <span className="text-[10px] text-slate-400 block mt-0.5">
                                    laravel.log
                                </span>
                            </div>
                        </div>

                        <div className="flex items-center justify-between mt-4 pt-3 border-t border-slate-100 dark:border-slate-800 text-[11px] text-slate-400 dark:text-slate-500">
                            <span>Access Control: {accessControl.total_roles} Roles ({accessControl.custom_roles} Custom)</span>
                            <span>{accessControl.total_permissions} Granular Permissions</span>
                        </div>
                    </div>
                </div>

                {/* Active Sessions Telemetry Table */}
                <div className="bg-white dark:bg-[#0F172A] rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-xs overflow-hidden transition-colors duration-200">
                    <div className="px-6 py-4 border-b border-slate-200/80 dark:border-slate-800 flex items-center justify-between">
                        <div className="flex items-center gap-2">
                            <span className="p-2 rounded-xl bg-blue-500/10 text-blue-600 dark:text-blue-400">
                                <Icons.Activity className="w-4 h-4" />
                            </span>
                            <div>
                                <h3 className="text-sm font-bold text-slate-900 dark:text-white">
                                    Active Sessions & Authentication Activity
                                </h3>
                                <p className="text-[11px] text-slate-400">
                                    Recent active sessions stored in database
                                </p>
                            </div>
                        </div>
                        <span className="text-xs text-slate-400">
                            Showing latest {sessionTelemetry.recent_sessions.length}
                        </span>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-slate-200 dark:divide-slate-800 text-xs">
                            <thead className="bg-slate-50/80 dark:bg-slate-900/60 text-slate-600 dark:text-slate-400 uppercase tracking-wider font-semibold">
                                <tr>
                                    <th className="px-6 py-3 text-left">User / Account</th>
                                    <th className="px-6 py-3 text-left">Role & Authority</th>
                                    <th className="px-6 py-3 text-left">IP Address</th>
                                    <th className="px-6 py-3 text-left">Browser & Platform</th>
                                    <th className="px-6 py-3 text-right">Last Active</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 dark:divide-slate-800/60">
                                {sessionTelemetry.recent_sessions.length > 0 ? (
                                    sessionTelemetry.recent_sessions.map((sess) => {
                                        const { browser, os } = parseUserAgent(sess.user_agent);
                                        const isOwnerSession = sess.user_role === 'owner' || sess.protected_authority === 'business';
                                        const isSuperAdminSession = sess.user_role === 'super_admin' || sess.protected_authority === 'technical';

                                        return (
                                            <tr
                                                key={sess.id}
                                                className="hover:bg-slate-50/60 dark:hover:bg-slate-800/40 transition-colors"
                                            >
                                                <td className="px-6 py-3.5">
                                                    <div className="font-semibold text-slate-900 dark:text-white">
                                                        {sess.user_name ?? 'Guest / Storefront'}
                                                    </div>
                                                    <div className="text-[11px] text-slate-400">
                                                        {sess.user_email ?? 'Unauthenticated'}
                                                    </div>
                                                </td>

                                                <td className="px-6 py-3.5">
                                                    {isOwnerSession ? (
                                                        <span className="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/30">
                                                            👑 Owner
                                                        </span>
                                                    ) : isSuperAdminSession ? (
                                                        <span className="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-500/30">
                                                            ⚡ Super Admin
                                                        </span>
                                                    ) : (
                                                        <span className="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
                                                            {sess.user_role ? String(sess.user_role).replace('_', ' ') : 'Guest'}
                                                        </span>
                                                    )}
                                                </td>

                                                <td className="px-6 py-3.5 font-mono text-slate-600 dark:text-slate-300 text-[11px]">
                                                    {sess.ip_address ?? '127.0.0.1'}
                                                </td>

                                                <td className="px-6 py-3.5 text-slate-600 dark:text-slate-300">
                                                    <div className="font-medium">{browser} on {os}</div>
                                                    <div className="text-[10px] text-slate-400 max-w-xs truncate" title={sess.user_agent ?? ''}>
                                                        {sess.user_agent ?? 'Standard Web Client'}
                                                    </div>
                                                </td>

                                                <td className="px-6 py-3.5 text-right font-mono text-slate-600 dark:text-slate-300 text-[11px]">
                                                    {formatLastActive(sess.last_activity)}
                                                </td>
                                            </tr>
                                        );
                                    })
                                ) : (
                                    <tr>
                                        <td colSpan={5} className="px-6 py-8 text-center text-slate-400 text-xs">
                                            No active session records found.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>

                {/* Recent Security Audit Events Table */}
                <div className="bg-white dark:bg-[#0F172A] rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-xs overflow-hidden transition-colors duration-200">
                    <div className="px-6 py-4 border-b border-slate-200/80 dark:border-slate-800 flex items-center justify-between">
                        <div className="flex items-center gap-2">
                            <span className="p-2 rounded-xl bg-blue-500/10 text-blue-600 dark:text-blue-400">
                                <Icons.History className="w-4 h-4" />
                            </span>
                            <div>
                                <h3 className="text-sm font-bold text-slate-900 dark:text-white">
                                    Recent Security & Administrative Actions
                                </h3>
                                <p className="text-[11px] text-slate-400">
                                    Immutable audit trail records
                                </p>
                            </div>
                        </div>
                        <Link
                            href="/admin/system/logs"
                            className="text-xs font-semibold text-blue-600 dark:text-blue-400 hover:underline inline-flex items-center gap-1"
                        >
                            <span>Open Full Audit Trail</span>
                            <Icons.ChevronRight className="w-3.5 h-3.5" />
                        </Link>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-slate-200 dark:divide-slate-800 text-xs">
                            <thead className="bg-slate-50/80 dark:bg-slate-900/60 text-slate-600 dark:text-slate-400 uppercase tracking-wider font-semibold">
                                <tr>
                                    <th className="px-6 py-3 text-left">Action</th>
                                    <th className="px-6 py-3 text-left">Actor</th>
                                    <th className="px-6 py-3 text-left">Target Entity</th>
                                    <th className="px-6 py-3 text-left">IP Address</th>
                                    <th className="px-6 py-3 text-right">Timestamp</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 dark:divide-slate-800/60">
                                {securityEvents.recent_logs.length > 0 ? (
                                    securityEvents.recent_logs.map((log) => (
                                        <tr
                                            key={log.id}
                                            onClick={() => (log.old_values || log.new_values) && setSelectedLog(log)}
                                            className={`hover:bg-slate-50/60 dark:hover:bg-slate-800/40 transition-colors ${
                                                log.old_values || log.new_values ? 'cursor-pointer' : ''
                                            }`}
                                        >
                                            <td className="px-6 py-3.5">
                                                <span className="font-mono text-[11px] font-semibold text-blue-600 dark:text-blue-400 bg-blue-500/10 px-2 py-0.5 rounded-md border border-blue-500/20">
                                                    {log.action}
                                                </span>
                                            </td>

                                            <td className="px-6 py-3.5">
                                                <div className="font-semibold text-slate-900 dark:text-white">
                                                    {log.user?.name ?? 'System'}
                                                </div>
                                                <div className="text-[10px] text-slate-400">
                                                    {log.user?.email}
                                                </div>
                                            </td>

                                            <td className="px-6 py-3.5 font-mono text-[11px] text-slate-600 dark:text-slate-400">
                                                {log.auditable_type ? log.auditable_type.split('\\').pop() : 'System'}
                                                {log.auditable_id && ` #${log.auditable_id}`}
                                            </td>

                                            <td className="px-6 py-3.5 font-mono text-[11px] text-slate-500">
                                                {log.ip_address ?? '127.0.0.1'}
                                            </td>

                                            <td className="px-6 py-3.5 text-right text-slate-500 text-[11px]">
                                                {new Date(log.created_at).toLocaleString()}
                                            </td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td colSpan={5} className="px-6 py-8 text-center text-slate-400 text-xs">
                                            No audit events recorded yet.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>

                {/* Inspect JSON Modal */}
                {selectedLog && (
                    <div className="fixed inset-0 z-50 overflow-y-auto bg-slate-900/70 backdrop-blur-xs flex items-center justify-center p-4">
                        <div className="bg-white dark:bg-[#0F172A] rounded-2xl max-w-2xl w-full p-6 shadow-2xl border border-slate-200 dark:border-slate-800 transition-colors">
                            <div className="flex items-center justify-between pb-3 border-b border-slate-200 dark:border-slate-800 mb-4">
                                <h3 className="text-base font-bold text-slate-900 dark:text-white">
                                    Audit Record Diff: {selectedLog.action}
                                </h3>
                                <button
                                    onClick={() => setSelectedLog(null)}
                                    className="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 text-lg font-bold"
                                >
                                    ✕
                                </button>
                            </div>

                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs font-mono">
                                <div>
                                    <div className="text-slate-500 font-semibold mb-1">Old Values</div>
                                    <pre className="p-3 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 overflow-x-auto text-[11px] text-slate-800 dark:text-slate-200 max-h-60">
                                        {selectedLog.old_values ? JSON.stringify(selectedLog.old_values, null, 2) : 'None'}
                                    </pre>
                                </div>
                                <div>
                                    <div className="text-slate-500 font-semibold mb-1">New Values</div>
                                    <pre className="p-3 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 overflow-x-auto text-[11px] text-slate-800 dark:text-slate-200 max-h-60">
                                        {selectedLog.new_values ? JSON.stringify(selectedLog.new_values, null, 2) : 'None'}
                                    </pre>
                                </div>
                            </div>

                            <div className="mt-4 pt-3 border-t border-slate-200 dark:border-slate-800 flex justify-end">
                                <button
                                    onClick={() => setSelectedLog(null)}
                                    className="px-4 py-2 rounded-xl text-xs font-semibold bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300"
                                >
                                    Close
                                </button>
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </AdminLayout>
    );
}

