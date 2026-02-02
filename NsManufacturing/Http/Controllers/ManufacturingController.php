<?php

namespace Modules\NsManufacturing\Http\Controllers;

use App\Http\Controllers\DashboardController;
use Modules\NsManufacturing\Crud\BomCrud;
use Modules\NsManufacturing\Crud\BomItemCrud;
use Modules\NsManufacturing\Crud\ProductionOrderCrud;
use Modules\NsManufacturing\Models\ManufacturingBom;
use Modules\NsManufacturing\Models\ManufacturingOrder;
use Modules\NsManufacturing\Models\ManufacturingBomItem;
use Modules\NsManufacturing\Services\ProductionService;
use Illuminate\Http\Request;

class ManufacturingController extends DashboardController
{
    public function __construct(
        protected ProductionService $productionService,
        protected \Modules\NsManufacturing\Services\AnalyticsService $analyticsService,
        protected \App\Services\DateService $dateService
    ) {
        parent::__construct($dateService);
    }

    /**
     * Display the BOMs list view.
     *
     * @return \Illuminate\View\View
     */
    public function boms(): \Illuminate\View\View
    {
        return BomCrud::table();
    }

    /**
     * Display the create BOM form.
     *
     * @return \Illuminate\View\View
     */
    public function createBom(): \Illuminate\View\View
    {
        return BomCrud::form();
    }

    /**
     * Display the edit BOM form.
     *
     * @param int $id The BOM ID
     * @return \Illuminate\View\View
     */
    public function editBom($id): \Illuminate\View\View
    {
        return BomCrud::form(ManufacturingBom::findOrFail($id));
    }

    /**
     * Display the BOM explosion view.
     *
     * @param int $id The BOM ID
     * @return \Illuminate\View\View
     */
    public function explodeBom($id): \Illuminate\View\View
    {
        $bom = ManufacturingBom::with('items.product.unit')->findOrFail($id);

        return view('ns-manufacturing::boms.explode', compact('bom'));
    }

    /**
     * Display the BOM items list view.
     *
     * @return \Illuminate\View\View
     */
    public function bomItems(): \Illuminate\View\View
    {
        return BomItemCrud::table();
    }

    /**
     * Display the create BOM item form.
     *
     * @return \Illuminate\View\View
     */
    public function createBomItem(): \Illuminate\View\View
    {
        return BomItemCrud::form();
    }

    /**
     * Display the edit BOM item form.
     *
     * @param int $id The BOM item ID
     * @return \Illuminate\View\View
     */
    public function editBomItem($id): \Illuminate\View\View
    {
        return BomItemCrud::form(ManufacturingBomItem::findOrFail($id));
    }

    /**
     * Display the manufacturing orders list view.
     *
     * @return \Illuminate\View\View
     */
    public function orders(): \Illuminate\View\View
    {
        return ProductionOrderCrud::table();
    }

    /**
     * Display the create manufacturing order form.
     *
     * @return \Illuminate\View\View
     */
    public function createOrder(): \Illuminate\View\View
    {
        return ProductionOrderCrud::form();
    }

    /**
     * Display the edit manufacturing order form.
     *
     * @param int $id The order ID
     * @return \Illuminate\View\View
     */
    public function editOrder($id): \Illuminate\View\View
    {
        return ProductionOrderCrud::form(ManufacturingOrder::findOrFail($id));
    }

    /**
     * Start a manufacturing order.
     *
     * @param int $id The order ID
     * @return \Illuminate\Http\JsonResponse
     */
    public function startOrder($id): \Illuminate\Http\JsonResponse
    {
        ns()->restrict('nexopos.start.manufacturing-orders');

        try {
            $order = ManufacturingOrder::findOrFail($id);
            $this->productionService->startOrder($order);

            return response()->json([
                'status' => 'success',
                'message' => __('Order started successfully.'),
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('Order not found.'),
            ], 404);
        } catch (\Exception $e) {
            \Log::error('Failed to start order: '.$e->getMessage(), [
                'order_id' => $id,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => __('Failed to start order. Please try again.'),
            ], 500);
        }
    }

    /**
     * Complete a manufacturing order.
     *
     * @param int $id The order ID
     * @return \Illuminate\Http\JsonResponse
     */
    public function completeOrder($id): \Illuminate\Http\JsonResponse
    {
        ns()->restrict('nexopos.complete.manufacturing-orders');

        try {
            $order = ManufacturingOrder::findOrFail($id);
            $this->productionService->completeOrder($order);

            return response()->json([
                'status' => 'success',
                'message' => __('Order completed successfully.'),
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('Order not found.'),
            ], 404);
        } catch (\Exception $e) {
            \Log::error('Failed to complete order: '.$e->getMessage(), [
                'order_id' => $id,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => __('Failed to complete order. Please try again.'),
            ], 500);
        }
    }

    /**
     * Display the manufacturing reports view.
     *
     * @return \Illuminate\View\View
     */
    public function reports(): \Illuminate\View\View
    {
        return view('ns-manufacturing::reports.index');
    }
}
