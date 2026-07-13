<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Models\Tenant;
use App\Models\TenantMembership;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    public function run(): void
    {
        $useAccounts = config('identity.use_accounts');
        $defaultTenant = Tenant::where('slug', 'default')->first();

        if (!$defaultTenant) {
            $this->command->warn('No Default Store found. Skipping order seeding.');
            return;
        }

        // Resolve customer accounts for the Default Store
        $customerIds = $this->resolveCustomerIds($useAccounts, $defaultTenant);

        if (empty($customerIds)) {
            $this->command->warn('No customer accounts found. Skipping order seeding.');
            return;
        }

        // Create 20 orders
        for ($i = 0; $i < 20; $i++) {
            $order = Order::factory()->create([
                'user_id' => $this->faker->randomElement($customerIds),
                'user_type' => $useAccounts ? Account::class : User::class,
            ]);

            // Create 1-4 order items per order
            $itemCount = rand(1, 4);
            for ($j = 0; $j < $itemCount; $j++) {
                OrderItem::factory()->create([
                    'order_id' => $order->id,
                ]);
            }

            // Recalculate order totals
            $itemsTotal = $order->items->sum(function ($item) {
                return $item->price * $item->quantity;
            });

            $deliveryFee = $order->delivery_fee ?? 2000;
            $order->update([
                'subtotal' => $itemsTotal,
                'total_amount' => $itemsTotal + $deliveryFee,
            ]);
        }

        $this->command->info('Demo orders seeded successfully.');
    }

    private function resolveCustomerIds(bool $useAccounts, Tenant $tenant): array
    {
        if ($useAccounts) {
            // Account mode: get Account IDs with customer membership in Default Store
            return TenantMembership::where('tenant_id', $tenant->id)
                ->whereHas('role', fn ($q) => $q->where('name', 'customer'))
                ->pluck('account_id')
                ->toArray();
        }

        // Legacy mode: get User IDs with customer role
        return User::role('customer')
            ->where('tenant_id', $tenant->id)
            ->pluck('id')
            ->toArray();
    }
}
