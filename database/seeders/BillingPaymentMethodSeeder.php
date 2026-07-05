<?php

namespace Database\Seeders;

use App\Models\BillingPaymentMethod;
use Illuminate\Database\Seeder;

class BillingPaymentMethodSeeder extends Seeder
{
    public function run(): void
    {
        if (BillingPaymentMethod::count() > 0) {
            return;
        }

        BillingPaymentMethod::create([
            'display_name' => 'KBZ Bank',
            'type' => 'bank_transfer',
            'account_name' => 'Demo Company',
            'account_number' => '0123456789',
            'bank_name' => 'KBZ Bank',
            'branch' => 'Main Branch',
            'instructions' => 'Please use your reference number as the transfer remark.',
            'currency' => 'MMK',
            'sort_order' => 1,
            'is_default' => true,
            'is_active' => true,
            'supports_manual_payment' => true,
            'supports_gateway' => false,
        ]);
    }
}
