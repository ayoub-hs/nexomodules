<?php

namespace Modules\NsContainerManagement\Services;

use App\Models\Customer;
use App\Models\Order;
use App\Services\OrdersService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Modules\NsContainerManagement\Models\ContainerType;
use Modules\NsContainerManagement\Models\ContainerInventory;
use Modules\NsContainerManagement\Models\ContainerMovement;
use Modules\NsContainerManagement\Models\CustomerContainerBalance;
use Modules\NsContainerManagement\Models\ProductContainer;

class ContainerLedgerService
{
    public function __construct(
        protected OrdersService $ordersService
    ) {}

    /**
     * Record containers going OUT to customer
     * Uses transaction to ensure atomic operation with balance update
     */
    public function recordContainerOut(
        int $customerId,
        int $containerTypeId,
        int $quantity,
        ?int $orderId = null,
        string $sourceType = ContainerMovement::SOURCE_MANUAL_GIVE,
        ?string $note = null
    ): ContainerMovement {
        return DB::transaction(function () use ($customerId, $containerTypeId, $quantity, $orderId, $sourceType, $note) {
            $containerType = ContainerType::findOrFail($containerTypeId);

            $movement = ContainerMovement::create([
                'container_type_id' => $containerTypeId,
                'customer_id' => $customerId,
                'order_id' => $orderId,
                'direction' => ContainerMovement::DIRECTION_OUT,
                'quantity' => $quantity,
                'unit_deposit_fee' => $containerType->deposit_fee,
                'total_deposit_value' => $quantity * $containerType->deposit_fee,
                'source_type' => $sourceType,
                'note' => $note,
                'author' => Auth::id(),
                'created_at' => now(),
            ]);

            // Update balance and inventory within the same transaction
            $this->updateCustomerBalance($customerId, $containerTypeId, out: $quantity);
            $this->adjustInventoryQuantity($containerTypeId, -$quantity);

            return $movement;
        });
    }

    /**
     * Record containers coming IN from customer
     * Uses transaction to ensure atomic operation with balance update
     */
    public function recordContainerIn(
        int $customerId,
        int $containerTypeId,
        int $quantity,
        string $sourceType = ContainerMovement::SOURCE_MANUAL_RETURN,
        ?string $note = null
    ): ContainerMovement {
        return DB::transaction(function () use ($customerId, $containerTypeId, $quantity, $sourceType, $note) {
            $containerType = ContainerType::findOrFail($containerTypeId);

            $movement = ContainerMovement::create([
                'container_type_id' => $containerTypeId,
                'customer_id' => $customerId,
                'direction' => ContainerMovement::DIRECTION_IN,
                'quantity' => $quantity,
                'unit_deposit_fee' => $containerType->deposit_fee,
                'total_deposit_value' => $quantity * $containerType->deposit_fee,
                'source_type' => $sourceType,
                'note' => $note,
                'author' => Auth::id(),
                'created_at' => now(),
            ]);

            // Update balance and inventory within the same transaction
            $this->updateCustomerBalance($customerId, $containerTypeId, in: $quantity);
            $this->adjustInventoryQuantity($containerTypeId, $quantity);

            return $movement;
        });
    }

    /**
     * Handle the side effects of a movement (Inventory & Balance)
     * This is triggered by ContainerMovement model created event
     * 
     * NOTE: This method is now deprecated as movements are handled atomically
     * in recordContainerOut/In methods. Kept for backward compatibility.
     */
    public function handleMovementEffect(ContainerMovement $movement): void
    {
        // This method is kept for backward compatibility but should not be called
        // as movements are now handled atomically in recordContainerOut/In methods
    }

    /**
     * Charge customer for unreturned containers
     */
    public function chargeCustomerForContainers(
        int $customerId,
        int $containerTypeId,
        int $quantity,
        ?string $note = null
    ): array {
        return DB::transaction(function () use ($customerId, $containerTypeId, $quantity, $note) {
            $containerType = ContainerType::findOrFail($containerTypeId);
            $customer = Customer::findOrFail($customerId);
            $totalCharge = $quantity * $containerType->deposit_fee;

            // Create POS Order for the charge
            $orderData = [
                'customer_id' => $customerId,
                'products' => [
                    [
                        'name' => "Container Deposit Charge: {$containerType->name}",
                        'quantity' => $quantity,
                        'unit_price' => $containerType->deposit_fee,
                        'total_price' => $totalCharge,
                        'mode' => 'custom',
                    ],
                ],
                'payments' => [],
                'title' => "Container Charge - {$customer->first_name} {$customer->last_name}",
                'note' => $note ?? "Charge for unreturned {$containerType->name} containers",
            ];

            $orderResult = $this->ordersService->create($orderData);
            $order = $orderResult['data']['order'];

            // Create charge movement (will trigger handleMovementEffect)
            $movement = ContainerMovement::create([
                'container_type_id' => $containerTypeId,
                'customer_id' => $customerId,
                'order_id' => $order->id,
                'direction' => ContainerMovement::DIRECTION_CHARGE,
                'quantity' => $quantity,
                'unit_deposit_fee' => $containerType->deposit_fee,
                'total_deposit_value' => $totalCharge,
                'source_type' => ContainerMovement::SOURCE_CHARGE_TRANSACTION,
                'reference_id' => $order->id,
                'note' => $note,
                'author' => Auth::id(),
                'created_at' => now(),
            ]);

            // Update balance within the same transaction
            $this->updateCustomerBalance($customerId, $containerTypeId, charged: $quantity);

            return [
                'movement' => $movement,
                'order' => $order,
                'total_charged' => $totalCharge,
            ];
        });
    }

