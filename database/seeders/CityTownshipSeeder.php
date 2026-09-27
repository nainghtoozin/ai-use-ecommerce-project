<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Services\MyanmarLocationImportService;
use Illuminate\Database\Seeder;

class CityTownshipSeeder extends Seeder
{
    public function run(): void
    {
        $tenants = Tenant::all();

        if ($tenants->isEmpty()) {
            $this->command->warn('No tenants found. Skipping location seeding.');
            return;
        }

        $importer = app(MyanmarLocationImportService::class);

        foreach ($tenants as $tenant) {
            $importer->import($tenant);
        }

        $this->command->info('Tenant locations seeded successfully for all tenants.');
    }
}
