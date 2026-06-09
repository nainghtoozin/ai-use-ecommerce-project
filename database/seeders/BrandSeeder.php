<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\Brand;
use Illuminate\Database\Seeder;

class BrandSeeder extends Seeder
{
    private array $defaultBrands = [
        ['name' => 'Samsung', 'slug' => 'samsung', 'description' => 'South Korean multinational electronics corporation'],
        ['name' => 'Apple', 'slug' => 'apple', 'description' => 'American multinational technology company'],
        ['name' => 'Xiaomi', 'slug' => 'xiaomi', 'description' => 'Chinese electronics and software company'],
        ['name' => 'Nike', 'slug' => 'nike', 'description' => 'American multinational athletic footwear and apparel corporation'],
        ['name' => 'Adidas', 'slug' => 'adidas', 'description' => 'German multinational sportswear and footwear corporation'],
        ['name' => 'Sony', 'slug' => 'sony', 'description' => 'Japanese multinational conglomerate corporation'],
    ];

    public function run(): void
    {
        $tenants = Tenant::all();

        if ($tenants->isEmpty()) {
            $this->command->warn('No tenants found. Skipping brand seeding.');
            return;
        }

        foreach ($tenants as $tenant) {
            foreach ($this->defaultBrands as $brand) {
                Brand::firstOrCreate(
                    ['tenant_id' => $tenant->id, 'name' => $brand['name']],
                    [
                        'slug' => $brand['slug'],
                        'description' => $brand['description'],
                        'is_active' => true,
                    ]
                );
            }
        }

        $this->command->info('Default brands seeded successfully for all tenants.');
    }
}
