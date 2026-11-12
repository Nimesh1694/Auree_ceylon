<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class CommissionLedgerEntry extends Model
{
    use HasFactory;

    public const TYPE_DIRECT_HIGH_LEVEL = 'direct_high_level';
    public const TYPE_DIRECT_LOW_LEVEL = 'direct_low_level';
    public const TYPE_UPLINE_OVERRIDE = 'upline_override';
    public const TYPE_REVERSAL = 'reversal';

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REVERSED = 'reversed';

    protected $fillable = [
        'entry_number',
        'order_id',
        'order_item_id',
        'agent_id',
        'selling_agent_id',
        'commission_type',
        'calculation_base',
        'base_amount',
        'rate_percentage',
        'calculated_amount',
        'currency',
        'status',
        'rate_snapshot',
        'original_entry_id',
        'reversal_reason',
        'approved_at',
        'reversed_at',
        'notes',
    ];

    protected $casts = [
        'base_amount' => 'decimal:2',
        'rate_percentage' => 'decimal:2',
        'calculated_amount' => 'decimal:2',
        'rate_snapshot' => 'array',
        'approved_at' => 'datetime',
        'reversed_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function sellingAgent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'selling_agent_id');
    }

    public function originalEntry(): BelongsTo
    {
        return $this->belongsTo(self::class, 'original_entry_id');
    }

    public function reversals(): HasMany
    {
        return $this->hasMany(self::class, 'original_entry_id');
    }

    public function reversal(): HasOne
    {
        return $this->hasOne(self::class, 'original_entry_id');
    }

    /**
     * Scope: Entries that have not been reversed.
     * Compatible with both append-only records (no child reversal)
     * and legacy historical records (status != 'reversed').
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereDoesntHave('reversals')
            ->where('status', '!=', self::STATUS_REVERSED);
    }

    /**
     * Scope: Exclude reversal entries (positive/original commissions only).
     */
    public function scopeNonReversal(Builder $query): Builder
    {
        return $query->where('commission_type', '!=', self::TYPE_REVERSAL);
    }

    /**
     * Scope: Unreversed pending commissions.
     */
    public function scopePendingActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING)
            ->where('commission_type', '!=', self::TYPE_REVERSAL)
            ->whereDoesntHave('reversals');
    }

    /**
     * Scope: Unreversed approved commissions.
     */
    public function scopeApprovedActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_APPROVED)
            ->where('commission_type', '!=', self::TYPE_REVERSAL)
            ->whereDoesntHave('reversals');
    }

    public static function generateEntryNumber(): string
    {
        $date = now()->format('Ymd');
        $random = strtoupper(Str::random(6));
        return "COMM-{$date}-{$random}";
    }
}

