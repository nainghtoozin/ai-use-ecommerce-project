<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Demo Data Seeder
 *
 * Seeds demo products and orders for development/testing.
 * Customer accounts + memberships are created by MembershipSeeder.
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            ProductSeeder::class,
            OrderSeeder::class,
        ]);

        $this->command->info('Demo data seeded successfully.');
    }
}
