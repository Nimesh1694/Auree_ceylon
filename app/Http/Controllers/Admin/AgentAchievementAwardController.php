<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DirectGrantAchievementAwardRequest;
use App\Http\Requests\Admin\ReinstateAchievementAwardRequest;
use App\Http\Requests\Admin\RevokeAchievementAwardRequest;
use App\Models\AgentAchievement;
use App\Models\AgentAchievementAward;
use App\Services\AgentAchievementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AgentAchievementAwardController extends Controller
{
    public function __construct(
        protected AgentAchievementService $achievementService
    ) {}

    /**
     * Display a listing of achievement awards.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', AgentAchievement::class);

        $query = AgentAchievementAward::with([
            'achievement',
            'version',
            'agent:id,name,email,role',
            'awarder:id,name,email',
            'revoker:id,name,email',
        ]);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('category')) {
            $query->whereHas('achievement', fn ($q) => $q->where('category', $request->category));
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('agent', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $awards = $query->latest('awarded_at')->paginate(15)->withQueryString();

        return Inertia::render('Admin/Agents/Achievements/Awards', [
            'awards' => $awards,
            'filters' => $request->only(['status', 'category', 'search']),
            'can' => [
                'award' => $request->user()->can('award', AgentAchievement::class),
                'revoke' => $request->user()->can('revoke', AgentAchievement::class),
            ],
        ]);
    }

    /**
     * Directly issue a discretionary achievement award without prior request (Owner only).
     */
    public function directGrant(DirectGrantAchievementAwardRequest $request): RedirectResponse
    {
        $this->achievementService->directGrantDiscretionaryAward(
            (int) $request->validated('agent_id'),
            (int) $request->validated('achievement_id'),
            $request->validated('period_key'),
            $request->validated('justification'),
            $request->user()
        );

        return redirect()->back()->with('success', 'Discretionary achievement award granted successfully.');
    }

    /**
     * Revoke an active or corrected award (Owner only).
     */
    public function revoke(RevokeAchievementAwardRequest $request, AgentAchievementAward $award): RedirectResponse
    {
        $this->achievementService->revokeAward(
            $award->id,
            $request->validated('revocation_reason'),
            $request->user()
        );

        return redirect()->back()->with('success', 'Achievement award revoked successfully.');
    }

    /**
     * Reinstate a previously revoked award (Owner only).
     */
    public function reinstate(ReinstateAchievementAwardRequest $request, AgentAchievementAward $award): RedirectResponse
    {
        $this->achievementService->reinstateAward(
            $award->id,
            $request->validated('reinstatement_reason'),
            $request->user()
        );

        return redirect()->back()->with('success', 'Achievement award reinstated successfully.');
    }
}

