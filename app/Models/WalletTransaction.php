<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

class WalletTransaction extends Model
{
    use HasFactory;

    public const TYPE_COMMISSION_CREDIT = 'commission_credit';
    public const TYPE_COMMISSION_REVERSAL = 'commission_reversal';
    public const TYPE_PAYOUT_RESERVED = 'payout_reserved';
    public const TYPE_PAYOUT_RELEASED = 'payout_released';
    public const TYPE_PAYOUT_COMPLETED = 'payout_completed';

    protected $fillable = [
        'transaction_number',
        'agent_id',
        'transaction_type',
        'amount',
        'balance_after',
        'available_balance_after',
        'currency',
        'source_type',
        'source_id',
        'description',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'available_balance_after' => 'decimal:2',
        'metadata' => 'array',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public static function generateTransactionNumber(): string
    {
        $date = now()->format('Ymd');
        $random = strtoupper(Str::random(6));
        return "WLT-{$date}-{$random}";
    }
}

