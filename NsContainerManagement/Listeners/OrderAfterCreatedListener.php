<?php

namespace Modules\NsContainerManagement\Listeners;

use App\Events\OrderAfterCreatedEvent;
use Modules\NsContainerManagement\Models\ContainerMovement;
use Modules\NsContainerManagement\Services\ContainerLedgerService;

class OrderAfterCreatedListener
{
    public function __construct(
        protected ContainerLedgerService $ledgerService
    ) {}

    public function handle(OrderAfterCreatedEvent $event): void
    {
        $order = $event->order;
        
        // Prevent duplicate processing if the event fires twice for the same order
        $alreadyProcessed = ContainerMovement::where('order_id', $order->id)
            ->where('source_type', 'pos_sale')
            ->exists();
            
        if ($alreadyProcessed) {
            return;
        }

        // Skip if no customer (walk-in without tracking)
        if (!$order->customer_id) {
            return;
        }

        /**
         * NexoPOS orders products are usually in the products relationship
         * but specific metadata for containers might be in the data attribute 
         * if it's sent from the POS correctly.
         */
        $data = $order->data;
        if ( empty( $data ) && method_exists( $order, 'getData' ) ) {
            $data = $order->getData();
        }

        $products = $data['products'] ?? [];

        if ( empty( $products ) ) {
            return;
        }

        foreach ($products as $productData) {
            // Respect manual toggle
            $track = $productData['container_tracking_enabled'] ?? true;
            if (!$track) {
                continue;
            }

            // Check if product has linked container for its specific unit
            $containerInfo = $this->ledgerService->calculateContainersForProduct(
                (int) ($productData['product_id'] ?? $productData['id']),
                (float) $productData['quantity'],
                (int) ($productData['unit_quantity_id'] ?? $productData['unit_id'] ?? null)
            );

            if (!$containerInfo) {
                continue;
            }

            // Check for cashier override
            $overrideQty = $productData['container_quantity_override'] ?? null;
            $finalQty = ($overrideQty !== null && $overrideQty !== '') ? (int) $overrideQty : $containerInfo['quantity'];

            // Skip if quantity is 0 (customer brought own container)
            if ($finalQty <= 0) {
                continue;
            }

            $this->ledgerService->recordContainerOut(
                customerId: $order->customer_id,
                containerTypeId: $containerInfo['container_type_id'],
                quantity: $finalQty,
                orderId: $order->id,
                sourceType: 'pos_sale',
                note: "Auto-generated from order #{$order->code}"
            );
        }
    }
}
