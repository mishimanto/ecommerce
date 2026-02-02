<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_id',
        'variant_id',
        'quantity',
        'price',
        'total',
        'product_name',
        'variant_name',
        'sku'
    ];

    protected $casts = [
        'quantity' => 'integer',
        'price' => 'decimal:2',
        'total' => 'decimal:2'
    ];

    // Relationships
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function review()
    {
        return $this->hasOne(ProductReview::class, 'order_item_id');
    }

    // Scopes
    public function scopeByOrder($query, $orderId)
    {
        return $query->where('order_id', $orderId);
    }

    public function scopeByProduct($query, $productId)
    {
        return $query->where('product_id', $productId);
    }

    // Methods
    public function getFullProductNameAttribute()
    {
        $name = $this->product_name;
        
        if ($this->variant_name) {
            $name .= ' - ' . $this->variant_name;
        }
        
        return $name;
    }

    public function getFormattedPriceAttribute()
    {
        return '$' . number_format($this->price, 2);
    }

    public function getFormattedTotalAttribute()
    {
        return '$' . number_format($this->total, 2);
    }

    public function getImageUrl()
    {
        if ($this->variant && $this->variant->image) {
            return $this->variant->getImageUrlAttribute();
        }
        
        $product = $this->product;
        if ($product) {
            $mainImage = $product->main_image;
            return $mainImage ? $mainImage->image_url : asset('images/placeholder.jpg');
        }
        
        return asset('images/placeholder.jpg');
    }

    public function getThumbnailUrl()
    {
        if ($this->variant && $this->variant->image) {
            $path = $this->variant->image;
            $filename = basename($path);
            return asset('storage/products/thumbs/' . $filename);
        }
        
        $product = $this->product;
        if ($product) {
            $mainImage = $product->main_image;
            return $mainImage ? $mainImage->thumbnail_url : asset('images/placeholder-thumb.jpg');
        }
        
        return asset('images/placeholder-thumb.jpg');
    }

    public function canBeReviewed()
    {
        // Check if order is delivered
        if ($this->order->status !== 'delivered') {
            return false;
        }

        // Check if already reviewed
        if ($this->review) {
            return false;
        }

        // Check if delivered within review period (e.g., 30 days)
        if (!$this->order->delivered_at || $this->order->delivered_at->diffInDays(now()) > 30) {
            return false;
        }

        return true;
    }

    public function createReview($data)
    {
        if (!$this->canBeReviewed()) {
            throw new \Exception('Cannot review this item');
        }

        return ProductReview::create([
            'product_id' => $this->product_id,
            'user_id' => $this->order->user_id,
            'order_id' => $this->order_id,
            'order_item_id' => $this->id,
            'rating' => $data['rating'],
            'title' => $data['title'],
            'comment' => $data['comment'],
            'pros' => $data['pros'] ?? null,
            'cons' => $data['cons'] ?? null,
            'verified_purchase' => true,
            'status' => 'approved' // Auto-approve verified purchases
        ]);
    }

    public function refund($quantity = null)
    {
        $refundQuantity = $quantity ?? $this->quantity;
        
        if ($refundQuantity > $this->quantity) {
            throw new \Exception('Refund quantity cannot exceed purchased quantity');
        }

        // Update refunded quantity
        $this->increment('refunded_quantity', $refundQuantity);

        // Restore product stock
        if ($this->variant) {
            $this->variant->incrementStock($refundQuantity);
        } else {
            $this->product->incrementStock($refundQuantity);
        }

        return $refundQuantity;
    }

    public function getRefundableQuantity()
    {
        return $this->quantity - ($this->refunded_quantity ?? 0);
    }

    public function isFullyRefunded()
    {
        return ($this->refunded_quantity ?? 0) >= $this->quantity;
    }

    public function saveProductSnapshot()
    {
        if ($this->product) {
            $this->update([
                'product_name' => $this->product->name,
                'sku' => $this->product->sku
            ]);
        }

        if ($this->variant) {
            $this->update([
                'variant_name' => $this->variant->getCombinationName(),
                'price' => $this->variant->price
            ]);
        } else {
            $this->update([
                'price' => $this->product->price
            ]);
        }

        $this->update([
            'total' => $this->price * $this->quantity
        ]);
    }
}