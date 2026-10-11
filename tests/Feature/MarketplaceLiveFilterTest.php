<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\StoreCategory;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketplaceLiveFilterTest extends TestCase
{
    use RefreshDatabase;

    private function activatedTenant(string $name, string $slug, ?int $storeCategoryId = null): Tenant
    {
        return Tenant::create([
            'name' => $name,
            'slug' => $slug,
            'status' => 'active',
            'activated_at' => now(),
            'store_category_id' => $storeCategoryId,
        ]);
    }

    private function makeCategory(Tenant $tenant, string $name, string $slug): Category
    {
        Tenant::setCurrent($tenant);

        try {
            return Category::create(['name' => $name, 'slug' => $slug]);
        } finally {
            app()->forgetInstance('current.tenant');
        }
    }

    private function makeProduct(Tenant $tenant, string $name, string $slug, string $status, int $categoryId): Product
    {
        Tenant::setCurrent($tenant);

        try {
            return Product::create([
                'name' => $name,
                'slug' => $slug,
                'price' => 10000,
                'category_id' => $categoryId,
                'status' => $status,
            ]);
        } finally {
            app()->forgetInstance('current.tenant');
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function storeRows($response): array
    {
        $stores = $response->viewData('page')['props']['stores'];

        if (is_array($stores)) {
            return array_values($stores['data'] ?? []);
        }

        return $stores instanceof \Traversable ? iterator_to_array($stores) : $stores->items();
    }

    /** @return array<string, mixed> */
    private function storesProp($response): array
    {
        $stores = $response->viewData('page')['props']['stores'];

        return is_array($stores) ? $stores : $stores->toArray();
    }

    public function test_search_matches_name_and_updates_results(): void
    {
        $this->activatedTenant('Alpha Boutique', 'alpha-boutique');
        $this->activatedTenant('Beta Outlet', 'beta-outlet');

        $slugs = array_column($this->storeRows($this->get('/marketplace?search=alpha')), 'slug');

        $this->assertSame(['alpha-boutique'], $slugs);
    }

    public function test_category_and_sort_work_independently_and_together(): void
    {
        $electronics = StoreCategory::create(['name' => 'Electronics', 'slug' => 'electronics']);
        $fashion = StoreCategory::create(['name' => 'Fashion', 'slug' => 'fashion']);

        $this->activatedTenant('Zeta Tech', 'zeta-tech', $electronics->id);
        $this->activatedTenant('Alpha Wear', 'alpha-wear', $fashion->id);
        $this->activatedTenant('Beta Tech', 'beta-tech', $electronics->id);

        $byCategory = array_column(
            $this->storeRows($this->get("/marketplace?category_id={$electronics->id}")),
            'slug'
        );
        $this->assertEqualsCanonicalizing(['zeta-tech', 'beta-tech'], $byCategory);

        $bySort = array_column($this->storeRows($this->get('/marketplace?sort=name_az')), 'slug');
        $this->assertSame(['alpha-wear', 'beta-tech', 'zeta-tech'], $bySort);

        $combined = array_column(
            $this->storeRows($this->get("/marketplace?category_id={$electronics->id}&sort=name_az")),
            'slug'
        );
        $this->assertSame(['beta-tech', 'zeta-tech'], $combined);
    }

    public function test_query_parameters_are_preserved_in_pagination_links(): void
    {
        $category = StoreCategory::create(['name' => 'Toys', 'slug' => 'toys']);
        for ($i = 1; $i <= 15; $i++) {
            $this->activatedTenant("Toy Shop {$i}", "toy-shop-{$i}", $category->id);
        }

        $stores = $this->storesProp(
            $this->get("/marketplace?category_id={$category->id}&sort=name_az&search=Toy")
        );

        $this->assertSame(1, $stores['current_page']);
        $this->assertGreaterThan(1, $stores['last_page']);

        $links = collect($stores['links'])->pluck('url')->filter()->implode(' ');
        $this->assertStringContainsString("category_id={$category->id}", $links);
        $this->assertStringContainsString('sort=name_az', $links);
        $this->assertStringContainsString('search=Toy', $links);
    }

    public function test_filter_change_starts_at_first_page(): void
    {
        $category = StoreCategory::create(['name' => 'Books', 'slug' => 'books']);
        for ($i = 1; $i <= 13; $i++) {
            $this->activatedTenant("Book Shop {$i}", "book-shop-{$i}", $category->id);
        }

        $this->assertSame(1, $this->storesProp($this->get('/marketplace'))['current_page']);
        $this->assertSame(2, $this->storesProp($this->get('/marketplace?page=2'))['current_page']);

        $filtered = $this->storesProp($this->get("/marketplace?category_id={$category->id}&sort=name_az"));
        $this->assertSame(1, $filtered['current_page']);
    }

    public function test_default_listing_restored_when_no_filters(): void
    {
        $category = StoreCategory::create(['name' => 'Garden', 'slug' => 'garden']);
        $this->activatedTenant('Garden One', 'garden-one', $category->id);
        $this->activatedTenant('Garden Two', 'garden-two');
        $this->activatedTenant('Garden Three', 'garden-three');

        $slugs = array_column($this->storeRows($this->get('/marketplace')), 'slug');

        $this->assertCount(3, $slugs);
        $this->assertEqualsCanonicalizing(['garden-one', 'garden-two', 'garden-three'], $slugs);
    }

    public function test_product_counts_and_tenant_isolation_remain_correct(): void
    {
        $catA = $this->activatedTenant('Count Shop A', 'count-shop-a');
        $catB = $this->activatedTenant('Count Shop B', 'count-shop-b');

        $productCatA = $this->makeCategory($catA, 'A Cat', 'a-cat');
        $productCatB = $this->makeCategory($catB, 'B Cat', 'b-cat');

        $this->makeProduct($catA, 'A1', 'a-1', Product::STATUS_ACTIVE, $productCatA->id);
        $this->makeProduct($catA, 'A2', 'a-2', Product::STATUS_ACTIVE, $productCatA->id);
        $this->makeProduct($catA, 'A3 Draft', 'a-3', Product::STATUS_INACTIVE, $productCatA->id);
        $this->makeProduct($catB, 'B1', 'b-1', Product::STATUS_ACTIVE, $productCatB->id);

        $counts = array_column($this->storeRows($this->get('/marketplace')), 'products_count', 'slug');

        $this->assertSame(2, $counts['count-shop-a']);
        $this->assertSame(1, $counts['count-shop-b']);
    }

    public function test_directory_wires_live_filtering_contract(): void
    {
        $source = (string) file_get_contents(base_path('resources/js/Pages/Public/StoreDirectory.jsx'));

        $this->assertStringContainsString('SEARCH_DEBOUNCE_MS = 300', $source);
        $this->assertStringContainsString("router.get('/marketplace'", $source);
        $this->assertStringContainsString("only: ['stores', 'filters']", $source);
        $this->assertStringContainsString('preserveState: true', $source);
        $this->assertStringContainsString('replace,', $source);
        $this->assertStringContainsString('clear_filters', $source);
        $this->assertStringContainsString('htmlFor="marketplace-search"', $source);
        $this->assertStringContainsString('htmlFor="marketplace-category"', $source);
        $this->assertStringContainsString('htmlFor="marketplace-sort"', $source);
    }

    public function test_directory_wires_mobile_collapsible_filters(): void
    {
        $source = (string) file_get_contents(base_path('resources/js/Pages/Public/StoreDirectory.jsx'));

        $this->assertStringContainsString('aria-expanded={filtersOpen}', $source);
        $this->assertStringContainsString('aria-controls="marketplace-filter-panel"', $source);
        $this->assertStringContainsString('id="marketplace-filter-panel"', $source);
        $this->assertStringContainsString('SlidersHorizontal', $source);
        $this->assertStringContainsString("t('landing.marketplace.filters')", $source);
        $this->assertStringContainsString('activeFilterCount', $source);
        $this->assertStringContainsString('filterSummary', $source);
    }

    public function test_marketplace_labels_exist_in_both_locales(): void
    {
        foreach (['en', 'my'] as $locale) {
            $messages = require base_path("lang/{$locale}/landing.php");
            $marketplace = $messages['marketplace'];

            foreach (['heading', 'intro', 'filters', 'clear_filters', 'stores_count', 'updating', 'load_error', 'retry'] as $key) {
                $this->assertArrayHasKey($key, $marketplace, "Missing {$locale}.landing.marketplace.{$key}");
                $this->assertNotSame('', $marketplace[$key]);
            }
        }
    }
}
