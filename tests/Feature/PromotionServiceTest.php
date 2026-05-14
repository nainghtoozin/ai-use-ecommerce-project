<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\User;
use App\Services\PromotionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromotionServiceTest extends TestCase
{
    use RefreshDatabase;

    private PromotionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        \App\Models\Category::create(['name' => 'Default']);
        $this->service = app(PromotionService::class);
    }

    public function test_validate_promotion_valid_code_with_matching_cart()
    {
        $promotion = Promotion::factory()->create([
            'code' => 'SUMMER20',
            'value' => 20,
            'is_automatic' => false,
        ]);

        $result = $this->service->validatePromotion('SUMMER20', [['price' => 50000, 'quantity' => 1]]);

        $this->assertTrue($result['valid']);
        $this->assertEquals(10000.00, $result['discount']);
    }

    public function test_validate_promotion_invalid_code_returns_error()
    {
        $result = $this->service->validatePromotion('INVALID', [['price' => 50000, 'quantity' => 1]]);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('Invalid', $result['message']);
    }

    public function test_validate_promotion_empty_code_returns_error()
    {
        $result = $this->service->validatePromotion('', [['price' => 50000, 'quantity' => 1]]);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('No promotion code', $result['message']);
    }

    public function test_validate_promotion_expired_returns_error()
    {
        $promotion = Promotion::factory()->expired()->create(['code' => 'EXPIRED', 'is_automatic' => false]);

        $result = $this->service->validatePromotion('EXPIRED', [['price' => 50000, 'quantity' => 1]]);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('expired', $result['message']);
    }

    public function test_validate_promotion_usage_limit_reached_returns_error()
    {
        $promotion = Promotion::factory()->usageLimitReached()->create([
            'code' => 'MAXED',
            'is_automatic' => false,
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
        ]);

        $result = $this->service->validatePromotion('MAXED', [['price' => 50000, 'quantity' => 1]]);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('usage limit', $result['message']);
    }

    public function test_validate_promotion_minimum_order_not_met_returns_error()
    {
        $promotion = Promotion::factory()->withMinOrder(100000)->create([
            'code' => 'MINORDER',
            'is_automatic' => false,
        ]);

        $result = $this->service->validatePromotion('MINORDER', [['price' => 50000, 'quantity' => 1]]);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('Minimum order', $result['message']);
    }

    public function test_get_applicable_auto_promotions_returns_matching()
    {
        Promotion::factory()->automatic()->forAllProducts()->create([
            'value' => 10,
            'priority' => 5,
        ]);
        Promotion::factory()->automatic()->forAllProducts()->create([
            'value' => 20,
            'priority' => 3,
        ]);

        $result = $this->service->getApplicableAutoPromotions([['price' => 50000, 'quantity' => 1]]);

        // Sorted by priority DESC: priority 5 (10%, discount=5000) first, then priority 3 (20%, discount=10000)
        $this->assertCount(2, $result);

        $sortedDiscounts = $result->pluck('discount')->toArray();
        $this->assertEquals([5000.00, 10000.00], array_values($sortedDiscounts));
    }

    public function test_get_applicable_auto_promotions_skips_non_matching()
    {
        $category = Category::factory()->create();
        $otherCategory = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $otherCategory->id]);

        $promotion = Promotion::factory()->automatic()->forSpecificCategories()->create();
        $promotion->categories()->attach($category->id);

        $result = $this->service->getApplicableAutoPromotions([['id' => $product->id, 'price' => 50000, 'quantity' => 1]]);

        $this->assertCount(0, $result);
    }

    public function test_get_best_promotion_returns_highest_discount()
    {
        Promotion::factory()->automatic()->forAllProducts()->create(['value' => 10, 'priority' => 1]);
        Promotion::factory()->automatic()->forAllProducts()->create(['value' => 25, 'priority' => 2]);
        Promotion::factory()->automatic()->forAllProducts()->create(['value' => 15, 'priority' => 3]);

        $best = $this->service->getBestPromotion([['price' => 100000, 'quantity' => 1]]);

        $this->assertNotNull($best);
        $this->assertEquals(25000.00, $best['discount']);
    }

    public function test_get_best_promotion_returns_null_when_none_applicable()
    {
        $best = $this->service->getBestPromotion([]);

        $this->assertNull($best);
    }

    public function test_apply_promotion_to_order_updates_order_and_records_usage()
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        $promotion = Promotion::factory()->create(['code' => 'TEST', 'usage_count' => 0]);

        $order = Order::create([
            'user_id' => $user->id,
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
            'payment_status' => 'unpaid',
            'order_status' => 'pending',
        ]);

        $this->service->applyPromotionToOrder($order, $promotion, 5000);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'promotion_id' => $promotion->id,
            'promotion_code' => 'TEST',
        ]);

        $this->assertDatabaseHas('promotion_usages', [
            'promotion_id' => $promotion->id,
            'order_id' => $order->id,
            'discount_amount' => 5000,
        ]);

        $this->assertEquals(1, $promotion->fresh()->usage_count);
    }

    public function test_get_auto_promotions_for_checkout_returns_formatted()
    {
        Promotion::factory()->automatic()->forAllProducts()->create([
            'name' => 'Summer Sale',
            'value' => 15,
            'type' => 'percentage',
        ]);

        $result = $this->service->getAutoPromotionsForCheckout([['price' => 100000, 'quantity' => 1]]);

        $this->assertCount(1, $result);
        $this->assertEquals('Summer Sale', $result[0]['name']);
        $this->assertEquals('percentage', $result[0]['type']);
        $this->assertEquals(15000.00, $result[0]['discount']);
    }
}
