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

    public function validateCouponCode(?string $code, Collection $cartItems, ?int $userId = null, ?float $deliveryFee = 0): array
    {
        if (empty($code)) {
            return ['valid' => false, 'message' => 'No coupon code provided.'];
        }

        $promotion = Promotion::where('code', $code)->where('is_automatic', false)->first();

        if (!$promotion) {
            return ['valid' => false, 'message' => 'Invalid or expired coupon code.'];
        }

        $errors = $promotion->validateForUsage(
            $userId ? User::find($userId) : null,
            $cartItems->toArray(),
            $deliveryFee
        );

        if (!empty($errors)) {
            return ['valid' => false, 'message' => $errors[0]];
        }

        $discount = $promotion->calculateDiscount($cartItems->toArray(), $deliveryFee);

        return [
            'valid' => true,
            'promotion' => $promotion,
            'discount' => $discount,
            'message' => 'Coupon applied successfully!',
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

    public function applyCouponToOrder(Order $order, Promotion $promotion, float $discountAmount): void
    {
        $this->applyPromotionToOrder($order, $promotion, $discountAmount);
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

    public function canStackWith(Promotion $promotion, ?Promotion $existing = null): bool
    {
        if (!$promotion->stackable) {
            return false;
        }

        if ($existing && !$existing->stackable) {
            return false;
        }

        return true;
    }

    public function resolveBestDiscount(array $cartItems, ?float $deliveryFee = 0, ?string $code = null, ?int $userId = null): array
    {
        $codeDiscount = 0;
        $codePromotion = null;
        $autoDiscount = 0;
        $autoPromotion = null;

        if ($code) {
            $codeResult = $this->validateCouponCode($code, collect($cartItems), $userId, $deliveryFee);
            if ($codeResult['valid']) {
                $codeDiscount = $codeResult['discount'];
                $codePromotion = $codeResult['promotion'];
            }
        }

        $autoPromotions = $this->getApplicableAutoPromotions($cartItems, $deliveryFee);
        if ($autoPromotions->isNotEmpty()) {
            $best = $autoPromotions->sortByDesc('discount')->first();
            $autoDiscount = $best['discount'];
            $autoPromotion = $best['promotion'];
        }

        if ($codePromotion && $autoPromotion) {
            if ($this->canStackWith($codePromotion, $autoPromotion)) {
                return [
                    'discount' => $codeDiscount + $autoDiscount,
                    'code_promotion' => $codePromotion,
                    'code_discount' => $codeDiscount,
                    'auto_promotion' => $autoPromotion,
                    'auto_discount' => $autoDiscount,
                ];
            }

            if ($codeDiscount >= $autoDiscount) {
                return [
                    'discount' => $codeDiscount,
                    'code_promotion' => $codePromotion,
                    'code_discount' => $codeDiscount,
                    'auto_promotion' => null,
                    'auto_discount' => 0,
                ];
            }

            return [
                'discount' => $autoDiscount,
                'code_promotion' => null,
                'code_discount' => 0,
                'auto_promotion' => $autoPromotion,
                'auto_discount' => $autoDiscount,
            ];
        }

        if ($codePromotion) {
            return [
                'discount' => $codeDiscount,
                'code_promotion' => $codePromotion,
                'code_discount' => $codeDiscount,
                'auto_promotion' => null,
                'auto_discount' => 0,
            ];
        }

        if ($autoPromotion) {
            return [
                'discount' => $autoDiscount,
                'code_promotion' => null,
                'code_discount' => 0,
                'auto_promotion' => $autoPromotion,
                'auto_discount' => $autoDiscount,
            ];
        }

        return [
            'discount' => 0,
            'code_promotion' => null,
            'code_discount' => 0,
            'auto_promotion' => null,
            'auto_discount' => 0,
        ];
    }
}
