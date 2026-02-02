<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'user_id',
        'payment_method',
        'amount',
        'currency',
        'status',
        'transaction_id',
        'payment_intent',
        'gateway_response',
        'failure_reason',
        'refunded_amount',
        'paid_at',
        'refunded_at',
        'billing_details'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'refunded_amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'refunded_at' => 'datetime',
        'gateway_response' => 'array',
        'billing_details' => 'array'
    ];

    // Relationships
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function refunds()
    {
        return $this->hasMany(PaymentRefund::class);
    }

    // Scopes
    public function scopeSuccessful($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeRefunded($query)
    {
        return $query->where('status', 'refunded');
    }

    public function scopePartiallyRefunded($query)
    {
        return $query->where('status', 'partially_refunded');
    }

    public function scopeByMethod($query, $method)
    {
        return $query->where('payment_method', $method);
    }

    public function scopeRecent($query, $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    // Methods
    public function getFormattedAmountAttribute()
    {
        return '$' . number_format($this->amount, 2);
    }

    public function getFormattedRefundedAmountAttribute()
    {
        return $this->refunded_amount ? '$' . number_format($this->refunded_amount, 2) : null;
    }

    public function getStatusBadgeAttribute()
    {
        $badges = [
            'pending' => 'bg-yellow-100 text-yellow-800',
            'completed' => 'bg-green-100 text-green-800',
            'failed' => 'bg-red-100 text-red-800',
            'refunded' => 'bg-purple-100 text-purple-800',
            'partially_refunded' => 'bg-orange-100 text-orange-800'
        ];

        return $badges[$this->status] ?? 'bg-gray-100 text-gray-800';
    }

    public function markAsCompleted($transactionId = null, $gatewayResponse = null)
    {
        $this->update([
            'status' => 'completed',
            'transaction_id' => $transactionId ?? $this->transaction_id,
            'gateway_response' => $gatewayResponse,
            'paid_at' => now()
        ]);

        // Update order payment status
        $this->order->update(['payment_status' => 'paid']);
    }

    public function markAsFailed($failureReason = null)
    {
        $this->update([
            'status' => 'failed',
            'failure_reason' => $failureReason
        ]);

        // Update order payment status
        $this->order->update(['payment_status' => 'failed']);
    }

    public function processRefund($amount, $reason = 'Customer request')
    {
        if ($amount <= 0) {
            throw new \Exception('Refund amount must be greater than 0');
        }

        if ($amount > $this->amount - $this->refunded_amount) {
            throw new \Exception('Refund amount exceeds available balance');
        }

        DB::beginTransaction();

        try {
            // Create refund record
            $refund = PaymentRefund::create([
                'payment_id' => $this->id,
                'amount' => $amount,
                'reason' => $reason,
                'status' => 'pending',
                'processed_by' => auth()->id()
            ]);

            // Process refund with payment gateway
            $gatewayResponse = $this->processGatewayRefund($amount, $reason);

            if ($gatewayResponse['success']) {
                $refund->update([
                    'status' => 'completed',
                    'gateway_response' => $gatewayResponse,
                    'completed_at' => now()
                ]);

                // Update payment refunded amount
                $newRefundedAmount = $this->refunded_amount + $amount;
                $this->update([
                    'refunded_amount' => $newRefundedAmount,
                    'refunded_at' => now()
                ]);

                // Update payment status
                if ($newRefundedAmount >= $this->amount) {
                    $this->update(['status' => 'refunded']);
                    $this->order->update(['payment_status' => 'refunded']);
                } else {
                    $this->update(['status' => 'partially_refunded']);
                    $this->order->update(['payment_status' => 'partially_refunded']);
                }

                DB::commit();
                return $refund;
            } else {
                $refund->update([
                    'status' => 'failed',
                    'failure_reason' => $gatewayResponse['error']
                ]);
                
                DB::rollBack();
                throw new \Exception('Refund failed: ' . $gatewayResponse['error']);
            }

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    private function processGatewayRefund($amount, $reason)
    {
        // Implement gateway-specific refund logic
        switch ($this->payment_method) {
            case 'stripe':
                return $this->processStripeRefund($amount, $reason);
            case 'sslcommerz':
                return $this->processSSLCommerzRefund($amount, $reason);
            default:
                return ['success' => false, 'error' => 'Refund not supported for this payment method'];
        }
    }

    private function processStripeRefund($amount, $reason)
    {
        // Implement Stripe refund logic
        return ['success' => false, 'error' => 'Stripe refund not implemented'];
    }

    private function processSSLCommerzRefund($amount, $reason)
    {
        // Implement SSLCommerz refund logic
        return ['success' => false, 'error' => 'SSLCommerz refund not implemented'];
    }

    public function getRefundableAmount()
    {
        return $this->amount - $this->refunded_amount;
    }

    public function isFullyRefunded()
    {
        return $this->refunded_amount >= $this->amount;
    }

    public function isPartiallyRefunded()
    {
        return $this->refunded_amount > 0 && $this->refunded_amount < $this->amount;
    }

    public function canBeRefunded()
    {
        return $this->status === 'completed' && $this->getRefundableAmount() > 0;
    }

    public function getPaymentMethodName()
    {
        $methods = [
            'stripe' => 'Credit/Debit Card',
            'sslcommerz' => 'SSLCommerz',
            'cod' => 'Cash on Delivery',
            'bank' => 'Bank Transfer'
        ];

        return $methods[$this->payment_method] ?? ucfirst($this->payment_method);
    }
}