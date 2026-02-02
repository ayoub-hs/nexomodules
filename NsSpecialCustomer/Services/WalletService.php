<?php

namespace Modules\NsSpecialCustomer\Services;

use App\Models\Customer;
use App\Models\CustomerAccountHistory;
use App\Services\CustomerService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Modules\NsSpecialCustomer\Contracts\WalletServiceInterface;
use Modules\NsSpecialCustomer\Exceptions\CustomerNotFoundException;
use Modules\NsSpecialCustomer\Exceptions\InsufficientBalanceException;
use Modules\NsSpecialCustomer\Exceptions\InvalidTopupAmountException;

class WalletService implements WalletServiceInterface
{
    public function __construct(
        private CustomerService $customerService,
        private AuditService $auditService
    ) {
    }

    /**
     * Process top-up with double-entry ledger and transaction safety
     */
    public function processTopup(int $customerId, float $amount, string $description, string $reference = 'ns_special_topup'): array
    {
        return DB::transaction(function () use ($customerId, $amount, $description, $reference) {
            // Validate inputs
            if ($amount == 0) {
                return [
                    'success' => false,
                    'message' => __('Amount cannot be zero'),
                    'transaction_id' => null,
                ];
            }

            $customer = Customer::query()
                ->where('id', $customerId)
                ->lockForUpdate()
                ->firstOrFail();

            $previousBalance = $customer->account_amount;
            $operation = $amount > 0
                ? CustomerAccountHistory::OPERATION_ADD
                : CustomerAccountHistory::OPERATION_DEDUCT;
            $amountValue = abs($amount);

            $result = $this->customerService->saveTransaction(
                customer: $customer,
                operation: $operation,
                amount: $amountValue,
                description: $description,
                details: [
                    'author' => auth()->id() ?? 1,
                    'reference' => $reference,
                ]
            );

            $customer->refresh();
            $newBalance = $customer->account_amount;

            // Clear customer cache
            $this->clearCustomerCache($customerId);

            $this->auditService->logTopupOperation(
                customerId: $customerId,
                amount: $amount,
                operation: $amount > 0 ? 'credit' : 'debit',
                metadata: [
                    'reference' => $reference,
                    'previous_balance' => $previousBalance,
                    'new_balance' => $newBalance,
                ]
            );

            return [
                'success' => true,
                'message' => __('Transaction processed successfully'),
                'transaction_id' => $result['data']['customerAccountHistory']->id ?? null,
                'previous_balance' => $previousBalance,
                'new_balance' => $newBalance,
                'amount' => $amount,
            ];
        });
    }

    /**
     * Get customer balance with caching
     */
    public function getBalance(int $customerId): float
    {
        return Cache::remember("ns_special_customer_balance_{$customerId}", 300, function () use ($customerId) {
            $customer = Customer::find($customerId);

            return $customer ? (float) $customer->account_amount : 0.0;
        });
    }

