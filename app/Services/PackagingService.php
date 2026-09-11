<?php

namespace App\Services;

use App\Models\PackagingOption;
use Illuminate\Validation\Rule;

class PackagingService
{
    public function list(int $perPage = 15)
    {
        return PackagingOption::forCurrentTenant()
            ->ordered()
            ->paginate($perPage);
    }

    public function search(string $query, int $perPage = 15)
    {
        return PackagingOption::forCurrentTenant()
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('code', 'like', "%{$query}%");
            })
            ->ordered()
            ->paginate($perPage);
    }

    public function create(array $data): PackagingOption
    {
        return PackagingOption::create($this->prepareData($data));
    }

    public function update(PackagingOption $packagingOption, array $data): PackagingOption
    {
        $data = $this->prepareData($data, $packagingOption);

        $packagingOption->update($data);

        return $packagingOption->fresh();
    }

    public function delete(PackagingOption $packagingOption): ?bool
    {
        $inUse = $packagingOption->orders()->exists();
        if ($inUse) {
            $packagingOption->update(['is_active' => false]);
            return null;
        }

        return $packagingOption->delete();
    }

    public function toggleActive(PackagingOption $packagingOption): PackagingOption
    {
        $packagingOption->update(['is_active' => !$packagingOption->is_active]);
        return $packagingOption->fresh();
    }

    public function rules(?PackagingOption $packagingOption = null): array
    {
        $tenantId = tenant()?->id;

        return [
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('packaging_options', 'code')
                    ->where('tenant_id', $tenantId)
                    ->ignore($packagingOption?->id),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'fee' => ['required', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function getActivePackagingOptions(): \Illuminate\Database\Eloquent\Collection
    {
        return PackagingOption::forCurrentTenant()
            ->active()
            ->ordered()
            ->get();
    }

    private function prepareData(array $data, ?PackagingOption $packagingOption = null): array
    {
        if (!isset($data['is_active'])) {
            $data['is_active'] = true;
        }

        if (!isset($data['sort_order'])) {
            $data['sort_order'] = 0;
        }

        return $data;
    }
}
