<?php

namespace Database\Seeders;

use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TenantSeeder extends Seeder
{
    public function run(): void
    {
        $this->ensureDemoTenants();
        $this->clearCaches();
    }

    private function ensureDemoTenants(): void
    {
        // ─────────────────────────────────────────────────────────────
        // DEMO TENANTS
        //
        // Per Platform Identity Design Lock:
        //   - Each tenant must have exactly one owner (created by MembershipSeeder)
        //   - Default Store is a migration artifact — kept for backward compatibility
        //   - Test tenants are demo data only — must NOT exist in production
        // ─────────────────────────────────────────────────────────────

        $tenants = [
            [
                'name' => 'Default Store',
                'slug' => 'default',
                'domain' => parse_url(config('app.url'), PHP_URL_HOST) ?? 'localhost',
                'email' => 'owner@defaultstore.com',
                'status' => 'active',
            ],
            [
                'name' => 'Khine Electronics',
                'slug' => 'khine',
                'domain' => 'khine.localhost',
                'email' => 'owner@khine.com',
                'status' => 'active',
            ],
            [
                'name' => 'Gadget World',
                'slug' => 'gadget',
                'domain' => 'gadget.localhost',
                'email' => 'owner@gadget.com',
                'status' => 'active',
            ],
        ];

        foreach ($tenants as $data) {
            Tenant::updateOrCreate(
                ['slug' => $data['slug']],
                [
                    'name' => $data['name'],
                    'domain' => $data['domain'],
                    'email' => $data['email'],
                    'status' => $data['status'],
                ]
            );

            $this->command?->info("  Tenant '{$data['name']}' ensured.");
        }
    }

    private function clearCaches(): void
    {
        Tenant::clearDefaultCache();
        $this->command?->info('Tenant cache cleared.');
    }
}
