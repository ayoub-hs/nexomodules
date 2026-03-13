<?php

namespace Modules\MobileApi\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\PaymentType;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\MobileApi\Support\MobileProductTransformer;

/**
 * Mobile Sync API Controller
 * 
 * Provides optimized sync endpoints for mobile apps:
 * - Bootstrap sync for initial data load
 * - Delta sync for incremental updates
 * - Sync status check
 */
class MobileSyncController extends Controller
{
    public function __construct(
        protected MobileProductTransformer $productTransformer
    ) {
    }

    /**
     * Bootstrap sync - Full initial data load
     * 
     * Returns all products, categories, customers, and payment methods.
     * Call this after first login or when cache needs rebuilding.
     * 
     * GET /api/mobile/sync/bootstrap
     */
    public function bootstrap(Request $request)
    {
        $startTime = microtime(true);
        $limit = max(1, min((int) $request->query('limit', 500), 1000));
        $cursorToken = $request->query('cursor');
        $cursor = $cursorToken ? $this->decodeBootstrapCursor($cursorToken) : null;

        if ($cursorToken && !$cursor) {
            return response()->json([
                'error' => 'Invalid cursor. Please restart bootstrap sync from the beginning.',
            ], 400);
        }

        $snapshot = $cursor['snapshot'] ?? now()->toIso8601String();
        $productsOffset = (int) ($cursor['products_offset'] ?? 0);
        $customersOffset = (int) ($cursor['customers_offset'] ?? 0);
        $pageSize = $limit + 1;

        \Log::debug('[MobileSync] Bootstrap sync started', [
            'user_id' => $request->user()?->id,
            'timestamp' => now()->toIso8601String(),
            'snapshot' => $snapshot,
            'products_offset' => $productsOffset,
            'customers_offset' => $customersOffset,
        ]);

        $includeStaticData = $cursor === null;

        $categoryCount = ProductCategory::displayOnPOS()
            ->where('updated_at', '<=', $snapshot)
            ->count();
        $productCount = Product::onSale()
            ->excludeVariations()
            ->where('updated_at', '<=', $snapshot)
            ->count();
        $customerCount = Customer::where('updated_at', '<=', $snapshot)->count();
        $paymentMethodCount = PaymentType::active()
            ->where('updated_at', '<=', $snapshot)
            ->count();

        $categories = $includeStaticData
            ? ProductCategory::displayOnPOS()
                ->where('updated_at', '<=', $snapshot)
                ->select(['id', 'name', 'description', 'updated_at'])
                ->withCount('products')
                ->orderBy('name')
                ->get()
                ->map(fn($cat) => $this->transformCategory($cat))
            : collect();

        $productsPage = Product::with(['unit_quantities.unit'])
            ->onSale()
            ->excludeVariations()
            ->where('updated_at', '<=', $snapshot)
            ->select([
                'id', 'name', 'barcode', 'barcode_type', 'sku',
                'status', 'category_id', 'updated_at'
            ])
            ->orderBy('id')
            ->offset($productsOffset)
            ->limit($pageSize)
            ->get()
            ->pipe(fn ($products) => $this->productTransformer->transformProducts($products));
        $productsHasMore = $productsPage->count() > $limit;
        $products = $productsPage->take($limit)->values();

        $customersPage = Customer::with('group')
            ->where('updated_at', '<=', $snapshot)
            ->select(['id', 'username', 'first_name', 'last_name', 'email', 'phone', 'group_id'])
            ->orderBy('id')
            ->offset($customersOffset)
            ->limit($pageSize)
            ->get()
            ->map(fn($customer) => $this->transformCustomer($customer));
        $customersHasMore = $customersPage->count() > $limit;
        $customers = $customersPage->take($limit)->values();

        $paymentMethods = $includeStaticData
            ? PaymentType::active()
                ->where('updated_at', '<=', $snapshot)
                ->select(['id', 'identifier', 'label', 'readonly'])
                ->orderBy('identifier')
                ->get()
                ->map(fn($pm) => $this->transformPaymentMethod($pm))
            : collect();

        $orderTypes = $includeStaticData ? $this->getOrderTypes() : [];
        $syncToken = $this->generateSyncToken($snapshot);
        $hasMore = $productsHasMore || $customersHasMore;
        $nextProductsOffset = $productsOffset + $products->count();
        $nextCustomersOffset = $customersOffset + $customers->count();

        $executionTime = round((microtime(true) - $startTime) * 1000);

        \Log::debug('[MobileSync] Bootstrap sync completed', [
            'execution_time_ms' => $executionTime,
            'categories_count' => $categoryCount,
            'products_count' => $products->count(),
            'customers_count' => $customers->count(),
            'payment_methods_count' => $paymentMethodCount,
            'has_more' => $hasMore,
        ]);

        return response()->json([
            'categories' => $categories,
            'products' => $products,
            'customers' => $customers,
            'payment_methods' => $paymentMethods,
            'order_types' => $orderTypes,
            'sync_token' => $syncToken,
            'server_time' => now()->format('Y-m-d H:i:s'),
            'has_more' => $hasMore,
            'next_cursor' => $hasMore
                ? $this->encodeBootstrapCursor($nextProductsOffset, $nextCustomersOffset, $snapshot)
                : null,
            'meta' => [
                'execution_time_ms' => $executionTime,
                'limit' => $limit,
                'snapshot' => $snapshot,
                'counts' => [
                    'categories' => $categoryCount,
                    'products' => $productCount,
                    'customers' => $customerCount,
                    'payment_methods' => $paymentMethodCount,
                ],
            ],
        ]);
    }

