# MobileApi Module - Production Ready Implementation Plan

**Module:** MobileApi  
**Version:** 1.0.0 → 2.0.0 (Production Ready)  
**Plan Date:** 2024-02-01  
**Estimated Effort:** 40-60 hours  
**Priority:** HIGH

---

## Overview

This document outlines the step-by-step plan to make the MobileApi module production-ready and safe for fresh installations. All critical security, validation, and database issues must be resolved.

---

## Phase 1: Critical Security Fixes (Priority: CRITICAL)

**Estimated Time:** 16-20 hours  
**Must Complete Before:** Any production deployment

### 1.1 Create FormRequest Validation Classes

**Files to Create:**

```
Http/Requests/
├── ProductSearchRequest.php
├── SyncBootstrapRequest.php
├── SyncDeltaRequest.php
├── OrderIndexRequest.php
├── OrderSyncRequest.php
├── BatchOrderRequest.php
└── CategoryProductsRequest.php
```

**Implementation:**

```php
// Http/Requests/ProductSearchRequest.php
<?php

namespace Modules\MobileApi\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProductSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Handled by auth:sanctum middleware
    }

    public function rules(): array
    {
        return [
            'search' => ['required', 'string', 'min:2', 'max:255'],
            'arguments.category_id' => ['nullable', 'integer', 'exists:nexopos_products_categories,id'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'search.required' => 'Search term is required',
            'search.min' => 'Search term must be at least 2 characters',
            'arguments.category_id.exists' => 'Invalid category ID',
        ];
    }
}
```

**Tasks:**
- [ ] Create ProductSearchRequest with validation rules
- [ ] Create SyncBootstrapRequest (no params but for consistency)
- [ ] Create SyncDeltaRequest with 'since' and 'limit' validation
- [ ] Create OrderIndexRequest with cursor, limit, filters validation
- [ ] Create OrderSyncRequest with 'since' validation
- [ ] Create BatchOrderRequest with orders array validation
- [ ] Create CategoryProductsRequest with ID validation
- [ ] Update all controllers to use FormRequests

### 1.2 Implement Comprehensive Error Handling

**Files to Modify:**
- All controller files

**Implementation Pattern:**

```php
public function bootstrap(Request $request)
{
    try {
        $startTime = microtime(true);
        
        // Existing logic wrapped in try-catch
        
        return response()->json([...]);
        
    } catch (\Illuminate\Database\QueryException $e) {
        \Log::error('MobileApi Bootstrap Query Error', [
            'error' => $e->getMessage(),
            'user_id' => auth()->id(),
        ]);
        
        return response()->json([
            'error' => 'Database error occurred. Please try again.',
            'code' => 'DB_ERROR',
        ], 500);
        
    } catch (\Exception $e) {
        \Log::error('MobileApi Bootstrap Error', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
            'user_id' => auth()->id(),
        ]);
        
        return response()->json([
            'error' => 'An unexpected error occurred. Please try again.',
            'code' => 'INTERNAL_ERROR',
        ], 500);
    }
}
```

**Tasks:**
- [ ] Add try-catch blocks to all controller methods
- [ ] Implement consistent error response format
- [ ] Add error logging with context
- [ ] Create custom exception classes for API errors
- [ ] Add error code constants
- [ ] Sanitize error messages (no stack traces in production)

### 1.3 Fix SQL Injection Vulnerabilities

**Files to Modify:**
- `Http/Controllers/MobileProductController.php`
- `Http/Controllers/MobileOrdersController.php`

**Implementation:**

```php
// BEFORE (Vulnerable)
->where('name', 'LIKE', "%{$searchTerm}%")

// AFTER (Safe)
->where('name', 'LIKE', '%' . str_replace(['%', '_'], ['\%', '\_'], $searchTerm) . '%')

// OR use whereRaw with bindings
->whereRaw('name LIKE ?', ['%' . addcslashes($searchTerm, '%_') . '%'])
```

**Tasks:**
- [ ] Escape all LIKE query parameters
- [ ] Use parameter binding for all user inputs
- [ ] Add input sanitization helper methods
- [ ] Review all raw queries
- [ ] Add SQL injection tests

### 1.4 Implement Rate Limiting

**Files to Create:**
- `Http/Middleware/MobileApiRateLimit.php`

