<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\CodRule;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Category;
use App\Models\StockMovement;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentMethodTenantTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function explicit_tenant_id_is_not_dropped_on_create(): void
    {
        $tenantA = Tenant::create(['name' => 'A', 'slug' => 'pm-a', 'store_url' => '/store/pm-a', 'status' => 'active']);
        $tenantB = Tenant::create(['name' => 'B', 'slug' => 'pm-b', 'store_url' => '/store/pm-b', 'status' => 'active']);
        Tenant::setCurrent($tenantA);

        $method = PaymentMethod::create([
            'tenant_id' => $tenantB->id,
            'name' => 'Foreign Method',
            'type' => 'cod',
            'is_active' => true,
        ]);

        $this->assertEquals($tenantB->id, (int) $method->fresh()->tenant_id);
    }

    /** @test */
    public function tenant_autofill_still_works_when_id_omitted(): void
    {
        $tenant = Tenant::create(['name' => 'A', 'slug' => 'pm-auto', 'store_url' => '/store/pm-auto', 'status' => 'active']);
        Tenant::setCurrent($tenant);

        $method = PaymentMethod::create([
            'name' => 'Auto Method',
            'type' => 'bank_transfer',
            'is_active' => true,
        ]);

        $this->assertEquals($tenant->id, (int) $method->fresh()->tenant_id);
    }

    /** @test */
    public function legacy_checkout_exposes_per_city_cod_availability(): void
    {
        $tenant = Tenant::create(['name' => 'Shop', 'slug' => 'pm-shop', 'store_url' => '/store/pm-shop', 'status' => 'active']);
        Tenant::setCurrent($tenant);

        $yangon = City::create(['name' => 'Yangon', 'is_active' => true]);
        $mandalay = City::create(['name' => 'Mandalay', 'is_active' => true]);

        PaymentMethod::create(['name' => 'Cash on Delivery', 'type' => 'cod', 'is_active' => true]);
        CodRule::create([
            'tenant_id' => $tenant->id, 'name' => 'Yangon Only',
            'allowed_city_ids' => [$yangon->id], 'is_active' => true,
        ]);

        $category = Category::create(['name' => 'Cat', 'slug' => 'cat']);
        $product = Product::create([
            'name' => 'Widget', 'type' => 'single', 'price' => 5000,
            'stock' => 50, 'status' => 'active', 'category_id' => $category->id,
        ]);
        StockMovement::create([
            'product_id' => $product->id, 'product_variant_id' => null,
            'type' => StockMovement::TYPE_OPENING_STOCK, 'quantity' => 50,
        ]);
        $user = User::create([
            'tenant_id' => $tenant->id, 'name' => 'Buyer',
            'email' => 'buyer-pm@test.com', 'password' => bcrypt('password'),
            'status' => 'active', 'allow_cod' => true,
        ]);

        $response = $this->actingAs($user)
            ->withSession(['cart' => ['k1' => ['product_id' => $product->id, 'quantity' => 1]]])
            ->get('/checkout');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('codAvailability.' . $yangon->id, true)
            ->where('codAvailability.' . $mandalay->id, false));
    }
}
