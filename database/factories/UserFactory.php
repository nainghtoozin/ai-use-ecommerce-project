<?php

namespace Database\Factories;

use Spatie\Permission\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'status' => 'active',
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function ($user) {
            if (!$user->hasAnyRole(Role::all())) {
                Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
                $user->assignRole('customer');
            }
        });
    }

    public function superadmin(): static
    {
        return $this->afterCreating(function ($user) {
            Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
            $user->syncRoles(['superadmin']);
        });
    }

    public function admin(): static
    {
        return $this->afterCreating(function ($user) {
            Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
            $user->syncRoles(['admin']);
        });
    }

    public function customer(): static
    {
        return $this->afterCreating(function ($user) {
            Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
            $user->syncRoles(['customer']);
        });
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
