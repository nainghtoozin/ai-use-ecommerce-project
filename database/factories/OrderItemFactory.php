<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    public function definition(): array
    {
        $products = Product::where('stock', '>', 0)->pluck('id')->toArray();
        
        if (empty($products)) {
            return [];
        }

        $productId = $this->faker->randomElement($products);
        $product = Product::find($productId);
        $quantity = $this->faker->numberBetween(1, 5);
        $price = $product?->price ?? $this->faker->numberBetween(10000, 100000);

        return [
            'product_id' => $productId,
            'quantity' => $quantity,
            'price' => $price,
        ];
    }
}