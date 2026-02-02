<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Stripe\Stripe;
use Stripe\PaymentIntent;

class PaymentController extends Controller
{
    public function createPaymentIntent(Request $request)
    {
        $request->validate([
            'order_id' => 'required|exists:orders,id',
            'amount' => 'required|numeric'
        ]);

        Stripe::setApiKey(config('services.stripe.secret'));

        try {
            $paymentIntent = PaymentIntent::create([
                'amount' => $request->amount * 100, // Convert to cents
                'currency' => 'usd',
                'metadata' => [
                    'order_id' => $request->order_id,
                    'user_id' => Auth::id()
                ]
            ]);

            // Update payment record
            $payment = Payment::where('order_id', $request->order_id)
                ->where('status', 'pending')
                ->first();

            if ($payment) {
                $payment->update([
                    'transaction_id' => $paymentIntent->id,
                    'payment_intent' => $paymentIntent->client_secret
                ]);
            }

            return response()->json([
                'clientSecret' => $paymentIntent->client_secret,
                'paymentIntentId' => $paymentIntent->id
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function confirmPayment(Request $request)
    {
        $request->validate([
            'payment_intent_id' => 'required',
            'order_id' => 'required|exists:orders,id'
        ]);

        Stripe::setApiKey(config('services.stripe.secret'));

        try {
            $paymentIntent = PaymentIntent::retrieve($request->payment_intent_id);

            if ($paymentIntent->status === 'succeeded') {
                $order = Order::find($request->order_id);
                $order->update([
                    'payment_status' => 'paid',
                    'status' => 'processing'
                ]);

                $payment = Payment::where('transaction_id', $request->payment_intent_id)
                    ->first();
                
                if ($payment) {
                    $payment->update([
                        'status' => 'completed',
                        'paid_at' => now()
                    ]);
                }

                // Send confirmation email
                // $this->sendOrderConfirmation($order);

                return response()->json([
                    'success' => true,
                    'message' => 'Payment confirmed successfully'
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Payment not completed'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function sslcommerzInit(Request $request)
    {
        $request->validate([
            'order_id' => 'required|exists:orders,id'
        ]);

        $order = Order::find($request->order_id);
        
        // SSLCommerz integration logic
        // This is a placeholder - implement SSLCommerz API integration
        
        return response()->json([
            'gateway_url' => 'https://sandbox.sslcommerz.com/gwprocess/v4/api.php',
            'params' => [
                'store_id' => config('services.sslcommerz.store_id'),
                'store_passwd' => config('services.sslcommerz.store_password'),
                'total_amount' => $order->total,
                'currency' => 'BDT',
                'tran_id' => $order->order_number,
                'success_url' => config('app.url') . '/api/payment/sslcommerz-success',
                'fail_url' => config('app.url') . '/api/payment/sslcommerz-fail',
                'cancel_url' => config('app.url') . '/api/payment/sslcommerz-cancel',
                'cus_name' => $order->user->name,
                'cus_email' => $order->user->email,
                'cus_add1' => $order->shippingAddress->address_line_1,
                'cus_city' => $order->shippingAddress->city,
                'cus_country' => 'Bangladesh',
                'cus_phone' => $order->user->phone
            ]
        ]);
    }

    public function sslcommerzSuccess(Request $request)
    {
        // Handle SSLCommerz success callback
        $val_id = $request->val_id;
        
        // Verify payment with SSLCommerz
        // Update order status
        
        return response()->json(['message' => 'Payment successful']);
    }

    public function webhookStripe(Request $request)
    {
        $payload = $request->getContent();
        $sig_header = $request->header('Stripe-Signature');
        $endpoint_secret = config('services.stripe.webhook_secret');

        try {
            $event = \Stripe\Webhook::constructEvent(
                $payload, $sig_header, $endpoint_secret
            );
        } catch(\UnexpectedValueException $e) {
            return response()->json(['error' => 'Invalid payload'], 400);
        } catch(\Stripe\Exception\SignatureVerificationException $e) {
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        switch ($event->type) {
            case 'payment_intent.succeeded':
                $paymentIntent = $event->data->object;
                $this->handlePaymentIntentSucceeded($paymentIntent);
                break;
            case 'payment_intent.payment_failed':
                $paymentIntent = $event->data->object;
                $this->handlePaymentIntentFailed($paymentIntent);
                break;
        }

        return response()->json(['success' => true]);
    }

    private function handlePaymentIntentSucceeded($paymentIntent)
    {
        $orderId = $paymentIntent->metadata->order_id;
        $order = Order::find($orderId);
        
        if ($order) {
            $order->update([
                'payment_status' => 'paid',
                'status' => 'processing'
            ]);

            $payment = Payment::where('transaction_id', $paymentIntent->id)->first();
            if ($payment) {
                $payment->update([
                    'status' => 'completed',
                    'paid_at' => now()
                ]);
            }
        }
    }

    private function handlePaymentIntentFailed($paymentIntent)
    {
        $orderId = $paymentIntent->metadata->order_id;
        $order = Order::find($orderId);
        
        if ($order) {
            $order->update([
                'payment_status' => 'failed'
            ]);

            $payment = Payment::where('transaction_id', $paymentIntent->id)->first();
            if ($payment) {
                $payment->update([
                    'status' => 'failed',
                    'failure_reason' => $paymentIntent->last_payment_error->message ?? 'Unknown error'
                ]);
            }
        }
    }
}