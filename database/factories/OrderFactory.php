<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\City;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\Township;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        $useAccounts = config('identity.use_accounts');
        $cities = City::pluck('id')->toArray();
        $paymentMethods = PaymentMethod::where('is_active', true)->pluck('id')->toArray();

        if (empty($cities) || empty($paymentMethods)) {
            return [];
        }

        // Resolve customer IDs based on identity mode
        $customerIds = $this->resolveCustomerIds($useAccounts);

        if (empty($customerIds)) {
            return [];
        }

        $customerId = $this->faker->randomElement($customerIds);
        $cityId = $this->faker->randomElement($cities);
        $city = City::find($cityId);
        $townships = Township::where('city_id', $cityId)->pluck('id')->toArray();
        $townshipId = !empty($townships) ? $this->faker->randomElement($townships) : null;

        $subtotal = $this->faker->numberBetween(10000, 500000);
        $deliveryFee = $city->delivery_fee ?? 2000;
        $totalAmount = $subtotal + $deliveryFee;

        $paymentStatuses = ['unpaid', 'paid', 'verified', 'rejected'];
        $orderStatuses = ['pending', 'verified', 'confirmed', 'shipped', 'delivered', 'cancelled'];

        $paymentStatus = $this->faker->randomElement($paymentStatuses);
        $orderStatus = $this->faker->randomElement($orderStatuses);

        // Resolve customer name
        $customerName = $this->resolveCustomerName($useAccounts, $customerId);

        return [
            'user_id' => $customerId,
            'user_type' => $useAccounts ? Account::class : User::class,
            'customer_name' => $customerName,
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'phone' => $this->faker->phoneNumber(),
            'email' => $this->faker->email(),
            'address' => $this->faker->address(),
            'city_id' => $cityId,
            'township_id' => $townshipId,
            'postal_code' => $this->faker->numerify('#####'),
            'notes' => $this->faker->optional()->sentence(),
            'payment_method_id' => $this->faker->randomElement($paymentMethods),
            'subtotal' => $subtotal,
            'total_amount' => $totalAmount,
            'delivery_fee' => $deliveryFee,
            'paid_amount' => $paymentStatus !== 'unpaid' ? $totalAmount : 0,
            'payment_status' => $paymentStatus,
            'order_status' => $orderStatus,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'order_status' => 'pending',
            'payment_status' => 'unpaid',
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'order_status' => 'delivered',
            'payment_status' => 'verified',
        ]);
    }

    private function resolveCustomerIds(bool $useAccounts): array
    {
        $defaultTenant = Tenant::where('slug', 'default')->first();

        if (!$defaultTenant) {
            return [];
        }

        if ($useAccounts) {
            return TenantMembership::where('tenant_id', $defaultTenant->id)
                ->whereHas('role', fn ($q) => $q->where('name', 'customer'))
                ->pluck('account_id')
                ->toArray();
        }

        return User::role('customer')
            ->where('tenant_id', $defaultTenant->id)
            ->pluck('id')
            ->toArray();
    }

    private function resolveCustomerName(bool $useAccounts, int $customerId): string
    {
        if ($useAccounts) {
            return Account::find($customerId)?->getDisplayName() ?? 'Customer';
        }

        return User::find($customerId)?->name ?? 'Customer';
    }
}
