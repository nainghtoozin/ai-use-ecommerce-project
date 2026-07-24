<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\Warehouse;
use Illuminate\Validation\Rule;

class WarehouseService
{
    public function list(int $perPage = 15)
    {
        return Warehouse::forCurrentTenant()
            ->latest()
            ->paginate($perPage);
    }

    public function search(string $query, int $perPage = 15)
    {
        return Warehouse::forCurrentTenant()
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('code', 'like', "%{$query}%");
            })
            ->latest()
            ->paginate($perPage);
    }

    public function create(array $data): Warehouse
    {
        return Warehouse::create($this->prepareData($data));
    }

    public function update(Warehouse $warehouse, array $data): Warehouse
    {
        $data = $this->prepareData($data, $warehouse);

        $warehouse->update($data);

        return $warehouse->fresh();
    }

    public function delete(Warehouse $warehouse): ?bool
    {
        if ($warehouse->is_default) {
            throw new \RuntimeException('Cannot delete the default warehouse. Set another warehouse as default first.');
        }

        return $warehouse->delete();
    }

    public function rules(?Warehouse $warehouse = null): array
    {
        $tenantId = tenant()?->id;

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('warehouses', 'name')
                    ->where('tenant_id', $tenantId)
                    ->ignore($warehouse?->id),
            ],
            'code' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('warehouses', 'code')
                    ->where('tenant_id', $tenantId)
                    ->ignore($warehouse?->id),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_default' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'address' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function getDefaultWarehouse(): ?Warehouse
    {
        return Warehouse::forCurrentTenant()->default()->first();
    }

    public function ensureDefaultWarehouseExists(): Warehouse
    {
        $default = $this->getDefaultWarehouse();

        if ($default) {
            return $default;
        }

        $first = Warehouse::forCurrentTenant()->first();

        if ($first) {
            $first->update(['is_default' => true]);
            return $first->fresh();
        }

        return $this->create([
            'name' => 'Main Warehouse',
            'code' => 'MAIN',
            'description' => 'Default warehouse created automatically.',
            'is_default' => true,
            'is_active' => true,
        ]);
    }

    public function getActiveWarehouses()
    {
        return Warehouse::forCurrentTenant()->active()->orderBy('name')->get();
    }

    private function prepareData(array $data, ?Warehouse $warehouse = null): array
    {
        $tenantId = tenant()?->id;
        $isFirst = !Warehouse::forCurrentTenant()->exists();

        if (!isset($data['is_default'])) {
            $data['is_default'] = $isFirst;
        }

        if ($data['is_default'] ?? false) {
            Warehouse::forCurrentTenant()
                ->where('id', '!=', $warehouse?->id)
                ->update(['is_default' => false]);
        }

        return $data;
    }
}
