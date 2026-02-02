<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Address extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'recipient_name',
        'phone',
        'address_line_1',
        'address_line_2',
        'city',
        'state',
        'country',
        'postal_code',
        'is_default',
        'type',
        'landmark',
        'latitude',
        'longitude'
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'latitude' => 'float',
        'longitude' => 'float'
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function shippingOrders()
    {
        return $this->hasMany(Order::class, 'shipping_address_id');
    }

    public function billingOrders()
    {
        return $this->hasMany(Order::class, 'billing_address_id');
    }

    // Scopes
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    public function scopeShipping($query)
    {
        return $query->where('type', 'shipping')->orWhereNull('type');
    }

    public function scopeBilling($query)
    {
        return $query->where('type', 'billing')->orWhereNull('type');
    }

    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    // Methods
    public function getFullAddressAttribute()
    {
        $address = $this->address_line_1;
        
        if ($this->address_line_2) {
            $address .= ', ' . $this->address_line_2;
        }
        
        $address .= ', ' . $this->city;
        
        if ($this->state) {
            $address .= ', ' . $this->state;
        }
        
        if ($this->postal_code) {
            $address .= ' - ' . $this->postal_code;
        }
        
        $address .= ', ' . $this->country;

        return $address;
    }

    public function getFormattedAddressAttribute()
    {
        $lines = [];
        $lines[] = $this->recipient_name;
        
        if ($this->phone) {
            $lines[] = 'Phone: ' . $this->phone;
        }
        
        $lines[] = $this->address_line_1;
        
        if ($this->address_line_2) {
            $lines[] = $this->address_line_2;
        }
        
        $lines[] = $this->city . ', ' . $this->state . ' ' . $this->postal_code;
        $lines[] = $this->country;

        return implode("\n", $lines);
    }

    public function markAsDefault()
    {
        // Remove default from other addresses
        $this->user->addresses()->update(['is_default' => false]);
        
        // Set this as default
        $this->update(['is_default' => true]);
    }

    public function isWithinServiceArea()
    {
        // Check if address is within service area
        // This could integrate with a service area API
        return true; // Default to true for now
    }

    public function getCoordinates()
    {
        if ($this->latitude && $this->longitude) {
            return [
                'lat' => $this->latitude,
                'lng' => $this->longitude
            ];
        }

        // Try to geocode the address
        return $this->geocodeAddress();
    }

    private function geocodeAddress()
    {
        // Implement geocoding logic
        // This would typically use a geocoding service like Google Maps
        return null;
    }

    // Validation rules for creating/updating addresses
    public static function validationRules($type = 'shipping')
    {
        $rules = [
            'recipient_name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'address_line_1' => 'required|string|max:500',
            'address_line_2' => 'nullable|string|max:500',
            'city' => 'required|string|max:100',
            'state' => 'required|string|max:100',
            'country' => 'required|string|max:100',
            'postal_code' => 'required|string|max:20',
            'is_default' => 'boolean',
            'type' => 'in:shipping,billing',
            'landmark' => 'nullable|string|max:255'
        ];

        return $rules;
    }
}