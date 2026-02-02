<?php

namespace Modules\NsManufacturing\Crud;

use App\Services\CrudService;
use Modules\NsManufacturing\Models\ManufacturingOrder;
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

class ProductionOrderCrud extends CrudService
{
    use ManufacturingHelper;
    
    const IDENTIFIER = 'ns.manufacturing-orders';

    protected $table = 'ns_manufacturing_orders';
    protected $model = ManufacturingOrder::class;
    
    protected $prependOptions = true;
    
    protected $namespace = 'ns.manufacturing-orders';

    protected $permissions = [
        'create' => 'nexopos.create.manufacturing-orders',
        'read' => 'nexopos.read.manufacturing-orders',
        'update' => 'nexopos.update.manufacturing-orders',
        'delete' => 'nexopos.delete.manufacturing-orders',
    ];

    public $relations = [
        ['nexopos_products as product', 'ns_manufacturing_orders.product_id', '=', 'product.id'],
        ['nexopos_units as unit', 'ns_manufacturing_orders.unit_id', '=', 'unit.id']
    ];

    public $pick = [
        'product' => ['name'],
        'unit'    => ['name']
    ];

    public function getLinks(): array
    {
        return [
            'list'   => ns()->route('ns.dashboard.manufacturing-orders'),
            'create' => ns()->route('ns.dashboard.manufacturing-orders.create'),
            'post'   => ns()->url('/api/crud/' . self::IDENTIFIER),
            'put'    => ns()->url('/api/crud/' . self::IDENTIFIER . '/{id}'),
            'edit'   => ns()->url('/dashboard/manufacturing/orders/edit/{id}'),
        ];
    }

    public function getLabels()
    {
        return CrudTable::labels(
            list_title: __('Production Orders'),
            list_description: __('Track and manage ongoing production runs.'),
            no_entry: __('No production orders found.'),
            create_new: __('Create Production Order'),
            create_title: __('Register New Order'),
            create_description: __('Manually register a production order and allocate ingredients.'),
            edit_title: __('Edit Order'),
            edit_description: __('Update order details or adjust quantities.'),
            back_to_list: __('Back to Orders'),
        );
    }

    public function getColumns(): array
    {
        return CrudTable::columns(
            CrudTable::column(__('Code'), 'code'),
            CrudTable::column(__('Product'), 'product_name'),
            CrudTable::column(__('Qty'), 'quantity'),
            CrudTable::column(__('Est. Cost'), 'estimated_cost'),
            CrudTable::column(__('Status'), 'status'),
            CrudTable::column(__('Created'), 'created_at')
        );
    }

    public function getForm($entry = null)
    {
        $fields = [
            FormInput::text(__('Code'), 'code', $entry->code ?? $this->generateCode(), 'required'),
            FormInput::select(__('BOM'), 'bom_id', $this->getBoms(), $entry->bom_id ?? '', 'required'),
        ];

        if ($entry) {
            $fields[] = FormInput::select(__('Product'), 'product_id', $this->getProducts(), $entry->product_id ?? '', 'required');
            $fields[] = FormInput::select(__('Unit'), 'unit_id', $this->getUnits(), $entry->unit_id ?? '', 'required');
        }

        $fields[] = FormInput::number(__('Quantity'), 'quantity', $entry->quantity ?? 1, 'required');
        $fields[] = FormInput::select(__('Status'), 'status', $this->getStatuses(), $entry->status ?? 'draft', 'required');

        return Hook::filter('ns-manufacturing-orders-crud-form', CrudForm::form(
            title: __('Order Details'),
            tabs: [
                'general' => [
                    'label' => __('General'),
                    'fields' => CrudForm::fields(...$fields)
                ]
            ]
        ));
    }

    public function setActions(CrudEntry $entry): CrudEntry
    {
        $order = ManufacturingOrder::find($entry->id);
        $bomService = app()->make(\Modules\NsManufacturing\Services\BomService::class);
        $unitCost = $order->bom ? $bomService->calculateEstimatedCost($order->bom) : 0;
        $totalCost = $unitCost * $order->quantity;

        $entry->estimated_cost = ns()->currency->define($totalCost)->format();
        $entry->quantity = $this->formatNumber($entry->quantity);
        $entry->product_name = $entry->product_name . ' (' . ($entry->unit_name ?? __('N/A')) . ')';
        $entry->status = ucwords(str_replace('_', ' ', $order->status));

        if ($order->status === ManufacturingOrder::STATUS_PLANNED || $order->status === ManufacturingOrder::STATUS_DRAFT) {
            $entry->action(
                identifier: 'start',
                label: '<i class="las la-play"></i> ' . __('Start'),
                url: ns()->url('/dashboard/manufacturing/orders/' . $entry->id . '/start'),
                type: 'GET',
                confirm: [
                    'message' => __('Are you sure you want to start this production order? This will deduct ingredients from stock.'),
                ]
            );
        }
        
        if ($order->status === ManufacturingOrder::STATUS_IN_PROGRESS) {
            $entry->action(
                identifier: 'complete',
                label: '<i class="las la-check"></i> ' . __('Complete'),
                url: ns()->url('/dashboard/manufacturing/orders/' . $entry->id . '/complete'),
                type: 'GET',
                confirm: [
                    'message' => __('Are you sure you want to complete this order? This will add the finished product to stock.'),
                ]
            );
        }

        $entry->action(
            identifier: 'edit',
            label: '<i class="las la-edit"></i> ' . __('Edit'),
            url: ns()->url('/dashboard/manufacturing/orders/edit/' . $entry->id)
        );

        $entry->action(
            identifier: 'delete',
            label: '<i class="las la-trash"></i> ' . __('Delete'),
            url: ns()->url('/api/crud/' . self::IDENTIFIER . '/' . $entry->id),
            type: 'DELETE',
            confirm: [
                'message' => __('Would you like to delete this production order?'),
            ]
        );

        return $entry;
    }

    public function filterPostInputs($inputs, $entry)
    {
        unset($inputs['undefined']);
        if (empty($inputs['author'])) $inputs['author'] = auth()->id();
        if (empty($inputs['code'])) $inputs['code'] = $this->generateCode();

        if (!empty($inputs['bom_id'])) {
            $bom = ManufacturingBom::find($inputs['bom_id']);
            if ($bom) {
                $inputs['product_id'] = $bom->product_id;
                $inputs['unit_id'] = $bom->unit_id;
            }
        }

        return $inputs;
    }

    public function filterPutInputs($inputs, $entry)
    {
        unset($inputs['undefined']);
        return $inputs;
    }

    private function generateCode() { return 'PO-' . date('YmdHis'); }
    private function getProducts() { return Helper::kvToJsOptions(Product::where('status', 'available')->limit(500)->pluck('name', 'id')->toArray()); }
    private function getUnits() { return Helper::kvToJsOptions(Unit::pluck('name', 'id')->toArray()); }
    private function getBoms() { return Helper::kvToJsOptions(ManufacturingBom::pluck('name', 'id')->toArray()); }
    private function getStatuses() {
        return Helper::kvToJsOptions([
            'draft' => __('Draft'),
            'planned' => __('Planned'),
            'in_progress' => __('In Progress'),
            'completed' => __('Completed'),
            'cancelled' => __('Cancelled')
        ]);
    }
}
