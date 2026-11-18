<?php

namespace App\Http\Requests\Public;

use Illuminate\Foundation\Http\FormRequest;

class StoreBackInStockSubscriptionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'locale' => ['nullable', 'string', 'in:en,si'],
        ];

        // If guest or unverified, require a valid email address
        $user = $this->user();
        if (!$user || !$user->email_verified_at) {
            $rules['email'] = ['required', 'string', 'email:rfc', 'max:255'];
        } else {
            $rules['email'] = ['nullable', 'string', 'email:rfc', 'max:255'];
        }

        return $rules;
    }

    /**
     * Custom validation messages.
     */
    public function messages(): array
    {
        return [
            'product_id.required' => 'A valid product is required.',
            'product_id.exists' => 'The selected product does not exist.',
            'email.required' => 'Please provide a valid email address.',
            'email.email' => 'Please provide a valid email address.',
            'email.max' => 'The email address must not exceed 255 characters.',
        ];
    }
}
