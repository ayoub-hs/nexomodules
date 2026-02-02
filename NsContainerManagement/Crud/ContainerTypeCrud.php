<?php

namespace Modules\NsContainerManagement\Crud;

use App\Services\CrudService;
use Modules\NsContainerManagement\Models\ContainerType;
use App\Classes\CrudForm;
use App\Classes\FormInput;
use App\Services\Helper;
use TorMorten\Eventy\Facades\Events as Hook;
use App\Services\CrudEntry;

class ContainerTypeCrud extends CrudService
{
    const IDENTIFIER = 'ns.container-types';

    protected $table = 'ns_container_types';
    protected $model = ContainerType::class;
    protected $namespace = 'ns.container-types';

    protected $permissions = [
        'create' => 'nexopos.create.container-types',
        'read' => 'nexopos.read.container-types',
        'update' => 'nexopos.update.container-types',
        'delete' => 'nexopos.delete.container-types',
    ];

    public function getLinks(): array
    {
        return [
            'list'   => ns()->route('ns.dashboard.container-types'),
            'create' => ns()->route('ns.dashboard.container-types.create'),
            'post'   => ns()->url('/api/crud/' . self::IDENTIFIER),
            'put'    => ns()->url('/api/crud/' . self::IDENTIFIER . '/{id}'),
            'edit'   => ns()->url('/dashboard/container-management/types/edit/{id}'),
        ];
    }

    public function getLabels()
    {
        return [
            'list_title' => __('Container Types'),
            'list_description' => __('Manage available container types and deposit fees.'),
            'no_entry' => __('No container types found.'),
            'create_new' => __('Add Container Type'),
            'create_title' => __('New Container Type'),
            'create_description' => __('Define a new container type.'),
            'edit_title' => __('Edit Container Type'),
            'edit_description' => __('Modify container type details.'),
            'back_to_list' => __('Back to Types'),
        ];
    }

    public function getColumns(): array
    {
        return [
            'name' => [
                'label' => __('Name'),
                '$sort' => true,
            ],
            'capacity' => [
                'label' => __('Capacity'),
                '$sort' => true,
            ],
            'capacity_unit' => [
                'label' => __('Unit'),
                '$sort' => true,
            ],
            'deposit_fee' => [
                'label' => __('Deposit Fee'),
                '$sort' => true,
            ],
            'is_active' => [
                'label' => __('Active'),
                '$sort' => true,
                'type' => 'boolean', 
            ],
            'created_at' => [
                'label' => __('Created'),
                '$sort' => true,
            ],
        ];
    }

    public function getForm($entry = null)
    {
        return Hook::filter('ns-container-types-crud-form', CrudForm::form(
            title: __('General Config'),
            description: __('Define main settings.'),
            tabs: [
                'general' => [
                    'label' => __('General Information'),
                    'fields' => CrudForm::fields(
                        FormInput::text(
                            name: 'name',
                            label: __('Name'),
                            value: $entry->name ?? '',
                            validation: 'required',
                            description: __('e.g., 20L Drum')
                        ),
                        FormInput::number(
                            name: 'capacity',
                            label: __('Capacity'),
                            value: $entry->capacity ?? '',
                            validation: 'required|numeric',
                            description: __('Volume/Weight')
                        ),
                        FormInput::select(
                            name: 'capacity_unit',
                            label: __('Unit'),
                            value: $entry->capacity_unit ?? 'L',
                            options: Helper::kvToJsOptions(['L' => 'Liters', 'kg' => 'Kilograms', 'pcs' => 'Pieces']),
                            validation: 'required'
                        ),
                        FormInput::number(
                            name: 'deposit_fee',
                            label: __('Deposit Fee'),
                            value: $entry->deposit_fee ?? 0,
                            validation: 'required|numeric',
                            description: __('Fee charged if unreturned')
                        ),
                        FormInput::textarea(
                            name: 'description',
                            label: __('Description'),
                            value: $entry->description ?? '',
                            description: __('Optional notes')
                        ),
                        FormInput::switch(
                            name: 'is_active',
                            label: __('Active'),
                            value: $entry->is_active ?? true,
                            options: Helper::kvToJsOptions([0 => __('No'), 1 => __('Yes')])
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

    public function setActions(CrudEntry $entry): CrudEntry
    {
        $entry->is_active = $entry->is_active ? __('Yes') : __('No');

        $entry->action(
            identifier: 'edit',
            label: '<i class="mr-2 las la-edit"></i> ' . __('Edit'),
            type: 'GOTO',
            url: ns()->route('ns.dashboard.container-types.edit', ['id' => $entry->id])
        );

        $entry->action(
            identifier: 'delete',
            label: '<i class="mr-2 las la-trash"></i> ' . __('Delete'),
            type: 'DELETE',
            url: ns()->url('/api/crud/' . self::IDENTIFIER . '/' . $entry->id),
            confirm: [
                'message' => __('Are you sure you want to delete this container type?'),
            ]
        );

        return $entry;
    }
}