    /**
     * Calculate containers needed for an order product
     */
    public function calculateContainersForProduct(int $productId, float $productQuantity, ?int $unitId = null): ?array
    {
        $query = ProductContainer::with('containerType')
            ->where('product_id', $productId)
            ->where('is_enabled', true);
            
        if ($unitId !== null) {
            $query->where('unit_id', $unitId);
        }

        $productContainer = $query->first();

        // If not found with unit and unit was provided, try product-wide (unit_id = null)
        if (!$productContainer && $unitId !== null) {
            $productContainer = ProductContainer::with('containerType')
                ->where('product_id', $productId)
                ->whereNull('unit_id')
                ->where('is_enabled', true)
                ->first();
        }

        if (!$productContainer || !$productContainer->containerType->is_active) {
            return null;
        }

        $containerType = $productContainer->containerType;
        $containersNeeded = (int) floor($productQuantity / $containerType->capacity);

        return [
            'container_type_id' => $containerType->id,
            'container_type_name' => $containerType->name,
            'capacity' => $containerType->capacity,
            'capacity_unit' => $containerType->capacity_unit,
            'quantity' => $containersNeeded,
            'deposit_fee' => $containerType->deposit_fee,
            'total_deposit' => $containersNeeded * $containerType->deposit_fee,
        ];
    }

    /**
     * Get container movements for reports
     */
    public function getMovements($from = null, $to = null, $page = 1, $perPage = 20, $customerId = null, $typeId = null)
    {
        $query = DB::table('ns_container_movements as m')
            ->leftJoin('nexopos_users as c', 'c.id', '=', 'm.customer_id')
            ->leftJoin('ns_container_types as t', 't.id', '=', 'm.container_type_id')
            ->select(
                DB::raw('DATE_FORMAT(m.created_at, "%Y-%m-%d %H:%i") as date'),
                DB::raw('COALESCE(c.first_name, "N/A") as customer'),
                DB::raw('COALESCE(t.name, "Unknown") as container'),
                'm.quantity',
                'm.direction',
                'm.source_type',
                'm.note'
            )
            ->orderByDesc('m.id');

        // Apply filters
        if ($from) {
            $query->whereDate('m.created_at', '>=', $from);
        }
        if ($to) {
            $query->whereDate('m.created_at', '<=', $to);
        }
        if ($customerId) {
            $query->where('m.customer_id', $customerId);
        }
        if ($typeId) {
            $query->where('m.container_type_id', $typeId);
        }

        $paginated = $query->paginate($perPage, ['*'], 'page', $page);

        return [
            'data' => $paginated->items(),
            'total' => $paginated->total(),
            'per_page' => $paginated->perPage(),
            'current_page' => $paginated->currentPage(),
            'last_page' => $paginated->lastPage(),
        ];
    }

    /**
     * Get summarized balances for a specific customer.
     */
    public function getCustomerBalances(int $customerId): array
    {
        $balances = CustomerContainerBalance::with('containerType')
            ->where('customer_id', $customerId)
            ->orderByDesc('balance')
            ->get();

        return $balances->map(function ($b) {
            $depositFee = $b->containerType->deposit_fee ?? 0;
            return [
                'container_type_id' => $b->container_type_id,
                'container' => $b->containerType->name ?? 'Unknown',
                'balance' => $b->balance,
                'deposit_value' => $b->balance * $depositFee,
                'updated_at' => $b->updated_at,
            ];
        })->toArray();
    }

    /**
     * Get recent movements for a customer, optionally filtered by container type.
     */
    public function getCustomerMovements(int $customerId, ?int $containerTypeId = null, int $limit = 50): array
    {
        $query = ContainerMovement::with('containerType')
            ->where('customer_id', $customerId)
            ->orderByDesc('created_at');

        if ($containerTypeId) {
            $query->where('container_type_id', $containerTypeId);
        }

        return $query->limit($limit)->get()->map(function ($m) {
            return [
                'date' => $m->created_at,
                'container' => $m->containerType->name ?? 'Unknown',
                'direction' => $m->direction,
                'quantity' => $m->quantity,
                'source_type' => $m->source_type,
                'note' => $m->note,
            ];
        })->toArray();
    }

