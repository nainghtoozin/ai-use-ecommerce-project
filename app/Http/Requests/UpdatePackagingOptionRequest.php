<?php

namespace App\Http\Requests;

use App\Services\PackagingService;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePackagingOptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $packagingOption = $this->route('packaging_option');
        return app(PackagingService::class)->rules($packagingOption);
    }
}
