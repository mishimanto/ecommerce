<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Wishlist extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'product_id'
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    // Scopes
    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    // Methods
    public static function addToWishlist($userId, $productId)
    {
        return self::firstOrCreate([
            'user_id' => $userId,
            'product_id' => $productId
        ]);
    }

    public static function removeFromWishlist($userId, $productId)
    {
        self::where('user_id', $userId)
            ->where('product_id', $productId)
            ->delete();
    }

    public static function getUserWishlist($userId)
    {
        return self::with('product.images')
            ->where('user_id', $userId)
            ->get()
            ->pluck('product');
    }

    public static function getWishlistCount($userId)
    {
        return self::where('user_id', $userId)->count();
    }

    public static function isInWishlist($userId, $productId)
    {
        return self::where('user_id', $userId)
            ->where('product_id', $productId)
            ->exists();
    }

    public static function clearWishlist($userId)
    {
        self::where('user_id', $userId)->delete();
    }

    // Guest wishlist methods (using session/cookies)
    public static function getGuestWishlist($wishlistItems)
    {
        if (empty($wishlistItems)) {
            return collect();
        }

        return Product::with('images')
            ->whereIn('id', $wishlistItems)
            ->get();
    }

    public static function addToGuestWishlist(&$wishlistItems, $productId)
    {
        if (!in_array($productId, $wishlistItems)) {
            $wishlistItems[] = $productId;
        }
        return $wishlistItems;
    }

    public static function removeFromGuestWishlist(&$wishlistItems, $productId)
    {
        $key = array_search($productId, $wishlistItems);
        if ($key !== false) {
            unset($wishlistItems[$key]);
            $wishlistItems = array_values($wishlistItems);
        }
        return $wishlistItems;
    }

    public static function syncGuestWishlist($userId, $guestWishlistItems)
    {
        if (empty($guestWishlistItems)) {
            return;
        }

        foreach ($guestWishlistItems as $productId) {
            if (!self::isInWishlist($userId, $productId)) {
                self::create([
                    'user_id' => $userId,
                    'product_id' => $productId
                ]);
            }
        }
    }
}