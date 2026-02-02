<?php

use Illuminate\Support\Facades\Route;
use Modules\NsSpecialCustomer\Http\Controllers\CashbackController;
use Modules\NsSpecialCustomer\Http\Controllers\SpecialCustomerController;

// Main API routes with permission middleware
Route::middleware( ['auth:sanctum', 'ns.special-customer.permission:settings'] )->prefix( 'special-customer' )->group( function () {

    // Config endpoint - requires settings permission
    Route::get( '/config', [SpecialCustomerController::class, 'getConfig'] );

    // Dashboard stats endpoint
    Route::get( '/stats', [SpecialCustomerController::class, 'getStats'] );

    // Customer management endpoints with view permission
    Route::middleware( 'ns.special-customer.permission:view' )->group( function () {
        Route::get( '/check/{customerId}', [SpecialCustomerController::class, 'checkCustomerSpecialStatus'] );
    } );

    Route::get( '/customers', [SpecialCustomerController::class, 'getCustomersList'] )
        ->middleware( 'ns.special-customer.permission:manage' );

    // Financial operations with rate limiting and permission checks
    Route::middleware( ['throttle:10,1', 'ns.special-customer.permission:topup'] )->group( function () {
        Route::post( '/topup', [SpecialCustomerController::class, 'topupAccount'] );

        Route::post( '/settings', [SpecialCustomerController::class, 'updateSettings'] )
            ->middleware( 'ns.special-customer.permission:settings' );
    } );
} );

// Balance endpoint - OUTSIDE main group to avoid settings permission requirement
// Accessible with manage OR pay-outstanding-tickets permission
// Users with 'manage' or 'pay-outstanding-tickets' permission can view any customer's balance
// Regular users can only view their own balance (with ownership check)
Route::middleware( ['auth:sanctum'] )->prefix( 'special-customer' )->group( function () {
    Route::get( '/balance/{customerId}', [SpecialCustomerController::class, 'getCustomerBalance'] )
        ->middleware( 'ns.special-customer.balance-access' );
} );

// Cashback routes with stricter rate limiting
Route::middleware( ['auth:sanctum', 'throttle:20,1', 'ns.special-customer.permission:cashback'] )
    ->prefix( 'special-customer/cashback' )
    ->group( function () {

        // Read operations
        Route::get( '/', [CashbackController::class, 'index'] );
        Route::get( '/statistics', [CashbackController::class, 'getStatistics'] );

        // IDOR protection: users can only view their own cashback summary unless they have manage permission
        Route::get( '/customer/{customerId}', [CashbackController::class, 'customerSummary'] )
            ->middleware( 'ns.special-customer.ownership:customerId' );

        // Financial operations with stricter rate limiting (5 per minute)
        Route::middleware( ['throttle:5,1'] )->group( function () {
            Route::post( '/', [CashbackController::class, 'process'] );
            Route::delete( '/{id}', [CashbackController::class, 'delete'] );
        } );
    } );

