<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Http\Requests\Agent\StoreDownlineTargetRequest;
use App\Http\Requests\Agent\UpdateDownlineTargetRequest;
use App\Models\AgentSalesTarget;
use App\Services\AgentAnalyticsService;
use App\Services\AgentSalesTargetService;
use App\Services\AnalyticsExportService;
use App\Services\PeriodBoundaryService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AnalyticsController extends Controller
{
    public function __construct(
        protected AgentAnalyticsService $analyticsService,
        protected AgentSalesTargetService $targetService,
        protected AnalyticsExportService $exportService,
        protected PeriodBoundaryService $boundaryService
    ) {}

    /**
     * Display personal performance analytics and HLA team metrics.
     */
    public function index(Request $request): Response
    {
        $agent = $request->user();
        if (!$agent || !$agent->isAgent()) {
            abort(403, 'Unauthorized access to agent analytics portal.');
        }

        $period = $request->input('period', 'this_month');
        $customStart = $request->input('start_date');
        $customEnd = $request->input('end_date');

        $boundaries = $this->boundaryService->resolvePeriodUtcBoundaries($period, $customStart, $customEnd);

        // 1. Personal Performance Analytics
        $personal = $this->analyticsService->getPersonalAnalytics($agent, $boundaries);

        // 2. HLA Downline Team Analytics (exclusive to High-Level Agents)
        $team = null;
        if ($agent->isHighLevelAgent()) {
            $team = $this->analyticsService->getTeamAnalytics($agent, $boundaries);
        }

        // 3. Historical Daily Performance Trend Points
        $trends = $this->analyticsService->getDailyPerformanceTrends(
            $agent->id,
            $agent->referral_code,
            $boundaries['startUtc'],
            $boundaries['endUtc']
        );

        return Inertia::render('Agent/Analytics/Index', [
            'period' => $boundaries['periodKey'] ?? $period,
            'periodBoundaries' => $boundaries,
            'periodLabel' => $boundaries['label'] ?? '',
            'personal' => $personal,
            'team' => $team,
            'downline' => $team ? array_merge($team['team_summary'], ['active_agents_count' => $team['downline_count']]) : null,
            'downlineMembers' => $team ? $team['downline_members'] : [],
            'trends' => $trends,
            'dailyTrends' => $trends,
            'isHla' => $agent->isHighLevelAgent(),
            'currentMonth' => Carbon::now(PeriodBoundaryService::TIMEZONE)->format('Y-m'),
        ]);
    }

    /**
     * Assign a monthly sales target to an active downline Low-Level Agent.
     */
    public function storeDownlineTarget(StoreDownlineTargetRequest $request): RedirectResponse
    {
        $actor = $request->user();
        $validated = $request->validated();

        $this->targetService->assignDownlineTarget(
            $actor,
            (int) $validated['agent_id'],
            $validated['target_month'],
            $validated['sales_target'],
            $validated['reason']
        );

        return redirect()->back()->with('success', 'Downline sales target successfully assigned.');
    }

    /**
     * Update an existing monthly sales target for an active downline Low-Level Agent.
     */
    public function updateDownlineTarget(UpdateDownlineTargetRequest $request, AgentSalesTarget $target): RedirectResponse
    {
        $actor = $request->user();
        $validated = $request->validated();

        $this->targetService->updateDownlineTarget($actor, $target, [
            'sales_target' => $validated['sales_target'],
            'reason' => $validated['reason'],
        ]);

        return redirect()->back()->with('success', 'Downline sales target successfully updated.');
    }

    /**
     * Stream CSV export of HLA downline team analytics.
     */
    public function export(Request $request): StreamedResponse
    {
        $agent = $request->user();
        if (!$agent || !$agent->isAgent()) {
            abort(403, 'Unauthorized access to agent analytics export.');
        }

        if (!$agent->isHighLevelAgent()) {
            abort(403, 'Team downline export is restricted to High-Level Agents.');
        }

        $period = $request->input('period', 'this_month');
        $customStart = $request->input('start_date');
        $customEnd = $request->input('end_date');

        $boundaries = $this->boundaryService->resolvePeriodUtcBoundaries($period, $customStart, $customEnd);
        $teamAnalytics = $this->analyticsService->getTeamAnalytics($agent, $boundaries);

        return $this->exportService->exportHlaTeamAnalytics($agent, $teamAnalytics);
    }
}
