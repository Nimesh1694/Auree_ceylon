<?php

namespace App\Http\Requests\Admin;

use App\Models\AgentAchievementAward;
use Illuminate\Foundation\Http\FormRequest;

class RevokeAchievementAwardRequest extends FormRequest
{
    public function authorize(): bool
    {
        $award = $this->route('award');
        if (is_numeric($award)) {
            $award = AgentAchievementAward::find($award);
        }

        return $award ? $this->user()->can('revoke', [AgentAchievementAward::class, $award]) : false;
    }

    public function rules(): array
    {
        return [
            'revocation_reason' => ['required', 'string', 'min:20', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'revocation_reason.required' => 'A revocation justification reason is mandatory.',
            'revocation_reason.min' => 'Please provide a detailed revocation reason (at least 20 characters).',
        ];
    }
}

