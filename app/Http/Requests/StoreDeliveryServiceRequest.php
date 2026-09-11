<?php

namespace App\Http\Requests;

use App\Services\DeliveryServiceService;
use Illuminate\Foundation\Http\FormRequest;

class StoreDeliveryServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return app(DeliveryServiceService::class)->rules();
    }
}
