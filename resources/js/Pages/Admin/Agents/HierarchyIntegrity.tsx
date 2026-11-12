import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import * as Icons from '@/Components/Icons';
import { PageProps } from '@/types';

export interface HierarchyAnomaly {
    id: string;
    agent_id: number | null;
    agent_name: string | null;
    agent_role: string | null;
    referral_code: string | null;
    category: string;
    severity: 'CRITICAL' | 'HIGH' | 'MEDIUM' | 'LOW';
    title: string;
    description: string;
    expected: string;
    actual: string;
}

export interface HierarchyAuditReport {
    status: 'HEALTHY' | 'WARNING' | 'CRITICAL' | 'SCAN_INCOMPLETE';
    timestamp: string;
    execution_time_ms: number;
    memory_peak_bytes: number;
    query_count: number;
    total_agents: number;
    total_closures: number;
    total_anomalies: number;
    depth_limit_exceeded: boolean;
    counts_by_severity: {
        critical: number;
        high: number;
        medium: number;
        low: number;
    };
    counts_by_category: Record<string, number>;
    anomalies: HierarchyAnomaly[];
}

interface Props extends PageProps {
    initialReport: HierarchyAuditReport;
}

export default function HierarchyIntegrity({ initialReport }: Props) {
    const [report, setReport] = useState<HierarchyAuditReport>(initialReport);
    const [isScanning, setIsScanning] = useState(false);
    const [selectedCategory, setSelectedCategory] = useState<string>('all');
    const [selectedSeverity, setSelectedSeverity] = useState<string>('all');
    const [searchQuery, setSearchQuery] = useState('');

    const handleRunScan = () => {
        setIsScanning(true);
        router.post(
            '/admin/agents/hierarchy/integrity/scan',
            {},
            {
                preserveScroll: true,
                onSuccess: (page) => {
                    // Check if updated report was flashed in props
                    const updated = (page.props as unknown as { report?: HierarchyAuditReport })?.report;
                    if (updated) {
                        setReport(updated);
                    }
                    setIsScanning(false);
                },
                onError: () => {
                    setIsScanning(false);
                },
                onFinish: () => {
                    setIsScanning(false);
                },
            }
        );
    };

    // Filter anomalies by category, severity, and search
    const filteredAnomalies = report.anomalies.filter((anomaly) => {
        if (selectedCategory !== 'all' && anomaly.category !== selectedCategory) {
            return false;
        }
        if (selectedSeverity !== 'all' && anomaly.severity !== selectedSeverity) {
            return false;
        }
        if (searchQuery.trim()) {
            const query = searchQuery.toLowerCase();
            const matchName = anomaly.agent_name?.toLowerCase().includes(query) ?? false;
            const matchCode = anomaly.referral_code?.toLowerCase().includes(query) ?? false;
            const matchTitle = anomaly.title.toLowerCase().includes(query);
            const matchDesc = anomaly.description.toLowerCase().includes(query);
            if (!matchName && !matchCode && !matchTitle && !matchDesc) {
                return false;
            }
        }
        return true;
    });

    const getStatusTheme = (status: HierarchyAuditReport['status']) => {
        switch (status) {
            case 'HEALTHY':
                return {
                    bg: 'bg-emerald-50 dark:bg-emerald-950/40 border-emerald-200 dark:border-emerald-800/60',
                    text: 'text-emerald-800 dark:text-emerald-300',
                    pill: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 border-emerald-500/30',
                    label: 'Hierarchy Fully Consistent & Healthy',
                    icon: Icons.CheckCircle2,
                };
            case 'WARNING':
                return {
                    bg: 'bg-amber-50 dark:bg-amber-950/40 border-amber-200 dark:border-amber-800/60',
                    text: 'text-amber-800 dark:text-amber-300',
                    pill: 'bg-amber-500/10 text-amber-700 dark:text-amber-300 border-amber-500/30',
                    label: 'Hierarchy Warnings Detected',
                    icon: Icons.AlertCircle,
                };
            case 'CRITICAL':
                return {
                    bg: 'bg-rose-50 dark:bg-rose-950/40 border-rose-200 dark:border-rose-800/60',
                    text: 'text-rose-800 dark:text-rose-300',
                    pill: 'bg-rose-500/10 text-rose-700 dark:text-rose-300 border-rose-500/30',
                    label: 'Critical Hierarchy Corruptions Detected',
                    icon: Icons.ShieldAlert,
                };
            case 'SCAN_INCOMPLETE':
                return {
                    bg: 'bg-purple-50 dark:bg-purple-950/40 border-purple-200 dark:border-purple-800/60',
                    text: 'text-purple-800 dark:text-purple-300',
                    pill: 'bg-purple-500/10 text-purple-700 dark:text-purple-300 border-purple-500/30',
                    label: 'Scan Incomplete: Depth Safety Limit Exceeded',
                    icon: Icons.AlertCircle,
                };
        }
    };

    const getSeverityPill = (severity: HierarchyAnomaly['severity']) => {
        switch (severity) {
            case 'CRITICAL':
                return 'bg-rose-100 text-rose-700 border-rose-200 dark:bg-rose-900/40 dark:text-rose-300 dark:border-rose-800';
            case 'HIGH':
                return 'bg-amber-100 text-amber-700 border-amber-200 dark:bg-amber-900/40 dark:text-amber-300 dark:border-amber-800';
            case 'MEDIUM':
                return 'bg-blue-100 text-blue-700 border-blue-200 dark:bg-blue-900/40 dark:text-blue-300 dark:border-blue-800';
            case 'LOW':
                return 'bg-slate-100 text-slate-700 border-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700';
        }
    };

    const formatCategoryLabel = (category: string) => {
        switch (category) {
            case 'missing_self_closure':
                return 'Missing Self Closure';
            case 'missing_parent_closure':
                return 'Missing Parent Closure';
            case 'missing_ancestor_closure':
                return 'Missing Ancestor Closure';
            case 'unexpected_closure':
                return 'Unexpected Closure';
            case 'incorrect_closure_depth':
                return 'Depth Mismatch';
            case 'invalid_parent':
                return 'Invalid Parent';
            case 'hla_with_parent':
                return 'HLA Has Parent';
            case 'lla_missing_hla_root':
                return 'LLA Missing HLA Root';
            case 'inactive_sponsor':
                return 'Inactive Sponsor';
            case 'circular_hierarchy':
                return 'Cycle Detected';
            case 'orphan_agent':
                return 'Orphan Agent';
            case 'non_agent_closure':
                return 'Non-Agent Closure';
            case 'depth_limit_exceeded':
                return 'Depth Limit Exceeded';
            default:
                return category.replace(/_/g, ' ');
        }
    };

    const theme = getStatusTheme(report.status);
    const StatusIcon = theme.icon;

    return (
        <AdminLayout>
            <Head title="Hierarchy Integrity Auditor - Agent Network" />

            <div className="space-y-6">
                {/* Header Banner */}
                <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-gradient-to-r from-slate-900 via-slate-800 to-indigo-950 p-6 rounded-2xl border border-slate-700 shadow-xl text-white">
                    <div className="space-y-1">
                        <div className="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-amber-400">
                            <Icons.ShieldAlert className="w-4 h-4" />
                            <span>Executive Network Diagnostics</span>
                        </div>
                        <h1 className="text-2xl font-bold tracking-tight text-white flex items-center gap-3">
                            Hierarchy Integrity Auditor
                            <span className="text-xs px-2.5 py-0.5 rounded-full border border-amber-400/40 bg-amber-500/10 text-amber-300 font-medium">
                                Step 5 Active
                            </span>
                        </h1>
                        <p className="text-sm text-slate-300 max-w-2xl">
                            Mathematical verification of the agent hierarchy graph. Validates authoritative{' '}
                            <code className="text-amber-300 text-xs px-1 py-0.5 bg-black/40 rounded">users.parent_id</code>{' '}
                            against transitive <code className="text-amber-300 text-xs px-1 py-0.5 bg-black/40 rounded">user_closures</code> with zero database mutations.
                        </p>
                    </div>

                    <div className="flex items-center gap-3 shrink-0">
                        <Link
                            href="/admin/agents/hierarchy"
                            className="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-xl border border-slate-600 bg-slate-800/80 hover:bg-slate-700 text-slate-200 transition-colors shadow-sm"
                        >
                            <Icons.GitBranch className="w-4 h-4 text-slate-300" />
                            <span>Tree Explorer</span>
                        </Link>

                        <button
                            onClick={handleRunScan}
                            disabled={isScanning}
                            className="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold rounded-xl bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 text-slate-950 shadow-md transition-all active:scale-95 disabled:opacity-60"
                        >
                            <Icons.RefreshCw className={`w-4 h-4 ${isScanning ? 'animate-spin' : ''}`} />
                            <span>{isScanning ? 'Scanning Network...' : 'Run Full Integrity Scan'}</span>
                        </button>
                    </div>
                </div>

                {/* Overall Health Status Banner */}
                <div className={`p-5 rounded-2xl border ${theme.bg} shadow-sm transition-all`}>
                    <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div className="flex items-center gap-4">
                            <div className="p-3 rounded-xl bg-white/80 dark:bg-slate-900/80 shadow-sm border border-slate-200 dark:border-slate-800 shrink-0">
                                <StatusIcon className={`w-8 h-8 ${theme.text}`} />
                            </div>
                            <div>
                                <div className="flex items-center gap-2">
                                    <span className="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                        Overall Hierarchy Health
                                    </span>
                                    <span className={`text-xs px-2 py-0.5 rounded-full border font-bold uppercase ${theme.pill}`}>
                                        {report.status}
                                    </span>
                                </div>
                                <h2 className={`text-xl font-bold ${theme.text}`}>
                                    {theme.label}
                                </h2>
                                <p className="text-xs text-slate-600 dark:text-slate-400 mt-0.5">
                                    Scan completed at {new Date(report.timestamp).toLocaleTimeString()} ({new Date(report.timestamp).toLocaleDateString()})
                                </p>
                            </div>
                        </div>

                        <div className="flex items-center gap-2 sm:self-center">
                            <span className="text-xs text-slate-500 dark:text-slate-400 font-mono">
                                {report.execution_time_ms} ms &bull; {report.query_count} queries &bull; {(report.memory_peak_bytes / 1024).toFixed(1)} KB RAM
                            </span>
                        </div>
                    </div>
                </div>

                {/* Telemetry Metric Cards */}
                <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div className="bg-white dark:bg-slate-900 p-4 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm">
                        <div className="flex items-center justify-between text-slate-500 dark:text-slate-400 mb-1">
                            <span className="text-xs font-semibold uppercase tracking-wider">Agents Scanned</span>
                            <Icons.Users className="w-4 h-4 text-blue-500" />
                        </div>
                        <div className="text-2xl font-bold text-slate-900 dark:text-white">
                            {report.total_agents.toLocaleString()}
                        </div>
                        <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
                            HLA &amp; LLA network nodes
                        </p>
                    </div>

                    <div className="bg-white dark:bg-slate-900 p-4 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm">
                        <div className="flex items-center justify-between text-slate-500 dark:text-slate-400 mb-1">
                            <span className="text-xs font-semibold uppercase tracking-wider">Closure Records</span>
                            <Icons.GitBranch className="w-4 h-4 text-indigo-500" />
                        </div>
                        <div className="text-2xl font-bold text-slate-900 dark:text-white">
                            {report.total_closures.toLocaleString()}
                        </div>
                        <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
                            Transitive path entries verified
                        </p>
                    </div>

                    <div className="bg-white dark:bg-slate-900 p-4 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm">
                        <div className="flex items-center justify-between text-slate-500 dark:text-slate-400 mb-1">
                            <span className="text-xs font-semibold uppercase tracking-wider">Critical Issues</span>
                            <Icons.ShieldAlert className="w-4 h-4 text-rose-500" />
                        </div>
                        <div className={`text-2xl font-bold ${report.counts_by_severity.critical > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-slate-900 dark:text-white'}`}>
                            {report.counts_by_severity.critical}
                        </div>
                        <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
                            Cycles, invalid parents, HLA violations
                        </p>
                    </div>

                    <div className="bg-white dark:bg-slate-900 p-4 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm">
                        <div className="flex items-center justify-between text-slate-500 dark:text-slate-400 mb-1">
                            <span className="text-xs font-semibold uppercase tracking-wider">Total Anomalies</span>
                            <Icons.AlertCircle className="w-4 h-4 text-amber-500" />
                        </div>
                        <div className={`text-2xl font-bold ${report.total_anomalies > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400'}`}>
                            {report.total_anomalies}
                        </div>
                        <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
                            Across all 12 anomaly categories
                        </p>
                    </div>
                </div>

                {/* Filter and Search Bar */}
                <div className="bg-white dark:bg-slate-900 p-4 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div className="flex flex-wrap items-center gap-2">
                        <button
                            onClick={() => setSelectedSeverity('all')}
                            className={`px-3 py-1.5 text-xs font-medium rounded-lg border transition-all ${
                                selectedSeverity === 'all'
                                    ? 'bg-slate-900 text-white border-slate-900 dark:bg-white dark:text-slate-900 dark:border-white'
                                    : 'bg-slate-50 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700 hover:bg-slate-100'
                            }`}
                        >
                            All ({report.total_anomalies})
                        </button>
                        <button
                            onClick={() => setSelectedSeverity('CRITICAL')}
                            className={`px-3 py-1.5 text-xs font-medium rounded-lg border transition-all ${
                                selectedSeverity === 'CRITICAL'
                                    ? 'bg-rose-600 text-white border-rose-600'
                                    : 'bg-rose-50 dark:bg-rose-950/40 text-rose-700 dark:text-rose-300 border-rose-200 dark:border-rose-800'
                            }`}
                        >
                            Critical ({report.counts_by_severity.critical})
                        </button>
                        <button
                            onClick={() => setSelectedSeverity('HIGH')}
                            className={`px-3 py-1.5 text-xs font-medium rounded-lg border transition-all ${
                                selectedSeverity === 'HIGH'
                                    ? 'bg-amber-600 text-white border-amber-600'
                                    : 'bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-800'
                            }`}
                        >
                            High ({report.counts_by_severity.high})
                        </button>
                        <button
                            onClick={() => setSelectedSeverity('MEDIUM')}
                            className={`px-3 py-1.5 text-xs font-medium rounded-lg border transition-all ${
                                selectedSeverity === 'MEDIUM'
                                    ? 'bg-blue-600 text-white border-blue-600'
                                    : 'bg-blue-50 dark:bg-blue-950/40 text-blue-700 dark:text-blue-300 border-blue-200 dark:border-blue-800'
                            }`}
                        >
                            Medium ({report.counts_by_severity.medium})
                        </button>
                    </div>

                    <div className="flex items-center gap-3 w-full md:w-auto">
                        <div className="relative flex-1 md:w-64">
                            <Icons.Search className="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
                            <input
                                type="text"
                                placeholder="Search by agent name, code, title..."
                                value={searchQuery}
                                onChange={(e) => setSearchQuery(e.target.value)}
                                className="w-full pl-9 pr-3 py-1.5 text-xs bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-1 focus:ring-amber-500"
                            />
                        </div>

                        {Object.keys(report.counts_by_category).length > 0 && (
                            <select
                                value={selectedCategory}
                                onChange={(e) => setSelectedCategory(e.target.value)}
                                className="text-xs bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg px-2.5 py-1.5 text-slate-900 dark:text-white focus:outline-none focus:ring-1 focus:ring-amber-500"
                            >
                                <option value="all">All Categories</option>
                                {Object.keys(report.counts_by_category).map((cat) => (
                                    <option key={cat} value={cat}>
                                        {formatCategoryLabel(cat)} ({report.counts_by_category[cat]})
                                    </option>
                                ))}
                            </select>
                        )}
                    </div>
                </div>

                {/* Anomalies Table or Empty State */}
                <div className="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
                    {filteredAnomalies.length === 0 ? (
                        <div className="p-12 text-center space-y-4">
                            <div className="w-16 h-16 mx-auto rounded-full bg-emerald-100 dark:bg-emerald-950/60 border border-emerald-200 dark:border-emerald-800 flex items-center justify-center text-emerald-600 dark:text-emerald-400 shadow-sm">
                                <Icons.CheckCircle2 className="w-8 h-8" />
                            </div>
                            <div className="space-y-1">
                                <h3 className="text-lg font-bold text-slate-900 dark:text-white">
                                    No Hierarchy Anomalies Detected
                                </h3>
                                <p className="text-sm text-slate-500 dark:text-slate-400 max-w-md mx-auto">
                                    {report.total_anomalies === 0
                                        ? 'All 12 integrity checks passed cleanly. The authoritative adjacency model and transitive closure graph are fully synchronized.'
                                        : 'No anomalies matched your active filters or search keywords.'}
                                </p>
                            </div>
                            {report.total_anomalies > 0 && (
                                <button
                                    onClick={() => {
                                        setSelectedCategory('all');
                                        setSelectedSeverity('all');
                                        setSearchQuery('');
                                    }}
                                    className="text-xs text-amber-600 dark:text-amber-400 hover:underline font-semibold"
                                >
                                    Reset active filters
                                </button>
                            )}
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-left border-collapse">
                                <thead>
                                    <tr className="border-b border-slate-200 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/40 text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                        <th className="py-3 px-4">Severity</th>
                                        <th className="py-3 px-4">Category</th>
                                        <th className="py-3 px-4">Agent / Node</th>
                                        <th className="py-3 px-4">Issue Description</th>
                                        <th className="py-3 px-4">Expected vs Actual</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100 dark:divide-slate-800/60 text-xs text-slate-700 dark:text-slate-300 font-normal">
                                    {filteredAnomalies.map((anomaly) => (
                                        <tr
                                            key={anomaly.id}
                                            className="hover:bg-slate-50/80 dark:hover:bg-slate-800/30 transition-colors"
                                        >
                                            <td className="py-3.5 px-4 align-top">
                                                <span
                                                    className={`inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold border tracking-wider uppercase ${getSeverityPill(
                                                        anomaly.severity
                                                    )}`}
                                                >
                                                    {anomaly.severity}
                                                </span>
                                            </td>

                                            <td className="py-3.5 px-4 align-top">
                                                <span className="font-semibold text-slate-900 dark:text-white">
                                                    {formatCategoryLabel(anomaly.category)}
                                                </span>
                                            </td>

                                            <td className="py-3.5 px-4 align-top whitespace-nowrap">
                                                {anomaly.agent_id ? (
                                                    <div>
                                                        <div className="font-semibold text-slate-900 dark:text-white">
                                                            {anomaly.agent_name || `Agent #${anomaly.agent_id}`}
                                                        </div>
                                                        <div className="flex items-center gap-1.5 text-[11px] text-slate-500 font-mono mt-0.5">
                                                            <span>#{anomaly.agent_id}</span>
                                                            {anomaly.referral_code && (
                                                                <>
                                                                    <span>&bull;</span>
                                                                    <span className="text-amber-600 dark:text-amber-400 font-semibold">
                                                                        {anomaly.referral_code}
                                                                    </span>
                                                                </>
                                                            )}
                                                            {anomaly.agent_role && (
                                                                <>
                                                                    <span>&bull;</span>
                                                                    <span className="uppercase text-[10px]">
                                                                        {anomaly.agent_role === 'high_level_agent'
                                                                            ? 'HLA'
                                                                            : 'LLA'}
                                                                    </span>
                                                                </>
                                                            )}
                                                        </div>
                                                    </div>
                                                ) : (
                                                    <span className="text-slate-400 italic">Network-wide</span>
                                                )}
                                            </td>

                                            <td className="py-3.5 px-4 align-top max-w-md">
                                                <div className="font-semibold text-slate-900 dark:text-white mb-0.5">
                                                    {anomaly.title}
                                                </div>
                                                <p className="text-slate-600 dark:text-slate-400 leading-relaxed text-[11px]">
                                                    {anomaly.description}
                                                </p>
                                            </td>

                                            <td className="py-3.5 px-4 align-top max-w-xs space-y-1">
                                                <div className="text-[11px]">
                                                    <span className="text-emerald-700 dark:text-emerald-400 font-semibold">
                                                        Expected:{' '}
                                                    </span>
                                                    <span className="font-mono text-[10px] text-slate-700 dark:text-slate-300">
                                                        {anomaly.expected}
                                                    </span>
                                                </div>
                                                <div className="text-[11px]">
                                                    <span className="text-rose-700 dark:text-rose-400 font-semibold">
                                                        Actual:{' '}
                                                    </span>
                                                    <span className="font-mono text-[10px] text-slate-700 dark:text-slate-300">
                                                        {anomaly.actual}
                                                    </span>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>

                {/* Audit Safety & Privacy Notice */}
                <div className="bg-slate-50 dark:bg-slate-900/60 p-4 rounded-xl border border-slate-200 dark:border-slate-800 text-xs text-slate-500 dark:text-slate-400 flex items-start gap-3">
                    <Icons.ShieldCheck className="w-5 h-5 text-emerald-500 shrink-0 mt-0.5" />
                    <div className="space-y-0.5">
                        <span className="font-semibold text-slate-700 dark:text-slate-300">
                            Strict Read-Only Verification Guarantee
                        </span>
                        <p>
                            This diagnostic scan verifies closure graph completeness without mutating user accounts,
                            parent references, or closure rows. No National Identity Cards (NIC), bank accounts,
                            wallet balances, sales volumes, or wholesale costs are queried or presented.
                        </p>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}

