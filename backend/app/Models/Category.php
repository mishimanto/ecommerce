<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'parent_id',
        'description',
        'image',
        'banner',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'status',
        'order',
        'featured'
    ];

    protected $casts = [
        'featured' => 'boolean',
        'order' => 'integer'
    ];

    // Relationships
    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id')->orderBy('order');
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeFeatured($query)
    {
        return $query->where('featured', true);
    }

    public function scopeMainCategories($query)
    {
        return $query->whereNull('parent_id');
    }

    public function scopeWithChildren($query)
    {
        return $query->with(['children' => function($q) {
            $q->active()->orderBy('order');
        }]);
    }

    // Methods
    public function getProductCountAttribute()
    {
        return $this->products()->count();
    }

    public function getAllProductsCountAttribute()
    {
        $count = $this->products()->count();
        foreach ($this->children as $child) {
            $count += $child->all_products_count;
        }
        return $count;
    }

    public function getBreadcrumbAttribute()
    {
        $breadcrumb = [];
        $category = $this;

        while ($category) {
            $breadcrumb[] = [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug
            ];
            $category = $category->parent;
        }

        return array_reverse($breadcrumb);
    }

    public function isMainCategory()
    {
        return is_null($this->parent_id);
    }

    public function hasChildren()
    {
        return $this->children()->exists();
    }

    public function getImageUrlAttribute()
    {
        return $this->image ? asset('storage/' . $this->image) : asset('images/default-category.jpg');
    }

    public function getBannerUrlAttribute()
    {
        return $this->banner ? asset('storage/' . $this->banner) : null;
    }

    // Event handlers
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($category) {
            if (empty($category->slug)) {
                $category->slug = \Str::slug($category->name);
            }
        });

        static::updating(function ($category) {
            if ($category->isDirty('name') && empty($category->slug)) {
                $category->slug = \Str::slug($category->name);
            }
        });
    }
}