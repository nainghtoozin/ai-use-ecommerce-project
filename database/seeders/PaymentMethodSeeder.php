<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class PaymentMethodSeeder extends Seeder
{
    private array $defaultMethods = [
        [
            'name' => 'KBZ Pay',
            'account_name' => 'Shop Name',
            'account_number' => '09998887766',
            'bank_name' => null,
            'is_active' => true,
        ],
        [
            'name' => 'WavePay',
            'account_name' => 'Shop Name',
            'account_number' => '09998887766',
            'bank_name' => null,
            'is_active' => true,
        ],
        [
            'name' => 'AYA Pay',
            'account_name' => 'Shop Name',
            'account_number' => '09998887766',
            'bank_name' => null,
            'is_active' => true,
        ],
        [
            'name' => 'Bank Transfer',
            'account_name' => 'Shop Name',
            'account_number' => '1234567890',
            'bank_name' => 'KBZ Bank',
            'is_active' => true,
        ],
        [
            'name' => 'Cash on Delivery',
            'account_name' => 'N/A',
            'account_number' => 'N/A',
            'bank_name' => null,
            'is_active' => false,
        ],
    ];

    public function run(): void
    {
        $tenants = Tenant::all();

        if ($tenants->isEmpty()) {
            $this->command->warn('No tenants found. Skipping payment method seeding.');
            return;
        }

        foreach ($tenants as $tenant) {
            foreach ($this->defaultMethods as $method) {
                PaymentMethod::firstOrCreate(
                    ['tenant_id' => $tenant->id, 'name' => $method['name']],
                    [
                        'account_name' => $method['account_name'],
                        'account_number' => $method['account_number'],
                        'bank_name' => $method['bank_name'],
                        'is_active' => $method['is_active'],
                    ]
                );
            }
        }

        $this->command->info('Default payment methods seeded successfully for all tenants.');
    }
}
