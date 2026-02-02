<?php

namespace App\Services\Courier;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PathaoService
{
    protected $clientId;
    protected $clientSecret;
    protected $baseUrl;
    protected $accessToken;

    public function __construct()
    {
        $this->clientId = config('services.courier.pathao.client_id');
        $this->clientSecret = config('services.courier.pathao.client_secret');
        $this->baseUrl = config('services.courier.pathao.base_url', 'https://api-hermes.pathao.com');
    }

    public function authenticate()
    {
        try {
            $response = Http::post($this->baseUrl . '/aladdin/api/v1/issue-token', [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'grant_type' => 'client_credentials'
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $this->accessToken = $data['access_token'];
                return [
                    'success' => true,
                    'access_token' => $this->accessToken,
                    'expires_in' => $data['expires_in']
                ];
            }

            return [
                'success' => false,
                'error' => 'Authentication failed'
            ];

        } catch (\Exception $e) {
            Log::error('Pathao authentication failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function createOrder($orderData)
    {
        if (!$this->accessToken) {
            $auth = $this->authenticate();
            if (!$auth['success']) {
                return $auth;
            }
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->accessToken,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ])->post($this->baseUrl . '/aladdin/api/v1/orders', $orderData);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'data' => $data,
                    'consignment_id' => $data['data']['consignment_id'] ?? null,
                    'tracking_code' => $data['data']['tracking_code'] ?? null
                ];
            }

            $error = $response->json();
            return [
                'success' => false,
                'error' => $error['message'] ?? 'Order creation failed',
                'details' => $error
            ];

        } catch (\Exception $e) {
            Log::error('Pathao order creation failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function getPrice($data)
    {
        if (!$this->accessToken) {
            $auth = $this->authenticate();
            if (!$auth['success']) {
                return $auth;
            }
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->accessToken,
                'Accept' => 'application/json'
            ])->post($this->baseUrl . '/aladdin/api/v1/merchant/price-plan', $data);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'data' => $data
                ];
            }

            return [
                'success' => false,
                'error' => 'Price calculation failed'
            ];

        } catch (\Exception $e) {
            Log::error('Pathao price calculation failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function trackOrder($consignmentId)
    {
        if (!$this->accessToken) {
            $auth = $this->authenticate();
            if (!$auth['success']) {
                return $auth;
            }
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->accessToken,
                'Accept' => 'application/json'
            ])->get($this->baseUrl . '/aladdin/api/v1/orders/' . $consignmentId . '/track');

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'data' => $data
                ];
            }

