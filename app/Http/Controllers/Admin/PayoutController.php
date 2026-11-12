<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AgentProfile;
use App\Models\CommissionLedgerEntry;
use App\Models\PayoutRequest;
use App\Services\PayoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class PayoutController extends Controller
{
    public function __construct(
        private readonly PayoutService $payoutService
    ) {}

    /**
     * Display company-wide executive financial dashboard and payout overview.
     */
    public function dashboard(Request $request): Response
    {
        $metrics = [
            'total_approved_commissions' => (float) CommissionLedgerEntry::approvedActive()->sum('calculated_amount'),
            'total_pending_commissions' => (float) CommissionLedgerEntry::pendingActive()->sum('calculated_amount'),
            'total_wallet_balances' => (float) AgentProfile::sum('wallet_balance'),
            'total_reserved_payouts' => (float) AgentProfile::sum('reserved_balance'),
            'total_outstanding_clawbacks' => (float) AgentProfile::sum('outstanding_clawback'),
            'total_completed_payouts' => (float) PayoutRequest::where('status', PayoutRequest::STATUS_PAID)->sum('amount'),
            'pending_requests_count' => PayoutRequest::where('status', PayoutRequest::STATUS_PENDING)->count(),
            'pending_requests_amount' => (float) PayoutRequest::where('status', PayoutRequest::STATUS_PENDING)->sum('amount'),
            'processing_requests_count' => PayoutRequest::whereIn('status', [PayoutRequest::STATUS_APPROVED, PayoutRequest::STATUS_PROCESSING])->count(),
            'processing_requests_amount' => (float) PayoutRequest::whereIn('status', [PayoutRequest::STATUS_APPROVED, PayoutRequest::STATUS_PROCESSING])->sum('amount'),
            'paid_requests_count' => PayoutRequest::where('status', PayoutRequest::STATUS_PAID)->count(),
            'paid_requests_amount' => (float) PayoutRequest::where('status', PayoutRequest::STATUS_PAID)->sum('amount'),
            'rejected_requests_count' => PayoutRequest::where('status', PayoutRequest::STATUS_REJECTED)->count(),
        ];

        // Recent payout activity
        $recentPayouts = PayoutRequest::with(['agent.agentProfile'])
            ->latest('id')
            ->limit(8)
            ->get();

        return Inertia::render('Admin/Payouts/Dashboard', [
            'metrics' => $metrics,
            'recentPayouts' => $recentPayouts,
        ]);
    }

    /**
     * Display management payout requests queue with filtering and search.
     */
    public function index(Request $request): Response
    {
        $query = PayoutRequest::with(['agent.agentProfile', 'reviewer', 'payer'])
            ->latest('id');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('reference_number', 'like', "%{$search}%")
                    ->orWhere('bank_transfer_reference', 'like', "%{$search}%")
                    ->orWhereHas('agent', fn ($aq) => $aq->where('name', 'like', "%{$search}%")
                        ->orWhere('referral_code', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->input('from_date'));
        }

        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->input('to_date'));
        }

        $payouts = $query->paginate(20)->withQueryString();

        $counts = [
            'pending' => PayoutRequest::where('status', PayoutRequest::STATUS_PENDING)->count(),
            'approved' => PayoutRequest::where('status', PayoutRequest::STATUS_APPROVED)->count(),
            'processing' => PayoutRequest::where('status', PayoutRequest::STATUS_PROCESSING)->count(),
            'paid' => PayoutRequest::where('status', PayoutRequest::STATUS_PAID)->count(),
            'rejected' => PayoutRequest::where('status', PayoutRequest::STATUS_REJECTED)->count(),
        ];

        return Inertia::render('Admin/Payouts/Index', [
            'payouts' => $payouts,
            'filters' => $request->only(['status', 'search', 'from_date', 'to_date']),
            'counts' => $counts,
        ]);
    }

    /**
     * Display detailed payout request record.
     */
    public function show(PayoutRequest $payoutRequest): Response
    {
        $payoutRequest->load(['agent.agentProfile', 'reviewer', 'payer', 'walletTransactions']);

        $profile = $payoutRequest->agent?->agentProfile;
        $wallet = (string) ($profile?->wallet_balance ?? '0.00');
        $reserved = (string) ($profile?->reserved_balance ?? '0.00');
        $amount = (string) $payoutRequest->amount;
        $isUnderfunded = bccomp($wallet, $amount, 2) < 0;
        $hasAggregateDeficit = bccomp($wallet, $reserved, 2) < 0;
        $aggregateShortfall = $hasAggregateDeficit ? (float) bcsub($reserved, $wallet, 2) : 0.0;

        return Inertia::render('Admin/Payouts/Show', [
            'payout' => $payoutRequest,
            'is_underfunded' => $isUnderfunded,
            'has_aggregate_deficit' => $hasAggregateDeficit,
            'aggregate_shortfall' => $aggregateShortfall,
        ]);
    }

    /**
     * Approve a payout request (Executive Owner ONLY).
     */
    public function approve(Request $request, PayoutRequest $payoutRequest): RedirectResponse
    {
        $user = $request->user();
        if (!$user->isOwner()) {
            abort(403, 'Only the executive company Owner can approve commission payout requests.');
        }

        try {
            $this->payoutService->approvePayoutRequest($payoutRequest, $user, $request->input('review_notes'));

            return back()->with('success', "Payout request #{$payoutRequest->reference_number} approved. Ready for bank transfer execution.");
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Reject a payout request (Executive Owner ONLY).
     */
    public function reject(Request $request, PayoutRequest $payoutRequest): RedirectResponse
    {
        $user = $request->user();
        if (!$user->isOwner()) {
            abort(403, 'Only the executive company Owner can reject commission payout requests.');
        }

        $validated = $request->validate([
            'rejection_reason' => 'required|string|min:5|max:500',
        ]);

        try {
            $this->payoutService->rejectPayoutRequest($payoutRequest, $user, $validated['rejection_reason']);

            return back()->with('success', "Payout request #{$payoutRequest->reference_number} rejected. Reserved funds released back to agent.");
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Mark payout as processing.
     */
    public function process(Request $request, PayoutRequest $payoutRequest): RedirectResponse
    {
        try {
            $this->payoutService->markProcessing($payoutRequest, $request->user());

            return back()->with('success', "Payout request #{$payoutRequest->reference_number} marked as processing.");
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Mark an approved or processing payout as failed by bank (OPS-06-01).
     * Releases reserved balance back to agent available balance.
     */
    public function fail(Request $request, PayoutRequest $payoutRequest): RedirectResponse
    {
        $validated = $request->validate([
            'failure_reason' => 'required|string|min:5|max:500',
        ]);

        try {
            $this->payoutService->failPayoutRequest($payoutRequest, $request->user(), $validated['failure_reason']);

            return back()->with('success', "Payout #{$payoutRequest->reference_number} marked as failed. Reserved funds returned to agent available balance.");
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Record bank transfer execution and complete payout.
     */
    public function recordPayment(Request $request, PayoutRequest $payoutRequest): RedirectResponse
    {
        $validated = $request->validate([
            'bank_transfer_reference' => 'required|string|min:3|max:100',
            'bank_transfer_notes' => 'nullable|string|max:500',
            'receipt' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:5120',
        ]);

        $receiptPath = null;
        if ($request->hasFile('receipt')) {
            // Secure private storage (SEC-06-02)
            $receiptPath = $request->file('receipt')->store('payout_receipts', 'local');
        }

        try {
            $this->payoutService->recordPayment($payoutRequest, $request->user(), [
                'bank_transfer_reference' => $validated['bank_transfer_reference'],
                'bank_transfer_notes' => $validated['bank_transfer_notes'] ?? null,
                'bank_transfer_receipt_path' => $receiptPath,
            ]);

            return back()->with('success', "Bank transfer recorded successfully. Payout #{$payoutRequest->reference_number} completed.");
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Secure controller-authenticated download for bank transfer receipts (SEC-06-02).
     */
    public function downloadReceipt(Request $request, PayoutRequest $payoutRequest): \Symfony\Component\HttpFoundation\Response
    {
        $user = $request->user();
        if (!$user->isOwner() && $user->role !== \App\Enums\UserRole::ACCOUNTANT) {
            abort(403, 'Unauthorized access to bank remittance receipt.');
        }

        if (empty($payoutRequest->bank_transfer_receipt_path)) {
            abort(404, 'Receipt not found for this payout.');
        }

        $path = $payoutRequest->bank_transfer_receipt_path;

        if (\Illuminate\Support\Facades\Storage::disk('local')->exists($path)) {
            return \Illuminate\Support\Facades\Storage::disk('local')->download($path);
        }

        // Backward compatibility fallback for historical uploads
        if (\Illuminate\Support\Facades\Storage::disk('public')->exists($path)) {
            return \Illuminate\Support\Facades\Storage::disk('public')->download($path);
        }

        abort(404, 'Receipt file does not exist on disk.');
    }
}

