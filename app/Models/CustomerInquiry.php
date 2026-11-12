<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class CustomerInquiry extends Model
{
    use HasFactory;

    public const STATUS_NEW = 'new';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_WAITING_CUSTOMER = 'waiting_customer';
    public const STATUS_RESOLVED = 'resolved';
    public const STATUS_CLOSED = 'closed';

    public const PRIORITY_LOW = 'low';
    public const PRIORITY_NORMAL = 'normal';
    public const PRIORITY_HIGH = 'high';
    public const PRIORITY_URGENT = 'urgent';

    public const NOTIFICATION_PENDING = 'pending';
    public const NOTIFICATION_SENT = 'sent';
    public const NOTIFICATION_FAILED = 'failed';
    public const NOTIFICATION_SKIPPED = 'skipped';

    protected $fillable = [
        'inquiry_number',
        'customer_id',
        'name',
        'email',
        'phone',
        'subject',
        'message',
        'status',
        'priority',
        'assigned_to',
        'resolved_at',
        'resolved_by',
        'notification_status',
        'notification_error',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * Customer who submitted the inquiry (null for guest inquiries).
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    /**
     * Staff user assigned to this inquiry.
     */
    public function assignedStaff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Staff user who marked the inquiry resolved.
     */
    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /**
     * Append-only administrative internal notes thread.
     */
    public function notes(): HasMany
    {
        return $this->hasMany(CustomerInquiryNote::class, 'customer_inquiry_id')->latest('created_at');
    }

    /**
     * Scope for filtering by status.
     */
    public function scopeStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * Scope for filtering by priority.
     */
    public function scopePriority(Builder $query, string $priority): Builder
    {
        return $query->where('priority', $priority);
    }

    /**
     * Scope for unresolved (active triage) inquiries.
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_NEW, self::STATUS_IN_PROGRESS]);
    }

    /**
     * Scope for resolved/closed inquiries.
     */
    public function scopeResolved(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_RESOLVED, self::STATUS_CLOSED]);
    }

    /**
     * The "booted" method of the model.
     * Enforces Option A application-level compliance record immutability.
     */
    protected static function booted(): void
    {
        static::deleting(function (CustomerInquiry $inquiry) {
            throw new \LogicException('Customer inquiries are immutable compliance records and cannot be deleted.');
        });
    }

    /**
     * Get all valid inquiry statuses.
     *
     * @return array<int, string>
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_NEW,
            self::STATUS_IN_PROGRESS,
            self::STATUS_WAITING_CUSTOMER,
            self::STATUS_RESOLVED,
            self::STATUS_CLOSED,
        ];
    }

    /**
     * Get all valid inquiry priorities.
     *
     * @return array<int, string>
     */
    public static function getPriorities(): array
    {
        return [
            self::PRIORITY_LOW,
            self::PRIORITY_NORMAL,
            self::PRIORITY_HIGH,
            self::PRIORITY_URGENT,
        ];
    }

    /**
     * Generate a unique human-readable inquiry reference number.
     * Format: INQ-YYYYMMDD-XXXXX (e.g., INQ-20261111-K9P2X)
     */
    public static function generateInquiryNumber(): string
    {
        do {
            $candidate = 'INQ-' . date('Ymd') . '-' . strtoupper(Str::random(5));
        } while (static::where('inquiry_number', $candidate)->exists());

        return $candidate;
    }
}

