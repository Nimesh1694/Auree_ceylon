<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class ProductSupplier extends Pivot
{
    use HasFactory;

    public $incrementing = true;

    protected $table = 'product_supplier';

    protected $fillable = [
        'product_id',
        'supplier_id',
        'supplier_sku',
        'unit_cost',
        'lead_time_days',
        'min_order_qty',
        'is_preferred',
        'preferred_product_id',
        'last_purchased_at',
    ];

    protected function casts(): array
    {
        return [
            'unit_cost' => 'decimal:2',
            'lead_time_days' => 'integer',
            'min_order_qty' => 'integer',
            'is_preferred' => 'boolean',
            'last_purchased_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Enforce at most one preferred supplier per product at both model and DB-unique-column layers.
        static::saving(function (ProductSupplier $pivot): void {
            if ($pivot->is_preferred && $pivot->product_id) {
                static::query()
                    ->where('product_id', $pivot->product_id)
                    ->when($pivot->exists, fn ($query) => $query->where('id', '!=', $pivot->id))
                    ->where(function ($query) {
                        $query->where('is_preferred', true)
                            ->orWhereNotNull('preferred_product_id');
                    })
                    ->update([
                        'is_preferred' => false,
                        'preferred_product_id' => null,
                    ]);

                $pivot->preferred_product_id = $pivot->product_id;
            } else {
                $pivot->preferred_product_id = null;
            }
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}

