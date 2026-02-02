<?php

namespace App\Services\Payment;

use Stripe\Stripe;
use Stripe\PaymentIntent;
use Stripe\Refund;
use Stripe\Charge;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\Log;

class StripeService
{
    protected $secretKey;

    public function __construct()
    {
        $this->secretKey = config('services.stripe.secret_key');
        Stripe::setApiKey($this->secretKey);
    }

    public function createPaymentIntent($amount, $currency = 'usd', $metadata = [])
    {
        try {
            $paymentIntent = PaymentIntent::create([
                'amount' => $this->convertToCents($amount),
                'currency' => $currency,
                'metadata' => $metadata,
                'automatic_payment_methods' => [
                    'enabled' => true,
                ],
            ]);

            return [
                'success' => true,
                'client_secret' => $paymentIntent->client_secret,
                'payment_intent_id' => $paymentIntent->id,
                'amount' => $amount
            ];
        } catch (\Exception $e) {
            Log::error('Stripe PaymentIntent creation failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function confirmPayment($paymentIntentId)
    {
        try {
            $paymentIntent = PaymentIntent::retrieve($paymentIntentId);
            
            if ($paymentIntent->status === 'succeeded') {
                return [
                    'success' => true,
                    'payment_intent' => $paymentIntent,
                    'message' => 'Payment confirmed successfully'
                ];
            }

            return [
                'success' => false,
                'status' => $paymentIntent->status,
                'message' => 'Payment not completed'
            ];
        } catch (\Exception $e) {
            Log::error('Stripe payment confirmation failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function capturePayment($paymentIntentId)
    {
        try {
            $paymentIntent = PaymentIntent::retrieve($paymentIntentId);
            $captured = $paymentIntent->capture();

            return [
                'success' => true,
                'payment_intent' => $captured
            ];
        } catch (\Exception $e) {
            Log::error('Stripe payment capture failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function refundPayment($chargeId, $amount = null, $reason = null)
    {
        try {
            $params = [
                'charge' => $chargeId,
                'reason' => $reason ?? 'requested_by_customer'
            ];

            if ($amount !== null) {
                $params['amount'] = $this->convertToCents($amount);
            }

            $refund = Refund::create($params);

            return [
                'success' => true,
                'refund' => $refund,
                'message' => 'Refund processed successfully'
            ];
        } catch (\Exception $e) {
            Log::error('Stripe refund failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function getPaymentIntent($paymentIntentId)
    {
        try {
            $paymentIntent = PaymentIntent::retrieve($paymentIntentId);
            return [
                'success' => true,
                'payment_intent' => $paymentIntent
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function getCharge($chargeId)
    {
        try {
            $charge = Charge::retrieve($chargeId);
            return [
                'success' => true,
                'charge' => $charge
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function createCustomer($email, $name = null, $metadata = [])
    {
        try {
            $customer = \Stripe\Customer::create([
                'email' => $email,
                'name' => $name,
                'metadata' => $metadata
            ]);

            return [
                'success' => true,
                'customer' => $customer
            ];
        } catch (\Exception $e) {
            Log::error('Stripe customer creation failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function savePaymentMethod($customerId, $paymentMethodId)
    {
        try {
            $paymentMethod = \Stripe\PaymentMethod::retrieve($paymentMethodId);
            $paymentMethod->attach(['customer' => $customerId]);

            return [
                'success' => true,
                'payment_method' => $paymentMethod
            ];
        } catch (\Exception $e) {
            Log::error('Stripe save payment method failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function getCustomerPaymentMethods($customerId)
    {
        try {
            $paymentMethods = \Stripe\PaymentMethod::all([
                'customer' => $customerId,
                'type' => 'card'
            ]);

            return [
                'success' => true,
                'payment_methods' => $paymentMethods->data
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function deletePaymentMethod($paymentMethodId)
    {
        try {
            $paymentMethod = \Stripe\PaymentMethod::retrieve($paymentMethodId);
            $detached = $paymentMethod->detach();

            return [
                'success' => true,
                'message' => 'Payment method deleted'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function handleWebhook($payload, $sigHeader)
    {
        $endpointSecret = config('services.stripe.webhook_secret');

        try {
            $event = \Stripe\Webhook::constructEvent(
                $payload, $sigHeader, $endpointSecret
            );
        } catch(\UnexpectedValueException $e) {
            Log::error('Invalid Stripe webhook payload');
            return ['success' => false, 'error' => 'Invalid payload'];
        } catch(\Stripe\Exception\SignatureVerificationException $e) {
            Log::error('Invalid Stripe webhook signature');
            return ['success' => false, 'error' => 'Invalid signature'];
        }

        switch ($event->type) {
            case 'payment_intent.succeeded':
                $this->handlePaymentIntentSucceeded($event->data->object);
                break;
            case 'payment_intent.payment_failed':
                $this->handlePaymentIntentFailed($event->data->object);
                break;
            case 'charge.refunded':
                $this->handleChargeRefunded($event->data->object);
                break;
            case 'charge.dispute.created':
                $this->handleDisputeCreated($event->data->object);
                break;
        }

        return ['success' => true];
    }

    private function handlePaymentIntentSucceeded($paymentIntent)
    {
        $orderId = $paymentIntent->metadata->order_id ?? null;
        
        if ($orderId) {
            $order = Order::find($orderId);
            if ($order) {
                $order->update([
                    'payment_status' => 'paid',
                    'status' => 'processing'
                ]);

                Payment::where('order_id', $orderId)
                    ->where('status', 'pending')
                    ->update([
                        'status' => 'completed',
                        'transaction_id' => $paymentIntent->id,
                        'paid_at' => now(),
                        'details' => json_encode($paymentIntent)
                    ]);
            }
        }
    }

    private function handlePaymentIntentFailed($paymentIntent)
    {
        $orderId = $paymentIntent->metadata->order_id ?? null;
        
        if ($orderId) {
            $order = Order::find($orderId);
            if ($order) {
                $order->update(['payment_status' => 'failed']);
            }

            Payment::where('order_id', $orderId)
                ->where('status', 'pending')
                ->update([
                    'status' => 'failed',
                    'failure_reason' => $paymentIntent->last_payment_error->message ?? 'Unknown error'
                ]);
        }
    }

    private function handleChargeRefunded($charge)
    {
        $paymentIntentId = $charge->payment_intent;
        $payment = Payment::where('transaction_id', $paymentIntentId)->first();
        
        if ($payment) {
            $order = $payment->order;
            if ($order) {
                $refundAmount = $charge->amount_refunded / 100;
                $order->update([
                    'payment_status' => $charge->amount_refunded === $charge->amount ? 'refunded' : 'partially_refunded',
                    'refund_amount' => $refundAmount,
                    'refunded_at' => now()
                ]);
            }
        }
    }

    private function handleDisputeCreated($dispute)
    {
        // Handle dispute creation
        Log::warning('Stripe dispute created: ' . $dispute->id);
    }

    private function convertToCents($amount)
    {
        return (int) ($amount * 100);
    }

    private function convertFromCents($cents)
    {
        return $cents / 100;
    }
}