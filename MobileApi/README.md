# Mobile API Module

Mobile-optimized API surface for NexoPOS clients. This module adds Sanctum-authenticated endpoints for sync, catalog, orders, inventory, registers, and optional integrations (manufacturing, container management, special customers).

**Requirements**
- NexoPOS 6.0.0+
- PHP 8.2+
- Laravel Sanctum enabled

**Installation**
1. Copy the module to `modules/MobileApi`.
2. Enable the module in the NexoPOS admin UI.
3. Run migrations:
```bash
php artisan migrate
```

**Authentication**
- `POST /api/mobile/auth/login` issues a Sanctum token.
- All other endpoints require `auth:sanctum`.
- Mobile routes add no-cache headers via `NoCacheHeaders` middleware.

**Key Capabilities**
- Bootstrap and delta sync with cursor-based pagination.
- Catalog, products, and unit quantity admin endpoints.
- Orders listing, detail, incremental sync, and batch creation.
- Inventory adjustments and history.
- Register configuration and register balance sync.
- Procurement CRUD workflows.
- Optional integrations for Manufacturing, Container Management, and Special Customer modules.

**Endpoints**
Auth
- `POST /api/mobile/auth/login`
- `POST /api/mobile/auth/logout`
- `GET /api/mobile/auth/permissions`
- `GET /api/mobile/auth/me`

Register
- `POST /api/cash-registers/sync/{register}`
- `GET /api/mobile/register/config`
- `GET /api/mobile/config/register`

Sync
- `GET /api/mobile/sync/bootstrap`
- `GET /api/mobile/sync/delta`
- `GET /api/mobile/sync/status`

Categories and Catalog
- `GET /api/mobile/categories`
- `GET /api/mobile/categories/{id}/products`
- `GET /api/mobile/catalog/category/{id}`
- `POST /api/mobile/catalog/search`
- `GET /api/mobile/catalog/product/{id}`

Products and Units
- `GET /api/mobile/products`
- `POST /api/mobile/products`
- `PUT /api/mobile/products/{id}`
- `POST /api/mobile/products/search`
- `GET /api/mobile/products/{id}`
- `GET /api/mobile/products/barcode/{barcode}`
- `PATCH /api/mobile/unit-quantities/{id}`
- `GET /api/mobile/units`
- `GET /api/mobile/unit-groups`
- `GET /api/mobile/tax-groups`

Inventory
- `POST /api/mobile/inventory/adjust`
- `GET /api/mobile/inventory/history`

Orders
- `GET /api/mobile/orders`
- `GET /api/mobile/orders/{order}`
- `GET /api/mobile/orders/sync`
- `POST /api/mobile/orders/batch`

Providers
- `GET /api/mobile/providers`

Procurements
- `GET /api/mobile/procurements`
- `GET /api/mobile/procurements/{id}`
- `POST /api/mobile/procurements`
- `PUT /api/mobile/procurements/{id}`
- `PUT /api/mobile/procurements/{id}/status`
- `PUT /api/mobile/procurements/{id}/receive`
- `PUT /api/mobile/procurements/{id}/cancel`
- `DELETE /api/mobile/procurements/{id}`

Optional: Manufacturing (requires `NsManufacturing`)
- `GET /api/mobile/manufacturing/orders`
- `GET /api/mobile/manufacturing/orders/{id}`
- `POST /api/mobile/manufacturing/orders`
- `PUT /api/mobile/manufacturing/orders/{id}/start`
- `PUT /api/mobile/manufacturing/orders/{id}/complete`
- `GET /api/mobile/manufacturing/boms`
- `GET /api/mobile/manufacturing/boms/{id}`
- `POST /api/mobile/manufacturing/boms`

Optional: Container Management (requires `NsContainerManagement`)
- `GET /api/mobile/containers/types`
- `GET /api/mobile/containers/inventory`
- `POST /api/mobile/containers/adjust`
- `POST /api/mobile/containers/receive`
- `GET /api/mobile/containers/customers/balances`
- `GET /api/mobile/containers/movements`
- `GET /api/mobile/containers/inventory/history`
- `GET /api/mobile/containers/charge/preview/{customerId}`
- `POST /api/mobile/containers/charge`

Optional: Special Customer (requires `NsSpecialCustomer`)
- `GET /api/mobile/special-customer/tickets`
- `GET /api/mobile/special-customer/tickets/{id}`
- `POST /api/mobile/special-customer/tickets/{id}/pay`
- `POST /api/mobile/special-customer/tickets/{id}/pay-from-wallet`
- `POST /api/mobile/special-customer/tickets/pay-with-method`
- `GET /api/mobile/special-customer/wallet/topups`
- `GET /api/mobile/special-customer/wallet/topups/{id}`
- `POST /api/mobile/special-customer/wallet/topup`
- `GET /api/mobile/special-customer/customers/{customerId}/balance`
- `GET /api/mobile/special-customer/customers`
- `GET /api/mobile/special-customer/stats`

**Testing**
- End-to-end specs are in `e2e/*.spec.ts`.
