<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('roles.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'not_in:superadmin,admin', Rule::unique('roles', 'name')->where(fn($q) => $q->where('guard_name', 'web')->where('tenant_id', tenant()?->id))],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'A role with this name already exists.',
            'name.not_in' => 'Cannot create a role with a protected system name.',
            'permissions.*.exists' => 'One or more selected permissions do not exist.',
        ];
    }
}
