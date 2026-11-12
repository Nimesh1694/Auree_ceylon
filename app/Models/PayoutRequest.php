<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class PayoutRequest extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_PAID = 'paid';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'reference_number',
        'agent_id',
        'amount',
        'currency',
        'status',
        'bank_name',
        'bank_branch',
        'bank_account_number',
        'bank_account_name',
        'bank_snapshot',
        'agent_notes',
        'rejection_reason',
        'review_notes',
        'reviewed_by',
        'reviewed_at',
        'paid_by',
        'paid_at',
        'failed_by',
        'failed_at',
        'failure_reason',
        'bank_transfer_reference',
        'bank_transfer_receipt_path',
        'bank_transfer_notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'bank_snapshot' => 'array',
        'reviewed_at' => 'datetime',
        'paid_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function failedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'failed_by');
    }

    public function walletTransactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class, 'source_id')
            ->where('source_type', self::class);
    }

    public static function generateReferenceNumber(): string
    {
        $date = now()->format('Ymd');
        $random = strtoupper(Str::random(6));
        return "PAY-{$date}-{$random}";
    }
}

