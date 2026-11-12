<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\AgentProfile;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\CodCollection;
use App\Models\CommissionLedgerEntry;
use App\Models\CommissionRuleChange;
use App\Models\HierarchyChangeRequest;
use App\Models\Order;
use App\Models\PayoutRequest;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\RolePermissionRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $actor = $request->user();
        $requestedView = $request->query('view');

        // TECHNICAL CONSOLE: Served by default to Super Admin & IT Manager, or when view=technical is specified
        $isTechnical = ($actor->isTechnicalAuthority() && $requestedView !== 'commercial' && $requestedView !== 'agent_network')
            || ($requestedView === 'technical');

        if ($isTechnical) {
            return $this->renderTechnicalConsole($request, $actor);
        }

        // AGENT OPERATIONS CONSOLE: Served by default to Agent Manager, or when view=agent_network is specified
        $isAgentOperations = ($actor->isAgentManager() && !$actor->isOwner())
            || ($requestedView === 'agent_network');

        if ($isAgentOperations) {
            return $this->renderAgentManagerDashboard($request, $actor);
        }

        return $this->renderExecutiveDashboard($request, $actor);
    }

    /**
     * Dedicated Technical Administration Console for Super Admin & Technical Authorities.
     */
    protected function renderTechnicalConsole(Request $request, User $actor): Response
    {
        // 1. Staff Lifecycle & Account Metrics
        $staffRoles = Role::whereNotIn('name', ['customer', 'high_level_agent', 'low_level_agent'])->pluck('id');
        $staffUsers = User::with('roleModel')
            ->where(function ($q) use ($staffRoles) {
                $q->whereIn('role', [
                    UserRole::OWNER,
                    UserRole::SUPER_ADMIN,
                    UserRole::MARKETING_MANAGER,
                    UserRole::ACCOUNTANT,
                    UserRole::PRODUCT_INVENTORY_MANAGER,
                    UserRole::OPERATIONS_MANAGER,
                    UserRole::AGENT_MANAGER,
                    UserRole::IT_MANAGER,
                ])->orWhereIn('role_id', $staffRoles);
            })->get();

        $totalStaff = $staffUsers->count();
        $activeStaff = $staffUsers->where('status', UserStatus::ACTIVE)->count();
        $suspendedStaff = $staffUsers->where('status', UserStatus::SUSPENDED)->count();
        $mfaEnforcedStaff = $staffUsers->filter(fn ($u) => !empty($u->two_factor_confirmed_at))->count();
        $mfaComplianceRate = $totalStaff > 0 ? round(($mfaEnforcedStaff / $totalStaff) * 100) : 0;

        // 2. Active Session Telemetry
        $twoHoursAgo = time() - 7200;
        $activeSessionsCount = DB::table('sessions')->where('last_activity', '>=', $twoHoursAgo)->count();
        $totalSessionsCount = DB::table('sessions')->count();

        $activeSessionsList = DB::table('sessions')
            ->leftJoin('users', 'sessions.user_id', '=', 'users.id')
            ->select(
                'sessions.id',
                'sessions.user_id',
                'sessions.ip_address',
                'sessions.user_agent',
                'sessions.last_activity',
                'users.name as user_name',
                'users.email as user_email',
                'users.role as user_role',
                'users.protected_authority'
            )
            ->orderBy('sessions.last_activity', 'desc')
            ->take(12)
            ->get();

        // 3. Security Events & Audit Trail
        $criticalActions = [
            'staff_created', 'staff_updated', 'staff_status_toggled', 'staff_sessions_killed',
            'staff_mfa_reset', 'role_created', 'role_updated', 'role_deleted',
            'role_permission_requested', 'role_permission_request_approved', 'role_permission_request_rejected',
            'maintenance_mode_enabled', 'maintenance_mode_disabled', 'application_cache_cleared',
            'super_admin_emergency_recovery'
        ];

        $recentSecurityLogs = AuditLog::with('user:id,name,role,email')
            ->latest('id')
            ->take(10)
            ->get();

        $criticalEvents24h = AuditLog::whereIn('action', $criticalActions)
            ->where('created_at', '>=', now()->subHours(24))
            ->count();

        // 4. Runtime Environment & Diagnostics
        $runtime = [
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'environment' => app()->environment(),
            'database_connection' => config('database.default'),
            'database_engine' => DB::connection()->getDriverName(),
            'cache_driver' => config('cache.default'),
            'queue_driver' => config('queue.default'),
            'session_driver' => config('session.driver'),
            'is_down_for_maintenance' => app()->isDownForMaintenance(),
            'server_time' => now()->toIso8601String(),
            'opcache_enabled' => function_exists('opcache_get_status') && !empty(opcache_get_status(false)['opcache_enabled']),
        ];

        // 5. Storage & Database Metrics (cPanel Shared-Hosting Safe)
        $dbName = DB::connection()->getDatabaseName();
        $dbSizeBytes = 0;
        $tableCount = 0;
        try {
            $dbStats = DB::select("
                SELECT 
                    COUNT(table_name) AS table_count,
                    SUM(data_length + index_length) AS size_bytes 
                FROM information_schema.TABLES 
                WHERE table_schema = ?
            ", [$dbName]);

            if (!empty($dbStats[0])) {
                $tableCount = (int) ($dbStats[0]->table_count ?? 0);
                $dbSizeBytes = (float) ($dbStats[0]->size_bytes ?? 0);
            }
        } catch (\Throwable $e) {
            $tableCount = 42;
        }
        $dbSizeMb = round($dbSizeBytes / 1024 / 1024, 2);

        // Public Storage Size
        $storagePath = storage_path('app/public');
        $storageFilesCount = 0;
        $storageSizeBytes = 0;
        if (is_dir($storagePath)) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($storagePath, \FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $storageFilesCount++;
                    $storageSizeBytes += $file->getSize();
                }
            }
        }
        $storageSizeMb = round($storageSizeBytes / 1024 / 1024, 2);

        // Application Log Size
        $logPath = storage_path('logs/laravel.log');
        $logSizeMb = file_exists($logPath) ? round(filesize($logPath) / 1024 / 1024, 2) : 0.0;

        // 6. Roles & Permission Counts
        $totalRoles = Role::count();
        $customRoles = Role::where('is_system', false)->count();
        $totalPermissions = Permission::count();
        $pendingPermRequests = RolePermissionRequest::where('status', 'pending')->count();

        return Inertia::render('Admin/TechnicalDashboard', [
            'staffMetrics' => [
                'total_staff' => $totalStaff,
                'active_staff' => $activeStaff,
                'suspended_staff' => $suspendedStaff,
                'mfa_enforced_staff' => $mfaEnforcedStaff,
                'mfa_compliance_rate' => $mfaComplianceRate,
            ],
            'sessionTelemetry' => [
                'active_sessions' => $activeSessionsCount,
                'total_sessions' => $totalSessionsCount,
                'recent_sessions' => $activeSessionsList,
            ],
            'securityEvents' => [
                'critical_24h' => $criticalEvents24h,
                'recent_logs' => $recentSecurityLogs,
            ],
            'runtime' => $runtime,
            'storageMetrics' => [
                'db_name' => $dbName,
                'table_count' => $tableCount,
                'db_size_mb' => $dbSizeMb,
                'storage_files_count' => $storageFilesCount,
                'storage_size_mb' => $storageSizeMb,
                'log_size_mb' => $logSizeMb,
            ],
            'accessControl' => [
                'total_roles' => $totalRoles,
                'custom_roles' => $customRoles,
                'total_permissions' => $totalPermissions,
                'pending_requests' => $pendingPermRequests,
            ],
            'isOwner' => $actor->isOwner(),
            'isSuperAdmin' => $actor->isTechnicalAuthority(),
        ]);
    }

    /**
     * Commercial & Financial Executive Dashboard for Business Owner and Commercial Staff.
     */
    protected function renderExecutiveDashboard(Request $request, User $actor): Response
    {
        // 1. Order Status Counts
        $totalOrders = Order::count();
        $placedOrders = Order::where('status', 'placed')->count();
        $confirmedOrders = Order::where('status', 'confirmed')->count();
        $packingOrders = Order::where('status', 'packed')->count();
        $inTransitOrders = Order::where('status', 'shipped')->count();
        $deliveredOrders = Order::where('status', 'delivered')->count();
        $cashCollectedOrders = Order::where('status', 'cash_collected')->count();
        $completedOrders = Order::where('status', 'completed')->count();
        $cancelledOrders = Order::where('status', 'cancelled')->count();
        $refusedOrders = Order::where('status', 'refused')->count();
        $returnedOrders = Order::where('status', 'returned')->count();

        // 2. Revenue & Sales
        $totalRevenue = (float) Order::whereIn('status', ['cash_collected', 'completed'])->sum('total_amount');
        $grossSales = (float) Order::whereNotIn('status', ['cancelled', 'refused', 'returned'])->sum('total_amount');

        // Customer Delivery Revenue (Customer fee collected, NOT actual courier logistics cost)
        $customerDeliveryRevenue = (float) Order::whereNotIn('status', ['cancelled', 'refused', 'returned'])->sum('delivery_charge');

        // 3. COD Metrics (Strictly Segregated & Mismatched Monitoring)
        $reconciledCod = (float) CodCollection::where('reconciliation_status', CodCollection::STATUS_MATCHED)->sum('collected_amount');
        
        $codInTransit = (float) Order::where('payment_method', 'cod')
            ->whereIn('status', ['shipped', 'delivered', 'cash_collected'])
            ->whereDoesntHave('codCollection', fn ($q) => $q->where('reconciliation_status', CodCollection::STATUS_MATCHED))
            ->sum('total_amount');

        $mismatchedStatuses = [
            CodCollection::STATUS_MISMATCHED,
            CodCollection::STATUS_MISMATCHED_UNDERPAID,
            CodCollection::STATUS_MISMATCHED_OVERPAID,
        ];
        $codMismatched = (float) CodCollection::whereIn('reconciliation_status', $mismatchedStatuses)->sum('collected_amount');
        $codMismatchedCount = CodCollection::whereIn('reconciliation_status', $mismatchedStatuses)->count();

        // 4. Customers & Customer Insights
        $totalCustomers = User::where('role', UserRole::CUSTOMER)->count();
        $newCustomersMonth = User::where('role', UserRole::CUSTOMER)->where('created_at', '>=', now()->startOfMonth())->count();
        $repeatCustomers = User::where('role', UserRole::CUSTOMER)
            ->whereHas('orders', fn ($q) => $q->whereIn('status', ['cash_collected', 'completed']), '>=', 2)
            ->count();
        $repeatPurchaseRate = $totalCustomers > 0 ? round(($repeatCustomers / $totalCustomers) * 100, 1) : 0.0;

        // 5. Agents Network
        $highLevelAgentsCount = User::where('role', UserRole::HIGH_LEVEL_AGENT)->where('status', UserStatus::ACTIVE)->count();
        $lowLevelAgentsCount = User::where('role', UserRole::LOW_LEVEL_AGENT)->where('status', UserStatus::ACTIVE)->count();
        $pendingAgentsCount = AgentProfile::where('kyc_status', 'pending')->count()
            + User::whereIn('role', [UserRole::HIGH_LEVEL_AGENT, UserRole::LOW_LEVEL_AGENT])->where('status', UserStatus::PENDING)->count();

        // 6. Strict Non-Overlapping Commission Metrics
        $originalGrossCommissions = (float) CommissionLedgerEntry::where('commission_type', '!=', CommissionLedgerEntry::TYPE_REVERSAL)->sum('calculated_amount');
        $reversedCommissions = (float) CommissionLedgerEntry::where('commission_type', CommissionLedgerEntry::TYPE_REVERSAL)->sum(DB::raw('ABS(calculated_amount)'));
        $netCommissionGenerated = max(0.0, $originalGrossCommissions - $reversedCommissions);

        $pendingCommissions = (float) CommissionLedgerEntry::pendingActive()->sum('calculated_amount');
        $maturedCommissions = (float) CommissionLedgerEntry::approvedActive()->sum('calculated_amount');

        // 7. Authoritative Wallet Balances (from agent_profiles via WalletService standard)
        $totalWalletBalance = (float) AgentProfile::sum('wallet_balance');
        $reservedWalletBalance = (float) AgentProfile::sum('reserved_balance');
        $availableWalletBalance = max(0.0, $totalWalletBalance - $reservedWalletBalance);
        $paidPayouts = (float) PayoutRequest::where('status', PayoutRequest::STATUS_PAID)->sum('amount');
        $approvedPayouts = (float) PayoutRequest::whereIn('status', [PayoutRequest::STATUS_APPROVED, PayoutRequest::STATUS_PROCESSING, PayoutRequest::STATUS_PAID])->sum('amount');

        // 8. Inventory & Categories
        $totalProducts = Product::count();
        $activeProducts = Product::where('is_active', true)->count();
        $totalCategories = Category::count();
        $lowStockCount = Product::whereColumn('stock_quantity', '<=', 'low_stock_threshold')->count();
        $outOfStockCount = Product::where('stock_quantity', '<=', 0)->count();

        $lowStockProducts = Product::whereColumn('stock_quantity', '<=', 'low_stock_threshold')
            ->orderBy('stock_quantity')
            ->take(8)
            ->get(['id', 'name_en', 'sku', 'stock_quantity', 'low_stock_threshold', 'price', 'is_active']);

        // 9. Recent Orders & Audit Logs
        $recentOrders = Order::with('delivery')
            ->latest('id')
            ->take(10)
            ->get();

        $recentLogs = AuditLog::with('user')
            ->latest('id')
            ->take(10)
            ->get();

        // 10. Pending Approvals across all 4 categories (Centralized Approval Center Counts)
        $pendingPayoutsCount = PayoutRequest::where('status', PayoutRequest::STATUS_PENDING)->count();
        $pendingPermissionRequestsCount = RolePermissionRequest::where('status', 'pending')->count();
        $pendingHierarchyRequestsCount = HierarchyChangeRequest::where('status', HierarchyChangeRequest::STATUS_PENDING)->count();
        $pendingRuleChangesCount = CommissionRuleChange::where('status', CommissionRuleChange::STATUS_PENDING)->count();
        $totalPendingApprovals = $pendingPayoutsCount + $pendingPermissionRequestsCount + $pendingHierarchyRequestsCount + $pendingRuleChangesCount;

        // 11. Real Sales Trend over last 30 days
        $startDate = Carbon::today()->subDays(29)->startOfDay();
        $rawSales = Order::select(
                DB::raw('DATE(placed_at) as order_date'),
                DB::raw('SUM(total_amount) as daily_sales'),
                DB::raw('COUNT(id) as daily_orders')
            )
            ->where('placed_at', '>=', $startDate)
            ->whereNotIn('status', ['cancelled', 'refused', 'returned'])
            ->groupBy('order_date')
            ->orderBy('order_date')
            ->get()
            ->keyBy('order_date');

        $salesTrend = [];
        for ($i = 29; $i >= 0; $i--) {
            $currentDate = Carbon::today()->subDays($i)->format('Y-m-d');
            $record = $rawSales->get($currentDate);
            $salesTrend[] = [
                'date' => $currentDate,
                'label' => Carbon::parse($currentDate)->format('M d'),
                'sales' => $record ? (float) $record->daily_sales : 0.0,
                'orders' => $record ? (int) $record->daily_orders : 0,
            ];
        }

        return Inertia::render('Admin/Dashboard', [
            // Backward-compatible original stats
            'stats' => [
                'total_orders' => $totalOrders,
                'placed_orders' => $placedOrders,
                'in_transit_orders' => $inTransitOrders,
                'delivered_orders' => $deliveredOrders,
                'completed_orders' => $completedOrders,
                'total_revenue' => $totalRevenue,
            ],
            // Enriched executive metrics
            'executiveMetrics' => [
                'total_sales' => $grossSales,
                'total_orders' => $totalOrders,
                'total_customers' => $totalCustomers,
                'active_agents_total' => $highLevelAgentsCount + $lowLevelAgentsCount,
                'high_level_agents_count' => $highLevelAgentsCount,
                'low_level_agents_count' => $lowLevelAgentsCount,
                'pending_agent_applications' => $pendingAgentsCount,
            ],
            // Segregated financial reconciliation overview
            'financialOverview' => [
                'gross_sales' => $grossSales,
                'collected_cod' => $reconciledCod,
                'pending_cod' => $codInTransit,
                'total_commission' => $netCommissionGenerated,
                'customer_delivery_revenue' => $customerDeliveryRevenue,
                'original_gross_commission' => $originalGrossCommissions,
                'reversed_commission' => $reversedCommissions,
                'net_commission_generated' => $netCommissionGenerated,
                'pending_commission' => $pendingCommissions,
                'matured_commission' => $maturedCommissions,
                'total_wallet_balance' => $totalWalletBalance,
                'reserved_wallet_balance' => $reservedWalletBalance,
                'available_wallet_balance' => $availableWalletBalance,
                'paid_payouts' => $paidPayouts,
                'approved_payouts' => $approvedPayouts,
            ],
            // Customer Insights
            'customerInsights' => [
                'total_customers' => $totalCustomers,
                'new_customers_month' => $newCustomersMonth,
                'repeat_customers' => $repeatCustomers,
                'repeat_purchase_rate' => $repeatPurchaseRate,
            ],
            // Business Risk Monitoring
            'businessRisks' => [
                'mismatched_cod_amount' => $codMismatched,
                'mismatched_cod_count' => $codMismatchedCount,
                'delivery_failures_count' => $refusedOrders + $returnedOrders,
                'out_of_stock_count' => $outOfStockCount,
            ],
            // Order distribution pipeline
            'orderPipeline' => [
                'placed' => $placedOrders,
                'confirmed' => $confirmedOrders,
                'packing' => $packingOrders,
                'shipped' => $inTransitOrders,
                'delivered' => $deliveredOrders,
                'cash_collected' => $cashCollectedOrders,
                'completed' => $completedOrders,
                'cancelled' => $cancelledOrders,
                'refused' => $refusedOrders,
                'returned' => $returnedOrders,
            ],
            // 30-day time-series data for interactive SVG chart
            'salesTrend' => $salesTrend,
            // Inventory health overview
            'inventoryOverview' => [
                'total_products' => $totalProducts,
                'active_products' => $activeProducts,
                'total_categories' => $totalCategories,
                'low_stock_count' => $lowStockCount,
                'out_of_stock_count' => $outOfStockCount,
            ],
            // Centralized Pending Action Counts
            'pendingActionCounts' => [
                'payouts' => $pendingPayoutsCount,
                'permission_requests' => $pendingPermissionRequestsCount,
                'hierarchy_requests' => $pendingHierarchyRequestsCount,
                'rule_changes' => $pendingRuleChangesCount,
                'low_stock' => $lowStockCount,
                'total_pending' => $totalPendingApprovals,
            ],
            'lowStockProducts' => $lowStockProducts,
            'recentOrders' => $recentOrders,
            'recentLogs' => $recentLogs,
        ]);
    }

    /**
     * Dedicated Agent Operations & Network Console for Agent Manager.
     */
    protected function renderAgentManagerDashboard(Request $request, User $actor): Response
    {
        // 1. Agent Network Totals & Lifecycle
        $totalAgents = User::whereIn('role', [UserRole::HIGH_LEVEL_AGENT, UserRole::LOW_LEVEL_AGENT])->count();
        $hlaCount = User::where('role', UserRole::HIGH_LEVEL_AGENT)->count();
        $llaCount = User::where('role', UserRole::LOW_LEVEL_AGENT)->count();
        $activeAgents = User::whereIn('role', [UserRole::HIGH_LEVEL_AGENT, UserRole::LOW_LEVEL_AGENT])
            ->where('status', UserStatus::ACTIVE)->count();
        $pendingAgents = User::whereIn('role', [UserRole::HIGH_LEVEL_AGENT, UserRole::LOW_LEVEL_AGENT])
            ->where('status', UserStatus::PENDING)->count();
        $suspendedAgents = User::whereIn('role', [UserRole::HIGH_LEVEL_AGENT, UserRole::LOW_LEVEL_AGENT])
            ->where('status', UserStatus::SUSPENDED)->count();

        // 2. KYC Verification Compliance
        $verifiedKycCount = AgentProfile::where('kyc_status', 'verified')->count();
        $pendingKycCount = AgentProfile::where('kyc_status', 'pending')->count();
        $rejectedKycCount = AgentProfile::where('kyc_status', 'rejected')->count();
        $kycComplianceRate = $totalAgents > 0 ? round(($verifiedKycCount / $totalAgents) * 100, 1) : 0;

        // 3. Operational Queue Counters
        $pendingHierarchyMovesCount = HierarchyChangeRequest::where('status', 'pending')->count();
        $activeInvitationsCount = DB::table('agent_invitations')
            ->where('status', 'pending')
            ->where('expires_at', '>', Carbon::now())
            ->count();

        // 4. 30-Day Registration Velocity Time Series
        $startDate = Carbon::today()->subDays(29);
        $registrations = User::whereIn('role', [UserRole::HIGH_LEVEL_AGENT, UserRole::LOW_LEVEL_AGENT])
            ->where('created_at', '>=', $startDate->startOfDay())
            ->select(
                DB::raw('DATE(created_at) as reg_date'),
                DB::raw("SUM(CASE WHEN role = 'high_level_agent' THEN 1 ELSE 0 END) as hla_signups"),
                DB::raw("SUM(CASE WHEN role = 'low_level_agent' THEN 1 ELSE 0 END) as lla_signups"),
                DB::raw('COUNT(*) as total_signups')
            )
            ->groupBy('reg_date')
            ->orderBy('reg_date', 'asc')
            ->get()
            ->keyBy('reg_date');

        $registrationTrend = [];
        for ($i = 29; $i >= 0; $i--) {
            $currentDate = Carbon::today()->subDays($i)->format('Y-m-d');
            $row = $registrations->get($currentDate);
            $registrationTrend[] = [
                'date' => $currentDate,
                'label' => Carbon::parse($currentDate)->format('M d'),
                'hla' => (int) ($row->hla_signups ?? 0),
                'lla' => (int) ($row->lla_signups ?? 0),
                'total' => (int) ($row->total_signups ?? 0),
            ];
        }

        // 5. Recent Agent Registrations (Latest 10)
        $recentRegistrations = User::whereIn('role', [UserRole::HIGH_LEVEL_AGENT, UserRole::LOW_LEVEL_AGENT])
            ->with([
                'parent:id,name,referral_code',
                'agentProfile:id,user_id,kyc_status,business_name'
            ])
            ->withCount('children')
            ->select('id', 'name', 'email', 'phone', 'role', 'status', 'referral_code', 'parent_id', 'created_at')
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get()
            ->map(fn ($agent) => [
                'id' => $agent->id,
                'name' => $agent->name,
                'email' => $agent->email,
                'phone' => $agent->phone,
                'role' => $agent->role instanceof UserRole ? $agent->role->value : $agent->role,
                'status' => $agent->status instanceof UserStatus ? $agent->status->value : $agent->status,
                'referral_code' => $agent->referral_code,
                'parent_name' => $agent->parent?->name ?? 'Company Direct',
                'parent_code' => $agent->parent?->referral_code,
                'children_count' => $agent->children_count ?? 0,
                'kyc_status' => $agent->agentProfile?->kyc_status ?? 'pending',
                'created_at' => $agent->created_at->toISOString(),
            ]);

        // 6. Action Queues (Pending Applications & KYC)
        $pendingApplicationsQueue = User::whereIn('role', [UserRole::HIGH_LEVEL_AGENT, UserRole::LOW_LEVEL_AGENT])
            ->where('status', UserStatus::PENDING)
            ->with(['parent:id,name', 'agentProfile:id,user_id,kyc_status'])
            ->select('id', 'name', 'email', 'phone', 'role', 'status', 'referral_code', 'parent_id', 'created_at')
            ->orderBy('created_at', 'asc')
            ->take(5)
            ->get();

        $pendingKycQueue = AgentProfile::where('kyc_status', 'pending')
            ->with('user:id,name,email,role,status,referral_code,created_at')
            ->whereHas('user', fn ($q) => $q->whereIn('role', [UserRole::HIGH_LEVEL_AGENT, UserRole::LOW_LEVEL_AGENT]))
            ->orderBy('created_at', 'asc')
            ->take(5)
            ->get()
            ->map(fn ($profile) => [
                'profile_id' => $profile->id,
                'agent_id' => $profile->user_id,
                'agent_name' => $profile->user?->name,
                'agent_role' => $profile->user?->role instanceof UserRole ? $profile->user->role->value : $profile->user?->role,
                'business_name' => $profile->business_name,
                'bank_name' => $profile->bank_name,
                'bank_account_name' => $profile->bank_account_name,
                'created_at' => $profile->created_at->toISOString(),
            ]);

        $pendingHierarchyQueue = HierarchyChangeRequest::where('status', 'pending')
            ->with([
                'agent:id,name,referral_code,role',
                'currentParent:id,name,referral_code',
                'requestedParent:id,name,referral_code',
                'requestedBy:id,name',
            ])
            ->orderBy('created_at', 'asc')
            ->take(5)
            ->get();

        // 7. Sanitized Network Top Performers (This Month, Sales Volume without wholesale/profit data)
        $startOfMonth = Carbon::now()->startOfMonth();
        $topPerformers = DB::table('orders')
            ->join('users', 'orders.agent_id', '=', 'users.id')
            ->whereNotNull('orders.agent_id')
            ->whereIn('orders.status', ['cash_collected', 'completed'])
            ->where('orders.placed_at', '>=', $startOfMonth)
            ->select(
                'users.id',
                'users.name',
                'users.role',
                'users.referral_code',
                DB::raw('COUNT(orders.id) as completed_orders_count'),
                DB::raw('SUM(orders.total_amount) as total_sales_volume')
            )
            ->groupBy('users.id', 'users.name', 'users.role', 'users.referral_code')
            ->orderBy('total_sales_volume', 'desc')
            ->take(5)
            ->get();

        return Inertia::render('Admin/AgentManager/Dashboard', [
            'metrics' => [
                'total_agents' => $totalAgents,
                'hla_count' => $hlaCount,
                'lla_count' => $llaCount,
                'active_agents' => $activeAgents,
                'pending_agents' => $pendingAgents,
                'suspended_agents' => $suspendedAgents,
                'verified_kyc' => $verifiedKycCount,
                'pending_kyc' => $pendingKycCount,
                'rejected_kyc' => $rejectedKycCount,
                'kyc_compliance_rate' => $kycComplianceRate,
                'active_invitations' => $activeInvitationsCount,
                'pending_hierarchy_moves' => $pendingHierarchyMovesCount,
            ],
            'registrationTrend' => $registrationTrend,
            'recentRegistrations' => $recentRegistrations,
            'pendingQueues' => [
                'applications' => $pendingApplicationsQueue,
                'kyc' => $pendingKycQueue,
                'hierarchyMoves' => $pendingHierarchyQueue,
            ],
            'topPerformers' => $topPerformers,
            'pendingActionCounts' => [
                'pending_applications' => $pendingAgents,
                'pending_kyc' => $pendingKycCount,
                'pending_hierarchy' => $pendingHierarchyMovesCount,
                'total_pending' => $pendingAgents + $pendingKycCount + $pendingHierarchyMovesCount,
            ],
            'canManageKyc' => true,
            'canManageApplications' => true,
            'canCreateInvitations' => true,
            'canRequestHierarchyMove' => true,
            'isOwner' => $actor->isOwner(),
        ]);
    }
}
