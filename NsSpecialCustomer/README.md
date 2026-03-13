# NsSpecialCustomer Module

Special customer management for NexoPOS. This module creates a dedicated customer group and adds discounts, cashback, wallet top-ups, and outstanding ticket payment flows with POS integration.

**Requirements**
- NexoPOS 6.0.0+
- PHP 8.2+

**Installation**
1. Copy the module to `modules/NsSpecialCustomer`.
2. Enable the module in the NexoPOS admin UI.
3. Run migrations:
```bash
php artisan migrate
```

**Core Features**
- Creates a "Special" customer group and stores its ID in options.
- Configurable discount and cashback settings, including stackable discounts.
- Wallet top-ups with audit trail and reference fields.
- Cashback processing with idempotency and period overlap protection.
- Outstanding ticket management and payment (cash, card, bank transfer, wallet).
- POS hooks for pricing, customer flags, and order metadata.

**Settings (POS Tab)**
- `ns_special_customer_group_id` (Special customer group)
- `ns_special_discount_percentage`
- `ns_special_cashback_percentage`
- `ns_special_apply_discount_stackable`
- `ns_special_min_order_amount`
- `ns_special_min_topup_amount`
- `ns_special_max_topup_amount`
- `ns_special_enable_auto_cashback`
- `ns_special_cashback_processing_month`

**Permissions Created**
- `special.customer.manage`
- `special.customer.view`
- `special.customer.cashback`
- `special.customer.topup`
- `special.customer.pay-outstanding-tickets`
- `special.customer.settings`

**Web Routes**
- `GET /dashboard/special-customer`
- `GET /dashboard/special-customer/customers`
- `GET /dashboard/special-customer/cashback`
- `GET /dashboard/special-customer/settings`
- `GET /dashboard/special-customer/topup`
- `GET /dashboard/special-customer/topup/create`
- `GET /dashboard/special-customer/outstanding-tickets`
- `POST /dashboard/special-customer/outstanding-tickets/pay`
- `GET /dashboard/special-customer/outstanding-tickets/payment/{order}`
- `GET /dashboard/special-customer/balance/{customerId}`
- `GET /dashboard/special-customer/statistics`
- `GET /dashboard/special-customer/cashback/create`
- `GET /dashboard/special-customer/cashback/edit/{id}`

**API Endpoints**
Config and Settings
- `GET /api/special-customer/config`
- `POST /api/special-customer/settings`
- `GET /api/special-customer/stats`

Customers and Wallet
- `GET /api/special-customer/check/{customerId}`
- `GET /api/special-customer/customers`
- `POST /api/special-customer/topup`
- `GET /api/special-customer/balance/{customerId}`

Cashback
- `GET /api/special-customer/cashback`
- `GET /api/special-customer/cashback/statistics`
- `GET /api/special-customer/cashback/calculate`
- `GET /api/special-customer/cashback/customer/{customerId}`
- `POST /api/special-customer/cashback`
- `DELETE /api/special-customer/cashback/{id}`

Outstanding Tickets
- `POST /api/special-customer/outstanding-tickets/pay`
- `POST /api/special-customer/outstanding-tickets/pay-with-method`

CRUD Utilities
- `GET /api/crud/ns.special-customers`
- `POST /api/crud/ns.special-customers`
- `GET /api/crud/ns.special-customers/{id}`
- `PUT /api/crud/ns.special-customers/{id}`
- `DELETE /api/crud/ns.special-customers/{id}`
- `GET /api/crud/ns.special-customer-cashback`
- `POST /api/crud/ns.special-customer-cashback`
- `GET /api/crud/ns.special-customer-cashback/{id}`
- `PUT /api/crud/ns.special-customer-cashback/{id}`
- `DELETE /api/crud/ns.special-customer-cashback/{id}`
- `GET /api/crud/ns.special-customer-topup`
- `GET /api/crud/ns.special-customer-topup/{id}`
- `GET /api/crud/ns.outstanding-tickets`
- `GET /api/crud/ns.outstanding-tickets/{id}`

**Data Changes**
- Creates `special_cashback_history` table.
- Adds `reference` and `received_date` columns to `nexopos_customers_account_history`.

**POS Integration Hooks**
- `ns-pos-options` adds special customer configuration to POS.
- `ns-pos-customer-selected` adds wallet balance and badges.
- `ns-pos-product-price` applies wholesale pricing for special customers.
- `ns-order-attributes` attaches special customer metadata to orders.
- `ns-orders-before-create` enforces discounts server-side.

**Notes**
- Balance access is gated by `special.customer.manage` or `special.customer.pay-outstanding-tickets`. Other users must pass ownership checks.
- Mobile API routes for special customers are exposed through the MobileApi module when installed.
