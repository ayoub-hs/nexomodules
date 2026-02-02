<?php

namespace Modules\MobileApi\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\PaymentType;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

        // Get categories that display on POS
        $categories = ProductCategory::displayOnPOS()
            ->select(['id', 'name', 'description', 'updated_at'])
            ->withCount('products')
            ->orderBy('name')
            ->get()
            ->map(fn($cat) => [
                'id' => $cat->id,
                'name' => $cat->name,
                'description' => $cat->description,
                'products_count' => $cat->products_count,
                'display_order' => 0,
            ]);

        // Get all available products with unit quantities
        $products = Product::with(['unit_quantities.unit'])
            ->onSale()
            ->excludeVariations()
            ->select([
                'id', 'name', 'barcode', 'barcode_type', 'sku', 
                'status', 'category_id', 'updated_at'
            ])
            ->get()
            ->map(fn($product) => $this->transformProduct($product));

        // Get customers
        $customers = Customer::with('group')
            ->select(['id', 'username', 'first_name', 'last_name', 'email', 'phone', 'group_id'])
            ->get()
            ->map(fn($customer) => $this->transformCustomer($customer));

        // Get active payment methods
        $paymentMethods = PaymentType::active()
            ->select(['id', 'identifier', 'label', 'readonly'])
            ->get()
            ->map(fn($pm) => [
                'identifier' => $pm->identifier,
                'label' => $pm->label,
                'selected' => $pm->identifier === 'cash-payment',
                'readonly' => (bool) $pm->readonly,
            ]);

        // Get order types from options or use defaults
        $orderTypes = $this->getOrderTypes();

        // Generate sync token
        $syncToken = $this->generateSyncToken();

        $executionTime = round((microtime(true) - $startTime) * 1000);

        return response()->json([
            'categories' => $categories,
            'products' => $products,
            'customers' => $customers,
            'payment_methods' => $paymentMethods,
            'order_types' => $orderTypes,
            'sync_token' => $syncToken,
            'server_time' => now()->format('Y-m-d H:i:s'),
            'meta' => [
                'execution_time_ms' => $executionTime,
                'counts' => [
                    'categories' => $categories->count(),
                    'products' => $products->count(),
                    'customers' => $customers->count(),
                    'payment_methods' => $paymentMethods->count(),
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

        $startTime = microtime(true);

        // Products delta
        $productsCreated = Product::with(['unit_quantities.unit'])
            ->onSale()
            ->excludeVariations()
            ->where('created_at', '>', $since)
            ->limit($limit)
            ->get()
            ->map(fn($p) => $this->transformProduct($p));

        $productsUpdated = Product::with(['unit_quantities.unit'])
            ->onSale()
            ->excludeVariations()
            ->where('updated_at', '>', $since)
            ->where('created_at', '<=', $since)
            ->limit($limit)
            ->get()
            ->map(fn($p) => $this->transformProduct($p));

        $productsDeleted = Product::onlyTrashed()
            ->where('deleted_at', '>', $since)
            ->pluck('id')
            ->toArray();

        // Customers delta
        $customersCreated = Customer::with('group')
            ->where('created_at', '>', $since)
            ->limit($limit)
            ->get()
            ->map(fn($c) => $this->transformCustomer($c));

        $customersUpdated = Customer::with('group')
            ->where('updated_at', '>', $since)
            ->where('created_at', '<=', $since)
            ->limit($limit)
            ->get()
            ->map(fn($c) => $this->transformCustomer($c));

        $customersDeleted = []; // Customers typically aren't deleted

        // Categories delta
        $categoriesCreated = ProductCategory::displayOnPOS()
            ->where('created_at', '>', $since)
            ->withCount('products')
            ->limit($limit)
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
            ->where('created_at', '<=', $since)
            ->withCount('products')
            ->limit($limit)
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
            ->get()
            ->map(fn($pm) => [
                'identifier' => $pm->identifier,
                'label' => $pm->label,
                'selected' => $pm->identifier === 'cash-payment',
                'readonly' => (bool) $pm->readonly,
            ]);

        $paymentMethodsUpdated = PaymentType::active()
            ->where('updated_at', '>', $since)
            ->where('created_at', '<=', $since)
            ->get()
            ->map(fn($pm) => [
                'identifier' => $pm->identifier,
                'label' => $pm->label,
                'selected' => $pm->identifier === 'cash-payment',
                'readonly' => (bool) $pm->readonly,
            ]);

        // Check if there might be more changes
        $hasMore = $productsCreated->count() >= $limit || 
                   $productsUpdated->count() >= $limit ||
                   $customersCreated->count() >= $limit ||
                   $customersUpdated->count() >= $limit;

        $newSyncToken = $this->generateSyncToken();
        $executionTime = round((microtime(true) - $startTime) * 1000);

        return response()->json([
            'products' => [
                'created' => $productsCreated,
                'updated' => $productsUpdated,
                'deleted_ids' => $productsDeleted,
            ],
            'customers' => [
                'created' => $customersCreated,
                'updated' => $customersUpdated,
                'deleted_ids' => $customersDeleted,
            ],
            'categories' => [
                'created' => $categoriesCreated,
                'updated' => $categoriesUpdated,
                'deleted_ids' => $categoriesDeleted,
            ],
            'payment_methods' => [
                'created' => $paymentMethodsCreated,
                'updated' => $paymentMethodsUpdated,
                'deleted_ids' => [],
            ],
            'sync_token' => $newSyncToken,
            'server_time' => now()->format('Y-m-d H:i:s'),
            'has_more' => $hasMore,
            'meta' => [
                'execution_time_ms' => $executionTime,
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
    private function generateSyncToken(): string
    {
        return base64_encode(json_encode([
            'timestamp' => now()->toIso8601String(),
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
}
