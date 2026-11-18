<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class BackInStockSubscription extends Model
{
    use HasFactory;

    public const STATUS_PENDING_VERIFICATION = 'pending_verification';
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_NOTIFIED = 'notified';
    public const STATUS_FAILED = 'failed';
    public const STATUS_UNSUBSCRIBED = 'unsubscribed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_MANUAL_REVIEW = 'manual_review';

    protected $table = 'back_in_stock_subscriptions';

    protected $fillable = [
        'product_id',
        'customer_id',
        'email',
        'normalized_email',
        'locale',
        'status',
        'is_active',
        'unsubscribe_token_hash',
        'verification_token_hash',
        'verification_expires_at',
        'verified_at',
        'notified_at',
    ];

    protected $casts = [
        'product_id' => 'integer',
        'customer_id' => 'integer',
        'is_active' => 'boolean',
        'verification_expires_at' => 'datetime',
        'verified_at' => 'datetime',
        'notified_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function claims(): HasMany
    {
        return $this->hasMany(RestockEventClaim::class, 'subscription_id');
    }

    public function activeLease(): HasOne
    {
        return $this->hasOne(SubscriptionActiveLease::class, 'subscription_id');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(BackInStockDelivery::class, 'subscription_id');
    }
}
