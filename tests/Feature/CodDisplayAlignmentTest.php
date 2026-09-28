<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\City;
use App\Models\CodRule;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Tenant;
use App\Models\Township;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CodDisplayAlignmentTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private City $yangon;
    private City $mandalay;
    private Township $bahan;
    private Township $chanmyathazi;
    private User $user;
    private Product $product;
    private PaymentMethod $codMethod;
    private PaymentMethod $bankMethod;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'COD Display Store', 'slug' => 'cod-display-store',
            'store_url' => '/store/cod-display-store', 'status' => 'active',
        ]);
        Tenant::setCurrent($this->tenant);

        $this->yangon = City::create(['name' => 'Yangon', 'is_active' => true]);
        $this->mandalay = City::create(['name' => 'Mandalay', 'is_active' => true]);
        $this->bahan = Township::create([
            'city_id' => $this->yangon->id, 'name' => 'Bahan',
            'postal_code' => '11201', 'delivery_fee' => 3000, 'is_active' => true,
        ]);
        $this->chanmyathazi = Township::create([
            'city_id' => $this->mandalay->id, 'name' => 'Chanmyathazi',
            'postal_code' => '05001', 'delivery_fee' => 2000, 'is_active' => true,
        ]);

        $category = Category::create(['name' => 'Display Cat', 'slug' => 'display-cat']);
        $this->product = Product::create([
            'name' => 'Display Widget', 'type' => 'single', 'price' => 5000,
            'stock' => 50, 'status' => 'active', 'category_id' => $category->id,
        ]);
        StockMovement::create([
            'product_id' => $this->product->id, 'product_variant_id' => null,
            'type' => StockMovement::TYPE_OPENING_STOCK, 'quantity' => 50,
        ]);

        $this->codMethod = PaymentMethod::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Cash on Delivery',
            'type' => 'cod', 'is_active' => true,
        ]);
        $this->bankMethod = PaymentMethod::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Bank Transfer',
            'type' => 'bank_transfer', 'account_name' => 'A', 'account_number' => '1',
            'is_active' => true,
        ]);

        $this->user = User::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Display Buyer',
            'email' => 'display-buyer@test.com', 'password' => bcrypt('password'),
            'status' => 'active', 'allow_cod' => true,
        ]);
    }

    private function quote(array $overrides = [])
    {
        return $this->withSession(['cart' => [
            'k1' => ['product_id' => $this->product->id, 'quantity' => 1],
        ]])->postJson('/store/cod-display-store/checkout/quote', array_merge([
            'city_id' => $this->yangon->id,
            'township_id' => $this->bahan->id,
        ], $overrides));
    }

    /** @test */
    public function quote_shows_cod_available_for_eligible_city(): void
    {
        CodRule::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Yangon COD',
            'allowed_city_ids' => [$this->yangon->id],
            'is_active' => true,
        ]);

        $response = $this->quote();

        $response->assertOk();
        $this->assertTrue($response->json('cod.available'));
        $this->assertNull($response->json('cod.unavailable_reason'));
    }

    /** @test */
    public function quote_shows_cod_unavailable_for_excluded_city(): void
    {
        CodRule::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Yangon Only',
            'allowed_city_ids' => [$this->yangon->id],
            'is_active' => true,
        ]);

        $response = $this->quote([
            'city_id' => $this->mandalay->id,
            'township_id' => $this->chanmyathazi->id,
        ]);

        $response->assertOk();
        $this->assertFalse($response->json('cod.available'));
        $this->assertStringContainsString('location', $response->json('cod.unavailable_reason'));
    }

    /** @test */
    public function quote_availability_uses_full_total_not_subtotal(): void
    {
        CodRule::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Capped COD',
            'max_order_amount' => 6000,
            'is_active' => true,
        ]);

        $response = $this->quote();

        $response->assertOk();
        $this->assertFalse($response->json('cod.available'));
        $this->assertStringContainsString('amount', $response->json('cod.unavailable_reason'));
    }

    /** @test */
    public function quote_availability_updates_when_city_changes(): void
    {
        CodRule::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Yangon COD',
            'allowed_city_ids' => [$this->yangon->id],
            'is_active' => true,
        ]);

        $this->assertTrue($this->quote()->json('cod.available'));

        $changed = $this->quote([
            'city_id' => $this->mandalay->id,
            'township_id' => $this->chanmyathazi->id,
        ]);

        $changed->assertOk();
        $this->assertFalse($changed->json('cod.available'));
    }

    /** @test */
    public function guest_store_redirects_to_login(): void
    {
        $response = $this->post('/store/cod-display-store/checkout', [
            'first_name' => 'Guest', 'last_name' => 'User', 'phone' => '09911122233',
            'address' => 'No. 1, Guest Road',
            'city_id' => $this->yangon->id, 'township_id' => $this->bahan->id,
            'payment_method_id' => $this->codMethod->id,
        ]);

        $response->assertRedirect();
        $this->assertEquals(0, Order::where('tenant_id', $this->tenant->id)->count());
    }

    /** @test */
    public function store_still_enforces_after_city_change(): void
    {
        CodRule::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Yangon COD',
            'allowed_city_ids' => [$this->yangon->id],
            'is_active' => true,
        ]);

        $this->actingAs($this->user, 'accounts');
        app()->instance('current.tenant', $this->tenant);
        $this->post('/store/cod-display-store/cart/add', ['product_id' => $this->product->id, 'quantity' => 1]);

        $response = $this->post('/store/cod-display-store/checkout', [
            'first_name' => 'Display', 'last_name' => 'Buyer', 'phone' => '09911122233',
            'address' => 'No. 1, Display Road',
            'city_id' => $this->mandalay->id, 'township_id' => $this->chanmyathazi->id,
            'postal_code' => '05001',
            'payment_method_id' => $this->codMethod->id,
        ]);

        $response->assertRedirect();
        $this->assertEquals(0, Order::where('tenant_id', $this->tenant->id)->count());
    }
}
