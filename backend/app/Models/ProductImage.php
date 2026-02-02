<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'image_path',
        'is_main',
        'order',
        'alt_text',
        'caption'
    ];

    protected $casts = [
        'is_main' => 'boolean',
        'order' => 'integer'
    ];

    // Relationships
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    // Scopes
    public function scopeMain($query)
    {
        return $query->where('is_main', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('order');
    }

    // Methods
    public function getImageUrlAttribute()
    {
        return asset('storage/' . $this->image_path);
    }

    public function getThumbnailUrlAttribute()
    {
        $path = $this->image_path;
        $filename = basename($path);
        return asset('storage/products/thumbs/' . $filename);
    }

    public function markAsMain()
    {
        // Remove main status from other images of the same product
        $this->product->images()->update(['is_main' => false]);
        
        // Set this image as main
        $this->update(['is_main' => true]);
    }

    public function reorder($newOrder)
    {
        $productId = $this->product_id;
        $oldOrder = $this->order;

        if ($newOrder < $oldOrder) {
            // Moving up - increment orders between new and old
            $this->product->images()
                ->where('order', '>=', $newOrder)
                ->where('order', '<', $oldOrder)
                ->increment('order');
        } else {
            // Moving down - decrement orders between old and new
            $this->product->images()
                ->where('order', '>', $oldOrder)
                ->where('order', '<=', $newOrder)
                ->decrement('order');
        }

        $this->update(['order' => $newOrder]);
    }
}