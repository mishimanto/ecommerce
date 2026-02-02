<?php

namespace App\Services\Payment;

use Illuminate\Support\Facades\Http;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\Log;

class SSLCommerzService
{
    protected $storeId;
    protected $storePassword;
    protected $sandbox;
    protected $baseUrl;

    public function __construct()
    {
        $this->storeId = config('services.sslcommerz.store_id');
        $this->storePassword = config('services.sslcommerz.store_password');
        $this->sandbox = config('services.sslcommerz.sandbox', true);
        $this->baseUrl = $this->sandbox 
            ? 'https://sandbox.sslcommerz.com'
            : 'https://securepay.sslcommerz.com';
    }

    public function initiatePayment($order, $successUrl, $failUrl, $cancelUrl, $ipnUrl = null)
    {
        $user = $order->user;
        $shippingAddress = $order->shippingAddress;

        $postData = [
            'store_id' => $this->storeId,
            'store_passwd' => $this->storePassword,
            'total_amount' => $order->total,
            'currency' => 'BDT',
            'tran_id' => $order->order_number,
            'success_url' => $successUrl,
            'fail_url' => $failUrl,
            'cancel_url' => $cancelUrl,
            'ipn_url' => $ipnUrl ?? $successUrl,
            
            // Customer Information
            'cus_name' => $user->name,
            'cus_email' => $user->email,
            'cus_add1' => $shippingAddress->address_line_1,
            'cus_add2' => $shippingAddress->address_line_2,
            'cus_city' => $shippingAddress->city,
            'cus_state' => $shippingAddress->state,
            'cus_postcode' => $shippingAddress->postal_code,
            'cus_country' => $shippingAddress->country ?? 'Bangladesh',
            'cus_phone' => $user->phone,
            
            // Shipping Information
            'shipping_method' => 'YES',
            'ship_name' => $shippingAddress->recipient_name,
            'ship_add1' => $shippingAddress->address_line_1,
            'ship_add2' => $shippingAddress->address_line_2,
            'ship_city' => $shippingAddress->city,
            'ship_state' => $shippingAddress->state,
            'ship_postcode' => $shippingAddress->postal_code,
            'ship_country' => $shippingAddress->country ?? 'Bangladesh',
            
            // Product Information
            'product_name' => 'Order #' . $order->order_number,
            'product_category' => 'E-commerce',
            'product_profile' => 'general',
            
            // Additional Parameters
            'value_a' => $order->id,
            'value_b' => $user->id,
            'value_c' => 'order_payment',
            'value_d' => 'ecommerce'
        ];

        try {
            $response = Http::asForm()
                ->post($this->baseUrl . '/gwprocess/v4/api.php', $postData);

            if ($response->successful()) {
                $result = $response->json();
                
                if ($result['status'] === 'SUCCESS') {
                    return [
                        'success' => true,
                        'gateway_url' => $result['GatewayPageURL'],
                        'session_key' => $result['sessionkey'],
                        'redirect' => true
                    ];
                } else {
                    return [
                        'success' => false,
                        'error' => $result['failedreason'] ?? 'Payment initiation failed'
                    ];
                }
            }

            return [
                'success' => false,
                'error' => 'SSLCommerz API request failed'
            ];

        } catch (\Exception $e) {
            Log::error('SSLCommerz payment initiation failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function validatePayment($tranId, $amount, $currency)
    {
        $postData = [
            'store_id' => $this->storeId,
            'store_passwd' => $this->storePassword,
            'tran_id' => $tranId,
            'format' => 'json'
        ];

        try {
            $response = Http::asForm()
                ->post($this->baseUrl . '/validator/api/validationserverAPI.php', $postData);

            if ($response->successful()) {
                $result = $response->json();
                
                // Check if payment is valid
                if ($result['status'] === 'VALID' || $result['status'] === 'VALIDATED') {
                    // Verify amount and currency
                    if ($result['currency'] === $currency && 
                        (float)$result['amount'] === (float)$amount) {
                        return [
                            'success' => true,
                            'data' => $result,
                            'message' => 'Payment validated successfully'
                        ];
                    } else {
                        return [
                            'success' => false,
                            'error' => 'Amount or currency mismatch'
                        ];
                    }
                } else {
                    return [
                        'success' => false,
                        'error' => 'Payment validation failed: ' . ($result['error'] ?? 'Unknown error')
                    ];
                }
            }

            return [
                'success' => false,
                'error' => 'Validation request failed'
            ];

        } catch (\Exception $e) {
            Log::error('SSLCommerz payment validation failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function initiateRefund($tranId, $refundAmount, $refundRemarks)
    {
        $postData = [
            'store_id' => $this->storeId,
            'store_passwd' => $this->storePassword,
            'tran_id' => $tranId,
            'refund_amount' => $refundAmount,
            'refund_remarks' => $refundRemarks,
            'format' => 'json'
        ];

        try {
            $response = Http::asForm()
                ->post($this->baseUrl . '/validator/api/merchantTransIDvalidationAPI.php', $postData);

            if ($response->successful()) {
                $result = $response->json();
                
                if ($result['status'] === 'success') {
                    return [
                        'success' => true,
                        'data' => $result,
                        'message' => 'Refund initiated successfully'
                    ];
                } else {
                    return [
                        'success' => false,
                        'error' => $result['error'] ?? 'Refund initiation failed'
                    ];
                }
            }

            return [
                'success' => false,
                'error' => 'Refund request failed'
            ];

        } catch (\Exception $e) {
            Log::error('SSLCommerz refund initiation failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function queryTransaction($tranId)
    {
        $postData = [
            'store_id' => $this->storeId,
            'store_passwd' => $this->storePassword,
            'tran_id' => $tranId,
            'format' => 'json'
        ];

        try {
            $response = Http::asForm()
                ->post($this->baseUrl . '/validator/api/merchantTransIDvalidationAPI.php', $postData);

            if ($response->successful()) {
                $result = $response->json();
                return [
                    'success' => true,
                    'data' => $result
                ];
            }

            return [
                'success' => false,
                'error' => 'Transaction query failed'
            ];

        } catch (\Exception $e) {
            Log::error('SSLCommerz transaction query failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function checkOrderValidation($valId)
    {
        $postData = [
            'store_id' => $this->storeId,
            'store_passwd' => $this->storePassword,
            'val_id' => $valId,
            'format' => 'json'
        ];

        try {
            $response = Http::asForm()
                ->post($this->baseUrl . '/validator/api/validationserverAPI.php', $postData);

            if ($response->successful()) {
                $result = $response->json();
                return [
                    'success' => true,
                    'data' => $result
                ];
            }

            return [
                'success' => false,
                'error' => 'Order validation check failed'
            ];

        } catch (\Exception $e) {
            Log::error('SSLCommerz order validation check failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function handleIPN($postData)
    {
        // Verify the IPN request
        if (!isset($postData['verify_key']) || !isset($postData['verify_sign'])) {
            return ['success' => false, 'error' => 'Invalid IPN data'];
        }

        // Sort the POST data
        $verifyKey = explode(',', $postData['verify_key']);
        $verifyData = [];
        
        foreach ($verifyKey as $key) {
            if (isset($postData[$key])) {
                $verifyData[$key] = $postData[$key];
            }
        }

        // Generate verification signature
        $verifyString = implode('', array_values($verifyData)) . $this->storePassword;
        $generatedSign = md5($verifyString);

        // Verify signature
        if ($generatedSign !== $postData['verify_sign']) {
            Log::error('SSLCommerz IPN signature mismatch');
            return ['success' => false, 'error' => 'Signature verification failed'];
        }

        // Process IPN based on status
        $tranId = $postData['tran_id'];
        $status = $postData['status'];
        $valId = $postData['val_id'];

        $order = Order::where('order_number', $tranId)->first();
        if (!$order) {
            return ['success' => false, 'error' => 'Order not found'];
        }

        switch ($status) {
            case 'VALID':
            case 'VALIDATED':
                $order->update([
                    'payment_status' => 'paid',
                    'status' => 'processing'
                ]);

                Payment::where('order_id', $order->id)
                    ->where('status', 'pending')
                    ->update([
                        'status' => 'completed',
                        'transaction_id' => $valId,
                        'paid_at' => now(),
                        'details' => json_encode($postData)
                    ]);
                break;

            case 'FAILED':
            case 'CANCELLED':
                $order->update(['payment_status' => 'failed']);
                
                Payment::where('order_id', $order->id)
                    ->where('status', 'pending')
                    ->update([
                        'status' => 'failed',
                        'failure_reason' => $postData['error'] ?? 'Payment failed'
                    ]);
                break;

            case 'UNATTEMPTED':
                // Payment not attempted yet
                break;

            case 'EXPIRED':
                $order->update(['payment_status' => 'expired']);
                break;
        }

        return ['success' => true];
    }

    public function getTransactionStatus($tranId)
    {
        $result = $this->queryTransaction($tranId);
        
        if ($result['success']) {
            return [
                'success' => true,
                'status' => $result['data']['status'] ?? 'UNKNOWN',
                'data' => $result['data']
            ];
        }

        return $result;
    }
}