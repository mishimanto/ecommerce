<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CartItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'cart_id',
        'product_id',
        'variant_id',
        'quantity',
        'price'
    ];

    protected $casts = [
        'quantity' => 'integer',
        'price' => 'decimal:2'
    ];

    // Relationships
    public function cart()
    {
        return $this->belongsTo(Cart::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    // Scopes
    public function scopeByCart($query, $cartId)
    {
        return $query->where('cart_id', $cartId);
    }

    public function scopeByProduct($query, $productId)
    {
        return $query->where('product_id', $productId);
    }

    // Methods
    public function getTotalAttribute()
    {
        return $this->price * $this->quantity;
    }

    public function getFormattedPriceAttribute()
    {
        return '$' . number_format($this->price, 2);
    }

    public function getFormattedTotalAttribute()
    {
        return '$' . number_format($this->total, 2);
    }

    public function updateQuantity($newQuantity)
    {
        if ($newQuantity < 1) {
            $this->delete();
            return;
        }

        $this->update(['quantity' => $newQuantity]);
    }

    public function incrementQuantity($amount = 1)
    {
        $this->increment('quantity', $amount);
    }

    public function decrementQuantity($amount = 1)
    {
        $newQuantity = $this->quantity - $amount;
        
        if ($newQuantity < 1) {
            $this->delete();
        } else {
            $this->update(['quantity' => $newQuantity]);
        }
    }

    public function getProductName()
    {
        if ($this->variant) {
            return $this->product->name . ' - ' . $this->variant->getCombinationName();
        }
        return $this->product->name;
    }

    public function getImageUrl()
    {
        if ($this->variant && $this->variant->image) {
            return $this->variant->getImageUrlAttribute();
        }
        
        $mainImage = $this->product->main_image;
        return $mainImage ? $mainImage->image_url : asset('images/placeholder.jpg');
    }

    public function getThumbnailUrl()
    {
        if ($this->variant && $this->variant->image) {
            $path = $this->variant->image;
            $filename = basename($path);
            return asset('storage/products/thumbs/' . $filename);
        }
        
        $mainImage = $this->product->main_image;
        return $mainImage ? $mainImage->thumbnail_url : asset('images/placeholder-thumb.jpg');
    }

    public function isAvailable()
    {
        if ($this->variant) {
            return $this->variant->isAvailable();
        }
        return $this->product->stock > 0;
    }

    public function getAvailableStock()
    {
        if ($this->variant) {
            return $this->variant->stock;
        }
        return $this->product->stock;
    }

    public function validateStock()
    {
        $availableStock = $this->getAvailableStock();
        
        if ($this->quantity > $availableStock) {
            if ($availableStock === 0) {
                throw new \Exception('Product is out of stock');
            } else {
                throw new \Exception("Only {$availableStock} units available");
            }
        }
        
        return true;
    }
}