<?php

namespace Modules\MobileApi\Http\Controllers;

use App\Classes\Hook;
use App\Crud\ProductCrud;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductUnitQuantity;
use App\Models\UnitGroup;
use App\Services\Helper;
use App\Services\ProductService;
use Illuminate\Http\Request;
use Modules\MobileApi\Support\MobileProductTransformer;

/**
 * Mobile Product API Controller
 * 
 * Provides product endpoints optimized for mobile apps
 * with bundled unit quantities and efficient search.
 */
class MobileProductController extends Controller
{
    public function __construct(
        protected ProductService $productService,
        protected MobileProductTransformer $productTransformer
    ) {
    }

    /**
     * Paginated product list for online-only inventory and selection flows.
     *
     * GET /api/mobile/products
     */
    public function index(Request $request)
    {
        $limit = max(1, min((int) $request->query('limit', 50), 100));
        $cursor = $request->query('cursor');
        $searchTerm = trim((string) (
            $request->query('search')
            ?? $request->query('q')
            ?? $request->query('query')
            ?? $request->query('term')
            ?? ''
        ));

        $query = Product::with(['unit_quantities.unit'])
            ->excludeVariations()
            ->orderBy('id');

        if ($searchTerm !== '') {
            $escapedSearchTerm = str_replace(['%', '_'], ['\%', '\_'], $searchTerm);

            $query->where(function ($q) use ($escapedSearchTerm) {
                $q->where('name', 'LIKE', "%{$escapedSearchTerm}%")
                    ->orWhere('barcode', 'LIKE', "%{$escapedSearchTerm}%")
                    ->orWhere('sku', 'LIKE', "%{$escapedSearchTerm}%");
            });
        }

        if ($cursor !== null && $cursor !== '') {
            $query->where('id', '>', (int) $cursor);
        }

        $products = $query
            ->limit($limit + 1)
            ->get()
            ->pipe(fn ($products) => $this->productTransformer->transformProducts($products));

        $hasMore = $products->count() > $limit;
        $data = $products->take($limit)->values();
        $nextCursor = $hasMore ? $data->last()['id'] : null;

        return $this->successResponse($data, [
            'has_more' => $hasMore,
            'next_cursor' => $nextCursor,
            'prev_cursor' => null,
            'limit' => $limit,
        ]);
    }

