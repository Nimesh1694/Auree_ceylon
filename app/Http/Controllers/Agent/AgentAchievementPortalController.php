<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AgentAchievementPortalService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AgentAchievementPortalController extends Controller
{
    public function __construct(
        protected AgentAchievementPortalService $portalService
    ) {}

    /**
     * Display the authenticated agent's achievements, progress, and downline honors.
     */
    public function index(Request $request): Response
    {
        $agent = $request->user();
        if (!$agent || !$agent->isAgent() || !$agent->isActive()) {
            abort(403, 'Unauthorized access to agent achievement portal.');
        }

        $filters = [
            'status' => $request->input('status'),
            'category' => $request->input('category'),
            'page' => $request->input('page', 1),
            'tab' => $request->input('tab', 'showcase'),
        ];

        $payload = $this->portalService->getPersonalAchievementPayload($agent, $filters);

        return Inertia::render('Agent/Achievements/Index', $payload);
    }

    /**
     * Display achievement records for an authorized direct downline Low-Level Agent (HLA only).
     */
    public function showDownlineMember(Request $request, User $member): Response
    {
        $actor = $request->user();
        if (!$actor || !$actor->isHighLevelAgent() || !$actor->isActive()) {
            abort(403, 'Unauthorized: Downline achievement inspection is restricted to active High-Level Agents.');
        }

        $payload = $this->portalService->getDownlineMemberDetailPayload($actor, $member);

        return Inertia::render('Agent/Achievements/DownlineMember', $payload);
    }
}

