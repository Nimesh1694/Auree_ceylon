<?php

namespace App\Http\Requests\Admin;

use App\Models\AgentAchievementRequest;
use Illuminate\Foundation\Http\FormRequest;

class ApproveAchievementRequest extends FormRequest
{
    public function authorize(): bool
    {
        $achievementRequest = $this->route('request');
        if (is_numeric($achievementRequest)) {
            $achievementRequest = AgentAchievementRequest::find($achievementRequest);
        }

        return $achievementRequest ? $this->user()->can('approveRequest', $achievementRequest) : false;
    }

    public function rules(): array
    {
        return [
            'decision_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}

