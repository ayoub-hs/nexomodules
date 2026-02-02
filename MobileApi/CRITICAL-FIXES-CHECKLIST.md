# MobileApi Module - Critical Fixes Checklist

**Priority:** 🔴 CRITICAL - Must complete before ANY production deployment  
**Estimated Time:** 8-12 hours for critical fixes only  
**Status:** ❌ NOT PRODUCTION READY

---

## ⚠️ STOP - Read This First

**DO NOT deploy this module to production until ALL items below are checked ✅**

This checklist contains ONLY the critical security and stability fixes that MUST be implemented before the module can be safely used in production. These are non-negotiable requirements.

---

## Critical Security Fixes (MUST DO)

### 1. Input Validation & Sanitization

- [ ] **Create ProductSearchRequest validation class**
  - File: `Http/Requests/ProductSearchRequest.php`
  - Validates: search term (min 2 chars), category_id, limit
  - Priority: 🔴 CRITICAL
  
- [ ] **Create SyncDeltaRequest validation class**
  - File: `Http/Requests/SyncDeltaRequest.php`
  - Validates: since parameter (required), limit
  - Priority: 🔴 CRITICAL
  
- [ ] **Create OrderIndexRequest validation class**
  - File: `Http/Requests/OrderIndexRequest.php`
  - Validates: cursor, limit, filters, direction
  - Priority: 🔴 CRITICAL
  
- [ ] **Create BatchOrderRequest validation class**
  - File: `Http/Requests/BatchOrderRequest.php`
  - Validates: orders array structure
  - Priority: 🔴 CRITICAL

- [ ] **Update MobileProductController to use ProductSearchRequest**
  ```php
  public function search(ProductSearchRequest $request)
  ```

- [ ] **Update MobileSyncController to use SyncDeltaRequest**
  ```php
  public function delta(SyncDeltaRequest $request)
  ```

- [ ] **Update MobileOrdersController to use requests**
  ```php
  public function index(OrderIndexRequest $request)
  public function batch(BatchOrderRequest $request, OrdersService $ordersService)
  ```

### 2. SQL Injection Prevention

- [ ] **Fix LIKE query in MobileProductController::search()**
  ```php
  // BEFORE (Line 30-32)
  ->where('name', 'LIKE', "%{$searchTerm}%")
  
  // AFTER
  ->where('name', 'LIKE', '%' . str_replace(['%', '_'], ['\%', '\_'], $searchTerm) . '%')
  ```

- [ ] **Fix LIKE query in MobileOrdersController::index()**
  ```php
  // BEFORE (Line 52-55)
  ->where('first_name', 'LIKE', "%{$customerFilter}%")
  
  // AFTER
  $escaped = str_replace(['%', '_'], ['\%', '\_'], $customerFilter);
  ->where('first_name', 'LIKE', "%{$escaped}%")
  ```

- [ ] **Verify all database queries use parameter binding**

### 3. Error Handling

- [ ] **Add try-catch to MobileSyncController::bootstrap()**
  ```php
  public function bootstrap(Request $request)
  {
      try {
          // existing code
      } catch (\Illuminate\Database\QueryException $e) {
          \Log::error('Mobile API Bootstrap DB Error', [
              'error' => $e->getMessage(),
              'user_id' => auth()->id(),
          ]);
          return response()->json([
              'error' => 'Database error occurred',
              'code' => 'DB_ERROR'
          ], 500);
      } catch (\Exception $e) {
          \Log::error('Mobile API Bootstrap Error', [
              'error' => $e->getMessage(),
              'user_id' => auth()->id(),
          ]);
          return response()->json([
              'error' => 'An error occurred',
              'code' => 'INTERNAL_ERROR'
          ], 500);
      }
  }
  ```

- [ ] **Add try-catch to MobileSyncController::delta()**
- [ ] **Add try-catch to MobileProductController::search()**
- [ ] **Add try-catch to MobileProductController::searchByBarcode()**
- [ ] **Add try-catch to MobileOrdersController::index()**
- [ ] **Add try-catch to MobileOrdersController::batch()**
- [ ] **Add try-catch to MobileCategoryController::products()**
- [ ] **Add try-catch to MobileRegisterConfigController::show()**

### 4. Rate Limiting

- [ ] **Create MobileApiRateLimit middleware**
  - File: `Http/Middleware/MobileApiRateLimit.php`
  - Implements per-user rate limiting
  - Returns 429 status when exceeded
  
- [ ] **Register middleware in MobileApiServiceProvider**
  ```php
  Route::aliasMiddleware('mobile.rate.limit', \Modules\MobileApi\Http\Middleware\MobileApiRateLimit::class);
  ```

- [ ] **Apply rate limiting to routes**
  ```php
  Route::get('sync/bootstrap', [...])
      ->middleware('mobile.rate.limit:10:1'); // 10 per minute
  
  Route::post('products/search', [...])
      ->middleware('mobile.rate.limit:60:1'); // 60 per minute
  
  Route::post('orders/batch', [...])
      ->middleware('mobile.rate.limit:20:1'); // 20 per minute
  ```

### 5. Database Transactions

- [ ] **Add transaction to MobileOrdersController::batch()**
  ```php
  foreach ($orders as $orderData) {
      DB::beginTransaction();
      try {
          // order creation logic
          DB::commit();
      } catch (\Exception $e) {
          DB::rollBack();
          // error handling
      }
  }
  ```

---

## Critical Database Fixes (MUST DO)

### 6. Migration Files

- [ ] **Create permissions migration**
  - File: `Database/Migrations/2024_02_01_000001_create_mobile_api_permissions.php`
  - Creates: mobile API permissions in nexopos_permissions table
  
