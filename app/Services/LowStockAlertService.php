<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\AuditLog;
use App\Models\Product;
use App\Models\StockAlert;
use App\Models\User;
use App\Notifications\LowStockAlertOpenedNotification;
use App\Notifications\LowStockDailyDigestNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class LowStockAlertService
{
    /**
     * Evaluate a product's current stock against its low_stock_threshold and
     * synchronize its active low-stock alert atomically.
     */
    public function syncForProduct(Product $product, string $triggerSource = 'stock_adjustment'): ?StockAlert
    {
        return DB::transaction(function () use ($product, $triggerSource) {
            /** @var Product $lockedProduct */
            $lockedProduct = Product::where('id', $product->id)->lockForUpdate()->firstOrFail();

            /** @var StockAlert|null $activeAlert */
            $activeAlert = StockAlert::where('product_id', $lockedProduct->id)
                ->whereIn('status', [StockAlert::STATUS_OPEN, StockAlert::STATUS_ACKNOWLEDGED])
                ->lockForUpdate()
                ->first();

            $isLowStock = $lockedProduct->is_active
                && $lockedProduct->stock_quantity <= $lockedProduct->low_stock_threshold;

            if ($isLowStock) {
                if ($activeAlert) {
                    if ($activeAlert->current_stock !== $lockedProduct->stock_quantity) {
                        $activeAlert->current_stock = $lockedProduct->stock_quantity;
                        $activeAlert->save();
                    }

                    return $activeAlert;
                }

                $alert = StockAlert::create([
                    'product_id' => $lockedProduct->id,
                    'active_product_id' => $lockedProduct->id,
                    'status' => StockAlert::STATUS_OPEN,
                    'stock_at_trigger' => $lockedProduct->stock_quantity,
                    'threshold_at_trigger' => $lockedProduct->low_stock_threshold,
                    'current_stock' => $lockedProduct->stock_quantity,
                    'trigger_source' => $triggerSource,
                    'opened_at' => now(),
                ]);

                AuditLog::log('stock_alert_opened', $alert, null, [
                    'product_id' => $lockedProduct->id,
                    'sku' => $lockedProduct->sku,
                    'stock_at_trigger' => $alert->stock_at_trigger,
                    'threshold_at_trigger' => $alert->threshold_at_trigger,
                    'trigger_source' => $triggerSource,
                ]);

                $alertId = $alert->id;
                DB::afterCommit(function () use ($alertId) {
                    $this->dispatchOpenedNotification($alertId);
                });

                return $alert;
            }

            if ($activeAlert) {
                $oldStatus = $activeAlert->status;
                $activeAlert->update([
                    'status' => StockAlert::STATUS_RESOLVED,
                    'active_product_id' => null,
                    'current_stock' => $lockedProduct->stock_quantity,
                    'resolved_at' => now(),
                    'resolution_type' => 'auto_restock',
                ]);

                AuditLog::log('stock_alert_resolved', $activeAlert, [
                    'status' => $oldStatus,
                ], [
                    'status' => StockAlert::STATUS_RESOLVED,
                    'product_id' => $lockedProduct->id,
                    'current_stock' => $lockedProduct->stock_quantity,
                    'trigger_source' => $triggerSource,
                ]);

                return $activeAlert->fresh();
            }

            return null;
        });
    }

    /**
     * Acknowledge an open low-stock alert.
     */
    public function acknowledge(StockAlert $alert, ?string $note, User $actor): StockAlert
    {
        return DB::transaction(function () use ($alert, $note, $actor) {
            /** @var StockAlert $lockedAlert */
            $lockedAlert = StockAlert::where('id', $alert->id)->lockForUpdate()->firstOrFail();

            if ($lockedAlert->status !== StockAlert::STATUS_OPEN) {
                throw ValidationException::withMessages([
                    'status' => 'Only open low-stock alerts can be acknowledged.',
                ]);
            }

            $cleanNote = $note !== null && trim($note) !== '' ? trim($note) : null;

            $lockedAlert->update([
                'status' => StockAlert::STATUS_ACKNOWLEDGED,
                'acknowledged_by' => $actor->id,
                'acknowledged_at' => now(),
                'acknowledgement_note' => $cleanNote,
            ]);

            AuditLog::log('stock_alert_acknowledged', $lockedAlert, [
                'status' => StockAlert::STATUS_OPEN,
            ], [
                'status' => StockAlert::STATUS_ACKNOWLEDGED,
                'acknowledged_by' => $actor->id,
                'acknowledgement_note' => $cleanNote,
            ]);

            return $lockedAlert->fresh(['product', 'acknowledger']);
        });
    }

    /**
     * Dispatch immediate notification for a newly opened alert without throwing on transport errors.
     */
    public function dispatchOpenedNotification(int $alertId): void
    {
        try {
            $alert = StockAlert::with('product:id,name_en,sku,stock_quantity,low_stock_threshold')->find($alertId);
            if (!$alert || $alert->status !== StockAlert::STATUS_OPEN || $alert->last_notified_at !== null) {
                return;
            }

            $recipients = $this->getAlertRecipients();
            if ($recipients->isEmpty()) {
                return;
            }

            Notification::send($recipients, new LowStockAlertOpenedNotification($alert));
            $alert->update(['last_notified_at' => now()]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Send daily digest for all currently open (unacknowledged) low-stock alerts.
     */
    public function sendDailyDigest(): int
    {
        $openAlerts = StockAlert::with('product:id,name_en,sku,stock_quantity,low_stock_threshold')
            ->where('status', StockAlert::STATUS_OPEN)
            ->orderBy('current_stock')
            ->get();

        if ($openAlerts->isEmpty()) {
            return 0;
        }

        $recipients = $this->getAlertRecipients();
        if ($recipients->isEmpty()) {
            return 0;
        }

        try {
            Notification::send($recipients, new LowStockDailyDigestNotification($openAlerts));
            StockAlert::whereIn('id', $openAlerts->pluck('id'))->update(['last_notified_at' => now()]);
        } catch (\Throwable $e) {
            report($e);
            return 0;
        }

        return $openAlerts->count();
    }

    /**
     * Resolve active management users authorized to view low-stock alerts.
     *
     * @return Collection<int, User>
     */
    public function getAlertRecipients(): Collection
    {
        return User::where('status', UserStatus::ACTIVE->value)
            ->whereNotIn('role', [UserRole::CUSTOMER->value, UserRole::HIGH_LEVEL_AGENT->value, UserRole::LOW_LEVEL_AGENT->value])
            ->get()
            ->filter(fn (User $user) => $user->isOwner() || $user->hasPermissionTo('inventory.alerts.view'))
            ->values();
    }
}

