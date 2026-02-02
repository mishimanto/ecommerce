<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CartController extends Controller
{
    public function getCart(Request $request)
    {
        $user = Auth::user();
        $cart = null;

        if ($user) {
            $cart = Cart::with('items.product.images')->firstOrCreate(['user_id' => $user->id]);
        } else {
            $cartId = $request->cookie('cart_id');
            if ($cartId) {
                $cart = Cart::with('items.product.images')->find($cartId);
            }
        }

        if (!$cart) {
            $cart = Cart::create();
            if (!$user) {
                return response()->json(['cart' => $cart])
                    ->cookie('cart_id', $cart->id, 60*24*30); // 30 days
            }
        }

        return response()->json(['cart' => $cart]);
    }

    public function addToCart(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
            'variant_id' => 'nullable|exists:product_variants,id'
        ]);

        $user = Auth::user();
        $product = Product::findOrFail($request->product_id);

        if ($user) {
            $cart = Cart::firstOrCreate(['user_id' => $user->id]);
        } else {
            $cartId = $request->cookie('cart_id');
            if ($cartId) {
                $cart = Cart::findOrCreate($cartId);
            } else {
                $cart = Cart::create();
            }
        }

        // Check if item already exists
        $existingItem = $cart->items()->where('product_id', $request->product_id)
            ->where('variant_id', $request->variant_id)
            ->first();

        if ($existingItem) {
            $existingItem->increment('quantity', $request->quantity);
        } else {
            CartItem::create([
                'cart_id' => $cart->id,
                'product_id' => $request->product_id,
                'variant_id' => $request->variant_id,
                'quantity' => $request->quantity,
                'price' => $product->price
            ]);
        }

        $cart->load('items.product.images');
        
        $response = response()->json([
            'cart' => $cart,
            'message' => 'Product added to cart'
        ]);

        if (!$user) {
            $response->cookie('cart_id', $cart->id, 60*24*30);
        }

        return $response;
    }

    public function updateCartItem(Request $request, $itemId)
    {
        $request->validate([
            'quantity' => 'required|integer|min:1'
        ]);

        $cartItem = CartItem::findOrFail($itemId);
        $cartItem->update(['quantity' => $request->quantity]);

        return response()->json([
            'message' => 'Cart updated successfully'
        ]);
    }

    public function removeFromCart($itemId)
    {
        CartItem::destroy($itemId);

        return response()->json([
            'message' => 'Item removed from cart'
        ]);
    }

    public function clearCart()
    {
        $user = Auth::user();
        
        if ($user) {
            $cart = Cart::where('user_id', $user->id)->first();
            if ($cart) {
                $cart->items()->delete();
            }
        }

        return response()->json([
            'message' => 'Cart cleared successfully'
        ]);
    }

    public function applyCoupon(Request $request)
    {
        $request->validate([
            'code' => 'required|string'
        ]);

        // Implement coupon logic here
        // Check coupon validity, calculate discount, etc.

        return response()->json([
            'message' => 'Coupon applied successfully',
            'discount' => 0 // Placeholder
        ]);
    }
}