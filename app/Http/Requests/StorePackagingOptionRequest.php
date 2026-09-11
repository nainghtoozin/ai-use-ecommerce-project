<?php

namespace App\Http\Requests;

use App\Services\PackagingService;
use Illuminate\Foundation\Http\FormRequest;

class StorePackagingOptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return app(PackagingService::class)->rules();
    }
}
