<?php

namespace Modules\MobileApi\Http\Controllers;

use App\Models\Procurement;
use App\Models\ProcurementProduct;
use App\Models\Product;
use App\Models\ProductUnitQuantity;
use App\Models\Provider;
use App\Models\TaxGroup;
use App\Services\ProcurementService;
use App\Services\TaxService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Mobile API controller for procurements.
 * Provides list and detail endpoints for the Android app.
 * 
 * Maps backend Procurement model fields to Android app expected format:
 * - delivery_status → status
 * - value → total
 * - delivery_time → delivery_date
 */
class MobileProcurementController
{
    public function __construct(
        protected ProcurementService $procurementService,
        protected TaxService $taxService
    ) {
    }

    /**
     * Get list of procurements for mobile app.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Procurement::with(['provider:id,first_name,last_name,email,phone,address_1,address_2'])
                ->select([
                    'id',
                    'name',
                    'provider_id',
                    'value',
                    'cost',
                    'tax_value',
                    'delivery_time',
                    'delivery_status',
                    'payment_status',
                    'total_items',
                    'description',
                    'created_at',
                    'updated_at'
                ]);

            // Filter by delivery status if provided
            if ($request->has('status') && !empty($request->status)) {
                $query->where('delivery_status', $request->status);
            }

            // Filter by payment status if provided
            if ($request->has('payment_status') && !empty($request->payment_status)) {
                $query->where('payment_status', $request->payment_status);
            }

            // Filter by provider if provided
            if ($request->has('provider_id') && !empty($request->provider_id)) {
                $query->where('provider_id', $request->provider_id);
            }

            // Search by name or provider name
            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhereHas('provider', function ($pq) use ($search) {
                            $pq->where('name', 'like', "%{$search}%");
                        });
                });
            }

            // Order by most recent first
            $query->orderBy('created_at', 'desc');

            // Pagination
            $limit = $request->get('limit', 50);
            $offset = $request->get('offset', 0);
            $total = $query->count();
            $procurements = $query->skip($offset)->take($limit)->get();

            // Transform for mobile API
            $data = $procurements->map(function ($procurement) {
                return $this->transformProcurement($procurement);
            });

            return response()->json([
                'status' => 'success',
                'data' => $data,
                'meta' => [
                    'total' => $total,
                    'limit' => (int) $limit,
                    'offset' => (int) $offset,
                    'has_more' => ($offset + $limit) < $total
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get single procurement details for mobile app.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        try {
            $procurement = Procurement::with([
                'provider:id,first_name,last_name,email,phone,address_1,address_2',
                'products' => function ($query) {
                    $query->with(['product:id,name,barcode', 'unit:id,name,identifier']);
                }
            ])->find($id);

            if (!$procurement) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Procurement not found'
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'data' => $this->transformProcurementDetail($procurement)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new procurement for mobile app.
     */
    public function store(Request $request): JsonResponse
    {
        if ($response = $this->requirePermission('nexopos.create.procurements')) {
            return $response;
        }

        $validator = Validator::make($request->all(), [
            'provider_id' => 'required|integer|exists:nexopos_providers,id',
            'name' => 'nullable|string|max:255',
            'invoice_reference' => 'nullable|string|max:255',
            'invoice_date' => 'nullable|date',
            'status' => 'nullable|string|in:pending,delivered,draft',
            'payment_status' => 'nullable|string|in:paid,unpaid',
            'expected_delivery' => 'nullable|date',
            'notes' => 'nullable|string|max:1000',
            'products' => 'required|array|min:1',
            'products.*.product_id' => 'required|integer|exists:nexopos_products,id',
            'products.*.quantity' => 'required|numeric|min:0.000001',
            'products.*.unit_price' => 'required|numeric|min:0',
            'products.*.unit_id' => 'nullable|integer|exists:nexopos_units,id',
            'products.*.expiration_date' => 'nullable|date',
            'products.*.tax_type' => 'nullable|string|in:inclusive,exclusive',
            'products.*.tax_group_id' => 'nullable|integer|exists:nexopos_tax_groups,id',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        $data = $validator->validated();

        try {
            $payload = [
                'name' => $data['name'] ?? null,
                'general' => $this->buildGeneralPayload($data, null),
                'products' => $this->buildProductsPayload($data['products']),
            ];

            $result = DB::transaction(function () use ($payload) {
                return $this->procurementService->create($payload);
            });
            $procurement = $result['data']['procurement'] ?? null;

            if (! $procurement instanceof Procurement) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unable to create procurement.',
                ], 500);
            }

            $procurement->refresh();
            $this->loadProcurementRelations($procurement);

            return response()->json([
                'status' => 'success',
                'message' => __('Procurement created successfully.'),
                'data' => $this->transformProcurementDetail($procurement),
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('The request is not valid.'),
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update an existing procurement for mobile app.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        if ($response = $this->requirePermission('nexopos.update.procurements')) {
            return $response;
        }

        $validator = Validator::make($request->all(), [
            'provider_id' => 'nullable|integer|exists:nexopos_providers,id',
            'name' => 'nullable|string|max:255',
            'invoice_reference' => 'nullable|string|max:255',
            'invoice_date' => 'nullable|date',
            'status' => 'nullable|string|in:pending,delivered,draft',
            'payment_status' => 'nullable|string|in:paid,unpaid',
            'expected_delivery' => 'nullable|date',
            'notes' => 'nullable|string|max:1000',
            'products' => 'nullable|array|min:1',
            'products.*.product_id' => 'required_with:products|integer|exists:nexopos_products,id',
            'products.*.quantity' => 'required_with:products|numeric|min:0.000001',
            'products.*.unit_price' => 'required_with:products|numeric|min:0',
            'products.*.unit_id' => 'nullable|integer|exists:nexopos_units,id',
            'products.*.expiration_date' => 'nullable|date',
            'products.*.tax_type' => 'nullable|string|in:inclusive,exclusive',
            'products.*.tax_group_id' => 'nullable|integer|exists:nexopos_tax_groups,id',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        $data = $validator->validated();

        try {
            $procurement = Procurement::with('products')->findOrFail($id);

            if ($procurement->delivery_status === Procurement::STOCKED) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('Unable to edit a stocked procurement.'),
                ], 409);
            }

            $productsPayload = [];
            if (array_key_exists('products', $data)) {
                $productsPayload = $this->buildProductsPayload($data['products']);
                $this->procurementService->deleteProducts($procurement);
            }

            $payload = [
                'name' => $data['name'] ?? $procurement->name,
                'general' => $this->buildGeneralPayload($data, $procurement),
                'products' => $productsPayload,
            ];

            DB::transaction(function () use ($procurement, $payload) {
                $this->procurementService->edit($procurement->id, $payload);
            });
            $procurement->refresh();
            $this->loadProcurementRelations($procurement);

            return response()->json([
                'status' => 'success',
                'message' => __('Procurement updated successfully.'),
                'data' => $this->transformProcurementDetail($procurement),
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('The request is not valid.'),
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update procurement delivery status.
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        if ($response = $this->requirePermission('nexopos.update.procurements')) {
            return $response;
        }

        $status = strtolower(trim((string) $request->input('status', '')));

        if ($status === '') {
            return response()->json([
                'status' => 'error',
                'message' => __('Status is required.'),
            ], 422);
        }

        if (! in_array($status, [Procurement::PENDING, Procurement::DELIVERED, Procurement::DRAFT], true)) {
            return response()->json([
                'status' => 'error',
                'message' => __('Unsupported status value.'),
            ], 422);
        }

        try {
            $procurement = Procurement::findOrFail($id);

            if ($procurement->delivery_status === Procurement::STOCKED) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('Unable to change status of a stocked procurement.'),
                ], 409);
            }

            $procurement->delivery_status = $status;
            $procurement->save();

            if ($status === Procurement::DELIVERED) {
                $this->procurementService->handleProcurement($procurement);
                $procurement->refresh();

                if ($procurement->delivery_status !== Procurement::STOCKED) {
                    $procurement->delivery_status = Procurement::STOCKED;
                    $procurement->save();
                }
            }

            $procurement->refresh();
            $this->loadProcurementRelations($procurement);

            return response()->json([
                'status' => 'success',
                'message' => __('Procurement status updated successfully.'),
                'data' => $this->transformProcurementDetail($procurement),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Receive procurement items (full delivery).
     */
    public function receive(Request $request, int $id): JsonResponse
    {
        $request->merge(['status' => Procurement::DELIVERED]);

        return $this->updateStatus($request, $id);
    }

    /**
     * Cancel a procurement (delete if not stocked).
     */
    public function cancel(Request $request, int $id): JsonResponse
    {
        return $this->destroy($id);
    }

    /**
     * Delete procurement.
     */
    public function destroy(int $id): JsonResponse
    {
        if ($response = $this->requirePermission('nexopos.delete.procurements')) {
            return $response;
        }

        try {
            $procurement = Procurement::findOrFail($id);

            if ($procurement->delivery_status === Procurement::STOCKED) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('Unable to delete a stocked procurement.'),
                ], 409);
            }

            $result = $this->procurementService->delete($id);

            return response()->json([
                'status' => $result['status'] ?? 'success',
                'message' => $result['message'] ?? __('Procurement deleted successfully.'),
                'data' => null,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Transform procurement for list view.
     * Maps backend fields to Android app expected format.
     *
     * @param Procurement $procurement
     * @return array
     */
    private function transformProcurement(Procurement $procurement): array
    {
        $provider = $this->resolveProvider($procurement);

        return [
            'id' => (int) $procurement->id,
            'provider_id' => (int) $procurement->provider_id,
            'provider' => $provider ? [
                'id' => (int) $provider->id,
                'name' => $this->formatProviderName($provider),
            ] : null,
            // Map delivery_status to status for Android app
            'status' => $procurement->delivery_status ?? Procurement::DRAFT,
            'payment_status' => $procurement->payment_status ?? Procurement::PAYMENT_UNPAID,
            // Map value to total for Android app
            'total' => (float) ($procurement->value ?? 0),
            'currency' => config('nexopos.currency', 'USD'),
            'created_at' => $this->formatTimestamp($procurement->created_at),
            'updated_at' => $this->formatTimestamp($procurement->updated_at),
            // Map delivery_time to delivery_date for Android app
            'delivery_date' => $this->formatTimestamp($procurement->delivery_time),
            'invoice_reference' => $procurement->invoice_reference,
            'invoice_date' => $this->formatTimestamp($procurement->invoice_date),
            'description' => $procurement->description,
        ];
    }

    /**
     * Transform procurement for detail view with products.
     * Maps backend fields to Android app expected format.
     *
     * @param Procurement $procurement
     * @return array
     */
    private function transformProcurementDetail(Procurement $procurement): array
    {
        $data = $this->transformProcurement($procurement);
        
        // Add provider details
        $provider = $this->resolveProvider($procurement);
        if ($provider) {
            $data['provider'] = [
                'id' => (int) $provider->id,
                'name' => $this->formatProviderName($provider),
                'email' => $provider->email,
                'phone' => $provider->phone,
                'address' => $this->formatProviderAddress($provider),
            ];
        }

        // Add products - map ProcurementProduct fields to Android expected format
        $data['products'] = $procurement->products->map(function ($procurementProduct) {
            return [
                'id' => (int) $procurementProduct->id,
                'product_id' => (int) $procurementProduct->product_id,
                'product_name' => $procurementProduct->product?->name ?? $procurementProduct->name,
                'quantity' => (float) $procurementProduct->quantity,
                // Map purchase_price to unit_price for Android app
                'unit_price' => (float) $procurementProduct->purchase_price,
                // Map total_purchase_price to total_price for Android app
                'total_price' => (float) $procurementProduct->total_purchase_price,
                'unit_id' => $procurementProduct->unit_id ? (int) $procurementProduct->unit_id : null,
            ];
        });

        return $data;
    }

    private function buildGeneralPayload(array $data, ?Procurement $existing): array
    {
        $deliveryStatus = $this->normalizeDeliveryStatus(
            $data['status'] ?? $existing?->delivery_status ?? Procurement::PENDING
        );
        $paymentStatus = $this->normalizePaymentStatus(
            $data['payment_status'] ?? $existing?->payment_status ?? Procurement::PAYMENT_UNPAID
        );

        return [
            'provider_id' => (int) ($data['provider_id'] ?? $existing?->provider_id ?? 0),
            'delivery_status' => $deliveryStatus,
            'payment_status' => $paymentStatus,
            'invoice_reference' => array_key_exists('invoice_reference', $data)
                ? $data['invoice_reference']
                : ($existing?->invoice_reference ?? null),
            'invoice_date' => array_key_exists('invoice_date', $data)
                ? $this->normalizeDateTime($data['invoice_date'])
                : ($existing?->invoice_date ?? null),
            'delivery_time' => array_key_exists('expected_delivery', $data)
                ? $this->normalizeDateTime($data['expected_delivery'])
                : ($existing?->delivery_time ?? null),
            'description' => array_key_exists('notes', $data)
                ? $data['notes']
                : ($existing?->description ?? null),
        ];
    }

    private function buildProductsPayload(array $items): array
    {
        return collect($items)->map(function ($item) {
            $product = Product::findOrFail($item['product_id']);
            if ($product->stock_management === 'disabled') {
                throw ValidationException::withMessages([
                    'products' => [sprintf(__('Unable to procure "%s" because stock management is disabled.'), $product->name)],
                ]);
            }
            if ($product->type === 'grouped') {
                throw ValidationException::withMessages([
                    'products' => [sprintf(__('Unable to procure grouped product "%s".'), $product->name)],
                ]);
            }
            $unitQuantity = $this->resolveUnitQuantity($product->id, $item['unit_id'] ?? null);

            if (! $unitQuantity instanceof ProductUnitQuantity) {
                throw ValidationException::withMessages([
                    'products' => [sprintf(__('No unit quantity could be resolved for product "%s".'), $product->name)],
                ]);
            }

            $unitPrice = (float) ($item['unit_price'] ?? 0);
            $quantity = (float) ($item['quantity'] ?? 0);
            $taxGroupId = $item['tax_group_id'] ?? $product->tax_group_id;
            $taxType = $this->normalizeTaxType($item['tax_type'] ?? $product->tax_type);
            $taxValuePerUnit = $this->computeTaxValuePerUnit($taxType, $taxGroupId, $unitPrice);

            if ($taxType === 'inclusive') {
                $netPurchasePrice = $unitPrice - $taxValuePerUnit;
                $grossPurchasePrice = $unitPrice;
                $purchasePrice = $grossPurchasePrice;
            } else {
                $grossPurchasePrice = $unitPrice + $taxValuePerUnit;
                $netPurchasePrice = $unitPrice;
                $purchasePrice = $grossPurchasePrice;
            }

            return [
                'product_id' => (int) $product->id,
                'quantity' => $quantity,
                'gross_purchase_price' => (float) $grossPurchasePrice,
                'net_purchase_price' => (float) $netPurchasePrice,
                'purchase_price' => (float) $purchasePrice,
                'total_purchase_price' => (float) ($purchasePrice * $quantity),
                'tax_group_id' => $taxGroupId ? (int) $taxGroupId : 0,
                'tax_type' => $taxType,
                'tax_value' => (float) ($taxValuePerUnit * $quantity),
                'unit_id' => (int) $unitQuantity->unit_id,
                'convert_unit_id' => $unitQuantity->convert_unit_id ?? null,
                'expiration_date' => $item['expiration_date'] ?? null,
                'purchase_unit_type' => 'unit',
            ];
        })->toArray();
    }

    private function resolveUnitQuantity(int $productId, ?int $unitId): ?ProductUnitQuantity
    {
        if ($unitId !== null) {
            $unitQuantity = ProductUnitQuantity::query()
                ->where('product_id', $productId)
                ->where('unit_id', $unitId)
                ->first();

            if ($unitQuantity instanceof ProductUnitQuantity) {
                return $unitQuantity;
            }
        }

        return ProductUnitQuantity::query()
            ->where('product_id', $productId)
            ->orderBy('id')
            ->first();
    }

    private function computeTaxValuePerUnit(string $taxType, ?int $taxGroupId, float $unitPrice): float
    {
        if (! $taxGroupId) {
            return 0.0;
        }

        $taxGroup = TaxGroup::with('taxes')->find($taxGroupId);
        if (! $taxGroup) {
            return 0.0;
        }

        return (float) $taxGroup->taxes
            ->map(fn ($tax) => $this->taxService->getVatValue($taxType, (float) $tax->rate, $unitPrice))
            ->sum();
    }

    private function normalizeTaxType(?string $type): string
    {
        $value = strtolower(trim((string) $type));
        return in_array($value, ['inclusive', 'exclusive'], true) ? $value : 'exclusive';
    }

    private function normalizeDeliveryStatus(?string $status): string
    {
        $value = strtolower(trim((string) $status));
        return in_array($value, [Procurement::PENDING, Procurement::DELIVERED, Procurement::DRAFT], true)
            ? $value
            : Procurement::PENDING;
    }

    private function normalizePaymentStatus(?string $status): string
    {
        $value = strtolower(trim((string) $status));
        return in_array($value, [Procurement::PAYMENT_PAID, Procurement::PAYMENT_UNPAID], true)
            ? $value
            : Procurement::PAYMENT_UNPAID;
    }

    private function normalizeDateTime(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse((string) $value)->format('Y-m-d H:i:s');
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function loadProcurementRelations(Procurement $procurement): void
    {
        $procurement->load([
            'provider:id,first_name,last_name,email,phone,address_1,address_2',
            'products' => function ($query) {
                $query->with(['product:id,name,barcode', 'unit:id,name,identifier']);
            },
        ]);
    }

    private function resolveProvider(Procurement $procurement): ?Provider
    {
        if ($procurement->relationLoaded('provider')) {
            return $procurement->provider instanceof Provider ? $procurement->provider : null;
        }

        if (! $procurement->provider_id) {
            return null;
        }

        return Provider::find($procurement->provider_id);
    }

    private function formatProviderName(Provider $provider): string
    {
        $name = trim($provider->first_name . ' ' . $provider->last_name);
        return $name !== '' ? $name : $provider->first_name;
    }

    private function formatProviderAddress(Provider $provider): ?string
    {
        $address = trim(implode(' ', array_filter([
            $provider->address_1 ?? null,
            $provider->address_2 ?? null,
        ])));

        return $address !== '' ? $address : null;
    }

    private function requirePermission(string $permission): ?JsonResponse
    {
        if (! function_exists('ns') || ! ns()->allowedTo($permission)) {
            return response()->json([
                'status' => 'error',
                'message' => __('Forbidden.'),
            ], 403);
        }

        return null;
    }

    private function validationErrorResponse($validator): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => __('The request is not valid.'),
            'errors' => $validator->errors(),
        ], 422);
    }

    private function formatTimestamp(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            if ($value instanceof Carbon) {
                return $value->toIso8601String();
            }

            return Carbon::parse((string) $value)->toIso8601String();
        } catch (\Throwable $e) {
            return null;
        }
    }
}
