import React from 'react';
import { AchievementStreakDTO } from '@/types/achievements';

export interface StreakFlameBannerProps {
    streak: AchievementStreakDTO | null;
    className?: string;
}

export const StreakFlameBanner: React.FC<StreakFlameBannerProps> = ({
    streak,
    className = '',
}) => {
    const hasActiveStreak = Boolean(streak && streak.is_active && streak.current_streak > 0);
    const currentStreak = streak?.current_streak ?? 0;
    const longestStreak = streak?.longest_streak ?? 0;

    if (!hasActiveStreak) {
        return (
            <div
                className={`relative overflow-hidden rounded-xl border border-neutral-800 bg-neutral-900/60 p-5 shadow-sm ${className}`}
            >
                <div className="flex flex-col items-start gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-3.5">
                        <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-neutral-700 bg-neutral-800 text-neutral-500">
                            <svg className="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                                <path
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    d="M17.657 18.657A8 8 0 016.343 7.343S7 9 9 10c0-2 .5-5 2.986-7C14 5 16.09 5.777 17.656 7.343A7.975 7.975 0 0120 13a7.975 7.975 0 01-2.343 5.657z"
                                />
                                <path
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    d="M9.879 16.121A3 3 0 1012.015 11L11 14H9c0 .768.293 1.536.879 2.121z"
                                />
                            </svg>
                        </div>
                        <div>
                            <h4 className="font-semibold text-neutral-300">Target Consistency Streak</h4>
                            <p className="text-xs text-neutral-400">
                                Achieve your monthly sales targets consecutively to ignite and build your streak!
                            </p>
                        </div>
                    </div>

                    {longestStreak > 0 && (
                        <div className="shrink-0 rounded-lg border border-neutral-800 bg-neutral-800/50 px-3 py-1.5 text-xs text-neutral-400">
                            Personal Record: <span className="font-semibold text-neutral-200">{longestStreak} Months</span>
                        </div>
                    )}
                </div>
            </div>
        );
    }

    return (
        <div
            className={`relative overflow-hidden rounded-xl border border-amber-500/40 bg-gradient-to-r from-amber-950/40 via-yellow-950/20 to-neutral-900 p-5 shadow-lg ${className}`}
        >
            {/* Ambient flame background glow */}
            <div className="pointer-events-none absolute -right-6 -top-6 h-32 w-32 rounded-full bg-amber-500/10 blur-2xl" />

            <div className="relative flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div className="flex items-center gap-4">
                    {/* Glowing Flame Icon Box */}
                    <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl border border-amber-500/50 bg-gradient-to-br from-amber-600 to-yellow-600 text-neutral-950 shadow-md shadow-amber-500/20">
                        <svg className="h-7 w-7 fill-neutral-950 stroke-neutral-950" viewBox="0 0 24 24">
                            <path d="M17.657 18.657A8 8 0 016.343 7.343S7 9 9 10c0-2 .5-5 2.986-7C14 5 16.09 5.777 17.656 7.343A7.975 7.975 0 0120 13a7.975 7.975 0 01-2.343 5.657z" />
                        </svg>
                    </div>

                    <div>
                        <div className="flex items-center gap-2">
                            <h4 className="text-base font-bold text-amber-300">
                                {currentStreak} {currentStreak === 1 ? 'Month' : 'Months'} Consecutive Streak!
                            </h4>
                            <span className="inline-flex items-center rounded-full border border-amber-500/30 bg-amber-500/10 px-2 py-0.5 text-xs font-semibold text-amber-400">
                                ACTIVE
                            </span>
                        </div>
                        <p className="mt-0.5 text-xs text-neutral-300">
                            {currentStreak >= 3
                                ? 'Outstanding momentum! 3-Month Consistency milestone unlocked.'
                                : `Target met for ${currentStreak} consecutive month${currentStreak === 1 ? '' : 's'}. Reach 3 months to unlock the Platinum consistency badge!`}
                        </p>
                    </div>
                </div>

                <div className="flex items-center gap-3">
                    <div className="rounded-lg border border-amber-500/20 bg-neutral-900/80 px-3.5 py-1.5 text-right text-xs">
                        <div className="text-neutral-400">Personal Best</div>
                        <div className="text-sm font-bold text-amber-200">
                            {longestStreak} {longestStreak === 1 ? 'Month' : 'Months'}
                        </div>
                    </div>
                    {streak?.last_qualifying_period && (
                        <div className="hidden rounded-lg border border-neutral-800 bg-neutral-900/60 px-3 py-1.5 text-xs text-neutral-400 md:block">
                            Qualified: <span className="text-neutral-300">{streak.last_qualifying_period}</span>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
};

export default StreakFlameBanner;

