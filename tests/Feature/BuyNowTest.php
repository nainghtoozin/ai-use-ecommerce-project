<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Category;
use App\Models\StockMovement;
use App\Models\Tenant;
use App\Services\BuyNowService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BuyNowTest extends TestCase
{
    use DatabaseTransactions;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private Product $productA;
    private ProductVariant $variantA;
    private Product $productB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::create(['name' => 'BN Store A', 'slug' => 'bn-store-a', 'status' => 'active']);
        $this->tenantB = Tenant::create(['name' => 'BN Store B', 'slug' => 'bn-store-b', 'status' => 'active']);

        $categoryA = Category::withoutTenantScope()->create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'BN Category',
            'slug' => 'bn-category',
        ]);

        Tenant::setCurrent($this->tenantA);

        $this->productA = Product::create([
            'name' => 'BN Simple',
            'slug' => 'bn-simple',
            'price' => 10000,
            'category_id' => $categoryA->id,
            'status' => Product::STATUS_ACTIVE,
        ]);

        $this->variantA = ProductVariant::create([
            'product_id' => $this->productA->id,
            'price' => 12000,
            'stock' => 5,
            'status' => ProductVariant::STATUS_ACTIVE,
            'attributes' => ['size' => 'M'],
        ]);

        Tenant::setCurrent($this->tenantB);

        $this->productB = Product::create([
            'name' => 'BN Other Tenant',
            'slug' => 'bn-other-tenant',
            'price' => 9000,
            'category_id' => $categoryA->id,
            'status' => Product::STATUS_ACTIVE,
        ]);

        app()->forgetInstance('current.tenant');

        StockMovement::withoutTenantScope()->create([
            'tenant_id' => $this->tenantA->id,
            'product_id' => $this->productA->id,
            'product_variant_id' => null,
            'type' => StockMovement::TYPE_OPENING_STOCK,
            'quantity' => 10,
        ]);

        StockMovement::withoutTenantScope()->create([
            'tenant_id' => $this->tenantA->id,
            'product_id' => $this->productA->id,
            'product_variant_id' => $this->variantA->id,
            'type' => StockMovement::TYPE_OPENING_STOCK,
            'quantity' => 3,
        ]);
    }

    private function service(): BuyNowService
    {
        return app(BuyNowService::class);
    }

    /** @test */
    public function start_creates_isolated_context_without_touching_normal_cart(): void
    {
        session()->put('cart', ['p999_v0' => ['product_id' => 999, 'quantity' => 1, 'price' => 100]]);

        $context = $this->service()->start($this->tenantA, $this->productA->id, null, 2);

        $this->assertSame($this->tenantA->id, $context['tenant_id']);
        $this->assertCount(1, $context['items']);
        $item = reset($context['items']);
        $this->assertSame($this->productA->id, $item['product_id']);
        $this->assertSame(2, $item['quantity']);

        $this->assertSame(['p999_v0' => ['product_id' => 999, 'quantity' => 1, 'price' => 100]], session()->get('cart'));
        $this->assertTrue($this->service()->isActive($this->tenantA));
    }

    /** @test */
    public function start_supports_variant_and_rejects_bad_input(): void
    {
        $context = $this->service()->start($this->tenantA, $this->productA->id, $this->variantA->id, 1);
        $item = reset($context['items']);
        $this->assertSame($this->variantA->id, $item['variant_id']);

        $this->expectException(ValidationException::class);
        $this->service()->start($this->tenantA, $this->productA->id, $this->variantA->id, 99);
    }

    /** @test */
    public function start_rejects_other_tenant_product_variant_and_quantity(): void
    {
        foreach ([
            fn () => $this->service()->start($this->tenantA, $this->productB->id, null, 1),
            fn () => $this->service()->start($this->tenantA, 999999, null, 1),
            fn () => $this->service()->start($this->tenantA, $this->productA->id, $this->variantA->id + 999999, 1),
            fn () => $this->service()->start($this->tenantA, $this->productA->id, null, 0),
            fn () => $this->service()->start($this->tenantA, $this->productA->id, null, 999),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('Expected ValidationException.');
            } catch (ValidationException $e) {
                $this->assertNotEmpty($e->errors());
            }
        }
    }

    /** @test */
    public function context_is_tenant_scoped_and_clearable(): void
    {
        $this->service()->start($this->tenantA, $this->productA->id, null, 1);

        Tenant::setCurrent($this->tenantB);
        $this->assertNull($this->service()->getActive($this->tenantB));
        $this->assertFalse($this->service()->isActive($this->tenantB));

        Tenant::setCurrent($this->tenantA);
        $this->assertNotNull($this->service()->getActive($this->tenantA));

        $this->service()->clear();
        $this->assertNull($this->service()->getActive($this->tenantA));
        $this->assertNull(session()->get(BuyNowService::SESSION_KEY));

        app()->forgetInstance('current.tenant');
    }

    /** @test */
    public function http_start_redirects_to_checkout_and_preserves_cart(): void
    {
        $cart = ['p1_v0' => ['product_id' => 1, 'quantity' => 1, 'price' => 100]];

        $response = $this->withSession(['cart' => $cart])->post(
            "/store/{$this->tenantA->slug}/checkout/buy-now",
            ['product_id' => $this->productA->id, 'quantity' => 2]
        );

        $response->assertRedirect("/store/{$this->tenantA->slug}/checkout");

        $buyNow = session(BuyNowService::SESSION_KEY);
        $this->assertNotNull($buyNow);
        $this->assertSame($this->tenantA->id, $buyNow['tenant_id']);
        $this->assertCount(1, $buyNow['items']);
        $this->assertSame($cart, session('cart'));
    }

    /** @test */
    public function http_start_supports_variant_and_rejects_invalid_requests(): void
    {
        $response = $this->post(
            "/store/{$this->tenantA->slug}/checkout/buy-now",
            ['product_id' => $this->productA->id, 'variant_id' => $this->variantA->id, 'quantity' => 1]
        );

        $response->assertRedirect("/store/{$this->tenantA->slug}/checkout");
        $item = reset(session(BuyNowService::SESSION_KEY)['items']);
        $this->assertSame($this->variantA->id, $item['variant_id']);

        foreach ([
            ['product_id' => $this->productB->id, 'quantity' => 1],
            ['product_id' => 999999, 'quantity' => 1],
            ['product_id' => $this->productA->id, 'quantity' => 0],
            ['product_id' => $this->productA->id, 'quantity' => 999],
        ] as $payload) {
            $this->post("/store/{$this->tenantA->slug}/checkout/buy-now", $payload)
                ->assertRedirect();
            $this->assertTrue(
                session()->has('errors'),
                'Expected validation errors for payload: ' . json_encode($payload)
            );
        }
    }
}
