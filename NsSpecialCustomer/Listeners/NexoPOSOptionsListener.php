<?php

namespace Modules\NsSpecialCustomer\Listeners;

use Modules\NsSpecialCustomer\Services\SpecialCustomerService;

class NexoPOSOptionsListener
{
    private SpecialCustomerService $specialCustomerService;

    public function __construct(SpecialCustomerService $specialCustomerService)
    {
        $this->specialCustomerService = $specialCustomerService;
    }

    public function handle($event): void
    {
        $config = $this->specialCustomerService->getConfig();
        
        // Add special customer configuration to POS options
        $event->options['specialCustomer'] = [
            'enabled' => !is_null($config['groupId']),
            'groupId' => $config['groupId'],
            'discountPercentage' => $config['discountPercentage'],
            'cashbackPercentage' => $config['cashbackPercentage'],
            'applyDiscountStackable' => $config['applyDiscountStackable'],
        ];

        // Add JavaScript hook for wholesale price display
        $event->options['hooks']['ns-pos-product-wholesale-price'] = 'nsSpecialCustomer.applySpecialPricing';
    }
}
