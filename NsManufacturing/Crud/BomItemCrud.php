<?php

namespace Modules\NsManufacturing\Crud;

use App\Services\CrudService;
use Modules\NsManufacturing\Models\ManufacturingBomItem;
use App\Classes\CrudForm;
use App\Classes\CrudTable;
use App\Classes\FormInput;
use App\Models\Product;
use App\Models\Unit;
use App\Services\Helper;
use App\Services\CrudEntry;
use TorMorten\Eventy\Facades\Events as Hook;
use Modules\NsManufacturing\Services\ManufacturingHelper;

class BomItemCrud extends CrudService
{
    use ManufacturingHelper;
    
    const IDENTIFIER = 'ns.manufacturing-bom-items';

    protected $table = 'ns_manufacturing_bom_items';
    protected $model = ManufacturingBomItem::class;
    protected $namespace = 'ns.manufacturing-bom-items';

    protected $prependOptions = true;

    protected $permissions = [
        'create' => 'nexopos.create.manufacturing-recipes',
        'read' => 'nexopos.read.manufacturing-recipes',
        'update' => 'nexopos.update.manufacturing-recipes',
        'delete' => 'nexopos.delete.manufacturing-recipes',
    ];

    public $relations = [
        ['nexopos_products as product', 'ns_manufacturing_bom_items.product_id', '=', 'product.id'],
        ['nexopos_units as unit', 'ns_manufacturing_bom_items.unit_id', '=', 'unit.id']
    ];

    public $pick = [
        'product' => ['name', 'purchase_price'],
        'unit'    => ['name']
    ];

    public function getLinks(): array
    {
        return [
            'list'   => ns()->route('ns.dashboard.manufacturing-bom-items'),
            'create' => ns()->route('ns.dashboard.manufacturing-bom-items.create'),
            'post'   => ns()->url('/api/crud/' . self::IDENTIFIER),
            'put'    => ns()->url('/api/crud/' . self::IDENTIFIER . '/{id}'),
            'edit'   => ns()->url('/dashboard/manufacturing/bom-items/edit/{id}'),
        ];
    }

    public function getLabels()
    {
        return CrudTable::labels(
            list_title: __('BOM Components'),
            list_description: __('Review and manage ingredients for your production recipes.'),
            no_entry: __('No components assigned to this BOM.'),
            create_new: __('Add Component'),
            create_title: __('New Component'),
            create_description: __('Link a product as an ingredient to a recipe.'),
            edit_title: __('Edit Component'),
            edit_description: __('Update quantity or waste ratios for this ingredient.'),
            back_to_list: __('Back to Components'),
        );
    }

    public function getColumns(): array
    {
        return CrudTable::columns(
            CrudTable::column(__('Ingredient'), 'product_name'),
            CrudTable::column(__('Unit'), 'unit_name'),
            CrudTable::column(__('Quantity'), 'quantity'),
            CrudTable::column(__('Unit Cost'), 'unit_cost'),
            CrudTable::column(__('Total Cost'), 'total_cost'),
            CrudTable::column(__('Waste %'), 'waste_percent')
        );
    }

    public function getForm($entry = null)
    {
        return Hook::filter('ns-manufacturing-bom-items-crud-form', CrudForm::form(
            title: __('Item Details'),
            tabs: [
                'general' => [
                    'label' => __('General'),
                    'fields' => CrudForm::fields(
                        FormInput::select(__('BOM'), 'bom_id', $this->getBoms(), $entry->bom_id ?? request()->query('bom_id'), 'required'),
                        FormInput::select(__('Component Product'), 'product_id', $this->getProducts(), $entry->product_id ?? '', 'required'),
                        FormInput::select(__('Unit'), 'unit_id', $this->getUnits(), $entry->unit_id ?? '', 'required'),
                        FormInput::number(__('Quantity'), 'quantity', $entry->quantity ?? 1, 'required'),
                        FormInput::number(__('Waste Percent'), 'waste_percent', $entry->waste_percent ?? 0),
                        FormInput::number(__('Cost Allocation (%)'), 'cost_allocation', $entry->cost_allocation ?? 100)
                    )
                ]
            ]
        ));
    }

    public function setActions(CrudEntry $entry): CrudEntry
    {
        $item = ManufacturingBomItem::find($entry->id);
        $productService = app()->make(\App\Services\ProductService::class);
        $unitPrice = $productService->getCogs($item->product, $item->unit);
        $totalPrice = $unitPrice * $item->quantity;

        $entry->unit_cost = ns()->currency->define($unitPrice)->format();
        $entry->total_cost = ns()->currency->define($totalPrice)->format();
        $entry->quantity = $this->formatNumber($item->quantity);
        $entry->waste_percent = $this->formatNumber($item->waste_percent) . '%';

        $entry->action(
            identifier: 'edit',
            label: '<i class="las la-edit"></i> ' . __('Edit'),
            url: ns()->url('/dashboard/manufacturing/bom-items/edit/' . $entry->id)
        );

        $entry->action(
            identifier: 'delete',
            label: '<i class="las la-trash"></i> ' . __('Delete'),
            url: ns()->url('/api/crud/' . self::IDENTIFIER . '/' . $entry->id),
            type: 'DELETE',
            confirm: [
                'message' => __('Would you like to remove this ingredient from the BOM?'),
            ]
        );

        return $entry;
    }

    public function filterPostInputs($inputs, $entry)
    {
        unset($inputs['undefined']);
        return $inputs;
    }

    public function filterPutInputs($inputs, $entry)
    {
        unset($inputs['undefined']);
        return $inputs;
    }

    public function hook($query): void
    {
        if (request()->query('bom_id')) {
            $query->where('bom_id', request()->query('bom_id'));
        }
    }

    private function getProducts() {
        return Helper::kvToJsOptions(
            \App\Models\Product::where('status', 'available')
                ->where(function($query) {
                    $query->where('is_manufactured', true)
                          ->orWhere('is_raw_material', true);
                })
                ->limit(500)
                ->pluck('name', 'id')
                ->toArray()
        );
    }

    private function getUnits() {
        return Helper::kvToJsOptions(\App\Models\Unit::pluck('name', 'id')->toArray());
    }

    private function getBoms() {
        return Helper::kvToJsOptions(\Modules\NsManufacturing\Models\ManufacturingBom::pluck('name', 'id')->toArray());
    }
}
