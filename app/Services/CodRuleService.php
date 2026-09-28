<?php

namespace App\Services;

use App\Models\City;
use App\Models\CodRule;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CodRuleService
{
    public function list(int $perPage = 15)
    {
        return CodRule::forCurrentTenant()
            ->orderBy('is_active', 'desc')
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function search(string $query, int $perPage = 15)
    {
        return CodRule::forCurrentTenant()
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%");
            })
            ->orderBy('is_active', 'desc')
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function create(array $data): CodRule
    {
        if (tenant() && CodRule::forCurrentTenant()->exists()) {
            throw ValidationException::withMessages([
                'name' => 'This store already has a COD rule. Edit the existing rule instead.',
            ]);
        }

        $this->normalizeCityIds($data);
        return CodRule::create($this->prepareData($data));
    }

    public function update(CodRule $codRule, array $data): CodRule
    {
        $this->normalizeCityIds($data);
        $codRule->update($this->prepareData($data, $codRule));
        return $codRule->fresh();
    }

    public function delete(CodRule $codRule): ?bool
    {
        return $codRule->delete();
    }

    public function toggleActive(CodRule $codRule): CodRule
    {
        $codRule->update(['is_active' => !$codRule->is_active]);
        return $codRule->fresh();
    }

    public function rules(?CodRule $codRule = null): array
    {
        $tenantId = tenant()?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'min_order_amount' => ['nullable', 'numeric', 'min:0'],
            'max_order_amount' => ['nullable', 'numeric', 'min:0', 'gte:min_order_amount'],
            'allowed_city_ids' => ['nullable', 'array'],
            'allowed_city_ids.*' => ['integer', Rule::exists('cities', 'id')->where('tenant_id', $tenantId)],
            'excluded_city_ids' => ['nullable', 'array'],
            'excluded_city_ids.*' => ['integer', Rule::exists('cities', 'id')->where('tenant_id', $tenantId)],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function validateCityRestrictions(array $data): ?array
    {
        $allowed = $data['allowed_city_ids'] ?? [];
        $excluded = $data['excluded_city_ids'] ?? [];

        if (!empty($allowed) && !empty($excluded)) {
            $overlap = array_intersect($allowed, $excluded);
            if (!empty($overlap)) {
                return ['A city cannot be both allowed and excluded.'];
            }
        }

        return null;
    }

    public function getActiveCities(): \Illuminate\Database\Eloquent\Collection
    {
        return City::active()->orderBy('name')->get();
    }

    private function normalizeCityIds(array &$data): void
    {
        if (isset($data['allowed_city_ids']) && is_array($data['allowed_city_ids'])) {
            $data['allowed_city_ids'] = array_values(array_unique(array_filter($data['allowed_city_ids'])));
        }
        if (isset($data['excluded_city_ids']) && is_array($data['excluded_city_ids'])) {
            $data['excluded_city_ids'] = array_values(array_unique(array_filter($data['excluded_city_ids'])));
        }
    }

    private function prepareData(array $data, ?CodRule $codRule = null): array
    {
        if (!isset($data['is_active'])) {
            $data['is_active'] = true;
        }

        return $data;
    }
}
