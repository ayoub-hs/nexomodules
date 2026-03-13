# NsContainerManagement Module

Container tracking and deposit management for NexoPOS. This module tracks container inventory, customer balances, and movements, and links containers to product units for automatic ledger updates.

**Requirements**
- NexoPOS 6.0.0+
- PHP 8.2+

**Installation**
1. Copy the module to `modules/NsContainerManagement`.
2. Enable the module in the NexoPOS admin UI.
3. Run migrations:
```bash
php artisan migrate
```

**Core Features**
- Container types with capacity, deposit fee, and active status.
- Inventory tracking with adjustments and movement history.
- Customer container balances with charge flows and ledger updates.
- Product-unit container linking for POS-driven container tracking.
- Automatic container-out movements on order creation when product data flags `container_tracking_enabled`.
- POS integration to expose container types and product links in `ns-pos-options`.

**Web Routes**
- `GET /dashboard/container-management/types`
- `GET /dashboard/container-management/types/create`
- `GET /dashboard/container-management/types/edit/{id}`
- `GET /dashboard/container-management/inventory`
- `GET /dashboard/container-management/adjust`
- `GET /dashboard/container-management/receive`
- `GET /dashboard/container-management/customers`
- `GET /dashboard/container-management/charge`
- `POST /dashboard/container-management/charge/process`
- `GET /dashboard/container-management/reports`
- `GET /dashboard/container-management/reports/summary`
- `GET /dashboard/container-management/reports/movements`
- `GET /dashboard/container-management/reports/charges`
- `GET /dashboard/container-management/reports/balances`
- `GET /dashboard/container-management/reports/export`
- `GET /dashboard/container-management/reports/filters`
- `POST /dashboard/container-management/products/calculate`

**API Endpoints**
- `GET /api/container-management/types`
- `POST /api/container-management/types`
- `GET /api/container-management/types/dropdown`
- `GET /api/container-management/types/{id}`
- `PUT /api/container-management/types/{id}`
- `DELETE /api/container-management/types/{id}`
- `GET /api/container-management/inventory`
- `GET /api/container-management/inventory/history`
- `GET /api/container-management/inventory/{typeId}`
- `POST /api/container-management/inventory/adjust`
- `POST /api/container-management/give`
- `POST /api/container-management/receive`
- `GET /api/container-management/movements`
- `GET /api/container-management/movements/{id}`
- `GET /api/container-management/customers/balances`
- `GET /api/container-management/customers/overdue`
- `GET /api/container-management/customers/search`
- `GET /api/container-management/customers/{id}/balance`
- `GET /api/container-management/customers/{id}/movements`
- `GET /api/container-management/charge/preview/{customerId}`
- `POST /api/container-management/charge`
- `POST /api/container-management/charge/all`
- `GET /api/container-management/products/{productId}/container`
- `POST /api/container-management/products/{productId}/container`
- `DELETE /api/container-management/products/{productId}/container`

**Data Tables**
- `ns_container_types`
- `ns_container_inventory`
- `ns_container_movements`
- `ns_customer_container_balances`
- `ns_product_containers`

**Permissions Created**
- `nexopos.create.container-types`
- `nexopos.read.container-types`
- `nexopos.update.container-types`
- `nexopos.delete.container-types`
- `nexopos.create.containers`
- `nexopos.read.containers`
- `nexopos.update.containers`
- `nexopos.delete.containers`
- `nexopos.manage.container-inventory`
- `nexopos.adjust.container-stock`
- `nexopos.receive.containers`
- `nexopos.view.container-customers`
- `nexopos.charge.containers`
- `nexopos.view.container-reports`
- `nexopos.export.container-reports`

**POS Integration Notes**
- Adds a `container_type_id` field to product unit quantities.
- Automatically updates container links on product create/update.
- Uses `RenderFooterEvent` to inject POS footer UI when on the POS route.
- Exposes container types and links to POS via `ns-pos-options`.

**Mobile API Integration**
- Mobile endpoints are exposed through the MobileApi module when both modules are installed.
