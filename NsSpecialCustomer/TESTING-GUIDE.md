# Outstanding Tickets Payment Popup - Testing Guide

## Quick Start

### 1. Clear Caches
```bash
# Clear Laravel cache
php artisan cache:clear
php artisan config:clear
php artisan view:clear

# Clear browser cache
# Press Ctrl+Shift+Delete (or Cmd+Shift+Delete on Mac)
# Or use hard refresh: Ctrl+F5 (Cmd+Shift+R on Mac)
```

### 2. Open Browser Console
- **Chrome/Edge**: Press F12 or Ctrl+Shift+I
- **Firefox**: Press F12 or Ctrl+Shift+K
- **Safari**: Press Cmd+Option+I

### 3. Navigate to Outstanding Tickets
```
Dashboard → Special Customer → Outstanding Tickets
```

## Expected Console Output

### On Page Load
```
[NsSpecialCustomer] Initializing component registration...
[NsSpecialCustomer] Creating nsExtraComponents registry
[NsSpecialCustomer] Component "nsOutstandingTicketPayment" registered successfully
[NsSpecialCustomer] Available components: ["nsOutstandingTicketPayment"]
```

### When Clicking "Pay From Wallet"
```
[OutstandingTicketPopup] Component mounted {row: {...}, popup: {...}}
[OutstandingTicketPopup] Loading ticket data {ticketId: 123}
[OutstandingTicketPopup] Order data loaded {id: 123, code: "ORD-001", ...}
[OutstandingTicketPopup] Loading customer balance {customerId: 45}
[OutstandingTicketPopup] Balance loaded {balance: 1000}
```

### When Submitting Payment
```
[OutstandingTicketPopup] Submitting payment {orderId: 123, customerId: 45}
[OutstandingTicketPopup] Payment successful {message: "..."}
[OutstandingTicketPopup] Refreshing CRUD table
```

## Test Scenarios

### ✅ Scenario 1: Successful Payment (Sufficient Balance)

**Setup:**
- Customer has wallet balance ≥ order due amount
- Order is unpaid or partially paid

**Steps:**
1. Navigate to Outstanding Tickets page
2. Find an order with due amount
3. Click "Pay From Wallet" button
4. Verify popup opens
5. Check wallet balance is displayed in green
6. Click "Pay with Wallet" button
7. Wait for success message

**Expected Results:**
- ✅ Popup opens without errors
- ✅ Order details display correctly
- ✅ Wallet balance shows in green
- ✅ "Pay with Wallet" button is enabled
- ✅ Payment processes successfully
- ✅ Success message appears
- ✅ Popup closes
- ✅ CRUD table refreshes
- ✅ Order removed from list (if fully paid)

### ❌ Scenario 2: Insufficient Balance

**Setup:**
- Customer has wallet balance < order due amount

**Steps:**
1. Navigate to Outstanding Tickets page
2. Find an order where customer has insufficient balance
3. Click "Pay From Wallet" button
4. Verify popup opens

**Expected Results:**
- ✅ Popup opens without errors
- ✅ Wallet balance shows in red
- ✅ Warning message displays: "Insufficient balance. Please choose another payment method or top up the wallet."
- ✅ "Pay with Wallet" button is disabled
- ✅ Cannot submit payment

### 🔍 Scenario 3: Component Registration Check

**Steps:**
1. Open browser console
2. Navigate to Outstanding Tickets page
3. Type in console: `window.nsExtraComponents`
4. Press Enter

**Expected Results:**
```javascript
{
  nsOutstandingTicketPayment: {
    __asyncLoader: ƒ,
    __asyncResolved: {...}
  }
}
```

### 🔍 Scenario 4: Multiple Popups

**Steps:**
1. Click "Pay From Wallet" on first order
2. Close popup
3. Click "Pay From Wallet" on different order
4. Verify correct order data loads

**Expected Results:**
- ✅ Each popup shows correct order data
- ✅ No data mixing between popups
- ✅ Balance loads for correct customer

## Troubleshooting

### Problem: Popup Doesn't Open

**Check:**
1. Console for component registration message
   ```
   [NsSpecialCustomer] Component "nsOutstandingTicketPayment" registered successfully
   ```
