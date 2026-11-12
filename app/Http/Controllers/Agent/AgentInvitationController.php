<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\AgentInvitation;
use App\Services\AgentInvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AgentInvitationController extends Controller
{
    public function __construct(
        protected AgentInvitationService $invitationService
    ) {}

    /**
     * Display a listing of Low-Level Agent invitations created by the authenticated High-Level Agent.
     */
    public function index(Request $request): Response
    {
        $actor = $request->user();

        // Only High-Level Agents can access recruitment
        if (!$actor->isHighLevelAgent()) {
            abort(403, 'Unauthorized: Only High-Level Agents can recruit and invite Low-Level Agents.');
        }

        $invitations = AgentInvitation::where('created_by', $actor->id)
            ->where('invitation_type', AgentInvitation::TYPE_LOW_LEVEL_AGENT)
            ->with('usedBy:id,name,email,status')
            ->latest('id')
            ->paginate(15);

        return Inertia::render('Agent/Invitations', [
            'invitations' => $invitations,
        ]);
    }

    /**
     * Generate a new Low-Level Agent invitation under this HLA's network.
     */
    public function store(Request $request): RedirectResponse
    {
        $actor = $request->user();

        if (!$actor->isHighLevelAgent() || !$actor->isActive()) {
            abort(403, 'Unauthorized: Only active High-Level Agents can issue invitations.');
        }

        $validated = $request->validate([
            'candidate_name' => ['nullable', 'string', 'max:255'],
            'candidate_email' => ['nullable', 'email', 'max:255'],
            'candidate_phone' => ['nullable', 'string', 'max:50'],
            'expiry_days' => ['nullable', 'integer', 'min:1', 'max:30'],
        ]);

        $expiryDays = (int) ($validated['expiry_days'] ?? 7);

        $result = $this->invitationService->createInvitation(
            creator: $actor,
            type: AgentInvitation::TYPE_LOW_LEVEL_AGENT,
            candidateData: $validated,
            expiryDays: $expiryDays
        );

        return redirect()->route('agent.invitations.index')->with([
            'success' => 'Low-Level Agent invitation generated successfully.',
            'generated_link' => $result['registration_url'],
            'candidate_name' => $validated['candidate_name'] ?? 'Candidate',
        ]);
    }

    /**
     * Revoke an active invitation.
     */
    public function revoke(Request $request, AgentInvitation $invitation): RedirectResponse
    {
        $actor = $request->user();

        if ($invitation->created_by !== $actor->id) {
            abort(403, 'Unauthorized to revoke this invitation.');
        }

        $this->invitationService->revokeInvitation($invitation, $actor);

        return back()->with('success', 'Invitation has been revoked.');
    }
}

