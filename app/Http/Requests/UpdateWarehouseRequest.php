<?php

namespace App\Http\Requests;

use App\Services\WarehouseService;
use Illuminate\Foundation\Http\FormRequest;

class UpdateWarehouseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $warehouse = $this->route('warehouse');
        return app(WarehouseService::class)->rules($warehouse);
    }
}
