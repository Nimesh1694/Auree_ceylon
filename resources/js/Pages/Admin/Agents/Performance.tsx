import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import * as Icons from '@/Components/Icons';
import { PageProps } from '@/types';

export interface LeaderboardAgent {
    id: number;
    name: string;
    email: string;
    role: string;
    status: string;
    referral_code: string;
    total_orders: number;
    completed_orders: number;
    cancelled_orders: number;
    sales_volume: number;
    sales_volume_formatted: string;
    completion_rate: number;
    cancellation_rate: number;
    rank: number;
}

export interface PerformanceMetrics {
    total_agents: number;
    active_agents: number;
    total_network_sales: string;
    total_orders: number;
    total_completed_orders: number;
    avg_order_value: string;
}

interface Props extends PageProps {
    leaderboard: LeaderboardAgent[];
    metrics: PerformanceMetrics;
    filters: {
        period: string;
        role: string;
        search: string;
    };
}

export default function Performance({ leaderboard, metrics, filters }: Props) {
    const [period, setPeriod] = useState(filters.period || 'this_month');
    const [role, setRole] = useState(filters.role || 'all');
    const [search, setSearch] = useState(filters.search || '');

    const handleFilterChange = (newPeriod?: string, newRole?: string) => {
        const p = newPeriod !== undefined ? newPeriod : period;
        const r = newRole !== undefined ? newRole : role;
        router.get(
            '/admin/agents/performance',
            {
                period: p,
                role: r,
                search: search,
            },
            { preserveState: true, preserveScroll: true }
        );
    };

    const handleSearchSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(
            '/admin/agents/performance',
            { period, role, search },
            { preserveState: true, preserveScroll: true }
        );
    };

    const getRankBadge = (rank: number) => {
        if (rank === 1) {
            return (
                <div className="w-8 h-8 rounded-full bg-gradient-to-tr from-amber-500 to-yellow-300 text-slate-950 font-black text-xs flex items-center justify-center shadow-md border border-amber-200">
                    1st
                </div>
            );
        }
        if (rank === 2) {
            return (
                <div className="w-8 h-8 rounded-full bg-gradient-to-tr from-slate-400 to-slate-200 text-slate-900 font-bold text-xs flex items-center justify-center shadow-md border border-slate-300">
                    2nd
                </div>
            );
        }
        if (rank === 3) {
            return (
                <div className="w-8 h-8 rounded-full bg-gradient-to-tr from-amber-700 to-amber-600 text-white font-bold text-xs flex items-center justify-center shadow-md border border-amber-800">
                    3rd
                </div>
            );
        }
        return (
            <div className="w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 font-semibold text-xs flex items-center justify-center border border-slate-200 dark:border-slate-700">
                #{rank}
            </div>
        );
    };

    return (
        <AdminLayout>
            <Head title="Sales Leaderboard - Agent Network" />

            <div className="space-y-6">
                {/* Header Banner */}
                <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-gradient-to-r from-slate-900 via-slate-800 to-indigo-950 p-6 rounded-2xl border border-slate-700 shadow-xl text-white">
                    <div className="space-y-1">
                        <div className="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-amber-400">
                            <Icons.TrendingUp className="w-4 h-4" />
                            <span>Commercial Performance &amp; Analytics</span>
                        </div>
                        <h1 className="text-2xl font-bold tracking-tight text-white flex items-center gap-3">
                            Agent Sales Leaderboard
                            <span className="text-xs px-2.5 py-0.5 rounded-full border border-amber-400/40 bg-amber-500/10 text-amber-300 font-medium">
                                Confirmed Orders
                            </span>
                        </h1>
                        <p className="text-sm text-slate-300 max-w-2xl">
                            Rankings and commercial performance metrics across High-Level and Low-Level Agents based on completed customer deliveries and reconciled orders.
                        </p>
                    </div>

                    <div className="flex items-center gap-3 shrink-0">
                        <Link
                            href="/admin/agents/reports"
                            className="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-xl border border-slate-600 bg-slate-800/80 hover:bg-slate-700 text-slate-200 transition-colors shadow-sm"
                        >
                            <Icons.Download className="w-4 h-4 text-slate-300" />
                            <span>Export Center</span>
                        </Link>
                    </div>
                </div>

                {/* KPI Metric Cards */}
                <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div className="bg-white dark:bg-slate-900 p-4 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm">
                        <div className="flex items-center justify-between text-slate-500 dark:text-slate-400 mb-1">
                            <span className="text-xs font-semibold uppercase tracking-wider">Network Turnover</span>
                            <Icons.DollarSign className="w-4 h-4 text-emerald-500" />
                        </div>
                        <div className="text-2xl font-bold text-slate-900 dark:text-white">
                            Rs. {metrics.total_network_sales}
                        </div>
                        <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
                            From {metrics.total_completed_orders} completed deliveries
                        </p>
                    </div>

                    <div className="bg-white dark:bg-slate-900 p-4 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm">
                        <div className="flex items-center justify-between text-slate-500 dark:text-slate-400 mb-1">
                            <span className="text-xs font-semibold uppercase tracking-wider">Active Agents</span>
                            <Icons.Users className="w-4 h-4 text-blue-500" />
                        </div>
                        <div className="text-2xl font-bold text-slate-900 dark:text-white">
                            {metrics.active_agents} / {metrics.total_agents}
                        </div>
                        <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
                            HLA &amp; LLA workforce
                        </p>
                    </div>

                    <div className="bg-white dark:bg-slate-900 p-4 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm">
                        <div className="flex items-center justify-between text-slate-500 dark:text-slate-400 mb-1">
                            <span className="text-xs font-semibold uppercase tracking-wider">Total Orders</span>
                            <Icons.Package className="w-4 h-4 text-indigo-500" />
                        </div>
                        <div className="text-2xl font-bold text-slate-900 dark:text-white">
                            {metrics.total_orders}
                        </div>
                        <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
                            Placed via referral network
                        </p>
                    </div>

                    <div className="bg-white dark:bg-slate-900 p-4 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm">
                        <div className="flex items-center justify-between text-slate-500 dark:text-slate-400 mb-1">
                            <span className="text-xs font-semibold uppercase tracking-wider">Average Order</span>
                            <Icons.TrendingUp className="w-4 h-4 text-amber-500" />
                        </div>
                        <div className="text-2xl font-bold text-slate-900 dark:text-white">
                            Rs. {metrics.avg_order_value}
                        </div>
                        <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
                            Average basket value
                        </p>
                    </div>
                </div>

                {/* Filter & Period Controls */}
                <div className="bg-white dark:bg-slate-900 p-4 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div className="flex flex-wrap items-center gap-2">
                        <div className="flex items-center bg-slate-100 dark:bg-slate-800 p-1 rounded-xl">
                            <button
                                onClick={() => {
                                    setPeriod('this_month');
                                    handleFilterChange('this_month', undefined);
                                }}
                                className={`px-3 py-1.5 text-xs font-medium rounded-lg transition-all ${
                                    period === 'this_month'
                                        ? 'bg-white dark:bg-slate-900 text-slate-900 dark:text-white shadow-sm font-semibold'
                                        : 'text-slate-600 dark:text-slate-400 hover:text-slate-900'
                                }`}
                            >
                                This Month
                            </button>
                            <button
                                onClick={() => {
                                    setPeriod('last_month');
                                    handleFilterChange('last_month', undefined);
                                }}
                                className={`px-3 py-1.5 text-xs font-medium rounded-lg transition-all ${
                                    period === 'last_month'
                                        ? 'bg-white dark:bg-slate-900 text-slate-900 dark:text-white shadow-sm font-semibold'
                                        : 'text-slate-600 dark:text-slate-400 hover:text-slate-900'
                                }`}
                            >
                                Last Month
                            </button>
                            <button
                                onClick={() => {
                                    setPeriod('90_days');
                                    handleFilterChange('90_days', undefined);
                                }}
                                className={`px-3 py-1.5 text-xs font-medium rounded-lg transition-all ${
                                    period === '90_days'
                                        ? 'bg-white dark:bg-slate-900 text-slate-900 dark:text-white shadow-sm font-semibold'
                                        : 'text-slate-600 dark:text-slate-400 hover:text-slate-900'
                                }`}
                            >
                                90 Days
                            </button>
                            <button
                                onClick={() => {
                                    setPeriod('all_time');
                                    handleFilterChange('all_time', undefined);
                                }}
                                className={`px-3 py-1.5 text-xs font-medium rounded-lg transition-all ${
                                    period === 'all_time'
                                        ? 'bg-white dark:bg-slate-900 text-slate-900 dark:text-white shadow-sm font-semibold'
                                        : 'text-slate-600 dark:text-slate-400 hover:text-slate-900'
                                }`}
                            >
                                All Time
                            </button>
                        </div>

                        <select
                            value={role}
                            onChange={(e) => {
                                setRole(e.target.value);
                                handleFilterChange(undefined, e.target.value);
                            }}
                            className="text-xs bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg px-2.5 py-1.5 text-slate-900 dark:text-white focus:outline-none focus:ring-1 focus:ring-amber-500"
                        >
                            <option value="all">All Tiers (HLA &amp; LLA)</option>
                            <option value="high_level_agent">High-Level Agents (HLA)</option>
                            <option value="low_level_agent">Low-Level Agents (LLA)</option>
                        </select>
                    </div>

                    <form onSubmit={handleSearchSubmit} className="flex items-center gap-2">
                        <div className="relative">
                            <Icons.Search className="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
                            <input
                                type="text"
                                placeholder="Search agent or code..."
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                className="w-full md:w-56 pl-9 pr-3 py-1.5 text-xs bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-1 focus:ring-amber-500"
                            />
                        </div>
                        <button
                            type="submit"
                            className="px-3 py-1.5 text-xs font-semibold rounded-lg bg-slate-900 text-white dark:bg-white dark:text-slate-900"
                        >
                            Filter
                        </button>
                    </form>
                </div>

                {/* Leaderboard Table */}
                <div className="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
                    {leaderboard.length === 0 ? (
                        <div className="p-12 text-center space-y-3">
                            <Icons.TrendingUp className="w-12 h-12 text-slate-300 dark:text-slate-600 mx-auto" />
                            <h3 className="text-base font-bold text-slate-900 dark:text-white">
                                No Sales Activity Recorded
                            </h3>
                            <p className="text-xs text-slate-500 dark:text-slate-400 max-w-sm mx-auto">
                                No completed orders found for the selected period or filters.
                            </p>
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-left border-collapse">
                                <thead>
                                    <tr className="border-b border-slate-200 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/40 text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                        <th className="py-3 px-4 text-center w-16">Rank</th>
                                        <th className="py-3 px-4">Agent Profile</th>
                                        <th className="py-3 px-4">Tier &amp; Status</th>
                                        <th className="py-3 px-4 text-right">Completed Sales</th>
                                        <th className="py-3 px-4 text-center">Orders</th>
                                        <th className="py-3 px-4 text-right">Completion Rate</th>
                                        <th className="py-3 px-4 text-center w-20">Action</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100 dark:divide-slate-800/60 text-xs text-slate-700 dark:text-slate-300 font-normal">
                                    {leaderboard.map((item) => (
                                        <tr
                                            key={item.id}
                                            className="hover:bg-slate-50/80 dark:hover:bg-slate-800/30 transition-colors"
                                        >
                                            <td className="py-3.5 px-4 text-center">
                                                <div className="flex justify-center">
                                                    {getRankBadge(item.rank)}
                                                </div>
                                            </td>

                                            <td className="py-3.5 px-4">
                                                <div className="font-semibold text-slate-900 dark:text-white">
                                                    {item.name}
                                                </div>
                                                <div className="flex items-center gap-1.5 text-[11px] text-slate-500 font-mono mt-0.5">
                                                    <span className="text-amber-600 dark:text-amber-400 font-semibold">
                                                        {item.referral_code}
                                                    </span>
                                                    <span>&bull;</span>
                                                    <span>{item.email}</span>
                                                </div>
                                            </td>

                                            <td className="py-3.5 px-4">
                                                <div className="flex items-center gap-1.5">
                                                    <span
                                                        className={`text-[10px] font-bold px-2 py-0.5 rounded-full border uppercase tracking-wider ${
                                                            item.role === 'high_level_agent'
                                                                ? 'bg-amber-500/10 text-amber-700 dark:text-amber-300 border-amber-500/30'
                                                                : 'bg-blue-500/10 text-blue-700 dark:text-blue-300 border-blue-500/30'
                                                        }`}
                                                    >
                                                        {item.role === 'high_level_agent' ? 'HLA' : 'LLA'}
                                                    </span>
                                                    <span
                                                        className={`text-[10px] font-semibold px-1.5 py-0.5 rounded ${
                                                            item.status === 'active'
                                                                ? 'text-emerald-700 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-950/40'
                                                                : 'text-slate-500 bg-slate-100 dark:bg-slate-800'
                                                        }`}
                                                    >
                                                        {item.status}
                                                    </span>
                                                </div>
                                            </td>

                                            <td className="py-3.5 px-4 text-right font-mono font-bold text-slate-900 dark:text-white text-sm">
                                                Rs. {item.sales_volume_formatted}
                                            </td>

                                            <td className="py-3.5 px-4 text-center">
                                                <div className="font-semibold text-slate-900 dark:text-white">
                                                    {item.completed_orders} / {item.total_orders}
                                                </div>
                                                <div className="text-[10px] text-slate-400">
                                                    {item.cancelled_orders} returned
                                                </div>
                                            </td>

                                            <td className="py-3.5 px-4 text-right">
                                                <div className="flex items-center justify-end gap-2">
                                                    <div className="w-16 bg-slate-200 dark:bg-slate-700 h-1.5 rounded-full overflow-hidden">
                                                        <div
                                                            className="bg-emerald-500 h-full rounded-full"
                                                            style={{ width: `${Math.min(100, item.completion_rate)}%` }}
                                                        />
                                                    </div>
                                                    <span className="font-mono text-xs font-semibold text-emerald-600 dark:text-emerald-400">
                                                        {item.completion_rate}%
                                                    </span>
                                                </div>
                                            </td>

                                            <td className="py-3.5 px-4 text-center">
                                                <Link
                                                    href={`/admin/agents/${item.id}`}
                                                    className="inline-flex items-center gap-1 text-xs text-amber-600 dark:text-amber-400 hover:underline font-semibold"
                                                >
                                                    <span>View</span>
                                                    <Icons.ChevronRight className="w-3.5 h-3.5" />
                                                </Link>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            </div>
        </AdminLayout>
    );
}

