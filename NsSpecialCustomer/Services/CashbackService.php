<?php

namespace Modules\NsSpecialCustomer\Services;

use App\Models\Customer;
use App\Models\CustomerAccountHistory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Modules\NsSpecialCustomer\Contracts\CashbackServiceInterface;
use Modules\NsSpecialCustomer\Exceptions\CashbackAlreadyProcessedException;
use Modules\NsSpecialCustomer\Exceptions\CustomerNotFoundException;
use Modules\NsSpecialCustomer\Models\SpecialCashbackHistory;

class CashbackService implements CashbackServiceInterface
{
    public function __construct(
        private SpecialCustomerService $specialCustomerService,
        private WalletService $walletService
    ) {
    }

    /**
     * Calculate yearly cashback for a customer
     */
    public function calculateYearlyCashback(int $customerId, int $year): array
    {
        $customer = Customer::find($customerId);
        if (! $customer) {
            return [
                'eligible' => false,
                'reason' => __('Customer not found'),
                'total_purchases' => 0,
                'cashback_amount' => 0,
                'cashback_percentage' => 0,
            ];
        }

        if (! $this->specialCustomerService->isSpecialCustomer($customer)) {
            return [
                'eligible' => false,
                'reason' => __('Customer is not a special customer'),
                'total_purchases' => 0,
                'cashback_amount' => 0,
                'cashback_percentage' => 0,
            ];
        }

        // Read current cashback percentage directly to avoid stale cache during tests
        $cashbackPercentage = $this->specialCustomerService->getCashbackPercentage();

        if ($cashbackPercentage <= 0) {
            return [
                'eligible' => false,
                'reason' => __('Cashback is not enabled'),
                'total_purchases' => 0,
                'cashback_amount' => 0,
                'cashback_percentage' => 0,
            ];
        }

        // Calculate total purchases for the year
        $startDate = "{$year}-01-01 00:00:00";
        $endDate = "{$year}-12-31 23:59:59";

        // Preferred: get purchases from paid orders minus refunded totals
        $totalPurchases = DB::table('nexopos_orders')
            ->where('customer_id', $customerId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('payment_status', 'paid')
            ->sum('total');

        $totalRefunds = DB::table('nexopos_orders')
            ->where('customer_id', $customerId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->whereIn('payment_status', ['refunded', 'partially_refunded'])
            ->sum('total');

        $netPurchases = max(0, $totalPurchases - $totalRefunds);

        // Fallback for tests or environments without orders data: derive from account history
        if ($netPurchases <= 0) {
            $purchasesFromHistory = CustomerAccountHistory::where('customer_id', $customerId)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->where(function ($q) {
                    $q->whereIn('operation', [
                        CustomerAccountHistory::OPERATION_PAYMENT,
                    ])
                    ->orWhereRaw("upper(operation) like '%PAYMENT%'");
                })
                ->sum('amount');

            $refundsFromHistory = CustomerAccountHistory::where('customer_id', $customerId)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->where(function ($q) {
                    $q->whereIn('operation', [
                        CustomerAccountHistory::OPERATION_REFUND,
                    ])
                    ->orWhereRaw("upper(operation) like '%REFUND%'");
                })
                ->sum('amount');

            $totalPurchases = max(0, (float) $purchasesFromHistory);
            $totalRefunds = abs(min(0, (float) $refundsFromHistory));
            $netPurchases = max(0, $totalPurchases - $totalRefunds);
        }
        $cashbackAmount = $netPurchases * ($cashbackPercentage / 100);

        return [
            'eligible' => true,
            'reason' => __('Customer is eligible for cashback'),
            'total_purchases' => $netPurchases,
            'total_refunds' => $totalRefunds,
            'cashback_amount' => round($cashbackAmount, 2),
            'cashback_percentage' => $cashbackPercentage,
            'year' => $year,
        ];
    }

    /**
     * Process cashback for a single customer
     */
    public function processCustomerCashback(int $customerId, int $year, ?string $description = null): array
    {
        return DB::transaction(function () use ($customerId, $year, $description) {
            $calculation = $this->calculateYearlyCashback($customerId, $year);

            if (! $calculation['eligible']) {
                return [
                    'success' => false,
                    'message' => $calculation['reason'],
                    'cashback_amount' => 0,
                ];
            }

            if ($calculation['cashback_amount'] <= 0) {
                return [
                    'success' => false,
                    'message' => __('No cashback amount to process'),
                    'cashback_amount' => 0,
                ];
            }

            // Check if cashback already processed for this year
            $existingCashback = SpecialCashbackHistory::where('customer_id', $customerId)
                ->where('year', $year)
                ->whereIn('status', [SpecialCashbackHistory::STATUS_PROCESSED, SpecialCashbackHistory::STATUS_PENDING])
                ->first();

            if ($existingCashback) {
                return [
                    'success' => false,
                    'message' => __('Cashback for year :year has already been processed', ['year' => $year]),
                    'cashback_amount' => $existingCashback->cashback_amount,
                ];
            }

            // Process the cashback
            $transactionDescription = $description ?? __('Special Customer Cashback for :year', ['year' => $year]);

            $walletResult = $this->walletService->processTopup(
                $customerId,
                $calculation['cashback_amount'],
                $transactionDescription,
                'ns_special_cashback'
            );

            if (! $walletResult['success']) {
                throw new \Exception(__('Failed to process wallet top-up: :message', ['message' => $walletResult['message']]));
            }

            // Record cashback history
            $cashbackHistory = SpecialCashbackHistory::create([
                'customer_id' => $customerId,
                'year' => $year,
                'total_purchases' => $calculation['total_purchases'],
                'total_refunds' => $calculation['total_refunds'] ?? 0,
                'cashback_percentage' => $calculation['cashback_percentage'],
                'cashback_amount' => $calculation['cashback_amount'],
                'transaction_id' => $walletResult['transaction_id'],
                'status' => SpecialCashbackHistory::STATUS_PROCESSED,
                'processed_at' => now(),
                'author' => auth()->id(),
                'description' => $transactionDescription,
            ]);

            // Clear cache
            $this->clearCache($customerId, $year);

            return [
                'success' => true,
                'message' => __('Cashback processed successfully'),
                'cashback_amount' => $calculation['cashback_amount'],
                'cashback_history_id' => $cashbackHistory->id,
                'transaction_id' => $walletResult['transaction_id'],
            ];
        });
    }

    /**
     * Process cashback batch for multiple customers
     */
    public function processCashbackBatch(int $year, array $options = []): array
    {
        $specialCustomers = $this->specialCustomerService->getSpecialCustomers([], 1000);
        $results = [
            'total_customers' => count($specialCustomers['data'] ?? []),
            'processed' => 0,
            'failed' => 0,
            'skipped' => 0,
            'total_cashback' => 0,
            'errors' => [],
        ];

        foreach ($specialCustomers['data'] ?? [] as $customer) {
            try {
                $result = $this->processCustomerCashback($customer['id'], $year);

                if ($result['success']) {
                    $results['processed']++;
                    $results['total_cashback'] += $result['cashback_amount'];
                } else {
                    $results['skipped']++;
                    $results['errors'][] = [
                        'customer_id' => $customer['id'],
                        'customer_name' => ($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? ''),
                        'error' => $result['message'],
                    ];
                }
            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][] = [
                    'customer_id' => $customer['id'],
                    'customer_name' => ($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? ''),
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Get cashback report for a year
     */
    public function getCashbackReport(int $year): array
    {
        $cacheKey = "ns_special_cashback_report_{$year}";

        return Cache::remember($cacheKey, 3600, function () use ($year) {
            $cashbackHistory = SpecialCashbackHistory::where('year', $year)
                ->with('customer')
                ->get();

            $summary = [
                'year' => $year,
                'total_customers' => $cashbackHistory->count(),
                'total_purchases' => $cashbackHistory->sum('total_purchases'),
                'total_refunds' => $cashbackHistory->sum('total_refunds'),
                'total_cashback' => $cashbackHistory->where('status', SpecialCashbackHistory::STATUS_PROCESSED)->sum('cashback_amount'),
                'total_cashback_processed' => $cashbackHistory->where('status', SpecialCashbackHistory::STATUS_PROCESSED)->sum('cashback_amount'),
                'average_cashback' => $cashbackHistory->where('status', SpecialCashbackHistory::STATUS_PROCESSED)->avg('cashback_amount'),
                'status_breakdown' => $cashbackHistory->groupBy('status')->map->count(),
            ];

            $details = $cashbackHistory->map(function ($record) {
                return [
                    'customer_id' => $record->customer_id,
                    'customer_name' => $record->customer
                        ? $record->customer->first_name . ' ' . $record->customer->last_name
                        : __('Unknown'),
                    'customer_email' => $record->customer?->email,
                    'total_purchases' => $record->total_purchases,
                    'total_refunds' => $record->total_refunds,
                    'cashback_percentage' => $record->cashback_percentage,
                    'cashback_amount' => $record->cashback_amount,
                    'status' => $record->status,
                    'processed_at' => $record->processed_at,
                    'transaction_id' => $record->transaction_id,
                ];
            });

            return [
                'summary' => $summary,
                'details' => $details,
                'generated_at' => now()->toISOString(),
            ];
        });
    }

    /**
     * Get customer cashback history
     */
    public function getCustomerCashbackHistory(int $customerId, int $perPage = 20): array
    {
        return SpecialCashbackHistory::where('customer_id', $customerId)
            ->orderBy('year', 'desc')
            ->paginate($perPage)
            ->toArray();
    }

    /**
     * Reverse cashback (for corrections)
     */
    public function reverseCashback(int $cashbackHistoryId, string $reason): array
    {
        return DB::transaction(function () use ($cashbackHistoryId, $reason) {
            $cashbackHistory = SpecialCashbackHistory::findOrFail($cashbackHistoryId);

            if ($cashbackHistory->status === SpecialCashbackHistory::STATUS_REVERSED) {
                throw new \Exception(__('Cashback has already been reversed'));
            }

            if ($cashbackHistory->status !== SpecialCashbackHistory::STATUS_PROCESSED) {
                throw new \Exception(__('Only processed cashback can be reversed'));
            }

            // Create reversal transaction
            $reversalResult = $this->walletService->processTopup(
                $cashbackHistory->customer_id,
                -$cashbackHistory->cashback_amount,
                __('Cashback Reversal: :reason', ['reason' => $reason]),
                'ns_special_cashback_reversal'
            );

            if (! $reversalResult['success']) {
                throw new \Exception(__('Failed to process reversal: :message', ['message' => $reversalResult['message']]));
            }

            // Update cashback history
            $cashbackHistory->update([
                'status' => SpecialCashbackHistory::STATUS_REVERSED,
                'reversed_at' => now(),
                'reversal_reason' => $reason,
                'reversal_transaction_id' => $reversalResult['transaction_id'],
                'reversal_author' => auth()->id(),
            ]);

            // Clear cache
            $this->clearCache($cashbackHistory->customer_id, $cashbackHistory->year);

            return [
                'success' => true,
                'message' => __('Cashback reversed successfully'),
                'reversed_amount' => $cashbackHistory->cashback_amount,
                'reversal_transaction_id' => $reversalResult['transaction_id'],
            ];
        });
    }

    /**
     * Get cashback statistics
     */
    public function getCashbackStatistics(?int $year = null): array
    {
        $query = SpecialCashbackHistory::query();

        if ($year) {
            $query->where('year', $year);
        }

        $processedQuery = (clone $query)->where('status', SpecialCashbackHistory::STATUS_PROCESSED);
        $reversedQuery = (clone $query)->where('status', SpecialCashbackHistory::STATUS_REVERSED);

        $stats = [
            'total_processed' => $processedQuery->count(),
            'total_reversed' => $reversedQuery->count(),
            'total_amount_processed' => (float) $processedQuery->sum('cashback_amount'),
            'total_amount_reversed' => (float) $reversedQuery->sum('cashback_amount'),
            'average_cashback' => (float) ($processedQuery->avg('cashback_amount') ?? 0),
        ];

        $stats['net_amount'] = $stats['total_amount_processed'] - $stats['total_amount_reversed'];

        if ($year) {
            $stats['year'] = $year;
        }

        return $stats;
    }

    /**
     * Clear cashback cache
     */
    public function clearCache(?int $customerId = null, ?int $year = null): void
    {
        if ($customerId && $year) {
            Cache::forget("ns_special_customer_cashback_{$customerId}_{$year}");
        }

        if ($year) {
            Cache::forget("ns_special_cashback_report_{$year}");
        }
    }

    /**
     * Get cashback statistics (alias for getCashbackStatistics for interface compatibility).
     *
     * @param  int|null  $year  Optional year filter
     * @return array<string, mixed> Statistics data
     */
    public function getStatistics(?int $year = null): array
    {
        return $this->getCashbackStatistics($year);
    }

    /**
     * Check if cashback has already been processed for a customer and year.
     *
     * @param  int  $customerId  The customer ID
     * @param  int  $year  The year
     * @return bool True if already processed
     */
    public function isCashbackProcessed(int $customerId, int $year): bool
    {
        return SpecialCashbackHistory::where('customer_id', $customerId)
            ->where('year', $year)
            ->whereIn('status', [SpecialCashbackHistory::STATUS_PROCESSED, SpecialCashbackHistory::STATUS_PENDING])
            ->exists();
    }
}
