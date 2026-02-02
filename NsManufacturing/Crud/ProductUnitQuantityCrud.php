<?php

namespace Modules\NsManufacturing\Crud;

use App\Services\CrudService;
use Modules\NsManufacturing\Models\ProductUnitQuantity as ManufacturingProductUnitQuantity;
use App\Classes\CrudForm;
use App\Classes\CrudTable;
use App\Classes\FormInput;
use App\Models\Product;
use App\Models\Unit;
use App\Services\Helper;
use App\Services\CrudEntry;
use TorMorten\Eventy\Facades\Events as Hook;
use Modules\NsManufacturing\Services\ManufacturingHelper;

class ProductUnitQuantityCrud extends CrudService
{
    use ManufacturingHelper;
    
    const IDENTIFIER = 'ns.manufacturing-product-unit-quantities';

    protected $table = 'nexopos_products_unit_quantities';
    protected $model = ManufacturingProductUnitQuantity::class;
    protected $namespace = 'ns.manufacturing-product-unit-quantities';

    protected $permissions = [
        'create' => 'nexopos.update.products',
        'read' => 'nexopos.update.products',
        'update' => 'nexopos.update.products',
        'delete' => 'nexopos.update.products',
    ];

    public $relations = [
        ['nexopos_products as product', 'nexopos_products_unit_quantities.product_id', '=', 'product.id'],
        ['nexopos_units as unit', 'nexopos_products_unit_quantities.unit_id', '=', 'unit.id']
    ];

    public $pick = [
        'product' => ['name', 'sku', 'barcode'],
        'unit'    => ['name', 'identifier']
    ];

    public function getLinks(): array
    {
        return [
            'list'   => ns()->route('ns.dashboard.manufacturing-product-unit-quantities'),
            'create' => ns()->route('ns.dashboard.manufacturing-product-unit-quantities.create'),
            'post'   => ns()->url('/api/crud/' . self::IDENTIFIER),
            'put'    => ns()->url('/api/crud/' . self::IDENTIFIER . '/{id}'),
            'edit'   => ns()->url('/dashboard/manufacturing/product-unit-quantities/edit/{id}'),
        ];
    }

    public function getLabels()
    {
        return CrudTable::labels(
            list_title: __('Product Unit Quantities'),
            list_description: __('Manage product units with manufacturing flags.'),
            no_entry: __('No Product Unit Quantities found.'),
            create_new: __('Add Product Unit Quantity'),
            create_title: __('New Product Unit Quantity'),
            create_description: __('Create a new product unit quantity with manufacturing flags.'),
            edit_title: __('Edit Product Unit Quantity'),
            edit_description: __('Modify an existing product unit quantity.'),
            back_to_list: __('Back to Product Unit Quantities'),
        );
    }

    public function getColumns(): array
    {
        return CrudTable::columns(
            CrudTable::column(__('Product'), 'product_name'),
            CrudTable::column(__('SKU'), 'product_sku'),
            CrudTable::column(__('Unit'), 'unit_name'),
            CrudTable::column(__('Barcode'), 'barcode'),
            CrudTable::column(__('Quantity'), 'quantity'),
            CrudTable::column(__('Manufactured'), 'is_manufactured'),
            CrudTable::column(__('Raw Material'), 'is_raw_material'),
            CrudTable::column(__('Status'), 'visible'),
            CrudTable::column(__('Created'), 'created_at')
        );
    }

