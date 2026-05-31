<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Wishlist;
use Inertia\Inertia;

class WishlistController extends Controller
{
    protected function wishlistEnabled(): bool
    {
        $settings = \App\Models\WebsiteInfo::getSettings();
        return (bool) ($settings->enable_wishlist ?? true);
    }

    public function index()
    {
        if (!$this->wishlistEnabled()) {
            return redirect()->route('home')->with('error', 'Wishlist feature is currently unavailable.');
        }

        $wishlistItems = auth()->user()->wishlistItems()
            ->with('product.category')
            ->latest()
            ->get();

        return Inertia::render('Client/Wishlist/Index', [
            'wishlistItems' => $wishlistItems,
        ]);
    }

    public function store(Product $product)
    {
        if (!$this->wishlistEnabled()) {
            return response()->json(['success' => false, 'message' => 'Wishlist feature is currently unavailable.'], 403);
        }

        $user = auth()->user();

        $exists = Wishlist::where('user_id', $user->id)
            ->where('product_id', $product->id)
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => true,
                'message' => 'Product is already in your wishlist.',
                'wishlist_count' => $user->wishlistItems()->count(),
            ]);
        }

        Wishlist::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Product added to wishlist.',
            'wishlist_count' => $user->wishlistItems()->count(),
        ]);
    }

    public function destroy(Product $product)
    {
        if (!$this->wishlistEnabled()) {
            return response()->json(['success' => false, 'message' => 'Wishlist feature is currently unavailable.'], 403);
        }

        $user = auth()->user();

        Wishlist::where('user_id', $user->id)
            ->where('product_id', $product->id)
            ->delete();

        return response()->json([
            'success' => true,
            'message' => 'Product removed from wishlist.',
            'wishlist_count' => $user->wishlistItems()->count(),
        ]);
    }

    public function moveToCart(Product $product)
    {
        if (!$this->wishlistEnabled()) {
            return response()->json(['success' => false, 'message' => 'Wishlist feature is currently unavailable.'], 403);
        }

        $cart = session()->get('cart', []);

        if (isset($cart[$product->id])) {
            $cart[$product->id]['quantity']++;
        } else {
            $cart[$product->id] = [
                'id' => $product->id,
                'product_id' => $product->id,
                'name' => $product->name,
                'price' => (float) $product->price,
                'photo1' => $product->photo1,
                'quantity' => 1,
            ];
        }

        session()->put('cart', $cart);

        $cartCount = array_sum(array_column($cart, 'quantity'));

        return response()->json([
            'success' => true,
            'message' => 'Product moved to cart.',
            'cart_count' => $cartCount,
        ]);
    }

    public function moveAllToCart()
    {
        if (!$this->wishlistEnabled()) {
            return response()->json(['success' => false, 'message' => 'Wishlist feature is currently unavailable.'], 403);
        }

        $user = auth()->user();
        $wishlistItems = $user->wishlistItems()->with('product')->get();

        if ($wishlistItems->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Your wishlist is empty.',
            ], 422);
        }

        $cart = session()->get('cart', []);

        foreach ($wishlistItems as $item) {
            $product = $item->product;
            if (!$product) continue;

            if (isset($cart[$product->id])) {
                $cart[$product->id]['quantity']++;
            } else {
                $cart[$product->id] = [
                    'id' => $product->id,
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'price' => (float) $product->price,
                    'photo1' => $product->photo1,
                    'quantity' => 1,
                ];
            }
        }

        session()->put('cart', $cart);

        $cartCount = array_sum(array_column($cart, 'quantity'));

        return response()->json([
            'success' => true,
            'message' => 'All items moved to cart.',
            'cart_count' => $cartCount,
        ]);
    }

    public function clear()
    {
        if (!$this->wishlistEnabled()) {
            return response()->json(['success' => false, 'message' => 'Wishlist feature is currently unavailable.'], 403);
        }

        auth()->user()->wishlistItems()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Wishlist cleared.',
            'wishlist_count' => 0,
        ]);
    }
}
