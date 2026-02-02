<?php

namespace Modules\NsContainerManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\NsContainerManagement\Services\ContainerLedgerService;
use Modules\NsContainerManagement\Services\ContainerService;

class ProductContainerController extends Controller
{
    public function __construct(
        protected ContainerService $containerService,
        protected ContainerLedgerService $ledgerService
    ) {}

    /**
     * GET /api/container-management/products/{productId}/container
     */
    public function show(Request $request, int $productId): JsonResponse
    {
        $unitId = $request->query('unit_id') ? (int) $request->query('unit_id') : null;
        $productContainer = $this->containerService->getProductContainer($productId, $unitId);

        if (!$productContainer) {
            return response()->json([
                'status' => 'success',
                'data' => null,
            ]);
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'product_id' => $productContainer->product_id,
                'unit_id' => $productContainer->unit_id,
                'container_type_id' => $productContainer->container_type_id,
                'container_type' => $productContainer->containerType,
                'is_enabled' => $productContainer->is_enabled,
            ],
        ]);
    }

    /**
     * POST /api/container-management/products/{productId}/container
     */
    public function store(Request $request, int $productId): JsonResponse
    {
        $validated = $request->validate([
            'container_type_id' => 'required|exists:ns_container_types,id',
            'unit_id' => 'nullable|integer',
        ]);

        $productContainer = $this->containerService->linkProductToContainer(
            $productId,
            $validated['container_type_id'],
            $validated['unit_id'] ?? null
        );

        return response()->json([
            'status' => 'success',
            'message' => __('Product unit linked to container type'),
            'data' => $productContainer->load('containerType'),
        ], 201);
    }

    /**
     * DELETE /api/container-management/products/{productId}/container
     */
    public function destroy(Request $request, int $productId): JsonResponse
    {
        $unitId = $request->query('unit_id') ? (int) $request->query('unit_id') : null;
        $deleted = $this->containerService->unlinkProductFromContainer($productId, $unitId);

        return response()->json([
            'status' => 'success',
            'message' => $deleted
                ? __('Product unit unlinked from container type')
                : __('Product unit was not linked to any container'),
        ]);
    }

    /**
     * POST /api/container-management/products/calculate
     * Calculate containers for multiple products (for POS display)
     */
    public function calculate(Request $request): JsonResponse
    {
        $request->validate([
            'products' => 'required|array',
            'products.*.product_id' => 'required|integer',
            'products.*.quantity' => 'required|numeric|min:0',
            'products.*.unit_id' => 'nullable|integer',
        ]);

        $results = [];
        foreach ($request->input('products') as $item) {
            $calculation = $this->ledgerService->calculateContainersForProduct(
                (int) $item['product_id'],
                (float) $item['quantity'],
                isset($item['unit_id']) ? (int) $item['unit_id'] : null
            );

            if ($calculation) {
                $results[] = array_merge(['product_id' => $item['product_id']], $calculation);
            }
        }

        return response()->json($results);
    }
}
