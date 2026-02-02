<?php

namespace Modules\MobileApi\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Http\Request;

/**
 * Mobile Category API Controller
 * 
 * Provides category endpoints optimized for mobile apps
 * with bundled product data including unit quantities.
 */
class MobileCategoryController extends Controller
{
    /**
     * Get products for a category with all unit quantities bundled
     * 
     * GET /api/mobile/categories/{id}/products
     */
    public function products(Request $request, int $id)
    {
        $category = ProductCategory::find($id);
        
        if (!$category) {
            return response()->json([
                'error' => 'Category not found',
            ], 404);
        }

        $products = Product::with(['unit_quantities.unit'])
            ->where('category_id', $id)
            ->onSale()
            ->excludeVariations()
            ->select([
                'id', 'name', 'barcode', 'barcode_type', 'sku',
                'status', 'category_id', 'updated_at'
            ])
            ->orderBy('name')
            ->get()
            ->map(fn($product) => $this->transformProduct($product));

        $lastUpdated = Product::where('category_id', $id)->max('updated_at');

        return response()->json([
            'category' => [
                'id' => $category->id,
                'name' => $category->name,
                'description' => $category->description,
                'products_count' => $products->count(),
                'display_order' => 0,
            ],
            'products' => $products,
            'last_updated' => $lastUpdated,
        ]);
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
