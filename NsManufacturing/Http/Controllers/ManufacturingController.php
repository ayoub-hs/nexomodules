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
use Illuminate\Support\Str;

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
        $bom = ManufacturingBom::with('items.product')->findOrFail($id);

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

    // ============================================
    // Mobile API Methods
    // ============================================

    /**
     * GET /api/mobile/manufacturing/orders
     * List all production orders with pagination (mobile optimized)
     */
    public function mobileIndex(Request $request): \Illuminate\Http\JsonResponse
    {
        $limit = min((int) $request->input('limit', 20), 100);
        $status = $request->input('status');
        $cursor = $request->input('cursor');
        $direction = $request->input('direction', 'before');

        $query = ManufacturingOrder::with([
            'bom:id,name',
            'product:id,name,sku',
            'unit:id,name',
            'authorUser:id,username,first_name,last_name'
        ])
        ->select([
            'id',
            'code',
            'bom_id',
            'product_id',
            'unit_id',
            'quantity',
            'status',
            'started_at',
            'completed_at',
            'author',
            'created_at',
            'updated_at',
        ]);

        if ($status) {
            $query->where('status', $status);
        }

        if ($cursor) {
            if ($direction === 'after') {
                $query->where('id', '>', $cursor);
            } else {
                $query->where('id', '<', $cursor);
            }
        }

        if ($direction === 'after') {
            $query->orderBy('id', 'asc');
        } else {
            $query->orderBy('id', 'desc');
        }

        $orders = $query->limit($limit + 1)->get();
        $hasMore = $orders->count() > $limit;
        if ($hasMore) {
            $orders = $orders->take($limit);
        }

        $transformedOrders = $orders->map(fn ($order) => $this->transformOrder($order));
        $nextCursor = $orders->isNotEmpty() ? $orders->last()->id : null;
        $prevCursor = $orders->isNotEmpty() ? $orders->first()->id : null;

        return response()->json([
            'data' => $transformedOrders,
            'meta' => [
                'has_more' => $hasMore,
                'next_cursor' => $hasMore ? $nextCursor : null,
                'prev_cursor' => $prevCursor,
                'limit' => $limit,
            ],
        ]);
    }

    /**
     * GET /api/mobile/manufacturing/orders/{id}
     * Show single production order with full details (mobile optimized)
     */
    public function mobileShow(Request $request, $id): \Illuminate\Http\JsonResponse
    {
        $order = ManufacturingOrder::with([
            'bom.items.product',
            'bom.product',
            'product',
            'unit',
            'authorUser:id,username,first_name,last_name',
            'movements'
        ])->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $this->transformOrder($order, true),
        ]);
    }

    /**
     * POST /api/mobile/manufacturing/orders
     * Create new production order (mobile optimized)
     */
    public function mobileStore(Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'bom_id' => 'required|exists:ns_manufacturing_boms,id',
            'product_id' => 'required|exists:nexopos_products,id',
            'unit_id' => 'required|exists:nexopos_units,id',
            'quantity' => 'required|numeric|min:0.001',
        ]);

        try {
            $order = ManufacturingOrder::create([
                'code' => $this->generateProductionOrderCode(),
                'bom_id' => $validated['bom_id'],
                'product_id' => $validated['product_id'],
                'unit_id' => $validated['unit_id'],
                'quantity' => $validated['quantity'],
                'status' => ManufacturingOrder::STATUS_PLANNED,
                'author' => auth()->id() ?? 0,
            ]);

            $order->load([
                'bom:id,name',
                'product:id,name,sku',
                'unit:id,name',
                'authorUser:id,username,first_name,last_name'
            ]);

            return response()->json([
                'status' => 'success',
                'message' => __('Production order created successfully.'),
                'data' => $this->transformOrder($order),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('Failed to create production order.'),
            ], 500);
        }
    }

    /**
     * PUT /api/mobile/manufacturing/orders/{id}/start
     * Start production (mobile optimized)
     */
    public function mobileStart(Request $request, $id): \Illuminate\Http\JsonResponse
    {
        try {
            $order = ManufacturingOrder::findOrFail($id);

            if ($order->status !== ManufacturingOrder::STATUS_PLANNED &&
                $order->status !== ManufacturingOrder::STATUS_DRAFT) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('Order cannot be started. Current status: ') . $order->status,
                ], 400);
            }

            $this->productionService->startOrder($order);

            $order->refresh();
            $order->load([
                'bom:id,name',
                'product:id,name,sku',
                'unit:id,name',
            ]);

            return response()->json([
                'status' => 'success',
                'message' => __('Production started successfully.'),
                'data' => $this->transformOrder($order),
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('Order not found.'),
            ], 404);
        } catch (\Exception $e) {
            \Log::error('Failed to start order: ' . $e->getMessage(), [
                'order_id' => $id,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => __('Failed to start production. Please try again.'),
            ], 500);
        }
    }

    private function generateProductionOrderCode(): string
    {
        do {
            $code = 'MPO-' . now()->format('Ymd-His') . '-' . Str::upper(Str::random(4));
        } while (ManufacturingOrder::where('code', $code)->exists());

        return $code;
    }

    /**
     * PUT /api/mobile/manufacturing/orders/{id}/complete
     * Complete production (mobile optimized)
     */
    public function mobileComplete(Request $request, $id): \Illuminate\Http\JsonResponse
    {
        try {
            $order = ManufacturingOrder::findOrFail($id);

            if ($order->status !== ManufacturingOrder::STATUS_IN_PROGRESS) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('Order cannot be completed. Current status: ') . $order->status,
                ], 400);
            }

            $this->productionService->completeOrder($order);

            $order->refresh();
            $order->load([
                'bom:id,name',
                'product:id,name,sku',
                'unit:id,name',
            ]);

            return response()->json([
                'status' => 'success',
                'message' => __('Production completed successfully.'),
                'data' => $this->transformOrder($order),
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('Order not found.'),
            ], 404);
        } catch (\Exception $e) {
            \Log::error('Failed to complete order: ' . $e->getMessage(), [
                'order_id' => $id,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => __('Failed to complete production. Please try again.'),
            ], 500);
        }
    }

    /**
     * GET /api/mobile/manufacturing/boms
     * List all Bill of Materials (mobile optimized)
     */
    public function mobileBoms(Request $request): \Illuminate\Http\JsonResponse
    {
        \Log::debug('[Manufacturing] mobileBoms called', [
            'user_id' => $request->user()?->id,
            'params' => $request->all(),
        ]);

        $limit = min((int) $request->input('limit', 20), 100);
        $activeOnly = $request->boolean('active_only', false);
        $cursor = $request->input('cursor');
        $direction = $request->input('direction', 'before');

        \Log::debug('[Manufacturing] mobileBoms query params', [
            'limit' => $limit,
            'activeOnly' => $activeOnly,
            'cursor' => $cursor,
            'direction' => $direction,
        ]);

        $query = ManufacturingBom::with([
            'product:id,name,sku',
            'unit:id,name',
            'authorUser:id,username,first_name,last_name'
        ])
        ->select([
            'id',
            'uuid',
            'name',
            'product_id',
            'unit_id',
            'quantity',
            'is_active',
            'description',
            'author',
            'created_at',
            'updated_at',
        ])
        ->withCount('items');

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        if ($cursor) {
            if ($direction === 'after') {
                $query->where('id', '>', $cursor);
            } else {
                $query->where('id', '<', $cursor);
            }
        }

        if ($direction === 'after') {
            $query->orderBy('id', 'asc');
        } else {
            $query->orderBy('id', 'desc');
        }

        $boms = $query->limit($limit + 1)->get();
        $hasMore = $boms->count() > $limit;
        if ($hasMore) {
            $boms = $boms->take($limit);
        }

        \Log::debug('[Manufacturing] mobileBoms query executed', [
            'boms_count' => $boms->count(),
            'has_more' => $hasMore,
        ]);

        $transformedBoms = $boms->map(fn ($bom) => $this->transformBom($bom));
        $nextCursor = $boms->isNotEmpty() ? $boms->last()->id : null;
        $prevCursor = $boms->isNotEmpty() ? $boms->first()->id : null;

        \Log::debug('[Manufacturing] mobileBoms response prepared', [
            'transformed_count' => $transformedBoms->count(),
            'next_cursor' => $hasMore ? $nextCursor : null,
            'prev_cursor' => $prevCursor,
        ]);

        return response()->json([
            'data' => $transformedBoms,
            'meta' => [
                'has_more' => $hasMore,
                'next_cursor' => $hasMore ? $nextCursor : null,
                'prev_cursor' => $prevCursor,
                'limit' => $limit,
            ],
        ]);
    }

    /**
     * GET /api/mobile/manufacturing/boms/{id}
     * Show BOM with items (mobile optimized)
     */
    public function mobileBomShow(Request $request, $id): \Illuminate\Http\JsonResponse
    {
        $bom = ManufacturingBom::with([
            'items.product',
            'product',
            'unit',
            'authorUser:id,username,first_name,last_name'
        ])->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $this->transformBom($bom, true),
        ]);
    }

    /**
     * POST /api/mobile/manufacturing/boms
     * Create a BOM (mobile).
     */
    public function mobileBomStore(Request $request): \Illuminate\Http\JsonResponse
    {
        ns()->restrict('nexopos.create.manufacturing-recipes');

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'product_id' => 'required|exists:nexopos_products,id',
            'unit_id' => 'required|exists:nexopos_units,id',
            'quantity' => 'required|numeric|min:0.000001',
            'is_active' => 'nullable|boolean',
            'description' => 'nullable|string',
        ]);

        try {
            $bom = ManufacturingBom::create([
                'uuid' => (string) Str::uuid(),
                'name' => $data['name'],
                'product_id' => $data['product_id'],
                'unit_id' => $data['unit_id'],
                'quantity' => $data['quantity'],
                'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true,
                'description' => $data['description'] ?? null,
                'author' => $request->user()?->id ?? auth()->id(),
            ]);

            $bom->load([
                'product:id,name,sku',
                'unit:id,name',
                'authorUser:id,username,first_name,last_name',
            ]);

            return response()->json([
                'status' => 'success',
                'message' => __('BOM created successfully.'),
                'data' => $this->transformBom($bom, true),
            ], 201);
        } catch (\Throwable $e) {
            \Log::error('Failed to create BOM (mobile): ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => __('Failed to create BOM. Please try again.'),
            ], 500);
        }
    }

    /**
     * Transform order for mobile API response
     */
    private function transformOrder(ManufacturingOrder $order, bool $includeDetails = false): array
    {
        $data = [
            'id' => $order->id,
            'code' => $order->code,
            'bom_id' => $order->bom_id,
            'bom' => $order->bom ? [
                'id' => $order->bom->id,
                'name' => $order->bom->name,
            ] : null,
            'product_id' => $order->product_id,
            'product' => $order->product ? [
                'id' => $order->product->id,
                'name' => $order->product->name,
                'sku' => $order->product->sku,
            ] : null,
            'unit_id' => $order->unit_id,
            'unit' => $order->unit ? [
                'id' => $order->unit->id,
                'name' => $order->unit->name,
            ] : null,
            'quantity' => (float) $order->quantity,
            'status' => $order->status,
            'started_at' => $order->started_at?->toIso8601String(),
            'completed_at' => $order->completed_at?->toIso8601String(),
            'author' => $order->author,
            'author_user' => $order->authorUser ? [
                'id' => $order->authorUser->id,
                'username' => $order->authorUser->username,
                'first_name' => $order->authorUser->first_name,
                'last_name' => $order->authorUser->last_name,
            ] : null,
            'created_at' => $order->created_at->toIso8601String(),
            'updated_at' => $order->updated_at->toIso8601String(),
        ];

        if ($includeDetails && $order->bom) {
            $data['bom_items'] = $order->bom->items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'product' => $item->product ? [
                        'id' => $item->product->id,
                        'name' => $item->product->name,
                        'sku' => $item->product->sku,
                    ] : null,
                    'component_product_id' => null,
                    'component_product' => null,
                    'quantity' => (float) $item->quantity,
                    'unit_id' => $item->unit_id,
                    'unit' => $item->unit ? [
                        'id' => $item->unit->id,
                        'name' => $item->unit->name,
                    ] : null,
                ];
            })->toArray();
        }

        if ($includeDetails && $order->movements) {
            $data['movements'] = $order->movements->map(function ($movement) {
                return [
                    'id' => $movement->id,
                    'type' => $movement->type,
                    'quantity' => (float) $movement->quantity,
                    'created_at' => $movement->created_at->toIso8601String(),
                ];
            })->toArray();
        }

        return $data;
    }

    /**
     * Transform BOM for mobile API response
     */
    private function transformBom(ManufacturingBom $bom, bool $includeItems = false): array
    {
        $data = [
            'id' => $bom->id,
            'uuid' => $bom->uuid,
            'name' => $bom->name,
            'product_id' => $bom->product_id,
            'product' => $bom->product ? [
                'id' => $bom->product->id,
                'name' => $bom->product->name,
                'sku' => $bom->product->sku,
            ] : null,
            'unit_id' => $bom->unit_id,
            'unit' => $bom->unit ? [
                'id' => $bom->unit->id,
                'name' => $bom->unit->name,
            ] : null,
            'quantity' => (float) $bom->quantity,
            'is_active' => (bool) $bom->is_active,
            'description' => $bom->description,
            'items_count' => $bom->items_count ?? ($bom->relationLoaded('items') ? $bom->items->count() : 0),
            'author' => $bom->author,
            'author_user' => $bom->authorUser ? [
                'id' => $bom->authorUser->id,
                'username' => $bom->authorUser->username,
                'first_name' => $bom->authorUser->first_name,
                'last_name' => $bom->authorUser->last_name,
            ] : null,
            'created_at' => $bom->created_at->toIso8601String(),
            'updated_at' => $bom->updated_at->toIso8601String(),
        ];

        if ($includeItems) {
            $data['items'] = $bom->items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'product' => $item->product ? [
                        'id' => $item->product->id,
                        'name' => $item->product->name,
                        'sku' => $item->product->sku,
                    ] : null,
                    'component_product_id' => null,
                    'component_product' => null,
                    'quantity' => (float) $item->quantity,
                    'unit_id' => $item->unit_id,
                    'unit' => $item->unit ? [
                        'id' => $item->unit->id,
                        'name' => $item->unit->name,
                    ] : null,
                ];
            })->toArray();
        }

        return $data;
    }
}
