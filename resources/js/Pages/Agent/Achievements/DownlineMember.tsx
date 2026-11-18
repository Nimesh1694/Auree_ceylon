import React, { useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import AgentLayout from '@/Layouts/AgentLayout';
import {
    AchievementAwardDTO,
    DownlineMemberDetailPageProps,
} from '@/types/achievements';
import {
    AchievementBadgeCard,
    AchievementDetailModal,
    StreakFlameBanner,
} from '@/Components/Agent/Achievements';

export default function DownlineMemberAchievements({
    member,
    awards,
    streak,
}: DownlineMemberDetailPageProps) {
    const [selectedAward, setSelectedAward] = useState<AchievementAwardDTO | null>(null);
    const [isModalOpen, setIsModalOpen] = useState(false);

    const handleInspect = (item: AchievementAwardDTO) => {
        setSelectedAward(item);
        setIsModalOpen(true);
    };

    return (
        <AgentLayout>
            <Head title={`${member.name}'s Achievements | Aura Luxe`} />

            <div className="space-y-8">
                {/* Back to Team Honors Navigation */}
                <div>
                    <Link
                        href="/agent/achievements?tab=downline"
                        className="inline-flex items-center gap-1.5 text-xs font-semibold text-rose-900 hover:text-rose-700 hover:underline"
                    >
                        <span>&larr;</span>
                        <span>Back to Team Honors</span>
                    </Link>
                </div>

                {/* Member Profile Header */}
                <div className="flex flex-col gap-4 rounded-2xl border border-stone-200 bg-white p-6 shadow-xs sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div className="flex items-center gap-2">
                            <span className="text-[11px] font-semibold uppercase tracking-wider text-rose-800">
                                Direct Downline Member
                            </span>
                            <span className="rounded-full bg-emerald-100 px-2.5 py-0.5 text-[10px] font-semibold uppercase text-emerald-800">
                                {member.status}
                            </span>
                        </div>
                        <h1 className="mt-1 font-serif text-2xl font-semibold text-stone-900 sm:text-3xl">
                            {member.name}
                        </h1>
                        <p className="mt-1 text-xs text-stone-500 font-mono">
                            Referral Code: <span className="font-semibold text-stone-700">{member.referral_code ?? 'None'}</span> &bull; Role: <span className="capitalize">{member.role.replace(/_/g, ' ')}</span>
                        </p>
                    </div>

                    <div className="rounded-xl border border-stone-200 bg-stone-50 px-5 py-3 text-center">
                        <span className="block text-[10px] font-semibold uppercase text-stone-500">
                            Active Badges Unlocked
                        </span>
                        <span className="text-2xl font-bold font-mono text-amber-700">
                            {awards.length}
                        </span>
                    </div>
                </div>

                {/* Target Consistency Streak */}
                <StreakFlameBanner streak={streak} />

                {/* Active Honors Showcase */}
                <div className="space-y-4">
                    <div>
                        <h2 className="font-serif text-lg font-semibold text-stone-900">
                            Unlocked Achievement Badges
                        </h2>
                        <p className="text-xs text-stone-500">
                            Honors and milestones earned by {member.name} in commercial sales and consistency
                        </p>
                    </div>

                    {awards.length === 0 ? (
                        <div className="rounded-2xl border border-stone-200 bg-white p-12 text-center shadow-xs">
                            <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-stone-100 text-3xl">
                                🎖️
                            </div>
                            <h3 className="mt-4 font-serif text-base font-semibold text-stone-900">
                                No Achievement Badges Yet
                            </h3>
                            <p className="mx-auto mt-1 max-w-sm text-xs text-stone-500">
                                This team member has not yet unlocked any achievement badges. Support them in hitting
                                monthly sales targets to help them earn honors!
                            </p>
                        </div>
                    ) : (
                        <div className="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                            {awards.map((award) => (
                                <AchievementBadgeCard
                                    key={award.id}
                                    item={award}
                                    onInspect={(item) => handleInspect(item as AchievementAwardDTO)}
                                />
                            ))}
                        </div>
                    )}
                </div>
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

