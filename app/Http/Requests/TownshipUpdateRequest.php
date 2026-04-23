<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TownshipUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'city_id' => 'required|exists:cities,id',
            'name' => 'required|string|max:255',
            'postal_code' => 'nullable|string|max:10',
            'is_active' => 'boolean',
        ];
    }
}
