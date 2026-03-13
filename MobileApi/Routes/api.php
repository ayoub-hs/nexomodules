<?php

use Illuminate\Support\Facades\Route;
use Modules\MobileApi\Http\Controllers\MobileSyncController;
use Modules\MobileApi\Http\Controllers\MobileCategoryController;
use Modules\MobileApi\Http\Controllers\MobileInventoryController;
use Modules\MobileApi\Http\Controllers\MobileProductController;
use Modules\MobileApi\Http\Controllers\MobileUnitQuantityController;
use Modules\MobileApi\Http\Controllers\MobileTaxController;
use Modules\MobileApi\Http\Controllers\MobileUnitController;
use Modules\MobileApi\Http\Controllers\MobileOrdersController;
use Modules\MobileApi\Http\Controllers\MobileProviderController;
use Modules\MobileApi\Http\Controllers\MobileRegisterConfigController;
use Modules\MobileApi\Http\Controllers\MobileRegisterSyncController;
use Modules\MobileApi\Http\Controllers\MobileAuthController;
use Modules\MobileApi\Http\Controllers\MobileContainerController;
use Modules\MobileApi\Http\Middleware\NoCacheHeaders;

$mobileAuthMiddleware = ['auth:sanctum'];
$mobileNoCacheMiddleware = [NoCacheHeaders::class];

// Public authentication endpoints (no auth required)
Route::prefix('mobile/auth')
    ->middleware($mobileNoCacheMiddleware)
    ->group(function () {
        Route::post('login', [MobileAuthController::class, 'login'])
            ->middleware('throttle:5,1'); // 5 login attempts per minute
    });

// Protected authentication endpoints (auth required)
Route::prefix('mobile/auth')
    ->middleware(array_merge($mobileAuthMiddleware, $mobileNoCacheMiddleware))
    ->group(function () {
        Route::post('logout', [MobileAuthController::class, 'logout'])
            ->middleware('throttle:30,1');
        Route::get('permissions', [MobileAuthController::class, 'permissions'])
            ->middleware('throttle:60,1');
        Route::get('me', [MobileAuthController::class, 'me'])
            ->middleware('throttle:60,1');
    });

Route::prefix('cash-registers')
    ->middleware(array_merge($mobileAuthMiddleware, $mobileNoCacheMiddleware))
    ->group(function () {
        Route::post('sync/{register}', [MobileRegisterSyncController::class, 'sync'])
            ->where('register', '[0-9]+')
            ->middleware('throttle:60,1');
    });

