<?php

namespace App\Http\Requests;

use App\Models\PaymentMethod;
use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaymentMethodUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $paymentMethod = $this->route('paymentMethod')
            ?? $this->route('payment_method')
            ?? $this->route('paymentMethods');

        $ignoreId = null;
        if ($paymentMethod instanceof PaymentMethod) {
            $ignoreId = $paymentMethod->id;
        } elseif (is_numeric($paymentMethod)) {
            $ignoreId = $paymentMethod;
        }

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('payment_methods', 'name')
                    ->ignore($ignoreId)
                    ->where('tenant_id', Tenant::getCurrent()?->id),
            ],
            'type' => 'required|string|in:bank_transfer,cod',
            'account_name' => 'required|string|max:255',
            'account_number' => 'required|string|max:255',
            'qr_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'bank_name' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ];
    }
}