    /**
     * Delta sync - Incremental updates since last sync
     * 
     * GET /api/mobile/sync/delta?since={sync_token}
     */
    public function delta(Request $request)
    {
        $syncToken = $request->query('since');
        $limit = min((int) $request->query('limit', 500), 1000);
        $cursorToken = $request->query('cursor');

        if (!$syncToken) {
            return response()->json([
                'error' => 'The "since" parameter is required. Use bootstrap sync for initial data.',
            ], 400);
        }

        $since = $this->decodeSyncToken($syncToken);
        if (!$since) {
            return response()->json([
                'error' => 'Invalid sync token. Please perform a bootstrap sync.',
            ], 400);
        }

        $cursor = $cursorToken ? $this->decodeDeltaCursor($cursorToken) : null;
        if ($cursorToken && !$cursor) {
            return response()->json([
                'error' => 'Invalid cursor. Please restart delta sync from the last stored sync token.',
            ], 400);
        }

        if ($cursor && ($cursor['since_token'] ?? null) !== $syncToken) {
            return response()->json([
                'error' => 'Cursor does not match the provided sync token.',
            ], 400);
        }

        $startTime = microtime(true);
        $snapshot = $cursor['snapshot'] ?? now()->toIso8601String();
        $offset = (int) ($cursor['offset'] ?? 0);
        $pageSize = $limit + 1;

        // Products delta
        $productsCreated = Product::with(['unit_quantities.unit'])
            ->onSale()
            ->excludeVariations()
            ->where('created_at', '>', $since)
            ->where('created_at', '<=', $snapshot)
            ->orderBy('created_at')
            ->orderBy('id')
            ->offset($offset)
            ->limit($pageSize)
            ->get()
            ->pipe(fn ($products) => $this->productTransformer->transformProducts($products));

        $productsUpdated = Product::with(['unit_quantities.unit'])
            ->onSale()
            ->excludeVariations()
            ->where('updated_at', '>', $since)
            ->where('updated_at', '<=', $snapshot)
            ->where('created_at', '<=', $since)
            ->orderBy('updated_at')
            ->orderBy('id')
            ->offset($offset)
            ->limit($pageSize)
            ->get()
            ->pipe(fn ($products) => $this->productTransformer->transformProducts($products));

        $productsDeleted = in_array(SoftDeletes::class, class_uses_recursive(Product::class), true)
            ? Product::onlyTrashed()
                ->where('deleted_at', '>', $since)
                ->where('deleted_at', '<=', $snapshot)
                ->orderBy('deleted_at')
                ->orderBy('id')
                ->offset($offset)
                ->limit($pageSize)
                ->get(['id'])
                ->pluck('id')
                ->toArray()
            : [];

        // Customers delta
        $customersCreated = Customer::with('group')
            ->where('created_at', '>', $since)
            ->where('created_at', '<=', $snapshot)
            ->orderBy('created_at')
            ->orderBy('id')
            ->offset($offset)
            ->limit($pageSize)
            ->get()
            ->map(fn($c) => $this->transformCustomer($c));

        $customersUpdated = Customer::with('group')
            ->where('updated_at', '>', $since)
            ->where('updated_at', '<=', $snapshot)
            ->where('created_at', '<=', $since)
            ->orderBy('updated_at')
            ->orderBy('id')
            ->offset($offset)
            ->limit($pageSize)
            ->get()
            ->map(fn($c) => $this->transformCustomer($c));

        $customersDeleted = []; // Customers typically aren't deleted

        // Categories delta
        $categoriesCreated = ProductCategory::displayOnPOS()
            ->where('created_at', '>', $since)
            ->where('created_at', '<=', $snapshot)
            ->withCount('products')
            ->orderBy('created_at')
            ->orderBy('id')
            ->offset($offset)
            ->limit($pageSize)
            ->get()
            ->map(fn($cat) => [
                'id' => $cat->id,
                'name' => $cat->name,
                'description' => $cat->description,
                'products_count' => $cat->products_count,
                'display_order' => 0,
            ]);

        $categoriesUpdated = ProductCategory::displayOnPOS()
            ->where('updated_at', '>', $since)
            ->where('updated_at', '<=', $snapshot)
            ->where('created_at', '<=', $since)
            ->withCount('products')
            ->orderBy('updated_at')
            ->orderBy('id')
            ->offset($offset)
            ->limit($pageSize)
            ->get()
            ->map(fn($cat) => [
                'id' => $cat->id,
                'name' => $cat->name,
                'description' => $cat->description,
                'products_count' => $cat->products_count,
                'display_order' => 0,
            ]);

        $categoriesDeleted = [];

        // Payment methods delta (rarely changes)
        $paymentMethodsCreated = PaymentType::active()
            ->where('created_at', '>', $since)
            ->where('created_at', '<=', $snapshot)
            ->orderBy('created_at')
            ->orderBy('id')
            ->offset($offset)
            ->limit($pageSize)
            ->get()
            ->map(fn($pm) => [
                'identifier' => $pm->identifier,
                'label' => $pm->label,
                'selected' => $pm->identifier === 'cash-payment',
                'readonly' => (bool) $pm->readonly,
            ]);

        $paymentMethodsUpdated = PaymentType::active()
            ->where('updated_at', '>', $since)
            ->where('updated_at', '<=', $snapshot)
            ->where('created_at', '<=', $since)
            ->orderBy('updated_at')
            ->orderBy('id')
            ->offset($offset)
            ->limit($pageSize)
            ->get()
            ->map(fn($pm) => [
                'identifier' => $pm->identifier,
                'label' => $pm->label,
                'selected' => $pm->identifier === 'cash-payment',
                'readonly' => (bool) $pm->readonly,
            ]);

        $productsCreatedHasMore = $productsCreated->count() > $limit;
        $productsUpdatedHasMore = $productsUpdated->count() > $limit;
        $productsDeletedHasMore = count($productsDeleted) > $limit;
        $customersCreatedHasMore = $customersCreated->count() > $limit;
        $customersUpdatedHasMore = $customersUpdated->count() > $limit;
        $categoriesCreatedHasMore = $categoriesCreated->count() > $limit;
        $categoriesUpdatedHasMore = $categoriesUpdated->count() > $limit;
        $paymentMethodsCreatedHasMore = $paymentMethodsCreated->count() > $limit;
        $paymentMethodsUpdatedHasMore = $paymentMethodsUpdated->count() > $limit;

        // Check if there might be more changes
        $hasMore = $productsCreatedHasMore ||
                   $productsUpdatedHasMore ||
                   $productsDeletedHasMore ||
                   $customersCreatedHasMore ||
                   $customersUpdatedHasMore ||
                   $categoriesCreatedHasMore ||
                   $categoriesUpdatedHasMore ||
                   $paymentMethodsCreatedHasMore ||
                   $paymentMethodsUpdatedHasMore;

        $finalSyncToken = $this->generateSyncToken($snapshot);
        $executionTime = round((microtime(true) - $startTime) * 1000);

        return response()->json([
            'products' => [
                'created' => $productsCreated->take($limit)->values(),
                'updated' => $productsUpdated->take($limit)->values(),
                'deleted_ids' => array_slice($productsDeleted, 0, $limit),
            ],
            'customers' => [
                'created' => $customersCreated->take($limit)->values(),
                'updated' => $customersUpdated->take($limit)->values(),
                'deleted_ids' => $customersDeleted,
            ],
            'categories' => [
                'created' => $categoriesCreated->take($limit)->values(),
                'updated' => $categoriesUpdated->take($limit)->values(),
                'deleted_ids' => $categoriesDeleted,
            ],
            'payment_methods' => [
                'created' => $paymentMethodsCreated->take($limit)->values(),
                'updated' => $paymentMethodsUpdated->take($limit)->values(),
                'deleted_ids' => [],
            ],
            'sync_token' => $finalSyncToken,
            'server_time' => now()->format('Y-m-d H:i:s'),
            'has_more' => $hasMore,
            'next_cursor' => $hasMore ? $this->encodeDeltaCursor($offset + $limit, $snapshot, $syncToken) : null,
            'meta' => [
                'execution_time_ms' => $executionTime,
                'offset' => $offset,
                'limit' => $limit,
                'snapshot' => $snapshot,
            ],
        ]);
    }

