<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $orders = Order::with(['items.product.images', 'shippingAddress', 'billingAddress'])
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json($orders);
    }

    public function show($id)
    {
        $user = Auth::user();
        $order = Order::with([
            'items.product.images',
            'shippingAddress',
            'billingAddress',
            'payments',
            'shipments'
        ])->where('user_id', $user->id)->findOrFail($id);

        return response()->json($order);
    }

    public function trackOrder($orderNumber)
    {
        $order = Order::with(['shipments'])->where('order_number', $orderNumber)->firstOrFail();
        
        $trackingInfo = [
            'order_number' => $order->order_number,
            'status' => $order->status,
            'estimated_delivery' => $order->estimated_delivery,
            'shipments' => $order->shipments,
            'timeline' => $this->getOrderTimeline($order)
        ];

        return response()->json($trackingInfo);
    }

    private function getOrderTimeline($order)
    {
        $timeline = [
            [
                'status' => 'Order Placed',
                'date' => $order->created_at,
                'completed' => true
            ],
            [
                'status' => 'Processing',
                'date' => $order->processed_at,
                'completed' => !is_null($order->processed_at)
            ],
            [
                'status' => 'Shipped',
                'date' => $order->shipped_at,
                'completed' => !is_null($order->shipped_at)
            ],
            [
                'status' => 'Out for Delivery',
                'date' => $order->out_for_delivery_at,
                'completed' => !is_null($order->out_for_delivery_at)
            ],
            [
                'status' => 'Delivered',
                'date' => $order->delivered_at,
                'completed' => !is_null($order->delivered_at)
            ]
        ];

        return $timeline;
    }

    public function cancelOrder($id)
    {
        $user = Auth::user();
        $order = Order::where('user_id', $user->id)->findOrFail($id);

        if (!in_array($order->status, ['pending', 'processing'])) {
            return response()->json([
                'message' => 'Order cannot be cancelled at this stage'
            ], 400);
        }

        $order->update(['status' => 'cancelled']);

        // Restore product stock
        foreach ($order->items as $item) {
            $item->product->increment('stock', $item->quantity);
        }

        return response()->json([
            'message' => 'Order cancelled successfully'
        ]);
    }

    public function requestReturn(Request $request, $id)
    {
        $request->validate([
            'reason' => 'required|string',
            'description' => 'required|string'
        ]);

        $user = Auth::user();
        $order = Order::where('user_id', $user->id)->findOrFail($id);

        // Check if order is eligible for return
        if ($order->status !== 'delivered') {
            return response()->json([
                'message' => 'Only delivered orders can be returned'
            ], 400);
        }

        // Create return request
        $order->update([
            'return_requested' => true,
            'return_reason' => $request->reason,
            'return_description' => $request->description,
            'return_requested_at' => now()
        ]);

        return response()->json([
            'message' => 'Return request submitted successfully'
        ]);
    }
}