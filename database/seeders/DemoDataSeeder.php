<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            ProductSeeder::class,
            OrderSeeder::class,
        ]);

        $this->command->info('Demo data seeded successfully.');
    }
}
