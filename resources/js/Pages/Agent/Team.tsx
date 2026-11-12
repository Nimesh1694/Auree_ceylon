import React, { useState, useMemo } from 'react';
import { Head } from '@inertiajs/react';
import AgentLayout from '@/Layouts/AgentLayout';
import { PageProps } from '@/types';
import {
    Users,
    GitBranch,
    Shield,
    Search,
    ChevronRight,
    ArrowUpCircle,
    UserCheck,
    CheckCircle2,
    Clock,
    Lock,
} from '@/Components/Icons';

interface UplineMember {
    id: number;
    name: string;
    referral_code: string;
    role: string;
    status: string;
}

interface DownlineMember {
    id: number;
    name: string;
    email: string;
    phone: string;
    referral_code: string;
    role: string;
    status: string;
    depth: number;
    joined_at?: string;
}

interface TeamProps extends PageProps {
    upline: UplineMember[];
    downline: DownlineMember[];
    directCount: number;
    totalCount: number;
}

export default function Team({ upline, downline, directCount, totalCount }: TeamProps) {
    const [search, setSearch] = useState('');
    const [selectedDepth, setSelectedDepth] = useState<string>('all');

    // Calculate maximum depth reached
    const maxDepth = useMemo(() => {
        if (!downline.length) return 0;
        return Math.max(...downline.map((d) => d.depth));
    }, [downline]);

    // Available depth options for filtering
    const depthOptions = useMemo(() => {
        const depths = Array.from(new Set(downline.map((d) => d.depth))).sort((a, b) => a - b);
        return depths;
    }, [downline]);

    // Filter downline based on search and selected depth
    const filteredDownline = useMemo(() => {
        return downline.filter((member) => {
            const matchesSearch =
                search === '' ||
                member.name.toLowerCase().includes(search.toLowerCase()) ||
                member.email.toLowerCase().includes(search.toLowerCase()) ||
                member.phone.includes(search) ||
                member.referral_code.toLowerCase().includes(search.toLowerCase());

            const matchesDepth =
                selectedDepth === 'all' || member.depth === parseInt(selectedDepth, 10);

            return matchesSearch && matchesDepth;
        });
    }, [downline, search, selectedDepth]);

    return (
        <AgentLayout>
            <Head title="My Downline Network" />

            <div className="max-w-6xl mx-auto space-y-6">
                {/* Header */}
                <div>
                    <h1 className="text-2xl font-bold text-slate-900 tracking-tight">My Downline Network</h1>
                    <p className="text-sm text-slate-500 mt-1">
                        View your authorized multi-level sales team, direct recruits, and upline sponsorship chain.
                    </p>
                </div>

                {/* Metrics Cards */}
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-5">
                    <div className="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm flex items-center gap-4">
                        <div className="p-3 bg-emerald-100 rounded-xl text-emerald-700">
                            <UserCheck className="w-6 h-6" />
                        </div>
                        <div>
                            <p className="text-xs font-semibold text-slate-500 uppercase">Direct Sponsored (Tier 1)</p>
                            <p className="text-2xl font-bold text-slate-900 mt-0.5">{directCount}</p>
                        </div>
                    </div>

                    <div className="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm flex items-center gap-4">
                        <div className="p-3 bg-blue-100 rounded-xl text-blue-700">
                            <Users className="w-6 h-6" />
                        </div>
                        <div>
                            <p className="text-xs font-semibold text-slate-500 uppercase">Total Downline Team</p>
                            <p className="text-2xl font-bold text-slate-900 mt-0.5">{totalCount}</p>
                        </div>
                    </div>

                    <div className="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm flex items-center gap-4">
                        <div className="p-3 bg-purple-100 rounded-xl text-purple-700">
                            <GitBranch className="w-6 h-6" />
                        </div>
                        <div>
                            <p className="text-xs font-semibold text-slate-500 uppercase">Max Network Depth</p>
                            <p className="text-2xl font-bold text-slate-900 mt-0.5">{maxDepth} {maxDepth === 1 ? 'Level' : 'Levels'}</p>
                        </div>
                    </div>
                </div>

                {/* Upline Sponsorship Chain */}
                <div className="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm">
                    <h2 className="text-sm font-semibold uppercase tracking-wider text-slate-600 mb-3 flex items-center gap-2">
                        <ArrowUpCircle className="w-4 h-4 text-emerald-600" />
                        Upline Sponsorship Hierarchy
                    </h2>
                    {upline.length === 0 ? (
                        <p className="text-sm text-slate-500 italic">
                            You are directly affiliated with the primary brand headquarters (Top of hierarchy).
                        </p>
                    ) : (
                        <div className="flex flex-wrap items-center gap-2 text-sm">
                            <span className="px-3 py-1 bg-emerald-600 text-white rounded-lg font-medium">You</span>
                            {upline.map((sponsor, idx) => (
                                <React.Fragment key={sponsor.id}>
                                    <ChevronRight className="w-4 h-4 text-slate-400" />
                                    <div className="px-3 py-1.5 bg-slate-100 border border-slate-200 rounded-lg flex items-center gap-2">
                                        <span className="font-semibold text-slate-800">{sponsor.name}</span>
                                        <span className="text-xs font-mono text-slate-500">({sponsor.referral_code})</span>
                                        <span className="text-[10px] px-1.5 py-0.5 bg-slate-200 text-slate-700 rounded font-semibold capitalize">
                                            {sponsor.role.replace(/_/g, ' ')}
                                        </span>
                                    </div>
                                </React.Fragment>
                            ))}
                        </div>
                    )}
                </div>

                {/* Privacy Boundary Banner */}
                <div className="p-4 bg-slate-50 border border-slate-200 rounded-xl flex items-start gap-3">
                    <Lock className="w-5 h-5 text-slate-500 flex-shrink-0 mt-0.5" />
                    <p className="text-xs text-slate-600 leading-relaxed">
                        <strong>Privacy & Data Protection Notice:</strong> In accordance with strict network privacy rules, you are authorized to view team contact information for coordination. Sensitive personal records (NIC numbers, banking details, and personal financial wallet balances) of downline agents are strictly confidential and protected from peer access.
                    </p>
                </div>

                {/* Downline Network Explorer */}
                <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                    <div className="p-5 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <h2 className="text-base font-semibold text-slate-900 flex items-center gap-2">
                            <Users className="w-5 h-5 text-emerald-600" />
                            Downline Agent Directory ({filteredDownline.length})
                        </h2>

                        <div className="flex flex-wrap items-center gap-3">
                            {/* Depth Selector */}
                            <select
                                value={selectedDepth}
                                onChange={(e) => setSelectedDepth(e.target.value)}
                                className="px-3 py-1.5 border border-slate-300 rounded-lg text-xs font-medium focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                            >
                                <option value="all">All Depth Levels</option>
                                {depthOptions.map((d) => (
                                    <option key={d} value={d.toString()}>
                                        Level {d} {d === 1 ? '(Direct)' : `(${d} tiers down)`}
                                    </option>
                                ))}
                            </select>

                            {/* Search */}
                            <div className="relative">
                                <Search className="w-4 h-4 text-slate-400 absolute left-3 top-2.5" />
                                <input
                                    type="text"
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    placeholder="Search by name, ref, phone..."
                                    className="pl-9 pr-3 py-1.5 border border-slate-300 rounded-lg text-xs w-56 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                />
                            </div>
                        </div>
                    </div>

                    {/* Table */}
                    {filteredDownline.length === 0 ? (
                        <div className="p-12 text-center">
                            <Users className="w-12 h-12 text-slate-300 mx-auto mb-3" />
                            <p className="text-base font-medium text-slate-700">No downline agents found</p>
                            <p className="text-xs text-slate-500 mt-1">
                                {search || selectedDepth !== 'all'
                                    ? 'Try adjusting your search query or depth filter.'
                                    : 'Share your personal referral link to invite partners and grow your multi-level beauty network.'}
                            </p>
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-sm text-slate-600">
                                <thead className="bg-slate-50 text-xs font-semibold text-slate-500 uppercase tracking-wider border-b border-slate-200">
                                    <tr>
                                        <th className="px-5 py-3">Network Tier</th>
                                        <th className="px-5 py-3">Agent</th>
                                        <th className="px-5 py-3">Referral Code</th>
                                        <th className="px-5 py-3">Role</th>
                                        <th className="px-5 py-3">Status</th>
                                        <th className="px-5 py-3">Joined</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {filteredDownline.map((member) => (
                                        <tr key={member.id} className="hover:bg-slate-50 transition-colors">
                                            <td className="px-5 py-4 whitespace-nowrap">
                                                <span
                                                    className={`inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold ${
                                                        member.depth === 1
                                                            ? 'bg-emerald-100 text-emerald-800 border border-emerald-200'
                                                            : member.depth === 2
                                                            ? 'bg-blue-100 text-blue-800 border border-blue-200'
                                                            : 'bg-purple-100 text-purple-800 border border-purple-200'
                                                    }`}
                                                >
                                                    Level {member.depth} {member.depth === 1 ? '• Direct' : ''}
                                                </span>
                                            </td>
                                            <td className="px-5 py-4">
                                                <div className="font-semibold text-slate-900">{member.name}</div>
                                                <div className="text-xs text-slate-500 font-mono mt-0.5">
                                                    {member.phone} • {member.email}
                                                </div>
                                            </td>
                                            <td className="px-5 py-4 font-mono font-semibold text-slate-800">
                                                {member.referral_code}
                                            </td>
                                            <td className="px-5 py-4 text-xs capitalize text-slate-700">
                                                {member.role.replace(/_/g, ' ')}
                                            </td>
                                            <td className="px-5 py-4">
                                                <span
                                                    className={`inline-block px-2 py-0.5 rounded-full text-xs font-semibold capitalize ${
                                                        member.status === 'active'
                                                            ? 'bg-emerald-100 text-emerald-700'
                                                            : 'bg-amber-100 text-amber-700'
                                                    }`}
                                                >
                                                    {member.status}
                                                </span>
                                            </td>
                                            <td className="px-5 py-4 text-xs text-slate-500 whitespace-nowrap">
                                                {member.joined_at
                                                    ? new Date(member.joined_at).toLocaleDateString()
                                                    : '—'}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            </div>
        </AgentLayout>
    );
}
