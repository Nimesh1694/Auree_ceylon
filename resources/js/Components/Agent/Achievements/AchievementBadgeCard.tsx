import React from 'react';
import {
    AchievementAwardDTO,
    AchievementCatalogItemDTO,
    AchievementTier,
} from '@/types/achievements';
import AchievementStatusBadge from './AchievementStatusBadge';

export interface AchievementBadgeCardProps {
    item: AchievementAwardDTO | AchievementCatalogItemDTO;
    isCatalog?: boolean;
    onInspect?: (item: AchievementAwardDTO | AchievementCatalogItemDTO) => void;
    className?: string;
}

/**
 * Type guard to safely identify an AchievementAwardDTO vs AchievementCatalogItemDTO
 */
export function isAwardDTO(
    item: AchievementAwardDTO | AchievementCatalogItemDTO
): item is AchievementAwardDTO {
    return 'status' in item && typeof (item as AchievementAwardDTO).status === 'string';
}

/**
 * Metallic styling tokens per achievement tier
 */
const TIER_THEMES: Record<
    AchievementTier,
    {
        border: string;
        bg: string;
        badgeGlow: string;
        accentText: string;
        iconFill: string;
        label: string;
    }
> = {
    bronze: {
        border: 'border-amber-700/40 hover:border-amber-600/70',
        bg: 'bg-gradient-to-br from-amber-950/30 via-neutral-900 to-neutral-950',
        badgeGlow: 'bg-amber-600/20 text-amber-300 border-amber-700/50',
        accentText: 'text-amber-300',
        iconFill: 'text-amber-400',
        label: 'Bronze',
    },
    silver: {
        border: 'border-slate-400/40 hover:border-slate-300/70',
        bg: 'bg-gradient-to-br from-slate-850/40 via-neutral-900 to-neutral-950',
        badgeGlow: 'bg-slate-400/20 text-slate-200 border-slate-400/50',
        accentText: 'text-slate-200',
        iconFill: 'text-slate-300',
        label: 'Silver',
    },
    gold: {
        border: 'border-yellow-500/40 hover:border-yellow-400/70',
        bg: 'bg-gradient-to-br from-yellow-950/35 via-neutral-900 to-neutral-950',
        badgeGlow: 'bg-yellow-500/20 text-yellow-300 border-yellow-500/50',
        accentText: 'text-yellow-300',
        iconFill: 'text-yellow-400',
        label: 'Gold',
    },
    platinum: {
        border: 'border-cyan-400/40 hover:border-cyan-300/70',
        bg: 'bg-gradient-to-br from-cyan-950/30 via-neutral-900 to-neutral-950',
        badgeGlow: 'bg-cyan-400/20 text-cyan-200 border-cyan-400/50',
        accentText: 'text-cyan-200',
        iconFill: 'text-cyan-300',
        label: 'Platinum',
    },
    diamond: {
        border: 'border-indigo-400/40 hover:border-indigo-300/70',
        bg: 'bg-gradient-to-br from-indigo-950/40 via-purple-950/25 to-neutral-950',
        badgeGlow: 'bg-indigo-400/20 text-indigo-200 border-indigo-400/50',
        accentText: 'text-indigo-200',
        iconFill: 'text-indigo-300',
        label: 'Diamond',
    },
};

/**
 * Embedded SVG Icons for Achievement Visuals
 */
const AchievementIcon: React.FC<{ icon: string; className?: string }> = ({
    icon,
    className = 'w-7 h-7',
}) => {
    switch (icon?.toLowerCase()) {
        case 'flame':
            return (
                <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.75}>
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
            );
        case 'zap':
            return (
                <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.75}>
                    <polygon strokeLinecap="round" strokeLinejoin="round" points="13 2 3 14 12 14 11 22 21 10 12 10 13 2" />
                </svg>
            );
        case 'crown':
            return (
                <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.75}>
                    <path
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        d="M5 16l-2-9 5 3 4-6 4 6 5-3-2 9H5z"
                    />
                    <path strokeLinecap="round" strokeLinejoin="round" d="M5 19h14v2H5v-2z" />
                </svg>
            );
        case 'gem':
            return (
                <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.75}>
                    <path
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        d="M6 3h12l4 6-10 12L2 9l4-6z"
                    />
                    <path strokeLinecap="round" strokeLinejoin="round" d="M2 9h20M10 3l2 6-2 12m4-18l-2 6 2 12" />
                </svg>
            );
        case 'target':
            return (
                <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.75}>
                    <circle cx="12" cy="12" r="10" />
                    <circle cx="12" cy="12" r="6" />
                    <circle cx="12" cy="12" r="2" />
                </svg>
            );
        case 'star':
            return (
                <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.75}>
                    <polygon
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"
                    />
                </svg>
            );
        case 'lock':
            return (
                <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.75}>
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
                    <path strokeLinecap="round" strokeLinejoin="round" d="M7 11V7a5 5 0 0110 0v4" />
                </svg>
            );
        case 'award':
        default:
            return (
                <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.75}>
                    <circle cx="12" cy="8" r="6" />
                    <path strokeLinecap="round" strokeLinejoin="round" d="M15.477 12.89L17 22l-5-3-5 3 1.523-9.11" />
                </svg>
            );
    }
};

