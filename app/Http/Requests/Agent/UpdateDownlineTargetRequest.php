<?php

namespace App\Http\Requests\Agent;

use App\Models\AgentSalesTarget;
use App\Models\User;
use App\Models\UserClosure;
use App\Services\AgentSalesTargetService;
use App\Services\PeriodBoundaryService;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

class UpdateDownlineTargetRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * Enforces active HLA actor, active LLA recipient, non-closed month,
     * downline closure hierarchy (depth > 0), and Management-Controlled provenance protection.
     */
    public function authorize(): bool
    {
        $actor = $this->user();
        if (!$actor || !$actor->isHighLevelAgent() || ((string) ($actor->status->value ?? $actor->status)) !== 'active') {
            return false;
        }

        /** @var AgentSalesTarget|null $target */
        $target = $this->route('target');
        if (!$target instanceof AgentSalesTarget) {
            $target = AgentSalesTarget::find($target);
        }

        if (!$target) {
            return false;
        }

        // Must not be a closed historical month
        $currentColomboMonth = Carbon::now(PeriodBoundaryService::TIMEZONE)->format('Y-m');
        if ($target->target_month < $currentColomboMonth) {
            return false;
        }

        // Must not be Management-Controlled (provenance fail-closed guard)
        $targetService = app(AgentSalesTargetService::class);
        if ($targetService->isTargetManagementControlled($target)) {
            return false;
        }

        // Recipient must be active Low-Level Agent with valid KYC
        $targetAgent = $target->agent ?? User::find($target->agent_id);
        if (!$targetAgent || !$targetAgent->isLowLevelAgent() || ((string) ($targetAgent->status->value ?? $targetAgent->status)) !== 'active') {
            return false;
        }

        if ($targetAgent->agentProfile && $targetAgent->agentProfile->kyc_status === 'rejected') {
            return false;
        }

        // Strict closure validation: Must be descendant with depth > 0
        return UserClosure::where('ancestor_id', $actor->id)
            ->where('descendant_id', $target->agent_id)
            ->where('depth', '>', 0)
            ->exists();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
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
            'reason.min' => 'A valid adjustment reason of at least 5 characters is required.',
        ];
    }
}

