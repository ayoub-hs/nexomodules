# NsManufacturing - Quick Fix Checklist

**Status:** ⚠️ NOT PRODUCTION READY  
**Priority:** URGENT - Fix before deployment

---

## 🔥 CRITICAL FIXES (Do These First!)

### 1. Fix Permission Migration Path (15 min)
**File:** `Providers/NsManufacturingServiceProvider.php` Line 157

```php
// WRONG ❌
$migrationPath = __DIR__ . '/../Database/Migrations/2026_01_31_000001_create_manufacturing_permissions.php';

// CORRECT ✅
$migrationPath = __DIR__ . '/../Migrations/2026_01_31_000001_create_manufacturing_permissions.php';
```

**Test:** Enable module and check if permissions are created

---

### 2. Add Permission Middleware to Routes (1 hour)
**File:** `Routes/web.php`

Add `->middleware('ns.restrict:permission.name')` to each route:

```php
// Example:
Route::get('boms', [ManufacturingController::class, 'boms'])
    ->name('ns.dashboard.manufacturing-boms')
    ->middleware('ns.restrict:nexopos.read.manufacturing-recipes'); // ADD THIS
```

**Routes to Fix:**
- [ ] `boms` - add `nexopos.read.manufacturing-recipes`
- [ ] `boms/create` - add `nexopos.create.manufacturing-recipes`
- [ ] `boms/edit/{id}` - add `nexopos.update.manufacturing-recipes`
- [ ] `boms/explode/{id}` - add `nexopos.read.manufacturing-recipes`
- [ ] `bom-items` - add `nexopos.read.manufacturing-recipes`
- [ ] `bom-items/create` - add `nexopos.create.manufacturing-recipes`
- [ ] `bom-items/edit/{id}` - add `nexopos.update.manufacturing-recipes`
- [ ] `orders` - add `nexopos.read.manufacturing-orders`
- [ ] `orders/create` - add `nexopos.create.manufacturing-orders`
- [ ] `orders/edit/{id}` - add `nexopos.update.manufacturing-orders`
- [ ] `reports` - add `nexopos.view.manufacturing-costs`
- [ ] All report endpoints - add `nexopos.view.manufacturing-costs`

**Test:** Try accessing routes without permission (should get 403)

---

### 3. Handle Empty API Routes (5 min OR 4 hours)

**Option A: Remove File (Quick Fix)**
1. Delete `Routes/api.php`
2. Remove from `Providers/NsManufacturingServiceProvider.php`:
   ```php
   $this->loadRoutesFrom(__DIR__ . '/../Routes/api.php'); // DELETE THIS LINE
   ```

**Option B: Implement API (Proper Fix)**
- See `PRODUCTION-READY-PLAN.md` Task 1.3 for full implementation

**Decision:** Choose option and implement

---

### 4. Create Validation Request Classes (3-4 hours)

**Files to Create:**

#### `Http/Requests/CreateBomRequest.php`
```php
<?php
namespace Modules\NsManufacturing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateBomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ns()->allowedTo('nexopos.create.manufacturing-recipes');
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'product_id' => 'required|exists:nexopos_products,id',
            'unit_id' => 'required|exists:nexopos_units,id',
            'quantity' => 'required|numeric|min:0.0001',
            'is_active' => 'sometimes|boolean',
            'description' => 'nullable|string|max:1000',
        ];
    }
}
```

#### `Http/Requests/UpdateBomRequest.php`
```php
<?php
namespace Modules\NsManufacturing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ns()->allowedTo('nexopos.update.manufacturing-recipes');
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|required|string|max:255',
            'product_id' => 'sometimes|required|exists:nexopos_products,id',
            'unit_id' => 'sometimes|required|exists:nexopos_units,id',
            'quantity' => 'sometimes|required|numeric|min:0.0001',
            'is_active' => 'sometimes|boolean',
            'description' => 'nullable|string|max:1000',
        ];
    }
}
```

#### `Http/Requests/CreateBomItemRequest.php`
```php
<?php
namespace Modules\NsManufacturing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\NsManufacturing\Services\BomService;

class CreateBomItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ns()->allowedTo('nexopos.create.manufacturing-recipes');
    }

    public function rules(): array
    {
        return [
            'bom_id' => 'required|exists:ns_manufacturing_boms,id',
            'product_id' => 'required|exists:nexopos_products,id',
            'unit_id' => 'required|exists:nexopos_units,id',
            'quantity' => 'required|numeric|min:0.0001',
            'waste_percent' => 'nullable|numeric|min:0|max:100',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $bomService = app(BomService::class);
            
            if (!$bomService->validateCircularDependency(
                $this->input('bom_id'),
                $this->input('product_id')
            )) {
                $validator->errors()->add(
                    'product_id',
                    __('Circular dependency detected.')
                );
            }
        });
    }
}
```

#### `Http/Requests/CreateProductionOrderRequest.php`
```php
<?php
namespace Modules\NsManufacturing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateProductionOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ns()->allowedTo('nexopos.create.manufacturing-orders');
    }

    public function rules(): array
    {
        return [
            'code' => 'nullable|string|max:255|unique:ns_manufacturing_orders,code',
            'bom_id' => 'required|exists:ns_manufacturing_boms,id',
            'product_id' => 'required|exists:nexopos_products,id',
            'unit_id' => 'required|exists:nexopos_units,id',
            'quantity' => 'required|numeric|min:0.0001',
            'status' => 'sometimes|in:draft,planned',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (empty($this->code)) {
            $this->merge([
                'code' => 'MO-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6))
            ]);
        }
    }
}
```

