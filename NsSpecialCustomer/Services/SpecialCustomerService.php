<?php

namespace Modules\NsSpecialCustomer\Services;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Order;
use App\Services\Options;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use Modules\NsSpecialCustomer\Contracts\SpecialCustomerServiceInterface;
use Modules\NsSpecialCustomer\Exceptions\CustomerNotFoundException;

class SpecialCustomerService implements SpecialCustomerServiceInterface
{
    private Options $options;

    public function __construct(Options $options)
    {
        $this->options = $options;
    }

    /**
     * Get special customer configuration with caching
     */
    public function getConfig(): array
    {
        return Cache::remember('ns_special_customer_config', 3600, function () {
            return [
                'groupId' => $this->getSpecialGroupId(),
                'discountPercentage' => $this->getDiscountPercentage(),
                'cashbackPercentage' => $this->getCashbackPercentage(),
                'applyDiscountStackable' => $this->isDiscountStackable(),
            ];
        });
    }

    /**
     * Get special customers with filters and pagination
     */
    public function getSpecialCustomers(array $filters = [], int $perPage = 50): array
    {
        $query = Customer::where('group_id', $this->getSpecialGroupId())
            ->with(['accountHistory' => function($q) {
                $q->latest()->limit(10);
            }]);

        // Apply filters
        if (!empty($filters['search'])) {
            $query->where(function($q) use ($filters) {
                $q->where('first_name', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('last_name', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('email', 'like', '%' . $filters['search'] . '%');
            });
        }

        if (!empty($filters['min_balance'])) {
            $query->where('account_amount', '>=', $filters['min_balance']);
        }

        if (!empty($filters['max_balance'])) {
            $query->where('account_amount', '<=', $filters['max_balance']);
        }

        return $query->paginate($perPage)->toArray();
    }

    /**
     * Get customer status with detailed information
     */
    public function getCustomerStatus(int $customerId): array
    {
        $customer = Customer::with(['accountHistory' => function($q) {
            $q->latest()->limit(20);
        }])->find($customerId);

        if (!$customer) {
            throw new CustomerNotFoundException();
        }

        $isSpecial = $this->isSpecialCustomer($customer);
        $config = $this->getConfig();

        return [
            'customer' => $customer,
            'is_special' => $isSpecial,
            'balance' => $customer->account_amount,
            'total_purchases' => $customer->purchases_amount,
            'recent_transactions' => $customer->accountHistory,
            'discount_eligible' => $isSpecial && $config['discountPercentage'] > 0,
            'cashback_eligible' => $isSpecial && $config['cashbackPercentage'] > 0,
            'config' => $config,
        ];
    }

    /**
     * Apply wholesale pricing to product for special customer
     */
    public function applyWholesalePricing($product, $customer): array
    {
        if (!$customer || !$this->isSpecialCustomer($customer)) {
            return [
                'original_price' => is_array($product) ? ($product['sale_price'] ?? 0) : ($product->sale_price ?? 0),
                'special_price' => is_array($product) ? ($product['sale_price'] ?? 0) : ($product->sale_price ?? 0),
                'wholesale_applied' => false,
                'savings' => 0,
            ];
        }

        $originalPrice = is_array($product) ? ($product['sale_price'] ?? 0) : ($product->sale_price ?? 0);
        $wholesalePrice = is_array($product) ? ($product['wholesale_price'] ?? 0) : ($product->wholesale_price ?? 0);
        $specialPrice = $wholesalePrice && $wholesalePrice > 0 
            ? $wholesalePrice 
            : $originalPrice;

        return [
            'original_price' => $originalPrice,
            'special_price' => $specialPrice,
            'wholesale_applied' => $specialPrice < $originalPrice,
            'savings' => max(0, $originalPrice - $specialPrice),
        ];
    }

    /**
     * Apply special discount to order with validation
     */
    public function applySpecialDiscount($order, $customer): array
    {
        $validation = $this->validateDiscountEligibility($customer, $order);
        if (!$validation['eligible']) {
            return [
                'discount_applied' => false,
                'discount_amount' => 0,
                'reason' => $validation['reason'],
            ];
        }

        $config = $this->getConfig();
        $discountPercentage = $config['discountPercentage'];
        
        // Handle both array and object cases for order total
        $orderTotal = is_array($order) ? ($order['total'] ?? 0) : ($order->total ?? 0);
        
        $discountAmount = $orderTotal * ($discountPercentage / 100);

        return [
            'discount_applied' => true,
            'discount_amount' => $discountAmount,
            'discount_percentage' => $discountPercentage,
            'new_total' => $orderTotal - $discountAmount,
            'stackable' => $config['applyDiscountStackable'],
        ];
    }

    /**
     * Validate discount eligibility with business rules
     */
    public function validateDiscountEligibility($customer, $order): array
    {
        // Check if customer is special
        if (!$this->isSpecialCustomer($customer)) {
            return [
                'eligible' => false,
                'reason' => 'Customer is not in the special customer group',
            ];
        }

        // Check if discount is enabled
        $config = $this->getConfig();
        if ($config['discountPercentage'] <= 0) {
            return [
                'eligible' => false,
                'reason' => 'Special discount is not enabled',
            ];
        }

        // Handle both array and object cases for order
        $orderData = is_array($order) ? $order : ($order->special_customer_data ?? []);
        
        // Check if order already has special discount
        if (isset($orderData['discount_applied']) && 
            $orderData['discount_applied'] && 
            !$config['applyDiscountStackable']) {
            return [
                'eligible' => false,
                'reason' => 'Special discount already applied and not stackable',
            ];
        }

        // Check minimum order amount (business rule)
        $minOrderAmount = $this->options->get('ns_special_min_order_amount', 0);
        $orderTotal = is_array($order) ? ($order['total'] ?? 0) : ($order->total ?? 0);
        if ($minOrderAmount > 0 && $orderTotal < $minOrderAmount) {
            return [
                'eligible' => false,
                'reason' => "Order total must be at least {$minOrderAmount} to qualify for special discount",
            ];
        }

        return [
            'eligible' => true,
            'reason' => 'Customer is eligible for special discount',
        ];
    }

    /**
     * Get the special customer group ID with caching
     */
    public function getSpecialGroupId(): ?int
    {
        return Cache::remember('ns_special_customer_group_id', 3600, function () {
            return $this->options->get('ns_special_customer_group_id');
        });
    }

    /**
     * Set the special customer group ID and clear cache
     */
    public function setSpecialGroupId(int $groupId): void
    {
        $this->options->set('ns_special_customer_group_id', $groupId);
        Cache::forget('ns_special_customer_group_id');
        Cache::forget('ns_special_customer_config');
    }

    /**
     * Get the special discount percentage
     */
    public function getDiscountPercentage(): float
    {
        return (float) $this->options->get('ns_special_discount_percentage', 7.0);
    }

    /**
     * Set the special discount percentage and clear cache
     */
    public function setDiscountPercentage(float $percentage): void
    {
        $this->options->set('ns_special_discount_percentage', $percentage);
        Cache::forget('ns_special_customer_config');
    }

    /**
     * Get the special cashback percentage
     */
    public function getCashbackPercentage(): float
    {
        return (float) $this->options->get('ns_special_cashback_percentage', 2.0);
    }

    /**
     * Set the special cashback percentage and clear cache
     */
    public function setCashbackPercentage(float $percentage): void
    {
        $this->options->set('ns_special_cashback_percentage', $percentage);
        Cache::forget('ns_special_customer_config');
    }

    /**
     * Check if discount is stackable
     */
    public function isDiscountStackable(): bool
    {
        $value = $this->options->get('ns_special_apply_discount_stackable', false);

        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (bool) (int) $value;
        }

        return in_array($value, ['yes', 'true', '1'], true);
    }

    /**
     * Set discount stackable option and clear cache
     */
    public function setDiscountStackable(bool $stackable): void
    {
        $this->options->set('ns_special_apply_discount_stackable', $stackable ? 'yes' : 'no');
        Cache::forget('ns_special_customer_config');
    }

    /**
     * Clear cached special customer configuration.
     */
    public function clearConfigCache(): void
    {
        Cache::forget('ns_special_customer_config');
        Cache::forget('ns_special_customer_group_id');
    }

    /**
     * Check if a customer is a special customer with caching
     * Works with both Customer model and array/object
     */
    public function isSpecialCustomer($customer): bool
    {
        if (!$customer) {
            return false;
        }

        // Handle both array and object cases
        $customerGroupId = is_array($customer) ? ($customer['group_id'] ?? null) : ($customer->group_id ?? null);
        
        if (!$customerGroupId) {
            return false;
        }

        $specialGroupId = $this->getSpecialGroupId();
        
        return $specialGroupId && (int) $customerGroupId === (int) $specialGroupId;
    }

    /**
     * Calculate special discount for an order
     */
    public function calculateSpecialDiscount($orderTotal, $customer): float
    {
        if (!$customer || !$this->isSpecialCustomer($customer)) {
            return 0;
        }

        return $orderTotal * ($this->getDiscountPercentage() / 100);
    }

    /**
     * Get default configuration
     */
    public function getDefaultConfig(): array
    {
        return [
            'ns_special_discount_percentage' => 7.0,
            'ns_special_cashback_percentage' => 2.0,
            'ns_special_apply_discount_stackable' => false,
        ];
    }

    /**
     * Initialize default configuration
     */
    public function initializeDefaults(): void
    {
        $defaults = $this->getDefaultConfig();
        
        foreach ($defaults as $key => $value) {
            if ($this->options->get($key) === null) {
                $this->options->set($key, $value);
            }
        }
    }

    /**
     * Clear all configuration caches
     */
    public function clearCache(): void
    {
        Cache::forget('ns_special_customer_group_id');
        Cache::forget('ns_special_customer_config');
    }
}
