<?php

namespace Modules\NsSpecialCustomer\Listeners;

use Modules\NsSpecialCustomer\Services\SpecialCustomerService;

class OrderAttributesListener
{
    private SpecialCustomerService $specialCustomerService;

    public function __construct(SpecialCustomerService $specialCustomerService)
    {
        $this->specialCustomerService = $specialCustomerService;
    }

    public function handle($event): void
    {
        $order = $event->order;
        
        // Handle both array and object cases
        $customer = is_array($order) ? ($order['customer'] ?? null) : ($order->customer ?? null);

        if ($customer && $this->specialCustomerService->isSpecialCustomer($customer)) {
            $config = $this->specialCustomerService->getConfig();
            
            // Add special customer metadata to order
            $orderData = [
                'is_special' => true,
                'group_id' => $config['groupId'],
                'discount_percentage' => $config['discountPercentage'],
                'cashback_percentage' => $config['cashbackPercentage'],
                'discount_stackable' => $config['applyDiscountStackable'],
            ];
            
            if (is_array($order)) {
                $order['special_customer_data'] = $orderData;
            } else {
                $order->special_customer_data = $orderData;
            }

            // Note: Backend recalculation should happen during order processing
            // This metadata is for reference and frontend display only
        }
    }
}
