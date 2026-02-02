<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $query = User::withCount(['orders' => function($q) {
            $q->where('payment_status', 'paid');
        }])
        ->withSum(['orders' => function($q) {
            $q->where('payment_status', 'paid');
        }], 'total')
        ->where('role', 'customer');

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by registration date
        if ($request->has('start_date')) {
            $query->where('created_at', '>=', $request->start_date);
        }
        if ($request->has('end_date')) {
            $query->where('created_at', '<=', $request->end_date);
        }

        // Sort
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $customers = $query->paginate(20);

        return response()->json($customers);
    }

    public function show($id)
    {
        $customer = User::with(['addresses', 'orders' => function($query) {
            $query->orderBy('created_at', 'desc')->limit(10);
        }])->findOrFail($id);

        // Get customer statistics
        $stats = [
            'total_orders' => $customer->orders()->count(),
            'total_spent' => $customer->orders()->where('payment_status', 'paid')->sum('total'),
            'average_order_value' => $customer->orders()->where('payment_status', 'paid')->avg('total'),
            'last_order_date' => $customer->orders()->latest()->first()?->created_at,
            'orders_pending' => $customer->orders()->where('status', 'pending')->count(),
            'orders_processing' => $customer->orders()->where('status', 'processing')->count(),
            'orders_delivered' => $customer->orders()->where('status', 'delivered')->count()
        ];

        return response()->json([
            'customer' => $customer,
            'stats' => $stats
        ]);
    }

    public function update(Request $request, $id)
    {
        $customer = User::findOrFail($id);

        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|unique:users,email,' . $id,
            'phone' => 'nullable|string|max:20',
            'status' => 'sometimes|required|in:active,inactive,suspended',
            'email_notifications' => 'boolean',
            'sms_notifications' => 'boolean',
            'order_updates' => 'boolean',
            'promotional_emails' => 'boolean'
        ]);

        $customer->update($request->only([
            'name', 'email', 'phone', 'status',
            'email_notifications', 'sms_notifications',
            'order_updates', 'promotional_emails'
        ]));

        return response()->json([
            'customer' => $customer,
            'message' => 'Customer updated successfully'
        ]);
    }

    public function destroy($id)
    {
        $customer = User::findOrFail($id);

        // Check if customer has orders
        if ($customer->orders()->exists()) {
            return response()->json([
                'message' => 'Cannot delete customer with existing orders'
            ], 400);
        }

        $customer->delete();

        return response()->json([
            'message' => 'Customer deleted successfully'
        ]);
    }

    public function resetPassword(Request $request, $id)
    {
        $request->validate([
            'password' => 'required|string|min:8|confirmed'
        ]);

        $customer = User::findOrFail($id);
        $customer->update([
            'password' => Hash::make($request->password)
        ]);

        return response()->json([
            'message' => 'Password reset successfully'
        ]);
    }

    public function getOrders($id)
    {
        $orders = Order::with(['items.product', 'shippingAddress'])
            ->where('user_id', $id)
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json($orders);
    }

    public function getWishlist($id)
    {
        $wishlist = User::findOrFail($id)
            ->wishlist()
            ->with('product.images')
            ->paginate(20);

        return response()->json($wishlist);
    }

    public function sendEmail(Request $request, $id)
    {
        $request->validate([
            'subject' => 'required|string|max:255',
            'message' => 'required|string'
        ]);

        $customer = User::findOrFail($id);

        // Send email logic here
        // Mail::to($customer->email)->send(new CustomerEmail($request->subject, $request->message));

        return response()->json([
            'message' => 'Email sent successfully'
        ]);
    }

    public function exportCustomers(Request $request)
    {
        $query = User::where('role', 'customer');

        if ($request->has('start_date')) {
            $query->where('created_at', '>=', $request->start_date);
        }
        if ($request->has('end_date')) {
            $query->where('created_at', '<=', $request->end_date);
        }
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $customers = $query->get();

        // Generate export file
        // This would typically generate a CSV or Excel file

        return response()->json([
            'message' => 'Export completed',
            'total_customers' => $customers->count(),
            'download_url' => '/api/admin/customers/export/download/customers.csv'
        ]);
    }
}