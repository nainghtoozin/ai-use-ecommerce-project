<?php

namespace Database\Factories;

use App\Models\Promotion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PromotionFactory extends Factory
{
    protected $model = Promotion::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true),
            'code' => strtoupper($this->faker->bothify('PROMO-#####')),
            'description' => $this->faker->sentence(),
            'type' => Promotion::TYPE_PERCENTAGE,
            'value' => 10.00,
            'max_discount_amount' => null,
            'minimum_order_amount' => null,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
            'usage_limit' => null,
            'usage_count' => 0,
            'per_customer_limit' => null,
            'is_active' => true,
            'is_automatic' => false,
            'applies_to' => Promotion::APPLIES_ALL,
            'priority' => 0,
            'stackable' => false,
            'created_by' => null,
        ];
    }

    public function percentage(): static
    {
        return $this->state(fn(array $attributes) => [
            'type' => Promotion::TYPE_PERCENTAGE,
            'value' => $this->faker->randomFloat(2, 5, 50),
        ]);
    }

    public function fixed(): static
    {
        return $this->state(fn(array $attributes) => [
            'type' => Promotion::TYPE_FIXED,
            'value' => $this->faker->randomFloat(2, 1000, 50000),
        ]);
    }

    public function freeShipping(): static
    {
        return $this->state(fn(array $attributes) => [
            'type' => Promotion::TYPE_FREE_SHIPPING,
            'value' => 0,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn(array $attributes) => [
            'starts_at' => now()->subMonths(2),
            'ends_at' => now()->subMonth(),
            'is_active' => true,
        ]);
    }

    public function notStarted(): static
    {
        return $this->state(fn(array $attributes) => [
            'starts_at' => now()->addMonth(),
            'ends_at' => now()->addMonths(2),
            'is_active' => true,
        ]);
    }

    public function usageLimitReached(): static
    {
        return $this->state(fn(array $attributes) => [
            'usage_limit' => 100,
            'usage_count' => 100,
        ]);
    }

    public function withUsageLimit(int $limit): static
    {
        return $this->state(fn(array $attributes) => [
            'usage_limit' => $limit,
            'usage_count' => 0,
        ]);
    }

    public function automatic(): static
    {
        return $this->state(fn(array $attributes) => [
            'is_automatic' => true,
            'is_active' => true,
        ]);
    }

    public function manual(): static
    {
        return $this->state(fn(array $attributes) => [
            'is_automatic' => false,
            'code' => $this->faker->unique()->bothify('MANUAL-####'),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn(array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function forAllProducts(): static
    {
        return $this->state(fn(array $attributes) => [
            'applies_to' => Promotion::APPLIES_ALL,
        ]);
    }

    public function forSpecificProducts(): static
    {
        return $this->state(fn(array $attributes) => [
            'applies_to' => Promotion::APPLIES_PRODUCTS,
        ]);
    }

    public function forSpecificCategories(): static
    {
        return $this->state(fn(array $attributes) => [
            'applies_to' => Promotion::APPLIES_CATEGORIES,
        ]);
    }

    public function withMinOrder(float $amount): static
    {
        return $this->state(fn(array $attributes) => [
            'minimum_order_amount' => $amount,
        ]);
    }

    public function withMaxDiscount(float $amount): static
    {
        return $this->state(fn(array $attributes) => [
            'max_discount_amount' => $amount,
        ]);
    }

    public function withPerCustomerLimit(int $limit): static
    {
        return $this->state(fn(array $attributes) => [
            'per_customer_limit' => $limit,
        ]);
    }

    public function createdBy(User $user): static
    {
        return $this->state(fn(array $attributes) => [
            'created_by' => $user->id,
        ]);
    }
}
