<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Coupon;
use App\Services\CouponService;
use App\Services\PromotionService;
use App\Services\ProductService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class CartController extends Controller
{
    public function __construct(
        private readonly CouponService $couponService,
        private readonly PromotionService $promotionService,
        private readonly ProductService $productService
    ) {}

    public function index()
    {
        $cart = session()->get('cart', []);
        $cartItems = $this->formatCartItems($cart);
        $subtotal = (float) array_sum(array_map(fn($item) => $item['price'] * $item['quantity'], $cartItems));

        $appliedPromotion = session('applied_promotion');
        $appliedCoupon = session('applied_coupon');

        $totalDiscount = 0;
        if ($appliedPromotion) {
            $totalDiscount += (float) ($appliedPromotion['discount'] ?? 0);
        }
        if ($appliedCoupon) {
            $totalDiscount += (float) ($appliedCoupon['discount'] ?? 0);
        }

        return Inertia::render('Client/Cart/Index', [
            'cartItems' => $cartItems,
            'subtotal' => $subtotal,
            'appliedPromotion' => $appliedPromotion,
            'appliedCoupon' => $appliedCoupon,
            'totalDiscount' => $totalDiscount,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'variant_id' => 'nullable|exists:product_variants,id',
            'quantity' => 'required|integer|min:1',
        ]);

        $cart = session()->get('cart', []);
        $productId = $request->product_id;
        $variantId = $request->variant_id;
        $quantity = $request->quantity;

        $purchasable = $this->productService->resolvePurchasable($productId, $variantId);

        if ($purchasable['stock'] < $quantity) {
            return response()->json([
                'error' => 'Insufficient stock. Available: ' . $purchasable['stock'],
            ], 422);
        }

        $cartKey = $this->buildCartKey($productId, $variantId);
        $productName = $purchasable['name'];

        if (isset($cart[$cartKey])) {
            $newQty = $cart[$cartKey]['quantity'] + $quantity;
            if ($purchasable['stock'] < $newQty) {
                return response()->json([
                    'error' => 'Insufficient stock. Available: ' . $purchasable['stock'],
                ], 422);
            }
            $cart[$cartKey]['quantity'] = $newQty;
        } else {
            $cart[$cartKey] = [
                'id' => $purchasable['product_id'],
                'product_id' => $purchasable['product_id'],
                'variant_id' => $purchasable['variant_id'],
                'name' => $purchasable['name'],
                'price' => $purchasable['price'],
                'photo1' => $purchasable['photo1'],
                'quantity' => $quantity,
            ];
        }

        session()->put('cart', $cart);
        
        $cartCount = array_sum(array_column($cart, 'quantity'));

        if ($request->header('X-Inertia')) {
            return redirect()->route('cart.index')
                ->with('success', '"' . $productName . '" added to cart successfully.');
        }

        return response()->json([
            'success' => '"' . $productName . '" added to cart successfully.',
            'cart_count' => $cartCount,
        ]);
    }

    public function update(Request $request, $cartKey)
    {
        $request->validate([
            'quantity' => 'required|integer|min:0',
        ]);

        $cart = session()->get('cart', []);

        if (isset($cart[$cartKey])) {
            if ($request->quantity <= 0) {
                $itemName = $cart[$cartKey]['name'] ?? 'Item';
                unset($cart[$cartKey]);
                session()->put('cart', $cart);
                
                $cartItems = $this->formatCartItems($cart);
                $subtotal = (float) array_sum(array_map(fn($item) => $item['price'] * $item['quantity'], $cartItems));

                return response()->json([
                    'success' => '"' . $itemName . '" removed from cart.',
                    'cartItems' => $cartItems,
                    'subtotal' => $subtotal,
                ]);
            } else {
                $item = $cart[$cartKey];
                $purchasable = $this->productService->resolvePurchasable(
                    $item['product_id'],
                    $item['variant_id'] ?? null
                );

                if ($purchasable['stock'] < $request->quantity) {
                    return response()->json([
                        'error' => 'Insufficient stock. Available: ' . $purchasable['stock'],
                    ], 422);
                }

                $cart[$cartKey]['quantity'] = $request->quantity;
                session()->put('cart', $cart);
                
                $cartItems = $this->formatCartItems($cart);
                $subtotal = (float) array_sum(array_map(fn($item) => $item['price'] * $item['quantity'], $cartItems));

                return response()->json([
                    'cartItems' => $cartItems,
                    'subtotal' => $subtotal,
                ]);
            }
        }

        return response()->json(['error' => 'Item not found in cart.'], 422);
    }

    public function destroy($cartKey)
    {
        $cart = session()->get('cart', []);
        
        if (isset($cart[$cartKey])) {
            $itemName = $cart[$cartKey]['name'] ?? 'Item';
            unset($cart[$cartKey]);
            session()->put('cart', $cart);
            
            $cartItems = $this->formatCartItems($cart);
            $subtotal = (float) array_sum(array_map(fn($item) => $item['price'] * $item['quantity'], $cartItems));

            return response()->json([
                'success' => '"' . $itemName . '" removed from cart.',
                'cartItems' => $cartItems,
                'subtotal' => $subtotal,
            ]);
        }

        return response()->json(['error' => 'Item not found in cart.'], 422);
    }

    public function clear(Request $request)
    {
        session()->forget('cart');

        if ($request->header('X-Inertia')) {
            return redirect()->back()->with('success', 'Cart cleared successfully.');
        }

        return response()->json([
            'success' => 'Cart cleared successfully.',
            'cartItems' => [],
            'subtotal' => 0,
        ]);
    }

    public function count()
    {
        $cart = session()->get('cart', []);
        $count = array_sum(array_column($cart, 'quantity'));
        return response()->json(['count' => $count]);
    }

    public function applyCoupon(Request $request)
    {
        $request->validate(['code' => 'required|string|max:50']);

        $cart = session()->get('cart', []);
        $cartItems = collect($this->formatCartItems($cart));
        $deliveryFee = (float) $request->input('delivery_fee', 0);

        $result = $this->couponService->validateCoupon(
            $request->code,
            $cartItems,
            auth()->id(),
            $deliveryFee
        );

        if (!$result['valid']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], 422);
        }

        session()->put('applied_coupon', [
            'code' => $result['coupon']->code,
            'coupon_id' => $result['coupon']->id,
            'type' => $result['coupon']->type,
            'discount' => $result['discount'],
            'name' => $result['coupon']->name,
        ]);

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'discount' => $result['discount'],
            'coupon_code' => $result['coupon']->code,
            'coupon_name' => $result['coupon']->name,
        ]);
    }

    public function removeCoupon()
    {
        session()->forget('applied_coupon');

        return response()->json([
            'success' => true,
            'message' => 'Coupon removed.',
        ]);
    }

    public function applyPromotion(Request $request)
    {
        $request->validate(['code' => 'required|string|max:50']);

        $cart = session()->get('cart', []);
        $cartItems = $this->formatCartItems($cart);
        $deliveryFee = (float) $request->input('delivery_fee', 0);

        $result = $this->promotionService->validatePromotion(
            $request->code,
            $cartItems,
            auth()->id(),
            $deliveryFee
        );

        if (!$result['valid']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], 422);
        }

        session()->put('applied_promotion', [
            'code' => $result['promotion']->code,
            'promotion_id' => $result['promotion']->id,
            'type' => $result['promotion']->type,
            'discount' => $result['discount'],
            'name' => $result['promotion']->name,
        ]);

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'discount' => $result['discount'],
            'promotion_code' => $result['promotion']->code,
            'promotion_name' => $result['promotion']->name,
        ]);
    }

    public function removePromotion()
    {
        session()->forget('applied_promotion');

        return response()->json([
            'success' => true,
            'message' => 'Promotion removed.',
        ]);
    }

    /**
     * Build a unique session key for a cart item.
     * Format: "p{product_id}_v{variant_id}" for variable, "p{product_id}" for single.
     */
    private function buildCartKey(int $productId, ?int $variantId = null): string
    {
        return 'p' . $productId . '_v' . ($variantId ?? '0');
    }

    private function formatCartItems(array $cart): array
    {
        if (empty($cart)) {
            return [];
        }

        $items = [];
        foreach ($cart as $cartKey => $item) {
            $productId = $item['product_id'];
            $variantId = $item['variant_id'] ?? null;

            $product = Product::select(['id', 'name', 'price', 'photo1', 'type'])->find($productId);
            if (!$product) {
                continue;
            }

            $variantName = null;
            $price = $item['price'];

            if ($variantId) {
                $variant = ProductVariant::select(['id', 'price', 'sku', 'attributes'])->find($variantId);
                if ($variant) {
                    $price = (float) ($variant->price ?? $product->price);
                    $variantName = $variant->label;
                }
            }

            $items[] = [
                'cart_key' => $cartKey,
                'id' => $product->id,
                'variant_id' => $variantId,
                'name' => $product->name,
                'variant_name' => $variantName,
                'price' => (float) $price,
                'photo1' => $product->photo1,
                'quantity' => $item['quantity'],
            ];
        }
        return $items;
    }
}