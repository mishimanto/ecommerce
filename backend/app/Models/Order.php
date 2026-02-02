<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'order_number',
        'subtotal',
        'tax',
        'shipping_cost',
        'total',
        'status',
        'payment_status',
        'payment_method',
        'shipping_address_id',
        'billing_address_id',
        'shipping_method',
        'notes',
        'refund_amount',
        'refunded_at',
        'cancelled_at',
        'processed_at',
        'shipped_at',
        'delivered_at',
        'estimated_delivery',
        'courier_tracking_id',
        'return_requested',
        'return_reason',
        'return_description',
        'return_requested_at',
        'return_approved_at',
        'return_completed_at'
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'total' => 'decimal:2',
        'refund_amount' => 'decimal:2',
        'return_requested' => 'boolean',
        'cancelled_at' => 'datetime',
        'processed_at' => 'datetime',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
        'refunded_at' => 'datetime',
        'estimated_delivery' => 'datetime',
        'return_requested_at' => 'datetime',
        'return_approved_at' => 'datetime',
        'return_completed_at' => 'datetime'
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function shippingAddress()
    {
        return $this->belongsTo(Address::class, 'shipping_address_id');
    }

    public function billingAddress()
    {
        return $this->belongsTo(Address::class, 'billing_address_id');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function shipments()
    {
        return $this->hasMany(Shipment::class);
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeProcessing($query)
    {
        return $query->where('status', 'processing');
    }

    public function scopeShipped($query)
    {
        return $query->where('status', 'shipped');
    }

    public function scopeDelivered($query)
    {
        return $query->where('status', 'delivered');
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    public function scopePaid($query)
    {
        return $query->where('payment_status', 'paid');
    }

    public function scopeRefunded($query)
    {
        return $query->where('payment_status', 'refunded');
    }

    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeRecent($query, $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    public function scopeThisWeek($query)
    {
        return $query->whereBetween('created_at', [
            now()->startOfWeek(),
            now()->endOfWeek()
        ]);
    }

    public function scopeThisMonth($query)
    {
        return $query->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year);
    }

    // Methods
    public function getFormattedSubtotalAttribute()
    {
        return '$' . number_format($this->subtotal, 2);
    }

    public function getFormattedTaxAttribute()
    {
        return '$' . number_format($this->tax, 2);
    }

    public function getFormattedShippingCostAttribute()
    {
        return '$' . number_format($this->shipping_cost, 2);
    }

    public function getFormattedTotalAttribute()
    {
        return '$' . number_format($this->total, 2);
    }

    public function getFormattedRefundAmountAttribute()
    {
        return $this->refund_amount ? '$' . number_format($this->refund_amount, 2) : null;
    }

    public function getStatusBadgeAttribute()
    {
        $badges = [
            'pending' => 'bg-yellow-100 text-yellow-800',
            'processing' => 'bg-blue-100 text-blue-800',
            'shipped' => 'bg-indigo-100 text-indigo-800',
            'delivered' => 'bg-green-100 text-green-800',
            'cancelled' => 'bg-red-100 text-red-800',
            'returned' => 'bg-purple-100 text-purple-800'
        ];

        return $badges[$this->status] ?? 'bg-gray-100 text-gray-800';
    }

    public function getPaymentStatusBadgeAttribute()
    {
        $badges = [
            'pending' => 'bg-yellow-100 text-yellow-800',
            'paid' => 'bg-green-100 text-green-800',
            'failed' => 'bg-red-100 text-red-800',
            'refunded' => 'bg-purple-100 text-purple-800',
            'partially_refunded' => 'bg-orange-100 text-orange-800'
        ];

        return $badges[$this->payment_status] ?? 'bg-gray-100 text-gray-800';
    }

    public function getItemCountAttribute()
    {
        return $this->items->sum('quantity');
    }

    public function canBeCancelled()
    {
        return in_array($this->status, ['pending', 'processing']);
    }

    public function canBeReturned()
    {
        return $this->status === 'delivered' && 
               $this->delivered_at &&
               $this->delivered_at->diffInDays(now()) <= 14; // 14-day return policy
    }

    public function markAsPaid()
    {
        $this->update([
            'payment_status' => 'paid',
            'status' => 'processing'
        ]);
    }

    public function markAsShipped($trackingNumber = null)
    {
        $this->update([
            'status' => 'shipped',
            'shipped_at' => now(),
            'courier_tracking_id' => $trackingNumber
        ]);
    }

    public function markAsDelivered()
    {
        $this->update([
            'status' => 'delivered',
            'delivered_at' => now()
        ]);
    }

    public function markAsCancelled()
    {
        $this->update([
            'status' => 'cancelled',
            'cancelled_at' => now()
        ]);

        // Restore product stock
        foreach ($this->items as $item) {
            $item->product->increment('stock', $item->quantity);
        }
    }

    public function requestReturn($reason, $description)
    {
        $this->update([
            'return_requested' => true,
            'return_reason' => $reason,
            'return_description' => $description,
            'return_requested_at' => now()
        ]);
    }

    public function approveReturn()
    {
        $this->update([
            'return_approved_at' => now(),
            'status' => 'returned'
        ]);
    }

    public function completeReturn()
    {
        $this->update([
            'return_completed_at' => now(),
            'payment_status' => 'refunded'
        ]);
    }

    public function getTimelineAttribute()
    {
        $timeline = [];

        if ($this->created_at) {
            $timeline[] = [
                'status' => 'Order Placed',
                'date' => $this->created_at,
                'completed' => true
            ];
        }

        if ($this->processed_at) {
            $timeline[] = [
                'status' => 'Processing',
                'date' => $this->processed_at,
                'completed' => true
            ];
        }

        if ($this->shipped_at) {
            $timeline[] = [
                'status' => 'Shipped',
                'date' => $this->shipped_at,
                'completed' => true
            ];
        }

        if ($this->delivered_at) {
            $timeline[] = [
                'status' => 'Delivered',
                'date' => $this->delivered_at,
                'completed' => true
            ];
        }

        return $timeline;
    }

    public function getNextStatus()
    {
        $statusFlow = [
            'pending' => 'processing',
            'processing' => 'shipped',
            'shipped' => 'delivered',
            'delivered' => null,
            'cancelled' => null,
            'returned' => null
        ];

        return $statusFlow[$this->status] ?? null;
    }

    // Event handlers
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($order) {
            if (empty($order->order_number)) {
                $order->order_number = 'ORD-' . time() . strtoupper(substr(md5(uniqid()), 0, 6));
            }
        });
    }
}