<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Delivery extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'courier_name',
        'tracking_number',
        'status',
        'actual_courier_fee',
        'courier_fee_recorded_by',
        'courier_fee_recorded_at',
        'courier_invoice_reference',
        'dispatched_at',
        'delivered_at',
        'notes',
    ];

    /**
     * The attributes that should be hidden for serialization to prevent leaking
     * internal logistic expense data to customer or public APIs.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'actual_courier_fee',
    ];

    protected function casts(): array
    {
        return [
            'actual_courier_fee' => 'decimal:2',
            'courier_fee_recorded_at' => 'datetime',
            'dispatched_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'courier_fee_recorded_by');
    }

    /**
     * Check whether an actual audited courier expense has been recorded.
     */
    public function isFeeRecorded(): bool
    {
        return $this->actual_courier_fee !== null;
    }

    /**
     * Get the verified actual courier fee, or null if unrecorded.
     */
    public function getActualFee(): ?float
    {
        return $this->actual_courier_fee !== null ? (float) $this->actual_courier_fee : null;
    }
}

