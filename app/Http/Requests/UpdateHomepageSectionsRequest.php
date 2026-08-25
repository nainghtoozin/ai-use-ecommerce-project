<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateHomepageSectionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('settings.website') ?? false;
    }

    public function rules(): array
    {
        return [
            'sections' => ['required', 'array', 'min:1'],
            'sections.*.id' => ['required', 'integer'],
            'sections.*.enabled' => ['required', 'boolean'],
            'sections.*.desktop_visible' => ['required', 'boolean'],
            'sections.*.mobile_visible' => ['required', 'boolean'],
            'sections.*.position' => ['required', 'integer', 'min:0'],
            'sections.*.variant' => ['nullable', 'string', 'max:50'],
            'sections.*.configuration' => ['nullable', 'array'],
        ];
    }
}
