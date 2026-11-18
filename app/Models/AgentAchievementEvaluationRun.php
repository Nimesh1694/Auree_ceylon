<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentAchievementEvaluationRun extends Model
{
    use HasFactory;

    protected $table = 'agent_achievement_evaluation_runs';

    protected $fillable = [
        'run_type',
        'period_type',
        'period_key',
        'started_at',
        'completed_at',
        'status',
        'agents_evaluated',
        'awards_created',
        'streaks_updated',
        'error_summary',
        'metadata',
        'executed_by',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'agents_evaluated' => 'integer',
        'awards_created' => 'integer',
        'streaks_updated' => 'integer',
        'metadata' => 'array',
    ];

    public function executor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'executed_by');
    }
}

