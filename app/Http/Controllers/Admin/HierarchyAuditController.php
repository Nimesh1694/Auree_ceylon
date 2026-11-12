<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\HierarchyIntegrityAuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HierarchyAuditController extends Controller
{
    public function __construct(
        protected HierarchyIntegrityAuditService $auditService
    ) {}

    /**
     * Authorize access to the Hierarchy Integrity Auditor.
     * Rules:
     * - Owner: Always permitted.
     * - Super Admin: Permitted with explicit 'agents.hierarchy.integrity' or 'system.health.view'.
     * - Agent Manager: Blocked unless explicitly granted 'agents.hierarchy.integrity'.
     * - All other roles & customers: Blocked (403).
     */
    protected function authorizeAudit(Request $request): User
    {
        $user = $request->user();

        if (!$user) {
            abort(401, 'Unauthenticated.');
        }

        $canAccess = $user->isOwner()
            || ($user->isSuperAdmin() && ($user->hasPermissionTo('agents.hierarchy.integrity') || $user->hasPermissionTo('system.health.view')))
            || $user->hasPermissionTo('agents.hierarchy.integrity');

        if (!$canAccess) {
            abort(403, 'Unauthorized access to Hierarchy Integrity Auditor.');
        }

        return $user;
    }

    /**
     * Display the Hierarchy Integrity Auditor page.
     */
    public function index(Request $request): Response
    {
        $this->authorizeAudit($request);

        $report = $this->auditService->scan();

        return Inertia::render('Admin/Agents/HierarchyIntegrity', [
            'initialReport' => $report,
        ]);
    }

    /**
     * Run an on-demand read-only integrity scan.
     */
    public function scan(Request $request): JsonResponse|RedirectResponse
    {
        $user = $this->authorizeAudit($request);

        $report = $this->auditService->scan();

        // Audit Log entry capturing scan telemetry
        AuditLog::log('hierarchy_integrity_scan_executed', null, null, [
            'status' => $report['status'],
            'total_agents' => $report['total_agents'],
            'total_closures' => $report['total_closures'],
            'total_anomalies' => $report['total_anomalies'],
            'execution_time_ms' => $report['execution_time_ms'],
            'query_count' => $report['query_count'],
            'triggered_by' => $user->id,
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'report' => $report,
            ]);
        }

        return back()->with([
            'success' => 'Hierarchy integrity scan completed successfully.',
            'report' => $report,
        ]);
    }
}
