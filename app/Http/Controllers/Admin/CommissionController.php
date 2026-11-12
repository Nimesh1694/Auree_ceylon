<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CommissionLedgerEntry;
use App\Models\CommissionRuleChange;
use App\Models\Setting;
use App\Services\CommissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CommissionController extends Controller
{
    public function __construct(
        private readonly CommissionService $commissionService
    ) {}

    /**
     * Display the company-wide commission ledger with filters and statistics.
     */
    public function ledger(Request $request): Response
    {
        $query = CommissionLedgerEntry::with(['order', 'agent', 'sellingAgent'])
            ->latest('id');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('type')) {
            $query->where('commission_type', $request->input('type'));
        }

        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('entry_number', 'like', "%{$search}%")
                    ->orWhereHas('order', fn ($oq) => $oq->where('order_number', 'like', "%{$search}%"))
                    ->orWhereHas('agent', fn ($aq) => $aq->where('name', 'like', "%{$search}%")->orWhere('referral_code', 'like', "%{$search}%"));
            });
        }

        $entries = $query->paginate(20)->withQueryString();

        // Calculate summary metrics across the ledger
        $stats = [
            'total_pending' => (float) CommissionLedgerEntry::pendingActive()->sum('calculated_amount'),
            'total_approved' => (float) CommissionLedgerEntry::approvedActive()->sum('calculated_amount'),
            'total_reversed' => (float) CommissionLedgerEntry::where('commission_type', CommissionLedgerEntry::TYPE_REVERSAL)->sum('calculated_amount'),
            'total_count' => CommissionLedgerEntry::count(),
        ];

        return Inertia::render('Admin/Commissions/Ledger', [
            'entries' => $entries,
            'filters' => $request->only(['status', 'type', 'search']),
            'stats' => $stats,
        ]);
    }

    /**
     * Display current configurable commission rules and modification history.
     */
    public function rules(): Response
    {
        $rules = [
            'direct_agent_commission_percent' => (float) Setting::get('direct_agent_commission_percent', 25.0),
            'upline_agent_commission_percent' => (float) Setting::get('upline_agent_commission_percent', 10.0),
            'max_commission_percent_per_order' => (float) Setting::get('max_commission_percent_per_order', 35.0),
            'commission_return_period_days' => (int) Setting::get('commission_return_period_days', 7),
        ];

        $changeRequests = CommissionRuleChange::with(['requester', 'reviewer', 'requestedBy', 'reviewedBy'])
            ->latest('id')
            ->get();

        return Inertia::render('Admin/Commissions/Rules', [
            'rules' => $rules,
            'changeRequests' => $changeRequests,
        ]);
    }

    /**
     * Request a change to a commission rule (requires Owner approval).
     */
    public function requestRuleChange(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'rule_key' => 'required|string|in:direct_agent_commission_percent,upline_agent_commission_percent,max_commission_percent_per_order,commission_return_period_days',
            'new_value' => 'required|numeric|min:0|max:100',
            'reason' => 'required|string|min:5|max:500',
        ]);

        $currentValue = Setting::get($validated['rule_key']);

        $change = CommissionRuleChange::create([
            'rule_key' => $validated['rule_key'],
            'old_value' => (string) $currentValue,
            'new_value' => (string) $validated['new_value'],
            'requested_by' => $request->user()->id,
            'status' => CommissionRuleChange::STATUS_PENDING,
            'reason' => $validated['reason'],
        ]);

        AuditLog::log('commission_rule_change_requested', $change, null, [
            'rule_key' => $validated['rule_key'],
            'from' => $currentValue,
            'to' => $validated['new_value'],
        ]);

        return back()->with('success', 'Commission rule change requested. Executive Owner approval is required.');
    }

    /**
     * Review and approve or reject a commission rule change request.
     * Owner approval strictly mandatory.
     */
    public function reviewRuleChange(Request $request, CommissionRuleChange $changeRequest): RedirectResponse
    {
        $user = $request->user();
        if (!$user->isOwner()) {
            abort(403, 'Only the executive company Owner can approve commission rule changes.');
        }

        $validated = $request->validate([
            'action' => 'required|string|in:approve,reject',
            'review_notes' => 'nullable|string|max:500',
        ]);

        if ($changeRequest->status !== CommissionRuleChange::STATUS_PENDING) {
            return back()->with('error', 'This rule change request has already been reviewed.');
        }

        if ($validated['action'] === 'approve') {
            $type = in_array($changeRequest->rule_key, ['commission_return_period_days'], true) ? 'integer' : 'float';
            Setting::set($changeRequest->rule_key, $changeRequest->new_value, 'commission', $type);

            $changeRequest->update([
                'status' => CommissionRuleChange::STATUS_APPROVED,
                'reviewed_by' => $user->id,
                'reviewed_at' => now(),
                'review_notes' => $validated['review_notes'] ?? null,
            ]);

            AuditLog::log('commission_rule_change_approved', $changeRequest, null, [
                'rule_key' => $changeRequest->rule_key,
                'new_value' => $changeRequest->new_value,
                'approved_by' => $user->id,
            ]);

            return back()->with('success', "Commission rule {$changeRequest->rule_key} successfully updated to {$changeRequest->new_value}.");
        } else {
            $changeRequest->update([
                'status' => CommissionRuleChange::STATUS_REJECTED,
                'reviewed_by' => $user->id,
                'reviewed_at' => now(),
                'review_notes' => $validated['review_notes'] ?? null,
            ]);

            AuditLog::log('commission_rule_change_rejected', $changeRequest, null, [
                'rule_key' => $changeRequest->rule_key,
                'rejected_by' => $user->id,
            ]);

            return back()->with('success', 'Commission rule change request was rejected.');
        }
    }

    /**
     * Manually trigger processing and approval of all matured, eligible commissions.
     */
    public function processEligible(): RedirectResponse
    {
        $approvedCount = $this->commissionService->approveEligibleCommissions();

        return back()->with('success', "Processed eligible commissions: {$approvedCount} entries approved.");
    }
}
