<?php

namespace Modules\NsContainerManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\NsContainerManagement\Models\ContainerMovement;
use Modules\NsContainerManagement\Models\ContainerType;
use Modules\NsContainerManagement\Models\CustomerContainerBalance;
use Modules\NsContainerManagement\Services\ContainerLedgerService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContainerReportController extends Controller
{
    public function __construct(
        protected ContainerLedgerService $ledgerService
    ) {}

    public function getSummary(Request $request): JsonResponse
    {
        $query = ContainerMovement::query();
        
        if ($request->from) {
            $query->whereDate('created_at', '>=', $request->from);
        }
        if ($request->to) {
            $query->whereDate('created_at', '<=', $request->to);
        }

        $movements = $query->select(
            DB::raw('SUM(CASE WHEN direction = "out" THEN quantity ELSE 0 END) as total_out'),
            DB::raw('SUM(CASE WHEN direction = "in" THEN quantity ELSE 0 END) as total_in'),
            DB::raw('SUM(total_deposit_value) as total_deposit')
        )->first();

        $balanceQuery = CustomerContainerBalance::query();
        if ($request->from) {
            $balanceQuery->whereDate('updated_at', '>=', $request->from);
        }
        if ($request->to) {
            $balanceQuery->whereDate('updated_at', '<=', $request->to);
        }

        $balanceCount = $balanceQuery->where('balance', '>', 0)->count();
        $outstandingQty = $balanceQuery->sum('balance');

        return response()->json([
            'total_out' => (int) ($movements->total_out ?? 0),
            'total_in' => (int) ($movements->total_in ?? 0),
            'outstanding' => (int) $outstandingQty,
            'active_customers' => $balanceCount,
            'total_deposit' => (float) ($movements->total_deposit ?? 0),
        ]);
    }

    public function getMovements(Request $request): JsonResponse
    {
        $result = $this->ledgerService->getMovements(
            $request->get('from'),
            $request->get('to'),
            $request->get('page', 1),
            $request->get('per_page', 20),
            $request->get('customer_id'),
            $request->get('type_id')
        );

        return response()->json($result);
    }

    public function getCustomerBalances(Request $request): JsonResponse
    {
        $result = $this->ledgerService->getBalances(
            $request->get('from'),
            $request->get('to'),
            $request->get('page', 1),
            $request->get('per_page', 20),
            $request->get('customer_id'),
            $request->get('type_id')
        );

        return response()->json($result);
    }

    public function getCharges(Request $request): JsonResponse
    {
        $query = ContainerMovement::where('direction', 'charge')
            ->with(['containerType', 'customer']);
        
        // Apply filters
        if ($request->from) {
            $query->whereDate('created_at', '>=', $request->from);
        }
        if ($request->to) {
            $query->whereDate('created_at', '<=', $request->to);
        }
        if ($request->customer_id) {
            $query->where('customer_id', $request->customer_id);
        }
        if ($request->type_id) {
            $query->where('container_type_id', $request->type_id);
        }

        $perPage = $request->get('per_page', 20);
        $page = $request->get('page', 1);

        $charges = $query->orderByDesc('created_at')
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'data' => $charges->getCollection()->map(function ($charge) {
                return [
                    'date' => $charge->created_at->format('Y-m-d H:i'),
                    'customer' => $charge->customer->first_name . ' ' . $charge->customer->last_name,
                    'container' => $charge->containerType->name ?? 'Unknown',
                    'quantity' => $charge->quantity,
                    'amount' => $charge->total_deposit_value,
                    'type' => $charge->source_type,
                    'notes' => $charge->note
                ];
            })->toArray(),
            'total' => $charges->total(),
            'per_page' => $perPage,
            'current_page' => $charges->currentPage(),
            'last_page' => $charges->lastPage(),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $type = $request->get('type', 'movements');
        
        if ($type === 'charges') {
            return $this->exportCharges($request);
        } elseif ($type === 'balances') {
            return $this->exportBalances($request);
        } else {
            return $this->exportMovements($request);
        }
    }

    private function exportMovements(Request $request)
    {
        $query = ContainerMovement::with(['containerType', 'customer'])
            ->orderBy('created_at', 'desc');

        // Apply same filters as movements
        if ($request->from) {
            $query->whereDate('created_at', '>=', $request->from);
        }
        if ($request->to) {
            $query->whereDate('created_at', '<=', $request->to);
        }
        if ($request->customer_id) {
            $query->where('customer_id', $request->customer_id);
        }
        if ($request->type_id) {
            $query->where('container_type_id', $request->type_id);
        }

        $movements = $query->get();

        $headers = ['Date', 'Customer', 'Container Type', 'Direction', 'Quantity', 'Source Type', 'Note'];
        $rows = $movements->map(function ($movement) {
            return [
                $movement->created_at->format('Y-m-d H:i:s'),
                $movement->customer ? $movement->customer->first_name . ' ' . $movement->customer->last_name : 'N/A',
                $movement->containerType ? $movement->containerType->name : 'Unknown',
                $movement->direction,
                $movement->quantity,
                $movement->source_type,
                $movement->note ?? '',
            ];
        })->toArray();

        $callback = function () use ($headers, $rows) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $headers);
            foreach ($rows as $row) {
                fputcsv($file, $row);
            }
            fclose($file);
        };

        return new StreamedResponse($callback, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="container-movements.csv"',
        ]);
    }

    private function exportCharges(Request $request)
    {
        $query = ContainerMovement::where('direction', 'charge')
            ->with(['containerType', 'customer'])
            ->orderBy('created_at', 'desc');

        // Apply filters
        if ($request->from) {
            $query->whereDate('created_at', '>=', $request->from);
        }
        if ($request->to) {
            $query->whereDate('created_at', '<=', $request->to);
        }
        if ($request->customer_id) {
            $query->where('customer_id', $request->customer_id);
        }
        if ($request->type_id) {
            $query->where('container_type_id', $request->type_id);
        }

        $charges = $query->get();

        $headers = ['Date', 'Customer', 'Container Type', 'Quantity', 'Amount', 'Notes'];
        $rows = $charges->map(function ($charge) {
            return [
                $charge->created_at->format('Y-m-d H:i:s'),
                $charge->customer ? $charge->customer->first_name . ' ' . $charge->customer->last_name : 'N/A',
                $charge->containerType ? $charge->containerType->name : 'Unknown',
                $charge->quantity,
                $charge->total_deposit_value,
                $charge->note ?? '',
            ];
        })->toArray();

        $callback = function () use ($headers, $rows) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $headers);
            foreach ($rows as $row) {
                fputcsv($file, $row);
            }
            fclose($file);
        };

        return new StreamedResponse($callback, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="container-charges.csv"',
        ]);
    }

    private function exportBalances(Request $request)
    {
        $query = CustomerContainerBalance::with(['customer', 'containerType'])
            ->orderBy('last_movement_at', 'desc');

        // Apply filters
        if ($request->customer_id) {
            $query->where('customer_id', $request->customer_id);
        }
        if ($request->type_id) {
            $query->where('container_type_id', $request->type_id);
        }

        $balances = $query->get();

        $headers = ['Customer', 'Container Type', 'Balance', 'Total Out', 'Total In', 'Total Charged', 'Last Movement'];
        $rows = $balances->map(function ($balance) {
            return [
                $balance->customer ? $balance->customer->first_name . ' ' . $balance->customer->last_name : 'N/A',
                $balance->containerType ? $balance->containerType->name : 'Unknown',
                $balance->balance,
                $balance->total_out,
                $balance->total_in,
                $balance->total_charged,
                $balance->last_movement_at ? $balance->last_movement_at->format('Y-m-d H:i:s') : 'Never',
            ];
        })->toArray();

        $callback = function () use ($headers, $rows) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $headers);
            foreach ($rows as $row) {
                fputcsv($file, $row);
            }
            fclose($file);
        };

        return new StreamedResponse($callback, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="container-balances.csv"',
        ]);
    }
}
