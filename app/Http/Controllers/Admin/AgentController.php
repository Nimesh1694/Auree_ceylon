<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\AgentProfile;
use App\Models\AuditLog;
use App\Models\HierarchyChangeRequest;
use App\Models\Order;
use App\Models\User;
use App\Models\UserClosure;
use App\Notifications\AgentKycUpdatedNotification;
use App\Notifications\AgentStatusUpdatedNotification;
use App\Notifications\HierarchyChangeUpdatedNotification;
use App\Services\AgentHierarchyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AgentController extends Controller
{
    public function __construct(
        private readonly AgentHierarchyService $hierarchyService
    ) {}

    /**
     * Display a listing of agents with filters and pending count.
     */
    public function index(Request $request): Response
    {
        $query = User::whereIn('role', [UserRole::HIGH_LEVEL_AGENT, UserRole::LOW_LEVEL_AGENT])
            ->with(['agentProfile', 'parent:id,name,referral_code,role'])
            ->withCount('children');

        // Search: name, email, phone, or referral code
        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('referral_code', 'like', "%{$search}%");
            });
        }

        // Tier / Role filter: high_level_agent or low_level_agent
        if ($request->filled('role')) {
            $query->where('role', $request->input('role'));
        }

        // Account Status filter: active, pending, suspended
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // KYC Verification Status filter: pending, verified, rejected
        if ($request->filled('kyc_status')) {
            $kyc = $request->input('kyc_status');
            $query->whereHas('agentProfile', fn ($q) => $q->where('kyc_status', $kyc));
        }

        // Date range filtering: from_date
        if ($request->filled('from_date')) {
            $query->where('created_at', '>=', \Carbon\Carbon::parse($request->input('from_date'))->startOfDay());
        }

        // Date range filtering: to_date
        if ($request->filled('to_date')) {
            $query->where('created_at', '<=', \Carbon\Carbon::parse($request->input('to_date'))->endOfDay());
        }

        $agents = $query->latest('id')->paginate(15)->withQueryString();

        $user = $request->user();
        $canViewFullFinancials = $user && ($user->isOwner() || $user->role === UserRole::ACCOUNTANT);
        $canViewFullKyc = $user && ($user->isOwner() || $user->role === UserRole::ACCOUNTANT || $user->role === UserRole::IT_MANAGER);

        $agents->getCollection()->transform(fn ($a) => $this->sanitizeAgentProfile($a, $canViewFullFinancials, $canViewFullKyc));

        $pendingApplicationsCount = User::whereIn('role', [UserRole::HIGH_LEVEL_AGENT, UserRole::LOW_LEVEL_AGENT])
            ->where('status', UserStatus::PENDING)
            ->count();

        $pendingKycCount = User::whereIn('role', [UserRole::HIGH_LEVEL_AGENT, UserRole::LOW_LEVEL_AGENT])
            ->whereHas('agentProfile', fn ($q) => $q->where('kyc_status', 'pending'))
            ->count();

        $pendingHierarchyRequestsCount = HierarchyChangeRequest::where('status', 'pending')->count();
        $totalHlaCount = User::where('role', UserRole::HIGH_LEVEL_AGENT)->count();
        $totalLlaCount = User::where('role', UserRole::LOW_LEVEL_AGENT)->count();
        $totalAgentsCount = $totalHlaCount + $totalLlaCount;

        return Inertia::render('Admin/Agents/Index', [
            'agents' => $agents,
            'filters' => $request->only(['search', 'role', 'status', 'kyc_status', 'from_date', 'to_date']),
            'stats' => [
                'pending_applications' => $pendingApplicationsCount,
                'pending_kyc' => $pendingKycCount,
                'pending_hierarchy_requests' => $pendingHierarchyRequestsCount,
                'total_hla' => $totalHlaCount,
                'total_lla' => $totalLlaCount,
                'total_agents' => $totalAgentsCount,
            ],
            'canManageApplications' => $user && ($user->isOwner() || $user->hasPermissionTo('agents.status.update') || $user->role === UserRole::AGENT_MANAGER),
            'canManageKyc' => $user && ($user->isOwner() || $user->hasPermissionTo('agents.kyc.verify') || $user->role === UserRole::AGENT_MANAGER || $user->role === UserRole::ACCOUNTANT),
        ]);
    }

    /**
     * Display agent details, profile, KYC information, and hierarchy.
     */
    public function show(Request $request, User $agent): Response
    {
        $user = $request->user();
        $canViewFullFinancials = $user && ($user->isOwner() || $user->role === UserRole::ACCOUNTANT);
        $canViewFullKyc = $user && ($user->isOwner() || $user->role === UserRole::ACCOUNTANT || $user->role === UserRole::IT_MANAGER);

        $agent->load(['agentProfile', 'parent', 'children.agentProfile']);
        $agent->loadCount('children');
        $this->sanitizeAgentProfile($agent, $canViewFullFinancials, $canViewFullKyc);

        if ($agent->relationLoaded('children')) {
            $agent->children->transform(fn ($c) => $this->sanitizeAgentProfile($c, $canViewFullFinancials, $canViewFullKyc));
        }

        $upline = $this->hierarchyService->getUpline($agent);
        $downline = $this->hierarchyService->getDownline($agent);

        $upline->transform(fn ($u) => $this->sanitizeAgentProfile($u, $canViewFullFinancials, $canViewFullKyc));
        $downline->transform(fn ($d) => $this->sanitizeAgentProfile($d, $canViewFullFinancials, $canViewFullKyc));

        $changeRequests = HierarchyChangeRequest::with(['requestedParent', 'requestedBy', 'reviewedBy'])
            ->where('agent_id', $agent->id)
            ->latest('id')
            ->get();

        $auditLogs = AuditLog::where(function ($q) use ($agent) {
            $q->where('auditable_type', User::class)
                ->where('auditable_id', $agent->id);
        })->latest('id')->get();

        // Sanitize audit log values to prevent sensitive data leakage through JSON history props
        $auditLogs->transform(function ($log) use ($canViewFullFinancials, $canViewFullKyc) {
            $old = $log->old_values;
            $new = $log->new_values;

            if (!$canViewFullFinancials) {
                if (isset($old['profile']['bank_account_number'])) {
                    $old['profile']['bank_account_number'] = $this->maskAccountNumber($old['profile']['bank_account_number']);
                }
                if (isset($new['profile']['bank_account_number'])) {
                    $new['profile']['bank_account_number'] = $this->maskAccountNumber($new['profile']['bank_account_number']);
                }
            }

            if (!$canViewFullKyc) {
                if (isset($old['profile']['nic'])) {
                    $old['profile']['nic'] = $this->maskNic($old['profile']['nic']);
                }
                if (isset($new['profile']['nic'])) {
                    $new['profile']['nic'] = $this->maskNic($new['profile']['nic']);
                }
            }

            $log->old_values = $old;
            $log->new_values = $new;
            return $log;
        });

        $availableParents = User::whereIn('role', [UserRole::HIGH_LEVEL_AGENT, UserRole::LOW_LEVEL_AGENT])
            ->where('status', UserStatus::ACTIVE)
            ->where('id', '!=', $agent->id)
            ->get(['id', 'name', 'referral_code', 'role']);

        return Inertia::render('Admin/Agents/Show', [
            'agent' => $agent,
            'upline' => $upline,
            'downline' => $downline,
            'changeRequests' => $changeRequests,
            'auditLogs' => $auditLogs,
            'availableParents' => $availableParents,
            'canViewFullFinancials' => $canViewFullFinancials,
            'canViewFullKyc' => $canViewFullKyc,
        ]);
    }

    /**
     * Mask sensitive bank account number (all but last 4 digits).
     */
    private function maskAccountNumber(?string $accountNumber): ?string
    {
        if (empty($accountNumber)) {
            return null;
        }
        $len = strlen($accountNumber);
        return $len > 4 ? str_repeat('*', $len - 4) . substr($accountNumber, -4) : '****';
    }

    /**
     * Mask sensitive national identity card number (all but last 4 digits).
     */
    private function maskNic(?string $nic): ?string
    {
        if (empty($nic)) {
            return null;
        }
        $len = strlen($nic);
        return $len > 4 ? str_repeat('*', $len - 4) . substr($nic, -4) : '****';
    }

    /**
     * Sanitize agent profile based on authenticated user's permissions.
     */
    private function sanitizeAgentProfile(User $targetUser, bool $canViewFullFinancials, bool $canViewFullKyc): User
    {
        if ($targetUser->relationLoaded('agentProfile') && $targetUser->agentProfile) {
            $cloneProfile = clone $targetUser->agentProfile;
            if (!$canViewFullFinancials && $cloneProfile->bank_account_number) {
                $cloneProfile->bank_account_number = $this->maskAccountNumber($cloneProfile->bank_account_number);
            }
            if (!$canViewFullKyc && $cloneProfile->nic) {
                $cloneProfile->nic = $this->maskNic($cloneProfile->nic);
            }
            $targetUser->setRelation('agentProfile', $cloneProfile);
        }

        return $targetUser;
    }

    /**
     * Approve a pending agent application.
     * Attaches agent to hierarchy closure and activates account.
     */
    public function approve(Request $request, User $agent): RedirectResponse
    {
        if ($agent->status !== UserStatus::PENDING) {
            return back()->with('error', 'Agent application is not in pending status.');
        }

        $agent->update(['status' => UserStatus::ACTIVE]);

        // Attach into closure table under proposed parent
        $this->hierarchyService->attachAgent($agent, $agent->parent);

        // Audit log
        AuditLog::log('agent_approved', $agent, ['status' => 'pending'], ['status' => 'active']);

        $agent->notify(new AgentStatusUpdatedNotification('active', 'Your agent application has been approved by management. Welcome to Aura Luxe!'));

        return back()->with('success', "Agent {$agent->name} has been approved and activated in the network hierarchy.");
    }

    /**
     * Reject a pending agent application.
     */
    public function reject(Request $request, User $agent): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $agent->update(['status' => UserStatus::SUSPENDED]);

        AuditLog::log('agent_rejected', $agent, ['status' => 'pending'], [
            'status' => 'suspended',
            'reason' => $validated['reason'],
        ]);

        $agent->notify(new AgentStatusUpdatedNotification('rejected', $validated['reason']));

        return back()->with('success', "Agent application for {$agent->name} was rejected.");
    }

    /**
     * Update agent account status (e.g. suspend or re-activate).
     */
    public function updateStatus(Request $request, User $agent): RedirectResponse
    {
        $validated = $request->validate([
            'status' => 'required|string|in:active,suspended',
            'reason' => 'nullable|string|max:500',
        ]);

        $oldStatus = $agent->status->value ?? (string) $agent->status;
        $newStatus = UserStatus::from($validated['status']);

        $agent->update(['status' => $newStatus]);

        // Defensive activation safeguard: If an agent becomes active and lacks closure records, attach them safely
        if ($newStatus === UserStatus::ACTIVE && $agent->isAgent()) {
            $hasSelf = UserClosure::where('ancestor_id', $agent->id)
                ->where('descendant_id', $agent->id)
                ->where('depth', 0)
                ->exists();

            $hasParentClosure = true;
            if ($agent->parent_id) {
                $hasParentClosure = UserClosure::where('ancestor_id', $agent->parent_id)
                    ->where('descendant_id', $agent->id)
                    ->where('depth', 1)
                    ->exists();
            }

            if (!$hasSelf || !$hasParentClosure) {
                $validParent = null;
                if ($agent->parent_id) {
                    $parentCandidate = User::find($agent->parent_id);
                    if ($parentCandidate && $parentCandidate->isAgent() && $parentCandidate->id !== $agent->id) {
                        $validParent = $parentCandidate;
                    }
                }
                $this->hierarchyService->attachAgent($agent, $validParent);
            }
        }

        AuditLog::log('agent_status_updated', $agent, ['status' => $oldStatus], [
            'status' => $validated['status'],
            'reason' => $validated['reason'] ?? null,
        ]);

        $agent->notify(new AgentStatusUpdatedNotification($validated['status'], $validated['reason'] ?? null));

        return back()->with('success', "Agent status updated to {$validated['status']}.");
    }

    /**
     * Display the dedicated KYC verification queue.
     */
    public function kycQueue(Request $request): Response
    {
        $user = $request->user();

        if (!$user || (!$user->isOwner() && !$user->isSuperAdmin() && !$user->isAgentManager() && !$user->hasPermissionTo('agents.kyc.verify') && $user->role !== UserRole::ACCOUNTANT && $user->role !== UserRole::IT_MANAGER)) {
            abort(403, 'Unauthorized access to KYC Queue.');
        }

        // Base query: agents with profile
        $query = User::whereIn('role', [UserRole::HIGH_LEVEL_AGENT, UserRole::LOW_LEVEL_AGENT])
            ->whereHas('agentProfile')
            ->with(['agentProfile', 'parent:id,name,referral_code,role'])
            ->withCount('children');

        // Status Filter: pending (default), verified, rejected, or all
        if ($request->filled('status')) {
            $status = $request->input('status');
            if ($status !== 'all') {
                $query->whereHas('agentProfile', fn ($q) => $q->where('kyc_status', $status));
            }
        } else {
            // Default to pending submissions
            $query->whereHas('agentProfile', fn ($q) => $q->where('kyc_status', 'pending'));
        }

        // Role / Tier filter (HLA vs LLA)
        if ($request->filled('role')) {
            $query->where('role', $request->input('role'));
        }

        // Search: agent name, email, referral code, or phone
        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('referral_code', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // Submission date filtering: from_date and to_date
        if ($request->filled('from_date')) {
            $query->where('created_at', '>=', \Carbon\Carbon::parse($request->input('from_date'))->startOfDay());
        }

        if ($request->filled('to_date')) {
            $query->where('created_at', '<=', \Carbon\Carbon::parse($request->input('to_date'))->endOfDay());
        }

        $agents = $query->latest('id')->paginate(15)->withQueryString();

        // Enforce least privilege masking on queue list:
        // Sensitive NIC and bank numbers remain masked by default!
        $canViewFullFinancials = $user->isOwner() || $user->role === UserRole::ACCOUNTANT;
        $canViewFullKyc = $user->isOwner() || $user->role === UserRole::ACCOUNTANT || $user->role === UserRole::IT_MANAGER;

        $agents->getCollection()->transform(fn ($a) => $this->sanitizeAgentProfile($a, $canViewFullFinancials, $canViewFullKyc));

        // Global KPI counts for KYC
        $baseAgentQuery = User::whereIn('role', [UserRole::HIGH_LEVEL_AGENT, UserRole::LOW_LEVEL_AGENT])
            ->whereHas('agentProfile');

        $totalSubmissions = (clone $baseAgentQuery)->count();
        $pendingCount = (clone $baseAgentQuery)->whereHas('agentProfile', fn ($q) => $q->where('kyc_status', 'pending'))->count();
        $verifiedCount = (clone $baseAgentQuery)->whereHas('agentProfile', fn ($q) => $q->where('kyc_status', 'verified'))->count();
        $rejectedCount = (clone $baseAgentQuery)->whereHas('agentProfile', fn ($q) => $q->where('kyc_status', 'rejected'))->count();

        return Inertia::render('Admin/Agents/KycQueue', [
            'agents' => $agents,
            'filters' => [
                'status' => $request->input('status', 'pending'),
                'role' => $request->input('role', ''),
                'search' => $request->input('search', ''),
                'from_date' => $request->input('from_date', ''),
                'to_date' => $request->input('to_date', ''),
            ],
            'stats' => [
                'total' => $totalSubmissions,
                'pending' => $pendingCount,
                'verified' => $verifiedCount,
                'rejected' => $rejectedCount,
            ],
            'canManageKyc' => true,
            'canUnmaskPii' => $user && ($user->isOwner() || $user->hasPermissionTo('agents.kyc.verify')),
        ]);
    }

    /**
     * Authorized, audited on-demand unmasking of agent PII (NIC, Banking credentials).
     * Strictly logs 'agent_pii_unmasked' with actor, target agent, timestamp, and reason.
     * Enforces least-privilege: Only Owner or staff explicitly granted 'agents.kyc.verify'.
     */
    public function unmaskPii(Request $request, User $agent): JsonResponse
    {
        $user = $request->user();

        $canUnmask = $user && (
            $user->isOwner()
            || $user->hasPermissionTo('agents.kyc.verify')
        );

        if (!$canUnmask) {
            return response()->json([
                'error' => 'Security Violation: Unauthorized attempt to unmask sensitive personal identity data.',
            ], 403);
        }

        $validated = $request->validate([
            'reason' => 'nullable|string|max:255',
        ]);

        $profile = $agent->agentProfile;

        if (!$profile) {
            return response()->json(['error' => 'Agent profile not found.'], 404);
        }

        // Record immutable audit event
        AuditLog::log('agent_pii_unmasked', $agent, null, [
            'actor_id' => $user->id,
            'actor_name' => $user->name,
            'actor_role' => $user->role->value ?? (string) $user->role,
            'target_agent_id' => $agent->id,
            'target_agent_name' => $agent->name,
            'reason' => $validated['reason'] ?? 'KYC identity verification in KYC Queue',
            'unmasked_fields' => ['nic', 'bank_account_number'],
            'timestamp' => now()->toIso8601String(),
        ]);

        return response()->json([
            'success' => true,
            'agent_id' => $agent->id,
            'nic' => $profile->nic,
            'bank_account_number' => $profile->bank_account_number,
            'bank_name' => $profile->bank_name,
            'bank_branch' => $profile->bank_branch,
            'bank_account_name' => $profile->bank_account_name,
            'address' => $profile->address,
            'business_name' => $profile->business_name,
        ]);
    }

    /**
     * Securely stream or verify KYC document files with authorization and logging.
     * Enforces least-privilege: Only Owner or staff explicitly granted 'agents.kyc.verify'.
     */
    public function viewKycDocument(Request $request, User $agent)
    {
        $user = $request->user();

        $canAccess = $user && (
            $user->isOwner()
            || $user->hasPermissionTo('agents.kyc.verify')
        );

        if (!$canAccess) {
            abort(403, 'Unauthorized access to agent KYC documents.');
        }

        $safeDir = "kyc_documents/{$agent->id}";
        $files = Storage::disk('local')->files($safeDir);

        if (!empty($files)) {
            $latestFile = end($files);
            AuditLog::log('agent_document_accessed', $agent, null, [
                'actor_id' => $user->id,
                'actor_name' => $user->name,
                'file' => basename($latestFile),
            ]);
            return Storage::disk('local')->download($latestFile);
        }

        return response()->json([
            'has_document' => false,
            'message' => 'No supplementary file uploaded. Identity verification is conducted using verified NIC and banking credentials.',
            'nic_recorded' => !empty($agent->agentProfile?->nic),
        ], 200);
    }

    /**
     * Review and verify or reject an agent's KYC submission.
     */
    public function updateKyc(Request $request, User $agent): RedirectResponse
    {
        $actor = $request->user();

        if (!$actor || (!$actor->isOwner() && !$actor->isSuperAdmin() && !$actor->isAgentManager() && $actor->role !== UserRole::ACCOUNTANT && $actor->role !== UserRole::IT_MANAGER && !$actor->hasPermissionTo('agents.kyc.verify'))) {
            abort(403, 'Unauthorized: You do not have permission to verify agent KYC.');
        }

        $isRejecting = $request->input('kyc_status') === 'rejected';

        $validated = $request->validate([
            'kyc_status' => 'required|string|in:verified,rejected',
            'kyc_notes' => [
                $isRejecting ? 'required' : 'nullable',
                'string',
                'max:500',
            ],
            'rejection_reason' => 'nullable|string|max:500',
        ], [
            'kyc_notes.required' => 'A rejection reason is mandatory when rejecting a KYC submission.',
        ]);

        $notes = $validated['rejection_reason'] ?? $validated['kyc_notes'] ?? null;
        $oldStatus = $agent->agentProfile?->kyc_status ?? 'pending';

        $agent->agentProfile()->updateOrCreate(
            ['user_id' => $agent->id],
            [
                'kyc_status' => $validated['kyc_status'],
                'kyc_notes' => $notes,
            ]
        );

        AuditLog::log('agent_kyc_updated', $agent, ['kyc_status' => $oldStatus], [
            'kyc_status' => $validated['kyc_status'],
            'notes' => $notes,
            'verified_by' => $actor->name,
            'verified_by_id' => $actor->id,
        ]);

        $agent->notify(new AgentKycUpdatedNotification($validated['kyc_status'], $notes));

        return back()->with('success', "KYC status for {$agent->name} updated to {$validated['kyc_status']}.");
    }

    /**
     * Display the visual agent network tree explorer.
     */
    public function hierarchyTree(Request $request): Response
    {
        $user = $request->user();

        if (!$user || (!$user->isOwner() && !$user->hasPermissionTo('agents.view'))) {
            abort(403, 'Unauthorized access to Agent Network Tree.');
        }

        // Fetch top-level root agents (Company-direct High-Level Agents)
        $rootAgents = User::where('role', UserRole::HIGH_LEVEL_AGENT)
            ->whereNull('parent_id')
            ->select(['id', 'name', 'email', 'role', 'status', 'referral_code', 'parent_id', 'created_at'])
            ->with(['agentProfile:id,user_id,kyc_status,business_name'])
            ->withCount('children')
            ->orderBy('name')
            ->get();

        return Inertia::render('Admin/Agents/Hierarchy', [
            'rootAgents' => $rootAgents,
            'canRequestHierarchyMove' => $user->isOwner() || $user->hasPermissionTo('agents.hierarchy.request'),
        ]);
    }

    /**
     * Lazy-load direct children of a specific agent or search agents for the interactive network tree.
     */
    public function hierarchyNodes(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user || (!$user->isOwner() && !$user->hasPermissionTo('agents.view'))) {
            return response()->json(['error' => 'Unauthorized.'], 403);
        }

        // Backend debounced search across the network tree
        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $matches = User::whereIn('role', [UserRole::HIGH_LEVEL_AGENT, UserRole::LOW_LEVEL_AGENT])
                ->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('referral_code', 'like', "%{$search}%");
                })
                ->select(['id', 'name', 'email', 'role', 'status', 'referral_code', 'parent_id', 'created_at'])
                ->with([
                    'agentProfile:id,user_id,kyc_status,business_name',
                    'parent:id,name,referral_code,role',
                ])
                ->withCount('children')
                ->limit(20)
                ->get();

            $results = $matches->map(function ($agent) {
                $upline = $this->hierarchyService->getUpline($agent);
                return [
                    'id' => $agent->id,
                    'name' => $agent->name,
                    'email' => $agent->email,
                    'role' => $agent->role instanceof UserRole ? $agent->role->value : $agent->role,
                    'status' => $agent->status instanceof UserStatus ? $agent->status->value : $agent->status,
                    'referral_code' => $agent->referral_code,
                    'parent_id' => $agent->parent_id,
                    'parent_name' => $agent->parent?->name,
                    'parent_referral_code' => $agent->parent?->referral_code,
                    'children_count' => $agent->children_count,
                    'kyc_status' => $agent->agentProfile?->kyc_status ?? 'pending',
                    'ancestor_path' => $upline->pluck('id')->reverse()->values()->all(),
                ];
            });

            return response()->json([
                'search' => $search,
                'results' => $results,
            ]);
        }

        $parentId = (int) $request->query('parent_id');

        $children = User::where('parent_id', $parentId)
            ->whereIn('role', [UserRole::HIGH_LEVEL_AGENT, UserRole::LOW_LEVEL_AGENT])
            ->select(['id', 'name', 'email', 'role', 'status', 'referral_code', 'parent_id', 'created_at'])
            ->with([
                'agentProfile:id,user_id,kyc_status,business_name',
                'parent:id,name,referral_code,role',
            ])
            ->withCount('children')
            ->orderBy('name')
            ->get();

        return response()->json([
            'parent_id' => $parentId,
            'nodes' => $children,
        ]);
    }

    /**
     * Display the queue of agent hierarchy position change requests.
     */
    public function hierarchyRequests(Request $request): Response
    {
        $user = $request->user();

        if (!$user || (!$user->isOwner() && !$user->hasPermissionTo('agents.view') && !$user->hasPermissionTo('agents.hierarchy.request'))) {
            abort(403, 'Unauthorized access to Hierarchy Requests Queue.');
        }

        $query = HierarchyChangeRequest::with([
            'agent:id,name,referral_code,role,status',
            'currentParent:id,name,referral_code,role',
            'requestedParent:id,name,referral_code,role',
            'requestedBy:id,name,role',
            'reviewedBy:id,name,role',
        ]);

        // Status filter: pending, approved, rejected
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // Search filter: agent name or referral code
        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->whereHas('agent', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('referral_code', 'like', "%{$search}%");
            });
        }

        $requests = $query->latest('id')->paginate(15)->withQueryString();

        // Calculate affected subtree size for each request so Owner can inspect hierarchy impact
        $requests->getCollection()->transform(function ($req) {
            $subtreeCount = UserClosure::where('ancestor_id', $req->agent_id)->count();
            $req->subtree_size = max(0, $subtreeCount - 1);
            return $req;
        });

        // Eligible agents & sponsors for the New Request modal
        $canRequest = $user->isOwner() || $user->hasPermissionTo('agents.hierarchy.request');
        $eligibleAgents = [];
        $eligibleSponsors = [];

        if ($canRequest) {
            $eligibleAgents = User::whereIn('role', [UserRole::HIGH_LEVEL_AGENT, UserRole::LOW_LEVEL_AGENT])
                ->whereIn('status', [UserStatus::ACTIVE, UserStatus::PENDING])
                ->select(['id', 'name', 'role', 'status', 'referral_code', 'parent_id'])
                ->with(['parent:id,name,referral_code'])
                ->orderBy('name')
                ->get();

            $eligibleSponsors = User::whereIn('role', [UserRole::HIGH_LEVEL_AGENT, UserRole::LOW_LEVEL_AGENT])
                ->where('status', UserStatus::ACTIVE)
                ->select(['id', 'name', 'role', 'status', 'referral_code'])
                ->orderBy('name')
                ->get();
        }

        // Global counts
        $pendingCount = HierarchyChangeRequest::where('status', HierarchyChangeRequest::STATUS_PENDING)->count();
        $approvedCount = HierarchyChangeRequest::where('status', HierarchyChangeRequest::STATUS_APPROVED)->count();
        $rejectedCount = HierarchyChangeRequest::where('status', HierarchyChangeRequest::STATUS_REJECTED)->count();

        return Inertia::render('Admin/Agents/HierarchyRequests', [
            'requests' => $requests,
            'filters' => [
                'status' => $request->input('status', ''),
                'search' => $request->input('search', ''),
            ],
            'metrics' => [
                'pending' => $pendingCount,
                'approved' => $approvedCount,
                'rejected' => $rejectedCount,
                'total' => $pendingCount + $approvedCount + $rejectedCount,
            ],
            'eligibleAgents' => $eligibleAgents,
            'eligibleSponsors' => $eligibleSponsors,
            'isOwner' => $user->isOwner(),
            'canRequest' => $canRequest,
        ]);
    }

    /**
     * Store an agent hierarchy position move request.
     */
    public function storeHierarchyRequest(Request $request): RedirectResponse
    {
        $actor = $request->user();

        $canRequest = $actor && (
            $actor->isOwner()
            || $actor->hasPermissionTo('agents.hierarchy.request')
        );

        if (!$canRequest) {
            abort(403, 'Unauthorized: You do not have permission to request hierarchy moves.');
        }

        $validated = $request->validate([
            'agent_id' => 'required|exists:users,id',
            'requested_parent_id' => 'nullable|exists:users,id',
            'reason' => 'required|string|max:500',
        ]);

        $agent = User::findOrFail($validated['agent_id']);

        if (!$agent->isAgent()) {
            return back()->with('error', 'Target user must be an active or pending agent.');
        }

        $requestedParentId = $validated['requested_parent_id'] ? (int) $validated['requested_parent_id'] : null;

        // Check for existing pending request for this agent
        $hasPending = HierarchyChangeRequest::where('agent_id', $agent->id)
            ->where('status', HierarchyChangeRequest::STATUS_PENDING)
            ->exists();

        if ($hasPending) {
            return back()->with('error', 'A hierarchy move request for this agent is already pending Owner review.');
        }

        // Self-parenting check
        if ($requestedParentId === $agent->id) {
            return back()->with('error', 'An agent cannot be their own parent.');
        }

        // Redundant move check
        if ($requestedParentId === $agent->parent_id) {
            return back()->with('error', 'The agent is already assigned to the requested sponsor.');
        }

        // If requested parent is specified, validate role and tier rules
        if ($requestedParentId) {
            $newParent = User::find($requestedParentId);

            if (!$newParent || !$newParent->isAgent() || !$newParent->isActive()) {
                return back()->with('error', 'The requested sponsor must be an active agent.');
            }

            // High-Level Agents must remain company-direct
            if ($agent->isHighLevelAgent()) {
                return back()->with('error', 'High-Level Agents must remain company-direct and cannot be assigned under another sponsor.');
            }

            // Cycle detection at submission time
            $isDescendant = UserClosure::where('ancestor_id', $agent->id)
                ->where('descendant_id', $requestedParentId)
                ->exists();

            if ($isDescendant) {
                return back()->with('error', 'Cannot move an agent under one of their own descendants (cycle detected).');
            }
        }

        $changeRequest = HierarchyChangeRequest::create([
            'agent_id' => $agent->id,
            'current_parent_id' => $agent->parent_id,
            'requested_parent_id' => $requestedParentId,
            'requested_by' => $actor->id,
            'reason' => $validated['reason'],
            'status' => HierarchyChangeRequest::STATUS_PENDING,
        ]);

        AuditLog::log('hierarchy_move_requested', $changeRequest, null, [
            'agent_id' => $agent->id,
            'from_parent' => $agent->parent_id,
            'to_parent' => $requestedParentId,
            'requested_by' => $actor->name,
        ]);

        return back()->with('success', 'Hierarchy change request submitted. Executive Owner approval is required.');
    }

    /**
     * Direct route adapter for requesting a hierarchy move for a specific agent.
     */
    public function requestHierarchyMove(Request $request, User $agent): RedirectResponse
    {
        $request->merge(['agent_id' => $agent->id]);
        return $this->storeHierarchyRequest($request);
    }

    /**
     * Review hierarchy move request (Owner approval mandatory).
     * Atomic transaction with pessimistic row-locking, stale-request protection,
     * sponsor revalidation, and cycle verification.
     */
    public function reviewHierarchyMove(Request $request, HierarchyChangeRequest $changeRequest): RedirectResponse
    {
        $user = $request->user();

        // Enforce Owner approval for hierarchy modification
        if (!$user || !$user->isOwner()) {
            abort(403, 'Only the company Owner has authorization to execute agent hierarchy movements.');
        }

        $validated = $request->validate([
            'action' => 'required|string|in:approve,reject',
            'review_notes' => 'nullable|string|max:500',
        ]);

        return DB::transaction(function () use ($user, $changeRequest, $validated) {
            // Pessimistically lock request to prevent race conditions or replay
            $lockedRequest = HierarchyChangeRequest::where('id', $changeRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedRequest->status !== HierarchyChangeRequest::STATUS_PENDING) {
                return back()->with('error', 'This change request is already resolved.');
            }

            // Lock target agent
            $agent = User::lockForUpdate()->find($lockedRequest->agent_id);
            if (!$agent || !$agent->isAgent()) {
                return back()->with('error', 'Target user is invalid or no longer an active agent.');
            }

            // Stale Request Detection: Current parent must match the parent recorded at submission time
            if ($agent->parent_id !== $lockedRequest->current_parent_id) {
                $lockedRequest->update([
                    'status' => HierarchyChangeRequest::STATUS_REJECTED,
                    'review_notes' => 'Stale request: Target agent sponsor changed prior to executive review.',
                    'reviewed_by' => $user->id,
                    'reviewed_at' => now(),
                ]);

                AuditLog::log('hierarchy_move_stale_rejected', $lockedRequest, null, [
                    'agent_id' => $agent->id,
                    'expected_parent' => $lockedRequest->current_parent_id,
                    'actual_parent' => $agent->parent_id,
                    'owner_id' => $user->id,
                ]);

                return back()->with('error', 'Request was marked as stale and rejected because the agent sponsor changed prior to review.');
            }

            if ($validated['action'] === 'approve') {
                $newParent = null;

                if ($lockedRequest->requested_parent_id) {
                    $newParent = User::lockForUpdate()->find($lockedRequest->requested_parent_id);

                    // Revalidate sponsor eligibility at approval time
                    if (!$newParent || !$newParent->isAgent() || !$newParent->isActive()) {
                        return back()->with('error', 'The requested sponsor is no longer an active agent.');
                    }

                    // High-Level Agents must remain company-direct
                    if ($agent->isHighLevelAgent()) {
                        return back()->with('error', 'High-Level Agents must remain company-direct and cannot be assigned under another sponsor.');
                    }

                    // Re-run cycle detection at approval time
                    $isDescendant = UserClosure::where('ancestor_id', $agent->id)
                        ->where('descendant_id', $newParent->id)
                        ->exists();

                    if ($isDescendant) {
                        return back()->with('error', 'Cannot move an agent under one of their own descendants (cycle detected).');
                    }
                }

                // Execute safe move with cycle detection and subtree locking via AgentHierarchyService
                $this->hierarchyService->moveAgent($agent, $newParent);

                $lockedRequest->update([
                    'status' => HierarchyChangeRequest::STATUS_APPROVED,
                    'review_notes' => $validated['review_notes'] ?? null,
                    'reviewed_by' => $user->id,
                    'reviewed_at' => now(),
                ]);

                AuditLog::log('hierarchy_move_approved', $lockedRequest, null, [
                    'agent_id' => $agent->id,
                    'new_parent_id' => $newParent?->id,
                    'owner_id' => $user->id,
                    'notes' => $validated['review_notes'] ?? null,
                ]);

                $agent->notify(new HierarchyChangeUpdatedNotification('approved', $validated['review_notes'] ?? null));

                return back()->with('success', "Hierarchy movement approved and executed for agent {$agent->name}.");
            } else {
                $lockedRequest->update([
                    'status' => HierarchyChangeRequest::STATUS_REJECTED,
                    'review_notes' => $validated['review_notes'] ?? null,
                    'reviewed_by' => $user->id,
                    'reviewed_at' => now(),
                ]);

                AuditLog::log('hierarchy_move_rejected', $lockedRequest, null, [
                    'agent_id' => $agent->id,
                    'owner_id' => $user->id,
                    'notes' => $validated['review_notes'] ?? null,
                ]);

                $agent->notify(new HierarchyChangeUpdatedNotification('rejected', $validated['review_notes'] ?? null));

                return back()->with('success', 'Hierarchy change request was rejected.');
            }
        });
    }

    /**
     * Display the Agent Performance Leaderboard.
     */
    public function performance(Request $request): Response
    {
        $user = $request->user();

        if (!$user || (!$user->isOwner() && !$user->isSuperAdmin() && !$user->isAgentManager() && !$user->hasPermissionTo('agents.view'))) {
            abort(403, 'Unauthorized access to agent performance analytics.');
        }

        $period = $request->input('period', 'this_month');
        $roleFilter = $request->input('role', 'all');
        $search = trim($request->input('search', ''));

        $query = User::whereIn('role', [UserRole::HIGH_LEVEL_AGENT, UserRole::LOW_LEVEL_AGENT])
            ->with(['agentProfile']);

        if ($roleFilter === 'high_level_agent' || $roleFilter === 'low_level_agent') {
            $query->where('role', $roleFilter);
        }

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('referral_code', 'like', "%{$search}%");
            });
        }

        $agents = $query->get();

        // Determine date range for order analytics
        $dateFrom = match ($period) {
            'this_month' => now()->startOfMonth(),
            'last_month' => now()->subMonth()->startOfMonth(),
            '90_days' => now()->subDays(90)->startOfDay(),
            default => null, // all_time
        };
        $dateTo = match ($period) {
            'last_month' => now()->subMonth()->endOfMonth(),
            default => null,
        };

        $agentIds = $agents->pluck('id')->all();

        // Fetch order statistics grouped by agent_id
        $ordersQuery = Order::whereIn('agent_id', $agentIds);
        if ($dateFrom) {
            $ordersQuery->where('created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $ordersQuery->where('created_at', '<=', $dateTo);
        }

        $orders = $ordersQuery->select([
            'id', 'agent_id', 'status', 'subtotal', 'total_amount', 'created_at',
        ])->get();

        $ordersByAgent = $orders->groupBy('agent_id');

        $completedStatuses = ['delivered', 'cash_collected', 'completed'];
        $cancelledStatuses = ['cancelled', 'refused', 'returned'];

        $leaderboard = $agents->map(function ($agent) use ($ordersByAgent, $completedStatuses, $cancelledStatuses) {
            $agentOrders = $ordersByAgent->get($agent->id, collect());
            $totalOrders = $agentOrders->count();

            $completedOrders = $agentOrders->filter(fn ($o) => in_array($o->status, $completedStatuses, true));
            $cancelledOrders = $agentOrders->filter(fn ($o) => in_array($o->status, $cancelledStatuses, true));

            $salesVolume = (float) $completedOrders->sum('subtotal');
            $completedCount = $completedOrders->count();
            $cancelledCount = $cancelledOrders->count();

            $completionRate = $totalOrders > 0 ? round(($completedCount / $totalOrders) * 100, 1) : 0;
            $cancellationRate = $totalOrders > 0 ? round(($cancelledCount / $totalOrders) * 100, 1) : 0;

            return [
                'id' => $agent->id,
                'name' => $agent->name,
                'email' => $agent->email,
                'role' => $agent->role instanceof UserRole ? $agent->role->value : (string) $agent->role,
                'status' => $agent->status instanceof UserStatus ? $agent->status->value : (string) $agent->status,
                'referral_code' => $agent->referral_code,
                'total_orders' => $totalOrders,
                'completed_orders' => $completedCount,
                'cancelled_orders' => $cancelledCount,
                'sales_volume' => $salesVolume,
                'sales_volume_formatted' => number_format($salesVolume, 2),
                'completion_rate' => $completionRate,
                'cancellation_rate' => $cancellationRate,
            ];
        })->sortByDesc('sales_volume')->values()->map(function ($item, $index) {
            $item['rank'] = $index + 1;
            return $item;
        });

        $totalNetworkSales = $leaderboard->sum('sales_volume');
        $totalOrdersPlaced = $leaderboard->sum('total_orders');
        $totalOrdersCompleted = $leaderboard->sum('completed_orders');
        $avgOrderValue = $totalOrdersCompleted > 0 ? round($totalNetworkSales / $totalOrdersCompleted, 2) : 0;

        return Inertia::render('Admin/Agents/Performance', [
            'leaderboard' => $leaderboard,
            'metrics' => [
                'total_agents' => $agents->count(),
                'active_agents' => $agents->where('status', UserStatus::ACTIVE->value)->count(),
                'total_network_sales' => number_format($totalNetworkSales, 2),
                'total_orders' => $totalOrdersPlaced,
                'total_completed_orders' => $totalOrdersCompleted,
                'avg_order_value' => number_format($avgOrderValue, 2),
            ],
            'filters' => [
                'period' => $period,
                'role' => $roleFilter,
                'search' => $search,
            ],
        ]);
    }

    /**
     * Display the Agent Activity Audit Trail.
     */
    public function activity(Request $request): Response
    {
        $user = $request->user();

        if (!$user || (!$user->isOwner() && !$user->isSuperAdmin() && !$user->isAgentManager() && !$user->hasPermissionTo('agents.view'))) {
            abort(403, 'Unauthorized access to agent activity audit trail.');
        }

        $query = AuditLog::with('user:id,name,role,email')
            ->where(function ($q) {
                $q->where('action', 'like', 'agent_%')
                    ->orWhere('action', 'like', 'hierarchy_%')
                    ->orWhereIn('auditable_type', [
                        'App\Models\User',
                        'App\Models\AgentInvitation',
                        'App\Models\HierarchyChangeRequest',
                    ]);
            })
            ->latest('id');

        if ($request->filled('action')) {
            $query->where('action', $request->input('action'));
        }

        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('action', 'like', "%{$search}%")
                    ->orWhere('ip_address', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($uq) => $uq->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        $logs = $query->paginate(20)->withQueryString();

        // Sanitize payloads to ensure no raw NIC or bank accounts are visible
        $logs->getCollection()->transform(function ($log) {
            $sanitizedNew = $log->new_values;
            $sanitizedOld = $log->old_values;

            foreach (['nic', 'bank_account_number', 'password'] as $sensitiveField) {
                if (is_array($sanitizedNew) && isset($sanitizedNew[$sensitiveField])) {
                    $sanitizedNew[$sensitiveField] = '********';
                }
                if (is_array($sanitizedOld) && isset($sanitizedOld[$sensitiveField])) {
                    $sanitizedOld[$sensitiveField] = '********';
                }
            }

            $log->new_values = $sanitizedNew;
            $log->old_values = $sanitizedOld;

            return $log;
        });

        // Available actions for filtering
        $availableActions = AuditLog::where(function ($q) {
                $q->where('action', 'like', 'agent_%')
                    ->orWhere('action', 'like', 'hierarchy_%');
            })
            ->distinct()
            ->pluck('action')
            ->values();

        return Inertia::render('Admin/Agents/Activity', [
            'logs' => $logs,
            'filters' => [
                'action' => $request->input('action', ''),
                'search' => $request->input('search', ''),
                'date_from' => $request->input('date_from', ''),
                'date_to' => $request->input('date_to', ''),
            ],
            'availableActions' => $availableActions,
        ]);
    }

    /**
     * Display the Agent Export Center.
     */
    public function reports(Request $request): Response
    {
        $user = $request->user();

        if (!$user || (!$user->isOwner() && !$user->isSuperAdmin() && !$user->isAgentManager() && !$user->hasPermissionTo('agents.view'))) {
            abort(403, 'Unauthorized access to agent export reports.');
        }

        $totalAgents = User::whereIn('role', [UserRole::HIGH_LEVEL_AGENT, UserRole::LOW_LEVEL_AGENT])->count();
        $totalHla = User::where('role', UserRole::HIGH_LEVEL_AGENT)->count();
        $totalLla = User::where('role', UserRole::LOW_LEVEL_AGENT)->count();
        $pendingKyc = AgentProfile::where('kyc_status', 'pending')->count();

        return Inertia::render('Admin/Agents/Reports', [
            'stats' => [
                'total_agents' => $totalAgents,
                'total_hla' => $totalHla,
                'total_lla' => $totalLla,
                'pending_kyc' => $pendingKyc,
            ],
        ]);
    }

    /**
     * Stream CSV export of requested agent report type.
     */
    public function exportReport(Request $request): StreamedResponse
    {
        $user = $request->user();

        if (!$user || (!$user->isOwner() && !$user->isSuperAdmin() && !$user->isAgentManager() && !$user->hasPermissionTo('agents.view'))) {
            abort(403, 'Unauthorized export access.');
        }

        $type = $request->input('type', 'directory');
        if (!in_array($type, ['directory', 'performance', 'hierarchy', 'kyc'], true)) {
            abort(400, 'Invalid export type requested.');
        }

        AuditLog::log('agent_report_exported', null, null, [
            'type' => $type,
            'exported_by' => $user->id,
        ]);

        $fileName = "aura_luxe_{$type}_report_" . now()->format('Ymd_His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return response()->stream(function () use ($type) {
            $handle = fopen('php://output', 'w');
            // Write UTF-8 BOM for Excel compatibility
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            if ($type === 'directory') {
                fputcsv($handle, ['Agent ID', 'Name', 'Email', 'Phone', 'Role', 'Status', 'Referral Code', 'Sponsor Parent ID', 'Registered At']);
                User::whereIn('role', [UserRole::HIGH_LEVEL_AGENT, UserRole::LOW_LEVEL_AGENT])
                    ->with('parent:id,name,referral_code')
                    ->chunk(200, function ($agents) use ($handle) {
                        foreach ($agents as $agent) {
                            fputcsv($handle, [
                                $agent->id,
                                $agent->name,
                                $agent->email,
                                $agent->phone,
                                $agent->role instanceof UserRole ? $agent->role->label() : (string) $agent->role,
                                $agent->status instanceof UserStatus ? ucfirst($agent->status->value) : ucfirst((string) $agent->status),
                                $agent->referral_code,
                                $agent->parent ? "{$agent->parent->name} ({$agent->parent->referral_code})" : 'None (Company-Direct)',
                                $agent->created_at?->format('Y-m-d H:i:s'),
                            ]);
                        }
                    });
            } elseif ($type === 'performance') {
                fputcsv($handle, ['Agent ID', 'Name', 'Role', 'Referral Code', 'Total Orders', 'Completed Orders', 'Completed Sales Volume (LKR)']);
                User::whereIn('role', [UserRole::HIGH_LEVEL_AGENT, UserRole::LOW_LEVEL_AGENT])
                    ->chunk(200, function ($agents) use ($handle) {
                        foreach ($agents as $agent) {
                            $orders = Order::where('agent_id', $agent->id)->get();
                            $completed = $orders->filter(fn ($o) => in_array($o->status, ['delivered', 'cash_collected', 'completed'], true));
                            fputcsv($handle, [
                                $agent->id,
                                $agent->name,
                                $agent->role instanceof UserRole ? $agent->role->label() : (string) $agent->role,
                                $agent->referral_code,
                                $orders->count(),
                                $completed->count(),
                                number_format((float) $completed->sum('subtotal'), 2, '.', ''),
                            ]);
                        }
                    });
            } elseif ($type === 'hierarchy') {
                fputcsv($handle, ['Ancestor ID', 'Ancestor Name', 'Ancestor Role', 'Descendant ID', 'Descendant Name', 'Descendant Role', 'Depth']);
                UserClosure::with(['ancestor:id,name,role', 'descendant:id,name,role'])
                    ->chunk(200, function ($closures) use ($handle) {
                        foreach ($closures as $c) {
                            fputcsv($handle, [
                                $c->ancestor_id,
                                $c->ancestor?->name ?? 'N/A',
                                $c->ancestor?->role instanceof UserRole ? $c->ancestor->role->value : (string) $c->ancestor?->role,
                                $c->descendant_id,
                                $c->descendant?->name ?? 'N/A',
                                $c->descendant?->role instanceof UserRole ? $c->descendant->role->value : (string) $c->descendant?->role,
                                $c->depth,
                            ]);
                        }
                    });
            } elseif ($type === 'kyc') {
                fputcsv($handle, ['Agent ID', 'Name', 'Email', 'Role', 'KYC Status', 'Bank Name', 'Bank Branch', 'Updated At']);
                User::whereIn('role', [UserRole::HIGH_LEVEL_AGENT, UserRole::LOW_LEVEL_AGENT])
                    ->with('agentProfile')
                    ->chunk(200, function ($agents) use ($handle) {
                        foreach ($agents as $agent) {
                            fputcsv($handle, [
                                $agent->id,
                                $agent->name,
                                $agent->email,
                                $agent->role instanceof UserRole ? $agent->role->label() : (string) $agent->role,
                                ucfirst($agent->agentProfile?->kyc_status ?? 'N/A'),
                                $agent->agentProfile?->bank_name ?? 'N/A',
                                $agent->agentProfile?->bank_branch ?? 'N/A',
                                $agent->agentProfile?->updated_at?->format('Y-m-d H:i:s') ?? 'N/A',
                            ]);
                        }
                    });
            }

            fclose($handle);
        }, 200, $headers);
    }
}

