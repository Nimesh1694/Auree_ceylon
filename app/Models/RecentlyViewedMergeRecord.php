<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecentlyViewedMergeRecord extends Model
{
    use HasFactory;

    /**
     * Immutable merge audit records only track creation time.
     * Setting UPDATED_AT = null instructs Eloquent's HasTimestamps trait
     * to never reference, insert, or update an 'updated_at' column.
     */
    const UPDATED_AT = null;

    protected $table = 'recently_viewed_merge_records';

    protected $fillable = [
        'customer_id',
        'idempotency_key',
        'payload_hash',
        'merged_count',
        'created_at',
    ];

    protected $casts = [
        'customer_id'  => 'integer',
        'merged_count' => 'integer',
        'created_at'   => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }
}

