<?php

namespace Modules\NsManufacturing\Services;

use App\Models\ProductUnitQuantity;
use Modules\NsManufacturing\Models\ProductUnitQuantity as ManufacturingProductUnitQuantity;
use Exception;

class ManufacturingFlagService
{
    /**
     * Set manufacturing flags for a product unit quantity
     *
     * @param int $productUnitQuantityId
     * @param bool $isManufactured
     * @param bool $isRawMaterial
     * @return ManufacturingProductUnitQuantity
     * @throws Exception
     */
    public function setManufacturingFlags(int $productUnitQuantityId, bool $isManufactured, bool $isRawMaterial): ManufacturingProductUnitQuantity
    {
        $productUnit = ManufacturingProductUnitQuantity::findOrFail($productUnitQuantityId);
        
        // Validate that at least one flag is true
        if (!$isManufactured && !$isRawMaterial) {
            throw new Exception('At least one manufacturing flag must be true (is_manufactured or is_raw_material)');
        }
        
        // Validate business rules
        $this->validateManufacturingFlags($productUnit, $isManufactured, $isRawMaterial);
        
        $productUnit->is_manufactured = $isManufactured;
        $productUnit->is_raw_material = $isRawMaterial;
        $productUnit->save();
        
        return $productUnit;
    }

    /**
     * Validate manufacturing flags based on business rules
     *
     * @param ManufacturingProductUnitQuantity $productUnit
     * @param bool $isManufactured
     * @param bool $isRawMaterial
     * @throws Exception
     */
    private function validateManufacturingFlags(ManufacturingProductUnitQuantity $productUnit, bool $isManufactured, bool $isRawMaterial): void
    {
        // Check if product is already used in active BOMs
        if ($isManufactured || $isRawMaterial) {
            $this->validateProductUsageInBoms($productUnit);
        }
        
        // Additional validation rules can be added here
    }

    /**
     * Validate that product is not used in active BOMs when changing flags
     *
     * @param ManufacturingProductUnitQuantity $productUnit
     * @throws Exception
     */
    private function validateProductUsageInBoms(ManufacturingProductUnitQuantity $productUnit): void
    {
        // Check if this product unit is used as a component in any active BOM
        $activeBomItems = \Modules\NsManufacturing\Models\ManufacturingBomItem::where('product_id', $productUnit->product_id)
            ->where('unit_id', $productUnit->unit_id)
            ->whereHas('bom', function($query) {
                $query->where('is_active', true);
            })
            ->exists();

        if ($activeBomItems) {
            throw new Exception('This product unit is currently used in active BOMs. Please update the BOMs first.');
        }
    }

    /**
     * Get all product units that can be used for production (manufactured products)
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getProductionUnits(): \Illuminate\Database\Eloquent\Collection
    {
        return ManufacturingProductUnitQuantity::forProduction()->with(['product', 'unit'])->get();
    }

    /**
     * Get all product units that can be used as components (raw materials and manufactured products)
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getComponentUnits(): \Illuminate\Database\Eloquent\Collection
    {
        return ManufacturingProductUnitQuantity::forComponents()->with(['product', 'unit'])->get();
    }

    /**
     * Get all raw material units
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getRawMaterialUnits(): \Illuminate\Database\Eloquent\Collection
    {
        return ManufacturingProductUnitQuantity::rawMaterial()->with(['product', 'unit'])->get();
    }

    /**
     * Check if a product unit can be used for production
     *
     * @param int $productUnitQuantityId
     * @return bool
     */
    public function canBeUsedForProduction(int $productUnitQuantityId): bool
    {
        return ManufacturingProductUnitQuantity::where('id', $productUnitQuantityId)
            ->where('is_manufactured', true)
            ->exists();
    }

    /**
     * Check if a product unit can be used as a component
     *
     * @param int $productUnitQuantityId
     * @return bool
     */
    public function canBeUsedAsComponent(int $productUnitQuantityId): bool
    {
        return ManufacturingProductUnitQuantity::where('id', $productUnitQuantityId)
            ->where(function($query) {
                $query->where('is_raw_material', true)
                      ->orWhere('is_manufactured', true);
            })
            ->exists();
    }

    /**
     * Update manufacturing flags for a single product unit.
     *
     * @param int $productUnitId The product unit quantity ID
     * @param bool $isManufactured Whether the product is manufactured
     * @param bool $isRawMaterial Whether the product is a raw material
     * @return bool True on success
     * @throws Exception
     */
    public function updateManufacturingFlags(int $productUnitId, bool $isManufactured, bool $isRawMaterial): bool
    {
        $productUnit = ManufacturingProductUnitQuantity::findOrFail($productUnitId);

        // Validate that at least one flag is true
        if (! $isManufactured && ! $isRawMaterial) {
            throw new Exception('At least one manufacturing flag must be true (is_manufactured or is_raw_material)');
        }

        // Validate business rules
        $this->validateManufacturingFlags($productUnit, $isManufactured, $isRawMaterial);

        $productUnit->is_manufactured = $isManufactured;
        $productUnit->is_raw_material = $isRawMaterial;

        return $productUnit->save();
    }

    /**
     * Bulk update manufacturing flags for multiple product units.
     *
     * @param array $productUnitIds Array of product unit quantity IDs
     * @param bool $isManufactured Whether the products are manufactured
     * @param bool $isRawMaterial Whether the products are raw materials
     * @return int Number of records updated
     * @throws Exception
     */
    public function bulkUpdateManufacturingFlags(array $productUnitIds, bool $isManufactured, bool $isRawMaterial): int
    {
        // Validate that at least one flag is true
        if (!$isManufactured && !$isRawMaterial) {
            throw new Exception('At least one manufacturing flag must be true (is_manufactured or is_raw_material)');
        }

        // Validate all products before bulk update
        foreach ($productUnitIds as $id) {
            $productUnit = ManufacturingProductUnitQuantity::findOrFail($id);
            $this->validateProductUsageInBoms($productUnit);
        }

        return ManufacturingProductUnitQuantity::whereIn('id', $productUnitIds)
            ->update([
                'is_manufactured' => $isManufactured,
                'is_raw_material' => $isRawMaterial,
            ]);
    }

    /**
     * Clear manufacturing flags for a product unit
     *
     * @param int $productUnitQuantityId
     * @return ManufacturingProductUnitQuantity
     * @throws Exception
     */
    public function clearManufacturingFlags(int $productUnitQuantityId): ManufacturingProductUnitQuantity
    {
        $productUnit = ManufacturingProductUnitQuantity::findOrFail($productUnitQuantityId);
        
        // Validate that it's not used in active BOMs
        $this->validateProductUsageInBoms($productUnit);
        
        $productUnit->is_manufactured = false;
        $productUnit->is_raw_material = false;
        $productUnit->save();
        
        return $productUnit;
    }
}