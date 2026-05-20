<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\BrandController;
use App\Http\Controllers\Api\DenominationController;
use App\Http\Controllers\Api\PurchaseController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Admin\StatisticsController;
use App\Http\Controllers\Admin\CustomerController as AdminCustomerController;
use App\Http\Controllers\Admin\InventoryController;

// Public routes
Route::post('/register', [App\Http\Controllers\Api\AuthController::class, 'register']);
Route::post('/login', [App\Http\Controllers\Api\AuthController::class, 'login']);

// Protected routes (require authentication)
Route::middleware('auth:sanctum')->group(function () {
    
    // Customer routes
    Route::get('/user/profile', [App\Http\Controllers\Api\UserController::class, 'profile']);
    Route::put('/user/profile', [App\Http\Controllers\Api\UserController::class, 'updateProfile']);
    
    // Purchase routes
    Route::get('/purchase/history', [PurchaseController::class, 'history']);
    Route::get('/purchase/balance', [PurchaseController::class, 'balance']);
    Route::post('/purchase', [PurchaseController::class, 'purchase']);
    
    // Product routes (dynamic)
    Route::get('/brands', [BrandController::class, 'index']);
    Route::get('/brands/{brandId}/denominations', [DenominationController::class, 'index']);
    
    // Admin routes (with admin middleware)
    Route::middleware('admin')->prefix('admin')->group(function () {
        // Dashboard & Statistics
        Route::get('/dashboard', [StatisticsController::class, 'overview']);
        Route::get('/top-customers', [StatisticsController::class, 'topCustomers']);
        
        // Customer management
        Route::get('/customers', [AdminCustomerController::class, 'index']);
        Route::get('/customers/{id}', [AdminCustomerController::class, 'show']);
        Route::put('/customers/{id}/status', [AdminCustomerController::class, 'updateStatus']);
        
        // Inventory management
        Route::get('/inventory', [InventoryController::class, 'index']);
        Route::put('/inventory/denominations/{id}', [InventoryController::class, 'updateDenomination']);
        Route::post('/inventory/sync', [InventoryController::class, 'syncFromProvider']);
        
        // Brand management
        Route::post('/brands/sync', [BrandController::class, 'syncBrands']);
        Route::put('/brands/{id}', [BrandController::class, 'update']);
        Route::post('/brands', [BrandController::class, 'store']);
        
        // Reports
        Route::get('/reports/sales', [StatisticsController::class, 'salesReport']);
        Route::get('/reports/customers', [StatisticsController::class, 'customerReport']);
    });
});