- [ ] **Create API logs table migration**
  - File: `Database/Migrations/2024_02_01_000002_create_mobile_api_logs_table.php`
  - Creates: mobile_api_logs table for request logging
  
- [ ] **Create indexes migration**
  - File: `Database/Migrations/2024_02_01_000003_add_mobile_api_indexes.php`
  - Adds indexes for: products (name, barcode, sku), orders (updated_at, payment_status), customers (name, email)

- [ ] **Test migrations on fresh database**
  ```bash
  php artisan migrate:fresh
  php artisan module:migrate MobileApi
  ```

- [ ] **Test migration rollback**
  ```bash
  php artisan module:migrate-rollback MobileApi
  ```

---

## Critical Performance Fixes (MUST DO)

### 7. Query Optimization

- [ ] **Add pagination to MobileSyncController::bootstrap()**
  ```php
  // Instead of ->get(), use:
  ->paginate(100)
  ```

- [ ] **Add limit to MobileProductController::search()**
  ```php
  // Already has limit, verify it's enforced:
  $limit = min((int) $request->input('limit', 50), 100);
  ```

- [ ] **Verify eager loading in all queries**
  - Check all `->with()` calls are necessary
  - Remove unused eager loads

---

## Critical Authorization Fixes (MUST DO)

### 8. Permission Checks

- [ ] **Create CheckMobileApiPermission middleware**
  - File: `Http/Middleware/CheckMobileApiPermission.php`
  - Checks user has required permission
  
- [ ] **Apply permission middleware to sensitive routes**
  ```php
  Route::post('orders/batch', [...])
      ->middleware('mobile.permission:mobile.create.orders');
  ```

- [ ] **Create permission seeder**
  - File: `Database/Seeders/MobileApiPermissionsSeeder.php`
  - Seeds default permissions

---

## Verification Steps (MUST DO)

### 9. Testing

- [ ] **Test all endpoints with invalid input**
  - Verify validation errors are returned
  - Verify no SQL errors exposed
  
- [ ] **Test rate limiting**
  - Send requests exceeding limit
  - Verify 429 response
  
- [ ] **Test error handling**
  - Simulate database errors
  - Verify no stack traces in response
  
- [ ] **Test fresh installation**
  ```bash
  php artisan migrate:fresh
  php artisan module:migrate MobileApi
  php artisan db:seed --class=MobileApiPermissionsSeeder
  ```

- [ ] **Test with production-like data**
  - Test with 10,000+ products
  - Test with 1,000+ orders
  - Verify performance is acceptable

---

## Documentation (MUST DO)

### 10. Critical Documentation

- [ ] **Update README.md with security warnings**
  - Document rate limits
  - Document required permissions
  - Document error codes
  
- [ ] **Create SECURITY.md**
  - Document security considerations
  - Document authentication requirements
  - Document input validation

---

## Pre-Deployment Checklist

Before deploying to production, verify:

- [ ] ✅ All validation classes created and applied
- [ ] ✅ All SQL injection vulnerabilities fixed
- [ ] ✅ All error handling implemented
- [ ] ✅ Rate limiting applied to all endpoints
- [ ] ✅ Database transactions added to batch operations
- [ ] ✅ All migrations created and tested
- [ ] ✅ Database indexes created
- [ ] ✅ Permission middleware created and applied
- [ ] ✅ All endpoints tested with invalid input
- [ ] ✅ Fresh installation tested successfully
- [ ] ✅ Performance tested with production-like data
- [ ] ✅ Security documentation completed

---

## Estimated Time Breakdown

| Task | Time | Priority |
|------|------|----------|
| Input Validation (4 classes) | 2 hours | 🔴 Critical |
| SQL Injection Fixes | 1 hour | 🔴 Critical |
| Error Handling (8 methods) | 2 hours | 🔴 Critical |
| Rate Limiting | 1.5 hours | 🔴 Critical |
| Database Transactions | 0.5 hours | 🔴 Critical |
| Migrations (3 files) | 2 hours | 🔴 Critical |
| Permission Middleware | 1 hour | 🔴 Critical |
| Testing & Verification | 2 hours | 🔴 Critical |
| **TOTAL** | **12 hours** | |

---

## Quick Start Guide

To implement critical fixes in order:

1. **Start with Input Validation** (2 hours)
   - Create all FormRequest classes
   - Apply to controllers
   
2. **Fix SQL Injection** (1 hour)
   - Escape all LIKE queries
   - Verify parameter binding
   
3. **Add Error Handling** (2 hours)
   - Wrap all methods in try-catch
   - Add logging
   
4. **Implement Rate Limiting** (1.5 hours)
   - Create middleware
   - Apply to routes
   
5. **Add Database Safety** (2.5 hours)
   - Add transactions
   - Create migrations
   
6. **Add Authorization** (1 hour)
   - Create permission middleware
   - Apply to routes
   
7. **Test Everything** (2 hours)
   - Run all verification steps
   - Fix any issues found

---

## Success Criteria

The module is ready for production when:

✅ All checkboxes above are marked complete  
✅ All tests pass  
✅ Fresh installation works without errors  
✅ No SQL injection vulnerabilities remain  
✅ All endpoints have input validation  
✅ All endpoints have error handling  
✅ Rate limiting is active on all endpoints  
✅ Performance is acceptable with production data  

---

## Support

If you encounter issues during implementation:

1. Check the detailed `PRODUCTION-READY-PLAN.md` for implementation examples
2. Review the `AUDIT-REPORT.md` for context on each issue
3. Test each fix individually before moving to the next
4. Document any deviations from this checklist

---

**Remember: This module CANNOT be deployed to production until ALL critical fixes are complete.**
