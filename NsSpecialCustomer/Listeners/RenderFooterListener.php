<?php

namespace Modules\NsSpecialCustomer\Listeners;

use App\Events\RenderFooterEvent;
use Modules\NsSpecialCustomer\Services\SpecialCustomerService;

class RenderFooterListener
{
    private static bool $outstandingInjected = false;
    private SpecialCustomerService $specialCustomerService;

    public function __construct(SpecialCustomerService $specialCustomerService)
    {
        $this->specialCustomerService = $specialCustomerService;
    }

    public function handle(RenderFooterEvent $event): void
    {
        if ($event->routeName === 'ns.dashboard.pos') {
            $config = $this->specialCustomerService->getConfig();
            $options = [
                'specialCustomer' => [
                    'enabled' => ! is_null($config['groupId']),
                    'groupId' => $config['groupId'],
                    'discountPercentage' => $config['discountPercentage'],
                    'cashbackPercentage' => $config['cashbackPercentage'],
                    'applyDiscountStackable' => $config['applyDiscountStackable'],
                ],
            ];

            // Inject the special customer JavaScript logic
            $event->output->addView('NsSpecialCustomer::pos-footer', $options);

            // Inject the visual indicators
            $event->output->addView('NsSpecialCustomer::pos.special-customer-indicator', $options);
            $event->output->addView('NsSpecialCustomer::pos.wallet-balance-widget', $options);
        }

        // Register Vue components and footer script for outstanding tickets page
        // Check for multiple route patterns to ensure component loads
        if (
            ! self::$outstandingInjected && (
                $event->routeName === 'ns.dashboard.special-customer-outstanding' ||
                str_contains($event->routeName ?? '', 'special-customer-outstanding') ||
                str_contains($event->routeName ?? '', 'outstanding-tickets')
            )
        ) {
            self::$outstandingInjected = true;
            $event->output->addView('NsSpecialCustomer::components-registration');
           $event->output->addView('NsSpecialCustomer::outstanding-tickets-footer');
        }
    }
}