    /**
     * Get customers with outstanding balances (> 0), grouped by customer.
     */
    public function getCustomersWithOutstandingBalances(): array
    {
        $rows = DB::table('ns_customer_container_balances as b')
            ->join('nexopos_users as c', 'c.id', '=', 'b.customer_id')
            ->select(
                'b.customer_id',
                DB::raw('COALESCE(c.first_name, "Unknown") as first_name'),
                DB::raw('COALESCE(c.last_name, "") as last_name'),
                DB::raw('SUM(b.balance) as total_balance')
            )
            ->where('b.balance', '>', 0)
            ->groupBy('b.customer_id')
            ->orderByDesc('total_balance')
            ->limit(50)
            ->get();

        return $rows->map(function ($r) {
            return [
                'customer_id' => $r->customer_id,
                'customer' => trim($r->first_name . ' ' . $r->last_name),
                'balance' => (int) $r->total_balance,
            ];
        })->toArray();
    }

    /**
     * Recalculate customer balance from movements
     */
    public function recalculateCustomerBalance(int $customerId, int $containerTypeId): CustomerContainerBalance
    {
        $movements = ContainerMovement::where('customer_id', $customerId)
            ->where('container_type_id', $containerTypeId)
            ->get();

        $totalOut = $movements->where('direction', ContainerMovement::DIRECTION_OUT)->sum('quantity');
        $totalIn = $movements->where('direction', ContainerMovement::DIRECTION_IN)->sum('quantity');
        $totalCharged = $movements->where('direction', ContainerMovement::DIRECTION_CHARGE)->sum('quantity');
        
        $balance = $totalOut - $totalIn - $totalCharged;
        $lastMovement = $movements->sortByDesc('created_at')->first();

        return CustomerContainerBalance::updateOrCreate(
            [
                'customer_id' => $customerId,
                'container_type_id' => $containerTypeId,
            ],
            [
                'balance' => max(0, $balance),
                'total_out' => $totalOut,
                'total_in' => $totalIn,
                'total_charged' => $totalCharged,
                'last_movement_at' => $lastMovement?->created_at,
            ]
        );
    }

    /**
     * Get customer balances for reports
     */
    public function getBalances($from = null, $to = null, $page = 1, $perPage = 20, $customerId = null, $typeId = null)
    {
        $query = DB::table('ns_customer_container_balances as b')
            ->leftJoin('nexopos_users as c', 'c.id', '=', 'b.customer_id')
            ->leftJoin('ns_container_types as t', 't.id', '=', 'b.container_type_id')
            ->select(
                DB::raw('COALESCE(c.first_name, "Unknown") as customer'),
                DB::raw('COALESCE(t.name, "Unknown") as container'),
                'b.balance',
                'b.updated_at'
            )
            ->where('b.balance', '!=', 0)
            ->orderByDesc('b.balance');

        // Apply filters
        if ($from) {
            $query->whereDate('b.updated_at', '>=', $from);
        }
        if ($to) {
            $query->whereDate('b.updated_at', '<=', $to);
        }
        if ($customerId) {
            $query->where('b.customer_id', $customerId);
        }
        if ($typeId) {
            $query->where('b.container_type_id', $typeId);
        }

        $paginated = $query->paginate($perPage, ['*'], 'page', $page);

        return [
            'data' => $paginated->items(),
            'total' => $paginated->total(),
            'per_page' => $paginated->perPage(),
            'current_page' => $paginated->currentPage(),
            'last_page' => $paginated->lastPage(),
        ];
    }

    /**
     * Update customer balance
     */
    public function updateCustomerBalance(int $customerId, int $containerTypeId, int $out = 0, int $in = 0, int $charged = 0): CustomerContainerBalance
    {
        $balance = CustomerContainerBalance::firstOrCreate(
            [
                'customer_id' => $customerId,
                'container_type_id' => $containerTypeId,
            ],
            [
                'balance' => 0,
                'total_out' => 0,
                'total_in' => 0,
                'total_charged' => 0,
            ]
        );

        $balance->update([
            'balance' => max(0, $balance->balance + $out - $in - $charged),
            'total_out' => $balance->total_out + $out,
            'total_in' => $balance->total_in + $in,
            'total_charged' => $balance->total_charged + $charged,
            'last_movement_at' => now(),
        ]);

        return $balance;
    }

    /**
     * Adjust inventory quantity
     */
    protected function adjustInventoryQuantity(int $containerTypeId, int $adjustment): void
    {
        $inventory = ContainerInventory::firstOrCreate(
            ['container_type_id' => $containerTypeId],
            ['quantity_on_hand' => 0, 'quantity_reserved' => 0]
        );

        $inventory->update([
            'quantity_on_hand' => $inventory->quantity_on_hand + $adjustment,
        ]);
    }
}
