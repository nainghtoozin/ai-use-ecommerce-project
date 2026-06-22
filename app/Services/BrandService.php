<?php

namespace App\Services;

use App\Models\Brand;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class BrandService
{
    public function list()
    {
        return Brand::forCurrentTenant()->latest()->paginate(10);
    }

    public function search(string $query)
    {
        return Brand::forCurrentTenant()
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('slug', 'like', "%{$query}%");
            })
            ->latest()
            ->paginate(10);
    }

    public function create(array $data): Brand
    {
        if (empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        return Brand::create($data);
    }

    public function update(Brand $brand, array $data): Brand
    {
        if (empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $brand->update($data);
        return $brand->fresh();
    }

    public function delete(Brand $brand): void
    {
        $brand->delete();
    }

    public function rules(?Brand $brand = null): array
    {
        $tenantId = tenant()?->id;
        return [
            'name' => [
                'required',
                'max:255',
                Rule::unique('brands', 'name')
                    ->where('tenant_id', $tenantId)
                    ->ignore($brand?->id),
            ],
            'slug' => [
                'nullable',
                'max:255',
                Rule::unique('brands', 'slug')
                    ->where('tenant_id', $tenantId)
                    ->ignore($brand?->id),
            ],
            'description' => 'nullable|string',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'is_active' => 'boolean',
        ];
    }
}
