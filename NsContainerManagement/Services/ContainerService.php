<?php

namespace Modules\NsContainerManagement\Services;

use App\Models\Product;
use Modules\NsContainerManagement\Models\ContainerInventory;
use Modules\NsContainerManagement\Models\ContainerType;
use Modules\NsContainerManagement\Models\ProductContainer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

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
     * Create a container type and ensure inventory exists.
     */
    public function createContainerType(array $data): ContainerType
    {
        $initialStock = $data['initial_stock'] ?? null;
        unset($data['initial_stock']);

        $containerType = ContainerType::create($data);

        $inventory = ContainerInventory::firstOrCreate(
            ['container_type_id' => $containerType->id],
            [
                'quantity_on_hand' => 0,
                'quantity_reserved' => 0,
            ]
        );

        if ($initialStock !== null) {
            $inventory->update(['quantity_on_hand' => (int) $initialStock]);
        }

        return $containerType->fresh(['inventory']);
    }

    /**
     * Update a container type.
     */
    public function updateContainerType(int $id, array $data): ContainerType
    {
        $containerType = ContainerType::findOrFail($id);
        unset($data['initial_stock']);
        $containerType->update($data);
        return $containerType->fresh(['inventory']);
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
    public function unlinkProductFromContainer(int $productId, ?int $unitId = null): bool
    {
        $query = ProductContainer::where('product_id', $productId);
        if ($unitId === null) {
            $query->whereNull('unit_id');
        } else {
            $query->where('unit_id', $unitId);
        }

        $deleted = $query->delete();
        return $deleted > 0;
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

    /**
     * Adjust inventory for a container type.
     */
    public function adjustInventory(int $containerTypeId, int $adjustment, string $reason): ContainerInventory
    {
        $inventory = ContainerInventory::firstOrCreate(
            ['container_type_id' => $containerTypeId],
            [
                'quantity_on_hand' => 0,
                'quantity_reserved' => 0,
                'last_adjustment_date' => null,
                'last_adjustment_by' => null,
                'last_adjustment_reason' => null,
            ]
        );

        $inventory->update([
            'quantity_on_hand' => $inventory->quantity_on_hand + $adjustment,
            'last_adjustment_date' => now(),
            'last_adjustment_by' => Auth::id() ?? 0,
            'last_adjustment_reason' => $reason,
        ]);

        return $inventory->fresh();
    }
}
