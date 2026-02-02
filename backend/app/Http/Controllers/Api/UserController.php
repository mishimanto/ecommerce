<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Address;
use App\Models\Wishlist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    public function profile()
    {
        $user = Auth::user();
        return response()->json($user);
    }

    public function updateProfile(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|max:20',
            'avatar' => 'sometimes|image|max:2048'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user->update($request->only(['name', 'phone']));

        if ($request->hasFile('avatar')) {
            $path = $request->file('avatar')->store('avatars', 'public');
            $user->update(['avatar' => $path]);
        }

        return response()->json([
            'user' => $user,
            'message' => 'Profile updated successfully'
        ]);
    }

    public function changePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required',
            'new_password' => 'required|min:8|confirmed'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = Auth::user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'message' => 'Current password is incorrect'
            ], 400);
        }

        $user->update([
            'password' => Hash::make($request->new_password)
        ]);

        return response()->json([
            'message' => 'Password changed successfully'
        ]);
    }

    // Address Management
    public function addresses()
    {
        $user = Auth::user();
        $addresses = $user->addresses()->get();
        return response()->json($addresses);
    }

    public function addAddress(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'recipient_name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'address_line_1' => 'required|string|max:500',
            'address_line_2' => 'nullable|string|max:500',
            'city' => 'required|string|max:100',
            'state' => 'required|string|max:100',
            'country' => 'required|string|max:100',
            'postal_code' => 'required|string|max:20',
            'is_default' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = Auth::user();

        // If setting as default, remove default from other addresses
        if ($request->is_default) {
            $user->addresses()->update(['is_default' => false]);
        }

        $address = $user->addresses()->create($request->all());

        return response()->json([
            'address' => $address,
            'message' => 'Address added successfully'
        ]);
    }

    public function updateAddress(Request $request, $id)
    {
        $address = Address::where('user_id', Auth::id())->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'recipient_name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|max:20',
            'address_line_1' => 'sometimes|string|max:500',
            'address_line_2' => 'nullable|string|max:500',
            'city' => 'sometimes|string|max:100',
            'state' => 'sometimes|string|max:100',
            'country' => 'sometimes|string|max:100',
            'postal_code' => 'sometimes|string|max:20',
            'is_default' => 'sometimes|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // If setting as default, remove default from other addresses
        if ($request->is_default) {
            Auth::user()->addresses()->where('id', '!=', $id)->update(['is_default' => false]);
        }

        $address->update($request->all());

        return response()->json([
            'address' => $address,
            'message' => 'Address updated successfully'
        ]);
    }

    public function deleteAddress($id)
    {
        $address = Address::where('user_id', Auth::id())->findOrFail($id);
        $address->delete();

        return response()->json([
            'message' => 'Address deleted successfully'
        ]);
    }

    // Wishlist Management
    public function wishlist()
    {
        $user = Auth::user();
        $wishlist = $user->wishlist()->with('product.images')->get();
        return response()->json($wishlist);
    }

    public function addToWishlist(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id'
        ]);

        $user = Auth::user();

        // Check if already in wishlist
        $exists = $user->wishlist()->where('product_id', $request->product_id)->exists();
        
        if ($exists) {
            return response()->json([
                'message' => 'Product already in wishlist'
            ], 400);
        }

        $user->wishlist()->create([
            'product_id' => $request->product_id
        ]);

        return response()->json([
            'message' => 'Product added to wishlist'
        ]);
    }

    public function removeFromWishlist($productId)
    {
        $user = Auth::user();
        $user->wishlist()->where('product_id', $productId)->delete();

        return response()->json([
            'message' => 'Product removed from wishlist'
        ]);
    }

    public function clearWishlist()
    {
        $user = Auth::user();
        $user->wishlist()->delete();

        return response()->json([
            'message' => 'Wishlist cleared'
        ]);
    }

    // Guest wishlist (using cookies)
    public function guestWishlist(Request $request)
    {
        $wishlistIds = json_decode($request->cookie('wishlist', '[]'), true);
        
        if (empty($wishlistIds)) {
            return response()->json([]);
        }

        $products = \App\Models\Product::with('images')
            ->whereIn('id', $wishlistIds)
            ->get();

        return response()->json($products);
    }

    public function syncWishlist(Request $request)
    {
        $user = Auth::user();
        $guestWishlist = json_decode($request->cookie('wishlist', '[]'), true);

        if (!empty($guestWishlist)) {
            foreach ($guestWishlist as $productId) {
                if (!$user->wishlist()->where('product_id', $productId)->exists()) {
                    $user->wishlist()->create(['product_id' => $productId]);
                }
            }
        }

        return response()->json([
            'message' => 'Wishlist synced successfully'
        ]);
    }

    // Notification preferences
    public function notificationPreferences()
    {
        $user = Auth::user();
        $preferences = [
            'email_notifications' => $user->email_notifications ?? true,
            'sms_notifications' => $user->sms_notifications ?? true,
            'order_updates' => $user->order_updates ?? true,
            'promotional_emails' => $user->promotional_emails ?? true
        ];

        return response()->json($preferences);
    }

    public function updateNotificationPreferences(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'email_notifications' => 'boolean',
            'sms_notifications' => 'boolean',
            'order_updates' => 'boolean',
            'promotional_emails' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user->update($request->all());

        return response()->json([
            'message' => 'Notification preferences updated'
        ]);
    }

    // Account deletion
    public function deleteAccount()
    {
        $user = Auth::user();
        
        // Soft delete user
        $user->delete();

        // Revoke all tokens
        $user->tokens()->delete();

        return response()->json([
            'message' => 'Account deleted successfully'
        ]);
    }
}