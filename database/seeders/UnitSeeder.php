<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\Unit;
use Illuminate\Database\Seeder;

class UnitSeeder extends Seeder
{
    private array $defaultUnits = [
        ['name' => 'Piece', 'short_name' => 'pcs', 'description' => 'Individual pieces or items'],
        ['name' => 'Kilogram', 'short_name' => 'kg', 'description' => 'Weight in kilograms'],
        ['name' => 'Gram', 'short_name' => 'g', 'description' => 'Weight in grams'],
        ['name' => 'Liter', 'short_name' => 'L', 'description' => 'Volume in liters'],
        ['name' => 'Milliliter', 'short_name' => 'mL', 'description' => 'Volume in milliliters'],
        ['name' => 'Meter', 'short_name' => 'm', 'description' => 'Length in meters'],
        ['name' => 'Centimeter', 'short_name' => 'cm', 'description' => 'Length in centimeters'],
        ['name' => 'Box', 'short_name' => 'box', 'description' => 'Box or carton'],
        ['name' => 'Pack', 'short_name' => 'pk', 'description' => 'Pack of items'],
        ['name' => 'Dozen', 'short_name' => 'doz', 'description' => '12 pieces'],
        ['name' => 'Pair', 'short_name' => 'pr', 'description' => 'Pairs (e.g. shoes, socks)'],
        ['name' => 'Set', 'short_name' => 'set', 'description' => 'Set of items'],
        ['name' => 'Bottle', 'short_name' => 'btl', 'description' => 'Bottled items'],
        ['name' => 'Bag', 'short_name' => 'bag', 'description' => 'Bagged items'],
    ];

    public function run(): void
    {
        $tenants = Tenant::all();

        if ($tenants->isEmpty()) {
            $this->command->warn('No tenants found. Skipping unit seeding.');
            return;
        }

        foreach ($tenants as $tenant) {
            foreach ($this->defaultUnits as $unit) {
                Unit::firstOrCreate(
                    ['tenant_id' => $tenant->id, 'name' => $unit['name']],
                    [
                        'short_name' => $unit['short_name'],
                        'description' => $unit['description'],
                        'is_active' => true,
                    ]
                );
            }
        }

        $this->command->info('Default units seeded successfully for all tenants.');
    }
}
