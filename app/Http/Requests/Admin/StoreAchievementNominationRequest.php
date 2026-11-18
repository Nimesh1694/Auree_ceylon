<?php

namespace App\Http\Requests\Admin;

use App\Models\AgentAchievement;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class StoreAchievementNominationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('request', AgentAchievement::class);
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
                        $fail('Anti-Self-Approval: You cannot submit an achievement nomination for yourself.');
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
                        $fail('Nominations can only be submitted for discretionary achievement awards.');
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
            'justification.min' => 'Please provide a detailed business justification (at least 20 characters).',
        ];
    }
}

