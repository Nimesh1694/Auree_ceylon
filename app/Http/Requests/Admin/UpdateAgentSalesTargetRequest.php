<?php

namespace App\Http\Requests\Admin;

use App\Models\AgentSalesTarget;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAgentSalesTargetRequest extends FormRequest
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
        /** @var AgentSalesTarget|int $target */
        $target = $this->route('target');
        $targetId = $target instanceof AgentSalesTarget ? $target->id : (int) $target;
        $agentId = $target instanceof AgentSalesTarget ? $target->agent_id : $this->input('agent_id');

        $rules = [
            'target_month' => [
                'sometimes',
                'required',
                'string',
                'date_format:Y-m',
                'regex:/^20[2-9][0-9]-(0[1-9]|1[0-2])$/',
                Rule::unique('agent_sales_targets', 'target_month')
                    ->where(function ($query) use ($agentId) {
                        return $query->where('agent_id', $agentId);
                    })
                    ->ignore($targetId),
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
        } else {
            $rules['reason'] = [
                'required',
                'string',
                'min:5',
                'max:255',
                'regex:/[^\s]/',
            ];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'target_month.date_format' => 'The target month must be in YYYY-MM format.',
            'target_month.regex' => 'The target month must be a valid 4-digit year and 2-digit zero-padded month (01-12).',
            'target_month.unique' => 'A sales target already exists for this agent in that month.',
            'sales_target.required' => 'A sales target amount is required.',
            'sales_target.numeric' => 'The sales target must be a valid numerical amount.',
            'sales_target.min' => 'The sales target must be strictly greater than 0.00 LKR.',
            'sales_target.decimal' => 'The sales target cannot exceed 2 decimal places.',
            'sales_target.max' => 'The sales target exceeds the maximum allowable threshold.',
            'reason.required' => 'An adjustment reason is required for sales target modifications.',
            'reason.min' => 'The adjustment reason must be at least 5 characters.',
            'reason.regex' => 'The adjustment reason cannot be empty or whitespace-only.',
            'override_reason.required' => 'A comprehensive override reason is required for historical modifications.',
            'override_reason.min' => 'The override reason must be at least 10 characters.',
            'override_reason.regex' => 'The override reason cannot be empty or whitespace-only.',
        ];
    }
}
