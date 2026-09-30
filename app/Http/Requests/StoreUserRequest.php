<?php

namespace App\Http\Requests;

use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('users.create') ?? false;
    }

    public function rules(): array
    {
        $emailTable = config('identity.use_accounts') ? 'accounts,email' : 'users,email';
        $tenantId = Tenant::getCurrent()?->id;

        $roleExists = Rule::exists('roles', 'name');
        if ($tenantId) {
            $roleExists->where(fn ($q) => $q->where('tenant_id', $tenantId)->orWhereNull('tenant_id'));
        }

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:' . $emailTable],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'role' => ['required', 'string', $roleExists, 'not_in:customer'],
            'status' => ['required', 'string', 'in:active,suspended,banned'],
            'allow_cod' => ['nullable', 'boolean'],
            'profile_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'role.exists' => 'The selected role does not exist for this store.',
            'role.not_in' => 'Customer accounts cannot be created here. Customers register through the storefront.',
            'status.in' => 'Status must be one of: active, suspended, banned.',
        ];
    }
}
