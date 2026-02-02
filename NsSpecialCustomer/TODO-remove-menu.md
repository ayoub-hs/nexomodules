# Task: Remove "Process Cashback" from Menu

## Analysis

### Information Gathered
1. The menu structure is defined in `NsSpecialCustomerServiceProvider.php` 
2. The "Process Cashback" menu item is located in the `ns-dashboard-menus` hook
3. The menu item should be removed since processing cashback is now accessed from the cashback history page

### Current Menu Structure (lines 76-84 in NsSpecialCustomerServiceProvider.php)
```php
$menus[] = [
    'label' => 'Special Customer',
    'icon' => 'la-star',
    'childrens' => [
        ['label' => 'Customer List', 'href' => url('/dashboard/special-customer/customers')],
        ['label' => 'Top-up Account', 'href' => url('/dashboard/special-customer/topup')],
        ['label' => 'Cashback History', 'href' => url('/dashboard/special-customer/cashback')],
        ['label' => 'Process Cashback', 'href' => url('/dashboard/special-customer/cashback/create')], // TO BE REMOVED
        ['label' => 'Statistics', 'href' => url('/dashboard/special-customer/statistics')],
        ['label' => 'Settings', 'href' => url('/dashboard/special-customer/settings')],
    ]
];
```

## Plan

### File to Edit
- `modules/NsSpecialCustomer/Providers/NsSpecialCustomerServiceProvider.php`

### Changes Required
1. Remove the menu item: `['label' => 'Process Cashback', 'href' => url('/dashboard/special-customer/cashback/create')],`

### Expected Result
The "Process Cashback" option will no longer appear in the Special Customer menu, and users will access cashback processing through the Cashback History page instead.

## Implementation Steps
1. ✅ Read and analyze the relevant files
2. ✅ Create the plan
3. ✅ Edit NsSpecialCustomerServiceProvider.php to remove the menu item
4. ✅ Task completed successfully

## Verification
- Removed the "Process Cashback" menu item from the dashboard menu
- The cashback processing is still accessible from the Cashback History page through the "Process Cashback" button/form within that page
- The menu now has 5 items instead of 6:
  - Customer List
  - Top-up Account
  - Cashback History
  - Statistics
  - Settings

## Additional Fix: POS Error and Special Customer Pricing
Fixed the error "Attempt to read property 'customer' on array" that occurred when selecting a special customer and clicking full payment in POS, and enabled wholesale pricing and special discounts.

**Files modified:**
1. `modules/NsSpecialCustomer/Providers/NsSpecialCustomerServiceProvider.php` - Fixed all POS hooks and added new hooks for order creation
2. `modules/NsSpecialCustomer/Listeners/OrderAfterCreatedListener.php` - Fixed to handle array/object cases
3. `modules/NsSpecialCustomer/Listeners/OrderAttributesListener.php` - Fixed to handle array/object cases
4. `modules/NsSpecialCustomer/Listeners/CustomerSelectedListener.php` - Fixed to handle array/object cases
5. `modules/NsSpecialCustomer/Listeners/ProductPriceListener.php` - Fixed to handle array/object cases
6. `modules/NsSpecialCustomer/Services/SpecialCustomerService.php` - Fixed core methods for array/object support

**Changes made:**
- Added `is_array()` checks before accessing object properties to handle both formats
- Updated the `isSpecialCustomer()` method to work with both array and object customer data
- Fixed `applyWholesalePricing()` to handle array products and apply wholesale pricing correctly
- Fixed `applySpecialDiscount()` to calculate discounts for special customers
- Fixed `validateDiscountEligibility()` to check eligibility for special discounts
- All POS hooks now properly detect special customers and apply wholesale pricing and discounts

**New hooks added:**
1. `ns-create-order-before` - Applies special customer discount (default 7%) during order creation
2. `ns-pos-product-price-before-save` - Applies wholesale pricing to individual products during cart operations

**Expected behavior after fix:**
1. When a special customer is selected in POS, the system recognizes them as special
2. Product prices are automatically set to wholesale price (if configured on products)
3. Special customer discount is applied to the order total (default 7%) when order is submitted
4. The discount and wholesale pricing are reflected in the order total
5. All changes are applied at order creation/submission time

