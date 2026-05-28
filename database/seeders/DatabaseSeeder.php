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
            WebsiteSettingsSeeder::class,
            PaymentMethodSeeder::class,
            LocationSeeder::class,
            UserSeeder::class,
            CategorySeeder::class,
            ProductSeeder::class,
            OrderSeeder::class,

            // Must run last: backfills any records created above that lack tenant_id
            TenantSeeder::class,
        ]);
    }
}
