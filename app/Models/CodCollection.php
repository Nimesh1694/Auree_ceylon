<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CodCollection extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_MATCHED = 'matched';
    public const STATUS_MISMATCHED = 'mismatched';
    public const STATUS_MISMATCHED_UNDERPAID = 'mismatched_underpaid';
    public const STATUS_MISMATCHED_OVERPAID = 'mismatched_overpaid';

    protected $fillable = [
        'order_id',
        'expected_amount',
        'collected_amount',
        'courier_name',
        'courier_reference',
        'reconciled_by',
        'reconciliation_status',
        'collected_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'expected_amount' => 'decimal:2',
            'collected_amount' => 'decimal:2',
            'collected_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function reconciledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reconciled_by');
    }
}

