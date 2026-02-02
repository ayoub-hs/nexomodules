<?php

namespace Modules\NsSpecialCustomer\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Modules\NsSpecialCustomer\Services\SpecialCustomerService;

class CustomerSelectedListener
{
    private SpecialCustomerService $specialCustomerService;

    public function __construct(SpecialCustomerService $specialCustomerService)
    {
        $this->specialCustomerService = $specialCustomerService;
    }

    /**
     * Handle the event.
     */
    public function handle($event)
    {
        $customer = $event->customer;
        
        // Handle both array and object cases
        $customerObj = is_array($customer) ? (object) $customer : $customer;
        
        if ($customerObj && $this->specialCustomerService->isSpecialCustomer($customerObj)) {
            // Add special customer data to the event
            $event->customerData['is_special'] = true;
            $event->customerData['special_badge'] = 'Special Customer';
            $event->customerData['wallet_balance'] = is_array($customer) ? ($customer['account_amount'] ?? 0) : ($customerObj->account_amount ?? 0);
            
            // Get customer status
            $customerId = is_array($customer) ? ($customer['id'] ?? 0) : ($customerObj->id ?? 0);
            $status = $this->specialCustomerService->getCustomerStatus($customerId);
            $event->customerData['discount_eligible'] = $status['discount_eligible'];
            $event->customerData['cashback_eligible'] = $status['cashback_eligible'];
        }
    }
}

