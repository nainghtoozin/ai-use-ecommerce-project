<?php

namespace App\Services;

use App\Models\Promotion;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class PromotionService
{
    public function validatePromotion(?string $code, array $cartItems, ?int $userId = null, ?float $deliveryFee = 0): array
    {
        if (empty($code)) {
            return ['valid' => false, 'message' => 'No promotion code provided.'];
        }

        $promotion = Promotion::where('code', $code)->first();

        if (!$promotion) {
            return ['valid' => false, 'message' => 'Invalid promotion code.'];
        }

        $errors = $promotion->validateForUsage(
            $userId ? User::find($userId) : null,
            $cartItems,
            $deliveryFee
        );

        if (!empty($errors)) {
            return ['valid' => false, 'message' => $errors[0]];
        }

        $discount = $promotion->calculateDiscount($cartItems, $deliveryFee);

        return [
            'valid' => true,
            'promotion' => $promotion,
            'discount' => $discount,
            'message' => 'Promotion applied successfully!',
        ];
    }

    public function getApplicableAutoPromotions(array $cartItems, ?float $deliveryFee = 0): Collection
    {
        $promotions = Promotion::valid()->automatic()
            ->orderBy('priority', 'desc')
            ->get();

        $applicable = collect();

        foreach ($promotions as $promotion) {
            if (!$promotion->appliesToCart($cartItems)) {
                continue;
            }

            $discount = $promotion->calculateDiscount($cartItems, $deliveryFee);

            if ($discount > 0) {
                $applicable->push([
                    'promotion' => $promotion,
                    'discount' => $discount,
                ]);
            }
        }

        return $applicable;
    }

    public function getBestPromotion(array $cartItems, ?float $deliveryFee = 0): ?array
    {
        return $this->getApplicableAutoPromotions($cartItems, $deliveryFee)
            ->sortByDesc('discount')
            ->first();
    }

    public function applyPromotionToOrder(Order $order, Promotion $promotion, float $discountAmount): void
    {
        $order->update([
            'promotion_id' => $promotion->id,
            'promotion_code' => $promotion->code ?? 'AUTO',
        ]);

        $promotion->recordUsage($order, $order->user, $discountAmount);

        Log::info('Promotion applied to order', [
            'order_id' => $order->id,
            'promotion_id' => $promotion->id,
            'discount' => $discountAmount,
        ]);
    }

    public function calculateSubtotal(array $cartItems): float
    {
        return (float) collect($cartItems)->sum(fn($item) => ($item['price'] ?? 0) * ($item['quantity'] ?? 1));
    }

    public function generateCode(int $length = 8): string
    {
        return Promotion::generateCode($length);
    }

    public function getAutoPromotionsForCheckout(array $cartItems, ?float $deliveryFee = 0): Collection
    {
        return $this->getApplicableAutoPromotions($cartItems, $deliveryFee)
            ->map(fn($item) => [
                'id' => $item['promotion']->id,
                'name' => $item['promotion']->name,
                'code' => $item['promotion']->code,
                'type' => $item['promotion']->type,
                'value' => $item['promotion']->value,
                'discount' => $item['discount'],
                'description' => $item['promotion']->description,
            ]);
    }
}
