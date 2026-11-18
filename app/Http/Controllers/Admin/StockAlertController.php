<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StockAlert;
use App\Models\User;
use App\Services\LowStockAlertService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StockAlertController extends Controller
{
    public function __construct(
        private readonly LowStockAlertService $alertService
    ) {}

    private function authorizePermission(?User $user, string $permission): void
    {
        if (!$user || (!$user->isOwner() && !$user->hasPermissionTo($permission))) {
            abort(403, 'Unauthorized access to low-stock alerts.');
        }
    }

    public function index(Request $request): Response
    {
        $user = $request->user();
        $this->authorizePermission($user, 'inventory.alerts.view');

        $search = trim((string) $request->input('search', ''));
        $status = (string) $request->input('status', 'open');

        $query = StockAlert::query()
            ->with(['product:id,name_en,sku,stock_quantity,low_stock_threshold', 'acknowledger:id,name']);

        if ($search !== '') {
            $query->whereHas('product', function ($q) use ($search) {
                $q->where('name_en', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        if (in_array($status, [
            StockAlert::STATUS_OPEN,
            StockAlert::STATUS_ACKNOWLEDGED,
            StockAlert::STATUS_RESOLVED,
        ], true)) {
            $query->where('status', $status);
        }

        $alerts = $query->latest('opened_at')->paginate(15)->withQueryString()->through(function (StockAlert $alert) {
            return [
                'id' => $alert->id,
                'product_id' => $alert->product_id,
                'product_name' => $alert->product?->name_en,
                'product_sku' => $alert->product?->sku,
                'live_stock' => $alert->product?->stock_quantity,
                'live_threshold' => $alert->product?->low_stock_threshold,
                'status' => $alert->status,
                'stock_at_trigger' => $alert->stock_at_trigger,
                'threshold_at_trigger' => $alert->threshold_at_trigger,
                'current_stock' => $alert->current_stock,
                'trigger_source' => $alert->trigger_source,
                'opened_at' => $alert->opened_at?->toISOString(),
                'last_notified_at' => $alert->last_notified_at?->toISOString(),
                'acknowledged_by_name' => $alert->acknowledger?->name,
                'acknowledged_at' => $alert->acknowledged_at?->toISOString(),
                'acknowledgement_note' => $alert->acknowledgement_note,
                'resolved_at' => $alert->resolved_at?->toISOString(),
                'resolution_type' => $alert->resolution_type,
            ];
        });

        return Inertia::render('Admin/Inventory/Alerts', [
            'alerts' => $alerts,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
            'stats' => [
                'open' => StockAlert::where('status', StockAlert::STATUS_OPEN)->count(),
                'acknowledged' => StockAlert::where('status', StockAlert::STATUS_ACKNOWLEDGED)->count(),
                'resolved' => StockAlert::where('status', StockAlert::STATUS_RESOLVED)->count(),
            ],
            'canAcknowledge' => $user->isOwner() || $user->hasPermissionTo('inventory.alerts.acknowledge'),
        ]);
    }

    public function acknowledge(Request $request, StockAlert $stockAlert): RedirectResponse
    {
        $user = $request->user();
        $this->authorizePermission($user, 'inventory.alerts.acknowledge');

        $validated = $request->validate([
            'acknowledgement_note' => ['nullable', 'string', 'max:500'],
        ]);

        $this->alertService->acknowledge(
            $stockAlert,
            $validated['acknowledgement_note'] ?? null,
            $user
        );

        return back()->with('success', 'Low-stock alert acknowledged.');
    }
}