    /**
     * Get transaction history with filters
     */
    public function getTransactionHistory(int $customerId, array $filters = [], int $perPage = 50): array
    {
        $query = CustomerAccountHistory::where('customer_id', $customerId)
            ->orderBy('created_at', 'desc');

        // Apply filters
        if (! empty($filters['operation'])) {
            $query->where('operation', $filters['operation']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        if (! empty($filters['min_amount'])) {
            $query->where('amount', '>=', $filters['min_amount']);
        }

        if (! empty($filters['max_amount'])) {
            $query->where('amount', '<=', $filters['max_amount']);
        }

        return $query->paginate($perPage)->toArray();
    }

    /**
     * Record ledger entry with audit trail
     */
    public function recordLedgerEntry(
        int $customerId,
        float $debitAmount,
        float $creditAmount,
        string $description,
        ?int $orderId = null,
        string $reference = 'ns_special_ledger'
    ): array {
        return DB::transaction(function () use ($customerId, $debitAmount, $creditAmount, $description, $orderId, $reference) {
            if ($debitAmount > 0 && $creditAmount > 0) {
                throw new InvalidTopupAmountException(__('Cannot have both debit and credit amounts in single entry'));
            }

            $amount = $debitAmount > 0 ? -$debitAmount : $creditAmount;

            return $this->processTopup($customerId, $amount, $description, $reference);
        });
    }

    /**
     * Validate balance before operation
     */
    public function validateBalance(int $customerId, float $requiredAmount): array
    {
        $currentBalance = $this->getBalance($customerId);

        return [
            'sufficient' => $currentBalance >= $requiredAmount,
            'current_balance' => $currentBalance,
            'required_amount' => $requiredAmount,
            'shortfall' => max(0, $requiredAmount - $currentBalance),
        ];
    }

    /**
     * Get balance summary for dashboard
     */
    public function getBalanceSummary(int $customerId): array
    {
        $cacheKey = "ns_special_customer_balance_summary_{$customerId}";

        return Cache::remember($cacheKey, 600, function () use ($customerId) {
            $customer = Customer::find($customerId);
            if (! $customer) {
                return [
                    'balance' => 0,
                    'total_credit' => 0,
                    'total_debit' => 0,
                    'transaction_count' => 0,
                    'last_transaction' => null,
                ];
            }

            $creditOperations = [
                CustomerAccountHistory::OPERATION_ADD,
                CustomerAccountHistory::OPERATION_REFUND,
            ];
            $debitOperations = [
                CustomerAccountHistory::OPERATION_DEDUCT,
                CustomerAccountHistory::OPERATION_PAYMENT,
            ];

            $totalCredit = CustomerAccountHistory::where('customer_id', $customerId)
                ->whereIn('operation', $creditOperations)
                ->sum('amount');

            $totalDebit = CustomerAccountHistory::where('customer_id', $customerId)
                ->whereIn('operation', $debitOperations)
                ->sum('amount');

            $transactionCount = CustomerAccountHistory::where('customer_id', $customerId)->count();

            $lastTransaction = CustomerAccountHistory::where('customer_id', $customerId)
                ->latest()
                ->first();

            $recentTransactions = CustomerAccountHistory::where('customer_id', $customerId)
                ->latest()
                ->limit(5)
                ->get();

            return [
                'balance' => (float) $customer->account_amount,
                'total_credit' => (float) $totalCredit,
                'total_debit' => (float) abs($totalDebit),
                'transaction_count' => $transactionCount,
                'last_transaction' => $lastTransaction,
                'recent_transactions' => $recentTransactions,
            ];
        });
    }

    /**
     * Get daily balance changes for reporting
     */
    public function getDailyBalanceChanges(int $customerId, int $days = 30): array
    {
        $cacheKey = "ns_special_customer_daily_balance_{$customerId}_{$days}";

        return Cache::remember($cacheKey, 1800, function () use ($customerId, $days) {
            $startDate = now()->subDays($days)->startOfDay();

            $creditOperations = [
                CustomerAccountHistory::OPERATION_ADD,
                CustomerAccountHistory::OPERATION_REFUND,
            ];
            $debitOperations = [
                CustomerAccountHistory::OPERATION_DEDUCT,
                CustomerAccountHistory::OPERATION_PAYMENT,
            ];

            $changes = CustomerAccountHistory::where('customer_id', $customerId)
                ->where('created_at', '>=', $startDate)
                ->selectRaw('DATE(created_at) as date')
                ->selectRaw('SUM(CASE WHEN operation IN (?, ?) THEN amount ELSE 0 END) as credits', $creditOperations)
                ->selectRaw('SUM(CASE WHEN operation IN (?, ?) THEN ABS(amount) ELSE 0 END) as debits', $debitOperations)
                ->selectRaw('COUNT(*) as transaction_count')
                ->groupBy('date')
                ->orderBy('date')
                ->get();

            return $changes->toArray();
        });
    }

    /**
     * Reconcile customer balance
     */
    public function reconcileBalance(int $customerId): array
    {
        return DB::transaction(function () use ($customerId) {
            $customer = Customer::findOrFail($customerId);

            // Calculate balance from transaction history
            $creditOperations = [
                CustomerAccountHistory::OPERATION_ADD,
                CustomerAccountHistory::OPERATION_REFUND,
            ];
            $debitOperations = [
                CustomerAccountHistory::OPERATION_DEDUCT,
                CustomerAccountHistory::OPERATION_PAYMENT,
            ];

            $totalCredits = CustomerAccountHistory::where('customer_id', $customerId)
                ->whereIn('operation', $creditOperations)
                ->sum('amount');

            $totalDebits = CustomerAccountHistory::where('customer_id', $customerId)
                ->whereIn('operation', $debitOperations)
                ->sum('amount');

            $calculatedBalance = $totalCredits - abs($totalDebits);
            $currentBalance = (float) $customer->account_amount;
            $discrepancy = $calculatedBalance - $currentBalance;

            if (abs($discrepancy) < 0.01) {
                return [
                    'reconciled' => true,
                    'message' => __('Balance is already reconciled'),
                    'current_balance' => $currentBalance,
                    'calculated_balance' => $calculatedBalance,
                    'discrepancy' => $discrepancy,
                ];
            }

            // Create reconciliation entry
            $reconciliationDescription = __('Balance reconciliation. Discrepancy: :amount', [
                'amount' => ns()->currency->define($discrepancy)->format(),
            ]);

            $this->processTopup(
                $customerId,
                $discrepancy,
                $reconciliationDescription,
                'ns_special_reconciliation'
            );

            // Clear cache
            $this->clearCustomerCache($customerId);

            return [
                'reconciled' => true,
                'message' => __('Balance reconciled successfully'),
                'current_balance' => $currentBalance,
                'calculated_balance' => $calculatedBalance,
                'discrepancy' => $discrepancy,
                'new_balance' => $currentBalance + $discrepancy,
            ];
        });
    }

    /**
     * Get wallet statistics for reporting
     */
    public function getWalletStatistics(?int $customerId = null): array
    {
        $cacheKey = 'ns_special_wallet_stats_' . ($customerId ?? 'all');

        return Cache::remember($cacheKey, 3600, function () use ($customerId) {
            $query = CustomerAccountHistory::query();

            if ($customerId) {
                $query->where('customer_id', $customerId);
            }

            $creditOperations = [
                CustomerAccountHistory::OPERATION_ADD,
                CustomerAccountHistory::OPERATION_REFUND,
            ];
            $debitOperations = [
                CustomerAccountHistory::OPERATION_DEDUCT,
                CustomerAccountHistory::OPERATION_PAYMENT,
            ];

            $totalTransactions = (clone $query)->count();

            $totalCredits = (clone $query)
                ->whereIn('operation', $creditOperations)
                ->sum('amount');

            $totalDebits = (clone $query)
                ->whereIn('operation', $debitOperations)
                ->sum('amount');

            $averageTransaction = (clone $query)->avg('amount');

            $largestCredit = (clone $query)
                ->whereIn('operation', $creditOperations)
                ->max('amount');

            $largestDebit = (clone $query)
                ->whereIn('operation', $debitOperations)
                ->min('amount');

            $stats = [
                'total_transactions' => $totalTransactions,
                'total_credits' => (float) $totalCredits,
                'total_debits' => (float) abs($totalDebits),
                'net_flow' => (float) ($totalCredits - abs($totalDebits)),
                'average_transaction' => (float) ($averageTransaction ?? 0),
                'largest_credit' => (float) ($largestCredit ?? 0),
                'largest_debit' => (float) abs($largestDebit ?? 0),
            ];

            if ($customerId) {
                $customer = Customer::find($customerId);
                $stats['current_balance'] = $customer ? (float) $customer->account_amount : 0;
            }

            return $stats;
        });
    }

    /**
     * Clear customer-specific cache
     */
    public function clearCustomerCache(int $customerId): void
    {
        Cache::forget("ns_special_customer_balance_{$customerId}");
        Cache::forget("ns_special_customer_balance_summary_{$customerId}");

        // Clear daily balance cache for common day ranges
        foreach ([7, 14, 30, 60, 90] as $days) {
            Cache::forget("ns_special_customer_daily_balance_{$customerId}_{$days}");
        }
    }

    /**
     * Clear all wallet cache
     */
    public function clearAllCache(): void
    {
        // Clear known cache keys pattern
        // Note: This is a simplified approach that works with all cache drivers
        // For production with many customers, consider using cache tags if available
        Cache::forget('ns_special_wallet_stats_all');
    }

    /**
     * Process wallet payment for an order.
     *
     * @param  int  $customerId  The customer ID
     * @param  float  $amount  The payment amount
     * @param  int  $orderId  The order ID
     * @param  string  $description  Payment description
     * @return array<string, mixed> Payment result
     *
     * @throws CustomerNotFoundException
     * @throws InsufficientBalanceException
     */
    public function processWalletPayment(int $customerId, float $amount, int $orderId, string $description): array
    {
        $customer = Customer::find($customerId);
        if (! $customer) {
            throw new CustomerNotFoundException();
        }

        $balanceCheck = $this->validateBalance($customerId, $amount);
        if (! $balanceCheck['sufficient']) {
            throw new InsufficientBalanceException(
                __('Insufficient balance for payment'),
                $balanceCheck['current_balance'],
                $balanceCheck['required_amount']
            );
        }

        return $this->processTopup(
            $customerId,
            -$amount,
            $description,
            'ns_special_wallet_payment'
        );
    }

    /**
     * Reverse a wallet transaction.
     *
     * @param  int  $transactionId  The transaction ID to reverse
     * @param  string  $reason  Reason for reversal
     * @return array<string, mixed> Reversal result
     *
     * @throws CustomerNotFoundException
     */
    public function reverseTransaction(int $transactionId, string $reason): array
    {
        $transaction = CustomerAccountHistory::find($transactionId);
        if (! $transaction) {
            throw new CustomerNotFoundException(__('Transaction not found'));
        }

        $customerId = $transaction->customer_id;
        $amount = -$transaction->amount; // Reverse the amount

        return $this->processTopup(
            $customerId,
            $amount,
            __('Transaction Reversal: :reason', ['reason' => $reason]),
            'ns_special_reversal'
        );
    }
}
