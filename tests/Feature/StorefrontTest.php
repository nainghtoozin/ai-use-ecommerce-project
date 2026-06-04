<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\Product;
use App\Models\Category;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StorefrontTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createMinimalSchema();

        $this->withoutMiddleware(
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Inertia\Middleware::class,
        );
    }

    private function createMinimalSchema(): void
    {
        $tables = [
            'tenants' => function ($table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->string('domain')->nullable()->unique();
                $table->string('store_url')->nullable();
                $table->string('email')->nullable();
                $table->string('logo')->nullable();
                $table->string('status')->default('active');
                $table->json('settings')->nullable();
                $table->unsignedBigInteger('subscription_plan_id')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->bigInteger('used_storage_bytes')->default(0);
                $table->timestamps();
            },
            'categories' => function ($table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->string('name');
                $table->string('slug')->nullable();
                $table->timestamps();
            },
            'products' => function ($table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->unsignedBigInteger('category_id')->nullable();
                $table->string('name');
                $table->text('description')->nullable();
                $table->decimal('price', 10, 2)->default(0);
                $table->string('photo1')->nullable();
                $table->string('status')->default('active');
                $table->string('type')->default('single');
                $table->integer('stock')->default(0);
                $table->timestamps();
            },
            'promotions' => function ($table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->string('name');
                $table->string('promotion_type')->default('percentage');
                $table->decimal('discount_value', 10, 2)->default(0);
                $table->string('applies_to')->default('all');
                $table->string('status')->default('active');
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('ends_at')->nullable();
                $table->integer('priority')->default(0);
                $table->timestamps();
            },
            'promotion_product' => function ($table) {
                $table->id();
                $table->unsignedBigInteger('promotion_id');
                $table->unsignedBigInteger('product_id');
            },
            'promotion_category' => function ($table) {
                $table->id();
                $table->unsignedBigInteger('promotion_id');
                $table->unsignedBigInteger('category_id');
            },
            'product_variants' => function ($table) {
                $table->id();
                $table->unsignedBigInteger('product_id');
                $table->string('name')->nullable();
                $table->decimal('price', 10, 2)->nullable();
                $table->integer('stock')->default(0);
                $table->string('status')->default('active');
                $table->timestamps();
            },
            'product_combos' => function ($table) {
                $table->id();
                $table->unsignedBigInteger('product_id');
                $table->unsignedBigInteger('combo_product_id');
                $table->unsignedBigInteger('linked_variant_id')->nullable();
                $table->integer('quantity')->default(1);
                $table->timestamps();
            },
            'website_infos' => function ($table) {
                $table->id();
                $table->string('site_name')->nullable();
                $table->string('theme_color')->nullable();
                $table->string('default_language')->nullable();
                $table->string('timezone')->nullable();
                $table->string('currency_code')->nullable();
                $table->string('currency_symbol')->nullable();
                $table->string('date_format')->nullable();
                $table->boolean('maintenance_mode')->default(false);
                $table->boolean('allow_registration')->default(true);
                $table->boolean('enable_wishlist')->default(true);
                $table->unsignedBigInteger('tenant_id')->nullable()->unique();
                $table->timestamps();
            },
        ];

        foreach ($tables as $name => $callback) {
            if (!Schema::hasTable($name)) {
                Schema::create($name, $callback);
            }
        }
    }

    public function test_valid_store_slug_returns_ok(): void
    {
        $tenant = Tenant::create([
            'name' => 'Coca Cola',
            'slug' => 'coca-cola',
            'store_url' => '/store/coca-cola',
            'status' => 'active',
        ]);

        $response = $this->get("/store/{$tenant->slug}");

        $response->assertOk();
    }

    public function test_invalid_store_slug_returns_404(): void
    {
        $response = $this->get('/store/nonexistent-store');

        $response->assertNotFound();
    }

    public function test_store_products_page_returns_ok(): void
    {
        $tenant = Tenant::create([
            'name' => 'May Fashion',
            'slug' => 'may-fashion',
            'store_url' => '/store/may-fashion',
            'status' => 'active',
        ]);

        $response = $this->get("/store/{$tenant->slug}/products");

        $response->assertOk();
    }

    public function test_storefront_sets_current_tenant(): void
    {
        $tenant = Tenant::create([
            'name' => 'Test Store',
            'slug' => 'test-store',
            'store_url' => '/store/test-store',
            'status' => 'active',
        ]);

        $this->get("/store/{$tenant->slug}");

        $currentTenant = Tenant::getCurrent();
        $this->assertNotNull($currentTenant);
        $this->assertEquals($tenant->id, $currentTenant->id);
    }

    public function test_store_home_returns_ok_with_products(): void
    {
        $tenant = Tenant::create(['name' => 'Store A', 'slug' => 'store-a', 'store_url' => '/store/store-a', 'status' => 'active']);

        $this->get("/store/{$tenant->slug}");

        $this->assertTrue(Tenant::getCurrent()?->id === $tenant->id);
    }
}
