<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_id',
        'product_name',
        'product_sku',
        'unit_price',
        'cost_price',
        'quantity',
        'subtotal',
    ];

    /**
     * The attributes that should be hidden for serialization to prevent leaking
     * internal product cost prices to customer or public storefront payloads.
     */
    protected $hidden = [
        'cost_price',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'quantity' => 'integer',
        ];
    }

    /**
     * Get the effective cost price.
     * Uses audited historical snapshot if present; falls back to current catalog cost for legacy orders.
     */
    public function getEffectiveCostPrice(): float
    {
        if ($this->cost_price !== null) {
            return (float) $this->cost_price;
        }

        return (float) ($this->product?->cost_price ?? 0.0);
    }

    /**
     * Indicates whether this order item has an audited, immutable historical cost snapshot.
     */
    public function isCostAudited(): bool
    {
        return $this->cost_price !== null;
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}

