# NsSpecialCustomer Module for NexoPOS 6

A comprehensive module that extends NexoPOS with specialized features for premium customer groups, providing wholesale pricing, special discounts, and cashback rewards.

## Features

### Core Features
- **Special Customer Group**: Automatically creates a "Special" customer group with premium benefits
- **Wholesale Pricing**: Special customers see wholesale prices in POS
- **Special Discounts**: Configurable discount percentage for special customers (default: 7%)
- **Cashback System**: Automated cashback rewards with idempotency protection
- **Account Top-ups**: Easy customer account credit management
- **Balance Dashboard**: Comprehensive customer balance reporting

### Technical Features
- **Idempotency Protection**: Prevents duplicate cashback processing
- **Period Overlap Protection**: Ensures no overlapping cashback periods
- **Backend Validation**: All pricing recalculated server-side for security
- **Real-time Updates**: POS interface updates instantly for special customers
- **Comprehensive Permissions**: Granular access control for all features
- **No External Dependencies**: Uses NexoPOS hooks and Vue injections
- **Bootstrap Integration**: Uses existing NexoPOS Bootstrap styling

## Installation

### 1. Module Setup
```bash
# Navigate to your NexoPOS installation
cd /path/to/nexopos

# The module should already be in modules/NsSpecialCustomer/
# If not, place it there
```

### 2. Run Migration
```bash
# From NexoPOS root
php artisan migrate
```

### 3. Clear Caches
```bash
php artisan cache:clear
php artisan view:clear
php artisan config:clear
```

### 4. Enable Module
Go to NexoPOS admin → Modules → Enable "Special Customer Management"

## Configuration

### Settings
Access the module settings at: `Dashboard → Special Customer → Dashboard`

- **Discount Percentage**: Default special discount (default: 7%)
- **Cashback Percentage**: Default cashback rate (default: 2%)
- **Discount Stackable**: Allow stacking with coupons/promotions (default: false)

### Permissions
The module automatically grants these permissions to admin users:
- `special.customer.manage`: Manage special customers and top-ups
- `special.customer.cashback`: Process and manage cashback
- `special.customer.settings`: Configure module settings

## Usage

### Making a Customer Special
1. Go to `Customers → Customer Groups`
2. Assign customer to the "Special" group (automatically created)
3. Customer will immediately receive special benefits

### Processing Account Top-ups
1. Navigate to `Special Customer → Top-up Account`
2. Select customer and enter amount
3. Add description and choose initiator
4. Process the top-up

### Managing Cashback
1. Navigate to `Special Customer → Cashback`
2. Click "Process Cashback"
3. Select customer, amount, and period
4. System prevents overlapping periods automatically
5. Cashback is credited to customer account

### Viewing Customer Balance
1. Navigate to `Special Customer → Customer Balance`
2. Select customer to view:
   - Current balance
   - Transaction history
   - Orders paid via wallet
   - Total credited/debited amounts

## API Endpoints

### Configuration
- `GET /api/special-customer/config` - Get module configuration
- `POST /api/special-customer/settings` - Update settings

### Customer Management
- `GET /api/special-customer/check/{customerId}` - Check if customer is special
- `GET /api/special-customer/balance/{customerId}` - Get customer balance info
- `POST /api/special-customer/topup` - Process account top-up

### Cashback Management
- `GET /api/special-customer/cashback` - List cashback history
- `POST /api/special-customer/cashback` - Process cashback
- `GET /api/special-customer/cashback/statistics` - Get statistics
- `GET /api/special-customer/cashback/customer/{customerId}` - Customer summary
- `DELETE /api/special-customer/cashback/{id}` - Delete cashback (with reversal)

## POS Integration

### Frontend Hooks
The module integrates with NexoPOS POS through these hooks:
- `ns-pos-options`: Injects special customer configuration
- `ns-pos-product-wholesale-price`: Applies wholesale pricing for special customers
- `ns-order-attributes`: Adds special customer metadata to orders
- `RenderFooterEvent`: Injects Vue components and JavaScript

### JavaScript API
```javascript
// Check if customer is special
const isSpecial = nsSpecialCustomer.isSpecialCustomer(customerGroupId);

// Get customer special status
const status = await nsSpecialCustomer.checkCustomerSpecialStatus(customerId);

// Apply special pricing
const pricedProduct = nsSpecialCustomer.applySpecialPricing(product, customerData);
```

## Database Schema

### Tables Created
- `ns_special_cashback_history`: Stores cashback records with idempotency protection

### Options Created
- `ns_special_customer_group_id`: Stores the special customer group ID
- `ns_special_discount_percentage`: Discount percentage setting (default: 7%)
- `ns_special_cashback_percentage`: Cashback percentage setting
- `ns_special_apply_discount_stackable`: Stackable discount setting

## Testing

### Run Tests
```bash
# From NexoPOS root
php artisan test --filter=SpecialCustomerTest
php artisan test --filter=CashbackTest
```

### Test Coverage
- Special customer identification
- Discount and cashback calculations
- Idempotency and overlap protection
- API endpoints
- Configuration management

## Security Features

### Backend Validation
- All pricing recalculated server-side during order processing
- Frontend logic never authoritative for financial calculations
- Proper permission checks on all endpoints

### Idempotency
- Unique constraints prevent duplicate cashback periods
- Transaction linking ensures audit trail
- Automatic reversal on deletion

### Data Integrity
- Foreign key constraints ensure referential integrity
- Database transactions prevent partial updates
- Proper error handling and rollback

## Troubleshooting

### Common Issues

1. **Module not appearing in dashboard**
   - Run `php artisan modules:symlink`
   - Clear caches: `php artisan cache:clear`

2. **JavaScript not working**
   - Check browser console for errors
   - Ensure POS hooks are registered
   - Verify RenderFooterEvent is firing

3. **Permissions not working**
   - Ensure migration ran successfully
   - Check admin role has special customer permissions

4. **POS not showing special prices**
   - Verify customer is in special group
   - Check browser console for JavaScript errors
   - Ensure POS hooks are registered

### Debug Mode
Enable debug mode in `.env`:
```env
APP_DEBUG=true
```

Check logs in `storage/logs/laravel.log` for detailed error information.

## Support

For issues and support:
1. Check the troubleshooting section above
2. Review NexoPOS documentation
3. Check module logs for specific errors
4. Verify all installation steps were completed

## Version History

### v1.0.0
- Initial release
- Special customer group management
- Wholesale pricing integration
- Cashback system with idempotency
- Account top-up functionality
- Comprehensive dashboard and reporting
- Full API coverage
- Automated testing suite
- No external dependencies
- Bootstrap styling integration

## License

This module is licensed under the MIT License, same as NexoPOS.
