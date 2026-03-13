<?php

namespace Modules\MobileApi\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductHistory;
use App\Models\ProductUnitQuantity;
use App\Services\ProductService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MobileInventoryController extends Controller
{
    public function __construct(
        protected ProductService $productService
    ) {
    }

    /**
     * Create a simple stock adjustment for mobile online/offline replay flows.
     *
     * POST /api/mobile/inventory/adjust
     */
    public function adjust(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|integer|exists:nexopos_products,id',
            'unit_quantity_id' => 'nullable|integer|exists:nexopos_products_unit_quantities,id',
            'adjustment_type' => 'nullable|string|in:add,remove',
            'operation_type' => 'nullable|string|in:set,added,deleted,defective,lost,removed',
            'quantity' => 'required|numeric|min:0.000001',
            'reason' => 'nullable|string|max:500',
            'reference' => 'nullable|string|max:255',
        ])->after(function ($validator) use ($request) {
            $hasAdjustmentType = filled($request->input('adjustment_type'));
            $hasOperationType = filled($request->input('operation_type'));

            if (!$hasAdjustmentType && !$hasOperationType) {
                $validator->errors()->add(
                    'operation_type',
                    __('Either an adjustment type or an operation type is required.')
                );
            }
        });

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => __('The stock adjustment request is not valid.'),
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        $product = Product::findOrFail($validated['product_id']);
        $unitQuantity = $this->resolveUnitQuantity(
            productId: $product->id,
            unitQuantityId: $validated['unit_quantity_id'] ?? null
        );

        if (!$unitQuantity instanceof ProductUnitQuantity) {
            return response()->json([
                'status' => 'error',
                'message' => __('No unit quantity could be resolved for this product.'),
            ], 422);
        }

        $action = $this->resolveAction($validated);

        $history = $this->productService->stockAdjustment($action, [
            'product_id' => $product->id,
            'unit_id' => $unitQuantity->unit_id,
            'unit_price' => (float) $unitQuantity->sale_price,
                'quantity' => (float) $validated['quantity'],
                'description' => $this->buildDescription(
                    reason: $validated['reason'] ?? null,
                    reference: $validated['reference'] ?? null
                ),
                'author' => $request->user()?->id ?? 0,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => __('The stock adjustment has been created successfully.'),
            'data' => [
                'id' => $history->id,
                'product_id' => $history->product_id,
                'previous_quantity' => (float) $history->before_quantity,
                'new_quantity' => (float) $history->after_quantity,
                'adjustment' => (float) $history->quantity,
                'created_at' => $history->created_at?->format('Y-m-d H:i:s'),
            ],
        ]);
    }

    /**
     * Read stock adjustment history for online-only inventory screens.
     *
     * GET /api/mobile/inventory/history
     */
    public function history(Request $request)
    {
        $limit = max(1, min((int) $request->query('limit', 50), 100));
        $offset = max(0, (int) $request->query('offset', 0));
        $productId = $request->query('product_id');

        $query = ProductHistory::query()
            ->with(['product', 'unit'])
            ->orderByDesc('id');

        if ($productId !== null && $productId !== '') {
            $query->where('product_id', (int) $productId);
        }

        $total = (clone $query)->count();
        $rows = $query
            ->offset($offset)
            ->limit($limit + 1)
            ->get();

        $hasMore = $rows->count() > $limit;
        $data = $rows->take($limit)->values()->map(fn($history) => [
            'id' => $history->id,
            'product_id' => $history->product_id,
            'product_name' => $history->product?->name,
            'operation' => $history->operation_type,
            'quantity' => (float) $history->quantity,
            'unit_name' => $history->unit?->name,
            'reason' => $history->description,
            'reference' => null,
            'created_at' => $history->created_at?->format('Y-m-d H:i:s'),
            'created_by' => $history->author ? (string) $history->author : null,
        ]);

        return response()->json([
            'data' => $data,
            'meta' => [
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset,
                'has_more' => $hasMore,
            ],
        ]);
    }

    private function resolveUnitQuantity(int $productId, ?int $unitQuantityId): ?ProductUnitQuantity
    {
        if ($unitQuantityId !== null) {
            return ProductUnitQuantity::query()
                ->where('product_id', $productId)
                ->where('id', $unitQuantityId)
                ->first();
        }

        return ProductUnitQuantity::query()
            ->where('product_id', $productId)
            ->orderBy('id')
            ->first();
    }

    private function buildDescription(?string $reason, ?string $reference): string
    {
        $parts = array_values(array_filter([$reason, $reference]));

        return implode(' | ', $parts);
    }

    private function resolveAction(array $validated): string
    {
        if (!empty($validated['operation_type'])) {
            return match ($validated['operation_type']) {
                'set' => ProductHistory::ACTION_SET,
                'added' => ProductHistory::ACTION_ADDED,
                'deleted' => ProductHistory::ACTION_DELETED,
                'defective' => ProductHistory::ACTION_DEFECTIVE,
                'lost' => ProductHistory::ACTION_LOST,
                'removed' => ProductHistory::ACTION_REMOVED,
                default => throw new \InvalidArgumentException(__('Unsupported stock operation')),
            };
        }

        return ($validated['adjustment_type'] ?? null) === 'add'
            ? ProductHistory::ACTION_ADDED
            : ProductHistory::ACTION_REMOVED;
    }
}
