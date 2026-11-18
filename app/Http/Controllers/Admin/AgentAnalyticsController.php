<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAgentSalesTargetRequest;
use App\Http\Requests\Admin\UpdateAgentSalesTargetRequest;
use App\Models\AgentSalesTarget;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\AgentAnalyticsService;
use App\Services\AgentSalesTargetService;
use App\Services\AnalyticsExportService;
use App\Services\PeriodBoundaryService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AgentAnalyticsController extends Controller
{
    public function __construct(
        protected AgentAnalyticsService $analyticsService,
        protected AgentSalesTargetService $targetService,
        protected AnalyticsExportService $exportService,
        protected PeriodBoundaryService $boundaryService
    ) {}

    /**
     * Display administrative agent performance leaderboard & overview.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        if (!$user || !$user->can('agents.analytics.view')) {
            abort(403, 'Unauthorized access to agent performance analytics.');
        }

        $period = $request->input('period', 'this_month');
        $customStart = $request->input('start_date');
        $customEnd = $request->input('end_date');

        $boundaries = $this->boundaryService->resolvePeriodUtcBoundaries($period, $customStart, $customEnd);

        $filters = [
            'period' => $period,
            'role' => $request->input('role', 'all'),
            'search' => trim($request->input('search', '')),
            'start_date' => $customStart,
            'end_date' => $customEnd,
        ];

        $leaderboard = $this->analyticsService->getAdminLeaderboard($boundaries, $filters, 15);

        // Active agents list for modal dropdown when creating targets
        $activeAgents = User::whereIn('role', ['high_level_agent', 'low_level_agent'])
            ->where('status', 'active')
            ->select(['id', 'name', 'referral_code', 'role'])
            ->orderBy('name')
            ->get();

        return Inertia::render('Admin/Agents/Analytics/Index', [
            'period' => $boundaries,
            'filters' => $filters,
            'leaderboard' => $leaderboard,
            'activeAgents' => $activeAgents,
            'canManageTargets' => $user->can('agents.targets.manage'),
            'canExport' => $user->can('agents.analytics.export'),
        ]);
    }

    /**
     * Display deep performance analytics for a specific agent.
     */
    public function show(Request $request, User $agent): Response
    {
        $user = $request->user();
        if (!$user || !$user->can('agents.analytics.view')) {
            abort(403, 'Unauthorized access to agent performance analytics.');
        }

        if (!$agent->isAgent()) {
            abort(404, 'The requested user is not an agent.');
        }

        $period = $request->input('period', 'this_month');
        $customStart = $request->input('start_date');
        $customEnd = $request->input('end_date');

        $boundaries = $this->boundaryService->resolvePeriodUtcBoundaries($period, $customStart, $customEnd);

        // 1. Personal Analytics
        $personal = $this->analyticsService->getPersonalAnalytics($agent, $boundaries);

        // 2. HLA Team Analytics (if High-Level Agent)
        $team = null;
        if ($agent->isHighLevelAgent()) {
            $team = $this->analyticsService->getTeamAnalytics($agent, $boundaries);
        }

        // 3. Daily Sales Trend Points
        $trends = $this->analyticsService->getDailyPerformanceTrends(
            $agent->id,
            $agent->referral_code,
            $boundaries['startUtc'],
            $boundaries['endUtc']
        );

        // 4. Sales Target History
        $targetHistory = AgentSalesTarget::where('agent_id', $agent->id)
            ->with('creator:id,name,email')
            ->orderBy('target_month', 'desc')
            ->limit(12)
            ->get();

        return Inertia::render('Admin/Agents/Analytics/Show', [
            'agent' => [
                'id' => $agent->id,
                'name' => $agent->name,
                'email' => $agent->email,
                'phone' => $agent->phone,
                'role' => $agent->getRoleValue(),
                'status' => $agent->status,
                'referral_code' => $agent->referral_code,
                'created_at' => $agent->created_at?->toIso8601String(),
                'parent' => $agent->parent ? [
                    'id' => $agent->parent->id,
                    'name' => $agent->parent->name,
                    'referral_code' => $agent->parent->referral_code,
                ] : null,
            ],
            'period' => $boundaries,
            'personal' => $personal,
            'team' => $team,
            'trends' => $trends,
            'targetHistory' => $targetHistory,
            'canManageTargets' => $user->can('agents.targets.manage'),
            'canExport' => $user->can('agents.analytics.export'),
        ]);
    }

    /**
     * Stream CSV export of administrative agent performance leaderboard.
     */
    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();
        if (!$user || !$user->can('agents.analytics.export')) {
            abort(403, 'Unauthorized to export agent performance analytics.');
        }

        $period = $request->input('period', 'this_month');
        $customStart = $request->input('start_date');
        $customEnd = $request->input('end_date');

        $boundaries = $this->boundaryService->resolvePeriodUtcBoundaries($period, $customStart, $customEnd);

        $filters = [
            'role' => $request->input('role', 'all'),
            'search' => trim($request->input('search', '')),
        ];

        return $this->exportService->exportAdminLeaderboard($boundaries, $filters);
    }

    /**
     * Create a new monthly sales target for an agent.
     */
    public function storeTarget(StoreAgentSalesTargetRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $user = $request->user();
        $currentColomboMonth = Carbon::now(PeriodBoundaryService::TIMEZONE)->format('Y-m');

        if ($validated['target_month'] < $currentColomboMonth) {
            if ($request->boolean('is_override')) {
                if (!$user->isOwner()) {
                    abort(403, 'Historical target creation is restricted to the Business Owner.');
                }
                $overrideReason = (string) $request->input('override_reason', '');
                $this->targetService->createHistoricalOverrideTarget($validated, $overrideReason, $user);
                return back()->with('success', 'Historical monthly sales target created successfully via Owner override.');
            }
            abort(403, 'Standard target creation is not permitted for closed historical months.');
        }

        $this->targetService->createTarget($validated, $user);

        return back()->with('success', 'Monthly sales target created successfully.');
    }

    /**
     * Update an existing monthly sales target.
     */
    public function updateTarget(UpdateAgentSalesTargetRequest $request, AgentSalesTarget $target): RedirectResponse
    {
        $validated = $request->validated();
        $user = $request->user();
        $currentColomboMonth = Carbon::now(PeriodBoundaryService::TIMEZONE)->format('Y-m');

        if ($target->target_month < $currentColomboMonth) {
            if ($request->boolean('is_override')) {
                if (!$user->isOwner()) {
                    abort(403, 'Historical target override is restricted to the Business Owner.');
                }
                $this->targetService->overrideHistoricalTarget(
                    $target,
                    $validated['sales_target'],
                    $validated['override_reason'],
                    $user
                );
                return back()->with('success', 'Historical sales target overridden successfully.');
            }
            abort(403, 'Targets in closed historical periods are locked. Owner override required.');
        }

        $this->targetService->updateTarget($target, $validated, $user);

        return back()->with('success', 'Monthly sales target updated successfully.');
    }

    /**
     * Retrieve paginated chronological audit history for a sales target.
     */
    public function targetHistory(AgentSalesTarget $target, Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user?->can('agents.targets.manage')) {
            abort(403, 'Unauthorized access to target audit history.');
        }

        $history = AuditLog::where('auditable_type', AgentSalesTarget::class)
            ->where('auditable_id', $target->id)
            ->with('user:id,name,role')
            ->orderByDesc('id')
            ->paginate(15);

        return response()->json($history);
    }
}

