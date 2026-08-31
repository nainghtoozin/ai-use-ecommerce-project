<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'parent_id' => ['nullable', 'integer', 'exists:categories,id'],
            'is_active' => ['boolean'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
            'featured' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $parentId = $this->input('parent_id');
            if ($parentId) {
                $parent = \App\Models\Category::withoutGlobalScopes()->find($parentId);
                if (!$parent) {
                    return;
                }
                if ($parent->tenant_id !== tenant()?->id) {
                    $validator->errors()->add('parent_id', 'The selected parent category does not belong to your store.');
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Category name is required.',
            'parent_id.exists' => 'The selected parent category is invalid.',
        ];
    }
}
