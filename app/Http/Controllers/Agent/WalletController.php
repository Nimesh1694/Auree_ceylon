<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\PayoutRequest;
use App\Models\WalletTransaction;
use App\Services\PayoutService;
use App\Services\WalletService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class WalletController extends Controller
{
    public function __construct(
        private readonly WalletService $walletService,
        private readonly PayoutService $payoutService
    ) {}

    /**
     * Display the authenticated agent's wallet dashboard, balances, and transaction history.
     */
    public function index(Request $request): Response
    {
        $agent = $request->user();

        $summary = $this->walletService->getWalletSummary($agent);

        // Payout Requests query
        $payoutRequests = PayoutRequest::where('agent_id', $agent->id)
            ->latest('id')
            ->paginate(10, ['*'], 'payouts_page')
            ->withQueryString();

        // Wallet Transactions query
        $txQuery = WalletTransaction::where('agent_id', $agent->id)->latest('created_at')->latest('id');

        if ($request->filled('type')) {
            $txQuery->where('transaction_type', $request->input('type'));
        }

        if ($request->filled('from_date')) {
            $txQuery->whereDate('created_at', '>=', $request->input('from_date'));
        }

        if ($request->filled('to_date')) {
            $txQuery->whereDate('created_at', '<=', $request->input('to_date'));
        }

        $transactions = $txQuery->paginate(15, ['*'], 'tx_page')->withQueryString();

        return Inertia::render('Agent/Wallet/Index', [
            'summary' => $summary,
            'payoutRequests' => $payoutRequests,
            'transactions' => $transactions,
            'filters' => $request->only(['type', 'from_date', 'to_date']),
        ]);
    }

    /**
     * Submit a commission payout request.
     */
    public function requestPayout(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:1|max:5000000',
            'agent_notes' => 'nullable|string|max:500',
        ]);

        try {
            $payout = $this->payoutService->createPayoutRequest(
                $request->user(),
                $validated['amount'],
                $validated['agent_notes'] ?? null
            );

            return back()->with('success', "Payout request #{$payout->reference_number} for LKR " . number_format((float) $payout->amount, 2) . " submitted successfully for Owner review.");
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Cancel an agent's own pending payout request.
     */
    public function cancelPayout(Request $request, PayoutRequest $payoutRequest): RedirectResponse
    {
        try {
            $this->payoutService->cancelPayoutRequest($payoutRequest, $request->user());

            return back()->with('success', "Payout request #{$payoutRequest->reference_number} cancelled. Reserved funds released back to your available balance.");
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Display the agent's official monthly statement.
     */
    public function statements(Request $request): Response
    {
        $agent = $request->user();

        $year = (int) $request->input('year', now()->year);
        $month = (int) $request->input('month', now()->month);

        if ($month < 1 || $month > 12) {
            $month = (int) now()->month;
        }

        if ($year < 2024 || $year > now()->year) {
            $year = (int) now()->year;
        }

        $statement = $this->walletService->getStatement($agent, $year, $month);

        return Inertia::render('Agent/Wallet/Statements', [
            'statement' => $statement,
            'selectedYear' => $year,
            'selectedMonth' => $month,
        ]);
    }

    /**
     * Secure download for agent's own payout receipt (SEC-06-02).
     */
    public function downloadReceipt(Request $request, PayoutRequest $payoutRequest): \Symfony\Component\HttpFoundation\Response
    {
        if ($payoutRequest->agent_id !== $request->user()->id) {
            abort(403, 'You can only view receipts for your own payouts.');
        }

        if (empty($payoutRequest->bank_transfer_receipt_path)) {
            abort(404, 'Receipt not found for this payout.');
        }

        $path = $payoutRequest->bank_transfer_receipt_path;

        if (\Illuminate\Support\Facades\Storage::disk('local')->exists($path)) {
            return \Illuminate\Support\Facades\Storage::disk('local')->download($path);
        }

        if (\Illuminate\Support\Facades\Storage::disk('public')->exists($path)) {
            return \Illuminate\Support\Facades\Storage::disk('public')->download($path);
        }

        abort(404, 'Receipt file does not exist on disk.');
    }
}

