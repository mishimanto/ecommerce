<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\ProductManagementController;
use App\Http\Controllers\Admin\OrderManagementController;

Route::prefix('admin')->middleware(['auth:sanctum', 'admin'])->group(function () {
    // Dashboard
    Route::get('/dashboard/stats', [AdminController::class, 'dashboardStats']);
    Route::get('/reports/sales', [AdminController::class, 'salesReport']);
    Route::get('/reports/inventory', [AdminController::class, 'inventoryReport']);
    Route::get('/reports/customers', [AdminController::class, 'customerReport']);
    Route::post('/reports/export', [AdminController::class, 'exportReport']);

    Route::get('/dashboard/stats', [AdminController::class, 'dashboardStats']);
    Route::get('/dashboard/sales-data', [AdminController::class, 'getSalesData']); 
    Route::get('/dashboard/order-status', [AdminController::class, 'getOrderStatus']); 
    Route::get('/dashboard/category-distribution', [AdminController::class, 'getCategoryDistribution']); 
    
    // Products
    Route::get('/products', [ProductManagementController::class, 'index']);
    Route::post('/products', [ProductManagementController::class, 'store']);
    Route::get('/products/{id}', [ProductManagementController::class, 'show']);
    Route::put('/products/{id}', [ProductManagementController::class, 'update']);
    Route::delete('/products/{id}', [ProductManagementController::class, 'destroy']);
    Route::post('/products/bulk-update', [ProductManagementController::class, 'bulkUpdate']);
    Route::post('/products/import', [ProductManagementController::class, 'importProducts']);
    Route::post('/products/export', [ProductManagementController::class, 'exportProducts']);
    Route::post('/products/{id}/stock', [ProductManagementController::class, 'updateStock']);
    Route::get('/products/{id}/stock-history', [ProductManagementController::class, 'getStockHistory']);
    Route::post('/products/{id}/duplicate', [ProductManagementController::class, 'duplicateProduct']);
    
    // Categories
    Route::apiResource('categories', \App\Http\Controllers\Admin\CategoryController::class);
    
    // Brands
    Route::apiResource('brands', \App\Http\Controllers\Admin\BrandController::class);
    
    // Orders
    Route::get('/orders', [OrderManagementController::class, 'index']);
    Route::get('/orders/{id}', [OrderManagementController::class, 'show']);
    Route::put('/orders/{id}/status', [OrderManagementController::class, 'updateStatus']);
    Route::put('/orders/{id}/payment-status', [OrderManagementController::class, 'updatePaymentStatus']);
    Route::post('/orders/{id}/notes', [OrderManagementController::class, 'addNote']);
    Route::get('/orders/{id}/notes', [OrderManagementController::class, 'getNotes']);
    Route::get('/orders/{id}/status-history', [OrderManagementController::class, 'getStatusHistory']);
    Route::post('/orders/{id}/shipments', [OrderManagementController::class, 'createShipment']);
    Route::put('/orders/{orderId}/shipments/{shipmentId}', [OrderManagementController::class, 'updateShipment']);
    Route::delete('/orders/{orderId}/shipments/{shipmentId}', [OrderManagementController::class, 'deleteShipment']);
    Route::post('/orders/{id}/refund', [OrderManagementController::class, 'refundOrder']);
    Route::get('/orders/{id}/invoice', [OrderManagementController::class, 'printInvoice']);
    Route::post('/orders/bulk-update', [OrderManagementController::class, 'bulkUpdateOrders']);
    Route::post('/orders/export', [OrderManagementController::class, 'exportOrders']);
    
    // Customers
    Route::get('/customers', [\App\Http\Controllers\Admin\CustomerController::class, 'index']);
    Route::get('/customers/{id}', [\App\Http\Controllers\Admin\CustomerController::class, 'show']);
    Route::put('/customers/{id}', [\App\Http\Controllers\Admin\CustomerController::class, 'update']);
    Route::delete('/customers/{id}', [\App\Http\Controllers\Admin\CustomerController::class, 'destroy']);
    
    // Coupons
    Route::apiResource('coupons', \App\Http\Controllers\Admin\CouponController::class);
    
    // CMS
    Route::apiResource('banners', \App\Http\Controllers\Admin\BannerController::class);
    Route::apiResource('pages', \App\Http\Controllers\Admin\PageController::class);
    
    // Settings
    Route::get('/settings', [\App\Http\Controllers\Admin\SettingController::class, 'index']);
    Route::put('/settings', [\App\Http\Controllers\Admin\SettingController::class, 'update']);
});