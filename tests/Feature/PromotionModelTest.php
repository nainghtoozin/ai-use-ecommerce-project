<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromotionModelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        \App\Models\Category::create(['name' => 'Default']);
    }

    public function test_valid_scope_filters_active_not_expired_not_used_up()
    {
        Promotion::factory()->create(['is_active' => true, 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonth(), 'usage_limit' => null]);
        Promotion::factory()->inactive()->create();
        Promotion::factory()->expired()->create();
        Promotion::factory()->usageLimitReached()->create();

        $valid = Promotion::valid()->get();

        $this->assertCount(1, $valid);
    }

    public function test_is_currently_active_checks_dates_and_active_flag()
    {
        $active = Promotion::factory()->create(['is_active' => true, 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonth()]);
        $inactive = Promotion::factory()->inactive()->create();
        $notStarted = Promotion::factory()->notStarted()->create();
        $expired = Promotion::factory()->expired()->create();

        $this->assertTrue($active->isCurrentlyActive());
        $this->assertFalse($inactive->isCurrentlyActive());
        $this->assertFalse($notStarted->isCurrentlyActive());
        $this->assertFalse($expired->isCurrentlyActive());
    }

    public function test_is_expired_when_ends_at_passed()
    {
        $expired = Promotion::factory()->expired()->create();
        $active = Promotion::factory()->create();

        $this->assertTrue($expired->isExpired());
        $this->assertFalse($active->isExpired());
    }

    public function test_has_reached_usage_limit()
    {
        $reached = Promotion::factory()->usageLimitReached()->create();
        $notReached = Promotion::factory()->create(['usage_limit' => 10, 'usage_count' => 5]);
        $noLimit = Promotion::factory()->create(['usage_limit' => null]);

        $this->assertTrue($reached->hasReachedUsageLimit());
        $this->assertFalse($notReached->hasReachedUsageLimit());
        $this->assertFalse($noLimit->hasReachedUsageLimit());
    }

    public function test_calculate_percentage_discount_without_cap()
    {
        $promotion = Promotion::factory()->percentage()->create(['value' => 20, 'max_discount_amount' => null]);

        $cart = [['price' => 50000, 'quantity' => 1]];

        $discount = $promotion->calculateDiscount($cart, 0);

        $this->assertEquals(10000.00, $discount);
    }

    public function test_calculate_percentage_discount_with_cap()
    {
        $promotion = Promotion::factory()->percentage()->create(['value' => 50, 'max_discount_amount' => 5000]);

        $cart = [['price' => 50000, 'quantity' => 1]];

        $discount = $promotion->calculateDiscount($cart, 0);

        $this->assertEquals(5000.00, $discount);
    }

    public function test_calculate_fixed_discount_capped_at_subtotal()
    {
        $promotion = Promotion::factory()->fixed()->create(['value' => 7000]);

        $cart = [['price' => 5000, 'quantity' => 1]];

        $discount = $promotion->calculateDiscount($cart, 0);

        $this->assertEquals(5000.00, $discount);
    }

    public function test_calculate_fixed_discount_full_value_when_subtotal_exceeds()
    {
        $promotion = Promotion::factory()->fixed()->create(['value' => 7000]);

        $cart = [['price' => 50000, 'quantity' => 1]];

        $discount = $promotion->calculateDiscount($cart, 0);

        $this->assertEquals(7000.00, $discount);
    }

    public function test_calculate_free_shipping_discount()
    {
        $promotion = Promotion::factory()->freeShipping()->create();

        $cart = [['price' => 50000, 'quantity' => 1]];

        $discount = $promotion->calculateDiscount($cart, 3000);

        $this->assertEquals(3000.00, $discount);
    }

    public function test_applies_to_cart_all_products()
    {
        $promotion = Promotion::factory()->forAllProducts()->create();

        $cart = [['id' => 1, 'price' => 10000, 'quantity' => 1]];

        $this->assertTrue($promotion->appliesToCart($cart));
    }

    public function test_applies_to_cart_specific_products()
    {
        $product = Product::factory()->create();
        $promotion = Promotion::factory()->forSpecificProducts()->create();
        $promotion->products()->attach($product->id);

        $cart = [['id' => $product->id, 'price' => 10000, 'quantity' => 1]];

        $this->assertTrue($promotion->appliesToCart($cart));
    }

    public function test_applies_to_cart_fails_when_product_not_in_promotion()
    {
        $product = Product::factory()->create();
        $otherProduct = Product::factory()->create();
        $promotion = Promotion::factory()->forSpecificProducts()->create();
        $promotion->products()->attach($product->id);

        $cart = [['id' => $otherProduct->id, 'price' => 10000, 'quantity' => 1]];

        $this->assertFalse($promotion->appliesToCart($cart));
    }

    public function test_applies_to_cart_categories()
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id]);
        $promotion = Promotion::factory()->forSpecificCategories()->create();
        $promotion->categories()->attach($category->id);

        $cart = [['id' => $product->id, 'price' => 10000, 'quantity' => 1]];

        $this->assertTrue($promotion->appliesToCart($cart));
    }

    public function test_applies_to_cart_fails_when_category_not_in_promotion()
    {
        $category1 = Category::factory()->create();
        $category2 = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category2->id]);
        $promotion = Promotion::factory()->forSpecificCategories()->create();
        $promotion->categories()->attach($category1->id);

        $cart = [['id' => $product->id, 'price' => 10000, 'quantity' => 1]];

        $this->assertFalse($promotion->appliesToCart($cart));
    }

    public function test_applies_to_cart_fails_when_empty()
    {
        $promotion = Promotion::factory()->create();

        $this->assertFalse($promotion->appliesToCart([]));
    }

    public function test_applies_to_cart_minimum_order_amount()
    {
        $promotion = Promotion::factory()->withMinOrder(50000)->create();

        $belowCart = [['price' => 20000, 'quantity' => 1]];
        $aboveCart = [['price' => 60000, 'quantity' => 1]];

        $this->assertFalse($promotion->appliesToCart($belowCart));
        $this->assertTrue($promotion->appliesToCart($aboveCart));
    }

    public function test_validate_for_usage_expired()
    {
        $promotion = Promotion::factory()->expired()->create();

        $errors = $promotion->validateForUsage(null, [['price' => 10000, 'quantity' => 1]]);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('expired', $errors[0]);
    }

    public function test_validate_for_usage_usage_limit()
    {
        $promotion = Promotion::factory()->usageLimitReached()->create();

        $errors = $promotion->validateForUsage(null, [['price' => 10000, 'quantity' => 1]]);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('usage limit', $errors[0]);
    }

    public function test_record_usage_creates_usage_and_increments_count()
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        $promotion = Promotion::factory()->create(['usage_count' => 0]);

        $order = Order::create([
            'user_id' => $user->id,
            'customer_name' => 'Test Customer',
            'first_name' => 'Test',
            'last_name' => 'Customer',
            'phone' => '09123456789',
            'email' => 'test@example.com',
            'address' => '123 Test St',
            'subtotal' => 50000,
            'total_amount' => 50000,
            'delivery_fee' => 0,
            'discount_amount' => 5000,
            'payment_status' => 'unpaid',
            'order_status' => 'pending',
        ]);

        $usage = $promotion->recordUsage($order, $user, 5000);

        $this->assertDatabaseHas('promotion_usages', [
            'promotion_id' => $promotion->id,
            'user_id' => $user->id,
            'order_id' => $order->id,
            'discount_amount' => 5000,
        ]);

        $this->assertEquals(1, $promotion->fresh()->usage_count);
    }

    public function test_can_be_used_by_respects_per_customer_limit()
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        $promotion = Promotion::factory()->withPerCustomerLimit(2)->create(['usage_count' => 5]);

        $order1 = Order::create([
            'user_id' => $user->id,
            'customer_name' => 'Test Customer',
            'first_name' => 'Test',
            'last_name' => 'Customer',
            'phone' => '09123456789',
            'email' => 'test@example.com',
            'address' => '123 Test St',
            'subtotal' => 50000,
            'total_amount' => 50000,
            'delivery_fee' => 0,
            'discount_amount' => 5000,
            'payment_status' => 'unpaid',
            'order_status' => 'pending',
        ]);

        $promotion->recordUsage($order1, $user, 5000);

        $this->assertTrue($promotion->canBeUsedBy($user));

        $order2 = Order::create([
            'user_id' => $user->id,
            'customer_name' => 'Test Customer',
            'first_name' => 'Test',
            'last_name' => 'Customer',
            'phone' => '09123456789',
            'email' => 'test@example.com',
            'address' => '123 Test St',
            'subtotal' => 50000,
            'total_amount' => 50000,
            'delivery_fee' => 0,
            'discount_amount' => 5000,
            'payment_status' => 'unpaid',
            'order_status' => 'pending',
        ]);

        $promotion->recordUsage($order2, $user, 5000);

        $this->assertFalse($promotion->canBeUsedBy($user));
    }

    public function test_can_be_used_by_returns_true_when_no_limit()
    {
        $user = User::factory()->create();
        $promotion = Promotion::factory()->create(['per_customer_limit' => null]);

        $this->assertTrue($promotion->canBeUsedBy($user));
    }

    public function test_find_by_code()
    {
        Promotion::factory()->create(['code' => 'TESTCODE']);
        Promotion::factory()->create(['code' => 'OTHER']);

        $found = Promotion::findByCode('TESTCODE');

        $this->assertNotNull($found);
        $this->assertEquals('TESTCODE', $found->code);
    }

    public function test_find_by_code_returns_null_for_missing()
    {
        $result = Promotion::findByCode('NONEXISTENT');

        $this->assertNull($result);
    }

    public function test_generate_code_unique()
    {
        $code = Promotion::generateCode(8);

        $this->assertEquals(8, strlen($code));
        $this->assertMatchesRegularExpression('/^[A-Z0-9]+$/', $code);
    }
}
