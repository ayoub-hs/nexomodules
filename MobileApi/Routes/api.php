<?php

use Illuminate\Support\Facades\Route;
use Modules\MobileApi\Http\Controllers\MobileSyncController;
use Modules\MobileApi\Http\Controllers\MobileCategoryController;
use Modules\MobileApi\Http\Controllers\MobileProductController;
use Modules\MobileApi\Http\Controllers\MobileOrdersController;
use Modules\MobileApi\Http\Controllers\MobileRegisterConfigController;

Route::prefix('api/mobile')
    ->middleware(['auth:sanctum'])
    ->group(function () {

        // Sync endpoints - Limited to prevent resource exhaustion
        Route::get('sync/bootstrap', [MobileSyncController::class, 'bootstrap'])
            ->middleware('throttle:10,1'); // 10 requests per minute
        Route::get('sync/delta', [MobileSyncController::class, 'delta'])
            ->middleware('throttle:30,1'); // 30 requests per minute
        Route::get('sync/status', [MobileSyncController::class, 'status'])
            ->middleware('throttle:60,1'); // 60 requests per minute

        // Category endpoints
        Route::get('categories/{id}/products', [MobileCategoryController::class, 'products'])
            ->where('id', '[0-9]+')
            ->middleware('throttle:60,1'); // 60 requests per minute

        // Product endpoints
        Route::post('products/search', [MobileProductController::class, 'search'])
            ->middleware('throttle:60,1'); // 60 requests per minute
        Route::get('products/{id}', [MobileProductController::class, 'show'])
            ->middleware('throttle:120,1'); // 120 requests per minute
        Route::get('products/barcode/{barcode}', [MobileProductController::class, 'searchByBarcode'])
            ->middleware('throttle:120,1'); // 120 requests per minute

        // Order endpoints
        Route::get('orders', [MobileOrdersController::class, 'index'])
            ->middleware('throttle:60,1'); // 60 requests per minute
        Route::get('orders/{order}', [MobileOrdersController::class, 'show'])
            ->middleware('throttle:120,1'); // 120 requests per minute
        Route::get('orders/sync', [MobileOrdersController::class, 'sync'])
            ->middleware('throttle:30,1'); // 30 requests per minute
        Route::post('orders/batch', [MobileOrdersController::class, 'batch'])
            ->middleware('throttle:20,1'); // 20 requests per minute - Most restrictive

        // Register config
        Route::get('register/config', [MobileRegisterConfigController::class, 'show'])
            ->middleware('throttle:60,1'); // 60 requests per minute
});
