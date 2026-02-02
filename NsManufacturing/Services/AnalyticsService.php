<?php

namespace Modules\NsManufacturing\Services;

use Modules\NsManufacturing\Models\ManufacturingOrder;
use Modules\NsManufacturing\Models\ManufacturingStockMovement;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AnalyticsService
{
    public function __construct(
        protected BomService $bomService
    ) {}

    /**
     * Get summary analytics for manufacturing.
     *
     * @param Carbon|null $from Start date filter
     * @param Carbon|null $to End date filter
     * @return array
     */
    public function getSummary(?Carbon $from = null, ?Carbon $to = null): array
    {
        $completedOrders = ManufacturingOrder::with('bom')
            ->where('status', ManufacturingOrder::STATUS_COMPLETED)
            ->get();
        $totalValue = 0;

        foreach ($completedOrders as $order) {
            $unitCost = $order->bom ? $this->bomService->calculateEstimatedCost($order->bom) : 0;
            $totalValue += $unitCost * $order->quantity;
        }

        return [
            'total_orders' => ManufacturingOrder::count(),
            'completed' => $completedOrders->count(),
            'pending' => ManufacturingOrder::whereIn('status', [
                ManufacturingOrder::STATUS_DRAFT,
                ManufacturingOrder::STATUS_PLANNED,
                ManufacturingOrder::STATUS_IN_PROGRESS
            ])->count(),
            'total_production_value' => ns()->currency->define($totalValue)->format(),
            'top_products' => $this->getTopProducts(),
            'recent_orders' => $this->getRecentOrders()
        ];
    }

    protected function getTopProducts()
    {
        return ManufacturingOrder::where('status', ManufacturingOrder::STATUS_COMPLETED)
            ->select('product_id', DB::raw('SUM(quantity) as total_qty'))
            ->with('product')
            ->groupBy('product_id')
            ->orderBy('total_qty', 'desc')
            ->limit(5)
            ->get()
            ->map(function($order) {
                return [
                    'name' => $order->product ? $order->product->name : __('Unknown'),
                    'quantity' => $order->total_qty
                ];
            });
    }

    protected function getRecentOrders()
    {
        return ManufacturingOrder::orderBy('created_at', 'desc')
            ->with(['product', 'unit'])
            ->limit(5)
            ->get()
            ->map(function($order) {
                $unitCost = $order->bom ? $this->bomService->calculateEstimatedCost($order->bom) : 0;

                return [
                    'code' => $order->code,
                    'product_name' => $order->product ? $order->product->name : __('Unknown'),
                    'unit_name' => $order->unit ? $order->unit->name : '',
                    'quantity' => $order->quantity,
                    'status' => ucwords(str_replace('_', ' ', $order->status)),
                    'value' => ns()->currency->define($unitCost * $order->quantity)->format(),
                    'created_at' => $order->created_at->diffForHumans()
                ];
            });
    }

    /**
     * Get BOM usage statistics.
     *
     * @param int $bomId The BOM ID
     * @return array
     */
    public function getBomUsage(int $bomId): array
    {
        $orders = ManufacturingOrder::where('bom_id', $bomId)->get();
        $completed = $orders->where('status', ManufacturingOrder::STATUS_COMPLETED);

        return [
            'total_orders' => $orders->count(),
            'completed_orders' => $completed->count(),
            'total_quantity_produced' => $completed->sum('quantity'),
        ];
    }
}
