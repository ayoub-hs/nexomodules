<?php

namespace Modules\NsManufacturing\Services;

use Modules\NsManufacturing\Models\ManufacturingOrder;
use Modules\NsManufacturing\Models\ManufacturingBom;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProductionService
{
    public function __construct(
        protected InventoryBridgeService $inventory,
        protected BomService $bomService
    ) {}

    /**
     * Start a production order and consume materials.
     *
     * @param ManufacturingOrder $order The order to start
     * @return void
     * @throws \Exception If insufficient stock or invalid status
     */
    public function startOrder(ManufacturingOrder $order): void
    {
        // Validate order status
        if (!in_array($order->status, [ManufacturingOrder::STATUS_PLANNED, ManufacturingOrder::STATUS_DRAFT])) {
            throw new Exception(
                __("Order must be in Planned or Draft state to start. Current status: :status", [
                    'status' => $order->status
                ])
            );
        }

        // Validate BOM exists
        $bom = $order->bom;
        if (!$bom) {
            throw new Exception(__("No BOM assigned to order #:code", ['code' => $order->code]));
        }

        // Validate BOM is active
        if (!$bom->is_active) {
            throw new Exception(__("BOM ':name' is not active", ['name' => $bom->name]));
        }

        // Check stock availability for all items
        $missing = [];
        $requirements = [];
        
        foreach ($bom->items as $item) {
            $required = $item->quantity * $order->quantity;
            $requirements[] = [
                'item' => $item,
                'required' => $required,
            ];
            
            if (!$this->inventory->isAvailable($item->product_id, $item->unit_id, $required)) {
                $productName = $item->product ? $item->product->name : __('Unknown Product');
                $unitName = $item->unit ? $item->unit->name : __('Unknown Unit');
                $available = app(\App\Services\ProductService::class)->getQuantity($item->product_id, $item->unit_id);
                
                $missing[] = sprintf(
                    '%s (%s): %s %s, %s %s',
                    $productName,
                    $unitName,
                    __('Required'),
                    $required,
                    __('Available'),
                    $available
                );
            }
        }

        if (!empty($missing)) {
            throw new Exception(
                __("Insufficient stock for the following items:") . "\n" . implode("\n", $missing)
            );
        }

        // Start transaction
        try {
            DB::beginTransaction();
            
            $productService = app(\App\Services\ProductService::class);
            
            // Consume all materials
            foreach ($requirements as $req) {
                $item = $req['item'];
                $required = $req['required'];
                $cost = $productService->getCogs($item->product, $item->unit);

                $this->inventory->consume(
                    $order->id,
                    $item->product_id,
                    $item->unit_id,
                    $required,
                    $cost
                );
                
                Log::info("Manufacturing: Consumed material", [
                    'order_id' => $order->id,
                    'order_code' => $order->code,
                    'product_id' => $item->product_id,
                    'quantity' => $required,
                    'cost' => $cost,
                ]);
            }

            // Update order status
            $order->status = ManufacturingOrder::STATUS_IN_PROGRESS;
            $order->started_at = now();
            $order->save();
            
            DB::commit();
            
            Log::info("Manufacturing: Order started successfully", [
                'order_id' => $order->id,
                'order_code' => $order->code,
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error("Manufacturing: Failed to start order", [
                'order_id' => $order->id,
                'order_code' => $order->code,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            throw new Exception(
                __("Failed to start production order: :message", ['message' => $e->getMessage()])
            );
        }
    }

    /**
     * Complete a production order and produce output.
     *
     * @param ManufacturingOrder $order The order to complete
     * @return void
     * @throws \Exception If order is not in progress
     */
    public function completeOrder(ManufacturingOrder $order): void
    {
        // Auto-start if in planned/draft status
        if (in_array($order->status, [ManufacturingOrder::STATUS_PLANNED, ManufacturingOrder::STATUS_DRAFT])) {
            $this->startOrder($order);
            $order->refresh();
        }
        
        // Validate order status
        if ($order->status !== ManufacturingOrder::STATUS_IN_PROGRESS) {
            throw new Exception(
                __("Order must be in progress to complete. Current status: :status", [
                    'status' => $order->status
                ])
            );
        }

        try {
            DB::beginTransaction();
            
            // Calculate unit cost
            $estimatedUnitCost = $this->bomService->calculateEstimatedCost($order->bom);

            // Produce finished goods
            $this->inventory->produce(
                $order->id,
                $order->product_id,
                $order->unit_id,
                $order->quantity,
                $estimatedUnitCost
            );

            // Update order status
            $order->status = ManufacturingOrder::STATUS_COMPLETED;
            $order->completed_at = now();
            $order->save();
            
            DB::commit();
            
            Log::info("Manufacturing: Order completed successfully", [
                'order_id' => $order->id,
                'order_code' => $order->code,
                'quantity_produced' => $order->quantity,
                'unit_cost' => $estimatedUnitCost,
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error("Manufacturing: Failed to complete order", [
                'order_id' => $order->id,
                'order_code' => $order->code,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            throw new Exception(
                __("Failed to complete production order: :message", ['message' => $e->getMessage()])
            );
        }
    }
    
    /**
     * Cancel a production order.
     *
     * @param ManufacturingOrder $order The order to cancel
     * @return void
     * @throws \Exception If order cannot be cancelled
     */
    public function cancelOrder(ManufacturingOrder $order): void
    {
        // Can only cancel planned, draft, or on-hold orders
        if (!in_array($order->status, [
            ManufacturingOrder::STATUS_PLANNED,
            ManufacturingOrder::STATUS_DRAFT,
            ManufacturingOrder::STATUS_ON_HOLD
        ])) {
            throw new Exception(
                __("Cannot cancel order in :status status", ['status' => $order->status])
            );
        }

        try {
            DB::beginTransaction();
            
            $order->status = ManufacturingOrder::STATUS_CANCELLED;
            $order->save();
            
            DB::commit();
            
            Log::info("Manufacturing: Order cancelled", [
                'order_id' => $order->id,
                'order_code' => $order->code,
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error("Manufacturing: Failed to cancel order", [
                'order_id' => $order->id,
                'order_code' => $order->code,
                'error' => $e->getMessage(),
            ]);
            
            throw new Exception(
                __("Failed to cancel order: :message", ['message' => $e->getMessage()])
            );
        }
    }
}
