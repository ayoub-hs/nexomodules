<?php

namespace Modules\MobileApi\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrdersService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Mobile-optimized Orders API Controller
 * 
 * Provides cursor-based pagination and filtering for mobile apps
 * to avoid loading thousands of orders at once.
 */
class MobileOrdersController extends Controller
{
    /**
     * Get paginated orders with optional filtering
     * 
     * Query Parameters:
     * - cursor: ID of the last order from previous page (for cursor pagination)
     * - limit: Number of orders per page (default: 20, max: 100)
     * - customer: Filter by customer name (partial match)
     * - payment_status: Filter by payment status (paid, unpaid, partially_paid, etc.)
     * - since: Only fetch orders updated after this timestamp (ISO 8601 format)
     * - direction: 'before' or 'after' cursor (default: 'before' = older orders)
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $limit = min((int) $request->input('limit', 20), 100);
        $cursor = $request->input('cursor');
        $customerFilter = $request->input('customer');
        $paymentStatus = $request->input('payment_status');
        $since = $request->input('since');
        $direction = $request->input('direction', 'before');

        $query = Order::with(['customer:id,first_name,last_name,email,phone', 'products'])
            ->select([
                'id',
                'code',
                'type',
                'payment_status',
                'total',
                'subtotal',
                'tendered',
                'change',
                'discount',
                'discount_type',
                'discount_percentage',
                'tax_value',
                'customer_id',
                'created_at',
                'updated_at',
            ]);

        // Apply cursor pagination
        if ($cursor) {
            if ($direction === 'after') {
                $query->where('id', '>', $cursor);
            } else {
                $query->where('id', '<', $cursor);
            }
        }

        // Filter by customer name
        if ($customerFilter) {
            // Escape LIKE wildcards to prevent wildcard injection
            $escapedFilter = str_replace(['%', '_'], ['\%', '\_'], $customerFilter);
            
            $query->whereHas('customer', function ($q) use ($escapedFilter) {
                $q->where(function ($subQuery) use ($escapedFilter) {
                    $subQuery->where('first_name', 'LIKE', "%{$escapedFilter}%")
                        ->orWhere('last_name', 'LIKE', "%{$escapedFilter}%")
                        ->orWhere('email', 'LIKE', "%{$escapedFilter}%")
                        ->orWhere('phone', 'LIKE', "%{$escapedFilter}%");
                });
            });
        }

        // Filter by payment status
        if ($paymentStatus) {
            $query->where('payment_status', $paymentStatus);
        }

        // Filter by updated_at for incremental sync
        if ($since) {
            $query->where('updated_at', '>=', $since);
        }

        // Order and limit
        if ($direction === 'after') {
            $query->orderBy('id', 'asc');
        } else {
            $query->orderBy('id', 'desc');
        }

        $orders = $query->limit($limit + 1)->get();

        // Check if there are more results
        $hasMore = $orders->count() > $limit;
        if ($hasMore) {
            $orders = $orders->take($limit);
        }

        // Transform orders for mobile consumption
        $transformedOrders = $orders->map(function ($order) {
            return $this->transformOrder($order);
        });

        // Get cursor for next page
        $nextCursor = $orders->isNotEmpty() ? $orders->last()->id : null;
        $prevCursor = $orders->isNotEmpty() ? $orders->first()->id : null;

        return response()->json([
            'data' => $transformedOrders,
            'meta' => [
                'has_more' => $hasMore,
                'next_cursor' => $hasMore ? $nextCursor : null,
                'prev_cursor' => $prevCursor,
                'limit' => $limit,
            ],
        ]);
    }

    /**
     * Get a single order with full details
     * 
     * @param Order $order
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Order $order)
    {
        $order->load([
            'customer:id,first_name,last_name,email,phone',
            'products',
            'payments',
        ]);

        return response()->json([
            'data' => $this->transformOrder($order, true),
        ]);
    }

    /**
     * Get orders updated since a specific timestamp
     * Useful for syncing changes without re-fetching everything
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function sync(Request $request)
    {
        $since = $request->input('since');
        $limit = min((int) $request->input('limit', 50), 200);

        if (!$since) {
            return response()->json([
                'error' => 'The "since" parameter is required',
            ], 400);
        }

        $orders = Order::with(['customer:id,first_name,last_name,email,phone', 'products'])
            ->select([
                'id',
                'code',
                'type',
                'payment_status',
                'total',
                'subtotal',
                'tendered',
                'change',
                'discount',
                'discount_type',
                'discount_percentage',
                'tax_value',
                'customer_id',
                'created_at',
                'updated_at',
            ])
            ->where('updated_at', '>=', $since)
            ->orderBy('updated_at', 'asc')
            ->limit($limit + 1)
            ->get();

        $hasMore = $orders->count() > $limit;
        if ($hasMore) {
            $orders = $orders->take($limit);
        }

        $transformedOrders = $orders->map(function ($order) {
            return $this->transformOrder($order);
        });

        $lastUpdatedAt = $orders->isNotEmpty() ? $orders->last()->updated_at : null;

        return response()->json([
            'data' => $transformedOrders,
            'meta' => [
                'has_more' => $hasMore,
                'last_updated_at' => $lastUpdatedAt,
                'count' => $transformedOrders->count(),
            ],
        ]);
    }

    /**
     * Transform order for mobile API response
     * 
     * @param Order $order
     * @param bool $includePayments
     * @return array
     */
    private function transformOrder(Order $order, bool $includePayments = false): array
    {
        $data = [
            'id' => $order->id,
            'code' => $order->code,
            'type' => $order->type,
            'payment_status' => $order->payment_status,
            'total' => (float) $order->total,
            'subtotal' => (float) $order->subtotal,
            'tendered' => (float) $order->tendered,
            'change' => (float) $order->change,
            'discount' => (float) $order->discount,
            'discount_type' => $order->discount_type,
            'discount_percentage' => (float) $order->discount_percentage,
            'tax_value' => (float) $order->tax_value,
            'created_at' => $order->created_at,
            'updated_at' => $order->updated_at,
            'customer' => $order->customer ? [
                'id' => $order->customer->id,
                'first_name' => $order->customer->first_name,
                'last_name' => $order->customer->last_name,
                'email' => $order->customer->email,
                'phone' => $order->customer->phone,
            ] : null,
            'products' => $order->products ? $order->products->map(function ($product) {
                return [
                    'id' => $product->id,
                    'product_id' => $product->product_id,
                    'name' => $product->name,
                    'quantity' => (float) $product->quantity,
                    'unit_id' => $product->unit_id,
                    'unit_name' => $product->unit_name ?? null,
                    'unit_price' => (float) $product->unit_price,
                    'total_price' => (float) $product->total_price,
                    'total_price_with_tax' => (float) $product->total_price_with_tax,
                    'tax_value' => (float) $product->tax_value,
                    'discount' => (float) $product->discount,
                ];
            })->toArray() : [],
        ];

        if ($includePayments && $order->payments) {
            $data['payments'] = $order->payments->map(function ($payment) {
                return [
                    'id' => $payment->id,
                    'identifier' => $payment->identifier,
                    'value' => (float) $payment->value,
                    'created_at' => $payment->created_at,
                ];
            })->toArray();
        }

        return $data;
    }

