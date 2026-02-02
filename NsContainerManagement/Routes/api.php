<?php

use Illuminate\Support\Facades\Route;
use Modules\NsContainerManagement\Http\Controllers\ContainerChargeController;
use Modules\NsContainerManagement\Http\Controllers\ContainerInventoryController;
use Modules\NsContainerManagement\Http\Controllers\ContainerMovementController;
use Modules\NsContainerManagement\Http\Controllers\ContainerTypeController;
use Modules\NsContainerManagement\Http\Controllers\CustomerContainerController;
use Modules\NsContainerManagement\Http\Controllers\ProductContainerController;

Route::prefix('api/container-management')->middleware(['auth:sanctum'])->group(function () {
    
    // Container Types
    Route::get('types', [ContainerTypeController::class, 'index']);
    Route::post('types', [ContainerTypeController::class, 'store']);
    Route::get('types/dropdown', [ContainerTypeController::class, 'dropdown']);
    Route::get('types/{id}', [ContainerTypeController::class, 'show']);
    Route::put('types/{id}', [ContainerTypeController::class, 'update']);
    Route::delete('types/{id}', [ContainerTypeController::class, 'destroy']);

    // Inventory
    Route::get('inventory', [ContainerInventoryController::class, 'index']);
    Route::get('inventory/history', [ContainerInventoryController::class, 'history']);
    Route::get('inventory/{typeId}', [ContainerInventoryController::class, 'show']);
    Route::post('inventory/adjust', [ContainerInventoryController::class, 'adjust']);

    // Core Operations - Give/Receive
    Route::post('give', [ContainerMovementController::class, 'give']);
    Route::post('receive', [ContainerMovementController::class, 'receive']);
    Route::get('movements', [ContainerMovementController::class, 'index']);
    Route::get('movements/{id}', [ContainerMovementController::class, 'show']);

    // Customer Balances
    Route::get('customers/balances', [CustomerContainerController::class, 'index']);
    Route::get('customers/overdue', [CustomerContainerController::class, 'overdue']);
    Route::get('customers/search', [CustomerContainerController::class, 'search']);
    Route::get('customers/{id}/balance', [CustomerContainerController::class, 'show']);
    Route::get('customers/{id}/movements', [CustomerContainerController::class, 'movements']);

    // Charging
    Route::get('charge/preview/{customerId}', [ContainerChargeController::class, 'preview']);
    Route::post('charge', [ContainerChargeController::class, 'charge']);
    Route::post('charge/all', [ContainerChargeController::class, 'chargeAll']);

    // Product Container Links (Stateless)
    Route::get('products/{productId}/container', [ProductContainerController::class, 'show']);
    Route::post('products/{productId}/container', [ProductContainerController::class, 'store']);
    Route::delete('products/{productId}/container', [ProductContainerController::class, 'destroy']);
    // Note: calculate is moved to web.php for POS session support
});
