<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ModerateProductReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('reviews.moderate') ?? false;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', 'in:approved,rejected'],
            'rejection_reason' => [
                'required_if:status,rejected',
                'nullable',
                'string',
                'min:5',
                'max:255',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'rejection_reason.required_if' => 'A rejection reason is required when rejecting a review.',
            'rejection_reason.min' => 'The rejection reason must be at least 5 characters.',
            'rejection_reason.max' => 'The rejection reason cannot exceed 255 characters.',
        ];
    }
}

