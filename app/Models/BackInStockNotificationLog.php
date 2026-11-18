<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BackInStockNotificationLog extends Model
{
    use HasFactory;

    public const STATUS_INITIATED = 'initiated';
    public const STATUS_PROVIDER_ACCEPTED = 'provider_accepted';
    public const STATUS_PROVIDER_REJECTED = 'provider_rejected';
    public const STATUS_FAILED = 'failed';

    protected $table = 'back_in_stock_notification_logs';

    protected $fillable = [
        'delivery_id',
        'attempt_number',
        'idempotency_key',
        'status',
        'provider_message_id',
        'error_message',
        'dispatched_at',
    ];

    protected $casts = [
        'delivery_id' => 'integer',
        'attempt_number' => 'integer',
        'dispatched_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(BackInStockDelivery::class, 'delivery_id');
    }
}
