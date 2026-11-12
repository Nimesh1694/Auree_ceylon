<?php

namespace App\Http\Controllers\Agent;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\AgentHierarchyService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly AgentHierarchyService $hierarchyService
    ) {}

    /**
     * Display the Agent Portal dashboard.
     */
    public function index(Request $request): Response
    {
        $agent = $request->user();
        $agent->loadMissing('agentProfile', 'parent');

        // 1. Orders physically placed by the agent
        $ownOrdersQuery = Order::where('agent_id', $agent->id);
        $ownOrdersCount = (clone $ownOrdersQuery)->count();
        $ownOrdersSales = (float) (clone $ownOrdersQuery)->whereIn('status', ['confirmed', 'packed', 'shipped', 'delivered', 'cash_collected', 'completed'])->sum('total_amount');

        // 2. Customer orders attributed via referral code (excluding orders placed by agent themselves)
        $referralOrdersQuery = Order::where('referral_code', $agent->referral_code)
            ->where(function ($q) use ($agent) {
                $q->whereNull('agent_id')
                    ->orWhere('agent_id', '!=', $agent->id);
            });
        $referralOrdersCount = (clone $referralOrdersQuery)->count();
        $referralOrdersSales = (float) (clone $referralOrdersQuery)->whereIn('status', ['confirmed', 'packed', 'shipped', 'delivered', 'cash_collected', 'completed'])->sum('total_amount');

        // 3. Network Hierarchy metrics
        $directDownlineCount = $agent->children()->where('status', UserStatus::ACTIVE)->count();
        $allDownline = $this->hierarchyService->getDownline($agent);
        $totalDownlineCount = $allDownline->where('status', UserStatus::ACTIVE)->count();
        $downlineIds = $allDownline->pluck('id')->toArray();

        // 4. Downline team orders
        $teamOrdersCount = 0;
        $teamOrdersSales = 0.00;
        if (!empty($downlineIds)) {
            $teamQuery = Order::whereIn('agent_id', $downlineIds);
            $teamOrdersCount = (clone $teamQuery)->count();
            $teamOrdersSales = (float) (clone $teamQuery)->whereIn('status', ['confirmed', 'packed', 'shipped', 'delivered', 'cash_collected', 'completed'])->sum('total_amount');
        }

        // 5. Recent orders placed by this agent
        $recentOrders = (clone $ownOrdersQuery)
            ->with(['delivery', 'items'])
            ->latest('id')
            ->take(5)
            ->get();

        return Inertia::render('Agent/Dashboard', [
            'agent' => $agent,
            'stats' => [
                'own_orders_count' => $ownOrdersCount,
                'own_orders_sales' => $ownOrdersSales,
                'referral_orders_count' => $referralOrdersCount,
                'referral_orders_sales' => $referralOrdersSales,
                'direct_downline_count' => $directDownlineCount,
                'total_downline_count' => $totalDownlineCount,
                'team_orders_count' => $teamOrdersCount,
                'team_orders_sales' => $teamOrdersSales,
                'wallet_balance' => (float) ($agent->agentProfile?->wallet_balance ?? 0.00),
                'kyc_status' => $agent->agentProfile?->kyc_status ?? 'pending',
            ],
            'recentOrders' => $recentOrders,
        ]);
    }
}

