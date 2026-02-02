# Special Customer POS Integration Flow

This document describes the exact flow implemented for customer selection, wholesale pricing, and special discount in the POS system.

## Overview

The special customer module integrates with the POS system through Laravel hooks and events to:
1. Detect when a special customer is selected
2. Apply wholesale pricing to products
3. Apply special discounts to orders

## Flow Diagram

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                           POS Customer Selection                             │
└─────────────────────────────────────────────────────────────────────────────┘
                                      │
                                      ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│  Hook: ns-pos-customer-selected                                               │
│  File: NsSpecialCustomerServiceProvider.php                                  │
│  Method: registerPOSHooks()                                                  │
└─────────────────────────────────────────────────────────────────────────────┘
                                      │
                                      ▼
    ┌──────────────────────────────────────────────────────────────────┐
    │  Step 1: Detect Special Customer                                  │
    │  - Get customer from POS selection                                │
    │  - Call isSpecialCustomer(customer) method                        │
    │  - Method checks if customer.group_id == special_group_id         │
    │  - Uses caching for performance                                   │
    └──────────────────────────────────────────────────────────────────┘
                                      │
                    ┌─────────────────┴─────────────────┐
                    │                                   │
                    ▼                                   ▼
        ┌─────────────────────┐             ┌─────────────────────┐
        │ Is Special Customer │             │ Not Special Customer│
        └─────────────────────┘             └─────────────────────┘
                    │                                   │
                    ▼                                   │
    ┌──────────────────────────────────────────────────────────────┐
    │  Step 2: Mark Customer as Special                             │
    │  - Set customerData['is_special'] = true                      │
    │  - Set customerData['special_badge'] = 'Special Customer'     │
    │  - Get wallet balance                                         │
    │  - Get discount and cashback eligibility                      │
    │  - Trigger frontend event for UI updates                      │
    └──────────────────────────────────────────────────────────────┘
                    │
                    ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                           Product Pricing Flow                                │
└─────────────────────────────────────────────────────────────────────────────┘
                                      │
                                      ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│  Hook: ns-pos-product-price                                                  │
│  File: NsSpecialCustomerServiceProvider.php                                  │
│  Method: registerPOSHooks()                                                  │
└─────────────────────────────────────────────────────────────────────────────┘
                                      │
                                      ▼
    ┌──────────────────────────────────────────────────────────────────┐
    │  Step 3: Apply Wholesale Pricing (when product is added)         │
    │  - Check if customer is special                                  │
    │  - Call applyWholesalePricing(product, customer)                 │
    │  - Check if product has wholesale_price                          │
    │  - Return wholesale price if available                           │
    └──────────────────────────────────────────────────────────────────┘
                                      │
                                      ▼
    ┌──────────────────────────────────────────────────────────────────┐
    │  applyWholesalePricing() Method Logic                            │
    │  File: SpecialCustomerService.php                                │
    │                                                                  │
    │  if !isSpecialCustomer(customer)                                 │
    │      return [                                                    │
    │          'original_price' => sale_price,                         │
    │          'special_price' => sale_price,                          │
    │          'wholesale_applied' => false,                           │
    │          'savings' => 0,                                         │
    │      ]                                                           │
    │                                                                  │
    │  originalPrice = product.sale_price                              │
    │  wholesalePrice = product.wholesale_price                        │
    │  specialPrice = wholesalePrice > 0 ? wholesalePrice : originalPrice│
    │                                                                  │
    │  return [                                                        │
    │      'original_price' => originalPrice,                          │
    │      'special_price' => specialPrice,                            │
    │      'wholesale_applied' => specialPrice < originalPrice,        │
    │      'savings' => max(0, originalPrice - specialPrice),          │
    │  ]                                                               │
    └──────────────────────────────────────────────────────────────────┘
                    │
                    ▼
        ┌───────────────────────────────┐
        │ Wholesale Price Applied?      │
        └───────────────────────────────┘
                    │
        ┌───────────┴───────────┐
        │                       │
        ▼                       ▼
    ┌──────────┐           ┌──────────┐
    │   YES    │           │    NO    │
    └──────────┘           └──────────┘
        │                       │
        ▼                       ▼
┌─────────────────┐    ┌─────────────────┐
│ Update POS UI   │    │ Use regular     │
│ with wholesale  │    │ sale price      │
│ price display   │    │                 │
└─────────────────┘    └─────────────────┘
                    │
                    ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                           Order Submission Flow                               │
└─────────────────────────────────────────────────────────────────────────────┘
                                      │
                                      ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│  Hook: ns-order-attributes                                                   │
