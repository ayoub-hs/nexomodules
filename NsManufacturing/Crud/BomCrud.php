<?php

namespace Modules\NsManufacturing\Crud;

use App\Services\CrudService;
use Modules\NsManufacturing\Models\ManufacturingBom;
use App\Classes\CrudForm;
use App\Classes\CrudTable;
use App\Classes\FormInput;
use App\Models\Product;
use App\Models\Unit;
use App\Services\Helper;
use App\Services\CrudEntry;
use TorMorten\Eventy\Facades\Events as Hook;
use Modules\NsManufacturing\Services\ManufacturingHelper;

class BomCrud extends CrudService
{
    use ManufacturingHelper;
    
    const IDENTIFIER = 'ns.manufacturing-boms';

    protected $table = 'ns_manufacturing_boms';
    protected $model = ManufacturingBom::class;
    protected $namespace = 'ns.manufacturing-boms';

    protected $prependOptions = true;

    protected $permissions = [
        'create' => 'nexopos.create.manufacturing-recipes',
        'read' => 'nexopos.read.manufacturing-recipes',
        'update' => 'nexopos.update.manufacturing-recipes',
        'delete' => 'nexopos.delete.manufacturing-recipes',
    ];

    public $relations = [
        ['nexopos_products as product', 'ns_manufacturing_boms.product_id', '=', 'product.id'],
        ['nexopos_units as unit', 'ns_manufacturing_boms.unit_id', '=', 'unit.id']
    ];

    public $pick = [
        'product' => ['name'],
        'unit'    => ['name']
    ];

    public function getLinks(): array
    {
        return [
            'list'   => ns()->route('ns.dashboard.manufacturing-boms'),
            'create' => ns()->route('ns.dashboard.manufacturing-boms.create'),
            'post'   => ns()->url('/api/crud/' . self::IDENTIFIER),
            'put'    => ns()->url('/api/crud/' . self::IDENTIFIER . '/{id}'),
            'edit'   => ns()->url('/dashboard/manufacturing/boms/edit/{id}'),
        ];
    }

    public function getLabels()
    {
        return CrudTable::labels(
            list_title: __('Bill of Materials'),
            list_description: __('Manage your product recipes and their components.'),
            no_entry: __('No Bill of Materials found.'),
            create_new: __('Add BOM'),
            create_title: __('New BOM'),
            create_description: __('Create a new Bill of Materials recipe.'),
            edit_title: __('Edit BOM'),
            edit_description: __('Modify an existing production recipe.'),
            back_to_list: __('Back to BOMs'),
        );
    }

    public function getColumns(): array
    {
        return CrudTable::columns(
            CrudTable::column(__('Name'), 'name'),
            CrudTable::column(__('Output Product'), 'product_name'),
            CrudTable::column(__('Output Qty'), 'quantity'),
            CrudTable::column(__('Est. Cost'), 'estimated_cost'),
            CrudTable::column(__('Status'), 'is_active'),
            CrudTable::column(__('Created'), 'created_at')
        );
    }

    public function getForm($entry = null)
    {
        return Hook::filter('ns-manufacturing-boms-crud-form', CrudForm::form(
            title: __('BOM Details'),
            tabs: [
                'general' => [
                    'label' => __('General'),
                    'fields' => CrudForm::fields(
                        FormInput::text(__('Name'), 'name', $entry->name ?? '', 'required'),
                        FormInput::select(__('Output Product'), 'product_id', $this->getProducts(), $entry->product_id ?? '', 'required'),
                        FormInput::select(__('Unit'), 'unit_id', $this->getUnits(), $entry->unit_id ?? '', 'required'),
                        FormInput::number(__('Output Quantity'), 'quantity', $entry->quantity ?? 1, 'required'),
                        FormInput::switch(__('Active'), 'is_active', Helper::boolToOptions(__('Yes'), __('No')), $entry->is_active ?? 1),
                        FormInput::textarea(__('Description'), 'description', $entry->description ?? '')
                    )
                ]
            ]
        ));
    }

    public function setActions(CrudEntry $entry): CrudEntry
    {
        $bom = ManufacturingBom::find($entry->id);
        $cost = app()->make(\Modules\NsManufacturing\Services\BomService::class)->calculateEstimatedCost($bom);
        
        $entry->estimated_cost = ns()->currency->define($cost)->format();
        $entry->quantity = $this->formatNumber($entry->quantity);
        $entry->product_name = $entry->product_name . ' (' . ($entry->unit_name ?? __('N/A')) . ')';
        $entry->is_active = $entry->is_active ? __('Active') : __('Inactive');

        $entry->action(
            identifier: 'items',
            label: '<i class="las la-list"></i> ' . __('View Items'),
            url: ns()->url('/dashboard/manufacturing/bom-items?bom_id=' . $entry->id)
        );

        $entry->action(
            identifier: 'explode',
            label: '<i class="las la-file-alt"></i> ' . __('Full Summary'),
            url: ns()->url('/dashboard/manufacturing/boms/explode/' . $entry->id)
        );

        $entry->action(
            identifier: 'edit',
            label: '<i class="las la-edit"></i> ' . __('Edit'),
            url: ns()->url('/dashboard/manufacturing/boms/edit/' . $entry->id)
        );

        $entry->action(
            identifier: 'delete',
            label: '<i class="las la-trash"></i> ' . __('Delete'),
            url: ns()->url('/api/crud/' . self::IDENTIFIER . '/' . $entry->id),
            type: 'DELETE',
            confirm: [
                'message' => __('Would you like to delete this BOM? All items will be removed.'),
            ]
        );

        return $entry;
    }

    public function filterPostInputs($inputs, $entry)
    {
        unset($inputs['undefined']);
        if (empty($inputs['author'])) $inputs['author'] = auth()->id();
        if (empty($inputs['uuid'])) $inputs['uuid'] = \Illuminate\Support\Str::uuid();
        return $inputs;
    }

    public function filterPutInputs($inputs, $entry)
    {
        unset($inputs['undefined']);
        return $inputs;
    }
    
    private function getProducts() {
        return Helper::kvToJsOptions(
            Product::where('status', 'available')
                ->where('is_manufactured', true)
                ->limit(500)
                ->pluck('name', 'id')
                ->toArray()
        );
    }

    private function getUnits() {
        return Helper::kvToJsOptions(Unit::pluck('name', 'id')->toArray());
    }
}