Route::prefix('mobile')
    ->middleware(array_merge($mobileAuthMiddleware, $mobileNoCacheMiddleware))
    ->group(function () {

        // Sync endpoints - Limited to prevent resource exhaustion
        Route::get('sync/bootstrap', [MobileSyncController::class, 'bootstrap'])
            ->middleware('throttle:10,1'); // 10 requests per minute
        Route::get('sync/delta', [MobileSyncController::class, 'delta'])
            ->middleware('throttle:30,1'); // 30 requests per minute
        Route::get('sync/status', [MobileSyncController::class, 'status'])
            ->middleware('throttle:60,1'); // 60 requests per minute

        // Category endpoints
        Route::get('categories', [MobileCategoryController::class, 'index'])
            ->middleware('throttle:60,1'); // 60 requests per minute
        Route::get('categories/{id}/products', [MobileCategoryController::class, 'products'])
            ->where('id', '[0-9]+')
            ->middleware('throttle:60,1'); // 60 requests per minute

        // Catalog routes for mobile app
        Route::prefix('catalog')
            ->middleware(['throttle:60,1'])
            ->group(function () {
                // Get products by category (0 = all products)
                Route::get('category/{id}', [MobileCategoryController::class, 'getCategoryProducts'])
                    ->where('id', '[0-9]+');
                
                // Search products
                Route::post('search', [MobileProductController::class, 'search']);
                
                // Get single product
                Route::get('product/{id}', [MobileProductController::class, 'show'])
                    ->where('id', '[0-9]+');
            });

        // Product endpoints
        Route::get('products', [MobileProductController::class, 'index'])
            ->middleware('throttle:60,1'); // 60 requests per minute
        Route::post('products', [MobileProductController::class, 'store'])
            ->middleware('throttle:30,1');
        Route::post('products/search', [MobileProductController::class, 'search'])
            ->middleware('throttle:60,1'); // 60 requests per minute
        Route::put('products/{id}', [MobileProductController::class, 'update'])
            ->where('id', '[0-9]+')
            ->middleware('throttle:30,1');
        Route::get('products/{id}', [MobileProductController::class, 'show'])
            ->middleware('throttle:120,1'); // 120 requests per minute
        Route::get('products/barcode/{barcode}', [MobileProductController::class, 'searchByBarcode'])
            ->middleware('throttle:120,1'); // 120 requests per minute
        Route::patch('unit-quantities/{id}', [MobileUnitQuantityController::class, 'update'])
            ->where('id', '[0-9]+')
            ->middleware('throttle:30,1');
        Route::get('units', [MobileUnitController::class, 'index'])
            ->middleware('throttle:60,1'); // 60 requests per minute
        Route::get('unit-groups', [MobileUnitController::class, 'groups'])
            ->middleware('throttle:60,1');
        Route::get('tax-groups', [MobileTaxController::class, 'index'])
            ->middleware('throttle:60,1');

        // Inventory endpoints
        Route::prefix('inventory')
            ->middleware('throttle:60,1')
            ->group(function () {
                Route::post('adjust', [MobileInventoryController::class, 'adjust']);
                Route::get('history', [MobileInventoryController::class, 'history']);
            });

        // Order endpoints
        Route::get('orders', [MobileOrdersController::class, 'index'])
            ->middleware('throttle:60,1'); // 60 requests per minute
        Route::get('orders/sync', [MobileOrdersController::class, 'sync'])
            ->middleware('throttle:30,1'); // 30 requests per minute
        Route::get('orders/{order}', [MobileOrdersController::class, 'show'])
            ->where('order', '[0-9]+')
            ->middleware('throttle:120,1'); // 120 requests per minute
        Route::post('orders/batch', [MobileOrdersController::class, 'batch'])
            ->middleware('throttle:20,1'); // 20 requests per minute - Most restrictive

        // Register config
        Route::get('register/config', [MobileRegisterConfigController::class, 'show'])
            ->middleware('throttle:60,1'); // 60 requests per minute
        Route::get('config/register', [MobileRegisterConfigController::class, 'show'])
            ->middleware('throttle:60,1'); // compatibility alias for older Android builds

        // Providers
        Route::get('providers', [MobileProviderController::class, 'index'])
            ->middleware('throttle:60,1');

        // Manufacturing endpoints (proxy to module controller)
        if (class_exists(\Modules\NsManufacturing\Http\Controllers\ManufacturingController::class)) {
            Route::prefix('manufacturing')
                ->middleware(['throttle:60,1'])
                ->group(function () {
                    Route::get('orders', [\Modules\NsManufacturing\Http\Controllers\ManufacturingController::class, 'mobileIndex']);
                    Route::get('orders/{id}', [\Modules\NsManufacturing\Http\Controllers\ManufacturingController::class, 'mobileShow'])
                        ->where('id', '[0-9]+');
                    Route::post('orders', [\Modules\NsManufacturing\Http\Controllers\ManufacturingController::class, 'mobileStore']);
                    Route::put('orders/{id}/start', [\Modules\NsManufacturing\Http\Controllers\ManufacturingController::class, 'mobileStart'])
                        ->where('id', '[0-9]+');
                    Route::put('orders/{id}/complete', [\Modules\NsManufacturing\Http\Controllers\ManufacturingController::class, 'mobileComplete'])
                        ->where('id', '[0-9]+');
                    Route::get('boms', [\Modules\NsManufacturing\Http\Controllers\ManufacturingController::class, 'mobileBoms']);
                    Route::get('boms/{id}', [\Modules\NsManufacturing\Http\Controllers\ManufacturingController::class, 'mobileBomShow'])
                        ->where('id', '[0-9]+');
                    Route::post('boms', [\Modules\NsManufacturing\Http\Controllers\ManufacturingController::class, 'mobileBomStore']);
                });
        }

        // Container Management endpoints (proxy to module controllers)
        if (class_exists(\Modules\NsContainerManagement\Http\Controllers\ContainerTypeController::class)) {
            Route::prefix('containers')
                ->middleware(['throttle:60,1'])
                ->group(function () {
                    Route::get('types', [MobileContainerController::class, 'types']);
                    Route::get('inventory', [MobileContainerController::class, 'inventory']);
                    Route::post('adjust', [MobileContainerController::class, 'adjust']);
                    Route::post('receive', [MobileContainerController::class, 'receive']);
                    Route::get('customers/balances', [MobileContainerController::class, 'balances']);
                    Route::get('movements', [MobileContainerController::class, 'movements']);
                    Route::get('inventory/history', [MobileContainerController::class, 'history']);
                    Route::get('charge/preview/{customerId}', [MobileContainerController::class, 'previewCharge'])
                        ->where('customerId', '[0-9]+');
                    Route::post('charge', [MobileContainerController::class, 'charge']);
                });
        }

        // Procurements endpoints
        Route::prefix('procurements')
            ->middleware(['throttle:60,1'])
            ->group(function () {
                Route::get('/', [\Modules\MobileApi\Http\Controllers\MobileProcurementController::class, 'index']);
                Route::get('/{id}', [\Modules\MobileApi\Http\Controllers\MobileProcurementController::class, 'show'])
                    ->where('id', '[0-9]+');
                Route::post('/', [\Modules\MobileApi\Http\Controllers\MobileProcurementController::class, 'store']);
                Route::put('/{id}', [\Modules\MobileApi\Http\Controllers\MobileProcurementController::class, 'update'])
                    ->where('id', '[0-9]+');
                Route::put('/{id}/status', [\Modules\MobileApi\Http\Controllers\MobileProcurementController::class, 'updateStatus'])
                    ->where('id', '[0-9]+');
                Route::put('/{id}/receive', [\Modules\MobileApi\Http\Controllers\MobileProcurementController::class, 'receive'])
                    ->where('id', '[0-9]+');
                Route::put('/{id}/cancel', [\Modules\MobileApi\Http\Controllers\MobileProcurementController::class, 'cancel'])
                    ->where('id', '[0-9]+');
                Route::delete('/{id}', [\Modules\MobileApi\Http\Controllers\MobileProcurementController::class, 'destroy'])
                    ->where('id', '[0-9]+');
            });

        // Special Customer endpoints (proxy to module controller)
        if (class_exists(\Modules\NsSpecialCustomer\Http\Controllers\OutstandingTicketsController::class)) {
            Route::prefix('special-customer')
                ->middleware(['throttle:60,1'])
                ->group(function () {
                    Route::get('tickets', [\Modules\NsSpecialCustomer\Http\Controllers\OutstandingTicketsController::class, 'indexMobile']);
                    Route::get('tickets/{id}', [\Modules\NsSpecialCustomer\Http\Controllers\OutstandingTicketsController::class, 'showMobile'])
                        ->where('id', '[0-9]+');
                    Route::post('tickets/{id}/pay', [\Modules\NsSpecialCustomer\Http\Controllers\OutstandingTicketsController::class, 'payMobile'])
                        ->where('id', '[0-9]+');
                    Route::post('tickets/{id}/pay-from-wallet', [\Modules\NsSpecialCustomer\Http\Controllers\OutstandingTicketsController::class, 'payFromWalletMobile'])
                        ->where('id', '[0-9]+');
                    // Pay with method (cash, card, bank transfer, wallet)
                    Route::post('tickets/pay-with-method', [\Modules\NsSpecialCustomer\Http\Controllers\OutstandingTicketsController::class, 'payWithMethod']);
                    
                    // Wallet topup routes for mobile app
                    Route::get('wallet/topups', [\Modules\NsSpecialCustomer\Http\Controllers\SpecialCustomerController::class, 'getWalletTopups'])
                        ->middleware('ns.special-customer.permission:view');
                    Route::get('wallet/topups/{id}', [\Modules\NsSpecialCustomer\Http\Controllers\SpecialCustomerController::class, 'getWalletTopup'])
                        ->where('id', '[0-9]+')
                        ->middleware('ns.special-customer.permission:view');
                    Route::post('wallet/topup', [\Modules\NsSpecialCustomer\Http\Controllers\SpecialCustomerController::class, 'topUpAccount']);
                    Route::get('customers/{customerId}/balance', [\Modules\NsSpecialCustomer\Http\Controllers\SpecialCustomerController::class, 'getCustomerBalance'])
                        ->where('customerId', '[0-9]+')
                        ->middleware('ns.special-customer.balance-access');
                    
                    // Special customers list for mobile app - use existing getCustomersList method
                    Route::get('customers', [\Modules\NsSpecialCustomer\Http\Controllers\SpecialCustomerController::class, 'getCustomersList'])
                        ->middleware('ns.special-customer.permission:manage');
                    
                    // Dashboard stats for mobile app (includes total_due)
                    Route::get('stats', [\Modules\NsSpecialCustomer\Http\Controllers\SpecialCustomerController::class, 'getStats'])
                        ->middleware('ns.special-customer.permission:manage');
                });
        }
    });