    /**
     * Sync status - Quick check if sync is needed
     * 
     * GET /api/mobile/sync/status
     */
    public function status(Request $request)
    {
        $since = $request->query('since');
        $sinceTimestamp = $since ? $this->decodeSyncToken($since) : null;

        $lastProductUpdate = Product::max('updated_at');
        $lastCustomerUpdate = Customer::max('updated_at');
        $lastCategoryUpdate = ProductCategory::max('updated_at');

        $productsUpdated = $sinceTimestamp ? 
            Product::where('updated_at', '>', $sinceTimestamp)->exists() : true;
        $customersUpdated = $sinceTimestamp ? 
            Customer::where('updated_at', '>', $sinceTimestamp)->exists() : true;
        $categoriesUpdated = $sinceTimestamp ? 
            ProductCategory::where('updated_at', '>', $sinceTimestamp)->exists() : true;

        return response()->json([
            'products_updated' => $productsUpdated,
            'customers_updated' => $customersUpdated,
            'categories_updated' => $categoriesUpdated,
            'last_product_update' => $lastProductUpdate,
            'last_customer_update' => $lastCustomerUpdate,
            'last_category_update' => $lastCategoryUpdate,
            'server_time' => now()->format('Y-m-d H:i:s'),
        ]);
    }

    private function transformCategory(ProductCategory $category): array
    {
        return [
            'id' => $category->id,
            'name' => $category->name,
            'description' => $category->description,
            'products_count' => $category->products_count ?? 0,
            'display_order' => 0,
        ];
    }

