<?php

namespace App\Services\Courier;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RedXService
{
    protected $apiKey;
    protected $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('services.courier.redx.api_key');
        $this->baseUrl = config('services.courier.redx.base_url');
    }

    public function createOrder($orderData)
    {
        try {
            $response = Http::withHeaders([
                'API-Key' => $this->apiKey,
                'Content-Type' => 'application/json'
            ])->post($this->baseUrl . '/parcel', $orderData);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'data' => $data,
                    'tracking_code' => $data['tracking_code'] ?? null,
                    'consignment_id' => $data['consignment_id'] ?? null
                ];
            }

            $error = $response->json();
            return [
                'success' => false,
                'error' => $error['message'] ?? 'Order creation failed',
                'details' => $error
            ];

        } catch (\Exception $e) {
            Log::error('RedX order creation failed: ' . $e->getMessage());
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
                'API-Key' => $this->apiKey,
                'Content-Type' => 'application/json'
            ])->post($this->baseUrl . '/price', $data);

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
            Log::error('RedX price calculation failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function trackOrder($trackingCode)
    {
        try {
            $response = Http::withHeaders([
                'API-Key' => $this->apiKey
            ])->get($this->baseUrl . '/track/' . $trackingCode);

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
            Log::error('RedX tracking failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function cancelOrder($trackingCode)
    {
        try {
            $response = Http::withHeaders([
                'API-Key' => $this->apiKey
            ])->post($this->baseUrl . '/cancel/' . $trackingCode);

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
            Log::error('RedX order cancellation failed: ' . $e->getMessage());
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
                'API-Key' => $this->apiKey
            ])->get($this->baseUrl . '/areas');

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
            Log::error('RedX areas fetch failed: ' . $e->getMessage());
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
            'customer_name' => $shippingAddress->recipient_name,
            'customer_phone' => $shippingAddress->phone,
            'delivery_address' => $shippingAddress->address_line_1,
            'district' => $shippingAddress->city,
            'thana' => $shippingAddress->area,
            'amount_to_collect' => $order->total,
            'merchant_invoice_id' => $order->order_number,
            'note' => $order->notes ?? '',
            'parcel_weight' => $this->calculateWeight($order),
            'product_details' => $this->getProductDescription($order)
        ];

        return $orderData;
    }

    private function calculateWeight($order)
    {
        $totalWeight = 0;
        foreach ($order->items as $item) {
            $product = $item->product;
            $weight = $product->weight ?? 0.5;
            $totalWeight += $weight * $item->quantity;
        }
        return max(0.5, $totalWeight);
    }

    private function getProductDescription($order)
    {
        $products = [];
        foreach ($order->items->take(3) as $item) {
            $products[] = $item->product->name . ' (x' . $item->quantity . ')';
        }
        
        $description = implode(', ', $products);
        if ($order->items->count() > 3) {
            $description .= ' and ' . ($order->items->count() - 3) . ' more items';
        }
        
        return $description;
    }

    public function handleWebhook($payload)
    {
        $trackingCode = $payload['tracking_code'] ?? null;
        $status = $payload['status'] ?? null;

        if (!$trackingCode || !$status) {
            return ['success' => false, 'error' => 'Invalid webhook data'];
        }

        $shipment = \App\Models\Shipment::where('tracking_number', $trackingCode)->first();
        
        if ($shipment) {
            $shipment->update([
                'status' => $status,
                'status_details' => json_encode($payload)
            ]);

            $this->updateOrderStatus($shipment->order, $status);

            return ['success' => true];
        }

        return ['success' => false, 'error' => 'Shipment not found'];
    }

    private function updateOrderStatus($order, $shipmentStatus)
    {
        $statusMap = [
            'pending' => 'processing',
            'pickup_scheduled' => 'processing',
            'picked' => 'shipped',
            'in_transit' => 'shipped',
            'out_for_delivery' => 'shipped',
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