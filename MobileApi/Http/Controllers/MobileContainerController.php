<?php

namespace Modules\MobileApi\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\NsContainerManagement\Models\ContainerInventory;
use Modules\NsContainerManagement\Models\ContainerMovement;
use Modules\NsContainerManagement\Models\ContainerType;
use Modules\NsContainerManagement\Models\CustomerContainerBalance;
use Modules\NsContainerManagement\Services\ContainerLedgerService;
use Modules\NsContainerManagement\Services\ContainerService;

class MobileContainerController extends Controller
{
    public function __construct(
        protected ContainerService $containerService,
        protected ContainerLedgerService $ledgerService
    ) {
    }

    public function types(Request $request): JsonResponse
    {
        $types = ContainerType::query()
            ->with('inventory')
            ->when($request->boolean('active_only'), fn($query) => $query->active())
            ->orderBy('name')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $types->map(fn(ContainerType $type) => $this->transformType($type))->values(),
        ]);
    }

    public function inventory(Request $request): JsonResponse
    {
        $items = ContainerInventory::query()
            ->with('containerType')
            ->orderBy('container_type_id')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $items->map(fn(ContainerInventory $inventory) => $this->transformInventory($inventory))->values(),
        ]);
    }

    public function adjust(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'container_type_id' => 'required|exists:ns_container_types,id',
            'adjustment' => 'required|integer|not_in:0',
            'reason' => 'nullable|string|max:255',
        ]);

        ContainerMovement::create([
            'container_type_id' => $validated['container_type_id'],
            'customer_id' => null,
            'order_id' => null,
            'direction' => ContainerMovement::DIRECTION_ADJUSTMENT,
            'quantity' => $validated['adjustment'],
            'unit_deposit_fee' => 0,
            'total_deposit_value' => 0,
            'source_type' => ContainerMovement::SOURCE_INVENTORY_ADJUSTMENT,
            'note' => $validated['reason'] ?? null,
            'author' => $request->user()?->id ?? 0,
        ]);

        $inventory = ContainerInventory::query()
            ->where('container_type_id', $validated['container_type_id'])
            ->firstOrFail()
            ->refresh();

        return response()->json([
            'status' => 'success',
            'message' => __('Inventory adjusted successfully'),
            'data' => $this->transformInventory($inventory),
        ]);
    }

    public function balances(Request $request): JsonResponse
    {
        $limit = max(1, min((int) $request->query('limit', 50), 100));
        $offset = max(0, (int) $request->query('offset', 0));
        $customerId = $request->query('customer_id');
        $search = trim((string) $request->query('q', ''));

        $query = CustomerContainerBalance::query()
            ->with(['customer', 'containerType'])
            ->when($request->boolean('with_balance_only', true), fn($builder) => $builder->withBalance())
            ->when($customerId !== null && $customerId !== '', fn($builder) => $builder->where('customer_id', (int) $customerId))
            ->when($search !== '', function ($builder) use ($search) {
                $builder->where(function ($inner) use ($search) {
                    $inner->whereHas('customer', function ($customerQuery) use ($search) {
                        $customerQuery
                            ->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    })->orWhereHas('containerType', function ($typeQuery) use ($search) {
                        $typeQuery->where('name', 'like', "%{$search}%");
                    });
                });
            })
            ->orderByDesc('balance')
            ->orderBy('id');

        $total = (clone $query)->count();
        $rows = $query->offset($offset)->limit($limit + 1)->get();
        $hasMore = $rows->count() > $limit;

        return response()->json([
            'status' => 'success',
            'message' => null,
            'data' => $rows
                ->take($limit)
                ->map(fn(CustomerContainerBalance $balance) => $this->transformBalance($balance))
                ->values(),
            'meta' => [
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset,
                'has_more' => $hasMore,
            ],
        ]);
    }

    public function receive(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:nexopos_users,id',
            'container_type_id' => 'required|exists:ns_container_types,id',
            'quantity' => 'required|integer|min:1',
            'note' => 'nullable|string|max:500',
        ]);

        $movement = $this->ledgerService->recordContainerIn(
            customerId: $validated['customer_id'],
            containerTypeId: $validated['container_type_id'],
            quantity: $validated['quantity'],
            sourceType: ContainerMovement::SOURCE_MANUAL_RETURN,
            note: $validated['note'] ?? null
        );

        return response()->json([
            'status' => 'success',
            'message' => __('Containers received from customer successfully'),
            'data' => $this->transformMovement($movement->load(['containerType', 'customer'])),
        ], 201);
    }

    public function movements(Request $request): JsonResponse
    {
        $limit = max(1, min((int) $request->query('limit', 50), 100));
        $offset = max(0, (int) $request->query('offset', 0));
        $direction = $this->normalizeDirection($request->query('type'));
        $search = trim((string) $request->query('q', ''));

        $query = ContainerMovement::query()
            ->with(['containerType', 'customer', 'order'])
            ->when($request->filled('customer_id'), fn($builder) => $builder->where('customer_id', (int) $request->query('customer_id')))
            ->when($request->filled('container_type_id'), fn($builder) => $builder->where('container_type_id', (int) $request->query('container_type_id')))
            ->when($direction !== null, fn($builder) => $builder->where('direction', $direction))
            ->when($request->filled('from_date'), fn($builder) => $builder->where('created_at', '>=', $request->date('from_date')))
            ->when($request->filled('to_date'), fn($builder) => $builder->where('created_at', '<=', $request->date('to_date')->endOfDay()))
            ->when($search !== '', function ($builder) use ($search) {
                $builder->where(function ($inner) use ($search) {
                    $inner->where('note', 'like', "%{$search}%")
                        ->orWhere('source_type', 'like', "%{$search}%")
                        ->orWhereHas('containerType', fn($typeQuery) => $typeQuery->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('customer', function ($customerQuery) use ($search) {
                            $customerQuery
                                ->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%")
                                ->orWhere('phone', 'like', "%{$search}%");
                        });
                });
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $total = (clone $query)->count();
        $rows = $query->offset($offset)->limit($limit + 1)->get();
        $hasMore = $rows->count() > $limit;

        return response()->json([
            'status' => 'success',
            'message' => null,
            'data' => $rows
                ->take($limit)
                ->map(fn(ContainerMovement $movement) => $this->transformMovement($movement))
                ->values(),
            'meta' => [
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset,
                'has_more' => $hasMore,
            ],
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        $limit = max(1, min((int) $request->query('limit', 50), 100));
        $offset = max(0, (int) $request->query('offset', 0));

        $query = ContainerMovement::query()
            ->with('containerType')
            ->where('direction', ContainerMovement::DIRECTION_ADJUSTMENT)
            ->when($request->filled('container_type_id'), fn($builder) => $builder->where('container_type_id', (int) $request->query('container_type_id')))
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $total = (clone $query)->count();
        $rows = $query->offset($offset)->limit($limit + 1)->get();
        $hasMore = $rows->count() > $limit;

        return response()->json([
            'status' => 'success',
            'message' => null,
            'data' => $rows
                ->take($limit)
                ->map(function (ContainerMovement $movement) {
                    $quantity = abs((int) $movement->quantity);

                    return [
                        'id' => (int) $movement->id,
                        'container_type_id' => (int) $movement->container_type_id,
                        'container_type_name' => $movement->containerType?->name ?? __('Unknown'),
                        'operation' => $movement->quantity >= 0 ? 'add' : 'remove',
                        'quantity' => $quantity,
                        'previous_quantity' => 0,
                        'new_quantity' => 0,
                        'reason' => $movement->note,
                        'created_at' => $movement->created_at?->format('Y-m-d H:i:s'),
                        'created_by' => $movement->author ? (string) $movement->author : null,
                    ];
                })
                ->values(),
            'meta' => [
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset,
                'has_more' => $hasMore,
            ],
        ]);
    }

    public function previewCharge(int $customerId): JsonResponse
    {
        $customer = Customer::findOrFail($customerId);
        $balances = CustomerContainerBalance::query()
            ->with('containerType')
            ->forCustomer($customerId)
            ->withBalance()
            ->get();

        $items = $balances->map(function (CustomerContainerBalance $balance) {
            $depositAmount = (float) ($balance->containerType?->deposit_fee ?? 0);

            return [
                'container_type_id' => (int) $balance->container_type_id,
                'container_type_name' => $balance->containerType?->name ?? __('Unknown'),
                'quantity' => (int) $balance->balance,
                'deposit_amount' => $depositAmount,
                'total_charge' => (float) $balance->deposit_value,
            ];
        })->values();

        return response()->json([
            'status' => 'success',
            'message' => null,
            'data' => [
                'customer_id' => (int) $customer->id,
                'customer_name' => $this->customerName($customer),
                'items' => $items,
                'total_charge' => (float) $items->sum('total_charge'),
                'containers_held' => (int) $items->sum('quantity'),
            ],
        ]);
    }

    public function charge(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:nexopos_users,id',
            'items' => 'required|array|min:1',
            'items.*.container_type_id' => 'required|exists:ns_container_types,id',
            'items.*.quantity' => 'required|integer|min:1',
            'notes' => 'nullable|string|max:500',
        ]);

        $results = [];
        foreach ($validated['items'] as $item) {
            $results[] = $this->ledgerService->chargeCustomerForContainers(
                customerId: $validated['customer_id'],
                containerTypeId: (int) $item['container_type_id'],
                quantity: (int) $item['quantity'],
                note: $validated['notes'] ?? null
            );
        }

        $lastMovement = $results !== [] ? $results[array_key_last($results)]['movement'] : null;

        return response()->json([
            'status' => 'success',
            'message' => __('Customer charged successfully. Order created.'),
            'data' => [
                'transaction_id' => $lastMovement?->id ? (int) $lastMovement->id : 0,
                'customer_id' => (int) $validated['customer_id'],
                'total_charged' => (float) collect($results)->sum('total_charged'),
                'containers_processed' => (int) collect($validated['items'])->sum('quantity'),
                'created_at' => now()->format('Y-m-d H:i:s'),
            ],
        ], 201);
    }

    private function transformType(ContainerType $type): array
    {
        return [
            'id' => (int) $type->id,
            'name' => $type->name,
            'capacity' => (float) $type->capacity,
            'capacity_unit' => $type->capacity_unit,
            'deposit_fee' => (float) $type->deposit_fee,
            'description' => $type->description,
            'is_active' => (bool) $type->is_active,
            'inventory' => $type->inventory ? $this->transformInventory($type->inventory) : null,
        ];
    }

    private function transformInventory(ContainerInventory $inventory): array
    {
        return [
            'id' => (int) $inventory->id,
            'container_type_id' => (int) $inventory->container_type_id,
            'total_quantity' => (int) $inventory->quantity_on_hand,
            'available_quantity' => (int) $inventory->available_quantity,
            'in_circulation' => (int) $inventory->quantity_reserved,
        ];
    }

    private function transformBalance(CustomerContainerBalance $balance): array
    {
        return [
            'customer_id' => (int) $balance->customer_id,
            'customer_name' => $this->customerName($balance->customer),
            'container_type_id' => (int) $balance->container_type_id,
            'container_type_name' => $balance->containerType?->name ?? __('Unknown'),
            'quantity_held' => (int) $balance->balance,
            'deposit_total' => (float) $balance->deposit_value,
            'last_transaction_at' => $balance->last_movement_at?->format('Y-m-d H:i:s'),
        ];
    }

    private function transformMovement(ContainerMovement $movement): array
    {
        return [
            'id' => (int) $movement->id,
            'container_type_id' => (int) $movement->container_type_id,
            'container_type_name' => $movement->containerType?->name ?? __('Unknown'),
            'customer_id' => $movement->customer_id ? (int) $movement->customer_id : null,
            'customer_name' => $movement->customer ? $this->customerName($movement->customer) : null,
            'type' => $movement->direction,
            // Keep the sign for inventory adjustments so the mobile app can render
            // stock increases and decreases correctly in movement history.
            'quantity' => $movement->direction === ContainerMovement::DIRECTION_ADJUSTMENT
                ? (int) $movement->quantity
                : abs((int) $movement->quantity),
            'notes' => $movement->note,
            'created_at' => $movement->created_at?->format('Y-m-d H:i:s'),
            'created_by' => $movement->author ? (string) $movement->author : null,
        ];
    }

    private function normalizeDirection(mixed $type): ?string
    {
        return match (strtolower(trim((string) $type))) {
            'receive', 'received', 'in' => ContainerMovement::DIRECTION_IN,
            'give', 'given', 'out', 'dispatch', 'dispatched' => ContainerMovement::DIRECTION_OUT,
            'adjust', 'adjusted', 'adjustment' => ContainerMovement::DIRECTION_ADJUSTMENT,
            'charge', 'charged' => ContainerMovement::DIRECTION_CHARGE,
            '', 'all' => null,
            default => trim((string) $type) !== '' ? trim((string) $type) : null,
        };
    }

    private function customerName(?Customer $customer): string
    {
        if (!$customer) {
            return __('Unknown');
        }

        $fullName = trim(implode(' ', array_filter([
            $customer->first_name ?? null,
            $customer->last_name ?? null,
        ])));

        return $fullName !== '' ? $fullName : (string) ($customer->username ?? $customer->email ?? $customer->id);
    }
}
