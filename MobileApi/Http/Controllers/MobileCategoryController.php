<?php

namespace Modules\MobileApi\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Modules\MobileApi\Support\MobileProductTransformer;

/**
 * Mobile Category API Controller
 * 
 * Provides category endpoints optimized for mobile apps
 * with bundled product data including unit quantities.
 */
class MobileCategoryController extends Controller
{
    public function __construct(
        protected MobileProductTransformer $productTransformer
    ) {
    }

    /**
     * Get all POS-visible categories for the mobile POS tab bar.
     *
     * GET /api/mobile/categories
     */
    public function index(Request $request)
    {
        $categories = ProductCategory::displayOnPOS()
            ->select(['id', 'name', 'description', 'updated_at'])
            ->withCount('products')
            ->orderBy('name')
            ->get()
            ->map(fn ($category) => $this->transformCategory($category));

        return response()->json($categories);
    }

    /**
     * Get products for a category with all unit quantities bundled
     * 
     * GET /api/mobile/categories/{id}/products
     */
    public function products(Request $request, int $id)
    {
        return response()->json($this->buildCategoryProductsResponse($id));
    }

    /**
     * Get products by category for mobile catalog
     *
     * GET /api/mobile/catalog/category/{id}
     * If ID is 0, returns all products
     */
    public function getCategoryProducts($id, Request $request)
    {
        return response()->json($this->buildCategoryProductsResponse((int) $id));
    }

    private function buildCategoryProductsResponse(int $id): array
    {
        if ($id === 0) {
            $products = Product::with(['unit_quantities.unit'])
                ->onSale()
                ->excludeVariations()
                ->select([
                'id', 'name', 'barcode', 'barcode_type', 'sku',
                    'status', 'category_id', 'updated_at'
                ])
                ->orderBy('name')
                ->get();

            $lastUpdated = Product::max('updated_at');

            return [
                'category' => [
                    'id' => 0,
                    'name' => 'All Products',
                    'description' => null,
                    'products_count' => $products->count(),
                    'display_order' => 0,
                ],
                'products' => $this->productTransformer->transformProducts($products),
                'last_updated' => $this->formatTimestamp($lastUpdated),
            ];
        }

        $category = ProductCategory::displayOnPOS()
            ->withCount('products')
            ->whereKey($id)
            ->first();

        if (!$category) {
            abort(404, 'Category not found');
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
            ->get();

        $lastUpdated = Product::where('category_id', $id)->max('updated_at');

        return [
            'category' => $this->transformCategory($category, $products->count()),
            'products' => $this->productTransformer->transformProducts($products),
            'last_updated' => $this->formatTimestamp($lastUpdated),
        ];
    }

    private function transformCategory(ProductCategory $category, ?int $productsCount = null): array
    {
        return [
            'id' => $category->id,
            'name' => $category->name,
            'slug' => Str::slug($category->name),
            'description' => $category->description,
            'products_count' => $productsCount ?? $category->products_count ?? 0,
            'display_order' => 0,
        ];
    }

    private function formatTimestamp(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value->format('Y-m-d H:i:s');
        }

        return Carbon::parse((string) $value)->format('Y-m-d H:i:s');
    }
}
