/**
 * AURA LUXE — Agent Achievement System TypeScript DTO Contracts
 * Strictly matching app/Services/AgentAchievementPortalService.php
 */

export type AchievementTier = 'bronze' | 'silver' | 'gold' | 'platinum' | 'diamond';

export type AchievementCategory = 'sales' | 'recruitment' | 'consistency' | 'special';

export type AwardDatabaseStatus = 'active' | 'corrected' | 'revoked';

/**
 * Single Achievement Award DTO (returned by transformAward)
 */
export interface AchievementAwardDTO {
    id: number;
    achievement_id: number;
    code: string;
    name: string;
    description: string;
    category: AchievementCategory;
    badge_tier: AchievementTier;
    badge_icon: string;
    metric_type: string;
    period_key: string | null;
    achieved_value: string;
    threshold_snapshot: string;
    status: AwardDatabaseStatus;
    is_discretionary: boolean;
    is_reinstated: boolean;
    reinstated_at: string | null;
    awarded_at: string | null;
    revoked_at: string | null;
    version: number | null;
    qualification_rules_snapshot: Record<string, unknown> | null;
}

/**
 * Catalog Item DTO (returned by transformCatalog)
 */
export interface AchievementCatalogItemDTO {
    id: number;
    code: string;
    name: string;
    description: string;
    category: AchievementCategory;
    badge_tier: AchievementTier;
    badge_icon: string;
    metric_type: string;
    threshold_value: string;
    period_type: string;
    is_recurring: boolean;
    is_discretionary: boolean;
    is_unlocked: boolean;
    is_corrected: boolean;
    award_id: number | null;
    awarded_at: string | null;
}

/**
 * Milestone Progress DTO (returned by calculateMilestoneProgress)
 */
export interface MilestoneProgressDTO {
    code: string;
    is_supported: boolean;
    progress_pct: string | null;
    current_value: string | null;
    threshold_value: string | null;
    unit: string | null;
    is_qualified: boolean;
    status_text: string;
    target_month?: string | null;
    has_target?: boolean;
}

/**
 * Target Attainment Streak DTO
 */
export interface AchievementStreakDTO {
    current_streak: number;
    longest_streak: number;
    last_qualifying_period: string | null;
    streak_started_period: string | null;
    is_active: boolean;
}

/**
 * Standard Laravel LengthAwarePaginator JSON serialization wrapper
 */
export interface PaginatedResponse<T> {
    data: T[];
    current_page: number;
    first_page_url: string;
    from: number | null;
    last_page: number;
    last_page_url: string;
    links: Array<{
        url: string | null;
        label: string;
        active: boolean;
    }>;
    next_page_url: string | null;
    path: string;
    per_page: number;
    prev_page_url: string | null;
    to: number | null;
    total: number;
}

/**
 * Direct Downline Member Summary DTO for High-Level Agents
 */
export interface DownlineMemberSummaryDTO {
    id: number;
    name: string;
    referral_code: string | null;
    role: string;
    status: string;
    badge_count: number;
    joined_at: string | null;
}

/**
 * Direct Downline Honors Payload for High-Level Agents
 */
export interface DownlineHonorsPayload {
    total_downline_count: number;
    total_badges_unlocked: number;
    top_achiever: {
        id: number;
        name: string;
        referral_code: string | null;
        badge_count: number;
    } | null;
    members: PaginatedResponse<DownlineMemberSummaryDTO>;
}

/**
 * Complete Agent Achievement Portal Page Props
 */
export interface AgentAchievementsPageProps {
    agent: {
        id: number;
        name: string;
        referral_code: string | null;
        role: string;
        is_hla: boolean;
    };
    awards: AchievementAwardDTO[];
    catalog: AchievementCatalogItemDTO[];
    streak: AchievementStreakDTO | null;
    progress: Record<string, MilestoneProgressDTO>;
    history: PaginatedResponse<AchievementAwardDTO>;
    filters: {
        status: string | null;
        category: string | null;
        tab: string;
    };
    is_hla: boolean;
    downline?: DownlineHonorsPayload | null;
}

/**
 * Dashboard Spotlight Telemetry DTO
 */
export interface AchievementDashboardSpotlightDTO {
    total_unlocked_badges: number;
    latest_award: AchievementAwardDTO | null;
    streak: {
        current_streak: number;
        longest_streak: number;
        is_active: boolean;
    } | null;
}

/**
 * Downline Member Inspection Detail Page Props
 */
export interface DownlineMemberDetailPageProps {
    member: {
        id: number;
        name: string;
        referral_code: string | null;
        role: string;
        status: string;
    };
    awards: AchievementAwardDTO[];
    streak: AchievementStreakDTO | null;
}
