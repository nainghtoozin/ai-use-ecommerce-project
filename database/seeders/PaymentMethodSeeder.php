<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;

class PaymentMethodSeeder extends Seeder
{
    public function run(): void
    {
        $methods = [
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

        foreach ($methods as $method) {
            PaymentMethod::create($method);
        }
    }
}
