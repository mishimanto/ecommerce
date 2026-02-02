<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cart extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'session_id',
        'coupon_code',
        'discount_amount',
        'shipping_cost',
        'tax_amount'
    ];

    protected $casts = [
        'discount_amount' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'tax_amount' => 'decimal:2'
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(CartItem::class);
    }

    // Methods
    public function getSubtotalAttribute()
    {
        return $this->items->sum(function($item) {
            return $item->price * $item->quantity;
        });
    }

    public function getTotalAttribute()
    {
        return $this->subtotal - $this->discount_amount + $this->shipping_cost + $this->tax_amount;
    }

    public function getItemCountAttribute()
    {
        return $this->items->sum('quantity');
    }

    public function getUniqueItemCountAttribute()
    {
        return $this->items->count();
    }

    public function isEmpty()
    {
        return $this->items->isEmpty();
    }

    public function hasProduct($productId, $variantId = null)
    {
        return $this->items()
            ->where('product_id', $productId)
            ->where('variant_id', $variantId)
            ->exists();
    }

    public function getProductQuantity($productId, $variantId = null)
    {
        $item = $this->items()
            ->where('product_id', $productId)
            ->where('variant_id', $variantId)
            ->first();
        
        return $item ? $item->quantity : 0;
    }

    public function addItem($productId, $quantity = 1, $variantId = null, $price = null)
    {
        $product = Product::findOrFail($productId);
        
        if ($price === null) {
            $price = $product->price;
        }

        $existingItem = $this->items()
            ->where('product_id', $productId)
            ->where('variant_id', $variantId)
            ->first();

        if ($existingItem) {
            $existingItem->increment('quantity', $quantity);
            return $existingItem;
        }

        return $this->items()->create([
            'product_id' => $productId,
            'variant_id' => $variantId,
            'quantity' => $quantity,
            'price' => $price
        ]);
    }

    public function updateItem($itemId, $quantity)
    {
        $item = $this->items()->findOrFail($itemId);
        $item->update(['quantity' => $quantity]);
        return $item;
    }

    public function removeItem($itemId)
    {
        $this->items()->where('id', $itemId)->delete();
    }

    public function removeProduct($productId, $variantId = null)
    {
        $query = $this->items()->where('product_id', $productId);
        
        if ($variantId !== null) {
            $query->where('variant_id', $variantId);
        }
        
        $query->delete();
    }

    public function clear()
    {
        $this->items()->delete();
        $this->update([
            'coupon_code' => null,
            'discount_amount' => 0,
            'shipping_cost' => 0,
            'tax_amount' => 0
        ]);
    }

    public function applyCoupon($couponCode)
    {
        // Implement coupon logic
        $coupon = Coupon::where('code', $couponCode)
            ->where('status', 'active')
            ->where('start_date', '<=', now())
            ->where('end_date', '>=', now())
            ->first();

        if (!$coupon) {
            return false;
        }

        // Calculate discount
        $discount = $this->calculateDiscount($coupon);
        
        if ($discount > 0) {
            $this->update([
                'coupon_code' => $couponCode,
                'discount_amount' => $discount
            ]);
            return true;
        }

        return false;
    }

    private function calculateDiscount($coupon)
    {
        switch ($coupon->type) {
            case 'percentage':
                return ($coupon->value / 100) * $this->subtotal;
            case 'fixed':
                return min($coupon->value, $this->subtotal);
            default:
                return 0;
        }
    }

    public function removeCoupon()
    {
        $this->update([
            'coupon_code' => null,
            'discount_amount' => 0
        ]);
    }

    public function calculateShipping($addressId = null)
    {
        // Implement shipping calculation
        // This is a simplified version
        $shippingCost = 0;
        
        if ($addressId) {
            // Calculate based on address
            $shippingCost = 100; // Default shipping cost
        }

        $this->update(['shipping_cost' => $shippingCost]);
        return $shippingCost;
    }

    public function calculateTax()
    {
        // Calculate tax based on subtotal and shipping
        $taxRate = 0.05; // 5% tax
        $taxAmount = ($this->subtotal + $this->shipping_cost) * $taxRate;
        
        $this->update(['tax_amount' => $taxAmount]);
        return $taxAmount;
    }

    public function mergeWithSessionCart($sessionCart)
    {
        if ($sessionCart) {
            foreach ($sessionCart->items as $item) {
                $this->addItem(
                    $item->product_id,
                    $item->quantity,
                    $item->variant_id,
                    $item->price
                );
            }
            $sessionCart->clear();
        }
    }

    // Scopes
    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeBySession($query, $sessionId)
    {
        return $query->where('session_id', $sessionId);
    }

    public function scopeAbandoned($query, $hours = 24)
    {
        return $query->where('updated_at', '<', now()->subHours($hours))
            ->whereHas('items')
            ->whereNull('user_id');
    }
}