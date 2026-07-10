<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Create customer users
        $customers = [
            ['name' => 'John Doe', 'email' => 'john@example.com'],
            ['name' => 'Jane Smith', 'email' => 'jane@example.com'],
            ['name' => 'Mike Johnson', 'email' => 'mike@example.com'],
            ['name' => 'Sarah Williams', 'email' => 'sarah@example.com'],
            ['name' => 'David Brown', 'email' => 'david@example.com'],
            ['name' => 'Emily Davis', 'email' => 'emily@example.com'],
            ['name' => 'Chris Wilson', 'email' => 'chris@example.com'],
            ['name' => 'Lisa Taylor', 'email' => 'lisa@example.com'],
            ['name' => 'Tom Anderson', 'email' => 'tom@example.com'],
            ['name' => 'Anna White', 'email' => 'anna@example.com'],
        ];

        foreach ($customers as $customer) {
            $user = User::updateOrCreate(
                ['email' => $customer['email']],
                [
                    'name' => $customer['name'],
                    'password' => bcrypt('password'),
                    'email_verified_at' => now(),
                ]
            );

            if (!$user->hasRole('customer')) {
                $user->assignRole('customer');
            }

            // When Account mode is active, create a matching Account so
            // customer credentials work with the accounts guard.
            if (config('identity.use_accounts')) {
                $account = Account::updateOrCreate(
                    ['email' => $customer['email']],
                    [
                        'password' => bcrypt('password'),
                        'email_verified_at' => now(),
                    ]
                );
                if (!$account->hasRole('customer')) {
                    $account->assignRole('customer');
                }
            }
        }
    }
}