    /**
     * Transform customer for mobile API response
     */
    private function transformCustomer(Customer $customer): array
    {
        return [
            'id' => $customer->id,
            'username' => $customer->username,
            'name' => trim($customer->first_name . ' ' . $customer->last_name),
            'first_name' => $customer->first_name,
            'last_name' => $customer->last_name,
            'email' => $customer->email,
            'phone' => $customer->phone,
            'group' => $customer->group ? [
                'id' => $customer->group->id,
                'name' => $customer->group->name,
            ] : null,
            'is_default' => false,
        ];
    }

    private function transformPaymentMethod(PaymentType $paymentMethod): array
    {
        return [
            'identifier' => $paymentMethod->identifier,
            'label' => $paymentMethod->label,
            'selected' => $paymentMethod->identifier === 'cash-payment',
            'readonly' => (bool) $paymentMethod->readonly,
        ];
    }

    /**
     * Get order types configuration
     */
    private function getOrderTypes(): array
    {
        // Default order types - can be extended from options table
        return [
            [
                'identifier' => 'takeaway',
                'label' => 'Takeaway',
                'icon' => null,
                'selected' => true,
            ],
            [
                'identifier' => 'delivery',
                'label' => 'Delivery',
                'icon' => null,
                'selected' => false,
            ],
        ];
    }

    /**
     * Generate sync token (base64 encoded timestamp)
     */
    private function generateSyncToken(?string $timestamp = null): string
    {
        return base64_encode(json_encode([
            'timestamp' => $timestamp ?? now()->toIso8601String(),
            'version' => 1,
        ]));
    }

    /**
     * Decode sync token to get timestamp
     */
    private function decodeSyncToken(string $token): ?string
    {
        try {
            $decoded = json_decode(base64_decode($token), true);
            return $decoded['timestamp'] ?? null;
        } catch (\Exception $e) {
            return null;
        }
    }

    private function encodeDeltaCursor(int $offset, string $snapshot, string $sinceToken): string
    {
        return base64_encode(json_encode([
            'offset' => $offset,
            'snapshot' => $snapshot,
            'since_token' => $sinceToken,
            'version' => 1,
        ]));
    }

    private function decodeDeltaCursor(string $cursor): ?array
    {
        try {
            $decoded = json_decode(base64_decode($cursor), true);
            if (!is_array($decoded)) {
                return null;
            }

            if (!isset($decoded['offset'], $decoded['snapshot'], $decoded['since_token'])) {
                return null;
            }

            return $decoded;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function encodeBootstrapCursor(int $productsOffset, int $customersOffset, string $snapshot): string
    {
        return base64_encode(json_encode([
            'products_offset' => $productsOffset,
            'customers_offset' => $customersOffset,
            'snapshot' => $snapshot,
            'version' => 1,
        ]));
    }

    private function decodeBootstrapCursor(string $cursor): ?array
    {
        try {
            $decoded = json_decode(base64_decode($cursor), true);
            if (!is_array($decoded)) {
                return null;
            }

            if (!isset($decoded['products_offset'], $decoded['customers_offset'], $decoded['snapshot'])) {
                return null;
            }

            return $decoded;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
