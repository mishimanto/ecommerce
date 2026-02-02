<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'sku',
        'category_id',
        'brand_id',
        'price',
        'compare_price',
        'cost_price',
        'stock',
        'description',
        'short_description',
        'specifications',
        'attributes',
        'tags',
        'weight',
        'dimensions',
        'is_featured',
        'is_trending',
        'status',
        'views',
        'average_rating',
        'total_reviews',
        'meta_title',
        'meta_description',
        'meta_keywords'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'compare_price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'stock' => 'integer',
        'weight' => 'decimal:2',
        'dimensions' => 'array',
        'specifications' => 'array',
        'attributes' => 'array',
        'is_featured' => 'boolean',
        'is_trending' => 'boolean',
        'views' => 'integer',
        'average_rating' => 'decimal:2',
        'total_reviews' => 'integer'
    ];

    // Relationships
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function images()
    {
        return $this->hasMany(ProductImage::class)->orderBy('order');
    }

    public function variants()
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function reviews()
    {
        return $this->hasMany(ProductReview::class)->where('status', 'approved');
    }

    public function allReviews()
    {
        return $this->hasMany(ProductReview::class);
    }

    public function orders()
    {
        return $this->belongsToMany(Order::class, 'order_items')
            ->withPivot('quantity', 'price', 'variant_id')
            ->withTimestamps();
    }

    public function wishlistedBy()
    {
        return $this->belongsToMany(User::class, 'wishlists');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeTrending($query)
    {
        return $query->where('is_trending', true);
    }

    public function scopeInStock($query)
    {
        return $query->where('stock', '>', 0);
    }

    public function scopeOutOfStock($query)
    {
        return $query->where('stock', 0);
    }

    public function scopeLowStock($query, $threshold = 10)
    {
        return $query->where('stock', '>', 0)
            ->where('stock', '<=', $threshold);
    }

    public function scopeByCategory($query, $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    public function scopeByBrand($query, $brandId)
    {
        return $query->where('brand_id', $brandId);
    }

    public function scopePriceRange($query, $min, $max)
    {
        return $query->whereBetween('price', [$min, $max]);
    }

    public function scopeSearch($query, $searchTerm)
    {
        return $query->where(function($q) use ($searchTerm) {
            $q->where('name', 'like', "%{$searchTerm}%")
              ->orWhere('description', 'like', "%{$searchTerm}%")
              ->orWhere('sku', 'like', "%{$searchTerm}%")
              ->orWhere('tags', 'like', "%{$searchTerm}%");
        });
    }

    // Methods
    public function getMainImageAttribute()
    {
        return $this->images->firstWhere('is_main', true) ?? $this->images->first();
    }

    public function getThumbnailAttribute()
    {
        $mainImage = $this->main_image;
        if ($mainImage) {
            $path = $mainImage->image_path;
            $filename = basename($path);
            return 'products/thumbs/' . $filename;
        }
        return null;
    }

    public function getDiscountPercentageAttribute()
    {
        if ($this->compare_price > $this->price) {
            return round((($this->compare_price - $this->price) / $this->compare_price) * 100);
        }
        return 0;
    }

    public function getStockStatusAttribute()
    {
        if ($this->stock === 0) {
            return 'out_of_stock';
        } elseif ($this->stock <= 10) {
            return 'low_stock';
        } else {
            return 'in_stock';
        }
    }

    public function getFormattedPriceAttribute()
    {
        return '$' . number_format($this->price, 2);
    }

    public function getFormattedComparePriceAttribute()
    {
        return $this->compare_price ? '$' . number_format($this->compare_price, 2) : null;
    }

    public function updateRating()
    {
        $reviews = $this->reviews();
        $this->average_rating = $reviews->avg('rating') ?? 0;
        $this->total_reviews = $reviews->count();
        $this->save();
    }

    public function hasVariants()
    {
        return $this->variants()->exists();
    }

    public function getAvailableVariants()
    {
        return $this->variants()->where('stock', '>', 0)->get();
    }

    public function getAttributeOptions($attributeName)
    {
        if (!$this->attributes || !isset($this->attributes[$attributeName])) {
            return collect();
        }

        return collect($this->attributes[$attributeName])->unique();
    }

    public function incrementStock($quantity)
    {
        $this->increment('stock', $quantity);
    }

    public function decrementStock($quantity)
    {
        if ($this->stock >= $quantity) {
            $this->decrement('stock', $quantity);
            return true;
        }
        return false;
    }

    // Event handlers
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($product) {
            if (empty($product->slug)) {
                $product->slug = \Str::slug($product->name);
            }
        });

        static::updating(function ($product) {
            if ($product->isDirty('name') && empty($product->slug)) {
                $product->slug = \Str::slug($product->name);
            }
        });
    }
}