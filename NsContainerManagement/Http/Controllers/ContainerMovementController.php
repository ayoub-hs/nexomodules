<?php

namespace Modules\NsContainerManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\NsContainerManagement\Models\ContainerMovement;
use Modules\NsContainerManagement\Services\ContainerLedgerService;

class ContainerMovementController extends Controller
{
    public function __construct(
        protected ContainerLedgerService $ledgerService
    ) {}

    /**
     * POST /api/container-management/give
     * Record containers going OUT to customer
     */
    public function give(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:nexopos_users,id',
            'container_type_id' => 'required|exists:ns_container_types,id',
            'quantity' => 'required|integer|min:1',
            'note' => 'nullable|string',
        ]);

        $movement = $this->ledgerService->recordContainerOut(
            customerId: $validated['customer_id'],
            containerTypeId: $validated['container_type_id'],
            quantity: $validated['quantity'],
            orderId: null,
            sourceType: 'manual_give',
            note: $validated['note'] ?? null
        );

        return response()->json([
            'status' => 'success',
            'message' => __('Containers given to customer successfully'),
            'data' => $movement->load('containerType'),
        ], 201);
    }

    /**
     * POST /api/container-management/receive
     * Record containers coming IN from customer (return)
     */
    public function receive(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:nexopos_users,id',
            'container_type_id' => 'required|exists:ns_container_types,id',
            'quantity' => 'required|integer|min:1',
            'note' => 'nullable|string',
        ]);

        $movement = $this->ledgerService->recordContainerIn(
            customerId: $validated['customer_id'],
            containerTypeId: $validated['container_type_id'],
            quantity: $validated['quantity'],
            sourceType: ContainerMovement::SOURCE_MANUAL_RETURN,
            note: $validated['note'] ?? null
        );

        return response()->json([
            'status' => 'success',
            'message' => __('Containers received from customer successfully'),
            'data' => $movement->load('containerType'),
        ], 201);
    }

    /**
     * GET /api/container-management/movements
     */
    public function index(Request $request): JsonResponse
    {
        $query = ContainerMovement::with(['containerType', 'customer', 'order'])
            ->orderByDesc('created_at');

        if ($request->has('customer_id')) {
            $query->where('customer_id', $request->integer('customer_id'));
        }

        if ($request->has('container_type_id')) {
            $query->where('container_type_id', $request->integer('container_type_id'));
        }

        if ($request->has('direction')) {
            $query->where('direction', $request->string('direction'));
        }

        if ($request->has('from_date')) {
            $query->where('created_at', '>=', $request->date('from_date'));
        }

        if ($request->has('to_date')) {
            $query->where('created_at', '<=', $request->date('to_date')->endOfDay());
        }

        $movements = $query->paginate($request->integer('per_page', 20));

        return response()->json([
            'status' => 'success',
            'data' => $movements,
        ]);
    }

    /**
     * GET /api/container-management/movements/{id}
     */
    public function show(int $id): JsonResponse
    {
        $movement = ContainerMovement::with(['containerType', 'customer', 'order'])->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $movement,
        ]);
    }
}
