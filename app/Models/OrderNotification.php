<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderNotification extends Model
{
    use HasFactory;

    // Notification Types
    public const TYPE_ORDER_PLACED = 'order_placed';
    public const TYPE_ORDER_CONFIRMED = 'order_confirmed';
    public const TYPE_ORDER_SHIPPED = 'order_shipped';
    public const TYPE_ORDER_DELIVERED = 'order_delivered';
    public const TYPE_ORDER_COMPLETED = 'order_completed';
    public const TYPE_ORDER_CANCELLED = 'order_cancelled';
    public const TYPE_ORDER_RETURNED = 'order_returned';
    public const TYPE_ORDER_REFUSED = 'order_refused';

    // Status Constants
    public const STATUS_PENDING = 'pending';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';
    public const STATUS_SKIPPED = 'skipped';

    // Recipient Types
    public const RECIPIENT_CUSTOMER = 'customer';
    public const RECIPIENT_AGENT = 'agent';
    public const RECIPIENT_ADMIN = 'admin';

    protected $fillable = [
        'order_id',
        'order_number',
        'notification_type',
        'recipient_type',
        'recipient_email',
        'mailer',
        'status',
        'attempts',
        'error_details',
        'provider_message_id',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }

    /**
     * Associated order (nullable if historical order was deleted).
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function isSent(): bool
    {
        return $this->status === self::STATUS_SENT;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function isSkipped(): bool
    {
        return $this->status === self::STATUS_SKIPPED;
    }

    public function scopeSent($query)
    {
        return $query->where('status', self::STATUS_SENT);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }
}

