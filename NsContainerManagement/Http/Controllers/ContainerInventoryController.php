<?php

namespace Modules\NsContainerManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\NsContainerManagement\Models\ContainerInventory;
use Modules\NsContainerManagement\Models\ContainerMovement;
use Modules\NsContainerManagement\Services\ContainerService;

class ContainerInventoryController extends Controller
{
    public function __construct(
        protected ContainerService $containerService
    ) {}

    /**
     * GET /api/container-management/inventory
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => $this->containerService->getInventorySummary(),
        ]);
    }

    /**
     * GET /api/container-management/inventory/{typeId}
     */
    public function show(int $typeId): JsonResponse
    {
        $inventory = ContainerInventory::with('containerType')
            ->where('container_type_id', $typeId)
            ->firstOrFail();

        return response()->json([
            'status' => 'success',
            'data' => $inventory,
        ]);
    }

    /**
     * POST /api/container-management/inventory/adjust
     */
    public function adjust(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'container_type_id' => 'required|exists:ns_container_types,id',
            'adjustment' => 'required|integer',
            'reason' => 'required|string|max:255',
        ]);

        $inventory = $this->containerService->adjustInventory(
            $validated['container_type_id'],
            $validated['adjustment'],
            $validated['reason']
        );

        // Create adjustment movement for audit trail
        ContainerMovement::create([
            'container_type_id' => $validated['container_type_id'],
            'customer_id' => null,
            'order_id' => null,
            'direction' => ContainerMovement::DIRECTION_ADJUSTMENT,
            'quantity' => abs($validated['adjustment']),
            'unit_deposit_fee' => 0,
            'total_deposit_value' => 0,
            'source_type' => ContainerMovement::SOURCE_INVENTORY_ADJUSTMENT,
            'note' => $validated['reason'],
            'author' => auth()->id() ?? 0,
            'created_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => __('Inventory adjusted successfully'),
            'data' => $inventory,
        ]);
    }

    /**
     * GET /api/container-management/inventory/history
     */
    public function history(Request $request): JsonResponse
    {
        $query = ContainerMovement::with('containerType')
            ->where('direction', ContainerMovement::DIRECTION_ADJUSTMENT)
            ->orderByDesc('created_at');

        if ($request->has('container_type_id')) {
            $query->where('container_type_id', $request->integer('container_type_id'));
        }

        $history = $query->paginate($request->integer('per_page', 20));

        return response()->json([
            'status' => 'success',
            'data' => $history,
        ]);
    }
}