    public function getForm($entry = null)
    {
        return Hook::filter('ns-manufacturing-product-unit-quantities-crud-form', CrudForm::form(
            title: __('Product Unit Quantity Details'),
            tabs: [
                'general' => [
                    'label' => __('General'),
                    'fields' => CrudForm::fields(
                        FormInput::select(__('Product'), 'product_id', $this->getProducts(), $entry->product_id ?? '', 'required'),
                        FormInput::select(__('Unit'), 'unit_id', $this->getUnits(), $entry->unit_id ?? '', 'required'),
                        FormInput::text(__('Barcode'), 'barcode', $entry->barcode ?? ''),
                        FormInput::number(__('Quantity'), 'quantity', $entry->quantity ?? 1, 'required'),
                        FormInput::switch(__('Visible'), 'visible', Helper::boolToOptions(__('Yes'), __('No')), $entry->visible ?? 1),
                    )
                ],
                'manufacturing' => [
                    'label' => __('Manufacturing'),
                    'fields' => CrudForm::fields(
                        FormInput::switch(__('Is Manufactured'), 'is_manufactured', [
                            ['label' => __('Yes'), 'value' => 1],
                            ['label' => __('No'), 'value' => 0],
                        ], (int) ($entry->is_manufactured ?? 0))
                            ->description(__('Check if this product can be manufactured (used for production)')),
                        FormInput::switch(__('Is Raw Material'), 'is_raw_material', [
                            ['label' => __('No'), 'value' => 0],
                            ['label' => __('Yes'), 'value' => 1],
                        ], (int) ($entry->is_raw_material ?? 0))
                            ->description(__('Check if this product is a raw material (can be used as component)')),
                        FormInput::help(__('Note: At least one manufacturing flag must be selected. Manufactured products can be used for production, while both manufactured products and raw materials can be used as components.'))
                    )
                ],
                'pricing' => [
                    'label' => __('Pricing'),
                    'fields' => CrudForm::fields(
                        FormInput::number(__('Sale Price'), 'sale_price', $entry->sale_price ?? 0, 'required')
                            ->append(ns()->currency->getSymbol()),
                        FormInput::number(__('Wholesale Price'), 'wholesale_price', $entry->wholesale_price ?? 0, 'required')
                            ->append(ns()->currency->getSymbol()),
                        FormInput::number(__('Low Quantity Alert'), 'low_quantity', $entry->low_quantity ?? 0, 'required'),
                        FormInput::switch(__('Stock Alert'), 'stock_alert_enabled', Helper::boolToOptions(__('Yes'), __('No')), $entry->stock_alert_enabled ?? 0),
                    )
                ]
            ]
        ));
    }

    public function setActions(CrudEntry $entry): CrudEntry
    {
        $entry->product_name = $entry->product_name . ' (' . ($entry->unit_name ?? __('N/A')) . ')';
        $entry->quantity = $this->formatNumber($entry->quantity);
        $entry->sale_price = ns()->currency->define($entry->sale_price)->format();
        $entry->wholesale_price = ns()->currency->define($entry->wholesale_price)->format();
        $entry->visible = $entry->visible ? __('Visible') : __('Hidden');
        $entry->is_manufactured = $entry->is_manufactured ? __('Yes') : __('No');
        $entry->is_raw_material = $entry->is_raw_material ? __('Yes') : __('No');

        $entry->action(
            identifier: 'edit',
            label: '<i class="las la-edit"></i> ' . __('Edit'),
            url: ns()->url('/dashboard/manufacturing/product-unit-quantities/edit/' . $entry->id)
        );

        $entry->action(
            identifier: 'delete',
            label: '<i class="las la-trash"></i> ' . __('Delete'),
            url: ns()->url('/api/crud/' . self::IDENTIFIER . '/' . $entry->id),
            type: 'DELETE',
            confirm: [
                'message' => __('Would you like to delete this product unit quantity?'),
            ]
        );

        return $entry;
    }

    public function filterPostInputs($inputs, $entry)
    {
        unset($inputs['undefined']);
        
        // Explicitly set boolean fields to ensure unchecked values are saved as 0
        // When a switch is unchecked, the browser doesn't send the field in the request
        $inputs['is_manufactured'] = !empty($inputs['is_manufactured']) ? 1 : 0;
        $inputs['is_raw_material'] = !empty($inputs['is_raw_material']) ? 1 : 0;
        
        // Validate manufacturing flags
        if (empty($inputs['is_manufactured']) && empty($inputs['is_raw_material'])) {
            throw new \Exception(__('At least one manufacturing flag must be selected (Is Manufactured or Is Raw Material).'));
        }
        
        if (empty($inputs['author'])) $inputs['author'] = auth()->id();
        if (empty($inputs['uuid'])) $inputs['uuid'] = \Illuminate\Support\Str::uuid();
        
        return $inputs;
    }

    public function filterPutInputs($inputs, $entry)
    {
        unset($inputs['undefined']);
        
        // Explicitly set boolean fields to ensure unchecked values are saved as 0
        // When a switch is unchecked, the browser doesn't send the field in the request
        $inputs['is_manufactured'] = !empty($inputs['is_manufactured']) ? 1 : 0;
        $inputs['is_raw_material'] = !empty($inputs['is_raw_material']) ? 1 : 0;
        
        // Validate manufacturing flags
        if (empty($inputs['is_manufactured']) && empty($inputs['is_raw_material'])) {
            throw new \Exception(__('At least one manufacturing flag must be selected (Is Manufactured or Is Raw Material).'));
        }
        
        return $inputs;
    }
    
    private function getProducts() {
        return Helper::kvToJsOptions(
            Product::where('status', 'available')
                ->where('stock_management', 'enabled')
                ->limit(500)
                ->pluck('name', 'id')
                ->toArray()
        );
    }

    private function getUnits() {
        return Helper::kvToJsOptions(Unit::pluck('name', 'id')->toArray());
    }
}