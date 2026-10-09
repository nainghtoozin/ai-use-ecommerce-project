<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontCartVariantImageTest extends TestCase
{
    use RefreshDatabase;

    public function test_variable_cart_item_uses_selected_variant_image(): void
    {
        [$tenant, $product, $variant] = $this->seedVariableProduct();

        $this->withSession(['cart' => [
            "p{$product->id}_v{$variant->id}" => [
                'product_id' => $product->id,
                'variant_id' => $variant->id,
                'quantity' => 1,
            ],
        ]]);

        $response = $this->get("/store/{$tenant->slug}/cart");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Storefront/Cart')
            ->where('cartItems.0.photo1_url', fn ($url) => is_string($url) && str_contains($url, 'variant-black.jpg'))
            ->where('cartItems.0.variant_name', 'Black / 128GB')
        );
    }

    public function test_variable_cart_item_falls_back_to_parent_when_variant_has_no_image(): void
    {
        [$tenant, $product, $variant] = $this->seedVariableProduct(null);

        $this->withSession(['cart' => [
            "p{$product->id}_v{$variant->id}" => [
                'product_id' => $product->id,
                'variant_id' => $variant->id,
                'quantity' => 1,
            ],
        ]]);

        $response = $this->get("/store/{$tenant->slug}/cart");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Storefront/Cart')
            ->where('cartItems.0.photo1_url', fn ($url) => is_string($url) && str_contains($url, 'parent-primary.jpg'))
            ->where('cartItems.0.variant_image_url', null)
        );
    }

    public function test_single_cart_item_keeps_parent_image(): void
    {
        $tenant = Tenant::create(['name' => 'Cart Store', 'slug' => 'cart-store-' . uniqid(), 'status' => 'active']);
        $category = Category::withoutTenantScope()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Simple',
            'slug' => 'simple-' . uniqid(),
        ]);

        $product = new Product([
            'name' => 'Single Widget',
            'type' => 'single',
            'price' => 20,
            'base_price' => 20,
            'category_id' => $category->id,
            'status' => Product::STATUS_ACTIVE,
            'photo1' => 'products/parent-single.jpg',
        ]);
        $product->tenant_id = $tenant->id;
        $product->save();

        $this->withSession(['cart' => [
            "p{$product->id}_v0" => [
                'product_id' => $product->id,
                'variant_id' => null,
                'quantity' => 1,
            ],
        ]]);

        $response = $this->get("/store/{$tenant->slug}/cart");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Storefront/Cart')
            ->where('cartItems.0.photo1_url', fn ($url) => is_string($url) && str_contains($url, 'parent-single.jpg'))
        );
    }

    private function seedVariableProduct(?string $variantImage = 'products/variant-black.jpg'): array
    {
        $tenant = Tenant::create(['name' => 'Cart Store', 'slug' => 'cart-store-' . uniqid(), 'status' => 'active']);
        $category = Category::withoutTenantScope()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Phones',
            'slug' => 'phones-' . uniqid(),
        ]);

        $product = new Product([
            'name' => 'Smartphone',
            'type' => 'variable',
            'price' => 100,
            'base_price' => 100,
            'category_id' => $category->id,
            'status' => Product::STATUS_ACTIVE,
            'photo1' => 'products/parent-primary.jpg',
        ]);
        $product->tenant_id = $tenant->id;
        $product->save();

        $variant = new ProductVariant([
            'product_id' => $product->id,
            'sku' => 'PHONE-BLK-128',
            'price' => 120,
            'stock' => 5,
            'status' => ProductVariant::STATUS_ACTIVE,
            'attributes' => ['Color' => 'Black', 'Storage' => '128GB'],
            'image' => $variantImage,
        ]);
        $variant->tenant_id = $tenant->id;
        $variant->save();

        return [$tenant, $product, $variant];
    }
}
