<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromotionApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        \App\Models\Category::create(['name' => 'Default']);
        $this->product = Product::factory()->create(['price' => 50000]);
    }

    private function withCartInSession(array $items): static
    {
        $cart = [];
        foreach ($items as $item) {
            $p = $item['product'] ?? $this->product;
            $cart[$p->id] = [
                'id' => $p->id,
                'name' => $p->name,
                'price' => (float) $p->price,
                'photo1' => $p->photo1,
                'quantity' => $item['quantity'] ?? 1,
            ];
        }
        return $this->withSession(['cart' => $cart]);
    }

    public function test_user_can_apply_valid_promotion_code_to_cart()
    {
        $promotion = Promotion::factory()->create([
            'code' => 'SUMMER',
            'value' => 20,
            'is_automatic' => false,
        ]);

        $response = $this->actingAs($this->user)
            ->withCartInSession([['quantity' => 1]])
            ->postJson('/cart/apply-promotion', ['code' => 'SUMMER']);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'discount' => 10000.00,
                'promotion_code' => 'SUMMER',
            ]);
    }

    public function test_user_cannot_apply_invalid_promotion_code()
    {
        $response = $this->actingAs($this->user)
            ->withCartInSession([['quantity' => 1]])
            ->postJson('/cart/apply-promotion', ['code' => 'INVALID']);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_user_cannot_apply_expired_promotion()
    {
        $promotion = Promotion::factory()->expired()->create(['code' => 'EXPIRED']);

        $response = $this->actingAs($this->user)
            ->withCartInSession([['quantity' => 1]])
            ->postJson('/cart/apply-promotion', ['code' => 'EXPIRED']);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_user_cannot_apply_promotion_below_minimum_order()
    {
        $promotion = Promotion::factory()->withMinOrder(100000)->create(['code' => 'MIN100K']);

        $response = $this->actingAs($this->user)
            ->withCartInSession([['quantity' => 1]])
            ->postJson('/cart/apply-promotion', ['code' => 'MIN100K']);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_user_can_remove_applied_promotion()
    {
        $promotion = Promotion::factory()->create(['code' => 'SUMMER', 'value' => 20]);

        $this->actingAs($this->user)
            ->withCartInSession([['quantity' => 1]])
            ->postJson('/cart/apply-promotion', ['code' => 'SUMMER']);

        $response = $this->actingAs($this->user)
            ->postJson('/cart/remove-promotion');

        $response->assertOk()
            ->assertJson(['success' => true]);
    }

    public function test_product_specific_promotion_applies_correctly()
    {
        $product1 = Product::factory()->create(['price' => 30000]);
        $product2 = Product::factory()->create(['price' => 70000]);

        $promotion = Promotion::factory()->percentage()->forSpecificProducts()->create([
            'code' => 'SPECIFIC',
            'value' => 10,
        ]);
        $promotion->products()->attach($product1->id);

        $response = $this->actingAs($this->user)
            ->withCartInSession([
                ['product' => $product1, 'quantity' => 1],
                ['product' => $product2, 'quantity' => 1],
            ])
            ->postJson('/cart/apply-promotion', ['code' => 'SPECIFIC']);

        // Discount is calculated on cart subtotal (not just matching products)
        // Cart subtotal = 30000 + 70000 = 100000, 10% = 10000
        $response->assertOk()
            ->assertJson([
                'success' => true,
                'discount' => 10000.00,
            ]);
    }

    public function test_order_discount_accuracy_with_promotion()
    {
        $promotion = Promotion::factory()->create([
            'code' => 'PROMO10',
            'value' => 10,
        ]);

        $this->actingAs($this->user)
            ->withCartInSession([['quantity' => 1]])
            ->postJson('/cart/apply-promotion', ['code' => 'PROMO10']);

        $this->assertTrue(session()->has('applied_promotion'));
        $this->assertEquals(5000.00, session('applied_promotion.discount'));
    }

    public function test_usage_recorded_correctly_on_order_creation()
    {
        $promotion = Promotion::factory()->create([
            'code' => 'TEST',
            'usage_count' => 0,
        ]);

        $order = Order::create([
            'user_id' => $this->user->id,
            'customer_name' => 'Test Customer',
            'first_name' => 'Test',
            'last_name' => 'Customer',
            'phone' => '09123456789',
            'email' => 'test@example.com',
            'address' => '123 Test St',
            'subtotal' => 50000,
            'total_amount' => 45000,
            'delivery_fee' => 0,
            'discount_amount' => 5000,
            'promotion_id' => $promotion->id,
            'promotion_code' => 'TEST',
            'payment_status' => 'unpaid',
            'order_status' => 'pending',
        ]);

        $promotion->recordUsage($order, $this->user, 5000);

        $this->assertDatabaseHas('promotion_usages', [
            'promotion_id' => $promotion->id,
            'user_id' => $this->user->id,
            'order_id' => $order->id,
            'discount_amount' => 5000,
        ]);

        $this->assertEquals(1, $promotion->fresh()->usage_count);
    }

    public function test_promotion_with_usage_limit_reached_cannot_be_applied()
    {
        $promotion = Promotion::factory()->usageLimitReached()->create([
            'code' => 'MAXED',
            'is_automatic' => false,
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
        ]);

        $response = $this->actingAs($this->user)
            ->withCartInSession([['quantity' => 1]])
            ->postJson('/cart/apply-promotion', ['code' => 'MAXED']);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_promotion_not_applicable_to_cart_returns_error()
    {
        $category = \App\Models\Category::factory()->create();
        $otherCategory = \App\Models\Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $otherCategory->id]);

        $promotion = Promotion::factory()->forSpecificCategories()->create([
            'code' => 'CATONLY',
            'is_automatic' => false,
        ]);
        $promotion->categories()->attach($category->id);

        $response = $this->actingAs($this->user)
            ->withCartInSession([['product' => $product, 'quantity' => 1]])
            ->postJson('/cart/apply-promotion', ['code' => 'CATONLY']);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);
    }
}