**Files to Modify:**
- `Routes/api.php`
- `Providers/MobileApiServiceProvider.php`

**Implementation:**

```php
// Http/Middleware/MobileApiRateLimit.php
<?php

namespace Modules\MobileApi\Http\Middleware;

use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;

class MobileApiRateLimit
{
    protected $limiter;

    public function __construct(RateLimiter $limiter)
    {
        $this->limiter = $limiter;
    }

    public function handle(Request $request, Closure $next, string $limit = '60:1')
    {
        [$maxAttempts, $decayMinutes] = explode(':', $limit);
        
        $key = 'mobile_api:' . $request->user()->id . ':' . $request->path();
        
        if ($this->limiter->tooManyAttempts($key, $maxAttempts)) {
            return response()->json([
                'error' => 'Too many requests. Please try again later.',
                'code' => 'RATE_LIMIT_EXCEEDED',
                'retry_after' => $this->limiter->availableIn($key),
            ], 429);
        }
        
        $this->limiter->hit($key, $decayMinutes * 60);
        
        $response = $next($request);
        
        return $response->header('X-RateLimit-Limit', $maxAttempts)
                        ->header('X-RateLimit-Remaining', $this->limiter->remaining($key, $maxAttempts));
    }
}
```

**Routes Configuration:**

```php
// Different limits for different endpoints
Route::get('sync/bootstrap', [MobileSyncController::class, 'bootstrap'])
    ->middleware('mobile.rate.limit:10:1'); // 10 per minute

Route::post('products/search', [MobileProductController::class, 'search'])
    ->middleware('mobile.rate.limit:60:1'); // 60 per minute

Route::post('orders/batch', [MobileOrdersController::class, 'batch'])
    ->middleware('mobile.rate.limit:20:1'); // 20 per minute
```

**Tasks:**
- [ ] Create rate limiting middleware
- [ ] Configure different limits per endpoint
- [ ] Add rate limit headers to responses
- [ ] Implement Redis-based rate limiting for production
- [ ] Add rate limit bypass for admin users
- [ ] Add rate limit monitoring

### 1.5 Add Permission-Based Authorization

**Files to Create:**
- `Http/Middleware/CheckMobileApiPermission.php`
- `Database/Migrations/2024_02_01_000001_create_mobile_api_permissions.php`

**Implementation:**

```php
// Http/Middleware/CheckMobileApiPermission.php
<?php

namespace Modules\MobileApi\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckMobileApiPermission
{
    public function handle(Request $request, Closure $next, string $permission)
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'error' => 'Unauthenticated',
                'code' => 'UNAUTHENTICATED',
            ], 401);
        }
        
        if (!$user->can($permission)) {
            return response()->json([
                'error' => 'You do not have permission to access this resource',
                'code' => 'FORBIDDEN',
            ], 403);
        }
        
        return $next($request);
    }
}
```

**Tasks:**
- [ ] Create permission middleware
- [ ] Create migration for mobile API permissions
- [ ] Add permission seeder
- [ ] Apply permissions to routes
- [ ] Add permission documentation
- [ ] Create admin UI for permission management

---

## Phase 2: Database & Migration Fixes (Priority: CRITICAL)

**Estimated Time:** 8-12 hours  
**Must Complete Before:** Fresh installations

### 2.1 Create Proper Migration Files

**Files to Create:**

```
Database/Migrations/
├── 2024_02_01_000001_create_mobile_api_permissions.php
├── 2024_02_01_000002_create_mobile_sync_tokens_table.php
├── 2024_02_01_000003_create_mobile_api_logs_table.php
└── 2024_02_01_000004_add_mobile_api_indexes.php
```

**Implementation:**

```php
// 2024_02_01_000002_create_mobile_sync_tokens_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('mobile_sync_tokens', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('token', 255)->unique();
            $table->timestamp('last_sync_at')->nullable();
            $table->json('sync_metadata')->nullable();
            $table->timestamps();
            
            $table->foreign('user_id')
                  ->references('id')
                  ->on('nexopos_users')
                  ->onDelete('cascade');
                  
            $table->index(['user_id', 'last_sync_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('mobile_sync_tokens');
    }
};
```

