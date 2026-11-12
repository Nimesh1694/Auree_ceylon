<?php

namespace App\Http\Controllers\Agent;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\AgentInvitation;
use App\Models\AgentProfile;
use App\Models\AuditLog;
use App\Models\User;
use App\Notifications\AgentRegistrationReceivedNotification;
use App\Services\AgentHierarchyService;
use App\Services\AgentInvitationService;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RegisterController extends Controller
{
    public function __construct(
        protected AgentInvitationService $invitationService,
        protected AgentHierarchyService $hierarchyService
    ) {}

    /**
     * Display the agent registration form.
     */
    public function create(Request $request): Response|RedirectResponse
    {
        if ($request->user() && $request->user()->isAgent()) {
            return redirect()->route('agent.dashboard');
        }

        $token = $request->query('token');
        $invitation = null;
        $isInvalidToken = false;

        if ($token) {
            $invitation = $this->invitationService->findValidInvitation($token);
            if (!$invitation) {
                $isInvalidToken = true;
            }
        }

        $referralCode = $request->query('ref') ?? $request->session()->get('referral_code');
        $sponsorName = null;

        if ($invitation) {
            if ($invitation->invitation_type === AgentInvitation::TYPE_HIGH_LEVEL_AGENT) {
                $sponsorName = 'Aura Luxe Company (Executive Management)';
            } else {
                $sponsorName = $invitation->parentAgent?->name ?? 'High-Level Sponsor';
            }
        } elseif ($referralCode) {
            $sponsor = User::where('referral_code', strtoupper(trim($referralCode)))
                ->whereIn('role', [UserRole::HIGH_LEVEL_AGENT, UserRole::LOW_LEVEL_AGENT])
                ->where('status', UserStatus::ACTIVE)
                ->first();
            $sponsorName = $sponsor?->name;
        }

        return Inertia::render('Agent/Register', [
            'initialReferralCode' => $referralCode ?? '',
            'sponsorName' => $sponsorName,
            'token' => $token ?? '',
            'invitation' => $invitation ? [
                'type' => $invitation->invitation_type,
                'candidate_name' => $invitation->candidate_name,
                'candidate_email' => $invitation->candidate_email,
                'candidate_phone' => $invitation->candidate_phone,
                'sponsor_name' => $sponsorName,
            ] : null,
            'isInvalidToken' => $isInvalidToken,
        ]);
    }

    /**
     * Process new agent application submission.
     * New agents remain in 'pending' status until authorized management approves.
     */
    public function store(Request $request): RedirectResponse
    {
        $token = $request->input('token');
        $invitation = null;

        if (!empty($token)) {
            $invitation = $this->invitationService->findValidInvitation($token);
            if (!$invitation) {
                throw ValidationException::withMessages([
                    'token' => 'This invitation token is invalid, expired, or has already been used.',
                ]);
            }
        }

        $rules = [
            'name' => 'required|string|min:2|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'phone' => 'required|string|min:9|max:20|unique:users,phone',
            'password' => 'required|string|min:8|confirmed',
            'nic' => 'required|string|min:9|max:25',
            'address' => 'required|string|min:5|max:500',
            'business_name' => 'nullable|string|max:255',
            'bank_name' => 'required|string|max:100',
            'bank_branch' => 'required|string|max:100',
            'bank_account_number' => 'required|string|max:50',
            'bank_account_name' => 'required|string|max:255',
        ];

        // If no invitation token is supplied, referral_code is required for legacy direct registration
        if (!$invitation) {
            $rules['referral_code'] = 'required|string|max:50';
        }

        $validated = $request->validate($rules);

        $normalizedPhone = OrderService::normalizePhoneNumber($validated['phone']);

        $assignedRole = UserRole::LOW_LEVEL_AGENT;
        $parentId = null;
        $sponsor = null;

        if ($invitation) {
            if ($invitation->invitation_type === AgentInvitation::TYPE_HIGH_LEVEL_AGENT) {
                $assignedRole = UserRole::HIGH_LEVEL_AGENT;
                $parentId = null; // High-level agent is directly under company
            } else {
                $assignedRole = UserRole::LOW_LEVEL_AGENT;
                $parentId = $invitation->parent_agent_id; // Low-level agent under inviting HLA
                $sponsor = $invitation->parentAgent;
            }
        } else {
            // Legacy / Direct referral fallback
            $referralCode = strtoupper(trim($validated['referral_code']));
            $sponsor = User::where('referral_code', $referralCode)
                ->whereIn('role', [UserRole::HIGH_LEVEL_AGENT, UserRole::LOW_LEVEL_AGENT])
                ->where('status', UserStatus::ACTIVE)
                ->first();

            if (!$sponsor) {
                throw ValidationException::withMessages([
                    'referral_code' => 'The provided sponsor referral code is invalid or does not belong to an active agent.',
                ]);
            }

            $assignedRole = UserRole::LOW_LEVEL_AGENT;
            $parentId = $sponsor->id;
        }

        $agent = DB::transaction(function () use ($validated, $normalizedPhone, $assignedRole, $parentId, $invitation, $sponsor) {
            $newUser = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $normalizedPhone,
                'role' => $assignedRole,
                'status' => UserStatus::PENDING, // Never automatically activated
                'parent_id' => $parentId,        // Stored as proposed parent
                'referral_code' => User::generateUniqueReferralCode($validated['name']),
                'password' => Hash::make($validated['password']),
            ]);

            AgentProfile::create([
                'user_id' => $newUser->id,
                'business_name' => $validated['business_name'] ?? null,
                'nic' => $validated['nic'],
                'address' => $validated['address'],
                'bank_name' => $validated['bank_name'],
                'bank_branch' => $validated['bank_branch'],
                'bank_account_number' => $validated['bank_account_number'],
                'bank_account_name' => $validated['bank_account_name'],
                'kyc_status' => 'pending',
                'wallet_balance' => 0.00,
                'total_earned' => 0.00,
                'total_withdrawn' => 0.00,
            ]);

            // Attach new agent to closure hierarchy inside the same atomic transaction
            $parentUser = $parentId ? ($sponsor ?? User::find($parentId)) : null;
            $this->hierarchyService->attachAgent($newUser, $parentUser);

            // If registered via invitation, mark invitation as accepted
            if ($invitation) {
                $this->invitationService->acceptInvitation($invitation, $newUser);
            }

            return $newUser;
        });

        // Audit Log
        AuditLog::log('agent_registered', $agent, null, [
            'name' => $agent->name,
            'role' => $assignedRole->value,
            'parent_id' => $parentId,
            'sponsor_name' => $sponsor?->name ?? 'Company',
            'referral_code' => $agent->referral_code,
            'via_invitation' => (bool) $invitation,
        ]);

        // Notify management
        $managers = User::whereIn('role', [UserRole::OWNER, UserRole::SUPER_ADMIN, UserRole::AGENT_MANAGER])->get();
        foreach ($managers as $manager) {
            $manager->notify(new AgentRegistrationReceivedNotification($agent->name, $agent->referral_code));
        }

        return redirect()->route('login')->with(
            'success',
            'Your agent application has been submitted successfully and is awaiting executive management review.'
        );
    }
}
