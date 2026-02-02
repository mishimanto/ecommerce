<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\CourierController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\BrandController;
use App\Http\Controllers\Api\CategoryController;

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/social-login', [AuthController::class, 'socialLogin']);

// Products
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/featured', [ProductController::class, 'featured']);
Route::get('/products/new-arrivals', [ProductController::class, 'newArrivals']);
Route::get('/products/trending', [ProductController::class, 'trending']);
Route::get('/products/{id}', [ProductController::class, 'show']);
Route::get('/products/compare', [ProductController::class, 'compare']);

// Search
Route::get('/search', [SearchController::class, 'search']);
Route::get('/search/autocomplete', [SearchController::class, 'autocomplete']);
Route::get('/search/popular', [SearchController::class, 'popularSearches']);
Route::get('/search/advanced', [SearchController::class, 'advancedSearch']);

// Categories
Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/categories/{slug}', [CategoryController::class, 'show']);

// Brands
Route::get('/brands', [BrandController::class, 'index']);

// Guest cart
Route::get('/cart', [CartController::class, 'getCart']);
Route::post('/cart/add', [CartController::class, 'addToCart']);
Route::put('/cart/update/{itemId}', [CartController::class, 'updateCartItem']);
Route::delete('/cart/remove/{itemId}', [CartController::class, 'removeFromCart']);
Route::post('/cart/apply-coupon', [CartController::class, 'applyCoupon']);

// Guest wishlist
Route::get('/wishlist/guest', [UserController::class, 'guestWishlist']);

// Protected routes (require authentication)
Route::middleware(['auth:sanctum'])->group(function () {
    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    // User profile
    Route::get('/profile', [UserController::class, 'profile']);
    Route::put('/profile', [UserController::class, 'updateProfile']);
    Route::post('/change-password', [UserController::class, 'changePassword']);
    
    // Addresses
    Route::get('/addresses', [UserController::class, 'addresses']);
    Route::post('/addresses', [UserController::class, 'addAddress']);
    Route::put('/addresses/{id}', [UserController::class, 'updateAddress']);
    Route::delete('/addresses/{id}', [UserController::class, 'deleteAddress']);
    
    // Wishlist
    Route::get('/wishlist', [UserController::class, 'wishlist']);
    Route::post('/wishlist/add', [UserController::class, 'addToWishlist']);
    Route::delete('/wishlist/remove/{productId}', [UserController::class, 'removeFromWishlist']);
    Route::delete('/wishlist/clear', [UserController::class, 'clearWishlist']);
    Route::post('/wishlist/sync', [UserController::class, 'syncWishlist']);
    
    // Search history
    Route::get('/search/history', [SearchController::class, 'searchHistory']);
    Route::delete('/search/history/clear', [SearchController::class, 'clearSearchHistory']);
    
    // User cart
    Route::delete('/cart/clear', [CartController::class, 'clearCart']);
    
    // Checkout
    Route::get('/checkout/shipping-options', [CheckoutController::class, 'shippingOptions']);
    Route::post('/checkout/calculate-totals', [CheckoutController::class, 'calculateTotals']);
    Route::post('/checkout/place-order', [CheckoutController::class, 'placeOrder']);
    
    // Orders
    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/{id}', [OrderController::class, 'show']);
    Route::get('/orders/track/{orderNumber}', [OrderController::class, 'trackOrder']);
    Route::post('/orders/{id}/cancel', [OrderController::class, 'cancelOrder']);
    Route::post('/orders/{id}/return', [OrderController::class, 'requestReturn']);
    
    // Payments
    Route::post('/payment/create-intent', [PaymentController::class, 'createPaymentIntent']);
    Route::post('/payment/confirm', [PaymentController::class, 'confirmPayment']);
    Route::post('/payment/sslcommerz/init', [PaymentController::class, 'sslcommerzInit']);
    
    // Courier tracking
    Route::get('/track/{trackingNumber}', [CourierController::class, 'trackShipment']);
    
    // Notifications
    Route::get('/notifications/preferences', [UserController::class, 'notificationPreferences']);
    Route::put('/notifications/preferences', [UserController::class, 'updateNotificationPreferences']);
    
    // Account deletion
    Route::delete('/account', [UserController::class, 'deleteAccount']);
});

// Webhook routes (no auth required)
Route::post('/webhook/stripe', [PaymentController::class, 'webhookStripe']);
Route::post('/webhook/sslcommerz/success', [PaymentController::class, 'sslcommerzSuccess']);
Route::post('/webhook/courier/{courier}', [CourierController::class, 'webhook']);

// Admin routes (separate file)
require __DIR__.'/admin.php';