<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            // Global seeders (no tenant dependency) - required platform setup
            PermissionSeeder::class,         // Global permissions
            RoleAndPermissionSeeder::class,   // Global superadmin role + (optional) SuperAdmin Account
            PlanSeeder::class,               // Subscription plans
            PlatformSettingSeeder::class,     // Platform settings
            BillingPaymentMethodSeeder::class, // Platform billing methods
            CityTownshipSeeder::class,       // Global cities + townships (shared reference data)
            StoreCategorySeeder::class,       // Platform store categories (shared, slug-stable)
        ]);
    }
}