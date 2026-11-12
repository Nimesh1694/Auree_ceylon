<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssignInquiryStaffRequest;
use App\Http\Requests\Admin\StoreInquiryNoteRequest;
use App\Http\Requests\Admin\UpdateInquiryPriorityRequest;
use App\Http\Requests\Admin\UpdateInquiryStatusRequest;
use App\Models\CustomerInquiry;
use App\Models\User;
use App\Services\CustomerInquiryAdminService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerInquiryController extends Controller
{
    use AuthorizesRequests;
    public function __construct(
        protected readonly CustomerInquiryAdminService $inquiryService
    ) {}

    /**
     * Display a paginated directory of customer inquiries with triage metrics.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', CustomerInquiry::class);

        $query = CustomerInquiry::query()
            ->with(['assignedStaff:id,name,email']);

        // Search by inquiry number, customer name, email, or phone
        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('inquiry_number', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // Filter by lifecycle status
        if ($request->filled('status')) {
            $status = trim((string) $request->input('status'));
            if (in_array($status, CustomerInquiry::getStatuses(), true)) {
                $query->where('status', $status);
            }
        }

        // Filter by priority
        if ($request->filled('priority')) {
            $priority = trim((string) $request->input('priority'));
            if (in_array($priority, CustomerInquiry::getPriorities(), true)) {
                $query->where('priority', $priority);
            }
        }

        // Filter by assigned staff
        if ($request->filled('assigned_to')) {
            $assignedTo = $request->input('assigned_to');
            if ($assignedTo === 'unassigned') {
                $query->whereNull('assigned_to');
            } elseif (is_numeric($assignedTo)) {
                $query->where('assigned_to', (int) $assignedTo);
            }
        }

        $inquiries = $query->latest('created_at')->paginate(15)->withQueryString();

        // Safe transform: strictly exclude diagnostic IP and User-Agent from standard props
        $inquiries->getCollection()->transform(function (CustomerInquiry $inquiry) {
            return [
                'id' => $inquiry->id,
                'inquiry_number' => $inquiry->inquiry_number,
                'customer_id' => $inquiry->customer_id,
                'name' => $inquiry->name,
                'email' => $inquiry->email,
                'phone' => $inquiry->phone,
                'subject' => $inquiry->subject,
                'status' => $inquiry->status,
                'priority' => $inquiry->priority,
                'notification_status' => $inquiry->notification_status,
                'created_at' => $inquiry->created_at?->toIso8601String(),
                'assigned_staff' => $inquiry->assignedStaff ? [
                    'id' => $inquiry->assignedStaff->id,
                    'name' => $inquiry->assignedStaff->name,
                    'email' => $inquiry->assignedStaff->email,
                ] : null,
            ];
        });

        // Triage Metric Summaries
        $stats = [
            'total' => CustomerInquiry::count(),
            'new' => CustomerInquiry::where('status', CustomerInquiry::STATUS_NEW)->count(),
            'in_progress' => CustomerInquiry::where('status', CustomerInquiry::STATUS_IN_PROGRESS)->count(),
            'waiting_customer' => CustomerInquiry::where('status', CustomerInquiry::STATUS_WAITING_CUSTOMER)->count(),
            'resolved' => CustomerInquiry::where('status', CustomerInquiry::STATUS_RESOLVED)->count(),
            'closed' => CustomerInquiry::where('status', CustomerInquiry::STATUS_CLOSED)->count(),
            'urgent_open' => CustomerInquiry::whereIn('status', [CustomerInquiry::STATUS_NEW, CustomerInquiry::STATUS_IN_PROGRESS])
                ->where('priority', CustomerInquiry::PRIORITY_URGENT)
                ->count(),
        ];

        // Eligible staff members for assignment/filter dropdown
        $eligibleStaff = User::query()
            ->where('status', 'active')
            ->get()
            ->filter(fn (User $u) => $this->inquiryService->isStaffEligible($u))
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'role' => $u->getRoleValue(),
            ])
            ->values();

        return Inertia::render('Admin/Inquiries/Index', [
            'inquiries' => $inquiries,
            'filters' => $request->only(['search', 'status', 'priority', 'assigned_to']),
            'stats' => $stats,
            'eligibleStaff' => $eligibleStaff,
        ]);
    }

    /**
     * Display a specific inquiry's details, customer dossier, and append-only notes thread.
     */
    public function show(CustomerInquiry $inquiry): Response
    {
        $this->authorize('view', $inquiry);

        $inquiry->load([
            'customer:id,name,email,phone,status,created_at',
            'assignedStaff:id,name,email,role',
            'resolvedBy:id,name,email',
            'notes.author:id,name,role',
        ]);

        // Customer dossier data (if registered)
        $customerDossier = null;
        if ($inquiry->customer) {
            $customerDossier = [
                'id' => $inquiry->customer->id,
                'name' => $inquiry->customer->name,
                'email' => $inquiry->customer->email,
                'phone' => $inquiry->customer->phone,
                'status' => $inquiry->customer->status instanceof \App\Enums\UserStatus ? $inquiry->customer->status->value : (string) $inquiry->customer->status,
                'member_since' => $inquiry->customer->created_at?->toIso8601String(),
                'orders_count' => $inquiry->customer->orders()->count(),
                'total_spent' => (float) $inquiry->customer->orders()->whereIn('status', ['delivered', 'cash_collected', 'completed'])->sum('total_amount'),
            ];
        }

        // Notes thread transform (append-only)
        $notes = $inquiry->notes->map(function ($note) {
            return [
                'id' => $note->id,
                'note' => $note->note,
                'created_at' => $note->created_at?->toIso8601String(),
                'author' => $note->author ? [
                    'id' => $note->author->id,
                    'name' => $note->author->name,
                    'role' => $note->author->getRoleValue(),
                ] : null,
            ];
        });

        // Eligible staff members for assignment modal/dropdown
        $eligibleStaff = User::query()
            ->where('status', 'active')
            ->get()
            ->filter(fn (User $u) => $this->inquiryService->isStaffEligible($u))
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'role' => $u->getRoleValue(),
            ])
            ->values();

        return Inertia::render('Admin/Inquiries/Show', [
            'inquiry' => [
                'id' => $inquiry->id,
                'inquiry_number' => $inquiry->inquiry_number,
                'name' => $inquiry->name,
                'email' => $inquiry->email,
                'phone' => $inquiry->phone,
                'subject' => $inquiry->subject,
                'message' => $inquiry->message,
                'status' => $inquiry->status,
                'priority' => $inquiry->priority,
                'assigned_to' => $inquiry->assigned_to,
                'assigned_staff' => $inquiry->assignedStaff ? [
                    'id' => $inquiry->assignedStaff->id,
                    'name' => $inquiry->assignedStaff->name,
                    'email' => $inquiry->assignedStaff->email,
                ] : null,
                'resolved_at' => $inquiry->resolved_at?->toIso8601String(),
                'resolved_by' => $inquiry->resolvedBy ? [
                    'id' => $inquiry->resolvedBy->id,
                    'name' => $inquiry->resolvedBy->name,
                ] : null,
                'notification_status' => $inquiry->notification_status,
                'created_at' => $inquiry->created_at?->toIso8601String(),
                'updated_at' => $inquiry->updated_at?->toIso8601String(),
            ],
            'customerDossier' => $customerDossier,
            'notes' => $notes,
            'eligibleStaff' => $eligibleStaff,
            'statuses' => CustomerInquiry::getStatuses(),
            'priorities' => CustomerInquiry::getPriorities(),
        ]);
    }

    /**
     * Update an inquiry's status according to state machine rules.
     */
    public function updateStatus(UpdateInquiryStatusRequest $request, CustomerInquiry $inquiry): RedirectResponse
    {
        $this->inquiryService->updateStatus(
            $inquiry,
            $request->input('status'),
            $request->user(),
            $request->input('reason')
        );

        return redirect()->back()->with('success', 'Inquiry status updated successfully.');
    }

    /**
     * Update an inquiry's priority level.
     */
    public function updatePriority(UpdateInquiryPriorityRequest $request, CustomerInquiry $inquiry): RedirectResponse
    {
        $this->inquiryService->updatePriority(
            $inquiry,
            $request->input('priority'),
            $request->user()
        );

        return redirect()->back()->with('success', 'Inquiry priority updated successfully.');
    }

    /**
     * Assign or unassign an inquiry.
     */
    public function assignStaff(AssignInquiryStaffRequest $request, CustomerInquiry $inquiry): RedirectResponse
    {
        $this->inquiryService->assignStaff(
            $inquiry,
            $request->input('staff_user_id'),
            $request->user()
        );

        return redirect()->back()->with('success', 'Staff assignment updated successfully.');
    }

    /**
     * Append an immutable internal staff note.
     */
    public function storeNote(StoreInquiryNoteRequest $request, CustomerInquiry $inquiry): RedirectResponse
    {
        $this->inquiryService->addNote(
            $inquiry,
            $request->input('note'),
            $request->user()
        );

        return redirect()->back()->with('success', 'Internal note added successfully.');
    }
}
