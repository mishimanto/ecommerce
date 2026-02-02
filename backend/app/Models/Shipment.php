<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Shipment extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'courier',
        'tracking_number',
        'status',
        'shipping_cost',
        'estimated_delivery',
        'actual_delivery',
        'notes',
        'label_url',
        'status_details'
    ];

    protected $casts = [
        'shipping_cost' => 'decimal:2',
        'estimated_delivery' => 'datetime',
        'actual_delivery' => 'datetime',
        'status_details' => 'array'
    ];

    // Relationships
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    // Scopes
    public function scopeByCourier($query, $courier)
    {
        return $query->where('courier', $courier);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeInTransit($query)
    {
        return $query->whereIn('status', ['picked', 'in_transit', 'out_for_delivery']);
    }

    public function scopeDelivered($query)
    {
        return $query->where('status', 'delivered');
    }

    public function scopeFailed($query)
    {
        return $query->whereIn('status', ['failed', 'returned', 'cancelled']);
    }

    // Methods
    public function getFormattedShippingCostAttribute()
    {
        return '$' . number_format($this->shipping_cost, 2);
    }

    public function getStatusBadgeAttribute()
    {
        $badges = [
            'pending' => 'bg-yellow-100 text-yellow-800',
            'created' => 'bg-blue-100 text-blue-800',
            'picked' => 'bg-indigo-100 text-indigo-800',
            'in_transit' => 'bg-purple-100 text-purple-800',
            'out_for_delivery' => 'bg-pink-100 text-pink-800',
            'delivered' => 'bg-green-100 text-green-800',
            'failed' => 'bg-red-100 text-red-800',
            'returned' => 'bg-gray-100 text-gray-800',
            'cancelled' => 'bg-gray-100 text-gray-800'
        ];

        return $badges[$this->status] ?? 'bg-gray-100 text-gray-800';
    }

    public function getStatusTextAttribute()
    {
        $statusText = [
            'pending' => 'Pending',
            'created' => 'Shipment Created',
            'picked' => 'Picked Up',
            'in_transit' => 'In Transit',
            'out_for_delivery' => 'Out for Delivery',
            'delivered' => 'Delivered',
            'failed' => 'Delivery Failed',
            'returned' => 'Returned',
            'cancelled' => 'Cancelled'
        ];

        return $statusText[$this->status] ?? $this->status;
    }

    public function getCourierNameAttribute()
    {
        $couriers = [
            'pathao' => 'Pathao',
            'steadfast' => 'Steadfast',
            'redx' => 'RedX',
            'manual' => 'Manual'
        ];

        return $couriers[$this->courier] ?? ucfirst($this->courier);
    }

    public function getTrackingUrlAttribute()
    {
        $urls = [
            'pathao' => 'https://pathao.com/track/' . $this->tracking_number,
            'steadfast' => 'https://steadfast.com.bd/track/' . $this->tracking_number,
            'redx' => 'https://redx.com.bd/track/' . $this->tracking_number
        ];

        return $urls[$this->courier] ?? null;
    }

    public function updateStatus($status, $details = null)
    {
        $oldStatus = $this->status;
        
        $this->update([
            'status' => $status,
            'status_details' => array_merge(
                $this->status_details ?? [],
                [
                    $status => [
                        'timestamp' => now(),
                        'details' => $details
                    ]
                ]
            )
        ]);

        // Update actual delivery time if delivered
        if ($status === 'delivered') {
            $this->update(['actual_delivery' => now()]);
            $this->order->update(['delivered_at' => now()]);
        }

        // Log status change
        $this->logStatusChange($oldStatus, $status, $details);
    }

    private function logStatusChange($oldStatus, $newStatus, $details = null)
    {
        DB::table('shipment_status_history')->insert([
            'shipment_id' => $this->id,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'details' => $details,
            'created_at' => now()
        ]);
    }

    public function getStatusHistory()
    {
        return DB::table('shipment_status_history')
            ->where('shipment_id', $this->id)
            ->orderBy('created_at', 'asc')
            ->get();
    }

    public function isDelivered()
    {
        return $this->status === 'delivered';
    }

    public function isInTransit()
    {
        return in_array($this->status, ['picked', 'in_transit', 'out_for_delivery']);
    }

    public function isFailed()
    {
        return in_array($this->status, ['failed', 'returned', 'cancelled']);
    }

    public function getEstimatedDeliveryDate()
    {
        return $this->estimated_delivery?->format('F j, Y');
    }

    public function getActualDeliveryDate()
    {
        return $this->actual_delivery?->format('F j, Y');
    }

    public function calculateDeliveryTime()
    {
        if ($this->actual_delivery && $this->created_at) {
            return $this->created_at->diffInDays($this->actual_delivery);
        }
        return null;
    }

    public function canBeCancelled()
    {
        return in_array($this->status, ['pending', 'created']);
    }

    public function cancel($reason = 'Customer request')
    {
        if (!$this->canBeCancelled()) {
            throw new \Exception('Shipment cannot be cancelled at this stage');
        }

        $this->updateStatus('cancelled', $reason);
        
        // Update order status
        $this->order->update(['status' => 'cancelled']);
    }
}