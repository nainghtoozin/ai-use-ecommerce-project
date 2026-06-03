<?php

namespace App\Http\Requests;

use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaymentMethodStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isBankTransfer = $this->input('type') === 'bank_transfer';

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('payment_methods', 'name')
                    ->where('tenant_id', Tenant::getCurrent()?->id),
            ],
            'type' => 'required|string|in:bank_transfer,cod',
            'account_name' => $isBankTransfer
                ? ['required', 'string', 'max:255']
                : ['nullable', 'string', 'max:255'],
            'account_number' => $isBankTransfer
                ? ['required', 'string', 'max:255']
                : ['nullable', 'string', 'max:255'],
            'qr_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'bank_name' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ];
    }

    protected function passedValidation(): void
    {
        $data = $this->validated();
        foreach (['account_name', 'account_number', 'bank_name'] as $field) {
            if (isset($data[$field]) && $data[$field] === '') {
                $data[$field] = null;
            }
        }
        $this->replace($data);
    }
}
