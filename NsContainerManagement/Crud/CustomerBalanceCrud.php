<?php

namespace Modules\NsContainerManagement\Crud;

use App\Services\CrudService;
use Modules\NsContainerManagement\Models\CustomerContainerBalance;
use App\Services\Helper;
use App\Services\CrudEntry;

class CustomerBalanceCrud extends CrudService
{
    const IDENTIFIER = 'ns.container-customers';

    protected $table = 'ns_customer_container_balances';
    protected $model = CustomerContainerBalance::class;
    protected $namespace = 'ns.container-customers';

    protected $relations = [
        [ 'nexopos_users as customer', 'ns_customer_container_balances.customer_id', '=', 'customer.id' ],
        [ 'ns_container_types as type', 'ns_customer_container_balances.container_type_id', '=', 'type.id' ],
    ];

    protected $permissions = [
        'create' => 'nexopos.view.container-customers',
        'read' => 'nexopos.view.container-customers',
        'update' => 'nexopos.view.container-customers',
        'delete' => 'nexopos.view.container-customers',
    ];

    public function getLinks(): array
    {
        return [
            'list' => ns()->route('ns.dashboard.container-customers'),
            'edit' => ns()->route('ns.dashboard.container-customers'), // Fallback
        ];
    }

    public function getLabels()
    {
        return [
            'list_title' => __('Customer Balances'),
            'list_description' => __('Total containers owed by each customer.'),
            'no_entry' => __('No balances found.'),
        ];
    }

    public function getColumns(): array
    {
        return [
            'customer_first_name' => [
                'label' => __('First Name'),
                '$sort' => true,
            ],
            'customer_last_name' => [
                'label' => __('Last Name'),
                '$sort' => true,
            ],
            'type_name' => [
                'label' => __('Type'),
                '$sort' => true,
            ],
            'balance' => [
                'label' => __('Owed Quantity'),
                '$sort' => true,
            ],
        ];
    }

    public function setActions(CrudEntry $entry): CrudEntry
    {
        $entry->action(
            identifier: 'charge_customer',
            label: __('Charge Customer'),
            type: 'GOTO',
            url: ns()->url('/dashboard/container-management/charge?customer_id=' . $entry->customer_id . '&container_type_id=' . $entry->container_type_id),
        );

        return $entry;
    }
}
