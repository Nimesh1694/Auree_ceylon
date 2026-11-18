<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ApproveAchievementRequest;
use App\Http\Requests\Admin\RejectAchievementRequest;
use App\Http\Requests\Admin\StoreAchievementNominationRequest;
use App\Models\AgentAchievement;
use App\Models\AgentAchievementRequest;
use App\Models\User;
use App\Services\AgentAchievementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AgentAchievementRequestController extends Controller
{
    public function __construct(
        protected AgentAchievementService $achievementService
    ) {}

    /**
     * Display a listing of achievement requests.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', AgentAchievement::class);

        $user = $request->user();
        $isOwner = $user->isOwner();

        $query = AgentAchievementRequest::with([
            'achievement',
            'agent:id,name,email,role',
            'requester:id,name,email,role',
            'decider:id,name,email,role',
        ]);

        // Branch-level filter: Non-owners only see requests they submitted or downline agents
        if (!$isOwner) {
            $query->where('requested_by', $user->id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('agent', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $requests = $query->latest()->paginate(15)->withQueryString();

        return Inertia::render('Admin/Agents/Achievements/Index', [
            'requests' => $requests,
            'filters' => $request->only(['status', 'search']),
            'can' => [
                'request' => $user->can('request', AgentAchievement::class),
                'award' => $user->can('award', AgentAchievement::class),
                'revoke' => $user->can('revoke', AgentAchievement::class),
            ],
        ]);
    }

    /**
     * Submit a new achievement nomination.
     */
    public function store(StoreAchievementNominationRequest $request): RedirectResponse
    {
        $this->achievementService->submitDiscretionaryRequest(
            $request->validated(),
            $request->user()
        );

        return redirect()->back()->with('success', 'Achievement nomination submitted successfully.');
    }

    /**
     * Approve a pending nomination (Owner only).
     */
    public function approve(ApproveAchievementRequest $request, AgentAchievementRequest $achievementRequest): RedirectResponse
    {
        $this->achievementService->approveDiscretionaryRequest(
            $achievementRequest->id,
            $request->validated('decision_notes'),
            $request->user()
        );

        return redirect()->back()->with('success', 'Nomination approved and achievement awarded successfully.');
    }

    /**
     * Reject a pending nomination (Owner only).
     */
    public function reject(RejectAchievementRequest $request, AgentAchievementRequest $achievementRequest): RedirectResponse
    {
        $this->achievementService->rejectDiscretionaryRequest(
            $achievementRequest->id,
            $request->validated('decision_notes'),
            $request->user()
        );

        return redirect()->back()->with('success', 'Nomination rejected successfully.');
    }
}

