<?php

namespace Modules\NsContainerManagement\Services;

use App\Models\Product;
use Modules\NsContainerManagement\Models\ContainerInventory;
use Modules\NsContainerManagement\Models\ContainerType;
use Modules\NsContainerManagement\Models\ProductContainer;
use Illuminate\Support\Collection;

class ContainerService
{
    /**
     * Get all container types
     */
    public function getContainerTypes(): Collection
    {
        return ContainerType::all();
    }

    /**
     * Get container types for dropdowns
     */
    public function getContainerTypesDropdown(): array
    {
        return ContainerType::where('is_active', true)
            ->get()
            ->map(function ($type) {
                return [
                    'label' => $type->name . " ({$type->capacity}{$type->capacity_unit})",
                    'value' => $type->id,
                ];
            })->toArray();
    }

    /**
     * Link product to a container type
     */
    public function linkProductToContainer(int $productId, int $containerTypeId, ?int $unitId = null): ProductContainer
    {
        return ProductContainer::updateOrCreate(
            [
                'product_id' => $productId,
                'unit_id' => $unitId,
            ],
            [
                'container_type_id' => $containerTypeId,
                'is_enabled' => true,
            ]
        );
    }

    /**
     * Unlink product from containers
     */
    public function unlinkProductFromContainer(int $productId, ?int $unitId = null): void
    {
        ProductContainer::where('product_id', $productId)
            ->where('unit_id', $unitId)
            ->delete();
    }

    /**
     * Get container linked to a product (with unit fallback)
     */
    public function getProductContainer(int $productId, ?int $unitId = null): ?ProductContainer
    {
        $link = ProductContainer::where('product_id', $productId)
            ->where('unit_id', $unitId)
            ->first();

        if (!$link && $unitId !== null) {
            $link = ProductContainer::where('product_id', $productId)
                ->whereNull('unit_id')
                ->first();
        }

        return $link;
    }

    /**
     * Get all active product container links for POS optimization
     */
    public function getAllProductContainerLinks(): Collection
    {
        return ProductContainer::with('containerType')
            ->where('is_enabled', true)
            ->get()
            ->map(function($link) {
                return [
                    'product_id' => $link->product_id,
                    'unit_id' => $link->unit_id,
                    'container_type_id' => $link->container_type_id,
                    'container_type_name' => $link->containerType->name,
                    'capacity' => $link->containerType->capacity,
                    'capacity_unit' => $link->containerType->capacity_unit,
                    'deposit_fee' => $link->containerType->deposit_fee,
                ];
            });
    }

    /**
     * Get inventory summary for all container types
     */
    public function getInventorySummary(): Collection
    {
        return ContainerInventory::with('containerType')
            ->get()
            ->map(function($inventory) {
                return [
                    'id' => $inventory->id,
                    'container_type_id' => $inventory->container_type_id,
                    'container_type_name' => $inventory->containerType->name ?? 'Unknown',
                    'quantity_on_hand' => $inventory->quantity_on_hand,
                    'quantity_reserved' => $inventory->quantity_reserved,
                    'available_quantity' => $inventory->available_quantity,
                    'last_adjustment_date' => $inventory->last_adjustment_date,
                    'last_adjustment_reason' => $inventory->last_adjustment_reason,
                    'updated_at' => $inventory->updated_at,
                ];
            });
    }
}
