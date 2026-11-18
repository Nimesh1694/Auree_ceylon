<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAgentSalesTargetRequest extends FormRequest
{
    public function authorize(): bool
    {
        if ($this->boolean('is_override')) {
            return $this->user()?->isOwner() ?? false;
        }

        return $this->user()?->can('agents.targets.manage') ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('month') && !$this->has('target_month')) {
            $this->merge(['target_month' => $this->input('month')]);
        }
    }

    public function rules(): array
    {
        $rules = [
            'agent_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where(function ($query) {
                    $query->whereIn('role', [
                        UserRole::HIGH_LEVEL_AGENT->value,
                        UserRole::LOW_LEVEL_AGENT->value,
                    ])->where('status', 'active');
                }),
            ],
            'target_month' => [
                'required',
                'string',
                'date_format:Y-m',
                'regex:/^20[2-9][0-9]-(0[1-9]|1[0-2])$/',
                Rule::unique('agent_sales_targets', 'target_month')->where(function ($query) {
                    return $query->where('agent_id', $this->input('agent_id'));
                }),
            ],
            'sales_target' => [
                'required',
                'numeric',
                'min:0.01',
                'max:999999999.99',
                'decimal:0,2',
            ],
        ];

        if ($this->boolean('is_override')) {
            $rules['override_reason'] = [
                'required',
                'string',
                'min:10',
                'max:255',
                'regex:/[^\s]/',
            ];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'agent_id.required' => 'An active agent must be selected.',
            'agent_id.exists' => 'The selected user is not an active agent.',
            'target_month.required' => 'The target month is required.',
            'target_month.date_format' => 'The target month must be in YYYY-MM format.',
            'target_month.regex' => 'The target month must be a valid 4-digit year and 2-digit zero-padded month (01-12).',
            'target_month.unique' => 'A sales target has already been established for this agent in the selected month.',
            'sales_target.required' => 'A sales target amount is required.',
            'sales_target.numeric' => 'The sales target must be a valid numerical amount.',
            'sales_target.min' => 'The sales target must be strictly greater than 0.00 LKR.',
            'sales_target.decimal' => 'The sales target cannot exceed 2 decimal places.',
            'sales_target.max' => 'The sales target exceeds the maximum allowable threshold.',
            'override_reason.required' => 'A comprehensive override reason is required for historical creation.',
            'override_reason.min' => 'The override reason must be at least 10 characters.',
            'override_reason.regex' => 'The override reason cannot be empty or whitespace-only.',
        ];
    }
}
