<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;

class MergeRecentlyViewedRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('customer') === true;
    }

    public function rules(): array
    {
        return [
            'idempotency_key'    => ['required', 'string', 'uuid:4'],
            'items'              => ['required', 'array', 'min:1', 'max:50'],
            'items.*.product_id' => ['required', 'integer', 'min:1'],
            'items.*.viewed_at'  => ['required', 'string', 'date_format:Y-m-d\TH:i:s\Z,Y-m-d\TH:i:sP,c'],
        ];
    }
}

