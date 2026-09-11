<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\CodRule;
use App\Models\DeliveryPricing;
use App\Models\DeliveryService;
use App\Models\PackagingOption;
use App\Models\PaymentMethod;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class CheckoutDemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $tenants = Tenant::all();

        if ($tenants->isEmpty()) {
            $this->command->warn('No tenants found. Skipping checkout demo seeding.');
            return;
        }

        foreach ($tenants as $tenant) {
            $this->seedDeliveryServices($tenant);
            $this->seedDeliveryPricing($tenant);
            $this->seedPackagingOptions($tenant);
            $this->seedCodRules($tenant);
            $this->seedPaymentMethods($tenant);
        }

        $this->command->info('Checkout demo data seeded successfully.');
    }

    private function seedDeliveryServices(Tenant $tenant): void
    {
        $services = [
            ['name' => 'Economy Delivery', 'code' => 'economy', 'base_fee' => 1500, 'min_days' => 4, 'max_days' => 7, 'sort_order' => 1],
            ['name' => 'Standard Delivery', 'code' => 'standard', 'base_fee' => 2500, 'min_days' => 2, 'max_days' => 5, 'sort_order' => 2],
            ['name' => 'Express Delivery', 'code' => 'express', 'base_fee' => 5000, 'min_days' => 1, 'max_days' => 2, 'sort_order' => 3],
        ];

        foreach ($services as $data) {
            DeliveryService::withoutTenantScope()->firstOrCreate(
                ['tenant_id' => $tenant->id, 'code' => $data['code']],
                [
                    'name' => $data['name'],
                    'base_fee' => $data['base_fee'],
                    'min_days' => $data['min_days'],
                    'max_days' => $data['max_days'],
                    'sort_order' => $data['sort_order'],
                    'is_active' => true,
                ]
            );
        }
    }

    private function seedDeliveryPricing(Tenant $tenant): void
    {
        $cities = City::all();

        foreach ($cities as $city) {
            $cityKey = strtolower($city->name);

            $prices = match (true) {
                str_contains($cityKey, 'yangon') => ['economy' => 1000, 'standard' => 2000, 'express' => 4000],
                str_contains($cityKey, 'mandalay') || str_contains($cityKey, 'naypyidaw') => ['economy' => 1500, 'standard' => 2500, 'express' => 4500],
                default => ['economy' => 2000, 'standard' => 3500, 'express' => 5500],
            };

            $services = DeliveryService::withoutTenantScope()->where('tenant_id', $tenant->id)->get()->keyBy('code');

            foreach ($prices as $code => $fee) {
                $service = $services->get($code);
                if (!$service) continue;

                DeliveryPricing::firstOrCreate(
                    ['delivery_service_id' => $service->id, 'city_id' => $city->id],
                    [
                        'fee' => $fee,
                        'min_days' => $service->min_days,
                        'max_days' => $service->max_days,
                        'is_active' => true,
                    ]
                );
            }
        }
    }

    private function seedPackagingOptions(Tenant $tenant): void
    {
        $options = [
            ['name' => 'Standard Packaging', 'code' => 'standard', 'fee' => 0, 'description' => 'Default free packaging', 'sort_order' => 1],
            ['name' => 'Premium Packaging', 'code' => 'premium', 'fee' => 1000, 'description' => 'Secure bubble wrap packaging', 'sort_order' => 2],
            ['name' => 'Gift Packaging', 'code' => 'gift', 'fee' => 2000, 'description' => 'Gift wrap with ribbon', 'sort_order' => 3],
        ];

        foreach ($options as $data) {
            PackagingOption::withoutTenantScope()->firstOrCreate(
                ['tenant_id' => $tenant->id, 'code' => $data['code']],
                [
                    'name' => $data['name'],
                    'fee' => $data['fee'],
                    'description' => $data['description'],
                    'sort_order' => $data['sort_order'],
                    'is_active' => true,
                ]
            );
        }
    }

    private function seedCodRules(Tenant $tenant): void
    {
        $yangon = City::where('name', 'Yangon')->first();

        // Replace old default rule with Standard COD
        CodRule::withoutTenantScope()->where('tenant_id', $tenant->id)->where('name', 'Default COD Rule')->delete();

        CodRule::withoutTenantScope()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Standard COD'],
            [
                'min_order_amount' => null,
                'max_order_amount' => 1000000,
                'allowed_city_ids' => null,
                'excluded_city_ids' => null,
                'cod_fee' => 0,
                'apply_cod_fee_to_total' => false,
                'is_active' => true,
            ]
        );

        CodRule::withoutTenantScope()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'High Value COD'],
            [
                'min_order_amount' => 1000001,
                'max_order_amount' => null,
                'allowed_city_ids' => $yangon ? [$yangon->id] : null,
                'excluded_city_ids' => null,
                'cod_fee' => 2000,
                'apply_cod_fee_to_total' => true,
                'is_active' => true,
            ]
        );
    }

    private function seedPaymentMethods(Tenant $tenant): void
    {
        $methods = [
            ['name' => 'KBZ Pay', 'type' => 'bank_transfer', 'account_name' => 'Shop Name (DEMO)', 'account_number' => '09998887766', 'bank_name' => null],
            ['name' => 'Wave Money', 'type' => 'bank_transfer', 'account_name' => 'Shop Name (DEMO)', 'account_number' => '09998887766', 'bank_name' => null],
            ['name' => 'AYA Pay', 'type' => 'bank_transfer', 'account_name' => 'Shop Name (DEMO)', 'account_number' => '09998887766', 'bank_name' => null],
            ['name' => 'Bank Transfer', 'type' => 'bank_transfer', 'account_name' => 'Shop Name (DEMO)', 'account_number' => '1234567890', 'bank_name' => 'KBZ Bank (DEMO)'],
        ];

        foreach ($methods as $data) {
            PaymentMethod::withoutTenantScope()->firstOrCreate(
                ['tenant_id' => $tenant->id, 'name' => $data['name']],
                [
                    'type' => $data['type'],
                    'account_name' => $data['account_name'],
                    'account_number' => $data['account_number'],
                    'bank_name' => $data['bank_name'],
                    'is_active' => true,
                ]
            );
        }
    }
}