export const AchievementBadgeCard: React.FC<AchievementBadgeCardProps> = ({
    item,
    isCatalog = false,
    onInspect,
    className = '',
}) => {
    const isAward = isAwardDTO(item);
    const tier = (item.badge_tier || 'bronze') as AchievementTier;
    const theme = TIER_THEMES[tier] || TIER_THEMES.bronze;

    // Determine unlock / lifecycle state
    const isUnlocked = isAward ? item.status === 'active' : item.is_unlocked;
    const isCorrected = isAward ? item.status === 'corrected' : item.is_corrected;
    const isRevoked = isAward && item.status === 'revoked';
    const isReinstated = isAward && item.is_reinstated;
    const isLocked = !isAward && !isUnlocked && !isCorrected;

    const handleClick = () => {
        if (onInspect) {
            onInspect(item);
        }
    };

    const handleKeyDown = (e: React.KeyboardEvent) => {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            handleClick();
        }
    };

    return (
        <div
            role="button"
            tabIndex={0}
            onClick={handleClick}
            onKeyDown={handleKeyDown}
            aria-label={`${item.name} (${theme.label} Tier) - ${isLocked ? 'Locked' : isRevoked ? 'Revoked' : isReinstated ? 'Reinstated' : isCorrected ? 'Under Review' : 'Unlocked'}. Click to inspect details.`}
            className={`group relative flex flex-col justify-between rounded-xl border p-5 shadow-lg transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-amber-500/50 ${
                theme.bg
            } ${theme.border} ${
                isLocked
                    ? 'opacity-65 grayscale hover:grayscale-0 hover:opacity-100'
                    : isRevoked
                    ? 'opacity-60 border-rose-900/50'
                    : 'hover:-translate-y-0.5 hover:shadow-xl'
            } ${className}`}
        >
            {/* Header: Tier Pill & Lifecycle Status */}
            <div className="flex items-center justify-between gap-2">
                <span
                    className={`inline-flex items-center rounded-md border px-2 py-0.5 text-xs font-semibold uppercase tracking-wider ${theme.badgeGlow}`}
                >
                    {theme.label}
                </span>

                {isAward ? (
                    <AchievementStatusBadge status={item.status} isReinstated={item.is_reinstated} size="sm" />
                ) : isLocked ? (
                    <span className="inline-flex items-center gap-1 rounded-full border border-neutral-700 bg-neutral-800/80 px-2 py-0.5 text-xs font-medium text-neutral-400">
                        <AchievementIcon icon="lock" className="h-3 w-3" />
                        Locked
                    </span>
                ) : isCorrected ? (
                    <span className="inline-flex items-center gap-1 rounded-full border border-amber-500/30 bg-amber-500/10 px-2 py-0.5 text-xs font-medium text-amber-400">
                        Under Review
                    </span>
                ) : (
                    <span className="inline-flex items-center gap-1 rounded-full border border-emerald-500/30 bg-emerald-500/10 px-2 py-0.5 text-xs font-medium text-emerald-400">
                        Unlocked
                    </span>
                )}
            </div>

            {/* Badge Icon & Identity */}
            <div className="my-4 flex items-center gap-3.5">
                <div
                    className={`flex h-12 w-12 shrink-0 items-center justify-center rounded-xl border shadow-inner ${
                        isLocked
                            ? 'border-neutral-700 bg-neutral-800/60 text-neutral-500'
                            : `${theme.border} ${theme.badgeGlow} ${theme.iconFill}`
                    }`}
                >
                    <AchievementIcon icon={isLocked ? 'lock' : item.badge_icon} className="h-6 w-6" />
                </div>

                <div className="min-w-0 flex-1">
                    <h3 className={`truncate font-semibold ${theme.accentText}`}>
                        {item.name}
                    </h3>
                    <p className="mt-0.5 line-clamp-2 text-xs text-neutral-400">
                        {item.description}
                    </p>
                </div>
            </div>

            {/* Footer Snapshot & Action Hint */}
            <div className="mt-2 border-t border-neutral-800/80 pt-3">
                <div className="flex items-center justify-between text-xs text-neutral-400">
                    <span className="capitalize text-neutral-500">
                        {item.category}
                    </span>

                    {isAward && item.awarded_at ? (
                        <span className="text-neutral-400">
                            Earned {new Date(item.awarded_at).toLocaleDateString()}
                        </span>
                    ) : (
                        <span className="text-neutral-500 group-hover:text-neutral-300">
                            Click to view details &rarr;
                        </span>
                    )}
                </div>
            </div>
        </div>
    );
};

export default AchievementBadgeCard;

