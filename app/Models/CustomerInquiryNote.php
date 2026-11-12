<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerInquiryNote extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_inquiry_id',
        'user_id',
        'note',
    ];

    /**
     * The "booted" method of the model.
     * Enforces Option A application-level append-only immutability.
     */
    protected static function booted(): void
    {
        static::updating(function (CustomerInquiryNote $note) {
            throw new \LogicException('Customer inquiry notes are immutable and cannot be modified.');
        });

        static::deleting(function (CustomerInquiryNote $note) {
            throw new \LogicException('Customer inquiry notes are immutable compliance records and cannot be deleted.');
        });
    }

    /**
     * The parent customer inquiry.
     */
    public function inquiry(): BelongsTo
    {
        return $this->belongsTo(CustomerInquiry::class, 'customer_inquiry_id');
    }

    /**
     * The staff author of the note.
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

