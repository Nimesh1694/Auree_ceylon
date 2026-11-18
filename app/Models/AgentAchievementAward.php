<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AgentAchievementAward extends Model
{
    use HasFactory;

    protected $table = 'agent_achievement_awards';

    protected $fillable = [
        'agent_id',
        'achievement_id',
        'version_id',
        'period_key',
        'achieved_value',
        'threshold_snapshot',
        'status',
        'is_discretionary',
        'request_id',
        'awarded_at',
        'awarded_by',
        'revoked_at',
        'revoked_by',
        'revocation_reason',
        'correction_of_award_id',
        'metadata',
    ];

    protected $casts = [
        'achieved_value' => 'decimal:2',
        'threshold_snapshot' => 'decimal:2',
        'is_discretionary' => 'boolean',
        'awarded_at' => 'datetime',
        'revoked_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function achievement(): BelongsTo
    {
        return $this->belongsTo(AgentAchievement::class, 'achievement_id');
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(AgentAchievementVersion::class, 'version_id');
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(AgentAchievementRequest::class, 'request_id');
    }

    public function awardedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'awarded_by');
    }

    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    public function correctionOf(): BelongsTo
    {
        return $this->belongsTo(AgentAchievementAward::class, 'correction_of_award_id');
    }

    public function corrections(): HasMany
    {
        return $this->hasMany(AgentAchievementAward::class, 'correction_of_award_id');
    }
}

