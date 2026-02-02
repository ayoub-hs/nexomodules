<?php

use Illuminate\Support\Facades\Route;
use Modules\NsSpecialCustomer\Http\Controllers\SpecialCustomerController;

Route::prefix( 'dashboard/special-customer' )->middleware( [
    'web',
    'auth',
    \App\Http\Middleware\Authenticate::class,
    \App\Http\Middleware\CheckApplicationHealthMiddleware::class,
    \App\Http\Middleware\HandleCommonRoutesMiddleware::class,
] )->group( function () {
    // Main entry point - redirect to customers list
    Route::get( '/', function () {
        return redirect()->route( 'ns.dashboard.special-customer-customers' );
    } )->name( 'ns.dashboard.special-customer' );

    // CRUD Pages - using NexoPOS CRUD system
    Route::get( '/customers', function () {
        return view( 'NsSpecialCustomer::customers' );
    } )->name( 'ns.dashboard.special-customer-customers' );

    Route::get( '/cashback', function () {
        return view( 'NsSpecialCustomer::cashback' );
    } )->name( 'ns.dashboard.special-customer-cashback' );

    // Management Pages (still custom for now)
    Route::get( 'settings', [SpecialCustomerController::class, 'settingsPage'] )->name( 'ns.dashboard.special-customer-settings' );
    Route::get( 'topup', [SpecialCustomerController::class, 'topupPage'] )->name( 'ns.dashboard.special-customer-topup' );
    Route::get( 'topup/create', [SpecialCustomerController::class, 'createTopup'] )->name( 'ns.dashboard.special-customer-topup.create' );

    // Outstanding Tickets - Now using CRUD pattern
    Route::get( 'outstanding-tickets', function () {
        return view( 'NsSpecialCustomer::outstanding-tickets' );
    } )->name( 'ns.dashboard.special-customer-outstanding' );

    // Web route for wallet payment (form submission)
    Route::post( 'outstanding-tickets/pay', [\Modules\NsSpecialCustomer\Http\Controllers\OutstandingTicketsController::class, 'pay'] )
        ->name( 'ns.dashboard.special-customer-outstanding.pay' );

    // Payment page for outstanding tickets
    Route::get( 'outstanding-tickets/payment/{order}', [\Modules\NsSpecialCustomer\Http\Controllers\OutstandingTicketsController::class, 'payment'] )
        ->name( 'ns.dashboard.special-customer-outstanding.payment' );

    Route::get( 'balance/{customerId}', [SpecialCustomerController::class, 'balancePage'] )->name( 'ns.dashboard.special-customer-balance' );
    Route::get( 'statistics', [SpecialCustomerController::class, 'statisticsPage'] )->name( 'ns.dashboard.special-customer-statistics' );

    // CRUD Create/Edit Pages
    Route::get( 'cashback/create', function () {
        return view( 'NsSpecialCustomer::cashback.create' );
    } )->name( 'ns.dashboard.special-customer-cashback.create' );
    Route::get( 'cashback/edit/{id}', function ( $id ) {
        return view( 'NsSpecialCustomer::cashback.edit', compact( 'id' ) );
    } )->name( 'ns.dashboard.special-customer-cashback.edit' );
} );
