# Mobile API Module

A NexoPOS 6.0.9 module that provides API endpoints for mobile app integration.

## Features

- **Sync Endpoints**: Bootstrap, delta, and status synchronization
- **Product Management**: Search, view, and barcode lookup
- **Category Management**: Browse categories and their products
- **Order Management**: View, create, and sync orders
- **Register Configuration**: Get cash register settings

## API Endpoints

All endpoints are protected with Sanctum authentication and prefixed with `/api/mobile`:

### Sync
- `GET /api/mobile/sync/bootstrap` - Initial sync data
- `GET /api/mobile/sync/delta` - Delta sync changes
- `GET /api/mobile/sync/status` - Sync status

### Products
- `POST /api/mobile/products/search` - Search products
- `GET /api/mobile/products/{id}` - Get product details
- `GET /api/mobile/products/barcode/{barcode}` - Lookup by barcode

### Categories
- `GET /api/mobile/categories/{id}/products` - Get products in category

### Orders
- `GET /api/mobile/orders` - List orders
- `GET /api/mobile/orders/{order}` - Get order details
- `GET /api/mobile/orders/sync` - Sync orders
- `POST /api/mobile/orders/batch` - Batch order operations

### Register
- `GET /api/mobile/register/config` - Get register configuration

## Installation

1. Place the module in `/modules/MobileApi/`
2. Enable the module through NexoPOS admin panel
3. Configure API permissions as needed

## Requirements

- NexoPOS 6.0.9+
- PHP 8.2+
- Laravel Sanctum for API authentication

## Controllers

- `MobileSyncController` - Handles data synchronization
- `MobileProductController` - Product operations
- `MobileCategoryController` - Category operations  
- `MobileOrdersController` - Order management
- `MobileRegisterConfigController` - Register configuration

## Security

All endpoints require valid Sanctum tokens. Ensure proper permissions are configured for mobile app access.
