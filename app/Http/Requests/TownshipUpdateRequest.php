<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TownshipUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = tenant()?->id;

        return [
            'city_id' => [
                'required',
                Rule::exists('cities', 'id')->where('tenant_id', $tenantId),
            ],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('townships', 'name')
                    ->where('tenant_id', $tenantId)
                    ->where('city_id', $this->input('city_id'))
                    ->ignore($this->route('township')),
            ],
            'postal_code' => 'nullable|string|max:10',
            'delivery_fee' => 'required|numeric|min:0',
            'is_active' => 'boolean',
        ];
    }
}
