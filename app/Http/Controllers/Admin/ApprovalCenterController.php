<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CommissionRuleChange;
use App\Models\HierarchyChangeRequest;
use App\Models\PayoutRequest;
use App\Models\RolePermissionRequest;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ApprovalCenterController extends Controller
{
    /**
     * Display the centralized Business Approval Center aggregating all 4 approval types.
     */
    public function index(Request $request): Response
    {
        $activeTab = $request->input('tab', 'payouts');

        // 1. Pending Payout Requests
        $pendingPayouts = PayoutRequest::with('agent.agentProfile')
            ->where('status', PayoutRequest::STATUS_PENDING)
            ->latest('id')
            ->get();

        // 2. Pending Commission Rule Changes
        $pendingRuleChanges = CommissionRuleChange::with(['requester', 'requestedBy'])
            ->where('status', CommissionRuleChange::STATUS_PENDING)
            ->latest('id')
            ->get();

        // 3. Pending Hierarchy Move Requests
        $pendingHierarchyMoves = HierarchyChangeRequest::with(['agent.agentProfile', 'currentParent', 'requestedParent', 'requestedBy'])
            ->where('status', HierarchyChangeRequest::STATUS_PENDING)
            ->latest('id')
            ->get();

        // 4. Pending Role Permission Requests
        $pendingPermissionRequests = RolePermissionRequest::with(['role', 'permission', 'requester'])
            ->where('status', 'pending')
            ->latest('id')
            ->get();

        $counts = [
            'payouts' => $pendingPayouts->count(),
            'commission_rules' => $pendingRuleChanges->count(),
            'hierarchy_moves' => $pendingHierarchyMoves->count(),
            'permission_requests' => $pendingPermissionRequests->count(),
            'total' => $pendingPayouts->count() + $pendingRuleChanges->count() + $pendingHierarchyMoves->count() + $pendingPermissionRequests->count(),
        ];

        return Inertia::render('Admin/Approvals/Index', [
            'activeTab' => $activeTab,
            'counts' => $counts,
            'payouts' => $pendingPayouts,
            'commissionRules' => $pendingRuleChanges,
            'hierarchyMoves' => $pendingHierarchyMoves,
            'permissionRequests' => $pendingPermissionRequests,
        ]);
    }
}

