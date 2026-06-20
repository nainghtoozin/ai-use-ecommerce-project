<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (!($this->user()?->can('roles.update') ?? false)) {
            return false;
        }

        $role = $this->route('role');
        if ($role && in_array($role->name, ['superadmin', 'admin'])) {
            return false;
        }

        return true;
    }

    public function rules(): array
    {
        $roleId = $this->route('role');

        return [
            'name' => ['required', 'string', 'max:255', 'not_in:superadmin,admin', Rule::unique('roles', 'name')->where(fn($q) => $q->where('guard_name', 'web')->where('tenant_id', tenant()?->id))->ignore($roleId)],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'A role with this name already exists.',
            'name.not_in' => 'Cannot rename a role to a protected system name.',
            'permissions.*.exists' => 'One or more selected permissions do not exist.',
        ];
    }
}
