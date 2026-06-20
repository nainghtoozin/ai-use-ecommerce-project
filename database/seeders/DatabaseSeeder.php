<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            // Permissions -> Roles & Super Admin -> Other seeders
            // SYSTEM SEEDERS
            PermissionSeeder::class,
            RoleAndPermissionSeeder::class,
            PlanSeeder::class,
            LocationSeeder::class,

            // TENANT BOOTSTRAP CANDIDATES (move to TenantBootstrapService in future)
            WebsiteSettingsSeeder::class,
            PaymentMethodSeeder::class,
            CategorySeeder::class,
            UnitSeeder::class,
            BrandSeeder::class,

            // Must run last: backfills any records created above that lack tenant_id
            TenantSeeder::class,
        ]);
    }
}
