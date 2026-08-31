<?php

namespace App\Services;

use App\Models\Brand;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class BrandService
{
    public function list(array $filters = [])
    {
        $query = Brand::forCurrentTenant()->withCount('products');

        if (!empty($filters['filter_active'])) {
            if ($filters['filter_active'] === 'active') {
                $query->where('is_active', true);
            } elseif ($filters['filter_active'] === 'inactive') {
                $query->where('is_active', false);
            }
        }

        if (!empty($filters['filter_featured'])) {
            if ($filters['filter_featured'] === 'featured') {
                $query->where('featured', true);
            } elseif ($filters['filter_featured'] === 'not_featured') {
                $query->where('featured', false);
            }
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        $brands = $query->sorted()->paginate(15);

        return $brands;
    }

    public function search(string $query)
    {
        return $this->list(['search' => $query]);
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
            'banner' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
            'featured' => 'boolean',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ];
    }
}
