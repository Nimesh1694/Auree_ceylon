<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SystemHealthController extends Controller
{
    /**
     * Display technical system diagnostics and health overview.
     */
    public function index(Request $request): Response
    {
        $actor = $request->user();

        if (!$actor->isOwner() && !$actor->hasPermissionTo('system.health.view')) {
            abort(403, 'Unauthorized to view system health diagnostics.');
        }

        $activeSessionsCount = DB::table('sessions')->count();
        $totalUsers = User::count();
        $totalLogs = AuditLog::count();

        // System runtime diagnostics
        $diagnostics = [
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'environment' => app()->environment(),
            'database_connection' => config('database.default'),
            'database_engine' => DB::connection()->getDriverName(),
            'cache_driver' => config('cache.default'),
            'queue_driver' => config('queue.default'),
            'session_driver' => config('session.driver'),
            'active_sessions' => $activeSessionsCount,
            'total_users' => $totalUsers,
            'total_audit_events' => $totalLogs,
            'is_down_for_maintenance' => app()->isDownForMaintenance(),
            'server_time' => now()->toIso8601String(),
        ];

        // Recent audit events
        $recentLogs = AuditLog::with('user:id,name,role')
            ->latest('id')
            ->take(10)
            ->get();

        return Inertia::render('Admin/System/Index', [
            'diagnostics' => $diagnostics,
            'recentLogs' => $recentLogs,
            'canManageMaintenance' => $actor->isOwner() || $actor->hasPermissionTo('system.maintenance.manage'),
        ]);
    }

    /**
     * Display a full paginated listing of system audit logs.
     */
    public function logs(Request $request): Response
    {
        $actor = $request->user();

        $canViewLogs = $actor->isOwner()
            || $actor->isSuperAdmin()
            || $actor->hasPermissionTo('system.logs.view')
            || $actor->hasPermissionTo('system.health.view');

        if (!$canViewLogs) {
            abort(403, 'Unauthorized to view system audit logs.');
        }

        $query = AuditLog::with('user:id,name,role,email')->latest('id');

        if ($request->filled('action')) {
            $query->where('action', $request->input('action'));
        }

        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('action', 'like', "%{$search}%")
                    ->orWhere('ip_address', 'like', "%{$search}%")
                    ->orWhere('auditable_type', 'like', "%{$search}%")
                    ->orWhere('auditable_id', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($uq) => $uq->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        $distinctActions = AuditLog::select('action')->distinct()->orderBy('action')->pluck('action')->all();

        $logs = $query->paginate(25)->withQueryString();

        return Inertia::render('Admin/System/Logs', [
            'logs' => $logs,
            'filters' => $request->only(['action', 'search', 'date_from', 'date_to']),
            'availableActions' => $distinctActions,
        ]);
    }

    /**
     * Toggle maintenance mode on or off.
     */
    public function toggleMaintenance(Request $request): RedirectResponse
    {
        $actor = $request->user();

        if (!$actor->isOwner() && !$actor->hasPermissionTo('system.maintenance.manage')) {
            abort(403, 'Unauthorized to alter application maintenance state.');
        }

        $isCurrentlyDown = app()->isDownForMaintenance();

        if ($isCurrentlyDown) {
            Artisan::call('up');
            AuditLog::log('maintenance_mode_disabled', null, null, ['toggled_by' => $actor->name]);
            return redirect()->back()->with('success', 'Application maintenance mode has been disabled. Storefront is live.');
        } else {
            $secret = $request->input('secret', 'aurabypass2026');
            Artisan::call('down', [
                '--secret' => $secret,
            ]);
            AuditLog::log('maintenance_mode_enabled', null, null, ['toggled_by' => $actor->name, 'bypass_configured' => true]);
            return redirect()->back()->with('success', "Application is now in maintenance mode. Bypass key configured.");
        }
    }

    /**
     * Clear application cache, routes, config, and compiled views (Shared-hosting friendly).
     */
    public function clearCache(Request $request): RedirectResponse
    {
        $actor = $request->user();

        if (!$actor->isOwner() && !$actor->isTechnicalAuthority() && !$actor->hasPermissionTo('system.maintenance.manage')) {
            abort(403, 'Unauthorized to clear application cache.');
        }

        Artisan::call('optimize:clear');
        AuditLog::log('application_cache_cleared', null, null, [
            'cleared_by' => $actor->name,
            'ip' => $request->ip(),
        ]);

        return redirect()->back()->with(
            'success',
            'Application cache, config, route, and compiled view caches cleared successfully.'
        );
    }
}

