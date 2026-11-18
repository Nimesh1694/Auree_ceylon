import React from 'react';
import { Link } from '@inertiajs/react';
import { AchievementDashboardSpotlightDTO, AchievementTier } from '@/types/achievements';
import AchievementStatusBadge from './AchievementStatusBadge';

export interface AchievementSpotlightCardProps {
    spotlight?: AchievementDashboardSpotlightDTO | null;
    className?: string;
}

const TIER_COLORS: Record<AchievementTier, { bg: string; text: string; border: string }> = {
    bronze: { bg: 'bg-amber-950/40', text: 'text-amber-300', border: 'border-amber-700/50' },
    silver: { bg: 'bg-slate-800/40', text: 'text-slate-200', border: 'border-slate-400/50' },
    gold: { bg: 'bg-yellow-950/40', text: 'text-yellow-300', border: 'border-yellow-500/50' },
    platinum: { bg: 'bg-cyan-950/40', text: 'text-cyan-200', border: 'border-cyan-400/50' },
    diamond: { bg: 'bg-indigo-950/40', text: 'text-indigo-200', border: 'border-indigo-400/50' },
};

export const AchievementSpotlightCard: React.FC<AchievementSpotlightCardProps> = ({
    spotlight,
    className = '',
}) => {
    const totalUnlocked = spotlight?.total_unlocked_badges ?? 0;
    const latestAward = spotlight?.latest_award;
    const streak = spotlight?.streak;
    const hasActiveStreak = Boolean(streak && streak.is_active && streak.current_streak > 0);

    const latestTier = (latestAward?.badge_tier || 'bronze') as AchievementTier;
    const tierStyle = TIER_COLORS[latestTier] || TIER_COLORS.bronze;

    return (
        <div className={`overflow-hidden rounded-2xl border border-stone-200 bg-white p-6 shadow-xs ${className}`}>
            {/* Header */}
            <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between border-b border-stone-100 pb-4">
                <div className="flex items-center gap-3">
                    <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-50 text-xl border border-amber-200">
                        🏆
                    </div>
                    <div>
                        <div className="flex items-center gap-2">
                            <h2 className="font-serif text-base font-semibold text-stone-900">
                                Achievements & Honors
                            </h2>
                            <span className="inline-flex items-center rounded-full border border-amber-500/30 bg-amber-500/10 px-2.5 py-0.5 text-xs font-semibold text-amber-700">
                                {totalUnlocked} {totalUnlocked === 1 ? 'Badge' : 'Badges'} Unlocked
                            </span>
                        </div>
                        <p className="text-xs text-stone-500">
                            Track milestones, unlocked honors, and consistency streaks
                        </p>
                    </div>
                </div>

                <Link
                    href="/agent/achievements"
                    className="inline-flex items-center gap-1 text-xs font-semibold text-rose-900 hover:text-rose-700 hover:underline"
                >
                    View All Achievements &rarr;
                </Link>
            </div>

            {/* Spotlight Grid */}
            <div className="mt-5 grid grid-cols-1 gap-5 md:grid-cols-2">
                {/* Left: Latest Unlocked Honor */}
                <div className="flex flex-col justify-between rounded-xl border border-stone-200/80 bg-stone-50/70 p-4">
                    <div className="text-[11px] font-semibold uppercase tracking-wider text-stone-500">
                        Latest Unlocked Honor
                    </div>

                    {latestAward ? (
                        <div className="mt-3 flex items-start gap-3.5">
                            <div
                                className={`flex h-12 w-12 shrink-0 items-center justify-center rounded-xl border shadow-inner ${tierStyle.bg} ${tierStyle.border}`}
                            >
                                <span className="text-xl">🎖️</span>
                            </div>
                            <div className="min-w-0 flex-1">
                                <div className="flex items-center gap-2">
                                    <h4 className="truncate text-sm font-semibold text-stone-900">
                                        {latestAward.name}
                                    </h4>
                                    <AchievementStatusBadge
                                        status={latestAward.status}
                                        isReinstated={latestAward.is_reinstated}
                                        size="sm"
                                    />
                                </div>
                                <p className="mt-0.5 line-clamp-1 text-xs text-stone-600">
                                    {latestAward.description}
                                </p>
                                {latestAward.awarded_at && (
                                    <div className="mt-1.5 text-[11px] text-stone-400">
                                        Earned {new Date(latestAward.awarded_at).toLocaleDateString()}
                                    </div>
                                )}
                            </div>
                        </div>
                    ) : (
                        <div className="mt-3 py-2 text-xs text-stone-500">
                            No badges unlocked yet. Place orders and hit monthly targets to unlock your first achievement!
                        </div>
                    )}

                    <div className="mt-3 pt-2 border-t border-stone-200/60 text-right">
                        <Link
                            href="/agent/achievements?tab=catalog"
                            className="text-xs font-medium text-stone-700 hover:text-stone-900 hover:underline"
                        >
                            Explore 12 Available Milestones &rarr;
                        </Link>
                    </div>
                </div>

                {/* Right: Consistency Streak Telemetry */}
                <div className="flex flex-col justify-between rounded-xl border border-stone-200/80 bg-stone-50/70 p-4">
                    <div className="flex items-center justify-between text-[11px] font-semibold uppercase tracking-wider text-stone-500">
                        <span>Target Consistency Streak</span>
                        {hasActiveStreak && (
                            <span className="text-amber-600 font-bold">Active</span>
                        )}
                    </div>

                    {hasActiveStreak ? (
                        <div className="mt-3 flex items-center gap-3.5">
                            <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl border border-amber-500/40 bg-gradient-to-br from-amber-500 to-yellow-600 text-neutral-950 shadow-sm">
                                <span className="text-2xl">🔥</span>
                            </div>
                            <div>
                                <div className="text-base font-bold text-stone-900">
                                    {streak?.current_streak} {streak?.current_streak === 1 ? 'Month' : 'Months'} Streak!
                                </div>
                                <p className="text-xs text-stone-600">
                                    {streak && streak.current_streak >= 3
                                        ? '3-Month consistency milestone achieved!'
                                        : 'Maintain target this month to extend your streak!'}
                                </p>
                                {streak?.longest_streak && streak.longest_streak > 0 && (
                                    <div className="mt-1 text-[11px] text-stone-500">
                                        Personal Best: <strong className="text-stone-700">{streak.longest_streak} Months</strong>
                                    </div>
                                )}
                            </div>
                        </div>
                    ) : (
                        <div className="mt-3 py-2 text-xs text-stone-500">
                            Hit this month&apos;s sales target consecutively to ignite your target attainment streak!
                        </div>
                    )}

                    <div className="mt-3 pt-2 border-t border-stone-200/60 text-right">
                        <Link
                            href="/agent/achievements?tab=showcase"
                            className="text-xs font-medium text-stone-700 hover:text-stone-900 hover:underline"
                        >
                            View Streak History &rarr;
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    );
};

export default AchievementSpotlightCard;

