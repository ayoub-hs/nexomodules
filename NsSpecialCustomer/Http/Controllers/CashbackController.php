<?php

namespace Modules\NsSpecialCustomer\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\CustomerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\NsSpecialCustomer\Models\SpecialCashbackHistory;
use Modules\NsSpecialCustomer\Services\CashbackService;
use Modules\NsSpecialCustomer\Services\SpecialCustomerService;

class CashbackController extends Controller
{
    public function __construct(
        private readonly SpecialCustomerService $specialCustomerService,
        private readonly CustomerService $customerService,
        private readonly CashbackService $cashbackService
    ) {
    }

    /**
     * Get cashback history list
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'customer_id' => 'nullable|integer|exists:nexopos_users,id',
            'year' => 'nullable|integer|min:2000|max:2100',
            'status' => 'nullable|string|in:pending,processing,processed,reversed,failed',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = SpecialCashbackHistory::with(['customer', 'transaction']);

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->filled('year')) {
            $query->where('year', $request->year);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $perPage = $request->integer('per_page', 25);
        $cashbackHistory = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => $cashbackHistory,
        ]);
    }

    /**
     * Process cashback for a customer
     */
    public function process(Request $request): JsonResponse
    {
        $request->validate([
            'customer_id' => 'required|integer|exists:nexopos_users,id',
            'year' => 'required|integer|min:2000|max:2100',
            'description' => 'nullable|string|max:255',
        ]);

        try {
            $result = $this->cashbackService->processCustomerCashback(
                customerId: $request->integer('customer_id'),
                year: $request->integer('year'),
                description: $request->input('description')
            );

            if ($result['success']) {
                return response()->json([
                    'status' => 'success',
                    'message' => $result['message'],
                    'data' => $result,
                ]);
            }

            return response()->json([
                'status' => 'error',
                'message' => $result['message'],
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get cashback summary for a customer
     */
    public function customerSummary(int $customerId): JsonResponse
    {
        try {
            $customer = Customer::findOrFail($customerId);

            // Check if customer is special customer
            if (! $this->specialCustomerService->isSpecialCustomer($customer)) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('Customer is not a special customer.'),
                ], 400);
            }

            $history = $this->cashbackService->getCustomerCashbackHistory($customerId);
            $stats = $this->cashbackService->getCashbackStatistics();

            // Calculate customer-specific totals
            $totalCashback = SpecialCashbackHistory::where('customer_id', $customerId)
                ->where('status', SpecialCashbackHistory::STATUS_PROCESSED)
                ->sum('cashback_amount');

            $totalReversed = SpecialCashbackHistory::where('customer_id', $customerId)
                ->where('status', SpecialCashbackHistory::STATUS_REVERSED)
                ->sum('cashback_amount');

            return response()->json([
                'status' => 'success',
                'data' => [
                    'customer' => $customer,
                    'is_special_customer' => true,
                    'total_cashback' => $totalCashback,
                    'total_reversed' => $totalReversed,
                    'net_cashback' => $totalCashback - $totalReversed,
                    'history' => $history,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Delete/reverse cashback record
     */
    public function delete(int $id): JsonResponse
    {
        try {
            $result = $this->cashbackService->reverseCashback(
                cashbackHistoryId: $id,
                reason: 'Deleted by administrator'
            );

            return response()->json([
                'status' => 'success',
                'message' => $result['message'],
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get cashback statistics
     */
    public function getStatistics(Request $request): JsonResponse
    {
        $request->validate([
            'year' => 'nullable|integer|min:2000|max:2100',
        ]);

        try {
            $year = $request->filled('year') ? $request->integer('year') : null;
            $stats = $this->cashbackService->getCashbackStatistics($year);

            // Get yearly breakdown if no specific year requested
            $yearlyBreakdown = [];
            if (! $year) {
                $years = SpecialCashbackHistory::selectRaw('DISTINCT year')
                    ->orderBy('year', 'desc')
                    ->pluck('year');

                foreach ($years as $y) {
                    $yearlyBreakdown[$y] = SpecialCashbackHistory::getYearStatistics($y);
                }
            }

            return response()->json([
                'status' => 'success',
                'data' => [
                    'statistics' => $stats,
                    'yearly_breakdown' => $yearlyBreakdown,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
