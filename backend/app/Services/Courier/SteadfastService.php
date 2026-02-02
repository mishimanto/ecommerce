<?php

namespace App\Services\Courier;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SteadfastService
{
    protected $apiKey;
    protected $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('services.courier.steadfast.api_key');
        $this->baseUrl = config('services.courier.steadfast.base_url');
    }

    public function createOrder($orderData)
    {
        try {
            $response = Http::withHeaders([
                'Api-Key' => $this->apiKey,
                'Content-Type' => 'application/json'
            ])->post($this->baseUrl . '/create_order', $orderData);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'data' => $data,
                    'consignment_id' => $data['consignment_id'] ?? null,
                    'tracking_code' => $data['tracking_code'] ?? null
                ];
            }

            $error = $response->json();
            return [
                'success' => false,
                'error' => $error['message'] ?? 'Order creation failed',
                'details' => $error
            ];

        } catch (\Exception $e) {
            Log::error('Steadfast order creation failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function getPrice($data)
    {
        try {
            $response = Http::withHeaders([
                'Api-Key' => $this->apiKey,
                'Content-Type' => 'application/json'
            ])->post($this->baseUrl . '/calculate_price', $data);

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
            Log::error('Steadfast price calculation failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function trackOrder($consignmentId)
    {
        try {
            $response = Http::withHeaders([
                'Api-Key' => $this->apiKey
            ])->get($this->baseUrl . '/track_order/' . $consignmentId);

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
            Log::error('Steadfast tracking failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function cancelOrder($consignmentId)
    {
        try {
            $response = Http::withHeaders([
                'Api-Key' => $this->apiKey
            ])->post($this->baseUrl . '/cancel_order/' . $consignmentId);

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
            Log::error('Steadfast order cancellation failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function getAreas()
    {
        try {
            $response = Http::withHeaders([
                'Api-Key' => $this->apiKey
            ])->get($this->baseUrl . '/get_areas');

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'areas' => $data['areas'] ?? []
                ];
            }

            return [
                'success' => false,
                'error' => 'Failed to fetch areas'
            ];

        } catch (\Exception $e) {
            Log::error('Steadfast areas fetch failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function getCities()
    {
        try {
            $response = Http::withHeaders([
                'Api-Key' => $this->apiKey
            ])->get($this->baseUrl . '/get_cities');

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'cities' => $data['cities'] ?? []
                ];
            }

            return [
                'success' => false,
                'error' => 'Failed to fetch cities'
            ];

        } catch (\Exception $e) {
            Log::error('Steadfast cities fetch failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function prepareOrderData($order)
    {
        $shippingAddress = $order->shippingAddress;

        $orderData = [
            'invoice' => $order->order_number,
            'recipient_name' => $shippingAddress->recipient_name,
            'recipient_phone' => $shippingAddress->phone,
            'recipient_address' => $shippingAddress->address_line_1,
            'recipient_city' => $shippingAddress->city,
            'recipient_area' => $shippingAddress->area,
            'cod_amount' => $order->total,
            'note' => $order->notes ?? '',
            'product_details' => $this->getProductDetails($order)
        ];

        return $orderData;
    }

    private function getProductDetails($order)
    {
        $products = [];
        foreach ($order->items as $item) {
            $products[] = [
                'name' => $item->product->name,
                'quantity' => $item->quantity,
                'price' => $item->price
            ];
        }
        return $products;
    }

    public function handleWebhook($payload)
    {
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
            'pending' => 'processing',
            'accepted' => 'processing',
            'picked' => 'shipped',
            'on_the_way' => 'shipped',
            'delivered' => 'delivered',
            'cancelled' => 'cancelled',
            'returned' => 'returned'
        ];

        if (isset($statusMap[$shipmentStatus])) {
            $order->update(['status' => $statusMap[$shipmentStatus]]);
            
            if ($shipmentStatus === 'delivered') {
                $order->update(['delivered_at' => now()]);
            }
        }
    }
}