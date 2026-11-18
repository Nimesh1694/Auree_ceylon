<?php

namespace App\Http\Requests\Admin;

use App\Models\AgentAchievement;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class DirectGrantAchievementAwardRequest extends FormRequest
{
    public function authorize(): bool
    {
        $beneficiary = User::find($this->input('agent_id'));
        return $this->user()->can('directGrant', [AgentAchievement::class, $beneficiary]);
    }

    public function rules(): array
    {
        return [
            'agent_id' => [
                'required',
                'integer',
                'exists:users,id',
                function ($attribute, $value, $fail) {
                    if ((int) $value === (int) $this->user()->id) {
                        $fail('Anti-Self-Approval: The Business Owner cannot grant an achievement to their own account.');
                    }
                    $agent = User::find($value);
                    if (!$agent || !$agent->isAgent() || $agent->status->value !== 'active') {
                        $fail('The selected agent must be an active registered brand ambassador.');
                    }
                    if (!$agent->agentProfile || $agent->agentProfile->kyc_status !== 'verified') {
                        $fail('The selected agent must have verified KYC status.');
                    }
                },
            ],
            'achievement_id' => [
                'required',
                'integer',
                'exists:agent_achievements,id',
                function ($attribute, $value, $fail) {
                    $achievement = AgentAchievement::find($value);
                    if (!$achievement || !$achievement->is_active) {
                        $fail('The selected achievement definition is inactive or not found.');
                    }
                    if (!$achievement->is_discretionary) {
                        $fail('Direct grants can only be issued for discretionary achievement awards.');
                    }
                },
            ],
            'period_key' => ['nullable', 'string', 'max:32'],
            'justification' => ['required', 'string', 'min:20', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'justification.min' => 'Please provide a detailed executive justification (at least 20 characters).',
        ];
    }
}

