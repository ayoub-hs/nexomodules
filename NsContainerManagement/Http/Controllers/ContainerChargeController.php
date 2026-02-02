<?php

namespace Modules\NsContainerManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\NsContainerManagement\Models\ContainerType;
use Modules\NsContainerManagement\Models\CustomerContainerBalance;
use Modules\NsContainerManagement\Services\ContainerLedgerService;

class ContainerChargeController extends Controller
{
    public function __construct(
        protected ContainerLedgerService $ledgerService
    ) {}

    /**
     * GET /api/container-management/charge/preview/{customerId}
     * Preview what would be charged
     */
    public function preview(int $customerId): JsonResponse
    {
        $balances = CustomerContainerBalance::with('containerType')
            ->forCustomer($customerId)
            ->withBalance()
            ->get();

        $items = $balances->map(function ($balance) {
            return [
                'container_type_id' => $balance->container_type_id,
                'container_type_name' => $balance->containerType->name,
                'quantity' => $balance->balance,
                'deposit_fee' => $balance->containerType->deposit_fee,
                'total' => $balance->balance * $balance->containerType->deposit_fee,
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => [
                'items' => $items,
                'total_charge' => $items->sum('total'),
            ],
        ]);
    }

    /**
     * POST /api/container-management/charge
     * Charge customer for unreturned containers - creates POS transaction
     */
    public function charge(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:nexopos_users,id',
            'container_type_id' => 'required|exists:ns_container_types,id',
            'quantity' => 'required|integer|min:1',
            'note' => 'nullable|string',
        ]);

        // Verify customer has this balance
        $balance = CustomerContainerBalance::forCustomer($validated['customer_id'])
            ->where('container_type_id', $validated['container_type_id'])
            ->first();

        if (!$balance || $balance->balance < $validated['quantity']) {
            return response()->json([
                'status' => 'error',
                'message' => __('Customer does not have enough container balance to charge'),
            ], 422);
        }

        $result = $this->ledgerService->chargeCustomerForContainers(
            customerId: $validated['customer_id'],
            containerTypeId: $validated['container_type_id'],
            quantity: $validated['quantity'],
            note: $validated['note'] ?? null
        );

        return response()->json([
            'status' => 'success',
            'message' => __('Customer charged successfully. Order created.'),
            'data' => [
                'movement' => $result['movement'],
                'order_id' => $result['order']->id,
                'order_code' => $result['order']->code,
                'total_charged' => $result['total_charged'],
            ],
        ], 201);
    }

    /**
     * POST /api/container-management/charge/all
     * Charge customer for ALL unreturned containers
     */
    public function chargeAll(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:nexopos_users,id',
            'note' => 'nullable|string',
        ]);

        $balances = CustomerContainerBalance::forCustomer($validated['customer_id'])
            ->withBalance()
            ->get();

        if ($balances->isEmpty()) {
            return response()->json([
                'status' => 'error',
                'message' => __('Customer has no outstanding container balance'),
            ], 422);
        }

        $results = [];
        $totalCharged = 0;

        foreach ($balances as $balance) {
            $result = $this->ledgerService->chargeCustomerForContainers(
                customerId: $validated['customer_id'],
                containerTypeId: $balance->container_type_id,
                quantity: $balance->balance,
                note: $validated['note'] ?? null
            );
            $results[] = $result;
            $totalCharged += $result['total_charged'];
        }

        return response()->json([
            'status' => 'success',
            'message' => __('Customer charged for all outstanding containers'),
            'data' => [
                'charges' => count($results),
                'total_charged' => $totalCharged,
            ],
        ], 201);
    }
}
