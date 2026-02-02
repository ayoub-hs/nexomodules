<?php

namespace Modules\NsSpecialCustomer\Contracts;

/**
 * Interface for Cashback Service
 *
 * Provides methods for calculating and processing yearly cashback
 * for special customers.
 */
interface CashbackServiceInterface
{
    /**
     * Calculate yearly cashback for a customer.
     *
     * @param int $customerId The customer ID
     * @param int $year The year to calculate for
     * @return array<string, mixed> Calculation result with eligible status, total_purchases, cashback_amount
     * @throws \Modules\NsSpecialCustomer\Exceptions\CustomerNotFoundException
     */
    public function calculateYearlyCashback(int $customerId, int $year): array;

    /**
     * Process cashback for a single customer.
     *
     * @param int $customerId The customer ID
     * @param int $year The year to process
     * @param string|null $description Optional description
     * @return array<string, mixed> Processing result
     * @throws \Modules\NsSpecialCustomer\Exceptions\CustomerNotFoundException
     * @throws \Modules\NsSpecialCustomer\Exceptions\CashbackAlreadyProcessedException
     */
    public function processCustomerCashback(int $customerId, int $year, ?string $description = null): array;

    /**
     * Process cashback batch for multiple customers.
     *
     * @param int $year The year to process
     * @param array<string, mixed> $options Processing options
     * @return array<string, mixed> Batch processing results
     */
    public function processCashbackBatch(int $year, array $options = []): array;

    /**
     * Get cashback history for a customer.
     *
     * @param int $customerId The customer ID
     * @param int $perPage Number of results per page
     * @return array<string, mixed> Cashback history data
     */
    public function getCustomerCashbackHistory(int $customerId, int $perPage = 20): array;

    /**
     * Get cashback statistics for reporting.
     *
     * @param int|null $year Optional year filter
     * @return array<string, mixed> Statistics data
     */
    public function getStatistics(?int $year = null): array;

    /**
     * Reverse a processed cashback.
     *
     * @param int $cashbackHistoryId The cashback history ID to reverse
     * @param string $reason Reason for reversal
     * @return array<string, mixed> Reversal result
     * @throws \Modules\NsSpecialCustomer\Exceptions\CustomerNotFoundException
     */
    public function reverseCashback(int $cashbackHistoryId, string $reason): array;

    /**
     * Check if cashback has already been processed for a customer and year.
     *
     * @param int $customerId The customer ID
     * @param int $year The year
     * @return bool True if already processed
     */
    public function isCashbackProcessed(int $customerId, int $year): bool;
}
