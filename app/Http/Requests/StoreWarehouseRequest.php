<?php

namespace App\Http\Requests;

use App\Services\WarehouseService;
use Illuminate\Foundation\Http\FormRequest;

class StoreWarehouseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return app(WarehouseService::class)->rules();
    }
}
