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
     */
    public function recordContainerOut(
        int $customerId,
        int $containerTypeId,
        int $quantity,
        ?int $orderId = null,
        string $sourceType = ContainerMovement::SOURCE_MANUAL_GIVE,
        ?string $note = null
    ): ContainerMovement {
        $containerType = ContainerType::findOrFail($containerTypeId);

        return ContainerMovement::create([
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
    }

    /**
     * Record containers coming IN from customer
     */
    public function recordContainerIn(
        int $customerId,
        int $containerTypeId,
        int $quantity,
        string $sourceType = ContainerMovement::SOURCE_MANUAL_RETURN,
        ?string $note = null
    ): ContainerMovement {
        $containerType = ContainerType::findOrFail($containerTypeId);

        return ContainerMovement::create([
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
    }

    /**
     * Handle the side effects of a movement (Inventory & Balance)
     * This is triggered by ContainerMovement model created event
     */
    public function handleMovementEffect(ContainerMovement $movement): void
    {
        $qty = $movement->quantity;
        $typeId = $movement->container_type_id;
        $customerId = $movement->customer_id;

        switch ($movement->direction) {
            case ContainerMovement::DIRECTION_OUT:
                $this->updateCustomerBalance($customerId, $typeId, out: $qty);
                $this->adjustInventoryQuantity($typeId, -$qty);
                break;

            case ContainerMovement::DIRECTION_IN:
                $this->updateCustomerBalance($customerId, $typeId, in: $qty);
                $this->adjustInventoryQuantity($typeId, $qty);
                break;

            case ContainerMovement::DIRECTION_CHARGE:
                $this->updateCustomerBalance($customerId, $typeId, charged: $qty);
                break;

            case ContainerMovement::DIRECTION_ADJUSTMENT:
                $this->adjustInventoryQuantity($typeId, $qty);
                break;
        }
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
