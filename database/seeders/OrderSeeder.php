<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::where('role', 'customer')->get();
        
        if ($users->isEmpty()) {
            $this->command->warn('No customer users found. Skipping order seeding.');
            return;
        }

        // Create 20 orders
        for ($i = 0; $i < 20; $i++) {
            $order = Order::factory()->create([
                'user_id' => $users->random()->id,
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
    }
}