<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionActiveLease extends Model
{
    use HasFactory;

    protected $table = 'subscription_active_leases';

    protected $primaryKey = 'subscription_id';
    public $incrementing = false;
    protected $keyType = 'integer';

    protected $fillable = [
        'subscription_id',
        'restock_event_id',
        'claim_id',
        'lease_owner',
        'lease_token',
        'lease_acquired_at',
        'lease_expires_at',
        'heartbeat_at',
        'lease_version',
        'yield_count',
    ];

    protected $casts = [
        'subscription_id' => 'integer',
        'claim_id' => 'integer',
        'lease_acquired_at' => 'datetime',
        'lease_expires_at' => 'datetime',
        'heartbeat_at' => 'datetime',
        'lease_version' => 'integer',
        'yield_count' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(BackInStockSubscription::class, 'subscription_id');
    }

    public function restockEvent(): BelongsTo
    {
        return $this->belongsTo(RestockEvent::class, 'restock_event_id');
    }

    public function claim(): BelongsTo
    {
        return $this->belongsTo(RestockEventClaim::class, 'claim_id');
    }
}
