<?php

namespace Database\Seeders;

use App\Models\StoreCategory;
use Illuminate\Database\Seeder;

class StoreCategorySeeder extends Seeder
{
    private array $storeCategories = [
        ['name' => 'Electronics', 'slug' => 'electronics', 'description' => 'Gadgets, smartphones, laptops, and electronic devices', 'sort_order' => 1],
        ['name' => 'Fashion', 'slug' => 'fashion', 'description' => 'Clothing, shoes, accessories, and fashion items', 'sort_order' => 2],
        ['name' => 'Home & Kitchen', 'slug' => 'home-kitchen', 'description' => 'Home appliances, kitchenware, furniture, and home decor', 'sort_order' => 3],
        ['name' => 'Beauty & Personal Care', 'slug' => 'beauty-personal-care', 'description' => 'Skincare, makeup, haircare, and personal hygiene products', 'sort_order' => 4],
        ['name' => 'Sports & Outdoors', 'slug' => 'sports-outdoors', 'description' => 'Sports equipment, outdoor gear, and fitness accessories', 'sort_order' => 5],
        ['name' => 'Books & Media', 'slug' => 'books-media', 'description' => 'Books, e-books, music, movies, and educational materials', 'sort_order' => 6],
        ['name' => 'Toys & Games', 'slug' => 'toys-games', 'description' => 'Children toys, board games, puzzles, and educational toys', 'sort_order' => 7],
        ['name' => 'Grocery & Food', 'slug' => 'grocery-food', 'description' => 'Food items, beverages, snacks, and daily essentials', 'sort_order' => 8],
        ['name' => 'Health & Wellness', 'slug' => 'health-wellness', 'description' => 'Vitamins, supplements, medical supplies, and health products', 'sort_order' => 9],
        ['name' => 'Automotive', 'slug' => 'automotive', 'description' => 'Car accessories, parts, tools, and automotive equipment', 'sort_order' => 10],
    ];

    public function run(): void
    {
        foreach ($this->storeCategories as $category) {
            StoreCategory::updateOrCreate(
                ['slug' => $category['slug']],
                [
                    'name' => $category['name'],
                    'description' => $category['description'],
                    'is_active' => true,
                    'sort_order' => $category['sort_order'],
                ]
            );
        }

        $this->command?->info('Store categories seeded successfully.');
    }
}
