<?php

use Illuminate\Support\Facades\Route;
use Modules\NsContainerManagement\Http\Controllers\DashboardController;
use Modules\NsContainerManagement\Http\Controllers\ProductContainerController;
use Modules\NsContainerManagement\Http\Controllers\ContainerReportController;

Route::prefix('dashboard/container-management')->middleware(['web', 'auth'])->group(function () {
    Route::get('types', [DashboardController::class, 'containerTypes'])->name('ns.dashboard.container-types');
    Route::get('types/create', [DashboardController::class, 'createContainerType'])->name('ns.dashboard.container-types.create');
    Route::get('types/edit/{id}', [DashboardController::class, 'editContainerType'])->name('ns.dashboard.container-types.edit');
    
    Route::get('inventory', [DashboardController::class, 'inventory'])->name('ns.dashboard.container-inventory');
    Route::get('adjust', [DashboardController::class, 'adjustStock'])->name('ns.dashboard.container-adjust');
    
    Route::get('receive', [DashboardController::class, 'receive'])->name('ns.dashboard.container-receive');
    Route::get('customers', [DashboardController::class, 'customers'])->name('ns.dashboard.container-customers');
    Route::get('charge', [DashboardController::class, 'charge'])->name('ns.dashboard.container-charge');
    Route::post('charge/process', [DashboardController::class, 'processCharge'])->name('ns.dashboard.container-charge.process');
    Route::get('reports', [DashboardController::class, 'reports'])->name('ns.dashboard.container-reports');
    Route::get('reports/summary', [ContainerReportController::class, 'getSummary'])->name('ns.container-management.reports.summary');
    Route::get('reports/movements', [ContainerReportController::class, 'getMovements'])->name('ns.container-management.reports.movements');
    Route::get('reports/charges', [ContainerReportController::class, 'getCharges'])->name('ns.container-management.reports.charges');
    Route::get('reports/balances', [ContainerReportController::class, 'getCustomerBalances'])->name('ns.container-management.reports.balances');
    Route::get('reports/export', [ContainerReportController::class, 'export'])->name('ns.container-management.reports.export');
    Route::get('reports/filters', [DashboardController::class, 'filters'])->name('ns.container-management.reports.filters');

    // POS Specific Internal API (uses web session)
    Route::post('products/calculate', [ProductContainerController::class, 'calculate'])->name('ns.container-management.calculate');
});