// CRUD API endpoints (following NexoPOS pattern)
Route::middleware( ['auth:sanctum'] )->group( function () {

    // Special Customers CRUD
    Route::get( '/crud/ns.special-customers', function () {
        $crudClass = \Modules\NsSpecialCustomer\Crud\SpecialCustomerCrud::class;
        $resource = new $crudClass;

        return $resource->getEntries();
    } );

    Route::post( '/crud/ns.special-customers', function () {
        $crudClass = \Modules\NsSpecialCustomer\Crud\SpecialCustomerCrud::class;
        $resource = new $crudClass;

        return $resource->createEntry( request() );
    } );

    Route::get( '/crud/ns.special-customers/{id}', function ( $id ) {
        $crudClass = \Modules\NsSpecialCustomer\Crud\SpecialCustomerCrud::class;
        $resource = new $crudClass;

        return $resource->getEntry( $id );
    } );

    Route::put( '/crud/ns.special-customers/{id}', function ( $id ) {
        $crudClass = \Modules\NsSpecialCustomer\Crud\SpecialCustomerCrud::class;
        $resource = new $crudClass;

        return $resource->updateEntry( $id, request() );
    } );

    Route::delete( '/crud/ns.special-customers/{id}', function ( $id ) {
        $crudClass = \Modules\NsSpecialCustomer\Crud\SpecialCustomerCrud::class;
        $resource = new $crudClass;

        return $resource->deleteEntry( $id );
    } );

    // Cashback CRUD
    Route::get( '/crud/ns.special-customer-cashback', function () {
        $crudClass = \Modules\NsSpecialCustomer\Crud\SpecialCashbackCrud::class;
        $resource = new $crudClass;

        return $resource->getEntries();
    } );

    Route::post( '/crud/ns.special-customer-cashback', function () {
        $crudClass = \Modules\NsSpecialCustomer\Crud\SpecialCashbackCrud::class;
        $resource = new $crudClass;

        return $resource->createEntry( request() );
    } );

    Route::get( '/crud/ns.special-customer-cashback/{id}', function ( $id ) {
        $crudClass = \Modules\NsSpecialCustomer\Crud\SpecialCashbackCrud::class;
        $resource = new $crudClass;

        return $resource->getEntry( $id );
    } );

    Route::put( '/crud/ns.special-customer-cashback/{id}', function ( $id ) {
        $crudClass = \Modules\NsSpecialCustomer\Crud\SpecialCashbackCrud::class;
        $resource = new $crudClass;

        return $resource->updateEntry( $id, request() );
    } );

    Route::delete( '/crud/ns.special-customer-cashback/{id}', function ( $id ) {
        $crudClass = \Modules\NsSpecialCustomer\Crud\SpecialCashbackCrud::class;
        $resource = new $crudClass;

        return $resource->deleteEntry( $id );
    } );

    // Top-up CRUD - Read only (create is handled by /api/special-customer/topup)
    Route::get( '/crud/ns.special-customer-topup', function () {
        $crudClass = \Modules\NsSpecialCustomer\Crud\CustomerTopupCrud::class;
        $resource = new $crudClass;

        return $resource->getEntries();
    } );

    Route::get( '/crud/ns.special-customer-topup/{id}', function ( $id ) {
        $crudClass = \Modules\NsSpecialCustomer\Crud\CustomerTopupCrud::class;
        $resource = new $crudClass;

        return $resource->getEntry( $id );
    } );

    // Outstanding Tickets CRUD - Read only
    Route::get( '/crud/ns.outstanding-tickets', function () {
        $crudClass = \Modules\NsSpecialCustomer\Crud\OutstandingTicketCrud::class;
        $resource = new $crudClass;

        return $resource->getEntries();
    } );

    Route::get( '/crud/ns.outstanding-tickets/{id}', function ( $id ) {
        $crudClass = \Modules\NsSpecialCustomer\Crud\OutstandingTicketCrud::class;
        $resource = new $crudClass;

        return $resource->getEntry( $id );
    } );
} );

// Outstanding Tickets payment endpoint
Route::middleware( ['auth:sanctum', 'ns.special-customer.permission:special.customer.pay-outstanding-tickets'] )
    ->post( '/special-customer/outstanding-tickets/pay', function () {
        $crudClass = \Modules\NsSpecialCustomer\Crud\OutstandingTicketCrud::class;
        $resource = new $crudClass;

        return $resource->payTicket( request() );
    } );

// Outstanding Tickets payment with method endpoint (Cash, Credit Card, Bank Transfer)
Route::middleware( ['auth:sanctum', 'ns.special-customer.permission:special.customer.pay-outstanding-tickets'] )
    ->post( '/special-customer/outstanding-tickets/pay-with-method', [\Modules\NsSpecialCustomer\Http\Controllers\OutstandingTicketsController::class, 'payWithMethod'] );
