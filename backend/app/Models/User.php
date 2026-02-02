<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
// use Illuminate\Contracts\Auth\MustVerifyEmail;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'avatar',
        'email_verified_at',
        'phone_verified_at',
        'status',
        'role',
        'last_login_at',
        'last_login_ip',
        'email_notifications',
        'sms_notifications',
        'order_updates',
        'promotional_emails'
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'phone_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'email_notifications' => 'boolean',
        'sms_notifications' => 'boolean',
        'order_updates' => 'boolean',
        'promotional_emails' => 'boolean'
    ];

    // Relationships
    public function addresses()
    {
        return $this->hasMany(Address::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function cart()
    {
        return $this->hasOne(Cart::class);
    }

    public function wishlist()
    {
        return $this->hasMany(Wishlist::class);
    }

    public function reviews()
    {
        return $this->hasMany(ProductReview::class);
    }

    public function searchHistories()
    {
        return $this->hasMany(SearchHistory::class);
    }

    public function defaultAddress()
    {
        return $this->hasOne(Address::class)->where('is_default', true);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeCustomer($query)
    {
        return $query->where('role', 'customer');
    }

    public function scopeAdmin($query)
    {
        return $query->where('role', 'admin');
    }

    public function scopeWithRecentOrders($query, $days = 30)
    {
        return $query->whereHas('orders', function($q) use ($days) {
            $q->where('created_at', '>=', now()->subDays($days));
        });
    }

    // Methods
    public function getTotalSpentAttribute()
    {
        return $this->orders()->where('payment_status', 'paid')->sum('total');
    }

    public function getTotalOrdersAttribute()
    {
        return $this->orders()->count();
    }

    public function getAverageOrderValueAttribute()
    {
        $totalOrders = $this->total_orders;
        return $totalOrders > 0 ? $this->total_spent / $totalOrders : 0;
    }

    // public function hasRole($role)
    // {
    //     return $this->role === $role;
    // }

    // public function hasAnyRole(array $roles)
    // {
    //     return in_array($this->role, $roles);
    // }

    public function hasActiveOrder()
    {
        return $this->orders()->whereIn('status', ['pending', 'processing', 'shipped'])->exists();
    }

    public function markEmailAsVerified()
    {
        $this->forceFill([
            'email_verified_at' => $this->freshTimestamp(),
        ])->save();
    }

    public function markPhoneAsVerified()
    {
        $this->forceFill([
            'phone_verified_at' => $this->freshTimestamp(),
        ])->save();
    }
}