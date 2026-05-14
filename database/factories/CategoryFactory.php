<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        $categories = [
            ['name' => 'Electronics', 'description' => 'Latest gadgets, smartphones, laptops, and electronic devices'],
            ['name' => 'Fashion', 'description' => 'Clothing, shoes, accessories, and fashion items for men and women'],
            ['name' => 'Home & Kitchen', 'description' => 'Home appliances, kitchenware, furniture, and home decor'],
            ['name' => 'Beauty & Personal Care', 'description' => 'Skincare, makeup, haircare, and personal hygiene products'],
            ['name' => 'Sports & Outdoors', 'description' => 'Sports equipment, outdoor gear, fitness accessories, and athletic wear'],
            ['name' => 'Books & Media', 'description' => 'Books, e-books, music, movies, and educational materials'],
            ['name' => 'Toys & Games', 'description' => 'Children toys, board games, puzzles, and educational toys'],
            ['name' => 'Grocery & Food', 'description' => 'Food items, beverages, snacks, and daily essentials'],
            ['name' => 'Health & Wellness', 'description' => 'Vitamins, supplements, medical supplies, and health products'],
            ['name' => 'Automotive', 'description' => 'Car accessories, parts, tools, and automotive equipment'],
        ];

        $category = $this->faker->unique()->randomElement($categories);

        return [
            'name' => $category['name'],
            'description' => $category['description'],
        ];
    }
}