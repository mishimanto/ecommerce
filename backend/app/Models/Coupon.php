<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'type',
        'value',
        'min_order_amount',
        'max_discount_amount',
        'start_date',
        'end_date',
        'usage_limit',
        'usage_limit_per_user',
        'description',
        'status',
        'applicable_to'
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'min_order_amount' => 'decimal:2',
        'max_discount_amount' => 'decimal:2',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'usage_limit' => 'integer',
        'usage_limit_per_user' => 'integer'
    ];

    // Relationships
    public function products()
    {
        return $this->belongsToMany(Product::class, 'coupon_product');
    }

    public function categories()
    {
        return $this->belongsToMany(Category::class, 'coupon_category');
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'coupon_user');
    }

    public function usageHistory()
    {
        return $this->hasMany(CouponUsage::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active')
            ->where('start_date', '<=', now())
            ->where('end_date', '>=', now());
    }

    public function scopeExpired($query)
    {
        return $query->where('end_date', '<', now());
    }

    public function scopeUpcoming($query)
    {
        return $query->where('start_date', '>', now());
    }

    public function scopeByCode($query, $code)
    {
        return $query->where('code', strtoupper($code));
    }

    public function scopeValidForUser($query, $userId)
    {
        return $query->where(function($q) use ($userId) {
            $q->whereDoesntHave('users')
              ->orWhereHas('users', function($q) use ($userId) {
                  $q->where('user_id', $userId);
              });
        });
    }

    // Methods
    public function getFormattedValueAttribute()
    {
        if ($this->type === 'percentage') {
            return $this->value . '%';
        }
        return '$' . number_format($this->value, 2);
    }

    public function getFormattedMinOrderAttribute()
    {
        return $this->min_order_amount ? '$' . number_format($this->min_order_amount, 2) : 'No minimum';
    }

    public function getFormattedMaxDiscountAttribute()
    {
        return $this->max_discount_amount ? '$' . number_format($this->max_discount_amount, 2) : 'No limit';
    }

    public function getStatusBadgeAttribute()
    {
        $badges = [
            'active' => 'bg-green-100 text-green-800',
            'inactive' => 'bg-gray-100 text-gray-800',
            'expired' => 'bg-red-100 text-red-800'
        ];

        return $badges[$this->status] ?? 'bg-gray-100 text-gray-800';
    }

    public function getUsedCountAttribute()
    {
        return $this->usageHistory()->count();
    }

    public function getRemainingUsesAttribute()
    {
        if (!$this->usage_limit) {
            return null;
        }
        return max(0, $this->usage_limit - $this->used_count);
    }

    public function isActive()
    {
        return $this->status === 'active' && 
               $this->start_date <= now() && 
               $this->end_date >= now();
    }

    public function isExpired()
    {
        return $this->end_date < now();
    }

    public function isUpcoming()
    {
        return $this->start_date > now();
    }

    public function calculateDiscount($amount)
    {
        if (!$this->isActive()) {
            return 0;
        }

        if ($this->min_order_amount && $amount < $this->min_order_amount) {
            return 0;
        }

        if ($this->type === 'percentage') {
            $discount = ($this->value / 100) * $amount;
        } else {
            $discount = $this->value;
        }

        // Apply max discount limit
        if ($this->max_discount_amount && $discount > $this->max_discount_amount) {
            $discount = $this->max_discount_amount;
        }

        // Ensure discount doesn't exceed order amount
        if ($discount > $amount) {
            $discount = $amount;
        }

        return round($discount, 2);
    }

    public function canBeUsedByUser($userId)
    {
        // Check if user is restricted
        if ($this->users()->exists() && !$this->users()->where('user_id', $userId)->exists()) {
            return false;
        }

        // Check per user usage limit
        if ($this->usage_limit_per_user) {
            $userUsage = $this->usageHistory()
                ->where('user_id', $userId)
                ->count();
            
            if ($userUsage >= $this->usage_limit_per_user) {
                return false;
            }
        }

        return true;
    }

    public function canBeAppliedToOrder($amount, $productIds = [], $categoryIds = [])
    {
        if (!$this->isActive()) {
            return false;
        }

        if ($this->min_order_amount && $amount < $this->min_order_amount) {
            return false;
        }

        // Check product restrictions
        if ($this->applicable_to !== 'all_products') {
            if ($this->applicable_to === 'specific_products') {
                if (empty($productIds) || !$this->products()->whereIn('id', $productIds)->exists()) {
                    return false;
                }
            } elseif ($this->applicable_to === 'specific_categories') {
                if (empty($categoryIds) || !$this->categories()->whereIn('id', $categoryIds)->exists()) {
                    return false;
                }
            }
        }

        return true;
    }

    public function recordUsage($orderId, $userId, $discountAmount)
    {
        return CouponUsage::create([
            'coupon_id' => $this->id,
            'order_id' => $orderId,
            'user_id' => $userId,
            'discount_amount' => $discountAmount,
            'used_at' => now()
        ]);
    }

    public function getApplicableToText()
    {
        $text = [
            'all_products' => 'All Products',
            'specific_products' => 'Specific Products',
            'specific_categories' => 'Specific Categories'
        ];

        return $text[$this->applicable_to] ?? $this->applicable_to;
    }

    public function getProductsList()
    {
        return $this->products->pluck('name')->join(', ');
    }

    public function getCategoriesList()
    {
        return $this->categories->pluck('name')->join(', ');
    }
}