<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AgentAnnouncement extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_ARCHIVED = 'archived';

    public const AUDIENCE_ALL = 'all';
    public const AUDIENCE_HLA = 'high_level_agent';
    public const AUDIENCE_LLA = 'low_level_agent';

    public const PRIORITY_NORMAL = 'normal';
    public const PRIORITY_IMPORTANT = 'important';
    public const PRIORITY_URGENT = 'urgent';

    public const ARCHIVED_REASON_MANUAL = 'manual';
    public const ARCHIVED_REASON_SYSTEM_EXPIRY = 'system_expiry';

    protected $fillable = [
        'title_en',
        'title_si',
        'content_en',
        'content_si',
        'target_audience',
        'priority',
        'status',
        'archived_reason',
        'is_pinned',
        'pin_order',
        'scheduled_at',
        'published_at',
        'expires_at',
        'created_by',
        'scheduled_by',
        'published_by',
        'archived_by',
    ];

    protected $appends = [
        'localized_title',
        'localized_content',
    ];

    protected function casts(): array
    {
        return [
            'is_pinned' => 'boolean',
            'pin_order' => 'integer',
            'scheduled_at' => 'datetime',
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scheduler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scheduled_by');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function archiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'archived_by');
    }

    public function reads(): HasMany
    {
        return $this->hasMany(AgentAnnouncementRead::class, 'announcement_id');
    }

    public function getLocalizedTitleAttribute(): string
    {
        $locale = app()->getLocale();
        if ($locale === 'si' && !empty($this->title_si)) {
            return $this->title_si;
        }

        return $this->title_en;
    }

    public function getLocalizedContentAttribute(): string
    {
        $locale = app()->getLocale();
        if ($locale === 'si' && !empty($this->content_si)) {
            return $this->content_si;
        }

        return $this->content_en;
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PUBLISHED)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function scopeActiveFeed(Builder $query, string $agentRole): Builder
    {
        return $query->published()
            ->where(function (Builder $q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->whereIn('target_audience', [self::AUDIENCE_ALL, $agentRole])
            ->orderBy('is_pinned', 'desc')
            ->orderBy('pin_order', 'asc')
            ->orderBy('published_at', 'desc')
            ->orderBy('id', 'desc');
    }
}

