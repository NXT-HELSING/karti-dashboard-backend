<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BrandController as ApiBrandController;
use App\Http\Controllers\Api\DenominationController;
use App\Http\Controllers\Api\PurchaseController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\InventoryController;
use App\Http\Controllers\Admin\BrandController as AdminBrandController;
use App\Http\Controllers\Admin\ReportController;

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    
    // Customer routes
    Route::get('/user/profile', [AuthController::class, 'profile']);
    Route::put('/user/profile', [AuthController::class, 'updateProfile']);
    
    // Purchase routes
    Route::get('/purchase/history', [PurchaseController::class, 'history']);
    Route::get('/purchase/balance', [PurchaseController::class, 'balance']);
    Route::post('/purchase', [PurchaseController::class, 'purchase']);
    
    // Product routes (dynamic)
    Route::get('/brands', [ApiBrandController::class, 'index']);
    Route::get('/brands/{brandId}/denominations', [DenominationController::class, 'index']);
    
    // Admin routes
    Route::middleware('admin')->prefix('admin')->group(function () {
        // Dashboard
        Route::get('/dashboard', [DashboardController::class, 'overview']);
        
        // Customer management
        Route::get('/customers', [CustomerController::class, 'index']);
        Route::get('/customers/{id}', [CustomerController::class, 'show']);
        Route::put('/customers/{id}/status', [CustomerController::class, 'updateStatus']);
        Route::post('/customers/{id}/balance', [CustomerController::class, 'addBalance']);
        
        // Inventory management
        Route::get('/inventory', [InventoryController::class, 'index']);
        Route::put('/inventory/denominations/{id}', [InventoryController::class, 'updateDenomination']);
        Route::post('/inventory/sync', [InventoryController::class, 'syncFromProvider']);
        
        // Brand management
        Route::get('/brands', [AdminBrandController::class, 'index']);
        Route::post('/brands', [AdminBrandController::class, 'store']);
        Route::put('/brands/{id}', [AdminBrandController::class, 'update']);
        Route::post('/brands/sync', [AdminBrandController::class, 'syncFromProvider']);
    });
});