```php
// 2024_02_01_000003_create_mobile_api_logs_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('mobile_api_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('endpoint', 255);
            $table->string('method', 10);
            $table->integer('status_code');
            $table->integer('response_time_ms')->nullable();
            $table->text('error_message')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'created_at']);
            $table->index(['endpoint', 'created_at']);
            $table->index('status_code');
        });
    }

    public function down()
    {
        Schema::dropIfExists('mobile_api_logs');
    }
};
```

```php
// 2024_02_01_000004_add_mobile_api_indexes.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Add indexes for common mobile API queries
        Schema::table('nexopos_products', function (Blueprint $table) {
            if (!$this->hasIndex('nexopos_products', 'idx_mobile_search')) {
                $table->index(['name', 'barcode', 'sku'], 'idx_mobile_search');
            }
        });
        
        Schema::table('nexopos_orders', function (Blueprint $table) {
            if (!$this->hasIndex('nexopos_orders', 'idx_mobile_sync')) {
                $table->index(['updated_at', 'payment_status'], 'idx_mobile_sync');
            }
        });
        
        Schema::table('nexopos_customers', function (Blueprint $table) {
            if (!$this->hasIndex('nexopos_customers', 'idx_mobile_search')) {
                $table->index(['first_name', 'last_name', 'email'], 'idx_mobile_search');
            }
        });
    }

    public function down()
    {
        Schema::table('nexopos_products', function (Blueprint $table) {
            $table->dropIndex('idx_mobile_search');
        });
        
        Schema::table('nexopos_orders', function (Blueprint $table) {
            $table->dropIndex('idx_mobile_sync');
        });
        
        Schema::table('nexopos_customers', function (Blueprint $table) {
            $table->dropIndex('idx_mobile_search');
        });
    }
    
    private function hasIndex($table, $index)
    {
        $indexes = Schema::getConnection()
            ->getDoctrineSchemaManager()
            ->listTableIndexes($table);
        return array_key_exists($index, $indexes);
    }
};
```

**Tasks:**
- [ ] Create permissions migration
- [ ] Create sync tokens table migration
- [ ] Create API logs table migration
- [ ] Create indexes migration
- [ ] Test migrations on fresh database
- [ ] Test rollback functionality
- [ ] Add migration documentation

### 2.2 Add Database Transactions

**Files to Modify:**
- `Http/Controllers/MobileOrdersController.php`

**Implementation:**

```php
public function batch(Request $request, OrdersService $ordersService)
{
    $orders = $request->input('orders', []);
    $results = [];
    $successCount = 0;
    $failureCount = 0;

    foreach ($orders as $orderData) {
        $clientReference = $orderData['client_reference'] ?? null;
        
        DB::beginTransaction();
        
        try {
            // Check for duplicate
            if ($clientReference) {
                $existing = Order::where('code', $clientReference)->first();
                if ($existing) {
                    $results[] = [
                        'client_reference' => $clientReference,
                        'success' => true,
                        'order' => $this->transformOrderSummary($existing),
                        'error' => null,
                        'duplicate' => true,
                    ];
                    $successCount++;
                    DB::commit();
                    continue;
                }
            }

            // Create the order
            $result = $ordersService->create($orderData);
            $order = $result['data']['order'];

            $results[] = [
                'client_reference' => $clientReference,
                'success' => true,
                'order' => $this->transformOrderSummary($order),
                'error' => null,
                'duplicate' => false,
            ];
            $successCount++;
            
            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            
            \Log::error('Batch order creation failed', [
                'client_reference' => $clientReference,
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);
            
            $results[] = [
                'client_reference' => $clientReference,
                'success' => false,
                'order' => null,
                'error' => 'Failed to create order: ' . $e->getMessage(),
                'duplicate' => false,
            ];
            $failureCount++;
        }
    }

    return response()->json([
        'results' => $results,
        'success_count' => $successCount,
        'failure_count' => $failureCount,
    ]);
}
```

**Tasks:**
- [ ] Add transactions to batch operations
- [ ] Add proper rollback handling
- [ ] Test transaction isolation
- [ ] Add deadlock detection and retry logic
- [ ] Document transaction behavior

---

## Phase 3: Code Quality & Architecture (Priority: HIGH)

**Estimated Time:** 12-16 hours

