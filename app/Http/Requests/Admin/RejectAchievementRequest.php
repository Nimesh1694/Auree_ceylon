<?php

namespace App\Http\Requests\Admin;

use App\Models\AgentAchievementRequest;
use Illuminate\Foundation\Http\FormRequest;

class RejectAchievementRequest extends FormRequest
{
    public function authorize(): bool
    {
        $achievementRequest = $this->route('request');
        if (is_numeric($achievementRequest)) {
            $achievementRequest = AgentAchievementRequest::find($achievementRequest);
        }

        return $achievementRequest ? $this->user()->can('rejectRequest', $achievementRequest) : false;
    }

    public function rules(): array
    {
        return [
            'decision_notes' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'decision_notes.required' => 'A rejection decision reason is mandatory.',
        ];
    }
}

