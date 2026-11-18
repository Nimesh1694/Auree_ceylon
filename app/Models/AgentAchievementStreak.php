<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentAchievementStreak extends Model
{
    use HasFactory;

    protected $table = 'agent_achievement_streaks';

    protected $fillable = [
        'agent_id',
        'streak_type',
        'current_streak',
        'longest_streak',
        'last_qualifying_period',
        'streak_started_period',
        'is_active',
    ];

    protected $casts = [
        'current_streak' => 'integer',
        'longest_streak' => 'integer',
        'is_active' => 'boolean',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }
}

