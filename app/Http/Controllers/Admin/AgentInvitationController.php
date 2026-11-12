<?php

namespace App\Http\Controllers\Admin;

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
     * Display a listing of company-wide agent invitations.
     */
    public function index(Request $request): Response
    {
        $actor = $request->user();

        // Access check: Owner, Super Admin, Agent Manager, or users with explicit invitation view permission
        if (!$actor->isOwner() && !$actor->isSuperAdmin() && !$actor->isAgentManager() && !$actor->hasPermissionTo('agents.invitations.view') && !$actor->hasPermissionTo('agents.view')) {
            abort(403, 'Unauthorized access to Agent Recruitment & Invitations.');
        }

        $now = now();

        // Calculate summary KPI statistics
        $stats = [
            'total' => AgentInvitation::count(),
            'pending' => AgentInvitation::where('status', AgentInvitation::STATUS_PENDING)->where('expires_at', '>', $now)->count(),
            'accepted' => AgentInvitation::where('status', AgentInvitation::STATUS_ACCEPTED)->count(),
            'expired' => AgentInvitation::where('status', AgentInvitation::STATUS_EXPIRED)
                ->orWhere(function ($q) use ($now) {
                    $q->where('status', AgentInvitation::STATUS_PENDING)->where('expires_at', '<=', $now);
                })->count(),
            'revoked' => AgentInvitation::where('status', AgentInvitation::STATUS_REVOKED)->count(),
        ];

        $query = AgentInvitation::with([
            'creator:id,name,role',
            'parentAgent:id,name,referral_code',
            'usedBy:id,name,email',
        ])->latest('id');

        // Filter by Status
        if ($request->input('status') === 'pending') {
            $query->where('status', AgentInvitation::STATUS_PENDING)
                ->where('expires_at', '>', $now);
        } elseif ($request->input('status') === 'expired') {
            $query->where(function ($q) use ($now) {
                $q->where('status', AgentInvitation::STATUS_EXPIRED)
                    ->orWhere(function ($sub) use ($now) {
                        $sub->where('status', AgentInvitation::STATUS_PENDING)
                            ->where('expires_at', '<=', $now);
                    });
            });
        } elseif ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // Filter by Type (high_level_agent / low_level_agent)
        if ($request->filled('type')) {
            $query->where('invitation_type', $request->input('type'));
        }

        // Multi-field search
        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('candidate_name', 'like', "%{$search}%")
                    ->orWhere('candidate_email', 'like', "%{$search}%")
                    ->orWhere('candidate_phone', 'like', "%{$search}%");
            });
        }

        $invitations = $query->paginate(15)->withQueryString();

        $canCreateHla = $actor->isOwner()
            || $actor->isSuperAdmin()
            || $actor->isAgentManager()
            || $actor->hasPermissionTo('agents.invitations.create_hla');

        return Inertia::render('Admin/Agents/Invitations', [
            'invitations' => $invitations,
            'filters' => $request->only(['status', 'type', 'search']),
            'stats' => $stats,
            'canCreateHla' => $canCreateHla,
            'newInvitation' => session('generated_link') ? [
                'link' => session('generated_link'),
                'token' => session('token'),
                'candidate_name' => session('candidate_name'),
            ] : null,
        ]);
    }

    /**
     * Generate a new High-Level Agent invitation.
     */
    public function store(Request $request): RedirectResponse
    {
        $actor = $request->user();

        $canCreate = $actor->isOwner()
            || $actor->isSuperAdmin()
            || $actor->isAgentManager()
            || $actor->hasPermissionTo('agents.invitations.create_hla');

        if (!$canCreate) {
            abort(403, 'Unauthorized: You do not have permission to create High-Level Agent invitations.');
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
            type: AgentInvitation::TYPE_HIGH_LEVEL_AGENT,
            candidateData: [
                'name' => $validated['candidate_name'] ?? null,
                'email' => $validated['candidate_email'] ?? null,
                'phone' => $validated['candidate_phone'] ?? null,
                'candidate_name' => $validated['candidate_name'] ?? null,
                'candidate_email' => $validated['candidate_email'] ?? null,
                'candidate_phone' => $validated['candidate_phone'] ?? null,
            ],
            expiryDays: $expiryDays
        );

        return redirect()->route('admin.agents.invitations.index')->with([
            'success' => 'High-Level Agent invitation generated successfully.',
            'generated_link' => $result['registration_url'],
            'token' => $result['token'],
            'candidate_name' => $validated['candidate_name'] ?? 'Candidate',
        ]);
    }

    /**
     * Revoke a pending invitation.
     */
    public function revoke(Request $request, AgentInvitation $invitation): RedirectResponse
    {
        $actor = $request->user();
        $this->invitationService->revokeInvitation($invitation, $actor);

        return back()->with('success', 'Invitation has been revoked.');
    }
}
