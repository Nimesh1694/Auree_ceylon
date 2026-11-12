<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\CommissionLedgerEntry;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CommissionController extends Controller
{
    /**
     * Display the authenticated agent's commission earnings and ledger history.
     * Scoped strictly to the logged-in agent.
     */
    public function index(Request $request): Response
    {
        $agent = $request->user();

        $query = CommissionLedgerEntry::where('agent_id', $agent->id)
            ->with(['order.delivery', 'sellingAgent'])
            ->latest('id');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('type')) {
            $query->where('commission_type', $request->input('type'));
        }

        $entries = $query->paginate(15)->withQueryString();

        // Calculate summary metrics for this specific agent
        $baseQuery = CommissionLedgerEntry::where('agent_id', $agent->id);

        $stats = [
            'pending_amount' => (float) (clone $baseQuery)->pendingActive()->sum('calculated_amount'),
            'approved_amount' => (float) (clone $baseQuery)->approvedActive()->sum('calculated_amount'),
            'reversed_amount' => (float) (clone $baseQuery)->where('commission_type', CommissionLedgerEntry::TYPE_REVERSAL)->sum('calculated_amount'),
            'wallet_balance' => (float) ($agent->agentProfile?->wallet_balance ?? 0.00),
            'total_earned' => (float) ($agent->agentProfile?->total_earned ?? 0.00),
            'total_entries_count' => (clone $baseQuery)->count(),
        ];

        return Inertia::render('Agent/Commissions/Index', [
            'entries' => $entries,
            'filters' => $request->only(['status', 'type']),
            'stats' => $stats,
        ]);
    }
}

