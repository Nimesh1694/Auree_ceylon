<?php

namespace App\Http\Requests;

use App\Http\Controllers\Public\CheckoutController;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class CustomerAddressRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'label' => ['nullable', 'string', 'max:50'],
            'recipient_name' => ['required', 'string', 'min:2', 'max:255'],
            'phone' => ['required', 'string', 'min:9', 'max:20'],
            'alternative_phone' => ['nullable', 'string', 'min:9', 'max:20'],
            'address_line_1' => ['required', 'string', 'min:5', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'min:2', 'max:100'],
            'district' => ['required', 'string', 'in:' . implode(',', CheckoutController::SRI_LANKA_DISTRICTS)],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'delivery_instructions' => ['nullable', 'string', 'max:500'],
            'is_default' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Configure the validator instance.
     * Enforces the business requirement: Maximum 10 saved addresses per customer.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            // Only enforce max 10 count on address creation (POST)
            if ($this->isMethod('POST')) {
                $user = $this->user();
                if ($user && $user->customerAddresses()->count() >= 10) {
                    $validator->errors()->add(
                        'address_limit',
                        'You have reached the maximum limit of 10 saved delivery addresses. Please update or remove an existing address.'
                    );
                }
            }
        });
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'label' => 'address label',
            'recipient_name' => 'recipient full name',
            'phone' => 'primary phone number',
            'alternative_phone' => 'alternative phone number',
            'address_line_1' => 'street address',
            'address_line_2' => 'apartment / landmark',
            'city' => 'city',
            'district' => 'district',
            'postal_code' => 'postal code',
            'delivery_instructions' => 'delivery instructions',
            'is_default' => 'default address',
        ];
    }
}