2. If missing, check route name in console:
   ```javascript
   // Type in console:
   window.location.pathname
   ```
3. Verify RenderFooterListener is loading the component

**Solution:**
- Clear all caches
- Hard refresh browser (Ctrl+F5)
- Check file permissions on blade files

### Problem: "Ticket ID is missing" Error

**Check:**
1. Console for error message
2. Verify CRUD is passing `row` prop correctly

**Solution:**
- Check OutstandingTicketCrud.php action configuration
- Verify component receives `row` prop in console

### Problem: Balance Not Loading

**Check:**
1. Console for API call:
   ```
   [OutstandingTicketPopup] Loading customer balance {customerId: X}
   ```
2. Network tab for API request to `/api/special-customer/balance/{id}`
3. Check if customer_id exists on order

**Solution:**
- Verify API route is registered
- Check customer has special customer group
- Verify permissions

### Problem: Payment Fails

**Check:**
1. Console for error message
2. Network tab for API response
3. Laravel logs: `storage/logs/laravel.log`

**Common Errors:**
- "Insufficient balance" - Customer needs top-up
- "Customer is not eligible" - Not in special customer group
- "Order is not eligible" - Already paid or wrong status

## API Endpoints Used

### GET `/api/crud/ns.outstanding-tickets/{id}`
**Purpose:** Fetch order details
**Response:**
```json
{
  "id": 123,
  "code": "ORD-001",
  "customer_id": 45,
  "total": 1000,
  "due_amount": 500,
  "customer": {
    "first_name": "John",
    "last_name": "Doe"
  }
}
```

### GET `/api/special-customer/balance/{customerId}`
**Purpose:** Fetch customer wallet balance
**Response:**
```json
{
  "balance": 1500.00
}
```

### POST `/api/special-customer/outstanding-tickets/pay`
**Purpose:** Process wallet payment
**Payload:**
```json
{
  "order_id": 123,
  "customer_id": 45
}
```
**Response:**
```json
{
  "status": "success",
  "message": "Outstanding ticket paid successfully."
}
```

## Browser Compatibility

### Tested Browsers
- ✅ Chrome 90+
- ✅ Firefox 88+
- ✅ Edge 90+
- ✅ Safari 14+

### Known Issues
- None currently

## Performance

### Expected Load Times
- Component registration: < 100ms
- Popup open: < 200ms
- Order data load: < 500ms
- Balance load: < 300ms
- Payment processing: < 1000ms

### Optimization Tips
- Component is lazy-loaded (defineAsyncComponent)
- API calls are cached where appropriate
- CRUD table uses pagination

## Security Considerations

### Permissions Required
- `special.customer.view` - To view outstanding tickets
- `special.customer.pay-outstanding-tickets` - To pay tickets

### Validation
- ✅ Customer ownership verified
- ✅ Order eligibility checked
- ✅ Balance validation on frontend and backend
- ✅ CSRF protection enabled
- ✅ Authentication required

## Support

### If Issues Persist

1. **Check Laravel Logs**
   ```bash
   tail -f storage/logs/laravel.log
   ```

2. **Enable Debug Mode** (temporarily)
   ```env
   APP_DEBUG=true
   ```

3. **Check Database**
   ```sql
   -- Verify order exists
   SELECT * FROM nexopos_orders WHERE id = 123;
   
   -- Verify customer is special
   SELECT * FROM nexopos_users WHERE id = 45;
   
   -- Check wallet balance
   SELECT * FROM nexopos_customers_account_history WHERE customer_id = 45;
   ```

4. **Contact Support**
   - Include console logs
   - Include network tab screenshots
   - Include Laravel logs
   - Describe steps to reproduce

## Additional Resources

- [POPUP-FIX-SUMMARY.md](./POPUP-FIX-SUMMARY.md) - Detailed fix documentation
- [TODO-POPUP-FIX.md](./TODO-POPUP-FIX.md) - Implementation checklist
- [OutstandingTicketCrud.php](./Crud/OutstandingTicketCrud.php) - CRUD configuration
- [components-registration.blade.php](./Resources/Views/components-registration.blade.php) - Component code
