<?php

namespace App\Services\ImportExport;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Unit;
use Illuminate\Support\Facades\DB;

class MasterDataResolver
{
    private int $tenantId;
    private array $categoryCache = [];
    private array $brandCache = [];
    private array $unitCache = [];

    public function __construct(int $tenantId)
    {
        $this->tenantId = $tenantId;
        $this->warmCaches();
    }

    private function warmCaches(): void
    {
        Category::withoutTenantScope()
            ->where('tenant_id', $this->tenantId)
            ->get()
            ->each(fn($c) => $this->categoryCache[strtolower(trim($c->name))] = $c);

        Brand::withoutTenantScope()
            ->where('tenant_id', $this->tenantId)
            ->get()
            ->each(fn($b) => $this->brandCache[strtolower(trim($b->name))] = $b);

        Unit::withoutTenantScope()
            ->where('tenant_id', $this->tenantId)
            ->get()
            ->each(fn($u) => $this->unitCache[strtolower(trim($u->name))] = $u);
    }

    public function resolveCategory(string $name): ?Category
    {
        $key = strtolower(trim($name));
        return $this->categoryCache[$key] ?? null;
    }

    public function resolveOrCreateCategory(string $name): Category
    {
        $key = strtolower(trim($name));
        if (isset($this->categoryCache[$key])) {
            return $this->categoryCache[$key];
        }

        $category = Category::create([
            'tenant_id' => $this->tenantId,
            'name' => trim($name),
            'description' => '',
        ]);

        $this->categoryCache[$key] = $category;
        return $category;
    }

    public function resolveBrand(string $name): ?Brand
    {
        $key = strtolower(trim($name));
        return $this->brandCache[$key] ?? null;
    }

    public function resolveOrCreateBrand(string $name): Brand
    {
        $key = strtolower(trim($name));
        if (isset($this->brandCache[$key])) {
            return $this->brandCache[$key];
        }

        $brand = Brand::create([
            'tenant_id' => $this->tenantId,
            'name' => trim($name),
            'slug' => \Illuminate\Support\Str::slug($name) . '-' . \Illuminate\Support\Str::random(6),
            'description' => '',
            'is_active' => true,
        ]);

        $this->brandCache[$key] = $brand;
        return $brand;
    }

    public function resolveUnit(string $name): ?Unit
    {
        $key = strtolower(trim($name));
        return $this->unitCache[$key] ?? null;
    }

    public function getMatchedCategories(): array
    {
        return array_values($this->categoryCache);
    }

    public function getMatchedBrands(): array
    {
        return array_values($this->brandCache);
    }

    public function getMatchedUnits(): array
    {
        return array_values($this->unitCache);
    }

    public function getTenantId(): int
    {
        return $this->tenantId;
    }
}
