import React from 'react';
import { AwardDatabaseStatus } from '@/types/achievements';

export interface AchievementStatusBadgeProps {
    status: AwardDatabaseStatus;
    isReinstated?: boolean;
    className?: string;
    size?: 'sm' | 'md';
}

export const AchievementStatusBadge: React.FC<AchievementStatusBadgeProps> = ({
    status,
    isReinstated = false,
    className = '',
    size = 'md',
}) => {
    // Reinstated awards hold status === 'active' with is_reinstated metadata
    const config = React.useMemo(() => {
        if (isReinstated) {
            return {
                label: 'Reinstated',
                bg: 'bg-sky-500/10 text-sky-400 border-sky-500/30',
                dot: 'bg-sky-400',
            };
        }

        switch (status) {
            case 'active':
                return {
                    label: 'Active',
                    bg: 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30',
                    dot: 'bg-emerald-400',
                };
            case 'corrected':
                return {
                    label: 'Under Review',
                    bg: 'bg-amber-500/10 text-amber-400 border-amber-500/30',
                    dot: 'bg-amber-400',
                };
            case 'revoked':
                return {
                    label: 'Revoked',
                    bg: 'bg-rose-500/10 text-rose-400 border-rose-500/30',
                    dot: 'bg-rose-400',
                };
            default:
                return {
                    label: status,
                    bg: 'bg-neutral-800 text-neutral-400 border-neutral-700',
                    dot: 'bg-neutral-400',
                };
        }
    }, [status, isReinstated]);

    const sizeClasses = size === 'sm' ? 'px-2 py-0.5 text-xs' : 'px-2.5 py-1 text-xs sm:text-sm';

    return (
        <span
            role="status"
            aria-label={`Award status: ${config.label}`}
            className={`inline-flex items-center gap-1.5 rounded-full border font-medium ${config.bg} ${sizeClasses} ${className}`}
        >
            <span className={`h-1.5 w-1.5 rounded-full ${config.dot}`} aria-hidden="true" />
            <span>{config.label}</span>
        </span>
    );
};

export default AchievementStatusBadge;