### 3.1 Extract Shared Logic to Services

**Files to Create:**

```
Services/
├── MobileProductService.php
├── MobileSyncService.php
├── MobileOrderService.php
└── MobileTransformerService.php
```

**Implementation:**

```php
// Services/MobileTransformerService.php
<?php

namespace Modules\MobileApi\Services;

use App\Models\Product;
use App\Models\Customer;
use App\Models\Order;

class MobileTransformerService
{
    public function transformProduct(Product $product): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'barcode' => $product->barcode,
            'barcode_type' => $product->barcode_type,
            'sku' => $product->sku,
            'status' => $product->status,
            'category_id' => $product->category_id,
            'unit_quantities' => $product->unit_quantities->map(fn($uq) => [
                'id' => $uq->id,
                'unit_id' => $uq->unit_id,
                'barcode' => $uq->barcode,
                'sale_price' => (float) $uq->sale_price,
                'wholesale_price' => (float) $uq->wholesale_price,
                'wholesale_price_edit' => (float) $uq->wholesale_price_edit,
                'unit' => $uq->unit ? [
                    'id' => $uq->unit->id,
                    'name' => $uq->unit->name,
                    'identifier' => $uq->unit->identifier,
                ] : null,
            ])->toArray(),
            'updated_at' => $product->updated_at?->format('Y-m-d H:i:s'),
            'deleted_at' => null,
        ];
    }

    public function transformCustomer(Customer $customer): array
    {
        return [
            'id' => $customer->id,
            'username' => $customer->username,
            'name' => trim($customer->first_name . ' ' . $customer->last_name),
            'first_name' => $customer->first_name,
            'last_name' => $customer->last_name,
            'email' => $customer->email,
            'phone' => $customer->phone,
            'group' => $customer->group ? [
                'id' => $customer->group->id,
                'name' => $customer->group->name,
            ] : null,
            'is_default' => false,
        ];
    }

    public function transformOrder(Order $order, bool $includePayments = false): array
    {
        // Implementation from controller
    }
}
```

**Tasks:**
- [ ] Create MobileTransformerService
- [ ] Create MobileProductService
- [ ] Create MobileSyncService
- [ ] Create MobileOrderService
- [ ] Update controllers to use services
- [ ] Add service provider bindings
- [ ] Add service tests

### 3.2 Implement Repository Pattern (Optional but Recommended)

**Files to Create:**

```
Repositories/
├── MobileProductRepository.php
├── MobileOrderRepository.php
└── Contracts/
    ├── MobileProductRepositoryInterface.php
    └── MobileOrderRepositoryInterface.php
```

**Tasks:**
- [ ] Create repository interfaces
- [ ] Implement repositories
- [ ] Bind interfaces in service provider
- [ ] Update services to use repositories
- [ ] Add repository tests

### 3.3 Add Comprehensive Type Hints

**Files to Modify:**
- All controller and service files

**Tasks:**
- [ ] Add return type hints to all methods
- [ ] Add parameter type hints
- [ ] Add property type hints
- [ ] Enable strict types
- [ ] Run static analysis (PHPStan/Psalm)

---

## Phase 4: Performance Optimization (Priority: HIGH)

**Estimated Time:** 8-12 hours

### 4.1 Implement Query Optimization

**Files to Modify:**
- `Http/Controllers/MobileSyncController.php`
- `Http/Controllers/MobileProductController.php`

**Implementation:**

```php
// Add pagination to bootstrap sync
public function bootstrap(Request $request)
{
    $page = $request->input('page', 1);
    $perPage = min($request->input('per_page', 100), 500);
    
    // Paginate products instead of loading all
    $products = Product::with(['unit_quantities.unit'])
        ->onSale()
        ->excludeVariations()
        ->select([...])
        ->paginate($perPage);
    
    return response()->json([
        'products' => $products->items(),
        'pagination' => [
            'current_page' => $products->currentPage(),
            'total_pages' => $products->lastPage(),
            'per_page' => $products->perPage(),
            'total' => $products->total(),
            'has_more' => $products->hasMorePages(),
        ],
        // ... other data
    ]);
}
```