│  File: NsSpecialCustomerServiceProvider.php                                  │
│  Method: registerUIComponents()                                              │
└─────────────────────────────────────────────────────────────────────────────┘
                                      │
                                      ▼
    ┌──────────────────────────────────────────────────────────────────┐
    │  Step 4: Set Discount Fields on Order Creation                    │
    │  - Check if customer is special                                  │
    │  - Set order['discount_type'] = 'percentage'                     │
    │  - Set order['discount_percentage'] = config['discountPercentage']│
    │  - Set order['special_customer_data'] with metadata              │
    └──────────────────────────────────────────────────────────────────┘
                                      │
                                      ▼
    ┌──────────────────────────────────────────────────────────────────┐
    │  Hook: ns-order-after-check-performed (Event Listener)            │
    │  File: NsSpecialCustomerServiceProvider.php                       │
    │  Method: registerEventListeners()                                 │
    └──────────────────────────────────────────────────────────────────┘
                                      │
                                      ▼
    ┌──────────────────────────────────────────────────────────────────┐
    │  Step 5: Apply Special Discount Calculation                       │
    │  - Check if customer is special                                  │
    │  - Check if discount already applied                             │
    │  - Calculate: discount = subtotal * (discountPercentage / 100)   │
    │  - Set order['discount'] = calculated_amount                     │
    │  - Set order['discount_type'] = 'percentage'                     │
    │  - Recalculate total: total = subtotal - discount + tax + shipping│
    └──────────────────────────────────────────────────────────────────┘
                                      │
                                      ▼
    ┌──────────────────────────────────────────────────────────────────┐
    │  applySpecialDiscount() Method Logic                             │
    │  File: SpecialCustomerService.php                                │
    │                                                                  │
    │  validateDiscountEligibility(customer, order)                    │
    │      - Check isSpecialCustomer(customer)                         │
    │      - Check discountPercentage > 0                              │
    │      - Check not already applied (if not stackable)              │
    │      - Check minimum order amount                                │
    │                                                                  │
    │  If eligible:                                                    │
    │      discountAmount = orderTotal * (discountPercentage / 100)   │
    │      return [                                                    │
    │          'discount_applied' => true,                             │
    │          'discount_amount' => discountAmount,                    │
    │          'discount_percentage' => discountPercentage,            │
    │          'new_total' => orderTotal - discountAmount,             │
    │          'stackable' => config['applyDiscountStackable'],        │
    │      ]                                                           │
    └──────────────────────────────────────────────────────────────────┘
                                      │
                                      ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                           Order Saved to Database                            │
└─────────────────────────────────────────────────────────────────────────────┘
                                      │
                                      ▼
    ┌──────────────────────────────────────────────────────────────────┐
    │  Order fields saved:                                              │
    │  - discount_type = 'percentage'                                   │
    │  - discount_percentage = 7.0 (default)                            │
    │  - discount = calculated_amount                                   │
    │  - special_customer_data = [                                      │
    │      'is_special' => true,                                        │
    │      'group_id' => special_group_id,                              │
    │      'discount_percentage' => 7.0,                                │
    │      'cashback_percentage' => 2.0,                                │
    │      'order_year' => current_year,                                │
    │  ]                                                                │
    └──────────────────────────────────────────────────────────────────┘

## Key Configuration

Default configuration values (stored in database options):

```php
[
    'ns_special_customer_group_id' => null,      // Customer group ID for special customers
    'ns_special_discount_percentage' => 7.0,     // 7% discount for special customers
    'ns_special_cashback_percentage' => 2.0,     // 2% cashback for special customers
    'ns_special_apply_discount_stackable' => false, // Discount is not stackable with others
    'ns_special_min_topup_amount' => 1,          // Minimum top-up amount
    'ns_special_enable_auto_cashback' => false,  // Auto cashback processing disabled
]
```

## Hook Sequence Timeline

```
1. Customer Select Event
   └─→ ns-pos-customer-selected hook
       └─→ isSpecialCustomer() check
       └─→ Mark customer as special
       └─→ Update POS UI

2. Product Add Event  
   └─→ ns-pos-product-price hook
       └─→ applyWholesalePricing() 
       └─→ Return wholesale price if available
       └─→ Update product display in cart

3. Order Submit Event
   └─→ ns-order-attributes hook
       └─→ Pre-set discount fields
       └─→ Add special_customer_data metadata

4. Order Processing Event
   └─→ ns-order-after-check-performed hook
       └─→ validateDiscountEligibility()
       └─→ applySpecialDiscount()
       └─→ Calculate and set discount amount
       └─→ Recalculate order total

5. Order Save Event
   └─→ ns-pos-after-order hook
       └─→ Record order for cashback tracking
       └─→ Log special customer order creation
```

## Files Involved

1. **NsSpecialCustomerServiceProvider.php**
   - Registers all POS hooks in `registerPOSHooks()` method
   - Handles customer selection in `ns-pos-customer-selected` hook
   - Applies wholesale pricing in `ns-pos-product-price` hook
   - Sets order attributes in `ns-order-attributes` hook
   - Applies discount in `ns-order-after-check-performed` hook

2. **SpecialCustomerService.php**
   - `isSpecialCustomer($customer)` - Checks if customer is in special group
   - `applyWholesalePricing($product, $customer)` - Returns wholesale pricing
   - `applySpecialDiscount($order, $customer)` - Calculates special discount
   - `validateDiscountEligibility($customer, $order)` - Validates discount rules
   - `getConfig()` - Returns special customer configuration

3. **Listeners/**
   - `CustomerSelectedListener.php` - Legacy listener for customer selection
   - `ProductPriceListener.php` - Legacy listener for product pricing
   - `OrderAttributesListener.php` - Legacy listener for order attributes
   - `OrderAfterCreatedListener.php` - Legacy listener for post-order processing

## Testing the Flow

To verify the flow is working:

1. Select a customer in POS
2. Check browser console for log: "Special customer discount attributes set"
3. Add products to cart - verify wholesale price is used if available
4. Submit order - check Laravel logs for discount calculation
5. Check order in database - verify discount fields are set correctly

## Common Issues

1. **"Attempt to read property 'customer' on array"**
   - Fixed by adding `is_array()` checks before accessing object properties
   - Both array and object formats are now handled

2. **Discount not applied**
   - Check if customer group ID is set in configuration
   - Verify discount percentage > 0
   - Check logs for "Special customer discount applied"

3. **Wholesale price not applied**
   - Verify product has wholesale_price set
   - Check that wholesale_price < sale_price
   - Verify customer is in special group

