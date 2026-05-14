<?php

namespace Database\Factories;

use App\Models\Promotion;
use App\Models\PromotionUsage;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PromotionUsageFactory extends Factory
{
    protected $model = PromotionUsage::class;

    public function definition(): array
    {
        return [
            'promotion_id' => Promotion::factory(),
            'user_id' => User::factory(),
            'order_id' => Order::factory(),
            'discount_amount' => $this->faker->randomFloat(2, 1000, 50000),
            'used_at' => now(),
        ];
    }

    public function forPromotion(Promotion $promotion): static
    {
        return $this->state(fn(array $attributes) => [
            'promotion_id' => $promotion->id,
        ]);
    }

    public function forOrder(Order $order): static
    {
        return $this->state(fn(array $attributes) => [
            'order_id' => $order->id,
        ]);
    }

    public function forUser(User $user): static
    {
        return $this->state(fn(array $attributes) => [
            'user_id' => $user->id,
        ]);
    }

    public function withDiscount(float $amount): static
    {
        return $this->state(fn(array $attributes) => [
            'discount_amount' => $amount,
        ]);
    }
}
