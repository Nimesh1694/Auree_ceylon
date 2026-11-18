<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RestockEvent extends Model
{
    use HasFactory, HasUuids;

    public const STATUS_PENDING_CLAIMS = 'pending_claims';
    public const STATUS_CLAIMED = 'claimed';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_EXHAUSTED = 'exhausted';
    public const STATUS_CANCELLED = 'cancelled';

    protected $table = 'restock_events';

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'product_id',
        'previous_stock',
        'new_stock',
        'restocked_quantity',
        'batch_limit',
        'claimed_count',
        'status',
    ];

    protected $casts = [
        'product_id' => 'integer',
        'previous_stock' => 'integer',
        'new_stock' => 'integer',
        'restocked_quantity' => 'integer',
        'batch_limit' => 'integer',
        'claimed_count' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function claims(): HasMany
    {
        return $this->hasMany(RestockEventClaim::class, 'restock_event_id');
    }

    public function activeLeases(): HasMany
    {
        return $this->hasMany(SubscriptionActiveLease::class, 'restock_event_id');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(BackInStockDelivery::class, 'restock_event_id');
    }
}
