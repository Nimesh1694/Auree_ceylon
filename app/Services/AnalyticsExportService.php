<?php

namespace App\Services;

use App\Models\AgentSalesTarget;
use App\Models\User;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AnalyticsExportService
{
    public function __construct(
        protected AgentAnalyticsService $analyticsService
    ) {}

    /**
     * Stream administrative agent performance leaderboard to CSV with bounded memory.
     *
     * @param array $boundaries
     * @param array $filters
     * @return StreamedResponse
     */
    public function exportAdminLeaderboard(array $boundaries, array $filters = []): StreamedResponse
    {
        $roleFilter = $filters['role'] ?? 'all';
        $search = trim($filters['search'] ?? '');
        $targetMonth = $boundaries['targetMonth'];
        $periodLabel = $boundaries['periodLabel'];

        $filename = "agent_analytics_{$targetMonth}_" . now()->format('Ymd_His') . ".csv";

        return new StreamedResponse(function () use ($roleFilter, $search, $boundaries, $targetMonth, $periodLabel) {
            $handle = fopen('php://output', 'w');

            // 1. Metadata & Disclosure Header
            fputcsv($handle, ['# AURA LUXE AGENT PERFORMANCE ANALYTICS EXPORT']);
            fputcsv($handle, ['# Period:', $periodLabel, 'Start UTC:', $boundaries['startUtc'], 'End UTC:', $boundaries['endUtc']]);
            fputcsv($handle, ['# Disclosure: Full-Return Order Value reflects commercial product turnover reversed upon return. Cash disbursements are not tracked.']);
            fputcsv($handle, []);

            // 2. Column Headers
            fputcsv($handle, [
                'Agent ID',
                'Agent Name',
                'Referral Code',
                'Role',
                'Parent Sponsor',
                'Target Month',
                'Sales Target (LKR)',
                'Target Attainment (%)',
                'Gross Product Sales (LKR)',
                'Product Discounts (LKR)',
                'Full-Return Order Value (Turnover Basis LKR)',
                'Fulfilled Partial Refunds (LKR)',
                'Total Commercial Deductions (LKR)',
                'Net Product Sales (LKR)',
                'Total Placed Orders',
                'Total Commercial Orders',
                'Total Fulfilled Orders',
                'Total Returned Orders',
                'Gross AOV Demand (LKR)',
                'Commercial Gross AOV (LKR)',
                'Net AOV (LKR)',
                'Delivery Success Rate (%)',
                'Full Return Rate (%)',
                'Partial Refund Incidence (%)',
                'Overall Friction Rate (%)',
                'Active Approved Commission (LKR)',
                'Active Pending Commission (LKR)',
                'Period Net Commission Activity (LKR)',
            ]);

            // 3. Query Cursor with Lazy Loading for Low Memory Footprint
            $query = User::query()
                ->whereIn('role', ['high_level_agent', 'low_level_agent'])
                ->with(['parent:id,name,referral_code'])
                ->orderBy('id');

            if ($roleFilter === 'high_level_agent' || $roleFilter === 'low_level_agent') {
                $query->where('role', $roleFilter);
            }

            if (!empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('referral_code', 'like', "%{$search}%");
                });
            }

            foreach ($query->cursor() as $agent) {
                $target = AgentSalesTarget::where('agent_id', $agent->id)
                    ->where('target_month', $targetMonth)
                    ->first();

                $targetAmt = $target ? AgentAnalyticsService::normalizeDecimalString($target->sales_target, false) : null;

                $summary = $this->analyticsService->computeAnalyticsSummary(
                    $agent->id,
                    $agent->referral_code,
                    $boundaries['startUtc'],
                    $boundaries['endUtc'],
                    $targetAmt
                );

                fputcsv($handle, [
                    $agent->id,
                    $agent->name,
                    $agent->referral_code,
                    $agent->getRoleValue(),
                    $agent->parent ? "{$agent->parent->name} ({$agent->parent->referral_code})" : 'None',
                    $targetMonth,
                    $summary['monthly_target'] ?? 'N/A',
                    $summary['target_attainment_pct'] ?? 'N/A',
                    $summary['gross_product_sales'],
                    $summary['product_discounts'],
                    $summary['full_return_order_value'],
                    $summary['fulfilled_partial_refund_amount'],
                    $summary['total_commercial_deductions'],
                    $summary['net_product_sales'],
                    $summary['total_placed_orders'],
                    $summary['total_commercial_orders'],
                    $summary['total_fulfilled_orders'],
                    $summary['total_returned_orders'],
                    $summary['gross_aov'] ?? 'N/A',
                    $summary['commercial_gross_aov'] ?? 'N/A',
                    $summary['net_aov'] ?? 'N/A',
                    $summary['delivery_success_rate'] ?? 'N/A',
                    $summary['full_return_order_rate'] ?? 'N/A',
                    $summary['partial_refund_order_incidence'] ?? 'N/A',
                    $summary['refund_return_order_rate'] ?? 'N/A',
                    $summary['active_approved_commission'],
                    $summary['active_pending_commission'],
                    $summary['period_net_commission'],
                ]);

                if (!app()->runningUnitTests()) {
                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();
                }
            }

            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control' => 'no-store, no-cache, must-revalidate, post-check=0, pre-check=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    /**
     * Stream HLA team downline performance to CSV with bounded memory.
     *
     * @param User $hla
     * @param array $teamAnalytics Result from AgentAnalyticsService::getTeamAnalytics()
     * @return StreamedResponse
     */
    public function exportHlaTeamAnalytics(User $hla, array $teamAnalytics): StreamedResponse
    {
        $targetMonth = $teamAnalytics['period']['targetMonth'];
        $filename = "team_analytics_{$hla->referral_code}_{$targetMonth}_" . now()->format('Ymd_His') . ".csv";

        return new StreamedResponse(function () use ($hla, $teamAnalytics) {
            $handle = fopen('php://output', 'w');

            // Header & Metadata
            fputcsv($handle, ['# AURA LUXE HLA DOWNLINE TEAM ANALYTICS']);
            fputcsv($handle, ['# HLA Sponsor:', $hla->name, 'Code:', $hla->referral_code]);
            fputcsv($handle, ['# Period:', $teamAnalytics['period']['periodLabel']]);
            fputcsv($handle, ['# Total Downline Members:', $teamAnalytics['downline_count']]);
            fputcsv($handle, []);

            // Column Headers
            fputcsv($handle, [
                'Member ID',
                'Member Name',
                'Referral Code',
                'Role',
                'Status',
                'Target Month',
                'Sales Target (LKR)',
                'Target Attainment (%)',
                'Gross Product Sales (LKR)',
                'Product Discounts (LKR)',
                'Full-Return Order Value (Turnover Basis LKR)',
                'Fulfilled Partial Refunds (LKR)',
                'Total Commercial Deductions (LKR)',
                'Net Product Sales (LKR)',
                'Placed Orders',
                'Fulfilled Orders',
                'Commercial Gross AOV (LKR)',
                'Net AOV (LKR)',
                'Active Approved Commission (LKR)',
                'Active Pending Commission (LKR)',
                'Period Net Commission Activity (LKR)',
            ]);

            foreach ($teamAnalytics['downline_members'] as $m) {
                $metrics = $m['metrics'];
                fputcsv($handle, [
                    $m['id'],
                    $m['name'],
                    $m['referral_code'],
                    $m['role'],
                    $m['status'],
                    $teamAnalytics['period']['targetMonth'],
                    $metrics['monthly_target'] ?? 'N/A',
                    $metrics['target_attainment_pct'] ?? 'N/A',
                    $metrics['gross_product_sales'],
                    $metrics['product_discounts'],
                    $metrics['full_return_order_value'],
                    $metrics['fulfilled_partial_refund_amount'],
                    $metrics['total_commercial_deductions'],
                    $metrics['net_product_sales'],
                    $metrics['total_placed_orders'],
                    $metrics['total_fulfilled_orders'],
                    $metrics['commercial_gross_aov'] ?? 'N/A',
                    $metrics['net_aov'] ?? 'N/A',
                    $metrics['active_approved_commission'],
                    $metrics['active_pending_commission'],
                    $metrics['period_net_commission'],
                ]);
            }

            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control' => 'no-store, no-cache, must-revalidate, post-check=0, pre-check=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }
}
