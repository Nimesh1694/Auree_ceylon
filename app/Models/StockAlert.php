<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockAlert extends Model
{
    use HasFactory;

    public const STATUS_OPEN = 'open';
    public const STATUS_ACKNOWLEDGED = 'acknowledged';
    public const STATUS_RESOLVED = 'resolved';

    protected $fillable = [
        'product_id',
        'active_product_id',
        'status',
        'stock_at_trigger',
        'threshold_at_trigger',
        'current_stock',
        'trigger_source',
        'opened_at',
        'last_notified_at',
        'acknowledged_by',
        'acknowledged_at',
        'acknowledgement_note',
        'resolved_at',
        'resolution_type',
    ];

    protected function casts(): array
    {
        return [
            'stock_at_trigger' => 'integer',
            'threshold_at_trigger' => 'integer',
            'current_stock' => 'integer',
            'opened_at' => 'datetime',
            'last_notified_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function acknowledger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    protected static function booted(): void
    {
        static::saving(function (StockAlert $alert): void {
            if (in_array($alert->status, [self::STATUS_OPEN, self::STATUS_ACKNOWLEDGED], true)) {
                $alert->active_product_id = $alert->product_id;
            } else {
                $alert->active_product_id = null;
            }
        });
    }
}

