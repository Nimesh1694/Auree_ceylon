<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AgentAchievementVersion extends Model
{
    use HasFactory;

    protected $table = 'agent_achievement_versions';

    protected $fillable = [
        'achievement_id',
        'version',
        'name',
        'description',
        'category',
        'badge_tier',
        'metric_type',
        'threshold_value',
        'period_type',
        'is_recurring',
        'is_discretionary',
        'qualification_rules_snapshot',
        'change_reason',
        'created_by',
    ];

    protected $casts = [
        'version' => 'integer',
        'threshold_value' => 'decimal:2',
        'is_recurring' => 'boolean',
        'is_discretionary' => 'boolean',
        'qualification_rules_snapshot' => 'array',
    ];

    public function achievement(): BelongsTo
    {
        return $this->belongsTo(AgentAchievement::class, 'achievement_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function awards(): HasMany
    {
        return $this->hasMany(AgentAchievementAward::class, 'version_id');
    }
}

