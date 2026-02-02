<?php

namespace Modules\NsContainerManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\NsContainerManagement\Models\CustomerContainerBalance;
use Modules\NsContainerManagement\Services\ContainerLedgerService;

class CustomerContainerController extends Controller
{
    public function __construct(
        protected ContainerLedgerService $ledgerService
    ) {}

    /**
     * GET /api/container-management/customers/balances
     */
    public function index(Request $request): JsonResponse
    {
        $query = CustomerContainerBalance::with(['customer', 'containerType'])
            ->when($request->boolean('with_balance_only'), fn ($q) => $q->withBalance())
            ->orderByDesc('balance');

        $balances = $query->paginate($request->integer('per_page', 20));

        return response()->json([
            'status' => 'success',
            'data' => $balances,
        ]);
    }

    /**
     * GET /api/container-management/customers/{id}/balance
     */
    public function show(int $customerId): JsonResponse
    {
        $customer = Customer::findOrFail($customerId);
        $balances = $this->ledgerService->getCustomerBalances($customerId);

        return response()->json([
            'status' => 'success',
            'data' => [
                'customer' => [
                    'id' => $customer->id,
                    'name' => "{$customer->first_name} {$customer->last_name}",
                    'phone' => $customer->phone,
                ],
                'balances' => $balances,
                'total_deposit_owed' => array_sum(array_column($balances, 'deposit_value')),
            ],
        ]);
    }

    /**
     * GET /api/container-management/customers/{id}/movements
     */
    public function movements(Request $request, int $customerId): JsonResponse
    {
        $movements = $this->ledgerService->getCustomerMovements(
            $customerId,
            $request->integer('container_type_id') ?: null,
            $request->integer('limit', 50)
        );

        return response()->json([
            'status' => 'success',
            'data' => $movements,
        ]);
    }

    /**
     * GET /api/container-management/customers/overdue
     */
    public function overdue(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => $this->ledgerService->getCustomersWithOutstandingBalances(),
        ]);
    }

    /**
     * GET /api/container-management/customers/search
     */
    public function search(Request $request): JsonResponse
    {
        $search = $request->string('q');
        
        $customers = Customer::query()
            ->when($search, function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            })
            ->limit(20)
            ->get(['id', 'first_name', 'last_name', 'phone'])
            ->map(fn ($c) => [
                'value' => $c->id,
                'label' => "{$c->first_name} {$c->last_name} ({$c->phone})",
            ]);

        return response()->json([
            'status' => 'success',
            'data' => $customers,
        ]);
    }
}
