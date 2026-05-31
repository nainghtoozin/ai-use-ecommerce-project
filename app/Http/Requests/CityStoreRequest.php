<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CityStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('cities', 'name')->where('tenant_id', tenant()?->id),
            ],
            'delivery_fee' => 'required|numeric|min:0',
            'is_active' => 'boolean',
        ];
    }
}