    /**
     * Search products with full details including unit quantities
     * 
     * POST /api/mobile/products/search
     */
    public function search(Request $request)
    {
        $startTime = microtime(true);
        
        $searchTerm = (string) (
            $request->input('search')
            ?? $request->input('q')
            ?? $request->input('query')
            ?? $request->input('term')
            ?? ''
        );
        $categoryId = $request->input('arguments.category_id');
        $limit = min((int) $request->input('limit', 50), 100);

        if (strlen($searchTerm) < 2) {
            return $this->successResponse([], [
                'total_count' => 0,
                'search_time_ms' => 0,
            ], [
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
            ->pipe(fn ($products) => $this->productTransformer->transformProducts($products));

        $searchTime = round((microtime(true) - $startTime) * 1000);

        return $this->successResponse($products, [
            'total_count' => $totalCount,
            'search_time_ms' => $searchTime,
        ], [
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
            return $this->errorResponse('Product not found', 404);
        }

        return response()->json($this->productTransformer->transformProduct($product));
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
            return response()->json($this->productTransformer->transformProduct($product));
        }

        // Then check unit quantity barcode
        $unitQuantity = ProductUnitQuantity::with(['product.unit_quantities.unit', 'unit'])
            ->where('barcode', $barcode)
            ->first();

        if ($unitQuantity && $unitQuantity->product) {
            return response()->json($this->productTransformer->transformProduct($unitQuantity->product));
        }

        return $this->errorResponse('Product not found', 404);
    }

    /**
     * Create a product using the dashboard-compatible nested payload.
     *
     * POST /api/mobile/products
     */
    public function store(Request $request)
    {
        $payload = $this->prepareCrudPayload($request);
        $flattened = $this->flattenCreatePayload($payload);
        $result = $this->productService->create($flattened);
        $product = $this->extractSavedProduct($result)->load(['unit_quantities.unit']);

        return response()->json($this->productTransformer->transformProduct($product), 201);
    }

    /**
     * Update a product using the dashboard-compatible nested payload.
     *
     * PUT /api/mobile/products/{id}
     */
    public function update(Request $request, int $id)
    {
        $product = Product::query()->with(['unit_quantities.unit'])->find($id);

        if (!$product) {
            return $this->errorResponse('Product not found', 404);
        }

        $payload = $this->prepareCrudPayload($request, $product);
        $flattened = (new ProductCrud())->getFlatForm($payload, $product);
        $result = $this->productService->update($product, $flattened);
        $savedProduct = $this->extractSavedProduct($result)->load(['unit_quantities.unit']);

        return response()->json($this->productTransformer->transformProduct($savedProduct));
    }

    private function successResponse(mixed $data, array $meta = [], array $extra = [], int $statusCode = 200)
    {
        return response()->json(array_merge([
            'status' => 'success',
            'success' => true,
            'data' => $data,
        ], $extra, $meta !== [] ? ['meta' => $meta] : []), $statusCode);
    }

    private function errorResponse(string $message, int $statusCode = 400, array $errors = [])
    {
        return response()->json(array_filter([
            'status' => 'error',
            'success' => false,
            'message' => $message,
            'errors' => $errors !== [] ? $errors : null,
        ], fn ($value) => $value !== null), $statusCode);
    }

    private function resolveSellingGroups(array $inputs, array $variation, ?Product $product = null): array
    {
        $sellingGroups = $this->normalizeSellingGroups($variation['units']['selling_group'] ?? []);

        if ($sellingGroups === []) {
            $sellingGroups = $this->normalizeSellingGroups($inputs['unit_quantities'] ?? []);
        }

        if ($sellingGroups === [] && $product instanceof Product) {
            $sellingGroups = $this->normalizeSellingGroups(
                $product->unit_quantities->map(function (ProductUnitQuantity $unitQuantity) {
                    return [
                        'unit_id' => $unitQuantity->unit_id,
                        'barcode' => $unitQuantity->barcode,
                        'sale_price' => $unitQuantity->sale_price,
                        'sale_price_edit' => $unitQuantity->sale_price_edit,
                        'wholesale_price' => $unitQuantity->wholesale_price,
                        'wholesale_price_edit' => $unitQuantity->wholesale_price_edit,
                        'cogs' => $unitQuantity->cogs,
                        'stock_alert_enabled' => $unitQuantity->stock_alert_enabled,
                        'low_quantity' => $unitQuantity->low_quantity,
                        'visible' => $unitQuantity->visible,
                        'quantity' => $unitQuantity->quantity,
                        'convert_unit_id' => $unitQuantity->convert_unit_id,
                        'preview_url' => $unitQuantity->preview_url,
                    ];
                })->all()
            );
        }

        if ($sellingGroups === []) {
            $sellingGroups = $this->buildFallbackSellingGroups($inputs, $variation);
        }

        return $sellingGroups;
    }

    private function normalizeSellingGroups(array $groups): array
    {
        return collect($groups)
            ->filter(fn ($group) => is_array($group))
            ->map(function (array $group) {
                return [
                    'unit_id' => (int) ($group['unit_id'] ?? $group['id'] ?? 0),
                    'barcode' => $group['barcode'] ?? null,
                    'sale_price' => (float) ($group['sale_price'] ?? $group['sale_price_edit'] ?? 0),
                    'sale_price_edit' => (float) ($group['sale_price_edit'] ?? $group['sale_price'] ?? 0),
                    'wholesale_price' => (float) ($group['wholesale_price'] ?? $group['wholesale_price_edit'] ?? 0),
                    'wholesale_price_edit' => (float) ($group['wholesale_price_edit'] ?? $group['wholesale_price'] ?? 0),
                    'cogs' => (float) ($group['cogs'] ?? 0),
                    'stock_alert_enabled' => $this->normalizeBoolean($group['stock_alert_enabled'] ?? false),
                    'low_quantity' => (float) ($group['low_quantity'] ?? 0),
                    'visible' => $this->normalizeBoolean($group['visible'] ?? true),
                    'quantity' => (float) ($group['quantity'] ?? 0),
                    'convert_unit_id' => $group['convert_unit_id'] ?? null,
                    'preview_url' => $group['preview_url'] ?? null,
                    'is_manufactured' => $this->normalizeBoolean($group['is_manufactured'] ?? false),
                    'is_raw_material' => $this->normalizeBoolean($group['is_raw_material'] ?? false),
                    'container_type_id' => $group['container_type_id'] ?? null,
                ];
            })
            ->filter(fn (array $group) => !empty($group['unit_id']))
            ->values()
            ->all();
    }

    private function buildFallbackSellingGroups(array $inputs, array $variation): array
    {
        $unitGroupId = $variation['units']['unit_group'] ?? $inputs['unit_group'] ?? null;

        if (!$unitGroupId) {
            return [];
        }

        $unit = UnitGroup::query()
            ->with('units:id,group_id')
            ->find($unitGroupId)
            ?->units
            ->sortBy('id')
            ->first();

        if ($unit === null) {
            return [];
        }

        return [[
            'unit_id' => (int) $unit->id,
            'barcode' => $inputs['barcode'] ?? $variation['identification']['barcode'] ?? null,
            'sale_price' => (float) ($inputs['sale_price'] ?? $inputs['sale_price_edit'] ?? 0),
            'sale_price_edit' => (float) ($inputs['sale_price_edit'] ?? $inputs['sale_price'] ?? 0),
            'wholesale_price' => (float) ($inputs['wholesale_price'] ?? $inputs['wholesale_price_edit'] ?? 0),
            'wholesale_price_edit' => (float) ($inputs['wholesale_price_edit'] ?? $inputs['wholesale_price'] ?? 0),
            'cogs' => (float) ($inputs['cogs'] ?? 0),
            'stock_alert_enabled' => $this->normalizeBoolean($inputs['stock_alert_enabled'] ?? false),
            'low_quantity' => (float) ($inputs['low_quantity'] ?? 0),
            'visible' => $this->normalizeBoolean($inputs['visible'] ?? true),
            'quantity' => (float) ($inputs['quantity'] ?? 0),
            'convert_unit_id' => $inputs['convert_unit_id'] ?? null,
            'preview_url' => $inputs['preview_url'] ?? null,
            'is_manufactured' => $this->normalizeBoolean($inputs['is_manufactured'] ?? false),
            'is_raw_material' => $this->normalizeBoolean($inputs['is_raw_material'] ?? false),
            'container_type_id' => $inputs['container_type_id'] ?? null,
        ]];
    }

    private function prepareCrudPayload(Request $request, ?Product $product = null): array
    {
        $inputs = $request->all();
        $variations = array_values($inputs['variations'] ?? []);

        if (empty($variations)) {
            $variations = [[
                '$primary' => true,
                'identification' => [],
                'expiry' => [],
                'taxes' => [],
                'units' => [
                    'selling_group' => [],
                ],
                'images' => [],
                'groups' => [],
            ]];
        }

        $hasPrimary = false;

        foreach ($variations as $index => &$variation) {
            $variation['$primary'] = (bool) ($variation['$primary'] ?? false);
            if ($variation['$primary']) {
                $hasPrimary = true;
            }

            $variation['identification'] = array_merge([
                'name' => $inputs['name'] ?? $variation['identification']['name'] ?? $product?->name ?? '',
                'category_id' => $inputs['category_id'] ?? $variation['identification']['category_id'] ?? $product?->category_id,
                'barcode' => $inputs['barcode'] ?? $variation['identification']['barcode'] ?? $product?->barcode,
                'sku' => $inputs['sku'] ?? $variation['identification']['sku'] ?? $product?->sku ?? '',
                'barcode_type' => $inputs['barcode_type'] ?? $variation['identification']['barcode_type'] ?? $product?->barcode_type ?? 'code128',
                'type' => $variation['identification']['type'] ?? $product?->type ?? 'materialized',
                'status' => $inputs['status'] ?? $variation['identification']['status'] ?? $product?->status ?? 'available',
                'stock_management' => $inputs['stock_management'] ?? $variation['identification']['stock_management'] ?? $product?->stock_management ?? 'enabled',
                'description' => $inputs['description'] ?? $variation['identification']['description'] ?? $product?->description ?? '',
                'is_manufactured' => $this->normalizeBoolean($inputs['is_manufactured'] ?? $variation['identification']['is_manufactured'] ?? $product?->is_manufactured ?? false),
                'is_raw_material' => $this->normalizeBoolean($inputs['is_raw_material'] ?? $variation['identification']['is_raw_material'] ?? $product?->is_raw_material ?? false),
            ], $variation['identification'] ?? []);

            $variation['expiry'] = array_merge([
                'expires' => $variation['expiry']['expires'] ?? false,
                'on_expiration' => $inputs['on_expiration'] ?? $variation['expiry']['on_expiration'] ?? $product?->on_expiration ?? 'prevent_sales',
            ], $variation['expiry'] ?? []);

            $variation['taxes'] = array_merge([
                'tax_group_id' => $inputs['tax_group_id'] ?? $variation['taxes']['tax_group_id'] ?? $product?->tax_group_id ?? null,
                'tax_type' => $inputs['tax_type'] ?? $variation['taxes']['tax_type'] ?? $product?->tax_type ?? 'inclusive',
            ], $variation['taxes'] ?? []);

            $variation['units'] = array_merge([
                'unit_group' => $inputs['unit_group'] ?? $variation['units']['unit_group'] ?? $product?->unit_group ?? null,
                'accurate_tracking' => $this->normalizeBoolean($inputs['accurate_tracking'] ?? $variation['units']['accurate_tracking'] ?? $product?->accurate_tracking ?? false),
                'auto_cogs' => $this->normalizeBoolean($inputs['auto_cogs'] ?? $variation['units']['auto_cogs'] ?? $product?->auto_cogs ?? false),
                'selling_group' => [],
            ], $variation['units'] ?? []);

            $variation['units']['selling_group'] = $this->resolveSellingGroups(
                inputs: $inputs,
                variation: $variation,
                product: $product
            );

            $variation['images'] = array_values($variation['images'] ?? []);
            $variation['groups'] = array_values($variation['groups'] ?? []);

            if (empty($variation['groups'])) {
                $variation['groups'] = [];
            }

            if ($index === 0 && !$hasPrimary) {
                $variation['$primary'] = true;
            }
        }
        unset($variation);

        $inputs['name'] = $inputs['name'] ?? ($variations[0]['identification']['name'] ?? $product?->name ?? '');
        $inputs['variations'] = $variations;

        return $inputs;
    }

    private function flattenCreatePayload(array $payload): array
    {
        $primary = collect($payload['variations'])
            ->first(fn (array $variation) => !empty($variation['$primary']));
        $source = $primary;

        unset($primary['units'], $primary['images'], $primary['groups']);

        $primary['identification']['name'] = $payload['name'];
        $primary = Helper::flatArrayWithKeys($primary)->toArray();
        $primary['product_type'] = 'product';
        $primary['images'] = $source['images'] ?? [];
        $primary['units'] = $source['units'] ?? ['selling_group' => []];
        $primary['groups'] = $source['groups'] ?? [];

        unset($primary['$primary']);

        return Hook::filter('ns-create-products-inputs', $primary, $source);
    }

    private function extractSavedProduct(array $result): Product
    {
        $product = $result['data']['product'] ?? $result['data']['parent'] ?? null;

        if (!$product instanceof Product) {
            throw new \RuntimeException('Unable to resolve saved product from service response.');
        }

        return $product->fresh(['unit_quantities.unit']);
    }

    private function normalizeBoolean(mixed $value): int
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
    }
}
