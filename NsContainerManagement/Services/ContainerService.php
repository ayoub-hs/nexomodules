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
     *
     * @param int      $productId       The product ID
     * @param int      $containerTypeId The container type ID
     * @param int|null $unitQuantityId  The unit quantity ID (from products_unit_quantities table)
     * @param int|null $unitId          The unit ID (from units table) – kept for reference
     */
    public function linkProductToContainer(int $productId, int $containerTypeId, ?int $unitQuantityId = null, ?int $unitId = null): ProductContainer
    {
        // Safety-net: resolve unit_quantity_id from unit_id if missing
        if ($unitQuantityId === null && $unitId !== null) {
            $unitQuantity = \App\Models\ProductUnitQuantity::where('product_id', $productId)
                ->where('unit_id', $unitId)
                ->first();
            if ($unitQuantity) {
                $unitQuantityId = $unitQuantity->id;
            }
        }

        // Match on product + unit_quantity — this is what the unique index is based on.
        // container_type_id goes into the UPDATE payload, not the match criteria.
        $matchCriteria = ['product_id' => $productId];
        if ($unitQuantityId !== null) {
            $matchCriteria['unit_quantity_id'] = $unitQuantityId;
        } elseif ($unitId !== null) {
            $matchCriteria['unit_id'] = $unitId;
        }

        $updateData = [
            'container_type_id' => $containerTypeId,
            'unit_id'           => $unitId,
            'unit_quantity_id'  => $unitQuantityId,
            'is_enabled'        => true,
        ];

        return ProductContainer::updateOrCreate($matchCriteria, $updateData);
    }

    /**
     * Unlink product from containers
     * Handles backward compatibility for fresh installs without unit_quantity_id column
     */
    public function unlinkProductFromContainer(int $productId, ?int $unitQuantityId = null): bool
    {
        $query = ProductContainer::where('product_id', $productId);

        if ($unitQuantityId === null) {
            $query->whereNull('unit_quantity_id');
        } else {
            $query->where('unit_quantity_id', $unitQuantityId);
        }

        $deleted = $query->delete();
        return $deleted > 0;
    }

    /**
     * Get container linked to a specific product unit
     */
    public function getProductContainer(int $productId, ?int $unitQuantityId = null): ?ProductContainer
    {
        return ProductContainer::where('product_id', $productId)
            ->where('unit_quantity_id', $unitQuantityId)
            ->first();
    }

    /**
     * Get all active product container links for POS optimisation
     */
    public function getAllProductContainerLinks(): Collection
    {
        return ProductContainer::with('containerType')
            ->where('is_enabled', true)
            ->get()
            ->map(function ($link) {
                return [
                    'product_id'         => $link->product_id,
                    'unit_quantity_id'   => $link->unit_quantity_id,
                    'unit_id'            => $link->unit_id,
                    'container_type_id'  => $link->container_type_id,
                    'container_type_name'=> $link->containerType->name,
                    'capacity'           => $link->containerType->capacity,
                    'capacity_unit'      => $link->containerType->capacity_unit,
                    'deposit_fee'        => $link->containerType->deposit_fee,
                ];
            });
    }

    public function getProductContainerLinksForProducts(iterable $productIds): Collection
    {
        $ids = collect($productIds)
            ->filter(fn ($id) => $id !== null && $id !== '')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        return ProductContainer::with('containerType')
            ->where('is_enabled', true)
            ->whereIn('product_id', $ids)
            ->get()
            ->map(function ($link) {
                return [
                    'product_id' => $link->product_id,
                    'unit_quantity_id' => $link->unit_quantity_id,
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
