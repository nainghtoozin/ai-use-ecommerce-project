<?php

namespace App\Services;

use App\Models\PaymentMethod;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;

class PaymentMethodService
{
    public function __construct(
        private readonly ImageService $imageService
    ) {}

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
        $data = $this->handleQrImage($data);
        return PaymentMethod::create($data);
    }

    public function updatePaymentMethod(PaymentMethod $paymentMethod, array $data): PaymentMethod
    {
        $data = $this->handleQrImage($data, $paymentMethod);
        $paymentMethod->update($data);
        return $paymentMethod->fresh();
    }

    private function handleQrImage(array $data, ?PaymentMethod $paymentMethod = null): array
    {
        if (isset($data['qr_image']) && $data['qr_image'] instanceof UploadedFile) {
            if ($paymentMethod && $paymentMethod->qr_image) {
                $this->imageService->delete($paymentMethod->qr_image);
            }
            $data['qr_image'] = $this->imageService->upload($data['qr_image'], 'payment-methods');
        } elseif (!isset($data['qr_image']) && $paymentMethod) {
            $data['qr_image'] = $paymentMethod->qr_image;
        }
        return $data;
    }

    public function deletePaymentMethod(PaymentMethod $paymentMethod): bool
    {
        $this->imageService->delete($paymentMethod->qr_image);
        return $paymentMethod->delete();
    }

    public function toggleActive(PaymentMethod $paymentMethod): PaymentMethod
    {
        $paymentMethod->update([
            'is_active' => !$paymentMethod->is_active,
        ]);
        return $paymentMethod->fresh();
    }
}
