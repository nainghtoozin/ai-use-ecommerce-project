<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    private array $defaultCategories = [
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

    public function run(): void
    {
        $tenants = Tenant::all();

        if ($tenants->isEmpty()) {
            $this->command->warn('No tenants found. Skipping category seeding.');
            return;
        }

        foreach ($tenants as $tenant) {
            foreach ($this->defaultCategories as $category) {
                Category::firstOrCreate(
                    ['tenant_id' => $tenant->id, 'name' => $category['name']],
                    [
                        'description' => $category['description'],
                    ]
                );
            }
        }

        $this->command->info('Default categories seeded successfully for all tenants.');
    }
}
