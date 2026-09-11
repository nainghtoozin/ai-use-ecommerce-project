<?php

namespace App\Http\Requests;

use App\Services\CodRuleService;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCodRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $codRule = $this->route('cod_rule');
        return app(CodRuleService::class)->rules($codRule);
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $data = $this->validated();
            $service = app(CodRuleService::class);
            $conflict = $service->validateCityRestrictions($data);
            if ($conflict) {
                $validator->errors()->add('city_restrictions', $conflict[0]);
            }
        });
    }
}
