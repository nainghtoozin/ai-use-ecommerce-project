<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromotionReportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        \App\Models\Category::create(['name' => 'Default']);
        $this->admin = User::factory()->admin()->create();
    }

    private function createDiscountedOrder(Promotion $promotion, User $user, float $discount, float $subtotal = 50000): Order
    {
        $order = Order::create([
            'user_id' => $user->id,
            'customer_name' => 'Test Customer',
            'first_name' => 'Test',
            'last_name' => 'Customer',
            'phone' => '09123456789',
            'email' => 'test@example.com',
            'address' => '123 Test St',
            'subtotal' => $subtotal,
            'total_amount' => $subtotal - $discount,
            'delivery_fee' => 0,
            'discount_amount' => $discount,
            'promotion_id' => $promotion->id,
            'promotion_code' => $promotion->code ?? 'AUTO',
            'payment_status' => 'paid',
            'order_status' => 'delivered',
        ]);

        $promotion->recordUsage($order, $user, $discount);

        return $order;
    }

    public function test_report_returns_total_discounts_given()
    {
        $user = User::factory()->create();
        $promotion = Promotion::factory()->create(['usage_count' => 0]);

        $this->createDiscountedOrder($promotion, $user, 5000);
        $this->createDiscountedOrder($promotion, $user, 3000);

        $response = $this->actingAs($this->admin)
            ->getJson('/admin/promotions/reports/data');

        $response->assertOk();
        $this->assertEquals(8000.00, $response->json('summary.total_discounts_given'));
        $this->assertEquals(2, $response->json('summary.orders_using_promotions'));
    }

    public function test_report_returns_orders_using_promotions_count()
    {
        $user = User::factory()->create();
        $promotion = Promotion::factory()->create(['usage_count' => 0]);

        $this->createDiscountedOrder($promotion, $user, 5000);

        Order::create([
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
            'discount_amount' => 0,
            'payment_status' => 'paid',
            'order_status' => 'delivered',
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/admin/promotions/reports/data');

        $response->assertOk();
        $this->assertEquals(1, $response->json('summary.orders_using_promotions'));
        $this->assertEquals(2, $response->json('summary.all_orders_count'));
    }

    public function test_report_top_promotions_ranked_by_discount()
    {
        $user = User::factory()->create();
        $promo1 = Promotion::factory()->create(['usage_count' => 0]);
        $promo2 = Promotion::factory()->create(['usage_count' => 0]);

        $this->createDiscountedOrder($promo1, $user, 10000);
        $this->createDiscountedOrder($promo1, $user, 5000);
        $this->createDiscountedOrder($promo2, $user, 3000);

        $response = $this->actingAs($this->admin)
            ->getJson('/admin/promotions/reports/data');

        $response->assertOk();
        $topPromotions = $response->json('top_promotions');

        $this->assertCount(2, $topPromotions);
        $this->assertEquals(15000.00, $topPromotions[0]['total_discount']);
        $this->assertEquals(2, $topPromotions[0]['usage_count']);
        $this->assertEquals(3000.00, $topPromotions[1]['total_discount']);
    }

    public function test_report_filters_by_date_range()
    {
        $user = User::factory()->create();
        $promotion = Promotion::factory()->create(['usage_count' => 0]);

        $this->travelTo(now()->subMonths(2));
        $oldOrder = $this->createDiscountedOrder($promotion, $user, 5000);
        $this->travelBack();

        $this->createDiscountedOrder($promotion, $user, 3000);

        $response = $this->actingAs($this->admin)
            ->getJson('/admin/promotions/reports/data?' . http_build_query([
                'start_date' => now()->subDays(1)->format('Y-m-d'),
                'end_date' => now()->addDays(1)->format('Y-m-d'),
            ]));

        $response->assertOk();
        $this->assertEquals(3000.00, $response->json('summary.total_discounts_given'));
        $this->assertEquals(1, $response->json('summary.orders_using_promotions'));
    }

    public function test_report_filters_by_promotion_id()
    {
        $user = User::factory()->create();
        $promo1 = Promotion::factory()->create(['usage_count' => 0]);
        $promo2 = Promotion::factory()->create(['usage_count' => 0]);

        $this->createDiscountedOrder($promo1, $user, 5000);
        $this->createDiscountedOrder($promo2, $user, 8000);

        $response = $this->actingAs($this->admin)
            ->getJson('/admin/promotions/reports/data?' . http_build_query([
                'promotion_id' => $promo1->id,
            ]));

        $response->assertOk();
        $this->assertEquals(5000.00, $response->json('summary.total_discounts_given'));
        $this->assertEquals(1, $response->json('summary.orders_using_promotions'));
    }

    public function test_report_filters_by_product_id()
    {
        $user = User::factory()->create();
        $product1 = Product::factory()->create(['price' => 50000]);
        $product2 = Product::factory()->create(['price' => 30000]);
        $promotion = Promotion::factory()->create(['usage_count' => 0]);

        $order1 = $this->createDiscountedOrder($promotion, $user, 5000);
        $order2 = $this->createDiscountedOrder($promotion, $user, 3000);

        $order1->items()->create(['product_id' => $product1->id, 'quantity' => 1, 'price' => 50000]);
        $order2->items()->create(['product_id' => $product2->id, 'quantity' => 1, 'price' => 30000]);

        $response = $this->actingAs($this->admin)
            ->getJson('/admin/promotions/reports/data?' . http_build_query([
                'product_id' => $product1->id,
            ]));

        $response->assertOk();
        $this->assertEquals(5000.00, $response->json('summary.total_discounts_given'));
    }

    public function test_report_filters_by_category_id()
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();
        $product = Product::factory()->create(['price' => 50000, 'category_id' => $category->id]);
        $otherCategory = Category::factory()->create();
        $otherProduct = Product::factory()->create(['price' => 30000, 'category_id' => $otherCategory->id]);
        $promotion = Promotion::factory()->create(['usage_count' => 0]);

        $order1 = $this->createDiscountedOrder($promotion, $user, 5000);
        $order2 = $this->createDiscountedOrder($promotion, $user, 3000);

        $order1->items()->create(['product_id' => $product->id, 'quantity' => 1, 'price' => 50000]);
        $order2->items()->create(['product_id' => $otherProduct->id, 'quantity' => 1, 'price' => 30000]);

        $response = $this->actingAs($this->admin)
            ->getJson('/admin/promotions/reports/data?' . http_build_query([
                'category_id' => $category->id,
            ]));

        $response->assertOk();
        $this->assertEquals(5000.00, $response->json('summary.total_discounts_given'));
    }

    public function test_report_monthly_comparison_accuracy()
    {
        $user = User::factory()->create();
        $promotion = Promotion::factory()->create(['usage_count' => 0]);

        $this->travelTo(now()->startOfMonth()->addDays(5));
        $this->createDiscountedOrder($promotion, $user, 5000);
        $this->travelBack();

        $this->travelTo(now()->subMonth()->startOfMonth()->addDays(3));
        $this->createDiscountedOrder($promotion, $user, 3000);
        $this->travelBack();

        $response = $this->actingAs($this->admin)
            ->getJson('/admin/promotions/reports/data');

        $response->assertOk();
        $monthly = $response->json('monthly_comparison');

        $this->assertNotEmpty($monthly);
    }

    public function test_report_type_breakdown()
    {
        $user = User::factory()->create();

        $percentagePromo = Promotion::factory()->percentage()->create([
            'value' => 10,
            'usage_count' => 0,
        ]);
        $fixedPromo = Promotion::factory()->fixed()->create([
            'value' => 5000,
            'usage_count' => 0,
        ]);

        $this->createDiscountedOrder($percentagePromo, $user, 5000);
        $this->createDiscountedOrder($fixedPromo, $user, 5000);

        $response = $this->actingAs($this->admin)
            ->getJson('/admin/promotions/reports/data');

        $response->assertOk();
        $typeBreakdown = $response->json('type_breakdown');

        $this->assertNotEmpty($typeBreakdown);

        $types = collect($typeBreakdown);
        $percentageType = $types->firstWhere('type', 'percentage');
        $fixedType = $types->firstWhere('type', 'fixed');

        $this->assertNotNull($percentageType);
        $this->assertNotNull($fixedType);
        $this->assertEquals(5000.00, (float) $percentageType['total_discount']);
        $this->assertEquals(5000.00, (float) $fixedType['total_discount']);
    }

    public function test_report_renders_page()
    {
        $promotion = Promotion::factory()->create();
        $response = $this->actingAs($this->admin)
            ->get('/admin/promotions/reports');

        $response->assertOk();
    }
}
