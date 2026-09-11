<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TownshipStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'city_id' => 'required|exists:cities,id',
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('townships', 'name')->where('city_id', $this->input('city_id')),
            ],
            'postal_code' => 'nullable|string|max:10',
            'is_active' => 'boolean',
        ];
    }
}