            return [
                'success' => false,
                'error' => 'Tracking failed'
            ];

        } catch (\Exception $e) {
            Log::error('Pathao tracking failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function getCities()
    {
        if (!$this->accessToken) {
            $auth = $this->authenticate();
            if (!$auth['success']) {
                return $auth;
            }
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->accessToken,
                'Accept' => 'application/json'
            ])->get($this->baseUrl . '/aladdin/api/v1/countries/1/city-list');

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'cities' => $data['data']['data'] ?? []
                ];
            }

            return [
                'success' => false,
                'error' => 'Failed to fetch cities'
            ];

        } catch (\Exception $e) {
            Log::error('Pathao cities fetch failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function getZones($cityId)
    {
        if (!$this->accessToken) {
            $auth = $this->authenticate();
            if (!$auth['success']) {
                return $auth;
            }
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->accessToken,
                'Accept' => 'application/json'
            ])->get($this->baseUrl . '/aladdin/api/v1/cities/' . $cityId . '/zone-list');

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'zones' => $data['data']['data'] ?? []
                ];
            }

            return [
                'success' => false,
                'error' => 'Failed to fetch zones'
            ];

        } catch (\Exception $e) {
            Log::error('Pathao zones fetch failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function getAreas($zoneId)
    {
        if (!$this->accessToken) {
            $auth = $this->authenticate();
            if (!$auth['success']) {
                return $auth;
            }
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->accessToken,
                'Accept' => 'application/json'
            ])->get($this->baseUrl . '/aladdin/api/v1/zones/' . $zoneId . '/area-list');

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'areas' => $data['data']['data'] ?? []
                ];
            }

            return [
                'success' => false,
                'error' => 'Failed to fetch areas'
            ];

        } catch (\Exception $e) {
            Log::error('Pathao areas fetch failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function getStores()
    {
        if (!$this->accessToken) {
            $auth = $this->authenticate();
            if (!$auth['success']) {
                return $auth;
            }
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->accessToken,
                'Accept' => 'application/json'
            ])->get($this->baseUrl . '/aladdin/api/v1/stores');

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'stores' => $data['data']['data'] ?? []
                ];
            }

            return [
                'success' => false,
                'error' => 'Failed to fetch stores'
            ];

        } catch (\Exception $e) {
            Log::error('Pathao stores fetch failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function cancelOrder($consignmentId, $reason = '')
    {
        if (!$this->accessToken) {
            $auth = $this->authenticate();
            if (!$auth['success']) {
                return $auth;
            }
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->accessToken,
                'Accept' => 'application/json'
            ])->post($this->baseUrl . '/aladdin/api/v1/orders/' . $consignmentId . '/cancel', [
                'cancellation_reason' => $reason
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'data' => $data
                ];
            }

            return [
                'success' => false,
                'error' => 'Order cancellation failed'
            ];

        } catch (\Exception $e) {
            Log::error('Pathao order cancellation failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function handleWebhook($payload)
    {
        // Handle Pathao webhook notifications
        $event = $payload['event'] ?? null;
        $consignmentId = $payload['consignment_id'] ?? null;
        $status = $payload['status'] ?? null;

        if (!$consignmentId || !$status) {
            return ['success' => false, 'error' => 'Invalid webhook data'];
        }

        // Update shipment status in database
        $shipment = \App\Models\Shipment::where('tracking_number', $consignmentId)->first();
        
        if ($shipment) {
            $shipment->update([
                'status' => $status,
                'status_details' => json_encode($payload)
            ]);

            // Update order status based on shipment status
            $this->updateOrderStatus($shipment->order, $status);

            return ['success' => true];
        }

        return ['success' => false, 'error' => 'Shipment not found'];
    }

    private function updateOrderStatus($order, $shipmentStatus)
    {
        $statusMap = [
            'Order Placed' => 'processing',
            'Order Picked' => 'shipped',
            'Order Dispatched' => 'shipped',
            'On The Way' => 'shipped',
            'Order Delivered' => 'delivered',
            'Order Cancelled' => 'cancelled',
            'Returned' => 'returned'
        ];

        if (isset($statusMap[$shipmentStatus])) {
            $order->update(['status' => $statusMap[$shipmentStatus]]);
            
            if ($shipmentStatus === 'Order Delivered') {
                $order->update(['delivered_at' => now()]);
            }
        }
    }

    public function prepareOrderData($order, $storeId = null)
    {
        $shippingAddress = $order->shippingAddress;
        
        // Default store ID if not provided
        if (!$storeId) {
            $storeId = config('services.courier.pathao.default_store_id', 1);
        }

        $orderData = [
            'store_id' => $storeId,
            'merchant_order_id' => $order->order_number,
            'recipient_name' => $shippingAddress->recipient_name,
            'recipient_phone' => $shippingAddress->phone,
            'recipient_address' => $shippingAddress->address_line_1,
            'recipient_city' => $this->getCityId($shippingAddress->city),
            'recipient_zone' => $this->getZoneId($shippingAddress->city, $shippingAddress->area),
            'recipient_area' => $this->getAreaId($shippingAddress->area),
            'delivery_type' => '48', // 48 hours delivery
            'item_type' => '2', // Parcel
            'special_instruction' => $order->notes ?? '',
            'item_quantity' => $order->items->sum('quantity'),
            'item_weight' => $this->calculateTotalWeight($order),
            'amount_to_collect' => $order->total,
            'item_description' => $this->generateItemDescription($order)
        ];

        return $orderData;
    }

    private function getCityId($cityName)
    {
        // Map city names to Pathao city IDs
        $cityMap = [
            'Dhaka' => 1,
            'Chittagong' => 2,
            'Sylhet' => 3,
            'Rajshahi' => 4,
            'Khulna' => 5,
            'Barisal' => 6,
            'Rangpur' => 7,
            'Mymensingh' => 8
        ];

        return $cityMap[$cityName] ?? 1; // Default to Dhaka
    }

    private function getZoneId($cityName, $area)
    {
        // This should be implemented based on your zone mapping
        return 1; // Default zone
    }

    private function getAreaId($area)
    {
        // This should be implemented based on your area mapping
        return 1; // Default area
    }

    private function calculateTotalWeight($order)
    {
        $totalWeight = 0;
        foreach ($order->items as $item) {
            $product = $item->product;
            $weight = $product->weight ?? 0.5; // Default 0.5kg per product
            $totalWeight += $weight * $item->quantity;
        }
        return max(0.5, $totalWeight); // Minimum 0.5kg
    }

    private function generateItemDescription($order)
    {
        $items = $order->items->take(3)->map(function($item) {
            return $item->product->name . ' (x' . $item->quantity . ')';
        })->toArray();

        $description = implode(', ', $items);
        
        if ($order->items->count() > 3) {
            $description .= ' and ' . ($order->items->count() - 3) . ' more items';
        }

        return $description;
    }
}