<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Shipment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderManagementController extends Controller
{
    public function index(Request $request)
    {
        $query = Order::with(['user', 'items.product', 'shippingAddress', 'billingAddress']);

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                  ->orWhereHas('user', function($q) use ($search) {
                      $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                  });
            });
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by payment status
        if ($request->has('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }

        // Date range filter
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('created_at', [$request->start_date, $request->end_date]);
        }

        // Sort
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $request->get('per_page', 20);
        $orders = $query->paginate($perPage);

        // Statistics
        $stats = [
            'total' => Order::count(),
            'pending' => Order::where('status', 'pending')->count(),
            'processing' => Order::where('status', 'processing')->count(),
            'shipped' => Order::where('status', 'shipped')->count(),
            'delivered' => Order::where('status', 'delivered')->count(),
            'cancelled' => Order::where('status', 'cancelled')->count(),
            'today' => Order::whereDate('created_at', today())->count(),
            'revenue_today' => Order::whereDate('created_at', today())
                ->where('payment_status', 'paid')
                ->sum('total')
        ];

        return response()->json([
            'orders' => $orders,
            'stats' => $stats,
            'status_options' => ['pending', 'processing', 'shipped', 'delivered', 'cancelled', 'returned'],
            'payment_status_options' => ['pending', 'paid', 'failed', 'refunded', 'partially_refunded']
        ]);
    }

    public function show($id)
    {
        $order = Order::with([
            'user',
            'items.product.images',
            'shippingAddress',
            'billingAddress',
            'payments',
            'shipments',
            'notes.user'
        ])->findOrFail($id);

        return response()->json($order);
    }

    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:pending,processing,shipped,delivered,cancelled,returned',
            'notes' => 'nullable|string'
        ]);

        $order = Order::findOrFail($id);
        $oldStatus = $order->status;
        
        $order->update([
            'status' => $request->status,
            'notes' => $request->notes
        ]);

        // Update timestamps based on status
        switch ($request->status) {
            case 'processing':
                $order->update(['processed_at' => now()]);
                break;
            case 'shipped':
                $order->update(['shipped_at' => now()]);
                break;
            case 'delivered':
                $order->update(['delivered_at' => now()]);
                break;
            case 'cancelled':
                $order->update(['cancelled_at' => now()]);
                // Restore stock
                foreach ($order->items as $item) {
                    $item->product->increment('stock', $item->quantity);
                }
                break;
        }

        // Add status history
        DB::table('order_status_history')->insert([
            'order_id' => $order->id,
            'old_status' => $oldStatus,
            'new_status' => $request->status,
            'notes' => $request->notes,
            'changed_by' => auth()->id(),
            'created_at' => now()
        ]);

        // Send notification to customer
        // $this->sendStatusUpdateNotification($order, $oldStatus, $request->status);

        return response()->json([
            'order' => $order,
            'message' => 'Order status updated successfully'
        ]);
    }

    public function updatePaymentStatus(Request $request, $id)
    {
        $request->validate([
            'payment_status' => 'required|in:pending,paid,failed,refunded,partially_refunded',
            'notes' => 'nullable|string'
        ]);

        $order = Order::findOrFail($id);
        $order->update([
            'payment_status' => $request->payment_status,
            'notes' => $request->notes
        ]);

        // If refunded, update refund amount
        if ($request->payment_status === 'refunded' || $request->payment_status === 'partially_refunded') {
            $refundAmount = $request->refund_amount ?? $order->total;
            $order->update(['refund_amount' => $refundAmount]);
        }

        return response()->json([
            'order' => $order,
            'message' => 'Payment status updated successfully'
        ]);
    }

    public function addNote(Request $request, $id)
    {
        $request->validate([
            'note' => 'required|string',
            'type' => 'nullable|in:internal,customer',
            'notify_customer' => 'boolean'
        ]);

        $order = Order::findOrFail($id);

        DB::table('order_notes')->insert([
            'order_id' => $order->id,
            'user_id' => auth()->id(),
            'note' => $request->note,
            'type' => $request->type ?? 'internal',
            'notify_customer' => $request->notify_customer ?? false,
            'created_at' => now()
        ]);

        // Send notification if requested
        if ($request->notify_customer) {
            // $this->sendOrderNoteNotification($order, $request->note);
        }

        return response()->json([
            'message' => 'Note added successfully'
        ]);
    }

    public function createShipment(Request $request, $id)
    {
        $request->validate([
            'courier' => 'required|in:pathao,steadfast,redx,manual',
            'tracking_number' => 'required_if:courier,manual|string',
            'shipping_cost' => 'nullable|numeric|min:0',
            'estimated_delivery' => 'nullable|date',
            'notes' => 'nullable|string'
        ]);

        $order = Order::findOrFail($id);

        if ($order->status !== 'processing') {
            return response()->json([
                'message' => 'Order must be in processing status to create shipment'
            ], 400);
        }

        if ($request->courier === 'manual') {
            $shipment = Shipment::create([
                'order_id' => $order->id,
                'courier' => 'manual',
                'tracking_number' => $request->tracking_number,
                'shipping_cost' => $request->shipping_cost,
                'estimated_delivery' => $request->estimated_delivery,
                'status' => 'created',
                'notes' => $request->notes
            ]);

            $order->update([
                'status' => 'shipped',
                'shipped_at' => now()
            ]);

            return response()->json([
                'shipment' => $shipment,
                'message' => 'Manual shipment created successfully'
            ]);
        }

        // Integrate with courier API (simplified)
        $courierController = new \App\Http\Controllers\Api\CourierController();
        $result = $courierController->createShipment(new Request([
            'order_id' => $order->id,
            'courier' => $request->courier
        ]));

        if ($result->getStatusCode() === 200) {
            return response()->json([
                'shipment' => $result->getData()->shipment,
                'message' => 'Shipment created via ' . ucfirst($request->courier)
            ]);
        }

        return response()->json([
            'message' => 'Failed to create shipment'
        ], 500);
    }

    public function updateShipment(Request $request, $orderId, $shipmentId)
    {
        $request->validate([
            'tracking_number' => 'sometimes|string',
            'shipping_cost' => 'sometimes|numeric|min:0',
            'estimated_delivery' => 'sometimes|date',
            'status' => 'sometimes|string',
            'notes' => 'nullable|string'
        ]);

        $shipment = Shipment::where('order_id', $orderId)->findOrFail($shipmentId);
        $shipment->update($request->all());

        return response()->json([
            'shipment' => $shipment,
            'message' => 'Shipment updated successfully'
        ]);
    }

    public function deleteShipment($orderId, $shipmentId)
    {
        $shipment = Shipment::where('order_id', $orderId)->findOrFail($shipmentId);
        $shipment->delete();

        $order = Order::find($orderId);
        $order->update(['status' => 'processing']);

        return response()->json([
            'message' => 'Shipment deleted successfully'
        ]);
    }

    public function refundOrder(Request $request, $id)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0',
            'reason' => 'required|string',
            'refund_method' => 'required|in:original,manual',
            'notes' => 'nullable|string'
        ]);

        $order = Order::findOrFail($id);

        if ($request->amount > $order->total) {
            return response()->json([
                'message' => 'Refund amount cannot exceed order total'
            ], 400);
        }

        DB::beginTransaction();

        try {
            // Update order payment status
            $refundStatus = $request->amount === $order->total ? 'refunded' : 'partially_refunded';
            $order->update([
                'payment_status' => $refundStatus,
                'refund_amount' => $request->amount,
                'refunded_at' => now()
            ]);

            // Create refund record
            DB::table('refunds')->insert([
                'order_id' => $order->id,
                'amount' => $request->amount,
                'reason' => $request->reason,
                'refund_method' => $request->refund_method,
                'status' => 'completed',
                'processed_by' => auth()->id(),
                'notes' => $request->notes,
                'created_at' => now()
            ]);

            // Process refund via payment gateway if original method
            if ($request->refund_method === 'original') {
                $this->processGatewayRefund($order, $request->amount);
            }

            DB::commit();

            // Send refund notification
            // $this->sendRefundNotification($order, $request->amount, $request->reason);

            return response()->json([
                'message' => 'Refund processed successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Refund failed: ' . $e->getMessage()
            ], 500);
        }
    }

    private function processGatewayRefund($order, $amount)
    {
        // Implement payment gateway refund logic
        // This is a placeholder
    }

    public function printInvoice($id)
    {
        $order = Order::with([
            'user',
            'items.product',
            'shippingAddress',
            'billingAddress'
        ])->findOrFail($id);

        // Generate invoice data
        $invoice = [
            'invoice_number' => 'INV-' . $order->order_number,
            'order' => $order,
            'company' => [
                'name' => config('app.name'),
                'address' => '123 Business Street, City, Country',
                'phone' => '+1234567890',
                'email' => 'billing@' . config('app.domain'),
                'tax_id' => 'TAX-123456'
            ],
            'date' => now()->format('F d, Y'),
            'due_date' => now()->addDays(30)->format('F d, Y')
        ];

        return response()->json($invoice);
    }

    public function bulkUpdateOrders(Request $request)
    {
        $request->validate([
            'order_ids' => 'required|array',
            'order_ids.*' => 'exists:orders,id',
            'action' => 'required|in:update_status,update_payment_status,print_invoices,export',
            'data' => 'nullable|array'
        ]);

        $orders = Order::whereIn('id', $request->order_ids)->get();

        switch ($request->action) {
            case 'update_status':
                if (!isset($request->data['status'])) {
                    return response()->json(['message' => 'Status is required'], 400);
                }
                
                foreach ($orders as $order) {
                    $order->update(['status' => $request->data['status']]);
                }
                break;

            case 'update_payment_status':
                if (!isset($request->data['payment_status'])) {
                    return response()->json(['message' => 'Payment status is required'], 400);
                }
                
                foreach ($orders as $order) {
                    $order->update(['payment_status' => $request->data['payment_status']]);
                }
                break;
        }

        return response()->json([
            'message' => count($orders) . ' orders updated successfully'
        ]);
    }

    public function exportOrders(Request $request)
    {
        $request->validate([
            'format' => 'required|in:csv,excel,pdf',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'status' => 'nullable|string',
            'payment_status' => 'nullable|string'
        ]);

        $query = Order::with(['user', 'items']);

        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('created_at', [$request->start_date, $request->end_date]);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }

        $orders = $query->get();

        // Generate export file
        // This would create and return a file download
        
        return response()->json([
            'message' => 'Export file generated',
            'download_url' => '/api/admin/orders/export/download/orders.' . $request->format,
            'total_orders' => $orders->count()
        ]);
    }

    public function getStatusHistory($id)
    {
        $history = DB::table('order_status_history')
            ->leftJoin('users', 'order_status_history.changed_by', '=', 'users.id')
            ->where('order_id', $id)
            ->select('order_status_history.*', 'users.name as changed_by_name')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($history);
    }

    public function getNotes($id)
    {
        $notes = DB::table('order_notes')
            ->leftJoin('users', 'order_notes.user_id', '=', 'users.id')
            ->where('order_id', $id)
            ->select('order_notes.*', 'users.name as user_name', 'users.avatar')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($notes);
    }
}