**Test:** Try creating BOM/Order with invalid data

---

### 5. Improve Error Handling (2-3 hours)

**File:** `Services/ProductionService.php`

Add try-catch blocks and better error messages:

```php
public function startOrder(ManufacturingOrder $order): void
{
    // Validate status
    if (!in_array($order->status, [ManufacturingOrder::STATUS_PLANNED, ManufacturingOrder::STATUS_DRAFT])) {
        throw new Exception(
            __("Order must be in Planned or Draft state. Current: :status", ['status' => $order->status])
        );
    }

    // Validate BOM
    $bom = $order->bom;
    if (!$bom) {
        throw new Exception(__("No BOM assigned to order #:code", ['code' => $order->code]));
    }

    // Check stock with detailed error
    $missing = [];
    foreach ($bom->items as $item) {
        $required = $item->quantity * $order->quantity;
        if (!$this->inventory->isAvailable($item->product_id, $item->unit_id, $required)) {
            $available = app(\App\Services\ProductService::class)
                ->getQuantity($item->product_id, $item->unit_id);
            $missing[] = sprintf(
                '%s: Required %s, Available %s',
                $item->product->name ?? 'Unknown',
                $required,
                $available
            );
        }
    }

    if (!empty($missing)) {
        throw new Exception(
            __("Insufficient stock:") . "\n" . implode("\n", $missing)
        );
    }

    // Use transaction with proper error handling
    try {
        DB::beginTransaction();
        
        // ... existing code ...
        
        DB::commit();
        
        Log::info("Order started", ['order_id' => $order->id]);
        
    } catch (\Exception $e) {
        DB::rollBack();
        Log::error("Failed to start order", [
            'order_id' => $order->id,
            'error' => $e->getMessage()
        ]);
        throw new Exception(__("Failed to start order: :msg", ['msg' => $e->getMessage()]));
    }
}
```

**Test:** Try starting order with insufficient stock

---

## ⚡ HIGH PRIORITY (Do Next)

### 6. Add Soft Deletes (1 hour)

**Create Migration:** `Migrations/2026_02_01_000001_add_soft_deletes.php`
```php
<?php
use App\Classes\Schema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ns_manufacturing_boms', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('ns_manufacturing_orders', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('ns_manufacturing_boms', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('ns_manufacturing_orders', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
```

**Update Models:**
```php
use Illuminate\Database\Eloquent\SoftDeletes;

class ManufacturingBom extends NsModel
{
    use SoftDeletes;
    // ...
}

class ManufacturingOrder extends NsModel
{
    use SoftDeletes;
    // ...
}
```

**Test:** Delete and restore a BOM

---

### 7. Enable Circular Dependency Validation (30 min)

**File:** `Crud/BomItemCrud.php`

Add to `filterPostInputs()`:
```php
public function filterPostInputs($inputs, $entry)
{
    $bomService = app(\Modules\NsManufacturing\Services\BomService::class);
    
    if (!$bomService->validateCircularDependency($inputs['bom_id'], $inputs['product_id'])) {
        throw new \Exception(__('Circular dependency detected.'));
    }
    
    return $inputs;
}
```

**Test:** Try creating circular BOM (A requires B, B requires A)

---

## 📝 TESTING CHECKLIST

After implementing fixes, test:

### Fresh Install Test
- [ ] Enable module successfully
- [ ] Permissions created in database
- [ ] Menu appears in dashboard
- [ ] No errors in logs

### Functionality Test
- [ ] Create BOM successfully
- [ ] Add BOM items successfully
- [ ] Create production order
- [ ] Start order (verify stock deduction)
- [ ] Complete order (verify stock addition)
- [ ] View reports

### Security Test
- [ ] Routes blocked without permission
- [ ] Validation rejects invalid data
- [ ] Circular dependency prevented
- [ ] No unauthorized access

### Error Handling Test
- [ ] Insufficient stock shows clear error
- [ ] Invalid status transitions prevented
- [ ] Database errors handled gracefully
- [ ] Logs contain useful information

---

## 📊 PROGRESS TRACKER

### Critical Fixes (Must Do)
- [ ] Fix permission migration path
- [ ] Add permission middleware to routes
- [ ] Handle empty API routes
- [ ] Create validation request classes
- [ ] Improve error handling

### High Priority (Should Do)
- [ ] Add soft deletes
- [ ] Enable circular dependency validation
- [ ] Add audit logging
- [ ] Implement stock availability check

### Testing
- [ ] Fresh install test passed
- [ ] Functionality test passed
- [ ] Security test passed
- [ ] Error handling test passed

---

## ✅ COMPLETION CRITERIA

Module is ready for production when:
- [x] All critical fixes completed
- [x] All high priority fixes completed
- [x] All tests passing
- [x] Fresh install works
- [x] No security vulnerabilities
- [x] Documentation updated

**Current Status:** ☐ Ready ☑ Not Ready

---

## 🆘 NEED HELP?

**Reference Documents:**
- `AUDIT-TODO.md` - Detailed issue list
- `PRODUCTION-READY-PLAN.md` - Complete implementation guide
- `AUDIT-SUMMARY.md` - Executive summary

**Estimated Time:**
- Critical fixes: 8-12 hours
- High priority: 4-6 hours
- Testing: 2-3 hours
- **Total: 14-21 hours**

---

**Last Updated:** 2024  
**Next Review:** After critical fixes
