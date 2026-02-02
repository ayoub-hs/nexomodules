<?php

namespace Modules\NsSpecialCustomer\Listeners;

use App\Events\OrderAfterCreatedEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Modules\NsSpecialCustomer\Services\SpecialCustomerService;
use Illuminate\Support\Facades\Log;

class OrderAfterCreatedListener
{
    private SpecialCustomerService $specialCustomerService;

    public function __construct(SpecialCustomerService $specialCustomerService)
    {
        $this->specialCustomerService = $specialCustomerService;
    }

    /**
     * Handle the event.
     */
    public function handle(OrderAfterCreatedEvent $event)
    {
        $order = $event->order;
        
        // Handle both array and object cases
        $customer = is_array($order) ? ($order['customer'] ?? null) : ($order->customer ?? null);
        
        if ($customer && $this->specialCustomerService->isSpecialCustomer($customer)) {
            // Add order metadata for cashback tracking
            $orderData = [
                'is_special' => true,
                'order_year' => now()->year,
                'eligible_for_cashback' => true,
            ];
            
            if (is_array($order)) {
                $order['special_customer_data'] = $orderData;
            } else {
                $order->special_customer_data = $orderData;
            }
            
            // Log for cashback calculation
            Log::info('Special customer order created', [
                'order_id' => is_array($order) ? ($order['id'] ?? null) : ($order->id ?? null),
                'customer_id' => is_array($order) ? ($order['customer_id'] ?? null) : ($order->customer_id ?? null),
                'total' => is_array($order) ? ($order['total'] ?? null) : ($order->total ?? null),
                'year' => now()->year,
            ]);
        }
    }
}
