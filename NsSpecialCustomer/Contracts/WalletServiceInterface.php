<?php

namespace Modules\NsSpecialCustomer\Contracts;

/**
 * Interface for Wallet Service
 *
 * Provides methods for managing customer wallet operations including
 * top-ups, balance queries, and transaction history.
 */
interface WalletServiceInterface
{
    /**
     * Process top-up with double-entry ledger and transaction safety.
     *
     * @param int $customerId The customer ID
     * @param float $amount The amount to top up (positive for credit, negative for debit)
     * @param string $description Transaction description
     * @param string $reference Transaction reference
     * @return array<string, mixed> Processing result
     * @throws \Modules\NsSpecialCustomer\Exceptions\CustomerNotFoundException
     * @throws \Modules\NsSpecialCustomer\Exceptions\InvalidTopupAmountException
     * @throws \Modules\NsSpecialCustomer\Exceptions\InsufficientBalanceException
     */
    public function processTopup(int $customerId, float $amount, string $description, string $reference = 'ns_special_topup'): array;

    /**
     * Get customer balance with caching.
     *
     * @param int $customerId The customer ID
     * @return float The current balance
     */
    public function getBalance(int $customerId): float;

    /**
     * Get transaction history with filters.
     *
     * @param int $customerId The customer ID
     * @param array<string, mixed> $filters Filter criteria (operation, reference, date_from, date_to, min_amount, max_amount)
     * @param int $perPage Number of results per page
     * @return array<string, mixed> Paginated transaction history
     */
    public function getTransactionHistory(int $customerId, array $filters = [], int $perPage = 50): array;

    /**
     * Record ledger entry with audit trail.
     *
     * @param int $customerId The customer ID
     * @param float $debitAmount Debit amount
     * @param float $creditAmount Credit amount
     * @param string $description Transaction description
     * @param int|null $orderId Associated order ID
     * @param string $reference Transaction reference
     * @return array<string, mixed> Ledger entry result
     * @throws \Modules\NsSpecialCustomer\Exceptions\InvalidTopupAmountException
     * @throws \Modules\NsSpecialCustomer\Exceptions\CustomerNotFoundException
     */
    public function recordLedgerEntry(
        int $customerId,
        float $debitAmount,
        float $creditAmount,
        string $description,
        ?int $orderId = null,
        string $reference = 'ns_special_ledger'
    ): array;

    /**
     * Validate balance before operation.
     *
     * @param int $customerId The customer ID
     * @param float $requiredAmount The required amount
     * @return array<string, mixed> Validation result with 'sufficient' boolean, current_balance, required_amount, shortfall
     */
    public function validateBalance(int $customerId, float $requiredAmount): array;

    /**
     * Get balance summary for dashboard.
     *
     * @param int $customerId The customer ID
     * @return array<string, mixed> Summary with balance, total_credit, total_debit, transaction_count, last_transaction
     */
    public function getBalanceSummary(int $customerId): array;

    /**
     * Clear customer balance cache.
     *
     * @param int $customerId The customer ID
     */
    public function clearCustomerCache(int $customerId): void;

    /**
     * Process wallet payment for an order.
     *
     * @param int $customerId The customer ID
     * @param float $amount The payment amount
     * @param int $orderId The order ID
     * @param string $description Payment description
     * @return array<string, mixed> Payment result
     * @throws \Modules\NsSpecialCustomer\Exceptions\CustomerNotFoundException
     * @throws \Modules\NsSpecialCustomer\Exceptions\InsufficientBalanceException
     */
    public function processWalletPayment(int $customerId, float $amount, int $orderId, string $description): array;

    /**
     * Reverse a wallet transaction.
     *
     * @param int $transactionId The transaction ID to reverse
     * @param string $reason Reason for reversal
     * @return array<string, mixed> Reversal result
     * @throws \Modules\NsSpecialCustomer\Exceptions\CustomerNotFoundException
     */
    public function reverseTransaction(int $transactionId, string $reason): array;
}
