<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CheckoutRequest;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Cart;
use App\Models\Product;
use App\Models\Address;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CheckoutController extends Controller
{
    public function shippingOptions(Request $request)
    {
        $request->validate([
            'address_id' => 'required|exists:addresses,id'
        ]);

        // Calculate shipping options based on address
        // This is a placeholder - integrate with courier APIs
        
        $options = [
            [
                'id' => 'standard',
                'name' => 'Standard Delivery',
                'cost' => 100,
                'estimated_days' => '3-5 business days'
            ],
            [
                'id' => 'express',
                'name' => 'Express Delivery',
                'cost' => 200,
                'estimated_days' => '1-2 business days'
            ]
        ];

        return response()->json($options);
    }

    public function placeOrder(CheckoutRequest $request)
    {
        DB::beginTransaction();

        try {
            $user = Auth::user();
            $cart = Cart::with('items.product')->where('user_id', $user->id)->first();

            if (!$cart || $cart->items->isEmpty()) {
                return response()->json(['message' => 'Cart is empty'], 400);
            }

            // Calculate totals
            $subtotal = $cart->items->sum(function($item) {
                return $item->price * $item->quantity;
            });

            $shippingCost = $request->shipping_cost;
            $tax = $subtotal * 0.05; // 5% tax
            $total = $subtotal + $shippingCost + $tax;

            // Create order
            $order = Order::create([
                'user_id' => $user->id,
                'order_number' => 'ORD-' . time() . rand(1000, 9999),
                'subtotal' => $subtotal,
                'tax' => $tax,
                'shipping_cost' => $shippingCost,
                'total' => $total,
                'status' => 'pending',
                'payment_status' => 'pending',
                'shipping_address_id' => $request->shipping_address_id,
                'billing_address_id' => $request->billing_address_id,
                'shipping_method' => $request->shipping_method,
                'notes' => $request->notes
            ]);

            // Create order items
            foreach ($cart->items as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item->product_id,
                    'variant_id' => $item->variant_id,
                    'quantity' => $item->quantity,
                    'price' => $item->price,
                    'total' => $item->price * $item->quantity
                ]);

                // Update product stock
                $product = Product::find($item->product_id);
                if ($product) {
                    $product->decrement('stock', $item->quantity);
                }
            }

            // Clear cart
            $cart->items()->delete();

            // Create initial payment record
            $payment = Payment::create([
                'order_id' => $order->id,
                'amount' => $total,
                'payment_method' => $request->payment_method,
                'status' => 'pending',
                'transaction_id' => null
            ]);

            DB::commit();

            return response()->json([
                'order' => $order,
                'payment' => $payment,
                'message' => 'Order placed successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Order failed: ' . $e->getMessage()
            ], 500);
        }
    }

    public function calculateTotals(Request $request)
    {
        $user = Auth::user();
        $cart = Cart::with('items.product')->where('user_id', $user->id)->first();

        if (!$cart) {
            return response()->json(['totals' => []]);
        }

        $subtotal = $cart->items->sum(function($item) {
            return $item->price * $item->quantity;
        });

        $tax = $subtotal * 0.05;
        $shippingCost = $request->shipping_cost ?? 0;
        $total = $subtotal + $tax + $shippingCost;

        return response()->json([
            'subtotal' => $subtotal,
            'tax' => $tax,
            'shipping_cost' => $shippingCost,
            'total' => $total
        ]);
    }
}