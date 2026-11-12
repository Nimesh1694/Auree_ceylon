<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\UserClosure;
use App\Services\AgentHierarchyService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TeamController extends Controller
{
    public function __construct(
        private readonly AgentHierarchyService $hierarchyService
    ) {}

    /**
     * Display the agent's authorized upline and downline network.
     * Enforces privacy: NIC, bank details, and wallet balance of downline are masked.
     */
    public function index(Request $request): Response
    {
        $agent = $request->user();

        // 1. Upline chain
        $upline = $this->hierarchyService->getUpline($agent)
            ->map(function ($ancestor) {
                return [
                    'id' => $ancestor->id,
                    'name' => $ancestor->name,
                    'referral_code' => $ancestor->referral_code,
                    'role' => $ancestor->role->value ?? (string) $ancestor->role,
                    'status' => $ancestor->status->value ?? (string) $ancestor->status,
                ];
            });

        // 2. Multi-level downline with depth
        $closures = UserClosure::with('descendant')
            ->where('ancestor_id', $agent->id)
            ->where('depth', '>', 0)
            ->orderBy('depth')
            ->get();

        $downline = $closures->map(function ($closure) {
            $user = $closure->descendant;
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'referral_code' => $user->referral_code,
                'role' => $user->role->value ?? (string) $user->role,
                'status' => $user->status->value ?? (string) $user->status,
                'depth' => $closure->depth,
                'joined_at' => $user->created_at?->toIso8601String(),
            ];
        });

        return Inertia::render('Agent/Team', [
            'upline' => $upline,
            'downline' => $downline,
            'directCount' => $agent->children()->count(),
            'totalCount' => $downline->count(),
        ]);
    }
}

