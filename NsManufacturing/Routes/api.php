<?php

use Illuminate\Support\Facades\Route;
use Modules\NsManufacturing\Http\Controllers\ManufacturingController;
use Modules\NsManufacturing\Http\Middleware\NoCacheHeaders;

Route::prefix('mobile/manufacturing')->middleware([
    'api',
    'auth:sanctum',
    NoCacheHeaders::class,
])->group(function () {
    // Orders
    Route::get('orders', [ManufacturingController::class, 'mobileIndex']);
    Route::get('orders/{id}', [ManufacturingController::class, 'mobileShow']);
    Route::post('orders', [ManufacturingController::class, 'mobileStore']);
    Route::put('orders/{id}/start', [ManufacturingController::class, 'mobileStart']);
    Route::put('orders/{id}/complete', [ManufacturingController::class, 'mobileComplete']);

    // BOMs
    Route::get('boms', [ManufacturingController::class, 'mobileBoms']);
    Route::get('boms/{id}', [ManufacturingController::class, 'mobileBomShow']);
    Route::post('boms', [ManufacturingController::class, 'mobileBomStore']);
});