    /**
     * Submit multiple orders in batch
     * 
     * Useful for syncing offline orders efficiently.
     * Checks for duplicates using client_reference (code field).
     * 
     * POST /api/mobile/orders/batch
     * 
     * @param Request $request
     * @param OrdersService $ordersService
     * @return \Illuminate\Http\JsonResponse
     */
    public function batch(Request $request, OrdersService $ordersService)
    {
        $orders = $request->input('orders', []);
        $results = [];
        $successCount = 0;
        $failureCount = 0;

        foreach ($orders as $orderData) {
            $clientReference = $orderData['client_reference'] ?? null;
            
            // Start database transaction for each order
            DB::beginTransaction();
            
            try {
                // Check for duplicate by client_reference (stored in code field)
                if ($clientReference) {
                    $existing = Order::where('code', $clientReference)->first();
                    if ($existing) {
                        $results[] = [
                            'client_reference' => $clientReference,
                            'success' => true,
                            'order' => $this->transformOrderSummary($existing),
                            'error' => null,
                            'duplicate' => true,
                        ];
                        $successCount++;
                        DB::commit();
                        continue;
                    }
                }

                // Create the order using the OrdersService
                $result = $ordersService->create($orderData);
                $order = $result['data']['order'];

                $results[] = [
                    'client_reference' => $clientReference,
                    'success' => true,
                    'order' => $this->transformOrderSummary($order),
                    'error' => null,
                    'duplicate' => false,
                ];
                $successCount++;
                
                // Commit transaction on success
                DB::commit();

            } catch (\Exception $e) {
                // Rollback transaction on failure
                DB::rollBack();
                
                \Log::error('Mobile API: Batch order creation failed', [
                    'client_reference' => $clientReference,
                    'error' => $e->getMessage(),
                    'user_id' => auth()->id(),
                ]);
                
                $results[] = [
                    'client_reference' => $clientReference,
                    'success' => false,
                    'order' => null,
                    'error' => $e->getMessage(),
                    'duplicate' => false,
                ];
                $failureCount++;
            }
        }

        return response()->json([
            'results' => $results,
            'success_count' => $successCount,
            'failure_count' => $failureCount,
        ]);
    }

    /**
     * Transform order to summary format for batch response
     */
    private function transformOrderSummary(Order $order): array
    {
        return [
            'id' => $order->id,
            'code' => $order->code,
            'total' => (float) $order->total,
            'total_without_tax' => (float) ($order->subtotal ?? $order->total),
            'total_with_tax' => (float) $order->total,
            'total_coupons' => (float) ($order->discount ?? 0),
            'tax_value' => (float) ($order->tax_value ?? 0),
            'payment_status' => $order->payment_status,
            'customer' => $order->customer ? [
                'id' => $order->customer->id,
                'first_name' => $order->customer->first_name,
                'last_name' => $order->customer->last_name,
            ] : null,
        ];
    }
}
