<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CityStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255|unique:cities,name',
            'delivery_fee' => 'required|numeric|min:0',
            'is_active' => 'boolean',
        ];
    }
}
