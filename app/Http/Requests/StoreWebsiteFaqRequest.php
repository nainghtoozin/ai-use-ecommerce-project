<?php

namespace App\Http\Requests;

use App\Models\WebsiteFaq;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWebsiteFaqRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('settings.website');
    }

    public function rules(): array
    {
        return [
            'category' => ['required', Rule::in(array_keys(WebsiteFaq::CATEGORIES))],
            'question_en' => 'required|string|max:500',
            'question_my' => 'nullable|string|max:500',
            'answer_en' => 'required|string|max:50000',
            'answer_my' => 'nullable|string|max:50000',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'category.required' => 'Please select a category.',
            'question_en.required' => 'The English question is required.',
            'answer_en.required' => 'The English answer is required.',
        ];
    }
}
