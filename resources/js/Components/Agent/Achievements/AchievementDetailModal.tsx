import React, { Fragment } from 'react';
import {
    Dialog,
    DialogPanel,
    DialogTitle,
    Transition,
    TransitionChild,
} from '@headlessui/react';
import {
    AchievementAwardDTO,
    AchievementCatalogItemDTO,
    AchievementTier,
} from '@/types/achievements';
import AchievementStatusBadge from './AchievementStatusBadge';
import { isAwardDTO } from './AchievementBadgeCard';

export interface AchievementDetailModalProps {
    award: AchievementAwardDTO | AchievementCatalogItemDTO | null;
    isOpen: boolean;
    onClose: () => void;
}

export const AchievementDetailModal: React.FC<AchievementDetailModalProps> = ({
    award,
    isOpen,
    onClose,
}) => {
    if (!award) {
        return null;
    }

    const isAward = isAwardDTO(award);
    const tier = (award.badge_tier || 'bronze') as AchievementTier;

    // Formatting date
    const formatDate = (dateStr?: string | null) => {
        if (!dateStr) return null;
        try {
            return new Date(dateStr).toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
            });
        } catch {
            return dateStr;
        }
    };

    return (
        <Transition show={isOpen} as={Fragment}>
            <Dialog as="div" className="relative z-50" onClose={onClose}>
                {/* Backdrop with transition */}
                <TransitionChild
                    as={Fragment}
                    enter="ease-out duration-300"
                    enterFrom="opacity-0"
                    enterTo="opacity-100"
                    leave="ease-in duration-200"
                    leaveFrom="opacity-100"
                    leaveTo="opacity-0"
                >
                    <div className="fixed inset-0 bg-neutral-950/80 backdrop-blur-sm transition-opacity" />
                </TransitionChild>

                {/* Dialog Container */}
                <div className="fixed inset-0 z-10 overflow-y-auto">
                    <div className="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
                        <TransitionChild
                            as={Fragment}
                            enter="ease-out duration-300"
                            enterFrom="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                            enterTo="opacity-100 translate-y-0 sm:scale-100"
                            leave="ease-in duration-200"
                            leaveFrom="opacity-100 translate-y-0 sm:scale-100"
                            leaveTo="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                        >
                            <DialogPanel className="relative w-full max-w-lg transform overflow-hidden rounded-2xl border border-neutral-800 bg-neutral-900 p-6 text-left shadow-2xl transition-all sm:my-8">
                                {/* Header with Tier & Close Button */}
                                <div className="flex items-start justify-between gap-4">
                                    <div className="flex items-center gap-2">
                                        <span className="rounded-md border border-neutral-700 bg-neutral-800 px-2.5 py-0.5 text-xs font-semibold uppercase tracking-wider text-neutral-300">
                                            {tier} Tier
                                        </span>
                                        {isAward ? (
                                            <AchievementStatusBadge
                                                status={award.status}
                                                isReinstated={award.is_reinstated}
                                                size="sm"
                                            />
                                        ) : award.is_unlocked ? (
                                            <span className="rounded-full border border-emerald-500/30 bg-emerald-500/10 px-2.5 py-0.5 text-xs font-medium text-emerald-400">
                                                Unlocked
                                            </span>
                                        ) : (
                                            <span className="rounded-full border border-neutral-700 bg-neutral-800 px-2.5 py-0.5 text-xs font-medium text-neutral-400">
                                                Locked
                                            </span>
                                        )}
                                    </div>

                                    <button
                                        type="button"
                                        onClick={onClose}
                                        aria-label="Close dialog"
                                        className="rounded-lg p-1.5 text-neutral-400 hover:bg-neutral-800 hover:text-neutral-200 focus:outline-none focus:ring-2 focus:ring-amber-500/50"
                                    >
                                        <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>

                                {/* Title & Code */}
                                <div className="mt-4">
                                    <DialogTitle as="h3" className="text-xl font-bold text-neutral-100">
                                        {award.name}
                                    </DialogTitle>
                                    <div className="mt-1 flex items-center gap-2 text-xs font-mono text-neutral-400">
                                        <span>Code: {award.code}</span>
                                        <span>&bull;</span>
                                        <span className="capitalize">{award.category}</span>
                                    </div>
                                    <p className="mt-3 text-sm text-neutral-300 leading-relaxed">
                                        {award.description}
                                    </p>
                                </div>

                                {/* Criteria Snapshot & Attributes Grid */}
                                <div className="mt-6 space-y-3 rounded-xl border border-neutral-800/80 bg-neutral-950/60 p-4 text-xs">
                                    <div className="flex items-center justify-between border-b border-neutral-800 pb-2">
                                        <span className="text-neutral-400">Metric Type</span>
                                        <span className="font-medium text-neutral-200 capitalize">
                                            {award.metric_type?.replace(/_/g, ' ')}
                                        </span>
                                    </div>

                                    <div className="flex items-center justify-between border-b border-neutral-800 pb-2">
                                        <span className="text-neutral-400">Qualification Threshold</span>
                                        <span className="font-semibold text-amber-300">
                                            {isAward ? award.threshold_snapshot : award.threshold_value}
                                        </span>
                                    </div>

                                    {isAward && award.achieved_value && (
                                        <div className="flex items-center justify-between border-b border-neutral-800 pb-2">
                                            <span className="text-neutral-400">Achieved Metric at Award</span>
                                            <span className="font-semibold text-emerald-400">
                                                {award.achieved_value}
                                            </span>
                                        </div>
                                    )}

                                    {isAward && award.period_key && (
                                        <div className="flex items-center justify-between border-b border-neutral-800 pb-2">
                                            <span className="text-neutral-400">Evaluation Period</span>
                                            <span className="font-mono text-neutral-300">
                                                {award.period_key}
                                            </span>
                                        </div>
                                    )}

                                    {isAward && award.version && (
                                        <div className="flex items-center justify-between border-b border-neutral-800 pb-2">
                                            <span className="text-neutral-400">Rule Version</span>
                                            <span className="font-mono text-neutral-300">
                                                v{award.version}
                                            </span>
                                        </div>
                                    )}

                                    {award.awarded_at && (
                                        <div className="flex items-center justify-between pt-1">
                                            <span className="text-neutral-400">Awarded At</span>
                                            <span className="text-neutral-300">
                                                {formatDate(award.awarded_at)}
                                            </span>
                                        </div>
                                    )}
                                </div>

                                {/* Reinstatement Banner */}
                                {isAward && award.is_reinstated && (
                                    <div className="mt-4 rounded-xl border border-sky-500/30 bg-sky-500/10 p-3.5 text-xs text-sky-300">
                                        <div className="flex items-center gap-2 font-semibold">
                                            <svg className="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                            Award Reinstated
                                        </div>
                                        <p className="mt-1 text-sky-200/80">
                                            This award was officially reinstated and restored to active standing
                                            {award.reinstated_at && ` on ${formatDate(award.reinstated_at)}`}.
                                        </p>
                                    </div>
                                )}

                                {/* Revoked Notice (Sensitive reasons excluded) */}
                                {isAward && award.status === 'revoked' && (
                                    <div className="mt-4 rounded-xl border border-rose-500/30 bg-rose-500/10 p-3.5 text-xs text-rose-300">
                                        <div className="flex items-center gap-2 font-semibold">
                                            <svg className="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                            </svg>
                                            Award Revoked
                                        </div>
                                        <p className="mt-1 text-rose-200/80">
                                            This award has been revoked
                                            {award.revoked_at && ` on ${formatDate(award.revoked_at)}`} and is retained
                                            solely in historical records.
                                        </p>
                                    </div>
                                )}

                                {/* Modal Footer */}
                                <div className="mt-6 flex justify-end">
                                    <button
                                        type="button"
                                        onClick={onClose}
                                        className="rounded-lg bg-neutral-800 px-4 py-2 text-sm font-medium text-neutral-200 hover:bg-neutral-700 focus:outline-none focus:ring-2 focus:ring-amber-500/50"
                                    >
                                        Close
                                    </button>
                                </div>
                            </DialogPanel>
                        </TransitionChild>
                    </div>
                </div>
            </Dialog>
        </Transition>
    );
};

export default AchievementDetailModal;

