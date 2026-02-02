<?php

namespace Modules\NsManufacturing\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\NsManufacturing\Models\ManufacturingOrder;
use Modules\NsManufacturing\Models\ManufacturingBom;
use Modules\NsManufacturing\Services\BomService;
use Modules\NsManufacturing\Services\AnalyticsService;

class ManufacturingReportController extends Controller
{
    public function __construct(
        protected BomService $bomService,
        protected AnalyticsService $analyticsService
    ) {}

    /**
     * Display the manufacturing reports index.
     *
     * @return \Illuminate\View\View
     */
    public function index(): \Illuminate\View\View
    {
        return view('ns-manufacturing::reports.index');
    }

    /**
     * Get summary metrics for the dashboard.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSummary(Request $request): \Illuminate\Http\JsonResponse
    {
        ns()->restrict('nexopos.view.manufacturing-costs');

        // Add validation
        $validated = $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
            'status' => 'nullable|string|in:planned,in_progress,completed,cancelled',
            'product_id' => 'nullable|integer|exists:nexopos_products,id',
        ]);

        $query = ManufacturingOrder::with('bom');

        if (! empty($validated['from'])) {
            $query->whereDate('created_at', '>=', $validated['from']);
        }
        if (! empty($validated['to'])) {
            $query->whereDate('created_at', '<=', $validated['to']);
        }

        $orders = $query->get();
        $completed = $orders->where('status', ManufacturingOrder::STATUS_COMPLETED);
        
        $totalValue = 0;
        foreach ($completed as $order) {
            $unitCost = $order->bom ? $this->bomService->calculateEstimatedCost($order->bom) : 0;
            $totalValue += $unitCost * $order->quantity;
        }

        return response()->json([
            'total_orders' => $orders->count(),
            'completed_orders' => $completed->count(),
            'pending_orders' => $orders->where('status', '!=', ManufacturingOrder::STATUS_COMPLETED)->count(),
            'total_value' => (float) $totalValue,
            'total_value_formatted' => ns()->currency->define($totalValue)->format(),
        ]);
    }

    /**
     * Get detailed production history.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getHistory(Request $request): \Illuminate\Http\JsonResponse
    {
        ns()->restrict('nexopos.view.manufacturing-costs');

        // Add validation
        $validated = $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
            'status' => 'nullable|string|in:planned,in_progress,completed,cancelled',
            'product_id' => 'nullable|integer|exists:nexopos_products,id',
            'per_page' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
        ]);

        $query = ManufacturingOrder::with(['product', 'unit', 'bom'])
            ->orderBy('created_at', 'desc');

        if (! empty($validated['from'])) {
            $query->whereDate('created_at', '>=', $validated['from']);
        }
        if (! empty($validated['to'])) {
            $query->whereDate('created_at', '<=', $validated['to']);
        }
        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }
        if (! empty($validated['product_id'])) {
            $query->where('product_id', $validated['product_id']);
        }

        $perPage = $validated['per_page'] ?? 15;
        $page = $validated['page'] ?? 1;

        $orders = $query->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'data' => $orders->getCollection()->map(function($order) {
                $unitCost = $order->bom ? $this->bomService->calculateEstimatedCost($order->bom) : 0;
                return [
                    'id' => $order->id,
                    'code' => $order->code,
                    'date' => $order->created_at->format('Y-m-d H:i'),
                    'product' => ($order->product->name ?? __('Unknown')) . ' (' . ($order->unit->name ?? '') . ')',
                    'quantity' => $order->quantity,
                    'status' => ucwords(str_replace('_', ' ', $order->status)),
                    'status_raw' => $order->status,
                    'value' => ns()->currency->define($unitCost * $order->quantity)->format(),
                ];
            }),
            'total' => $orders->total(),
            'last_page' => $orders->lastPage(),
            'current_page' => $orders->currentPage(),
        ]);
    }

    /**
     * Get ingredient consumption report.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getConsumption(Request $request): \Illuminate\Http\JsonResponse
    {
        ns()->restrict('nexopos.view.manufacturing-costs');

        // Add validation
        $validated = $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
        ]);

        // This query joins production orders -> ingredients (via BOM items)
        // to aggregate total consumption with COGS data in a single query
        $query = DB::table('ns_manufacturing_orders as o')
            ->join('ns_manufacturing_bom_items as i', 'o.bom_id', '=', 'i.bom_id')
            ->join('nexopos_products as p', 'p.id', '=', 'i.product_id')
            ->leftJoin('nexopos_units as u', 'u.id', '=', 'i.unit_id')
            ->leftJoin('nexopos_products_unit_quantities as puq', function ($join) {
                $join->on('puq.product_id', '=', 'p.id')
                     ->on('puq.unit_id', '=', 'u.id');
            })
            ->where('o.status', ManufacturingOrder::STATUS_COMPLETED)
            ->select(
                'p.name as ingredient_name',
                'u.name as unit_name',
                'p.id as product_id',
                'puq.cogs',
                DB::raw('SUM(i.quantity * o.quantity) as total_quantity')
            )
            ->groupBy('p.id', 'p.name', 'u.name', 'puq.cogs');

        if (! empty($validated['from'])) {
            $query->whereDate('o.completed_at', '>=', $validated['from']);
        }
        if (! empty($validated['to'])) {
            $query->whereDate('o.completed_at', '<=', $validated['to']);
        }

        $consumption = $query->get();

        return response()->json([
            'data' => $consumption->map(function ($item) {
                return [
                    'ingredient' => $item->ingredient_name,
                    'unit' => $item->unit_name,
                    'quantity' => (float) $item->total_quantity,
                    'total_cost' => ns()->currency->define($item->total_quantity * ($item->cogs ?? 0))->format(),
                ];
            }),
        ]);
    }

    /**
     * Get filter options for the report.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getFilters(): \Illuminate\Http\JsonResponse
    {
        ns()->restrict('nexopos.view.manufacturing-costs');

        return response()->json([
            'products' => \App\Models\Product::where('status', 'available')->limit(100)->get(['id', 'name']),
            'statuses' => [
                ['value' => 'draft', 'label' => __('Draft')],
                ['value' => 'planned', 'label' => __('Planned')],
                ['value' => 'in_progress', 'label' => __('In Progress')],
                ['value' => 'completed', 'label' => __('Completed')],
            ]
        ]);
    }
}
