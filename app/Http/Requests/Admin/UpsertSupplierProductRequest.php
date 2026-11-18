<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpsertSupplierProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        return (bool) ($user && ($user->isOwner() || $user->hasPermissionTo('suppliers.manage')));
    }

    public function rules(): array
    {
        $isCreate = $this->isMethod('POST');

        return [
            'product_id' => $isCreate ? ['required', 'integer', 'exists:products,id'] : ['nullable'],
            'supplier_sku' => ['nullable', 'string', 'max:100'],
            'unit_cost' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'lead_time_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'min_order_qty' => ['required', 'integer', 'min:1', 'max:1000000'],
            'is_preferred' => ['boolean'],
        ];
    }
}

