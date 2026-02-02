<?php

namespace Modules\MobileApi\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductUnitQuantity;
use Illuminate\Http\Request;

/**
 * Mobile Product API Controller
 * 
 * Provides product endpoints optimized for mobile apps
 * with bundled unit quantities and efficient search.
 */
class MobileProductController extends Controller
{
    /**
     * Search products with full details including unit quantities
     * 
     * POST /api/mobile/products/search
     */
    public function search(Request $request)
    {
        $startTime = microtime(true);
        
        $searchTerm = $request->input('search', '');
        $categoryId = $request->input('arguments.category_id');
        $limit = min((int) $request->input('limit', 50), 100);

        if (strlen($searchTerm) < 2) {
            return response()->json([
                'results' => [],
                'total_count' => 0,
                'search_time_ms' => 0,
            ]);
        }

        // Escape LIKE wildcards to prevent wildcard injection
        $escapedSearchTerm = str_replace(['%', '_'], ['\%', '\_'], $searchTerm);

        $query = Product::with(['unit_quantities.unit'])
            ->onSale()
            ->excludeVariations()
            ->where(function ($q) use ($escapedSearchTerm) {
                $q->where('name', 'LIKE', "%{$escapedSearchTerm}%")
                    ->orWhere('barcode', 'LIKE', "%{$escapedSearchTerm}%")
                    ->orWhere('sku', 'LIKE', "%{$escapedSearchTerm}%");
            });

        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        $totalCount = $query->count();
        
        $products = $query
            ->select([
                'id', 'name', 'barcode', 'barcode_type', 'sku',
                'status', 'category_id', 'updated_at'
            ])
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->map(fn($product) => $this->transformProduct($product));

        $searchTime = round((microtime(true) - $startTime) * 1000);

        return response()->json([
            'results' => $products,
            'total_count' => $totalCount,
            'search_time_ms' => $searchTime,
        ]);
    }

    /**
     * Get single product with full details
     * 
     * GET /api/mobile/products/{id}
     */
    public function show(int $id)
    {
        $product = Product::with(['unit_quantities.unit'])->find($id);
        
        if (!$product) {
            return response()->json([
                'error' => 'Product not found',
            ], 404);
        }

        return response()->json($this->transformProduct($product));
    }

    /**
     * Search by barcode with full product details
     * 
     * GET /api/mobile/products/barcode/{barcode}
     */
    public function searchByBarcode(string $barcode)
    {
        // First check product barcode
        $product = Product::with(['unit_quantities.unit'])
            ->where('barcode', $barcode)
            ->onSale()
            ->first();

        if ($product) {
            return response()->json($this->transformProduct($product));
        }

        // Then check unit quantity barcode
        $unitQuantity = ProductUnitQuantity::with(['product.unit_quantities.unit', 'unit'])
            ->where('barcode', $barcode)
            ->first();

        if ($unitQuantity && $unitQuantity->product) {
            return response()->json($this->transformProduct($unitQuantity->product));
        }

        return response()->json(null);
    }

    /**
     * Transform product for mobile API response
     */
    private function transformProduct(Product $product): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'barcode' => $product->barcode,
            'barcode_type' => $product->barcode_type,
            'sku' => $product->sku,
            'status' => $product->status,
            'category_id' => $product->category_id,
            'unit_quantities' => $product->unit_quantities->map(fn($uq) => [
                'id' => $uq->id,
                'unit_id' => $uq->unit_id,
                'barcode' => $uq->barcode,
                'sale_price' => (float) $uq->sale_price,
                'wholesale_price' => (float) $uq->wholesale_price,
                'wholesale_price_edit' => (float) $uq->wholesale_price_edit,
                'unit' => $uq->unit ? [
                    'id' => $uq->unit->id,
                    'name' => $uq->unit->name,
                    'identifier' => $uq->unit->identifier,
                ] : null,
            ])->toArray(),
            'updated_at' => $product->updated_at?->format('Y-m-d H:i:s'),
            'deleted_at' => null,
        ];
    }
}
