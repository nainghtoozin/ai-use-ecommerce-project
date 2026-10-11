<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\StoreCategory;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketplaceStoreDirectoryTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenant(string $name, string $slug, string $status, array $overrides = []): Tenant
    {
        return Tenant::create(array_merge([
            'name' => $name,
            'slug' => $slug,
            'status' => $status,
        ], $overrides));
    }

    private function activate(Tenant $tenant): Tenant
    {
        $tenant->update(['activated_at' => now()]);
        return $tenant->fresh();
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

    private function makeProduct(Tenant $tenant, string $name, string $slug, string $status, ?int $categoryId = null): Product
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
        $page = $response->viewData('page');
        $stores = $page['props']['stores'];

        if (is_array($stores)) {
            return array_values($stores['data'] ?? []);
        }

        return $stores instanceof \Traversable
            ? iterator_to_array($stores)
            : $stores->items();
    }

    public function test_trialing_and_active_stores_appear_without_seeder_data(): void
    {
        $this->activate($this->makeTenant('Trial Shop', 'trial-shop', 'trialing'));
        $this->activate($this->makeTenant('Active Shop', 'active-shop', 'active'));

        $response = $this->get('/marketplace');
        $response->assertOk();

        $slugs = array_column($this->storeRows($response), 'slug');

        $this->assertContains('trial-shop', $slugs);
        $this->assertContains('active-shop', $slugs);
        $this->assertNotContains('default', $slugs);
        $this->assertCount(2, $slugs);
    }

    public function test_product_counts_are_per_tenant_and_only_active_products(): void
    {
        $trial = $this->activate($this->makeTenant('Trial Shop', 'trial-shop', 'trialing'));
        $active = $this->activate($this->makeTenant('Active Shop', 'active-shop', 'active'));
        $suspended = $this->makeTenant('Suspended Shop', 'suspended-shop', 'suspended');
        $suspended->update(['activated_at' => now()]);

        $catTrial = $this->makeCategory($trial, 'Trial Cat', 'trial-cat');
        $catActive = $this->makeCategory($active, 'Active Cat', 'active-cat');
        $catSuspended = $this->makeCategory($suspended, 'Suspended Cat', 'suspended-cat');

        $this->makeProduct($trial, 'T1', 't-1', Product::STATUS_ACTIVE, $catTrial->id);
        $this->makeProduct($trial, 'T2', 't-2', Product::STATUS_ACTIVE, $catTrial->id);
        $this->makeProduct($trial, 'T3 Draft', 't-3', Product::STATUS_INACTIVE, $catTrial->id);
        $this->makeProduct($active, 'A1', 'a-1', Product::STATUS_ACTIVE, $catActive->id);
        $this->makeProduct($suspended, 'S1', 's-1', Product::STATUS_ACTIVE, $catSuspended->id);

        $rows = $this->storeRows($this->get('/marketplace'));
        $counts = array_column($rows, 'products_count', 'slug');

        $this->assertSame(2, $counts['trial-shop']);
        $this->assertSame(1, $counts['active-shop']);
        $this->assertArrayNotHasKey('suspended-shop', $counts);
    }

    public function test_ineligible_stores_are_excluded(): void
    {
        $this->activate($this->makeTenant('Suspended', 'suspended-shop', 'suspended'));
        $this->makeTenant('Pending', 'pending-shop', 'pending');

        $locked = $this->activate($this->makeTenant('Locked Active', 'locked-shop', 'active'));
        $locked->update(['locked_at' => now()]);

        $expiredTrial = $this->activate($this->makeTenant('Old Trial', 'old-trial', 'trialing'));
        $expiredTrial->update(['locked_at' => now()]);

        $this->activate($this->makeTenant('Visible', 'visible-shop', 'active'));

        $slugs = array_column($this->storeRows($this->get('/marketplace')), 'slug');

        $this->assertSame(['visible-shop'], $slugs);
    }

    public function test_zero_product_store_is_included_without_duplicates(): void
    {
        $trial = $this->activate($this->makeTenant('Empty Shop', 'empty-shop', 'trialing'));
        $cat = $this->makeCategory($trial, 'Empty Cat', 'empty-cat');
        $this->makeProduct($trial, 'Only', 'only-1', Product::STATUS_ACTIVE, $cat->id);
        $this->activate($this->makeTenant('Bare Shop', 'bare-shop', 'active'));

        $rows = $this->storeRows($this->get('/marketplace'));
        $slugs = array_column($rows, 'slug');
        $counts = array_column($rows, 'products_count', 'slug');

        $this->assertSame($slugs, array_unique($slugs));
        $this->assertContains('empty-shop', $slugs);
        $this->assertSame(1, array_count_values($slugs)['empty-shop']);
        $this->assertContains('bare-shop', $slugs);
        $this->assertSame(0, $counts['bare-shop']);
    }

    public function test_search_category_sort_and_pagination_still_work(): void
    {
        $alpha = $this->activate($this->makeTenant('Alpha Store', 'alpha-store', 'active'));
        $beta = $this->activate($this->makeTenant('Beta Store', 'beta-store', 'trialing'));

        $catElectronics = StoreCategory::create(['name' => 'Electronics', 'slug' => 'electronics']);
        $catFashion = StoreCategory::create(['name' => 'Fashion', 'slug' => 'fashion']);
        $alpha->update(['store_category_id' => $catElectronics->id]);
        $beta->update(['store_category_id' => $catFashion->id]);

        $catAlpha = $this->makeCategory($alpha, 'Electronics', 'electronics');
        $catBeta = $this->makeCategory($beta, 'Fashion', 'fashion');
        $this->makeProduct($alpha, 'Phone', 'phone-1', Product::STATUS_ACTIVE, $catAlpha->id);
        $this->makeProduct($beta, 'Shirt', 'shirt-1', Product::STATUS_ACTIVE, $catBeta->id);

        $searchSlugs = array_column($this->storeRows($this->get('/marketplace?search=Alpha')), 'slug');
        $this->assertSame(['alpha-store'], $searchSlugs);

        $catSlugs = array_column($this->storeRows($this->get("/marketplace?category_id={$catFashion->id}")), 'slug');
        $this->assertSame(['beta-store'], $catSlugs);

        $sorted = array_column($this->storeRows($this->get('/marketplace?sort=name_az')), 'slug');
        $this->assertSame(['alpha-store', 'beta-store'], $sorted);

        for ($i = 1; $i <= 13; $i++) {
            $this->activate($this->makeTenant("Extra {$i}", "extra-{$i}", 'active'));
        }

        $page1 = $this->get('/marketplace');
        $this->assertCount(12, $this->storeRows($page1));

        $page2Slugs = array_column($this->storeRows($this->get('/marketplace?page=2')), 'slug');
        $this->assertCount(3, $page2Slugs);
    }
}
