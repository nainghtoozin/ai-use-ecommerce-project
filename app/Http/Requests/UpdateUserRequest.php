<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        $userId = $this->route('user');

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email,' . $userId],
            'password' => ['nullable', 'confirmed', Rules\Password::defaults()],
            'role' => ['sometimes', 'string', 'exists:roles,name'],
            'status' => ['sometimes', 'string', 'in:active,suspended,banned'],
            'allow_cod' => ['nullable', 'boolean'],
            'profile_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'role.exists' => 'The selected role does not exist in the system.',
            'status.in' => 'Status must be one of: active, suspended, banned.',
        ];
    }
}
