<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BackInStockDelivery extends Model
{
    use HasFactory;

    public const STATUS_INITIATED = 'initiated';
    public const STATUS_PROVIDER_ACCEPTED = 'provider_accepted';
    public const STATUS_PROVIDER_REJECTED = 'provider_rejected';
    public const STATUS_FAILED = 'failed';
    public const STATUS_MANUAL_REVIEW = 'manual_review';

    protected $table = 'back_in_stock_deliveries';

    protected $fillable = [
        'subscription_id',
        'product_id',
        'restock_event_id',
        'logical_delivery_key',
        'status',
    ];

    protected $casts = [
        'subscription_id' => 'integer',
        'product_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(BackInStockSubscription::class, 'subscription_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function restockEvent(): BelongsTo
    {
        return $this->belongsTo(RestockEvent::class, 'restock_event_id');
    }

    public function notificationLogs(): HasMany
    {
        return $this->hasMany(BackInStockNotificationLog::class, 'delivery_id');
    }
}
