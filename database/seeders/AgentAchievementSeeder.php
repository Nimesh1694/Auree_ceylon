<?php

namespace Database\Seeders;

use App\Models\AgentAchievement;
use App\Models\AgentAchievementVersion;
use Illuminate\Database\Seeder;

class AgentAchievementSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Seeds the approved Phase 1 baseline achievement catalog definitions and immutable version 1 snapshots.
     */
    public function run(): void
    {
        $achievements = [
            [
                'code' => 'SALES_FIRST_ORDER',
                'name' => 'Rising Star',
                'description' => 'Completed first commercial delivered customer order.',
                'category' => 'sales_milestone',
                'badge_tier' => 'bronze',
                'badge_icon' => 'zap',
                'metric_type' => 'order_count',
                'threshold_value' => '1.00',
                'period_type' => 'lifetime',
                'is_recurring' => false,
                'is_discretionary' => false,
                'sort_order' => 1,
            ],
            [
                'code' => 'SALES_100K_CUMULATIVE',
                'name' => 'Century Club',
                'description' => 'Achieved cumulative lifetime net commercial sales of LKR 100,000.',
                'category' => 'sales_milestone',
                'badge_tier' => 'silver',
                'badge_icon' => 'award',
                'metric_type' => 'sales_volume',
                'threshold_value' => '100000.00',
                'period_type' => 'lifetime',
                'is_recurring' => false,
                'is_discretionary' => false,
                'sort_order' => 2,
            ],
            [
                'code' => 'SALES_500K_CUMULATIVE',
                'name' => 'High Flyer',
                'description' => 'Achieved cumulative lifetime net commercial sales of LKR 500,000.',
                'category' => 'sales_milestone',
                'badge_tier' => 'gold',
                'badge_icon' => 'award',
                'metric_type' => 'sales_volume',
                'threshold_value' => '500000.00',
                'period_type' => 'lifetime',
                'is_recurring' => false,
                'is_discretionary' => false,
                'sort_order' => 3,
            ],
            [
                'code' => 'SALES_1M_CUMULATIVE',
                'name' => 'Emerald Ambassador',
                'description' => 'Achieved cumulative lifetime net commercial sales of LKR 1,000,000.',
                'category' => 'sales_milestone',
                'badge_tier' => 'platinum',
                'badge_icon' => 'gem',
                'metric_type' => 'sales_volume',
                'threshold_value' => '1000000.00',
                'period_type' => 'lifetime',
                'is_recurring' => false,
                'is_discretionary' => false,
                'sort_order' => 4,
            ],
            [
                'code' => 'TARGET_MET_MONTHLY',
                'name' => 'Target Crusher',
                'description' => 'Achieved 100% or greater of assigned monthly sales target.',
                'category' => 'target_attainment',
                'badge_tier' => 'silver',
                'badge_icon' => 'target',
                'metric_type' => 'target_attainment_pct',
                'threshold_value' => '100.00',
                'period_type' => 'monthly',
                'is_recurring' => true,
                'is_discretionary' => false,
                'sort_order' => 5,
            ],
            [
                'code' => 'TARGET_OVERACHIEVE_125',
                'name' => 'Overachiever',
                'description' => 'Exceeded assigned monthly sales target by achieving 125% or greater.',
                'category' => 'target_attainment',
                'badge_tier' => 'gold',
                'badge_icon' => 'trending-up',
                'metric_type' => 'target_attainment_pct',
                'threshold_value' => '125.00',
                'period_type' => 'monthly',
                'is_recurring' => true,
                'is_discretionary' => false,
                'sort_order' => 6,
            ],
            [
                'code' => 'TARGET_STREAK_3M',
                'name' => 'Consistency Master',
                'description' => 'Successfully met or exceeded assigned monthly sales target for 3 consecutive months.',
                'category' => 'consistency_streak',
                'badge_tier' => 'gold',
                'badge_icon' => 'flame',
                'metric_type' => 'streak_months',
                'threshold_value' => '3.00',
                'period_type' => 'monthly',
                'is_recurring' => true,
                'is_discretionary' => false,
                'sort_order' => 7,
            ],
            [
                'code' => 'CUSTOMERS_ACQUIRED_10',
                'name' => 'Client Magnet',
                'description' => 'Acquired and completed commercial orders for 10 unique customers.',
                'category' => 'customer_growth',
                'badge_tier' => 'bronze',
                'badge_icon' => 'user-check',
                'metric_type' => 'customer_count',
                'threshold_value' => '10.00',
                'period_type' => 'lifetime',
                'is_recurring' => false,
                'is_discretionary' => false,
                'sort_order' => 8,
            ],
            [
                'code' => 'TEAM_BUILDER_DIRECT_5',
                'name' => 'Team Builder',
                'description' => 'Recruited and onboarded 5 active verified direct downline agents (LLAs).',
                'category' => 'team_building',
                'badge_tier' => 'silver',
                'badge_icon' => 'users',
                'metric_type' => 'active_recruits',
                'threshold_value' => '5.00',
                'period_type' => 'lifetime',
                'is_recurring' => false,
                'is_discretionary' => false,
                'sort_order' => 9,
            ],
            [
                'code' => 'TEAM_SALES_1M_MONTHLY',
                'name' => 'Dynasty Captain',
                'description' => 'Generated over LKR 1,000,000 in monthly downline network sales volume (HLA exclusive).',
                'category' => 'team_building',
                'badge_tier' => 'platinum',
                'badge_icon' => 'shield',
                'metric_type' => 'downline_volume',
                'threshold_value' => '1000000.00',
                'period_type' => 'monthly',
                'is_recurring' => true,
                'is_discretionary' => false,
                'sort_order' => 10,
            ],
            [
                'code' => 'LEADERBOARD_MONTH_TOP1',
                'name' => 'Ceylon Number One',
                'description' => 'Ranked #1 across the entire agent network on the official monthly sales leaderboard.',
                'category' => 'special_honor',
                'badge_tier' => 'diamond',
                'badge_icon' => 'crown',
                'metric_type' => 'leaderboard_rank',
                'threshold_value' => '1.00',
                'period_type' => 'monthly',
                'is_recurring' => true,
                'is_discretionary' => false,
                'sort_order' => 11,
            ],
            [
                'code' => 'HONOR_CONVENTION_AWARD',
                'name' => "Founder's Choice",
                'description' => 'Special discretionary recognition awarded exclusively by the Business Owner for exemplary customer loyalty and ethical leadership.',
                'category' => 'special_honor',
                'badge_tier' => 'diamond',
                'badge_icon' => 'star',
                'metric_type' => 'manual',
                'threshold_value' => '0.00',
                'period_type' => 'lifetime',
                'is_recurring' => false,
                'is_discretionary' => true,
                'sort_order' => 12,
            ],
        ];

        // Safely remove legacy unreferenced test/draft codes if any exist without awards
        $approvedCodes = array_column($achievements, 'code');
        AgentAchievement::whereNotIn('code', $approvedCodes)->whereDoesntHave('awards')->delete();

        foreach ($achievements as $data) {
            $achievement = AgentAchievement::updateOrCreate(
                ['code' => $data['code']],
                array_merge($data, [
                    'is_active' => true,
                    'current_version' => 1,
                ])
            );

            AgentAchievementVersion::updateOrCreate(
                [
                    'achievement_id' => $achievement->id,
                    'version' => 1,
                ],
                [
                    'name' => $achievement->name,
                    'description' => $achievement->description,
                    'category' => $achievement->category,
                    'badge_tier' => $achievement->badge_tier,
                    'metric_type' => $achievement->metric_type,
                    'threshold_value' => $achievement->threshold_value,
                    'period_type' => $achievement->period_type,
                    'is_recurring' => $achievement->is_recurring,
                    'is_discretionary' => $achievement->is_discretionary,
                    'qualification_rules_snapshot' => [
                        'code' => $achievement->code,
                        'category' => $achievement->category,
                        'metric_type' => $achievement->metric_type,
                        'threshold_value' => (string) $achievement->threshold_value,
                        'period_type' => $achievement->period_type,
                    ],
                    'change_reason' => 'Approved Phase 1 baseline catalog seed (v1.0)',
                ]
            );
        }
    }
}
