<?php

namespace App\Services;

use App\Models\PaymentMethod;
use Illuminate\Database\Eloquent\Collection;

class PaymentMethodService
{
    public function getActivePaymentMethods(): Collection
    {
        return PaymentMethod::active()
            ->orderBy('name')
            ->get();
    }

    public function getAllPaymentMethods(): Collection
    {
        return PaymentMethod::orderBy('name')->get();
    }

    public function createPaymentMethod(array $data): PaymentMethod
    {
        return PaymentMethod::create($data);
    }

    public function updatePaymentMethod(PaymentMethod $paymentMethod, array $data): PaymentMethod
    {
        $paymentMethod->update($data);
        return $paymentMethod->fresh();
    }

    public function deletePaymentMethod(PaymentMethod $paymentMethod): bool
    {
        return $paymentMethod->delete();
    }

    public function toggleActive(PaymentMethod $paymentMethod): PaymentMethod
    {
        $paymentMethod->is_active = !$paymentMethod->is_active;
        $paymentMethod->save();
        return $paymentMethod;
    }
}
