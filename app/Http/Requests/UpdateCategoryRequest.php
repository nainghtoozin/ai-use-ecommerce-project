<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $category = $this->route('category');

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'parent_id' => ['nullable', 'integer', 'exists:categories,id'],
            'is_active' => ['boolean'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
            'remove_image' => ['boolean'],
            'featured' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $category = $this->route('category');
            $parentId = $this->input('parent_id');

            if ($parentId && $category) {
                if ((int) $parentId === (int) $category->id) {
                    $validator->errors()->add('parent_id', 'A category cannot be its own parent.');
                    return;
                }

                $parent = \App\Models\Category::withoutGlobalScopes()->find($parentId);
                if (!$parent) {
                    return;
                }

                if ($parent->tenant_id !== tenant()?->id) {
                    $validator->errors()->add('parent_id', 'The selected parent category does not belong to your store.');
                    return;
                }

                $categoryModel = \App\Models\Category::withoutGlobalScopes()->find($category->id);
                if ($categoryModel && $categoryModel->hasCircularReference((int) $parentId)) {
                    $validator->errors()->add('parent_id', 'Cannot select this parent as it would create a circular reference.');
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
