<?php

namespace App\Services;

use App\Models\BillingPaymentMethod;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;

class BillingPaymentMethodService
{
    public function __construct(
        private readonly ImageService $imageService
    ) {}

    public function getActivePaymentMethods(): Collection
    {
        return BillingPaymentMethod::active()
            ->ordered()
            ->get();
    }

    public function getAllPaymentMethods(): Collection
    {
        return BillingPaymentMethod::ordered()->get();
    }

    public function createPaymentMethod(array $data): BillingPaymentMethod
    {
        $data = $this->handleQrImage($data);

        if (isset($data['is_default']) && $data['is_default']) {
            BillingPaymentMethod::where('is_default', true)->update(['is_default' => false]);
        }

        return BillingPaymentMethod::create($data);
    }

    public function updatePaymentMethod(BillingPaymentMethod $method, array $data): BillingPaymentMethod
    {
        $data = $this->handleQrImage($data, $method);

        if (isset($data['is_default']) && $data['is_default']) {
            BillingPaymentMethod::where('is_default', true)->where('id', '!=', $method->id)->update(['is_default' => false]);
        }

        $method->update($data);
        return $method->fresh();
    }

    private function handleQrImage(array $data, ?BillingPaymentMethod $method = null): array
    {
        if (array_key_exists('qr_image', $data) && $data['qr_image'] instanceof UploadedFile) {
            if ($method && $method->qr_image) {
                $this->imageService->delete($method->qr_image);
            }
            $data['qr_image'] = $this->imageService->upload($data['qr_image'], 'billing-payment-methods');
        } elseif (!array_key_exists('qr_image', $data) && $method) {
            $data['qr_image'] = $method->qr_image;
        } elseif (array_key_exists('qr_image', $data) && is_null($data['qr_image']) && $method) {
            $data['qr_image'] = $method->qr_image;
        } elseif (!array_key_exists('qr_image', $data) && !$method) {
            $data['qr_image'] = null;
        }
        return $data;
    }

    public function deletePaymentMethod(BillingPaymentMethod $method): bool
    {
        $this->imageService->delete($method->qr_image);
        return $method->delete();
    }

    public function toggleActive(BillingPaymentMethod $method): BillingPaymentMethod
    {
        $method->update([
            'is_active' => !$method->is_active,
        ]);
        return $method->fresh();
    }
}
