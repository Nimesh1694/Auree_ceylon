<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    /**
     * Display a paginated directory of customer accounts.
     * Strictly scopes query to role='customer' and excludes staff, Owner, Super Admin, and Agents.
     */
    public function index(Request $request): Response
    {
        $query = User::query()
            ->where('role', UserRole::CUSTOMER)
            ->withCount('orders')
            ->withSum([
                'orders as total_spent' => function ($subQuery) {
                    $subQuery->whereIn('status', ['delivered', 'cash_collected', 'completed']);
                },
            ], 'total_amount')
            ->withMax('orders as latest_order_date', 'placed_at');

        // Search by customer name, email, or phone
        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // Filter by account status (active, suspended)
        if ($request->filled('status')) {
            $status = trim((string) $request->input('status'));
            if (in_array($status, ['active', 'suspended', 'pending'], true)) {
                $query->where('status', $status);
            }
        }

        $customers = $query->latest('id')->paginate(15)->withQueryString();

        // Safe transform to ensure sensitive credentials are never serialized
        $customers->getCollection()->transform(function (User $customer) {
            return [
                'id' => $customer->id,
                'name' => $customer->name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'status' => $customer->status instanceof UserStatus ? $customer->status->value : (string) $customer->status,
                'created_at' => $customer->created_at?->toIso8601String(),
                'orders_count' => (int) ($customer->orders_count ?? 0),
                'total_spent' => (float) ($customer->total_spent ?? 0.0),
                'latest_order_date' => $customer->latest_order_date,
            ];
        });

        // Summary counts for filter tabs
        $baseCountQuery = User::where('role', UserRole::CUSTOMER);
        $totalCustomers = (clone $baseCountQuery)->count();
        $activeCustomers = (clone $baseCountQuery)->where('status', UserStatus::ACTIVE)->count();
        $suspendedCustomers = (clone $baseCountQuery)->where('status', UserStatus::SUSPENDED)->count();

        return Inertia::render('Admin/Customers/Index', [
            'customers' => $customers,
            'filters' => $request->only(['search', 'status']),
            'stats' => [
                'total' => $totalCustomers,
                'active' => $activeCustomers,
                'suspended' => $suspendedCustomers,
            ],
        ]);
    }

    /**
     * Display the specified customer's profile, saved addresses, and order history.
     * Enforces strict customer role validation to prevent IDOR traversal across non-customers.
     */
    public function show(Request $request, User $customer): Response
    {
        // IDOR & Role Protection: Only users with role exactly 'customer' can be inspected
        if (!$customer->isCustomer()) {
            abort(404, 'Customer record not found.');
        }

        // 1. Saved delivery addresses from CustomerAddress model
        $addresses = $customer->customerAddresses()
            ->orderByDesc('is_default')
            ->latest('updated_at')
            ->get();

        // 2. Paginated customer order history (strictly customer-facing fields)
        $ordersQuery = $customer->orders()->latest('placed_at');

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $ordersQuery->where('order_number', 'like', "%{$search}%");
        }

        if ($request->filled('status')) {
            $ordersQuery->where('status', $request->input('status'));
        }

        $orders = $ordersQuery->withCount('items')->paginate(10)->withQueryString();

        // Transform orders to exclude internal financial data (commissions, uplines, cost prices)
        $orders->getCollection()->transform(function ($order) {
            return [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'total_amount' => (float) $order->total_amount,
                'delivery_charge' => (float) $order->delivery_charge,
                'payment_method' => $order->payment_method,
                'placed_at' => $order->placed_at?->toIso8601String(),
                'items_count' => (int) ($order->items_count ?? 0),
            ];
        });

        // 3. Customer Lifetime Aggregates
        $totalOrders = $customer->orders()->count();
        $completedOrders = $customer->orders()
            ->whereIn('status', ['delivered', 'cash_collected', 'completed'])
            ->count();
        $totalSpent = (float) ($customer->orders()
            ->whereIn('status', ['delivered', 'cash_collected', 'completed'])
            ->sum('total_amount') ?? 0.0);
        $savedAddressesCount = $addresses->count();
        $latestOrderDate = $customer->orders()->max('placed_at');

        // 4. Audit Log History for this customer
        $auditLogs = AuditLog::with('user:id,name,role')
            ->where('auditable_type', User::class)
            ->where('auditable_id', $customer->id)
            ->latest('id')
            ->take(20)
            ->get();

        return Inertia::render('Admin/Customers/Show', [
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'status' => $customer->status instanceof UserStatus ? $customer->status->value : (string) $customer->status,
                'email_verified_at' => $customer->email_verified_at?->toIso8601String(),
                'created_at' => $customer->created_at?->toIso8601String(),
            ],
            'addresses' => $addresses,
            'orders' => $orders,
            'filters' => $request->only(['search', 'status']),
            'metrics' => [
                'total_orders' => $totalOrders,
                'completed_orders' => $completedOrders,
                'total_spent' => $totalSpent,
                'saved_addresses_count' => $savedAddressesCount,
                'latest_order_date' => $latestOrderDate,
            ],
            'auditLogs' => $auditLogs,
        ]);
    }

    /**
     * Suspend or reactivate a customer's account.
     * Enforces strict role checks, session termination, and audit logging.
     */
    public function toggleStatus(Request $request, User $customer): RedirectResponse
    {
        $actor = $request->user();

        // 1. Authorization: Only Owner, Super Admin, Operations Manager, or users with users.suspend permission
        $canSuspend = $actor && (
            $actor->isOwner()
            || $actor->isTechnicalAuthority()
            || $actor->role === UserRole::OPERATIONS_MANAGER
            || $actor->hasPermissionTo('users.suspend')
        );

        if (!$canSuspend) {
            abort(403, 'Unauthorized to alter customer account status.');
        }

        // 2. IDOR & Role Protection: Only customer-role users can be targeted
        if (!$customer->isCustomer()) {
            abort(404, 'Customer record not found.');
        }

        // 3. Validation: Meaningful reason required when suspending
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:active,suspended'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        if ($validated['status'] === 'suspended' && empty(trim((string) ($validated['reason'] ?? '')))) {
            throw ValidationException::withMessages([
                'reason' => ['A meaningful suspension reason is required when suspending a customer account.'],
            ]);
        }

        // 4. Atomic Database Transaction
        DB::transaction(function () use ($customer, $validated, $actor) {
            $priorStatus = $customer->status instanceof UserStatus ? $customer->status->value : (string) $customer->status;
            $newStatus = UserStatus::from($validated['status']);

            $customer->update(['status' => $newStatus]);

            // When suspended, immediately terminate all active sessions for this specific customer
            // and cycle the remember_token to invalidate any existing Remember-Me recaller cookies
            if ($newStatus === UserStatus::SUSPENDED) {
                DB::table('sessions')->where('user_id', $customer->id)->delete();
                $customer->forceFill(['remember_token' => Str::random(60)])->save();
            }

            // Record in AuditLog with actor details
            AuditLog::log(
                $newStatus === UserStatus::SUSPENDED ? 'customer_suspended' : 'customer_reactivated',
                $customer,
                ['status' => $priorStatus],
                [
                    'status' => $newStatus->value,
                    'reason' => $validated['reason'] ?? null,
                    'actor_id' => $actor->id,
                    'actor_name' => $actor->name,
                ]
            );
        });

        $actionLabel = $validated['status'] === 'suspended' ? 'suspended' : 'reactivated';

        return back()->with(
            'success',
            "Customer '{$customer->name}' has been successfully {$actionLabel}."
        );
    }
}

