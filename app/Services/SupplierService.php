<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Product;
use App\Models\ProductSupplier;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SupplierService
{
    /**
     * Generate a unique supplier code in SUP-YYYY-NNNN format.
     */
    public function generateUniqueCode(): string
    {
        $year = now()->format('Y');
        $prefix = "SUP-{$year}-";

        $latestCode = Supplier::where('code', 'like', "{$prefix}%")
            ->orderByDesc('code')
            ->value('code');

        $nextNumber = 1;
        if ($latestCode && preg_match('/(\d+)$/', $latestCode, $matches)) {
            $nextNumber = ((int) $matches[1]) + 1;
        }

        do {
            $candidate = $prefix . str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
            $exists = Supplier::where('code', $candidate)->exists();
            $nextNumber++;
        } while ($exists);

        return $candidate;
    }

    /**
     * Register a new supplier.
     */
    public function createSupplier(array $data, User $actor): Supplier
    {
        return DB::transaction(function () use ($data, $actor) {
            $code = !empty($data['code'])
                ? strtoupper(trim((string) $data['code']))
                : $this->generateUniqueCode();

            $supplier = Supplier::create([
                'code' => $code,
                'name' => trim((string) $data['name']),
                'contact_person' => $data['contact_person'] ?? null,
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'address' => $data['address'] ?? null,
                'city' => $data['city'] ?? null,
                'country' => $data['country'] ?? 'Sri Lanka',
                'tax_id' => $data['tax_id'] ?? null,
                'payment_terms' => $data['payment_terms'] ?? Supplier::TERMS_NET_30,
                'default_lead_time_days' => (int) ($data['default_lead_time_days'] ?? 7),
                'status' => $data['status'] ?? Supplier::STATUS_ACTIVE,
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor->id,
            ]);

            AuditLog::log('supplier_created', $supplier, null, [
                'code' => $supplier->code,
                'name' => $supplier->name,
                'status' => $supplier->status,
            ]);

            return $supplier;
        });
    }

    /**
     * Update an existing supplier's profile.
     */
    public function updateSupplier(Supplier $supplier, array $data, User $actor): Supplier
    {
        return DB::transaction(function () use ($supplier, $data) {
            $oldValues = $supplier->only([
                'code', 'name', 'contact_person', 'email', 'phone',
                'address', 'city', 'country', 'tax_id', 'payment_terms',
                'default_lead_time_days', 'status', 'notes',
            ]);

            $payload = [
                'name' => trim((string) $data['name']),
                'contact_person' => $data['contact_person'] ?? null,
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'address' => $data['address'] ?? null,
                'city' => $data['city'] ?? null,
                'country' => $data['country'] ?? 'Sri Lanka',
                'tax_id' => $data['tax_id'] ?? null,
                'payment_terms' => $data['payment_terms'] ?? $supplier->payment_terms,
                'default_lead_time_days' => (int) ($data['default_lead_time_days'] ?? $supplier->default_lead_time_days),
                'status' => $data['status'] ?? $supplier->status,
                'notes' => $data['notes'] ?? null,
            ];

            if (!empty($data['code'])) {
                $payload['code'] = strtoupper(trim((string) $data['code']));
            }

            $supplier->update($payload);

            AuditLog::log('supplier_updated', $supplier, $oldValues, $supplier->only(array_keys($oldValues)));

            return $supplier->fresh();
        });
    }

    /**
     * Toggle supplier active/inactive status.
     */
    public function toggleStatus(Supplier $supplier, User $actor): Supplier
    {
        return DB::transaction(function () use ($supplier) {
            $oldStatus = $supplier->status;
            $newStatus = $oldStatus === Supplier::STATUS_ACTIVE
                ? Supplier::STATUS_INACTIVE
                : Supplier::STATUS_ACTIVE;

            $supplier->update(['status' => $newStatus]);

            AuditLog::log('supplier_status_changed', $supplier, ['status' => $oldStatus], ['status' => $newStatus]);

            return $supplier->fresh();
        });
    }

    /**
     * Link an active product to a supplier with Product row-locking for preferred-supplier safety.
     */
    public function linkProduct(Supplier $supplier, Product $product, array $data, User $actor): ProductSupplier
    {
        if (!$product->is_active) {
            throw ValidationException::withMessages([
                'product_id' => 'Only active products can be linked to a supplier.',
            ]);
        }

        return DB::transaction(function () use ($supplier, $product, $data) {
            // Serialize preferred-supplier mutations per product (OBS-01).
            Product::where('id', $product->id)->lockForUpdate()->firstOrFail();

            $existing = ProductSupplier::where('product_id', $product->id)
                ->where('supplier_id', $supplier->id)
                ->first();

            if ($existing) {
                throw ValidationException::withMessages([
                    'product_id' => 'This product is already linked to the selected supplier.',
                ]);
            }

            $link = ProductSupplier::create([
                'product_id' => $product->id,
                'supplier_id' => $supplier->id,
                'supplier_sku' => $data['supplier_sku'] ?? null,
                'unit_cost' => $data['unit_cost'],
                'lead_time_days' => $data['lead_time_days'] ?? null,
                'min_order_qty' => (int) ($data['min_order_qty'] ?? 1),
                'is_preferred' => (bool) ($data['is_preferred'] ?? false),
            ]);

            AuditLog::log('supplier_product_linked', $link, null, [
                'supplier_id' => $supplier->id,
                'product_id' => $product->id,
                'unit_cost' => $link->unit_cost,
                'is_preferred' => $link->is_preferred,
            ]);

            return $link;
        });
    }

    /**
     * Update an existing supplier-product link with Product row-locking.
     */
    public function updateProductLink(Supplier $supplier, Product $product, array $data, User $actor): ProductSupplier
    {
        return DB::transaction(function () use ($supplier, $product, $data) {
            Product::where('id', $product->id)->lockForUpdate()->firstOrFail();

            $link = ProductSupplier::where('product_id', $product->id)
                ->where('supplier_id', $supplier->id)
                ->lockForUpdate()
                ->firstOrFail();

            $oldValues = $link->only(['supplier_sku', 'unit_cost', 'lead_time_days', 'min_order_qty', 'is_preferred']);

            $link->update([
                'supplier_sku' => $data['supplier_sku'] ?? null,
                'unit_cost' => $data['unit_cost'],
                'lead_time_days' => $data['lead_time_days'] ?? null,
                'min_order_qty' => (int) ($data['min_order_qty'] ?? 1),
                'is_preferred' => (bool) ($data['is_preferred'] ?? $link->is_preferred),
            ]);

            AuditLog::log('supplier_product_updated', $link, $oldValues, $link->only(array_keys($oldValues)));

            return $link->fresh();
        });
    }

    /**
     * Mark a supplier as the sole preferred supplier for a product atomically.
     */
    public function setPreferredSupplier(Supplier $supplier, Product $product, User $actor): ProductSupplier
    {
        return DB::transaction(function () use ($supplier, $product) {
            Product::where('id', $product->id)->lockForUpdate()->firstOrFail();

            $link = ProductSupplier::where('product_id', $product->id)
                ->where('supplier_id', $supplier->id)
                ->lockForUpdate()
                ->firstOrFail();

            $previousPreferred = ProductSupplier::where('product_id', $product->id)
                ->where('is_preferred', true)
                ->first();

            $link->is_preferred = true;
            $link->save();

            AuditLog::log('supplier_preferred_changed', $link, [
                'previous_preferred_supplier_id' => $previousPreferred?->supplier_id,
            ], [
                'new_preferred_supplier_id' => $supplier->id,
                'product_id' => $product->id,
            ]);

            return $link->fresh();
        });
    }

    /**
     * Remove a supplier-product relationship safely without affecting historical POs.
     */
    public function unlinkProduct(Supplier $supplier, Product $product, User $actor): void
    {
        DB::transaction(function () use ($supplier, $product) {
            Product::where('id', $product->id)->lockForUpdate()->firstOrFail();

            $link = ProductSupplier::where('product_id', $product->id)
                ->where('supplier_id', $supplier->id)
                ->lockForUpdate()
                ->firstOrFail();

            $snapshot = [
                'supplier_id' => $supplier->id,
                'product_id' => $product->id,
                'supplier_sku' => $link->supplier_sku,
                'is_preferred' => $link->is_preferred,
            ];

            $link->delete();

            AuditLog::log('supplier_product_unlinked', $supplier, $snapshot, null);
        });
    }
}

