<?php

namespace App\Http\Requests\Agent;

use App\Models\AgentSalesTarget;
use App\Models\User;
use App\Models\UserClosure;
use App\Services\PeriodBoundaryService;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDownlineTargetRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * Enforces active HLA actor, active LLA recipient, and closure depth > 0.
     */
    public function authorize(): bool
    {
        $actor = $this->user();
        if (!$actor || !$actor->isHighLevelAgent() || ((string) ($actor->status->value ?? $actor->status)) !== 'active') {
            return false;
        }

        $targetAgentId = (int) $this->input('agent_id');
        if ($targetAgentId <= 0 || $targetAgentId === $actor->id) {
            return false; // HLA cannot set targets for themselves or invalid ID
        }

        $targetAgent = User::find($targetAgentId);
        if (!$targetAgent || !$targetAgent->isLowLevelAgent() || ((string) ($targetAgent->status->value ?? $targetAgent->status)) !== 'active') {
            return false; // Target must be an active Low-Level Agent
        }

        if ($targetAgent->agentProfile && $targetAgent->agentProfile->kyc_status === 'rejected') {
            return false; // Target must not have rejected KYC status
        }

        // Strict closure validation: Must be a descendant with depth > 0
        return UserClosure::where('ancestor_id', $actor->id)
            ->where('descendant_id', $targetAgentId)
            ->where('depth', '>', 0)
            ->exists();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $currentColomboMonth = Carbon::now(PeriodBoundaryService::TIMEZONE)->format('Y-m');

        return [
            'agent_id' => [
                'required',
                'integer',
                'exists:users,id',
            ],
            'target_month' => [
                'required',
                'string',
                'regex:/^\d{4}-(0[1-9]|1[0-2])$/',
                "after_or_equal:{$currentColomboMonth}",
                Rule::unique('agent_sales_targets', 'target_month')->where(function ($query) {
                    return $query->where('agent_id', $this->input('agent_id'));
                }),
            ],
            'sales_target' => [
                'required',
                'numeric',
                'min:0.01',
                'max:999999999999.99',
            ],
            'reason' => [
                'required',
                'string',
                'min:5',
                'max:1000',
            ],
        ];
    }

    /**
     * Custom validation messages.
     */
    public function messages(): array
    {
        return [
            'target_month.after_or_equal' => 'Targets cannot be assigned for closed historical months.',
            'target_month.unique' => 'A sales target already exists for this agent in the selected month.',
            'reason.min' => 'A clear justification of at least 5 characters is required.',
        ];
    }
}