**Tasks:**
- [ ] Add pagination to bootstrap sync
- [ ] Optimize N+1 queries with eager loading
- [ ] Add select() to limit columns
- [ ] Add chunk() for large datasets
- [ ] Implement query result caching
- [ ] Add database query monitoring

### 4.2 Implement Response Caching

**Files to Create:**
- `Http/Middleware/CacheMobileApiResponse.php`

**Implementation:**

```php
// Http/Middleware/CacheMobileApiResponse.php
<?php

namespace Modules\MobileApi\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CacheMobileApiResponse
{
    public function handle(Request $request, Closure $next, int $ttl = 300)
    {
        // Only cache GET requests
        if ($request->method() !== 'GET') {
            return $next($request);
        }
        
        $key = 'mobile_api:' . md5($request->fullUrl() . ':' . $request->user()->id);
        
        return Cache::remember($key, $ttl, function () use ($next, $request) {
            return $next($request);
        });
    }
}
```

**Tasks:**
- [ ] Create caching middleware
- [ ] Apply to appropriate endpoints
- [ ] Implement cache invalidation
- [ ] Add cache warming
- [ ] Configure Redis for production
- [ ] Add cache monitoring

---

## Phase 5: Testing & Documentation (Priority: MEDIUM)

**Estimated Time:** 12-16 hours

### 5.1 Create Comprehensive Tests

**Files to Create:**

```
Tests/
├── Feature/
│   ├── MobileSyncTest.php
│   ├── MobileProductTest.php
│   ├── MobileOrderTest.php
│   └── MobileCategoryTest.php
└── Unit/
    ├── MobileTransformerServiceTest.php
    ├── MobileProductServiceTest.php
    └── ValidationTest.php
```

**Implementation:**

```php
// Tests/Feature/MobileSyncTest.php
<?php

namespace Modules\MobileApi\Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

class MobileSyncTest extends TestCase
{
    public function test_bootstrap_requires_authentication()
    {
        $response = $this->getJson('/api/mobile/sync/bootstrap');
        
        $response->assertStatus(401);
    }
    
    public function test_bootstrap_returns_valid_data()
    {
        Sanctum::actingAs(User::factory()->create());
        
        $response = $this->getJson('/api/mobile/sync/bootstrap');
        
        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'categories',
                     'products',
                     'customers',
                     'payment_methods',
                     'order_types',
                     'sync_token',
                     'server_time',
                     'meta',
                 ]);
    }
    
    public function test_delta_sync_requires_since_parameter()
    {
        Sanctum::actingAs(User::factory()->create());
        
        $response = $this->getJson('/api/mobile/sync/delta');
        
        $response->assertStatus(400)
                 ->assertJson(['error' => 'The "since" parameter is required. Use bootstrap sync for initial data.']);
    }
    
    // More tests...
}
```

**Tasks:**
- [ ] Create feature tests for all endpoints
- [ ] Create unit tests for services
- [ ] Create validation tests
- [ ] Create security tests
- [ ] Create performance tests
- [ ] Achieve 80%+ code coverage
- [ ] Add CI/CD integration

### 5.2 Create API Documentation

**Files to Create:**
- `docs/API.md`
- `docs/AUTHENTICATION.md`
- `docs/ERROR_CODES.md`
- `openapi.yaml` (OpenAPI 3.0 specification)

**Tasks:**
- [ ] Document all endpoints
- [ ] Add request/response examples
- [ ] Document error codes
- [ ] Create authentication guide
- [ ] Add rate limiting documentation
- [ ] Create integration guide
- [ ] Generate Postman collection

---

## Phase 6: Monitoring & Logging (Priority: MEDIUM)

**Estimated Time:** 6-8 hours

### 6.1 Implement Request Logging

**Files to Create:**
- `Http/Middleware/LogMobileApiRequest.php`
- `Services/MobileApiLogger.php`

**Implementation:**

```php
// Http/Middleware/LogMobileApiRequest.php
<?php

namespace Modules\MobileApi\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\MobileApi\Services\MobileApiLogger;

class LogMobileApiRequest
{
    protected $logger;

    public function __construct(MobileApiLogger $logger)
    {
        $this->logger = $logger;
    }

    public function handle(Request $request, Closure $next)
    {
        $startTime = microtime(true);
        
        $response = $next($request);
        
        $responseTime = round((microtime(true) - $startTime) * 1000);
        
        $this->logger->log([
            'user_id' => $request->user()?->id,
            'endpoint' => $request->path(),
            'method' => $request->method(),
            'status_code' => $response->status(),
            'response_time_ms' => $responseTime,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
        
        return $response;
    }
}
```

