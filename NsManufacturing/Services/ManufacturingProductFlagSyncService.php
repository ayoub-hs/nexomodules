<?php

namespace Modules\NsManufacturing\Services;

use App\Models\Product;
use App\Models\ProductUnitQuantity;

class ManufacturingProductFlagSyncService
{
    /**
     * Sync product-level manufacturing flags from unit quantities.
     */
    public function syncProductFlags(int $productId): void
    {
        $product = Product::find($productId);

        if (! $product instanceof Product) {
            return;
        }

        $hasManufactured = ProductUnitQuantity::where('product_id', $productId)
            ->where('is_manufactured', true)
            ->exists();

        $hasRawMaterial = ProductUnitQuantity::where('product_id', $productId)
            ->where('is_raw_material', true)
            ->exists();

        $product->is_manufactured = $hasManufactured;
        $product->is_raw_material = $hasRawMaterial;
        $product->save();
    }
}
