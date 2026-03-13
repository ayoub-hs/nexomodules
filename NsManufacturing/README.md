# NsManufacturing Module

Manufacturing management for NexoPOS. This module adds Bill of Materials (BOM), production orders, inventory consumption/production, and reporting.

**Requirements**
- NexoPOS 6.0.0+
- PHP 8.2+

**Installation**
1. Copy the module to `modules/NsManufacturing`.
2. Enable the module in the NexoPOS admin UI.
3. Run migrations:
```bash
php artisan migrate
```

**Core Features**
- BOMs and BOM items with product and unit references.
- Production orders with lifecycle states (draft, planned, in_progress, completed, cancelled).
- Inventory consumption and production using NexoPOS stock adjustments.
- Manufacturing stock movements with cost-at-time tracking.
- Product and unit flags: `is_manufactured` and `is_raw_material`.
- Reports for summary metrics, production history, and ingredient consumption.

**Web Routes**
- `GET /dashboard/manufacturing/boms`
- `GET /dashboard/manufacturing/boms/create`
- `GET /dashboard/manufacturing/boms/edit/{id}`
- `GET /dashboard/manufacturing/boms/explode/{id}`
- `GET /dashboard/manufacturing/bom-items`
- `GET /dashboard/manufacturing/bom-items/create`
- `GET /dashboard/manufacturing/bom-items/edit/{id}`
- `GET /dashboard/manufacturing/orders`
- `GET /dashboard/manufacturing/orders/create`
- `GET /dashboard/manufacturing/orders/edit/{id}`
- `GET|POST /dashboard/manufacturing/orders/{id}/start`
- `GET|POST /dashboard/manufacturing/orders/{id}/complete`
- `GET /dashboard/manufacturing/analytics`
- `GET /dashboard/manufacturing/reports`
- `GET /dashboard/manufacturing/reports/summary`
- `GET /dashboard/manufacturing/reports/history`
- `GET /dashboard/manufacturing/reports/consumption`
- `GET /dashboard/manufacturing/reports/filters`

**Mobile API Endpoints**
- `GET /api/mobile/manufacturing/orders`
- `GET /api/mobile/manufacturing/orders/{id}`
- `POST /api/mobile/manufacturing/orders`
- `PUT /api/mobile/manufacturing/orders/{id}/start`
- `PUT /api/mobile/manufacturing/orders/{id}/complete`
- `GET /api/mobile/manufacturing/boms`
- `GET /api/mobile/manufacturing/boms/{id}`
- `POST /api/mobile/manufacturing/boms`

**Data Tables**
- `ns_manufacturing_boms`
- `ns_manufacturing_bom_items`
- `ns_manufacturing_orders`
- `ns_manufacturing_stock_movements`
- Adds manufacturing flags to products and product unit quantities

**Permissions Created**
- `nexopos.create.manufacturing-recipes`
- `nexopos.read.manufacturing-recipes`
- `nexopos.update.manufacturing-recipes`
- `nexopos.delete.manufacturing-recipes`
- `nexopos.create.manufacturing-orders`
- `nexopos.read.manufacturing-orders`
- `nexopos.update.manufacturing-orders`
- `nexopos.delete.manufacturing-orders`
- `nexopos.start.manufacturing-orders`
- `nexopos.complete.manufacturing-orders`
- `nexopos.cancel.manufacturing-orders`
- `nexopos.view.manufacturing-costs`
- `nexopos.export.manufacturing-reports`

**POS and CRUD Integration**
- Adds manufacturing fields to product and product-unit CRUD forms.
- Adds history labels for manufacturing stock movements.
- Exposes manufacturing menus in the dashboard after Inventory.

**Mobile API Integration**
- Mobile endpoints are exposed through the MobileApi module when both modules are installed.
