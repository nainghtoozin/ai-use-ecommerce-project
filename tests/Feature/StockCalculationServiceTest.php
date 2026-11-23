<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\Tenant;
use App\Services\StockCalculationService;
use App\Services\StockMovementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockCalculationServiceTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Product $product;
    private ProductVariant $variant;
    private StockCalculationService $calculator;
    private StockMovementService $movements;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Stock Store',
            'slug' => 'stock-store',
            'store_url' => '/store/stock-store',
            'status' => 'active',
        ]);

        Tenant::setCurrent($this->tenant);

        $category = \App\Models\Category::create(['name' => 'Stock', 'slug' => 'stock']);

        $this->product = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Stock Product',
            'type' => 'single',
            'price' => 1000,
            'stock' => 10,
            'status' => 'active',
            'category_id' => $category->id,
        ]);

        $this->variant = ProductVariant::create([
            'product_id' => $this->product->id,
            'sku' => 'V1',
            'price' => 1200,
            'stock' => 10,
            'status' => 'active',
        ]);

        $this->calculator = app(StockCalculationService::class);
        $this->movements = app(StockMovementService::class);
    }

    public function test_no_movements_with_cached_stock_returns_cached_for_product(): void
    {
        $this->assertSame(10.0, $this->calculator->forProduct($this->product));
    }

    public function test_no_movements_with_cached_stock_returns_cached_for_variant(): void
    {
        $this->assertSame(10.0, $this->calculator->forVariant($this->variant));
    }

    public function test_movements_exist_sum_zero_returns_zero_for_product(): void
    {
        $this->movements->record($this->product, StockMovement::TYPE_ADJUSTMENT, 5);
        $this->movements->record($this->product, StockMovement::TYPE_ADJUSTMENT, -5);

        $this->assertSame(0.0, $this->calculator->forProduct($this->product));
    }

    public function test_movements_exist_sum_zero_returns_zero_for_variant(): void
    {
        $this->movements->record($this->product, StockMovement::TYPE_ADJUSTMENT, 5, $this->variant);
        $this->movements->record($this->product, StockMovement::TYPE_ADJUSTMENT, -5, $this->variant);

        $this->assertSame(0.0, $this->calculator->forVariant($this->variant));
    }

    public function test_movements_exist_sum_positive_returns_sum(): void
    {
        $this->movements->record($this->product, StockMovement::TYPE_ADJUSTMENT, 7);

        $this->assertSame(7.0, $this->calculator->forProduct($this->product));
    }

    public function test_movements_exist_sum_negative_preserved(): void
    {
        $this->movements->record($this->product, StockMovement::TYPE_ADJUSTMENT, -2);

        $this->assertSame(-2.0, $this->calculator->forProduct($this->product));
    }

    public function test_no_movements_zero_cached_returns_zero(): void
    {
        $this->product->update(['stock' => 0]);

        $this->assertSame(0.0, $this->calculator->forProduct($this->product->fresh()));
    }

    public function test_product_variant_isolation(): void
    {
        $otherVariant = ProductVariant::create([
            'product_id' => $this->product->id,
            'sku' => 'V2',
            'price' => 1300,
            'stock' => 0,
            'status' => 'active',
        ]);

        $this->movements->record($this->product, StockMovement::TYPE_ADJUSTMENT, 4, $this->variant);

        $this->assertSame(4.0, $this->calculator->forVariant($this->variant));
        $this->assertSame(0.0, $this->calculator->forVariant($otherVariant));
    }
}
