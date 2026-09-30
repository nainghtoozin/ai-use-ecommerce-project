<?php

namespace App\Http\Requests;

use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('users.update') ?? false;
    }

    public function rules(): array
    {
        $userId = $this->route('user');
        $emailTable = config('identity.use_accounts') ? 'accounts' : 'users';
        $tenantId = Tenant::getCurrent()?->id;

        $roleExists = Rule::exists('roles', 'name');
        if ($tenantId) {
            $roleExists->where(fn ($q) => $q->where('tenant_id', $tenantId)->orWhereNull('tenant_id'));
        }

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'string', 'lowercase', 'email', 'max:255', Rule::unique($emailTable, 'email')->ignore($userId)],
            'password' => ['nullable', 'confirmed', Rules\Password::defaults()],
            'role' => ['sometimes', 'string', $roleExists, 'not_in:customer'],
            'status' => ['sometimes', 'string', 'in:active,suspended,banned'],
            'allow_cod' => ['nullable', 'boolean'],
            'profile_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'role.exists' => 'The selected role does not exist for this store.',
            'role.not_in' => 'Member role cannot be changed to customer. Customers register through the storefront.',
            'status.in' => 'Status must be one of: active, suspended, banned.',
        ];
    }
}
