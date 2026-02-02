<?php

namespace Modules\NsContainerManagement\Crud;

use App\Services\CrudService;
use Modules\NsContainerManagement\Models\ContainerMovement;
use Modules\NsContainerManagement\Models\ContainerType;
use App\Models\Customer;
use App\Classes\CrudForm;
use App\Classes\FormInput;
use App\Services\Helper;
use TorMorten\Eventy\Facades\Events as Hook;

class ReceiveContainerCrud extends CrudService
{
    const IDENTIFIER = 'ns.container-receive';

    protected $table = 'ns_container_movements';
    protected $model = ContainerMovement::class;
    protected $namespace = 'ns.container-receive';

    protected $permissions = [
        'create' => 'nexopos.receive.containers',
        'read' => 'nexopos.receive.containers',
        'update' => 'nexopos.receive.containers',
        'delete' => 'nexopos.receive.containers',
    ];

    public function getLinks(): array
    {
        return [
            'list'   => ns()->route('ns.dashboard.container-receive'),
            'create' => ns()->route('ns.dashboard.container-receive'),
            'post'   => ns()->url('/api/crud/' . self::IDENTIFIER),
            'edit'   => ns()->route('ns.dashboard.container-receive'), // Fallback for CrudService
        ];
    }

    public function getLabels()
    {
        return [
            'create_title' => __('Receive Containers'),
            'create_description' => __('Record containers returned by a customer.'),
            'create_new' => __('Receive Containers'),
            'save' => __('Confirm Receipt'),
            'return_to_list' => __('View History'),
        ];
    }

    public function getForm($entry = null)
    {
        $customers = Customer::limit(50)->get();
        $types = ContainerType::where('is_active', 1)->get();

        return Hook::filter('ns-container-receive-crud-form', CrudForm::form(
            title: __('Receive Config'),
            description: __('Details of the transaction.'),
            tabs: [
                'general' => [
                    'label' => __('Receive Details'),
                    'fields' => CrudForm::fields(
                        FormInput::searchSelect(
                            name: 'customer_id',
                            label: __('Customer'),
                            validation: 'required',
                            description: __('Select the customer returning containers.'),
                            options: Helper::toJsOptions($customers, function($customer) {
                                return [
                                    'label' => ($customer->first_name || $customer->last_name) 
                                        ? "{$customer->first_name} {$customer->last_name}" 
                                        : $customer->username,
                                    'value' => $customer->id,
                                ];
                            }),
                            component: 'nsCrudForm',
                            props: [
                                'url' => ns()->url('/api/shared/customers')
                            ]
                        ),
                        FormInput::select(
                            name: 'container_type_id',
                            label: __('Container Type'),
                            validation: 'required',
                            options: Helper::toJsOptions($types, ['id', 'name'])
                        ),
                        FormInput::number(
                            name: 'quantity',
                            label: __('Quantity'),
                            validation: 'required|numeric|min:1'
                        ),
                        FormInput::textarea(
                            name: 'note',
                            label: __('Note'),
                            description: __('Optional remarks.')
                        ),
                        FormInput::hidden(
                            name: 'direction',
                            label: '',
                            value: 'in'
                        ),
                        FormInput::hidden(
                            name: 'source_type',
                            label: '',
                            value: 'manual_return'
                        )
                    )
                ]
            ]
        ));
    }

    public function filterPostInputs($inputs, $entry)
    {
        return $this->sanitizeInputs($inputs);
    }

    public function filterPutInputs($inputs, $entry)
    {
        return $this->sanitizeInputs($inputs);
    }

    private function sanitizeInputs($inputs)
    {
        if (is_array($inputs)) {
            unset($inputs['undefined']);
            $inputs['author'] = auth()->id() ?? 0;
        }
        return $inputs;
    }

    public function beforePost($request)
    {
        return $request;
    }

    public function afterPost($request, $entry)
    {
        return $request;
    }
}
