<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\StoreCategory;
use App\Models\Tenant;
use Database\Seeders\StoreCategorySeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MarketplaceStoreCategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_categories_table_is_platform_level(): void
    {
        $this->assertTrue(Schema::hasTable('store_categories'));
        $this->assertTrue(Schema::hasColumn('tenants', 'store_category_id'));

        $this->assertNotContains(
            \App\Models\Traits\TenantAware::class,
            class_uses_recursive(StoreCategory::class)
        );
        $this->assertContains(
            \App\Models\Traits\TenantAware::class,
            class_uses_recursive(Category::class)
        );
    }

    public function test_store_category_slug_is_globally_unique(): void
    {
        StoreCategory::create(['name' => 'Electronics', 'slug' => 'electronics']);

        $this->expectException(QueryException::class);

        StoreCategory::create(['name' => 'Electronics Again', 'slug' => 'electronics']);
    }

    public function test_store_category_seeder_is_idempotent_with_stable_slugs(): void
    {
        Artisan::call('db:seed', ['--class' => StoreCategorySeeder::class]);
        $first = StoreCategory::orderBy('id')->pluck('id', 'slug')->toArray();

        Artisan::call('db:seed', ['--class' => StoreCategorySeeder::class]);
        $second = StoreCategory::orderBy('id')->pluck('id', 'slug')->toArray();

        $this->assertNotEmpty($first);
        $this->assertSame($first, $second);
        $this->assertSame(count($first), StoreCategory::count());
    }

    public function test_tenant_store_category_assignment_is_optional_and_preserved(): void
    {
        $category = StoreCategory::create(['name' => 'Fashion', 'slug' => 'fashion']);

        $tenant = Tenant::create([
            'name' => 'Unassigned Shop',
            'slug' => 'unassigned-shop',
            'status' => 'trialing',
            'activated_at' => now(),
        ]);
        $this->assertNull($tenant->store_category_id);

        $tenant->update(['store_category_id' => $category->id]);

        $this->assertTrue($tenant->fresh()->storeCategory->is($category));
        $this->assertTrue($category->tenants()->whereKey($tenant->id)->exists());
    }

    public function test_marketplace_dropdown_has_no_duplicates_across_tenants(): void
    {
        $category = StoreCategory::create(['name' => 'Electronics', 'slug' => 'electronics']);

        foreach ([['A', 'dup-a'], ['B', 'dup-b'], ['C', 'dup-c']] as [$suffix, $slug]) {
            $tenant = Tenant::create([
                'name' => "Dup Shop {$suffix}",
                'slug' => $slug,
                'status' => 'active',
                'activated_at' => now(),
                'store_category_id' => $category->id,
            ]);

            Tenant::setCurrent($tenant);
            try {
                Category::create(['name' => 'Electronics', 'slug' => "electronics-{$slug}"]);
            } finally {
                app()->forgetInstance('current.tenant');
            }
        }

        $response = $this->get('/marketplace');
        $response->assertOk();

        $names = array_column($response->viewData('page')['props']['categories'], 'name');

        $this->assertSame(['Electronics'], $names);
    }

    public function test_marketplace_filter_uses_store_category_not_product_category(): void
    {
        $storeCat = StoreCategory::create(['name' => 'Fashion', 'slug' => 'fashion']);

        $assigned = Tenant::create([
            'name' => 'Assigned Shop', 'slug' => 'assigned-shop',
            'status' => 'active', 'activated_at' => now(),
            'store_category_id' => $storeCat->id,
        ]);
        Tenant::create([
            'name' => 'Unassigned Shop', 'slug' => 'unassigned-shop-2',
            'status' => 'active', 'activated_at' => now(),
        ]);

        Tenant::setCurrent($assigned);
        try {
            $productCat = Category::create(['name' => 'Fashion', 'slug' => 'fashion-prod']);
        } finally {
            app()->forgetInstance('current.tenant');
        }

        $byStoreCat = array_column(
            $this->storeRows($this->get("/marketplace?category_id={$storeCat->id}")),
            'slug'
        );
        $this->assertSame(['assigned-shop'], $byStoreCat);

        $byProductCat = array_column(
            $this->storeRows($this->get("/marketplace?category_id={$productCat->id}")),
            'slug'
        );
        $this->assertSame([], $byProductCat);
    }

    /** @return array<int, array<string, mixed>> */
    private function storeRows($response): array
    {
        $stores = $response->viewData('page')['props']['stores'];

        if (is_array($stores)) {
            return array_values($stores['data'] ?? []);
        }

        return $stores instanceof \Traversable
            ? iterator_to_array($stores)
            : $stores->items();
    }
}
