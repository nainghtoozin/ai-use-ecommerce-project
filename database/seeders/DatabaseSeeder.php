<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            // Permissions -> Roles & Super Admin -> Other seeders
            PermissionSeeder::class,
            RoleAndPermissionSeeder::class,
            PaymentMethodSeeder::class,
            LocationSeeder::class,
            UserSeeder::class,
            CategorySeeder::class,
            ProductSeeder::class,
            OrderSeeder::class,
        ]);
    }
}
