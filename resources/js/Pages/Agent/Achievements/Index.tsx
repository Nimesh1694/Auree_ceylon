import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AgentLayout from '@/Layouts/AgentLayout';
import {
    AchievementAwardDTO,
    AchievementCatalogItemDTO,
    AgentAchievementsPageProps,
} from '@/types/achievements';
import {
    AchievementBadgeCard,
    AchievementDetailModal,
    AchievementProgressMeter,
    AchievementStatusBadge,
    StreakFlameBanner,
} from '@/Components/Agent/Achievements';

export default function AgentAchievementsIndex({
    agent,
    awards,
    catalog,
    streak,
    progress,
    history,
    filters,
    is_hla,
    downline,
}: AgentAchievementsPageProps) {
    const [activeTab, setActiveTab] = useState<string>(filters.tab || 'showcase');
    const [selectedAward, setSelectedAward] = useState<
        AchievementAwardDTO | AchievementCatalogItemDTO | null
    >(null);
    const [isModalOpen, setIsModalOpen] = useState(false);

    // Derived statistics
    const activeUnlockedCount = awards.filter((a) => a.status === 'active').length;
    const underReviewCount = awards.filter((a) => a.status === 'corrected').length;

    // Handle Tab Navigation with URL State
    const handleTabChange = (tabKey: string) => {
        setActiveTab(tabKey);
        router.get(
            '/agent/achievements',
            {
                tab: tabKey,
                status: tabKey === 'history' ? filters.status : undefined,
                category: tabKey === 'history' ? filters.category : undefined,
            },
            { preserveState: true, preserveScroll: true, replace: true }
        );
    };

    // Handle History Filtering
    const handleStatusFilter = (statusValue: string | null) => {
        router.get(
            '/agent/achievements',
            {
                tab: 'history',
                status: statusValue || undefined,
                category: filters.category || undefined,
            },
            { preserveState: true, preserveScroll: true }
        );
    };

    // Handle History Category Filter
    const handleCategoryFilter = (categoryValue: string | null) => {
        router.get(
            '/agent/achievements',
            {
                tab: 'history',
                status: filters.status || undefined,
                category: categoryValue || undefined,
            },
            { preserveState: true, preserveScroll: true }
        );
    };

    // Inspect Details
    const handleInspect = (item: AchievementAwardDTO | AchievementCatalogItemDTO) => {
        setSelectedAward(item);
        setIsModalOpen(true);
    };

    // Separate catalog into automated milestones (7) vs recognition honors (5)
    const automatedCodes = [
        'SALES_FIRST_ORDER',
        'SALES_100K_CUMULATIVE',
        'SALES_500K_CUMULATIVE',
        'SALES_1M_CUMULATIVE',
        'TARGET_MET_MONTHLY',
        'TARGET_OVERACHIEVE_125',
        'TARGET_STREAK_3M',
    ];

    const automatedCatalog = catalog.filter((c) => automatedCodes.includes(c.code));
    const recognitionCatalog = catalog.filter((c) => !automatedCodes.includes(c.code));

    return (
        <AgentLayout>
            <Head title="Achievements & Honors | Aura Luxe" />

            <div className="space-y-8">
                {/* 1. Header & Identity Ribbon */}
                <div className="flex flex-col gap-4 rounded-2xl border border-stone-200 bg-white p-6 shadow-xs sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div className="flex items-center gap-2">
                            <span className="text-[11px] font-semibold uppercase tracking-wider text-rose-800">
                                Aura Luxe Recognition Portal
                            </span>
                            {agent.is_hla && (
                                <span className="rounded bg-amber-100 px-2 py-0.5 font-mono text-[10px] font-bold uppercase text-amber-800">
                                    High-Level Agent
                                </span>
                            )}
                        </div>
                        <h1 className="mt-1 font-serif text-2xl font-semibold text-stone-900 sm:text-3xl">
                            Achievements & Honors
                        </h1>
                        <p className="mt-1 text-xs text-stone-500">
                            Celebrate milestone progress, target consistency, and network honors
                        </p>
                    </div>

                    <div className="flex items-center gap-4 text-xs font-mono">
                        <div className="rounded-xl border border-stone-200 bg-stone-50 px-4 py-2 text-center">
                            <span className="block text-[10px] uppercase text-stone-500">Unlocked Badges</span>
                            <span className="text-xl font-bold text-stone-900">{activeUnlockedCount}</span>
                        </div>
                        {underReviewCount > 0 && (
                            <div className="rounded-xl border border-amber-300 bg-amber-50 px-4 py-2 text-center">
                                <span className="block text-[10px] uppercase text-amber-700">Under Review</span>
                                <span className="text-xl font-bold text-amber-800">{underReviewCount}</span>
                            </div>
                        )}
                    </div>
                </div>

                {/* 2. Target Consistency Streak Banner */}
                <StreakFlameBanner streak={streak} />

                {/* 3. Navigation Tabs */}
                <div className="border-b border-stone-200">
                    <nav className="-mb-px flex space-x-6 overflow-x-auto text-xs font-medium tracking-wide">
                        <button
                            type="button"
                            onClick={() => handleTabChange('showcase')}
                            className={`border-b-2 py-3.5 whitespace-nowrap transition-colors cursor-pointer ${
                                activeTab === 'showcase'
                                    ? 'border-stone-900 text-stone-900 font-semibold'
                                    : 'border-transparent text-stone-500 hover:border-stone-400 hover:text-stone-700'
                            }`}
                        >
                            🎖️ Showcase ({awards.length})
                        </button>
                        <button
                            type="button"
                            onClick={() => handleTabChange('catalog')}
                            className={`border-b-2 py-3.5 whitespace-nowrap transition-colors cursor-pointer ${
                                activeTab === 'catalog'
                                    ? 'border-stone-900 text-stone-900 font-semibold'
                                    : 'border-transparent text-stone-500 hover:border-stone-400 hover:text-stone-700'
                            }`}
                        >
                            📋 Catalog & Progress (12)
                        </button>
                        <button
                            type="button"
                            onClick={() => handleTabChange('history')}
                            className={`border-b-2 py-3.5 whitespace-nowrap transition-colors cursor-pointer ${
                                activeTab === 'history'
                                    ? 'border-stone-900 text-stone-900 font-semibold'
                                    : 'border-transparent text-stone-500 hover:border-stone-400 hover:text-stone-700'
                            }`}
                        >
                            📜 History ({history.total})
                        </button>
                        {is_hla && (
                            <button
                                type="button"
                                onClick={() => handleTabChange('downline')}
                                className={`border-b-2 py-3.5 whitespace-nowrap transition-colors cursor-pointer ${
                                    activeTab === 'downline'
                                        ? 'border-rose-900 text-rose-900 font-semibold'
                                        : 'border-transparent text-stone-500 hover:border-stone-400 hover:text-stone-700'
                                }`}
                            >
                                👥 Team Honors ({downline?.total_downline_count ?? 0})
                            </button>
                        )}
                    </nav>
                </div>

                {/* 4. Tab Content Panels */}

                {/* TAB 1: SHOWCASE */}
                {activeTab === 'showcase' && (
                    <div className="space-y-6">
                        {awards.length === 0 ? (
                            <div className="rounded-2xl border border-stone-200 bg-white p-12 text-center shadow-xs">
                                <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-stone-100 text-3xl">
                                    🎖️
                                </div>
                                <h3 className="mt-4 font-serif text-lg font-semibold text-stone-900">
                                    Begin Your Recognition Journey
                                </h3>
                                <p className="mx-auto mt-2 max-w-md text-xs text-stone-500">
                                    You have not unlocked any achievement badges yet. Complete sales milestones and
                                    hit monthly targets to earn prestigious brand honors!
                                </p>
                                <button
                                    type="button"
                                    onClick={() => handleTabChange('catalog')}
                                    className="mt-6 rounded-xl bg-stone-900 px-4 py-2 text-xs font-semibold text-white hover:bg-stone-800 transition-colors"
                                >
                                    Explore Available Milestones &rarr;
                                </button>
                            </div>
                        ) : (
                            <div className="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                                {awards.map((award) => (
                                    <AchievementBadgeCard
                                        key={award.id}
                                        item={award}
                                        onInspect={handleInspect}
                                    />
                                ))}
                            </div>
                        )}
                    </div>
                )}

                {/* TAB 2: CATALOG & PROGRESS */}
                {activeTab === 'catalog' && (
                    <div className="space-y-10">
                        {/* Section A: Automated Milestones */}
                        <div className="space-y-4">
                            <div>
                                <h2 className="font-serif text-lg font-semibold text-stone-900">
                                    Automated Performance Milestones
                                </h2>
                                <p className="text-xs text-stone-500">
                                    Track continuous progress computed automatically from commercial sales and monthly target attainment
                                </p>
                            </div>

                            <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                                {automatedCatalog.map((item) => (
                                    <div
                                        key={item.id}
                                        className="flex flex-col justify-between gap-4 rounded-xl border border-stone-200 bg-white p-5 shadow-xs"
                                    >
                                        <AchievementBadgeCard
                                            item={item}
                                            isCatalog
                                            onInspect={handleInspect}
                                        />
                                        {progress[item.code] && (
                                            <AchievementProgressMeter
                                                progress={progress[item.code]}
                                                tier={item.badge_tier}
                                            />
                                        )}
                                    </div>
                                ))}
                            </div>
                        </div>

                        {/* Section B: Special & Criteria Honors */}
                        <div className="space-y-4">
                            <div>
                                <h2 className="font-serif text-lg font-semibold text-stone-900">
                                    Leadership, Team & Convention Honors
                                </h2>
                                <p className="text-xs text-stone-500">
                                    Conferred through recruitment thresholds, annual convention excellence, and discretionary leadership nominations
                                </p>
                            </div>

                            <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                                {recognitionCatalog.map((item) => (
                                    <div
                                        key={item.id}
                                        className="flex flex-col justify-between gap-4 rounded-xl border border-stone-200 bg-white p-5 shadow-xs"
                                    >
                                        <AchievementBadgeCard
                                            item={item}
                                            isCatalog
                                            onInspect={handleInspect}
                                        />
                                        {progress[item.code] && (
                                            <AchievementProgressMeter
                                                progress={progress[item.code]}
                                                tier={item.badge_tier}
                                            />
                                        )}
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>
                )}

                {/* TAB 3: HISTORY */}
                {activeTab === 'history' && (
                    <div className="space-y-4">
                        {/* Filters Bar */}
                        <div className="flex flex-col gap-3 rounded-xl border border-stone-200 bg-white p-4 sm:flex-row sm:items-center sm:justify-between">
                            <div className="flex items-center gap-2 overflow-x-auto text-xs">
                                <span className="font-semibold text-stone-500">Status:</span>
                                <button
                                    type="button"
                                    onClick={() => handleStatusFilter(null)}
                                    className={`rounded-lg px-2.5 py-1 font-medium transition-colors cursor-pointer ${
                                        !filters.status
                                            ? 'bg-stone-900 text-white'
                                            : 'bg-stone-100 text-stone-700 hover:bg-stone-200'
                                    }`}
                                >
                                    All
                                </button>
                                <button
                                    type="button"
                                    onClick={() => handleStatusFilter('active')}
                                    className={`rounded-lg px-2.5 py-1 font-medium transition-colors cursor-pointer ${
                                        filters.status === 'active'
                                            ? 'bg-emerald-600 text-white'
                                            : 'bg-stone-100 text-stone-700 hover:bg-stone-200'
                                    }`}
                                >
                                    Active
                                </button>
                                <button
                                    type="button"
                                    onClick={() => handleStatusFilter('corrected')}
                                    className={`rounded-lg px-2.5 py-1 font-medium transition-colors cursor-pointer ${
                                        filters.status === 'corrected'
                                            ? 'bg-amber-600 text-white'
                                            : 'bg-stone-100 text-stone-700 hover:bg-stone-200'
                                    }`}
                                >
                                    Under Review
                                </button>
                                <button
                                    type="button"
                                    onClick={() => handleStatusFilter('revoked')}
                                    className={`rounded-lg px-2.5 py-1 font-medium transition-colors cursor-pointer ${
                                        filters.status === 'revoked'
                                            ? 'bg-rose-600 text-white'
                                            : 'bg-stone-100 text-stone-700 hover:bg-stone-200'
                                    }`}
                                >
                                    Revoked
                                </button>
                            </div>

                            <div className="flex items-center gap-2 text-xs">
                                <span className="font-semibold text-stone-500">Category:</span>
                                <select
                                    value={filters.category || ''}
                                    onChange={(e) => handleCategoryFilter(e.target.value || null)}
                                    className="rounded-lg border-stone-200 bg-stone-50 py-1 text-xs text-stone-700 focus:border-stone-900 focus:ring-stone-900"
                                >
                                    <option value="">All Categories</option>
                                    <option value="sales">Sales</option>
                                    <option value="recruitment">Recruitment</option>
                                    <option value="consistency">Consistency</option>
                                    <option value="special">Special</option>
                                </select>
                            </div>
                        </div>

                        {/* History Table */}
                        <div className="overflow-hidden rounded-2xl border border-stone-200 bg-white shadow-xs">
                            <div className="overflow-x-auto">
                                <table className="w-full text-left text-xs">
                                    <thead className="border-b border-stone-200 bg-stone-50 font-semibold text-stone-600">
                                        <tr>
                                            <th className="px-5 py-3">Achievement</th>
                                            <th className="px-4 py-3">Category</th>
                                            <th className="px-4 py-3">Tier</th>
                                            <th className="px-4 py-3">Period</th>
                                            <th className="px-4 py-3">Status</th>
                                            <th className="px-4 py-3">Awarded At</th>
                                            <th className="px-4 py-3 text-right">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-stone-100">
                                        {history.data.length === 0 ? (
                                            <tr>
                                                <td colSpan={7} className="px-5 py-10 text-center text-stone-400">
                                                    No achievement history found matching current filters.
                                                </td>
                                            </tr>
                                        ) : (
                                            history.data.map((award) => (
                                                <tr
                                                    key={award.id}
                                                    className={`hover:bg-stone-50/80 transition-colors ${
                                                        award.status === 'revoked' ? 'opacity-60 bg-rose-50/20' : ''
                                                    }`}
                                                >
                                                    <td className="px-5 py-3.5">
                                                        <div className="flex items-center gap-3">
                                                            <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border border-stone-200 bg-stone-100 text-sm">
                                                                🎖️
                                                            </div>
                                                            <div>
                                                                <span className={`font-semibold text-stone-900 block ${award.status === 'revoked' ? 'line-through text-stone-500' : ''}`}>
                                                                    {award.name}
                                                                </span>
                                                                <span className="font-mono text-[10px] text-stone-400">
                                                                    {award.code}
                                                                </span>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td className="px-4 py-3.5 capitalize text-stone-600">
                                                        {award.category}
                                                    </td>
                                                    <td className="px-4 py-3.5 font-medium uppercase text-stone-600">
                                                        {award.badge_tier}
                                                    </td>
                                                    <td className="px-4 py-3.5 font-mono text-stone-500">
                                                        {award.period_key || 'Lifetime'}
                                                    </td>
                                                    <td className="px-4 py-3.5">
                                                        <AchievementStatusBadge
                                                            status={award.status}
                                                            isReinstated={award.is_reinstated}
                                                            size="sm"
                                                        />
                                                    </td>
                                                    <td className="px-4 py-3.5 text-stone-500">
                                                        {award.awarded_at
                                                            ? new Date(award.awarded_at).toLocaleDateString()
                                                            : '—'}
                                                    </td>
                                                    <td className="px-4 py-3.5 text-right">
                                                        <button
                                                            type="button"
                                                            onClick={() => handleInspect(award)}
                                                            className="text-rose-900 hover:text-rose-700 font-semibold hover:underline"
                                                        >
                                                            Inspect &rarr;
                                                        </button>
                                                    </td>
                                                </tr>
                                            ))
                                        )}
                                    </tbody>
                                </table>
                            </div>

                            {/* Pagination Links */}
                            {history.links && history.links.length > 3 && (
                                <div className="flex items-center justify-between border-t border-stone-200 px-5 py-3 text-xs">
                                    <div className="text-stone-500">
                                        Showing {history.from ?? 0} to {history.to ?? 0} of {history.total} entries
                                    </div>
                                    <div className="flex gap-1">
                                        {history.links.map((link, idx) => (
                                            <button
                                                key={idx}
                                                type="button"
                                                disabled={!link.url}
                                                onClick={() => {
                                                    if (link.url) {
                                                        router.get(link.url, {}, { preserveState: true, preserveScroll: true });
                                                    }
                                                }}
                                                dangerouslySetInnerHTML={{ __html: link.label }}
                                                className={`rounded px-2.5 py-1 text-xs font-medium transition-colors ${
                                                    link.active
                                                        ? 'bg-stone-900 text-white'
                                                        : link.url
                                                        ? 'bg-stone-100 text-stone-700 hover:bg-stone-200'
                                                        : 'text-stone-300 cursor-not-allowed'
                                                }`}
                                            />
                                        ))}
                                    </div>
                                </div>
                            )}
                        </div>
                    </div>
                )}

                {/* TAB 4: TEAM HONORS (HLA ONLY) */}
                {is_hla && activeTab === 'downline' && downline && (
                    <div className="space-y-6">
                        {/* Summary Metrics */}
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div className="rounded-xl border border-stone-200 bg-white p-5 shadow-xs">
                                <span className="block text-[11px] font-semibold uppercase tracking-wider text-stone-500">
                                    Direct Downline Agents
                                </span>
                                <span className="mt-2 block font-mono text-2xl font-bold text-stone-900">
                                    {downline.total_downline_count}
                                </span>
                                <span className="mt-1 block text-[10px] text-stone-400">
                                    Direct depth=1 team members
                                </span>
                            </div>

                            <div className="rounded-xl border border-stone-200 bg-white p-5 shadow-xs">
                                <span className="block text-[11px] font-semibold uppercase tracking-wider text-stone-500">
                                    Total Team Badges Unlocked
                                </span>
                                <span className="mt-2 block font-mono text-2xl font-bold text-amber-700">
                                    {downline.total_badges_unlocked}
                                </span>
                                <span className="mt-1 block text-[10px] text-stone-400">
                                    Active honors across downline
                                </span>
                            </div>

                            <div className="rounded-xl border border-stone-200 bg-white p-5 shadow-xs">
                                <span className="block text-[11px] font-semibold uppercase tracking-wider text-stone-500">
                                    Top Team Achiever
                                </span>
                                {downline.top_achiever ? (
                                    <div className="mt-2">
                                        <span className="block font-semibold text-stone-900">
                                            {downline.top_achiever.name}
                                        </span>
                                        <span className="font-mono text-xs text-amber-700">
                                            {downline.top_achiever.badge_count} Badges Earned
                                        </span>
                                    </div>
                                ) : (
                                    <span className="mt-2 block text-xs text-stone-400">—</span>
                                )}
                            </div>
                        </div>

                        {/* Members Table */}
                        <div className="overflow-hidden rounded-2xl border border-stone-200 bg-white shadow-xs">
                            <div className="border-b border-stone-100 px-5 py-4">
                                <h3 className="font-serif text-base font-semibold text-stone-900">
                                    Direct Downline Achievement Honors
                                </h3>
                                <p className="text-xs text-stone-500">
                                    Inspect active honors earned by your direct Low-Level Agents
                                </p>
                            </div>

                            <div className="overflow-x-auto">
                                <table className="w-full text-left text-xs">
                                    <thead className="border-b border-stone-200 bg-stone-50 font-semibold text-stone-600">
                                        <tr>
                                            <th className="px-5 py-3">Agent</th>
                                            <th className="px-4 py-3">Referral Code</th>
                                            <th className="px-4 py-3">Status</th>
                                            <th className="px-4 py-3">Badges Unlocked</th>
                                            <th className="px-4 py-3">Joined Date</th>
                                            <th className="px-4 py-3 text-right">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-stone-100">
                                        {downline.members.data.length === 0 ? (
                                            <tr>
                                                <td colSpan={6} className="px-5 py-8 text-center text-stone-400">
                                                    No direct downline agents in your network yet.
                                                </td>
                                            </tr>
                                        ) : (
                                            downline.members.data.map((member) => (
                                                <tr key={member.id} className="hover:bg-stone-50 transition-colors">
                                                    <td className="px-5 py-3.5 font-semibold text-stone-900">
                                                        {member.name}
                                                    </td>
                                                    <td className="px-4 py-3.5 font-mono text-stone-600">
                                                        {member.referral_code ?? '—'}
                                                    </td>
                                                    <td className="px-4 py-3.5">
                                                        <span className="inline-flex rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-semibold uppercase text-emerald-800">
                                                            {member.status}
                                                        </span>
                                                    </td>
                                                    <td className="px-4 py-3.5 font-mono font-bold text-amber-700">
                                                        {member.badge_count}
                                                    </td>
                                                    <td className="px-4 py-3.5 text-stone-500">
                                                        {member.joined_at
                                                            ? new Date(member.joined_at).toLocaleDateString()
                                                            : '—'}
                                                    </td>
                                                    <td className="px-4 py-3.5 text-right">
                                                        <Link
                                                            href={`/agent/achievements/downline/${member.id}`}
                                                            className="text-rose-900 hover:text-rose-700 font-semibold hover:underline"
                                                        >
                                                            Inspect Badges &rarr;
                                                        </Link>
                                                    </td>
                                                </tr>
                                            ))
                                        )}
                                    </tbody>
                                </table>
                            </div>

                            {/* Downline Pagination */}
                            {downline.members.links && downline.members.links.length > 3 && (
                                <div className="flex items-center justify-between border-t border-stone-200 px-5 py-3 text-xs">
                                    <div className="text-stone-500">
                                        Showing {downline.members.from ?? 0} to {downline.members.to ?? 0} of {downline.members.total} members
                                    </div>
                                    <div className="flex gap-1">
                                        {downline.members.links.map((link, idx) => (
                                            <button
                                                key={idx}
                                                type="button"
                                                disabled={!link.url}
                                                onClick={() => {
                                                    if (link.url) {
                                                        router.get(link.url, {}, { preserveState: true, preserveScroll: true });
                                                    }
                                                }}
                                                dangerouslySetInnerHTML={{ __html: link.label }}
                                                className={`rounded px-2.5 py-1 text-xs font-medium transition-colors ${
                                                    link.active
                                                        ? 'bg-stone-900 text-white'
                                                        : link.url
                                                        ? 'bg-stone-100 text-stone-700 hover:bg-stone-200'
                                                        : 'text-stone-300 cursor-not-allowed'
                                                }`}
                                            />
                                        ))}
                                    </div>
                                </div>
                            )}
                        </div>
                    </div>
                )}
            </div>

            {/* Achievement Detail Modal */}
            <AchievementDetailModal
                award={selectedAward}
                isOpen={isModalOpen}
                onClose={() => {
                    setIsModalOpen(false);
                    setSelectedAward(null);
                }}
            />
        </AgentLayout>
    );
}

