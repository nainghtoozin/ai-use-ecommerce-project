<?php

namespace App\Http\Requests;

use App\Services\DeliveryServiceService;
use Illuminate\Foundation\Http\FormRequest;

class UpdateDeliveryServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $deliveryService = $this->route('delivery_service');
        return app(DeliveryServiceService::class)->rules($deliveryService);
    }
}
