<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AgentAchievement extends Model
{
    use HasFactory;

    protected $table = 'agent_achievements';

    protected $fillable = [
        'code',
        'name',
        'description',
        'category',
        'badge_tier',
        'badge_icon',
        'metric_type',
        'threshold_value',
        'period_type',
        'is_recurring',
        'is_discretionary',
        'is_active',
        'current_version',
        'sort_order',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'threshold_value' => 'decimal:2',
        'is_recurring' => 'boolean',
        'is_discretionary' => 'boolean',
        'is_active' => 'boolean',
        'current_version' => 'integer',
        'sort_order' => 'integer',
    ];

    public function versions(): HasMany
    {
        return $this->hasMany(AgentAchievementVersion::class, 'achievement_id');
    }

    public function awards(): HasMany
    {
        return $this->hasMany(AgentAchievementAward::class, 'achievement_id');
    }

    public function requests(): HasMany
    {
        return $this->hasMany(AgentAchievementRequest::class, 'achievement_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}

