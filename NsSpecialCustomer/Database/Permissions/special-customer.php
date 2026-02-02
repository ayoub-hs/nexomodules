<?php

use App\Models\Permission;

if (defined('NEXO_CREATE_PERMISSIONS')) {
    // Special Customer Management Permissions
    $permission = Permission::firstOrNew(['namespace' => 'special.customer.manage']);
    $permission->name = __('Manage Special Customers');
    $permission->namespace = 'special.customer.manage';
    $permission->description = __('Let the user manage special customer features and settings.');
    $permission->save();

    $permission = Permission::firstOrNew(['namespace' => 'special.customer.cashback']);
    $permission->name = __('Manage Special Customer Cashback');
    $permission->namespace = 'special.customer.cashback';
    $permission->description = __('Let the user process cashback rewards for special customers.');
    $permission->save();

    $permission = Permission::firstOrNew(['namespace' => 'special.customer.settings']);
    $permission->name = __('Special Customer Settings');
    $permission->namespace = 'special.customer.settings';
    $permission->description = __('Let the user configure special customer module settings.');
    $permission->save();

    $permission = Permission::firstOrNew(['namespace' => 'special.customer.topup']);
    $permission->name = __('Special Customer Top-up');
    $permission->namespace = 'special.customer.topup';
    $permission->description = __('Let the user add funds to special customer accounts.');
    $permission->save();

    $permission = Permission::firstOrNew(['namespace' => 'special.customer.pay-outstanding-tickets']);
    $permission->name = __('Pay Outstanding Tickets');
    $permission->namespace = 'special.customer.pay-outstanding-tickets';
    $permission->description = __('Let the user pay outstanding orders from special customer wallets.');
    $permission->save();

    $permission = Permission::firstOrNew(['namespace' => 'special.customer.view']);
    $permission->name = __('View Special Customers');
    $permission->namespace = 'special.customer.view';
    $permission->description = __('Let the user view special customer information and balances.');
    $permission->save();
}