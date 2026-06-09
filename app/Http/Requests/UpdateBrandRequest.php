<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBrandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $brand = $this->route('brand');

        return [
            'name' => [
                'required',
                'max:255',
                Rule::unique('brands', 'name')
                    ->where('tenant_id', tenant()?->id)
                    ->ignore($brand?->id),
            ],
            'slug' => [
                'nullable',
                'max:255',
                Rule::unique('brands', 'slug')
                    ->where('tenant_id', tenant()?->id)
                    ->ignore($brand?->id),
            ],
            'description' => 'nullable|string',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'is_active' => 'boolean',
        ];
    }
}
