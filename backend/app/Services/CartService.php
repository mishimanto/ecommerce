<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\Coupon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class CartService
{
    protected $cart;
    protected $userId;
    protected $sessionId;

    public function __construct($userId = null, $sessionId = null)
    {
        $this->userId = $userId;
        $this->sessionId = $sessionId;
        $this->loadCart();
    }

    private function loadCart()
    {
        if ($this->userId) {
            $this->cart = Cart::firstOrCreate(['user_id' => $this->userId]);
        } elseif ($this->sessionId) {
            $this->cart = Cart::firstOrCreate(['session_id' => $this->sessionId]);
        } else {
            // Create a new cart for guest
            $this->cart = Cart::create();
            $this->sessionId = $this->cart->session_id;
        }
    }

    public function getCart()
    {
        $this->cart->load(['items.product.images', 'items.variant']);
        return $this->cart;
    }

    public function addItem($productId, $quantity = 1, $variantId = null, $price = null)
    {
        $product = Product::findOrFail($productId);

        // Check stock
        if ($product->stock < $quantity) {
            throw new \Exception('Insufficient stock');
        }

        // Get price
        if ($price === null) {
            $price = $product->price;
        }

        // Check if item already exists
        $existingItem = $this->cart->items()
            ->where('product_id', $productId)
            ->where('variant_id', $variantId)
            ->first();

        if ($existingItem) {
            $newQuantity = $existingItem->quantity + $quantity;
            
            // Check stock for updated quantity
            if ($product->stock < $newQuantity) {
                throw new \Exception('Insufficient stock for requested quantity');
            }

            $existingItem->update(['quantity' => $newQuantity]);
            $item = $existingItem;
        } else {
            $item = CartItem::create([
                'cart_id' => $this->cart->id,
                'product_id' => $productId,
                'variant_id' => $variantId,
                'quantity' => $quantity,
                'price' => $price
            ]);
        }

        $this->updateCartTotals();

        return $item;
    }

    public function updateItem($itemId, $quantity)
    {
        if ($quantity < 1) {
            $this->removeItem($itemId);
            return;
        }

        $item = CartItem::with('product')->findOrFail($itemId);

        // Check stock
        if ($item->product->stock < $quantity) {
            throw new \Exception('Insufficient stock');
        }

        $item->update(['quantity' => $quantity]);
        $this->updateCartTotals();

        return $item;
    }

    public function removeItem($itemId)
    {
        CartItem::destroy($itemId);
        $this->updateCartTotals();
    }

    public function clearCart()
    {
        $this->cart->items()->delete();
        $this->updateCartTotals();
    }

    public function updateCartTotals()
    {
        $subtotal = $this->calculateSubtotal();
        $discount = $this->cart->discount_amount ?? 0;
        $shipping = $this->cart->shipping_cost ?? 0;
        $tax = $this->calculateTax($subtotal, $shipping);

        $total = $subtotal - $discount + $shipping + $tax;

        $this->cart->update([
            'subtotal' => $subtotal,
            'discount_amount' => $discount,
            'shipping_cost' => $shipping,
            'tax_amount' => $tax,
            'total' => $total
        ]);
    }

    private function calculateSubtotal()
    {
        return $this->cart->items()->sum(DB::raw('price * quantity'));
    }

    private function calculateTax($subtotal, $shipping)
    {
        $taxRate = config('app.tax_rate', 0.05); // 5% default tax
        return ($subtotal + $shipping) * $taxRate;
    }

    public function applyCoupon($couponCode)
    {
        $coupon = Coupon::where('code', strtoupper($couponCode))
            ->where('status', 'active')
            ->where('start_date', '<=', now())
            ->where('end_date', '>=', now())
            ->first();

        if (!$coupon) {
            throw new \Exception('Invalid coupon code');
        }

        // Check usage limit
        if ($coupon->usage_limit && $coupon->used_count >= $coupon->usage_limit) {
            throw new \Exception('Coupon usage limit reached');
        }

        // Check per user limit
        if ($this->userId && $coupon->usage_limit_per_user) {
            $userUsage = DB::table('coupon_usage_history')
                ->where('coupon_id', $coupon->id)
                ->where('user_id', $this->userId)
                ->count();
            
            if ($userUsage >= $coupon->usage_limit_per_user) {
                throw new \Exception('You have reached the maximum usage limit for this coupon');
            }
        }

        // Check minimum order amount
        $subtotal = $this->calculateSubtotal();
        if ($coupon->min_order_amount && $subtotal < $coupon->min_order_amount) {
            throw new \Exception('Minimum order amount not met');
        }

        // Calculate discount
        $discount = $this->calculateDiscount($coupon, $subtotal);

        if ($discount <= 0) {
            throw new \Exception('Coupon cannot be applied to this cart');
        }

        // Apply discount
        $this->cart->update([
            'coupon_code' => $coupon->code,
            'discount_amount' => $discount
        ]);

        $this->updateCartTotals();

        return [
            'coupon' => $coupon,
            'discount' => $discount
        ];
    }

    private function calculateDiscount($coupon, $subtotal)
    {
        if ($coupon->type === 'percentage') {
            $discount = ($coupon->value / 100) * $subtotal;
        } else {
            $discount = $coupon->value;
        }

        // Apply max discount limit
        if ($coupon->max_discount_amount && $discount > $coupon->max_discount_amount) {
            $discount = $coupon->max_discount_amount;
        }

        // Ensure discount doesn't exceed subtotal
        if ($discount > $subtotal) {
            $discount = $subtotal;
        }

        return round($discount, 2);
    }

    public function removeCoupon()
    {
        $this->cart->update([
            'coupon_code' => null,
            'discount_amount' => 0
        ]);

        $this->updateCartTotals();
    }

    public function calculateShipping($address = null)
    {
        // Default shipping cost
        $shippingCost = config('app.default_shipping_cost', 10);

        // Calculate based on address if provided
        if ($address) {
            // Implement shipping calculation based on address
            // This could integrate with shipping APIs
            $shippingCost = $this->calculateShippingByAddress($address);
        }

        // Apply free shipping threshold
        $freeShippingThreshold = config('app.free_shipping_threshold', 100);
        $subtotal = $this->calculateSubtotal();
        
        if ($subtotal >= $freeShippingThreshold) {
            $shippingCost = 0;
        }

        $this->cart->update(['shipping_cost' => $shippingCost]);
        $this->updateCartTotals();

        return $shippingCost;
    }

    private function calculateShippingByAddress($address)
    {
        // Implement shipping calculation logic
        // This is a placeholder
        return 10;
    }

    public function mergeCarts($sourceCart)
    {
        if (!$sourceCart || $sourceCart->id === $this->cart->id) {
            return;
        }

        foreach ($sourceCart->items as $item) {
            try {
                $this->addItem(
                    $item->product_id,
                    $item->quantity,
                    $item->variant_id,
                    $item->price
                );
            } catch (\Exception $e) {
                // Skip items that can't be added
                continue;
            }
        }

        // Clear the source cart
        $sourceCart->items()->delete();
        $sourceCart->delete();
    }

    public function getItemCount()
    {
        return $this->cart->items()->sum('quantity');
    }

    public function getUniqueItemCount()
    {
        return $this->cart->items()->count();
    }

    public function isEmpty()
    {
        return $this->cart->items()->count() === 0;
    }

    public function getSummary()
    {
        return [
            'items' => $this->cart->items()->count(),
            'quantity' => $this->cart->items()->sum('quantity'),
            'subtotal' => $this->cart->subtotal,
            'discount' => $this->cart->discount_amount,
            'shipping' => $this->cart->shipping_cost,
            'tax' => $this->cart->tax_amount,
            'total' => $this->cart->total,
            'coupon_code' => $this->cart->coupon_code
        ];
    }

    public function validateCart()
    {
        $errors = [];

        foreach ($this->cart->items as $item) {
            $product = $item->product;
            
            // Check if product exists and is active
            if (!$product || $product->status !== 'active') {
                $errors[] = "Product '{$product->name}' is no longer available";
                $item->delete();
                continue;
            }

            // Check stock
            if ($product->stock < $item->quantity) {
                if ($product->stock === 0) {
                    $errors[] = "Product '{$product->name}' is out of stock";
                    $item->delete();
                } else {
                    $errors[] = "Only {$product->stock} units of '{$product->name}' are available";
                    $item->update(['quantity' => $product->stock]);
                }
            }
        }

        if (!empty($errors)) {
            $this->updateCartTotals();
        }

        return $errors;
    }
}s