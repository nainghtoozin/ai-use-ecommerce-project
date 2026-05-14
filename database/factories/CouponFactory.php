<?php

namespace Database\Factories;

use App\Models\Coupon;
use Illuminate\Database\Eloquent\Factories\Factory;

class CouponFactory extends Factory
{
    protected $model = Coupon::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->sentence(),
            'code' => strtoupper($this->faker->bothify('COUPON-#####')),
            'type' => Coupon::TYPE_PERCENTAGE,
            'discount_value' => 10.00,
            'min_order_amount' => null,
            'discount_cap' => null,
            'usage_limit' => null,
            'per_customer_limit' => null,
            'used_count' => 0,
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addMonth(),
            'priority' => 0,
            'is_stackable' => false,
        ];
    }

    public function percentage(): static
    {
        return $this->state(fn(array $attributes) => [
            'type' => Coupon::TYPE_PERCENTAGE,
            'discount_value' => $this->faker->randomFloat(2, 5, 50),
        ]);
    }

    public function fixed(): static
    {
        return $this->state(fn(array $attributes) => [
            'type' => Coupon::TYPE_FIXED_AMOUNT,
            'discount_value' => $this->faker->randomFloat(2, 1000, 50000),
        ]);
    }

    public function freeShipping(): static
    {
        return $this->state(fn(array $attributes) => [
            'type' => Coupon::TYPE_FREE_SHIPPING,
            'discount_value' => 0,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn(array $attributes) => [
            'starts_at' => now()->subMonths(2),
            'expires_at' => now()->subMonth(),
        ]);
    }

    public function notStarted(): static
    {
        return $this->state(fn(array $attributes) => [
            'starts_at' => now()->addMonth(),
            'expires_at' => now()->addMonths(2),
        ]);
    }

    public function usageLimitReached(): static
    {
        return $this->state(fn(array $attributes) => [
            'usage_limit' => 100,
            'used_count' => 100,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn(array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function withMinOrder(float $amount): static
    {
        return $this->state(fn(array $attributes) => [
            'min_order_amount' => $amount,
        ]);
    }

    public function withDiscountCap(float $amount): static
    {
        return $this->state(fn(array $attributes) => [
            'discount_cap' => $amount,
        ]);
    }
}
