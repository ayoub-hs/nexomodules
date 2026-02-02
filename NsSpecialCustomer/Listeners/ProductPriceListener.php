<?php

namespace Modules\NsSpecialCustomer\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Modules\NsSpecialCustomer\Services\SpecialCustomerService;

class ProductPriceListener
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
        $product = $event->product;
        $customer = $event->customer;
        
        // Handle both array and object cases
        $customerObj = is_array($customer) ? (object) $customer : $customer;
        
        if ($customerObj && $this->specialCustomerService->isSpecialCustomer($customerObj)) {
            $pricing = $this->specialCustomerService->applyWholesalePricing($product, $customerObj);
            
            if ($pricing['wholesale_applied']) {
                $event->price = [
                    'price' => $pricing['special_price'],
                    'original_price' => $pricing['original_price'],
                    'wholesale_applied' => true,
                    'savings' => $pricing['savings'],
                ];
            }
        }
    }
}
