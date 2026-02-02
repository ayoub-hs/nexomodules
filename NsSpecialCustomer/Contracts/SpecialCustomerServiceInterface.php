<?php

namespace Modules\NsSpecialCustomer\Contracts;

use App\Models\Customer;

/**
 * Interface for Special Customer Service
 *
 * Provides methods for managing special customer functionality including
 * discounts, cashback configuration, and customer status verification.
 */
interface SpecialCustomerServiceInterface
{
    /**
     * Get special customer configuration with caching.
     *
     * @return array<string, mixed> Configuration array with groupId, discountPercentage, cashbackPercentage, applyDiscountStackable
     */
    public function getConfig(): array;

    /**
     * Get special customers with filters and pagination.
     *
     * @param array<string, mixed> $filters Filter criteria (search, min_balance, max_balance)
     * @param int $perPage Number of results per page
     * @return array<string, mixed> Paginated customer data
     */
    public function getSpecialCustomers(array $filters = [], int $perPage = 50): array;

    /**
     * Get customer status with detailed information.
     *
     * @param int $customerId The customer ID
     * @return array<string, mixed> Customer status data
     * @throws \Modules\NsSpecialCustomer\Exceptions\CustomerNotFoundException
     */
    public function getCustomerStatus(int $customerId): array;

    /**
     * Apply wholesale pricing to product for special customer.
     *
     * @param array<string, mixed>|object $product Product data
     * @param array<string, mixed>|object|null $customer Customer data
     * @return array<string, mixed> Pricing result with original_price, special_price, wholesale_applied, savings
     */
    public function applyWholesalePricing($product, $customer): array;

    /**
     * Apply special discount to order with validation.
     *
     * @param array<string, mixed>|object $order Order data
     * @param array<string, mixed>|object $customer Customer data
     * @return array<string, mixed> Discount result
     */
    public function applySpecialDiscount($order, $customer): array;

    /**
     * Validate discount eligibility with business rules.
     *
     * @param array<string, mixed>|object $customer Customer data
     * @param array<string, mixed>|object $order Order data
     * @return array<string, mixed> Eligibility result with 'eligible' boolean and 'reason' string
     */
    public function validateDiscountEligibility($customer, $order): array;

    /**
     * Get the special customer group ID with caching.
     *
     * @return int|null The group ID or null if not set
     */
    public function getSpecialGroupId(): ?int;

    /**
     * Set the special customer group ID and clear cache.
     *
     * @param int $groupId The group ID to set
     */
    public function setSpecialGroupId(int $groupId): void;

    /**
     * Get the special discount percentage.
     *
     * @return float The discount percentage
     */
    public function getDiscountPercentage(): float;

    /**
     * Set the special discount percentage and clear cache.
     *
     * @param float $percentage The percentage to set
     */
    public function setDiscountPercentage(float $percentage): void;

    /**
     * Get the special cashback percentage.
     *
     * @return float The cashback percentage
     */
    public function getCashbackPercentage(): float;

    /**
     * Set the special cashback percentage and clear cache.
     *
     * @param float $percentage The percentage to set
     */
    public function setCashbackPercentage(float $percentage): void;

    /**
     * Check if discount is stackable with other discounts.
     *
     * @return bool True if stackable
     */
    public function isDiscountStackable(): bool;

    /**
     * Set whether discount is stackable.
     *
     * @param bool $stackable Whether discount should be stackable
     */
    public function setDiscountStackable(bool $stackable): void;

    /**
     * Check if a customer is a special customer.
     *
     * @param array<string, mixed>|object|null $customer Customer data
     * @return bool True if special customer
     */
    public function isSpecialCustomer($customer): bool;

    /**
     * Clear the configuration cache.
     */
    public function clearConfigCache(): void;
}
