<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryMovement extends Model
{
    use HasFactory;

    public const TYPE_OPENING_STOCK = 'opening_stock';
    public const TYPE_RESTOCK = 'restock';
    public const TYPE_ADJUSTMENT = 'adjustment';
    public const TYPE_ORDER_DEDUCTION = 'order_deduction';
    public const TYPE_CANCELLATION_RESTOCK = 'cancellation_restock';
    public const TYPE_CUSTOMER_RETURN = 'customer_return';
    public const TYPE_DAMAGE_LOSS = 'damage_loss';
    public const TYPE_EXPIRED = 'expired';

    protected $fillable = [
        'product_id',
        'movement_type',
        'quantity',
        'previous_stock',
        'new_stock',
        'reason',
        'order_id',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'previous_stock' => 'integer',
            'new_stock' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

