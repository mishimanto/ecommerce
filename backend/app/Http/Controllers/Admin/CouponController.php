<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CouponController extends Controller
{
    public function index(Request $request)
    {
        $query = Coupon::query();

        // Search
        if ($request->has('search')) {
            $query->where('code', 'like', "%{$request->search}%")
                  ->orWhere('description', 'like', "%{$request->search}%");
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Filter by date
        if ($request->has('start_date')) {
            $query->where('start_date', '>=', $request->start_date);
        }
        if ($request->has('end_date')) {
            $query->where('end_date', '<=', $request->end_date);
        }

        $coupons = $query->orderBy('created_at', 'desc')->paginate(20);

        // Get statistics
        $stats = [
            'total' => Coupon::count(),
            'active' => Coupon::where('status', 'active')->count(),
            'expired' => Coupon::where('end_date', '<', now())->count(),
            'upcoming' => Coupon::where('start_date', '>', now())->count()
        ];

        return response()->json([
            'coupons' => $coupons,
            'stats' => $stats
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'code' => 'required|string|unique:coupons,code|max:50',
            'type' => 'required|in:percentage,fixed',
            'value' => 'required|numeric|min:0',
            'min_order_amount' => 'nullable|numeric|min:0',
            'max_discount_amount' => 'nullable|numeric|min:0',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'usage_limit' => 'nullable|integer|min:1',
            'usage_limit_per_user' => 'nullable|integer|min:1',
            'description' => 'nullable|string',
            'status' => 'required|in:active,inactive',
            'applicable_to' => 'required|in:all_products,specific_products,specific_categories',
            'product_ids' => 'nullable|array',
            'product_ids.*' => 'exists:products,id',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'exists:categories,id',
            'user_ids' => 'nullable|array',
            'user_ids.*' => 'exists:users,id'
        ]);

        DB::beginTransaction();

        try {
            $coupon = Coupon::create([
                'code' => strtoupper($request->code),
                'type' => $request->type,
                'value' => $request->value,
                'min_order_amount' => $request->min_order_amount,
                'max_discount_amount' => $request->max_discount_amount,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'usage_limit' => $request->usage_limit,
                'usage_limit_per_user' => $request->usage_limit_per_user,
                'description' => $request->description,
                'status' => $request->status,
                'applicable_to' => $request->applicable_to
            ]);

            // Handle product associations
            if ($request->applicable_to === 'specific_products' && $request->has('product_ids')) {
                $coupon->products()->attach($request->product_ids);
            }

            // Handle category associations
            if ($request->applicable_to === 'specific_categories' && $request->has('category_ids')) {
                $coupon->categories()->attach($request->category_ids);
            }

            // Handle user restrictions
            if ($request->has('user_ids')) {
                $coupon->users()->attach($request->user_ids);
            }

            DB::commit();

            return response()->json([
                'coupon' => $coupon->load(['products', 'categories', 'users']),
                'message' => 'Coupon created successfully'
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to create coupon: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        $coupon = Coupon::with(['products', 'categories', 'users', 'usageHistory.user'])->findOrFail($id);
        
        // Get usage statistics
        $usageStats = [
            'total_used' => $coupon->usageHistory()->count(),
            'total_discount' => $coupon->usageHistory()->sum('discount_amount'),
            'unique_users' => $coupon->usageHistory()->distinct('user_id')->count('user_id'),
            'remaining_uses' => $coupon->usage_limit ? $coupon->usage_limit - $coupon->usageHistory()->count() : null
        ];

        return response()->json([
            'coupon' => $coupon,
            'usage_stats' => $usageStats
        ]);
    }

    public function update(Request $request, $id)
    {
        $coupon = Coupon::findOrFail($id);

        $request->validate([
            'code' => 'required|string|unique:coupons,code,' . $id . '|max:50',
            'type' => 'required|in:percentage,fixed',
            'value' => 'required|numeric|min:0',
            'min_order_amount' => 'nullable|numeric|min:0',
            'max_discount_amount' => 'nullable|numeric|min:0',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'usage_limit' => 'nullable|integer|min:1',
            'usage_limit_per_user' => 'nullable|integer|min:1',
            'description' => 'nullable|string',
            'status' => 'required|in:active,inactive',
            'applicable_to' => 'required|in:all_products,specific_products,specific_categories',
            'product_ids' => 'nullable|array',
            'product_ids.*' => 'exists:products,id',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'exists:categories,id',
            'user_ids' => 'nullable|array',
            'user_ids.*' => 'exists:users,id'
        ]);

        DB::beginTransaction();

        try {
            $coupon->update([
                'code' => strtoupper($request->code),
                'type' => $request->type,
                'value' => $request->value,
                'min_order_amount' => $request->min_order_amount,
                'max_discount_amount' => $request->max_discount_amount,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'usage_limit' => $request->usage_limit,
                'usage_limit_per_user' => $request->usage_limit_per_user,
                'description' => $request->description,
                'status' => $request->status,
                'applicable_to' => $request->applicable_to
            ]);

            // Sync product associations
            if ($request->applicable_to === 'specific_products') {
                $coupon->products()->sync($request->product_ids ?? []);
            } else {
                $coupon->products()->detach();
            }

            // Sync category associations
            if ($request->applicable_to === 'specific_categories') {
                $coupon->categories()->sync($request->category_ids ?? []);
            } else {
                $coupon->categories()->detach();
            }

            // Sync user restrictions
            $coupon->users()->sync($request->user_ids ?? []);

            DB::commit();

            return response()->json([
                'coupon' => $coupon->fresh(['products', 'categories', 'users']),
                'message' => 'Coupon updated successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to update coupon: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        $coupon = Coupon::findOrFail($id);

        // Check if coupon has been used
        if ($coupon->usageHistory()->exists()) {
            return response()->json([
                'message' => 'Cannot delete coupon that has been used'
            ], 400);
        }

        $coupon->delete();

        return response()->json([
            'message' => 'Coupon deleted successfully'
        ]);
    }

    public function validateCoupon(Request $request)
    {
        $request->validate([
            'code' => 'required|string',
            'amount' => 'required|numeric|min:0',
            'user_id' => 'nullable|exists:users,id',
            'product_ids' => 'nullable|array',
            'product_ids.*' => 'exists:products,id'
        ]);

        $coupon = Coupon::where('code', strtoupper($request->code))
            ->where('status', 'active')
            ->where('start_date', '<=', now())
            ->where('end_date', '>=', now())
            ->first();

        if (!$coupon) {
            return response()->json([
                'valid' => false,
                'message' => 'Invalid or expired coupon'
            ]);
        }

        // Check usage limit
        if ($coupon->usage_limit && $coupon->usageHistory()->count() >= $coupon->usage_limit) {
            return response()->json([
                'valid' => false,
                'message' => 'Coupon usage limit reached'
            ]);
        }

        // Check per user limit
        if ($request->user_id && $coupon->usage_limit_per_user) {
            $userUsage = $coupon->usageHistory()
                ->where('user_id', $request->user_id)
                ->count();
            
            if ($userUsage >= $coupon->usage_limit_per_user) {
                return response()->json([
                    'valid' => false,
                    'message' => 'You have reached the maximum usage limit for this coupon'
                ]);
            }
        }

        // Check minimum order amount
        if ($coupon->min_order_amount && $request->amount < $coupon->min_order_amount) {
            return response()->json([
                'valid' => false,
                'message' => 'Minimum order amount not met'
            ]);
        }

        // Check user restrictions
        if ($coupon->users()->exists() && $request->user_id) {
            if (!$coupon->users()->where('user_id', $request->user_id)->exists()) {
                return response()->json([
                    'valid' => false,
                    'message' => 'Coupon not available for your account'
                ]);
            }
        }

        // Check product restrictions
        if ($coupon->applicable_to !== 'all_products' && $request->has('product_ids')) {
            if ($coupon->applicable_to === 'specific_products') {
                $validProducts = $coupon->products()->whereIn('product_id', $request->product_ids)->exists();
                if (!$validProducts) {
                    return response()->json([
                        'valid' => false,
                        'message' => 'Coupon not applicable to selected products'
                    ]);
                }
            } elseif ($coupon->applicable_to === 'specific_categories') {
                $productCategories = DB::table('products')
                    ->whereIn('id', $request->product_ids)
                    ->whereIn('category_id', $coupon->categories()->pluck('category_id'))
                    ->exists();
                
                if (!$productCategories) {
                    return response()->json([
                        'valid' => false,
                        'message' => 'Coupon not applicable to selected product categories'
                    ]);
                }
            }
        }

        // Calculate discount
        $discount = $this->calculateDiscount($coupon, $request->amount);

        return response()->json([
            'valid' => true,
            'coupon' => $coupon,
            'discount' => $discount,
            'message' => 'Coupon applied successfully'
        ]);
    }

    private function calculateDiscount($coupon, $amount)
    {
        if ($coupon->type === 'percentage') {
            $discount = ($coupon->value / 100) * $amount;
        } else {
            $discount = $coupon->value;
        }

        // Apply max discount limit
        if ($coupon->max_discount_amount && $discount > $coupon->max_discount_amount) {
            $discount = $coupon->max_discount_amount;
        }

        // Ensure discount doesn't exceed order amount
        if ($discount > $amount) {
            $discount = $amount;
        }

        return round($discount, 2);
    }

    public function getUsageHistory($id)
    {
        $history = DB::table('coupon_usage_history')
            ->join('users', 'coupon_usage_history.user_id', '=', 'users.id')
            ->join('orders', 'coupon_usage_history.order_id', '=', 'orders.id')
            ->where('coupon_id', $id)
            ->select(
                'coupon_usage_history.*',
                'users.name as user_name',
                'users.email as user_email',
                'orders.order_number',
                'orders.total as order_total'
            )
            ->orderBy('coupon_usage_history.created_at', 'desc')
            ->paginate(20);

        return response()->json($history);
    }
}