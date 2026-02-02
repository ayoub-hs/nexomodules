<?php

use Illuminate\Support\Facades\Route;
use Modules\NsManufacturing\Http\Controllers\ManufacturingController;
use App\Http\Middleware\NsRestrictMiddleware;

Route::prefix('dashboard/manufacturing')->middleware([
    'web', 
    'auth',
    \App\Http\Middleware\Authenticate::class,
    \App\Http\Middleware\CheckApplicationHealthMiddleware::class,
    \App\Http\Middleware\HandleCommonRoutesMiddleware::class,
])->group(function () {
    // BOMs - Protected by permissions
    Route::get('boms', [ManufacturingController::class, 'boms'])
        ->name('ns.dashboard.manufacturing-boms')
        ->middleware(NsRestrictMiddleware::arguments('nexopos.read.manufacturing-recipes'));
        
    Route::get('boms/create', [ManufacturingController::class, 'createBom'])
        ->name('ns.dashboard.manufacturing-boms.create')
        ->middleware(NsRestrictMiddleware::arguments('nexopos.create.manufacturing-recipes'));
        
    Route::get('boms/edit/{id}', [ManufacturingController::class, 'editBom'])
        ->name('ns.dashboard.manufacturing-boms.edit')
        ->middleware(NsRestrictMiddleware::arguments('nexopos.update.manufacturing-recipes'));
        
    Route::get('boms/explode/{id}', [ManufacturingController::class, 'explodeBom'])
        ->name('ns.dashboard.manufacturing-boms.explode')
        ->middleware(NsRestrictMiddleware::arguments('nexopos.read.manufacturing-recipes'));

    // BOM Items - Protected by permissions
    Route::get('bom-items', [ManufacturingController::class, 'bomItems'])
        ->name('ns.dashboard.manufacturing-bom-items')
        ->middleware(NsRestrictMiddleware::arguments('nexopos.read.manufacturing-recipes'));
        
    Route::get('bom-items/create', [ManufacturingController::class, 'createBomItem'])
        ->name('ns.dashboard.manufacturing-bom-items.create')
        ->middleware(NsRestrictMiddleware::arguments('nexopos.create.manufacturing-recipes'));
        
    Route::get('bom-items/edit/{id}', [ManufacturingController::class, 'editBomItem'])
        ->name('ns.dashboard.manufacturing-bom-items.edit')
        ->middleware(NsRestrictMiddleware::arguments('nexopos.update.manufacturing-recipes'));

    // Orders - Protected by permissions
    Route::get('orders', [ManufacturingController::class, 'orders'])
        ->name('ns.dashboard.manufacturing-orders')
        ->middleware(NsRestrictMiddleware::arguments('nexopos.read.manufacturing-orders'));
        
    Route::get('orders/create', [ManufacturingController::class, 'createOrder'])
        ->name('ns.dashboard.manufacturing-orders.create')
        ->middleware(NsRestrictMiddleware::arguments('nexopos.create.manufacturing-orders'));
        
    Route::get('orders/edit/{id}', [ManufacturingController::class, 'editOrder'])
        ->name('ns.dashboard.manufacturing-orders.edit')
        ->middleware(NsRestrictMiddleware::arguments('nexopos.update.manufacturing-orders'));
    
    // Actions - Protected by permissions
    Route::match(['get', 'post'], 'orders/{id}/start', [ManufacturingController::class, 'startOrder'])
        ->name('ns.dashboard.manufacturing-orders.start')
        ->middleware(NsRestrictMiddleware::arguments('nexopos.start.manufacturing-orders'));
        
    Route::match(['get', 'post'], 'orders/{id}/complete', [ManufacturingController::class, 'completeOrder'])
        ->name('ns.dashboard.manufacturing-orders.complete')
        ->middleware(NsRestrictMiddleware::arguments('nexopos.complete.manufacturing-orders'));
    
    // Analytics & Reports - Protected by permissions
    Route::get('analytics', [ManufacturingController::class, 'reports'])
        ->name('ns.dashboard.manufacturing-analytics')
        ->middleware(NsRestrictMiddleware::arguments('nexopos.view.manufacturing-costs'));
        
    Route::get('reports', [ManufacturingController::class, 'reports'])
        ->name('ns.dashboard.manufacturing-reports')
        ->middleware(NsRestrictMiddleware::arguments('nexopos.view.manufacturing-costs'));
        
    Route::get('reports/summary', [\Modules\NsManufacturing\Http\Controllers\ManufacturingReportController::class, 'getSummary'])
        ->name('ns.manufacturing.reports.summary')
        ->middleware(NsRestrictMiddleware::arguments('nexopos.view.manufacturing-costs'));
        
    Route::get('reports/history', [\Modules\NsManufacturing\Http\Controllers\ManufacturingReportController::class, 'getHistory'])
        ->name('ns.manufacturing.reports.history')
        ->middleware(NsRestrictMiddleware::arguments('nexopos.view.manufacturing-costs'));
        
    Route::get('reports/consumption', [\Modules\NsManufacturing\Http\Controllers\ManufacturingReportController::class, 'getConsumption'])
        ->name('ns.manufacturing.reports.consumption')
        ->middleware(NsRestrictMiddleware::arguments('nexopos.view.manufacturing-costs'));
        
    Route::get('reports/filters', [\Modules\NsManufacturing\Http\Controllers\ManufacturingReportController::class, 'getFilters'])
        ->name('ns.manufacturing.reports.filters')
        ->middleware(NsRestrictMiddleware::arguments('nexopos.view.manufacturing-costs'));
});