**Tasks:**
- [ ] Create logging middleware
- [ ] Create logger service
- [ ] Log all API requests
- [ ] Add performance metrics
- [ ] Add error tracking
- [ ] Integrate with monitoring tools (Sentry, etc.)

---

## Phase 7: Configuration & Deployment (Priority: MEDIUM)

**Estimated Time:** 4-6 hours

### 7.1 Create Configuration File

**Files to Create:**
- `Config/mobile-api.php`

**Implementation:**

```php
// Config/mobile-api.php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Mobile API Configuration
    |--------------------------------------------------------------------------
    */

    'rate_limits' => [
        'bootstrap' => env('MOBILE_API_RATE_BOOTSTRAP', '10:1'),
        'delta' => env('MOBILE_API_RATE_DELTA', '30:1'),
        'search' => env('MOBILE_API_RATE_SEARCH', '60:1'),
        'orders' => env('MOBILE_API_RATE_ORDERS', '60:1'),
        'batch' => env('MOBILE_API_RATE_BATCH', '20:1'),
    ],

    'pagination' => [
        'default_per_page' => env('MOBILE_API_DEFAULT_PER_PAGE', 50),
        'max_per_page' => env('MOBILE_API_MAX_PER_PAGE', 500),
    ],

    'cache' => [
        'enabled' => env('MOBILE_API_CACHE_ENABLED', true),
        'ttl' => env('MOBILE_API_CACHE_TTL', 300), // 5 minutes
    ],

    'logging' => [
        'enabled' => env('MOBILE_API_LOGGING_ENABLED', true),
        'channel' => env('MOBILE_API_LOG_CHANNEL', 'daily'),
    ],

    'features' => [
        'sync_enabled' => env('MOBILE_API_SYNC_ENABLED', true),
        'batch_orders_enabled' => env('MOBILE_API_BATCH_ORDERS_ENABLED', true),
    ],
];
```

**Tasks:**
- [ ] Create configuration file
- [ ] Add environment variables
- [ ] Document configuration options
- [ ] Add config caching support
- [ ] Create config validation

### 7.2 Update Service Provider

**Files to Modify:**
- `Providers/MobileApiServiceProvider.php`

**Implementation:**

```php
<?php

namespace Modules\MobileApi\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use Modules\MobileApi\Services\MobileTransformerService;
use Modules\MobileApi\Services\MobileProductService;
use Modules\MobileApi\Services\MobileSyncService;
use Modules\MobileApi\Services\MobileApiLogger;

class MobileApiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register config
        $this->mergeConfigFrom(
            __DIR__.'/../Config/mobile-api.php', 'mobile-api'
        );

        // Register services
        $this->app->singleton(MobileTransformerService::class);
        $this->app->singleton(MobileProductService::class);
        $this->app->singleton(MobileSyncService::class);
        $this->app->singleton(MobileApiLogger::class);
    }

    public function boot(): void
    {
        // Load routes
        $this->loadRoutesFrom(__DIR__.'/../Routes/api.php');
        
        // Load migrations
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        
        // Publish config
        $this->publishes([
            __DIR__.'/../Config/mobile-api.php' => config_path('mobile-api.php'),
        ], 'mobile-api-config');
        
        // Register middleware
        Route::aliasMiddleware('mobile.rate.limit', \Modules\MobileApi\Http\Middleware\MobileApiRateLimit::class);
        Route::aliasMiddleware('mobile.permission', \Modules\MobileApi\Http\Middleware\CheckMobileApiPermission::class);
        Route::aliasMiddleware('mobile.log', \Modules\MobileApi\Http\Middleware\LogMobileApiRequest::class);
        Route::aliasMiddleware('mobile.cache', \Modules\MobileApi\Http\Middleware\CacheMobileApiResponse::class);
    }
}
```

**Tasks:**
- [ ] Update service provider
- [ ] Register all services
- [ ] Register middleware
- [ ] Publish configuration
- [ ] Add event listeners
- [ ] Add command registration
