<?php

namespace Modules\NsContainerManagement\Crud;

use App\Services\CrudService;
use Modules\NsContainerManagement\Models\ContainerMovement;
use Modules\NsContainerManagement\Models\ContainerType;
use App\Classes\CrudForm;
use App\Classes\FormInput;
use App\Services\Helper;
use TorMorten\Eventy\Facades\Events as Hook;

class ContainerAdjustmentCrud extends CrudService
{
    const IDENTIFIER = 'ns.container-adjustment';

    protected $table = 'ns_container_movements';
    protected $model = ContainerMovement::class;
    protected $namespace = 'ns.container-adjustment';

    protected $permissions = [
        'create' => 'nexopos.adjust.container-stock',
        'read' => 'nexopos.adjust.container-stock',
        'update' => 'nexopos.adjust.container-stock',
        'delete' => 'nexopos.adjust.container-stock',
    ];

    public function getLinks(): array
    {
        return [
            'list'   => ns()->route('ns.dashboard.container-inventory'),
            'create' => ns()->route('ns.dashboard.container-inventory'),
            'post'   => ns()->url('/api/crud/' . self::IDENTIFIER),
            'edit'   => ns()->route('ns.dashboard.container-inventory'), // Fallback
        ];
    }

    public function getLabels()
    {
        return [
            'create_title' => __('Adjust Inventory'),
            'create_description' => __('Add or remove container stock.'),
            'create_new' => __('Adjust Stock'),
            'save' => __('Confirm Adjustment'),
            'back_to_list' => __('Back to Inventory'),
        ];
    }

    public function getForm($entry = null)
    {
        $types = ContainerType::where('is_active', 1)->get();

        return Hook::filter('ns-container-adjustment-crud-form', CrudForm::form(
            title: __('Adjustment Details'),
            description: __('Manually update inventory levels.'),
            tabs: [
                'general' => [
                    'label' => __('Adjustment'),
                    'fields' => CrudForm::fields(
                        FormInput::select(
                            name: 'container_type_id',
                            label: __('Container Type'),
                            validation: 'required',
                            options: Helper::toJsOptions($types, ['id', 'name'])
                        ),
                        FormInput::number(
                            name: 'quantity',
                            label: __('Quantity Delta'),
                            validation: 'required|numeric',
                            description: __('Positive to add, negative to remove.')
                        ),
                        FormInput::textarea(
                            name: 'note',
                            label: __('Note'),
                            validation: 'required',
                            description: __('Reason for adjustment (e.g., Procurement, Lost, Damage).')
                        ),
                        FormInput::hidden(
                            name: 'direction',
                            label: '',
                            value: 'adjustment'
                        ),
                        FormInput::hidden(
                            name: 'source_type',
                            label: '',
                            value: 'inventory_adjustment'
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

    private function sanitizeInputs($inputs)
    {
        if (is_array($inputs)) {
            unset($inputs['undefined']);
            $inputs['author'] = auth()->id();
            // Timestamps handled by Eloquent automatically now
        }
        return $inputs;
    }
}
