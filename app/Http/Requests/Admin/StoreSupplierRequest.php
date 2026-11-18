<?php

namespace App\Http\Requests\Admin;

use App\Models\Supplier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        return (bool) ($user && ($user->isOwner() || $user->hasPermissionTo('suppliers.manage')));
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('code')) {
            $this->merge(['code' => strtoupper(trim((string) $this->input('code')))]);
        }
    }

    public function rules(): array
    {
        return [
            'code' => ['nullable', 'string', 'max:32', 'regex:/^[A-Z0-9\-]+$/', Rule::unique('suppliers', 'code')],
            'name' => ['required', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:1000'],
            'city' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'tax_id' => ['nullable', 'string', 'max:100'],
            'payment_terms' => ['required', 'string', Rule::in([
                Supplier::TERMS_COD,
                Supplier::TERMS_ADVANCE,
                Supplier::TERMS_NET_7,
                Supplier::TERMS_NET_15,
                Supplier::TERMS_NET_30,
                Supplier::TERMS_NET_60,
            ])],
            'default_lead_time_days' => ['required', 'integer', 'min:0', 'max:365'],
            'status' => ['required', 'string', Rule::in([Supplier::STATUS_ACTIVE, Supplier::STATUS_INACTIVE])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}

