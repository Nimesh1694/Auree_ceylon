<?php

namespace App\Http\Requests\Admin;

use App\Models\AgentAchievementAward;
use Illuminate\Foundation\Http\FormRequest;

class ReinstateAchievementAwardRequest extends FormRequest
{
    public function authorize(): bool
    {
        $award = $this->route('award');
        if (is_numeric($award)) {
            $award = AgentAchievementAward::find($award);
        }

        return $award ? $this->user()->can('reinstate', [AgentAchievementAward::class, $award]) : false;
    }

    public function rules(): array
    {
        return [
            'reinstatement_reason' => ['required', 'string', 'min:20', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'reinstatement_reason.required' => 'A reinstatement justification reason is mandatory.',
            'reinstatement_reason.min' => 'Please provide a detailed reinstatement reason (at least 20 characters).',
        ];
    }
}

