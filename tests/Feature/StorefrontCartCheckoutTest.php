<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StorefrontCartCheckoutTest extends TestCase
{
    use DatabaseTransactions;

    private Tenant $tenant;
    private Tenant $otherTenant;
    private Category $category;
    private Brand $brand;
    private Product $singleProduct;
    private Product $variableProduct;
    private Product $comboProduct;
    private ProductVariant $variant;
    private PaymentMethod $paymentMethod;
    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupMinimalSchema();
        $this->setupTestData();
    }

    private function setupMinimalSchema(): void
    {
        $tables = [
            'tenants', 'categories', 'brands', 'products',
            'product_variants', 'product_combos',
            'website_infos', 'promotions', 'promotion_product', 'promotion_category',
            'orders', 'order_items', 'payment_methods', 'coupons',
            'cities', 'townships',
        ];

        foreach ($tables as $table) {
            if (!Schema::hasTable($table)) {
                $this->markTestSkipped("Table {$table} not found. Run migrations first.");
            }
        }
    }

    private function setupTestData(): void
    {
        $this->tenant = Tenant::create([
            'name' => 'Test Store',
            'slug' => 'test-store',
            'store_url' => '/store/test-store',
            'status' => 'active',
        ]);

        $this->grantStorefrontEntitlement($this->tenant);

        $this->otherTenant = Tenant::create([
            'name' => 'Other Store',
            'slug' => 'other-store',
            'store_url' => '/store/other-store',
            'status' => 'active',
        ]);

        $this->grantStorefrontEntitlement($this->otherTenant);

        Tenant::setCurrent($this->tenant);

        $this->customer = User::create([
            'name' => 'Test Customer',
            'email' => 'cart-customer@example.com',
            'password' => bcrypt('password'),
            'tenant_id' => $this->tenant->id,
        ]);

        $this->actingAs($this->customer, 'web');

        $this->category = Category::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Electronics',
            'slug' => 'electronics',
            'is_active' => true,
        ]);

        $this->brand = Brand::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'TestBrand',
            'slug' => 'testbrand',
            'is_active' => true,
        ]);

        $this->singleProduct = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Single Product',
            'type' => 'single',
            'price' => 100,
            'stock' => 50,
            'category_id' => $this->category->id,
            'brand_id' => $this->brand->id,
            'status' => 'active',
        ]);

        $this->variableProduct = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Variable Product',
            'type' => 'variable',
            'price' => 200,
            'stock' => 0,
            'category_id' => $this->category->id,
            'status' => 'active',
        ]);

        $this->variant = ProductVariant::create([
            'product_id' => $this->variableProduct->id,
            'name' => 'Size M',
            'price' => 200,
            'stock' => 30,
            'status' => 'active',
            'attributes' => ['size' => 'M'],
        ]);

        $this->comboProduct = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Combo Product',
            'type' => 'combo',
            'price' => 150,
            'stock' => 20,
            'category_id' => $this->category->id,
            'status' => 'active',
        ]);

        $this->paymentMethod = PaymentMethod::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Bank Transfer',
            'type' => 'bank_transfer',
            'account_name' => 'Test Account',
            'account_number' => '123456789',
            'is_active' => true,
        ]);

        StockMovement::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->singleProduct->id,
            'product_variant_id' => null,
            'type' => StockMovement::TYPE_OPENING_STOCK,
            'quantity' => 50,
        ]);

        StockMovement::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->variableProduct->id,
            'product_variant_id' => $this->variant->id,
            'type' => StockMovement::TYPE_OPENING_STOCK,
            'quantity' => 30,
        ]);
    }

    private function addToCart(int $productId, int $quantity, ?int $variantId = null): \Illuminate\Testing\TestResponse
    {
        return $this->post('/cart/add', [
            'product_id' => $productId,
            'variant_id' => $variantId,
            'quantity' => $quantity,
        ]);
    }

    /** @test */
    public function empty_cart_shows_empty_state(): void
    {
        $response = $this->get('/cart');

        $response->assertOk();
        $this->assertSame([], $response->viewData('page')['props']['cartItems']);
    }

    /** @test */
    public function single_product_can_be_added_to_cart(): void
    {
        $response = $this->addToCart($this->singleProduct->id, 2);

        $response->assertJson(['count' => 2]);
        $this->assertEquals(2, session()->get('cart.p' . $this->singleProduct->id . '_v0.quantity'));
    }

    /** @test */
    public function variable_product_preserves_selected_variant(): void
    {
        $response = $this->addToCart($this->variableProduct->id, 1, $this->variant->id);

        $response->assertJson(['count' => 1]);
        $cartKey = 'p' . $this->variableProduct->id . '_v' . $this->variant->id;
        $this->assertEquals(1, session()->get('cart.' . $cartKey . '.quantity'));
        $this->assertEquals($this->variant->id, session()->get('cart.' . $cartKey . '.variant_id'));
    }

    /** @test */
    public function quantity_above_stock_is_rejected(): void
    {
        $response = $this->addToCart($this->singleProduct->id, 100);

        $response->assertStatus(422);
        $response->assertJsonFragment(['error' => 'Insufficient stock. Available: 50']);
    }

    /** @test */
    public function out_of_stock_product_cannot_be_added(): void
    {
        $outOfStock = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Out of Stock',
            'type' => 'single',
            'price' => 50,
            'stock' => 0,
            'category_id' => $this->category->id,
            'status' => 'active',
        ]);

        $response = $this->addToCart($outOfStock->id, 1);

        $response->assertStatus(422);
    }

    /** @test */
    public function cart_quantity_can_be_updated(): void
    {
        $this->addToCart($this->singleProduct->id, 2);

        $cartKey = 'p' . $this->singleProduct->id . '_v0';
        $response = $this->patch('/cart/' . $cartKey, ['quantity' => 5]);

        $response->assertOk();
        $this->assertEquals(5, session()->get('cart.' . $cartKey . '.quantity'));
    }

    /** @test */
    public function cart_item_can_be_removed(): void
    {
        $this->addToCart($this->singleProduct->id, 2);

        $cartKey = 'p' . $this->singleProduct->id . '_v0';
        $response = $this->delete('/cart/' . $cartKey);

        $response->assertOk();
        $this->assertNull(session()->get('cart.' . $cartKey));
    }

    /** @test */
    public function cart_totals_are_correct(): void
    {
        $this->addToCart($this->singleProduct->id, 2);

        $response = $this->get('/cart');

        $response->assertOk();
        $props = $response->viewData('page')['props'];
        $this->assertEquals(200.0, $props['subtotal']);
    }

    /** @test */
    public function cross_tenant_product_cannot_enter_cart(): void
    {
        Tenant::setCurrent($this->otherTenant);

        $otherCategory = Category::create([
            'tenant_id' => $this->otherTenant->id,
            'name' => 'Other Category',
            'slug' => 'other-category',
            'is_active' => true,
        ]);

        $otherProduct = Product::create([
            'category_id' => $otherCategory->id,
            'name' => 'Other Tenant Product',
            'type' => 'single',
            'price' => 100,
            'stock' => 10,
            'status' => 'active',
        ]);

        Tenant::setCurrent($this->tenant);

        $response = $this->addToCart($otherProduct->id, 1);

        $response->assertNotFound();
    }

    /** @test */
    public function cross_tenant_variant_cannot_enter_cart(): void
    {
        Tenant::setCurrent($this->otherTenant);

        $otherCategory = Category::create([
            'tenant_id' => $this->otherTenant->id,
            'name' => 'Other Variant Category',
            'slug' => 'other-variant-category',
            'is_active' => true,
        ]);

        $otherProduct = Product::create([
            'category_id' => $otherCategory->id,
            'name' => 'Other Tenant Variable',
            'type' => 'variable',
            'price' => 200,
            'stock' => 0,
            'status' => 'active',
        ]);

        $otherVariant = ProductVariant::create([
            'product_id' => $otherProduct->id,
            'name' => 'Size L',
            'price' => 200,
            'stock' => 30,
            'status' => 'active',
            'attributes' => ['size' => 'L'],
        ]);

        Tenant::setCurrent($this->tenant);

        $response = $this->addToCart($this->variableProduct->id, 1, $otherVariant->id);

        $response->assertStatus(422);
    }

    /** @test */
    public function server_side_product_price_is_authoritative(): void
    {
        $this->addToCart($this->singleProduct->id, 1);

        $cartKey = 'p' . $this->singleProduct->id . '_v0';
        $this->assertEquals(100, session()->get('cart.' . $cartKey . '.price'));
    }

    /** @test */
    public function variant_price_is_authoritative(): void
    {
        $variantPrice = 250;
        $this->variant->update(['price' => $variantPrice]);

        $this->addToCart($this->variableProduct->id, 1, $this->variant->id);

        $cartKey = 'p' . $this->variableProduct->id . '_v' . $this->variant->id;
        $this->assertEquals($variantPrice, session()->get('cart.' . $cartKey . '.price'));
    }

    /** @test */
    public function checkout_loads_with_valid_cart(): void
    {
        $this->addToCart($this->singleProduct->id, 2);

        $response = $this->get('/checkout');

        $response->assertOk();
        $cartItems = $response->viewData('page')['props']['cartItems'];
        $this->assertCount(1, $cartItems);
        $this->assertSame('Single Product', $cartItems[0]['name']);
    }

    /** @test */
    public function checkout_validates_required_fields(): void
    {
        $this->addToCart($this->singleProduct->id, 1);

        $response = $this->post('/checkout', [
            'first_name' => '',
            'last_name' => '',
            'phone' => '',
            'address' => '',
            'payment_method_id' => '',
        ]);

        $response->assertSessionHasErrors(['first_name', 'last_name', 'phone', 'address', 'payment_method_id']);
    }

    /** @test */
    public function checkout_rejects_invalid_payment_method(): void
    {
        $this->addToCart($this->singleProduct->id, 1);

        $response = $this->post('/checkout', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone' => '1234567890',
            'address' => '123 Test St',
            'payment_method_id' => 99999,
        ]);

        $response->assertSessionHasErrors(['payment_method_id']);
    }

    /** @test */
    public function stock_is_revalidated_before_order_creation(): void
    {
        $this->addToCart($this->singleProduct->id, 50);

        StockMovement::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->singleProduct->id,
            'product_variant_id' => null,
            'type' => StockMovement::TYPE_ADJUSTMENT,
            'quantity' => -45,
        ]);

        $response = $this->post('/checkout', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone' => '1234567890',
            'address' => '123 Test St',
            'payment_method_id' => $this->paymentMethod->id,
        ]);

        $response->assertSessionHas('error');
        $this->assertStringContainsString('Insufficient stock', (string) $response->getSession()->get('error'));
        $this->assertDatabaseCount('orders', 0);
    }

    /** @test */
    public function order_creation_succeeds_with_valid_cart(): void
    {
        $this->addToCart($this->singleProduct->id, 2);

        $response = $this->post('/checkout', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone' => '1234567890',
            'address' => '123 Test St',
            'payment_method_id' => $this->paymentMethod->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseCount('orders', 1);

        $cartPage = $this->get('/cart');
        $cartPage->assertOk();
        $this->assertSame([], $cartPage->viewData('page')['props']['cartItems']);
    }

    /** @test */
    public function order_items_preserve_variant_information(): void
    {
        $this->addToCart($this->variableProduct->id, 1, $this->variant->id);

        $response = $this->post('/checkout', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone' => '1234567890',
            'address' => '123 Test St',
            'payment_method_id' => $this->paymentMethod->id,
        ]);

        $response->assertRedirect();
    }

    /** @test */
    public function cart_is_cleared_after_successful_order(): void
    {
        $this->addToCart($this->singleProduct->id, 1);

        $this->post('/checkout', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone' => '1234567890',
            'address' => '123 Test St',
            'payment_method_id' => $this->paymentMethod->id,
        ]);

        $this->assertEmpty(session()->get('cart', []));
    }

    /** @test */
    public function client_cannot_manipulate_price(): void
    {
        $this->addToCart($this->singleProduct->id, 1);

        $cartKey = 'p' . $this->singleProduct->id . '_v0';
        session()->put('cart.' . $cartKey . '.price', 1);

        $response = $this->post('/checkout', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone' => '1234567890',
            'address' => '123 Test St',
            'payment_method_id' => $this->paymentMethod->id,
        ]);

        $order = \App\Models\Order::latest()->first();
        $this->assertNotNull($order);
        $this->assertNotEquals(1, $order->subtotal);
        $this->assertEquals(100.0, (float) $order->subtotal);
    }

    /** @test */
    public function client_cannot_bypass_stock_validation(): void
    {
        $this->addToCart($this->singleProduct->id, 10);

        StockMovement::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->singleProduct->id,
            'product_variant_id' => null,
            'type' => StockMovement::TYPE_ADJUSTMENT,
            'quantity' => -45,
        ]);

        $response = $this->post('/checkout', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone' => '1234567890',
            'address' => '123 Test St',
            'payment_method_id' => $this->paymentMethod->id,
        ]);

        $response->assertSessionHas('error');
    }

    /** @test */
    public function internal_fields_are_not_exposed(): void
    {
        $this->addToCart($this->singleProduct->id, 1);

        $response = $this->get('/cart');

        $response->assertOk();
        $cartItems = $response->viewData('page')['props']['cartItems'];

        $this->assertNotEmpty($cartItems);
        foreach ($cartItems as $item) {
            $this->assertArrayNotHasKey('cost_price', $item);
            $this->assertArrayNotHasKey('supplier', $item);
        }
    }

    /** @test */
    public function duplicate_submission_does_not_create_duplicate_orders(): void
    {
        $this->addToCart($this->singleProduct->id, 1);

        $this->post('/checkout', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone' => '1234567890',
            'address' => '123 Test St',
            'payment_method_id' => $this->paymentMethod->id,
        ]);

        $initialCount = \App\Models\Order::count();

        $this->post('/checkout', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone' => '1234567890',
            'address' => '123 Test St',
            'payment_method_id' => $this->paymentMethod->id,
        ]);

        $this->assertEquals($initialCount, \App\Models\Order::count());
    }
}
