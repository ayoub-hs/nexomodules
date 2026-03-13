<?php

namespace Modules\NsContainerManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\NsContainerManagement\Http\Requests\CreateContainerTypeRequest;
use Modules\NsContainerManagement\Models\ContainerType;
use Modules\NsContainerManagement\Services\ContainerService;

class ContainerTypeController extends Controller
{
    public function __construct(
        protected ContainerService $containerService
    ) {}

    /**
     * GET /api/container-management/types
     */
    public function index(Request $request): JsonResponse
    {
        $query = ContainerType::with('inventory')
            ->when($request->boolean('active_only'), fn ($q) => $q->active())
            ->orderBy('name');

        $types = $request->has('per_page')
            ? $query->paginate($request->integer('per_page', 15))
            : $query->get();

        return response()->json([
            'status' => 'success',
            'data' => $types,
        ]);
    }

    /**
     * POST /api/container-management/types
     */
    public function store(CreateContainerTypeRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $containerType = $this->containerService->createContainerType($validated);

        return response()->json([
            'status' => 'success',
            'message' => __('Container type created successfully'),
            'data' => $containerType,
        ], 201);
    }

    /**
     * GET /api/container-management/types/{id}
     */
    public function show(int $id): JsonResponse
    {
        $containerType = ContainerType::with(['inventory', 'customerBalances'])->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $containerType,
        ]);
    }

    /**
     * PUT /api/container-management/types/{id}
     */
    public function update(CreateContainerTypeRequest $request, int $id): JsonResponse
    {
        $validated = $request->validated();

        $containerType = $this->containerService->updateContainerType($id, $validated);

        return response()->json([
            'status' => 'success',
            'message' => __('Container type updated successfully'),
            'data' => $containerType,
        ]);
    }

    /**
     * DELETE /api/container-management/types/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $containerType = ContainerType::findOrFail($id);
        
        // Soft delete by deactivating
        $containerType->update(['is_active' => false]);

        return response()->json([
            'status' => 'success',
            'message' => __('Container type deactivated successfully'),
        ]);
    }

    /**
     * GET /api/container-management/types/dropdown
     */
    public function dropdown(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => $this->containerService->getContainerTypesDropdown(),
        ]);
    }
}
