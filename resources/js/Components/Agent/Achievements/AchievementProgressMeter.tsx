import React from 'react';
import { AchievementTier, MilestoneProgressDTO } from '@/types/achievements';

export interface AchievementProgressMeterProps {
    progress: MilestoneProgressDTO;
    title?: string;
    description?: string;
    tier?: AchievementTier;
    className?: string;
}

export const AchievementProgressMeter: React.FC<AchievementProgressMeterProps> = ({
    progress,
    title,
    description,
    tier = 'bronze',
    className = '',
}) => {
    // Safely clamp progress percentage between 0 and 100
    const numericPct = React.useMemo(() => {
        if (!progress.is_supported || progress.progress_pct === null || progress.progress_pct === undefined) {
            return null;
        }
        const parsed = parseFloat(progress.progress_pct);
        if (isNaN(parsed)) {
            return 0;
        }
        return Math.min(100, Math.max(0, parsed));
    }, [progress.is_supported, progress.progress_pct]);

    // Tier-based bar fill colors
    const barColor = React.useMemo(() => {
        switch (tier) {
            case 'silver':
                return 'bg-gradient-to-r from-slate-400 to-slate-200';
            case 'gold':
                return 'bg-gradient-to-r from-yellow-600 via-yellow-500 to-amber-300';
            case 'platinum':
                return 'bg-gradient-to-r from-cyan-600 via-cyan-500 to-sky-300';
            case 'diamond':
                return 'bg-gradient-to-r from-indigo-500 via-purple-500 to-pink-400';
            case 'bronze':
            default:
                return 'bg-gradient-to-r from-amber-700 via-amber-600 to-yellow-600';
        }
    }, [tier]);

    // 1. Unsupported or Non-Automated Criteria (Catalog achievements 8 to 12)
    if (!progress.is_supported || numericPct === null) {
        return (
            <div className={`rounded-lg border border-neutral-800 bg-neutral-900/60 p-4 ${className}`}>
                <div className="flex items-start justify-between gap-2">
                    <div>
                        {title && <h4 className="font-medium text-neutral-200">{title}</h4>}
                        {description && <p className="mt-0.5 text-xs text-neutral-400">{description}</p>}
                    </div>
                    <span className="shrink-0 rounded-full border border-neutral-700 bg-neutral-800 px-2.5 py-0.5 text-xs font-medium text-neutral-400">
                        Manual / Review
                    </span>
                </div>
                <div className="mt-3 flex items-center gap-2 text-xs text-neutral-400">
                    <svg className="h-4 w-4 text-neutral-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <circle cx="12" cy="12" r="10" strokeWidth={1.5} />
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M12 8v4m0 4h.01" />
                    </svg>
                    <span>{progress.status_text || 'Criteria-based qualification and recognition'}</span>
                </div>
            </div>
        );
    }

    // 2. Binary Milestone (SALES_FIRST_ORDER)
    if (progress.code === 'SALES_FIRST_ORDER') {
        const isCompleted = progress.is_qualified;
        return (
            <div className={`rounded-lg border border-neutral-800 bg-neutral-900/80 p-4 ${className}`}>
                <div className="flex items-center justify-between gap-2">
                    <div>
                        {title && <h4 className="font-medium text-neutral-200">{title}</h4>}
                        {description && <p className="mt-0.5 text-xs text-neutral-400">{description}</p>}
                    </div>
                    <span
                        className={`rounded-full border px-2.5 py-0.5 text-xs font-semibold ${
                            isCompleted
                                ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-400'
                                : 'border-amber-500/30 bg-amber-500/10 text-amber-400'
                        }`}
                    >
                        {isCompleted ? '1 / 1 Completed' : '0 / 1 Pending'}
                    </span>
                </div>

                {/* Accessible Progress Bar */}
                <div
                    role="progressbar"
                    aria-valuenow={isCompleted ? 100 : 0}
                    aria-valuemin={0}
                    aria-valuemax={100}
                    aria-valuetext={progress.status_text}
                    className="mt-3 h-2 w-full overflow-hidden rounded-full bg-neutral-800"
                >
                    <div
                        className={`h-full transition-all duration-500 ${barColor}`}
                        style={{ width: isCompleted ? '100%' : '0%' }}
                    />
                </div>

                <div className="mt-2 flex items-center justify-between text-xs text-neutral-400">
                    <span>{progress.status_text}</span>
                    <span className="font-medium text-neutral-300">{isCompleted ? '100.00%' : '0.00%'}</span>
                </div>
            </div>
        );
    }

    // 3. Supported Continuous Progress (Volume, Target Attainment, Streak)
    return (
        <div className={`rounded-lg border border-neutral-800 bg-neutral-900/80 p-4 ${className}`}>
            <div className="flex items-center justify-between gap-2">
                <div>
                    {title && <h4 className="font-medium text-neutral-200">{title}</h4>}
                    {description && <p className="mt-0.5 text-xs text-neutral-400">{description}</p>}
                </div>
                <div className="text-right">
                    <span className="text-sm font-bold text-neutral-200">
                        {progress.progress_pct}%
                    </span>
                    {progress.is_qualified && (
                        <span className="ml-2 inline-flex items-center text-xs font-semibold text-emerald-400">
                            &check; Qualified
                        </span>
                    )}
                </div>
            </div>

            {/* Accessible Progress Bar */}
            <div
                role="progressbar"
                aria-valuenow={numericPct}
                aria-valuemin={0}
                aria-valuemax={100}
                aria-valuetext={progress.status_text}
                className="mt-3 h-2.5 w-full overflow-hidden rounded-full bg-neutral-800 shadow-inner"
            >
                <div
                    className={`h-full rounded-full transition-all duration-500 ${barColor}`}
                    style={{ width: `${numericPct}%` }}
                />
            </div>

            <div className="mt-2 flex items-center justify-between text-xs text-neutral-400">
                <span className="truncate pr-2">{progress.status_text}</span>
                {progress.current_value && progress.threshold_value && (
                    <span className="shrink-0 text-neutral-500">
                        {progress.unit === 'LKR' ? `LKR ${progress.current_value}` : progress.current_value} /{' '}
                        {progress.unit === 'LKR' ? `LKR ${progress.threshold_value}` : progress.threshold_value}
                    </span>
                )}
            </div>
        </div>
    );
};

export default AchievementProgressMeter;

