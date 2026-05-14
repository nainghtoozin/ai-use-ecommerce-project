<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Create admin user
        if (!User::where('email', 'admin@shop.com')->exists()) {
            User::create([
                'name' => 'Admin User',
                'email' => 'admin@shop.com',
                'password' => bcrypt('password'),
                'role' => 'admin',
                'email_verified_at' => now(),
            ]);
        }

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
            if (!User::where('email', $customer['email'])->exists()) {
                User::create([
                    'name' => $customer['name'],
                    'email' => $customer['email'],
                    'password' => bcrypt('password'),
                    'role' => 'customer',
                    'email_verified_at' => now(),
                ]);
            }
        }
    }
}