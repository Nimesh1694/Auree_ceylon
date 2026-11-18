<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class RestockEventClaim extends Model
{
    use HasFactory;

    public const STATUS_CLAIMED = 'claimed';
    public const STATUS_DISPATCHED = 'dispatched';
    public const STATUS_RELEASED = 'released';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_FAILED = 'failed';

    public $timestamps = false;

    protected $table = 'restock_event_claims';

    protected $fillable = [
        'restock_event_id',
        'subscription_id',
        'claim_order',
        'status',
        'attempt_number',
        'release_reason',
        'lease_owner',
        'lease_token',
        'lease_acquired_at',
        'lease_expires_at',
        'heartbeat_at',
        'lease_version',
        'created_at',
    ];

    protected $casts = [
        'subscription_id' => 'integer',
        'claim_order' => 'integer',
        'attempt_number' => 'integer',
        'lease_acquired_at' => 'datetime',
        'lease_expires_at' => 'datetime',
        'heartbeat_at' => 'datetime',
        'lease_version' => 'integer',
        'created_at' => 'datetime',
    ];

    public function restockEvent(): BelongsTo
    {
        return $this->belongsTo(RestockEvent::class, 'restock_event_id');
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(BackInStockSubscription::class, 'subscription_id');
    }

    public function activeLease(): HasOne
    {
        return $this->hasOne(SubscriptionActiveLease::class, 'claim_id');
    }
}
