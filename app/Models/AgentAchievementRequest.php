<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AgentAchievementRequest extends Model
{
    use HasFactory;

    protected $table = 'agent_achievement_requests';

    protected $fillable = [
        'achievement_id',
        'agent_id',
        'requested_by',
        'status',
        'justification',
        'period_key',
        'expires_at',
        'decision_notes',
        'decided_by',
        'decided_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'decided_at' => 'datetime',
    ];

    public function achievement(): BelongsTo
    {
        return $this->belongsTo(AgentAchievement::class, 'achievement_id');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function award(): HasOne
    {
        return $this->hasOne(AgentAchievementAward::class, 'request_id');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('status', 'expired');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'pending')
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            });
    }
}
