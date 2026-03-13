<?php

namespace Modules\NsManufacturing\Models;

use App\Models\ProductUnitQuantity as CoreProductUnitQuantity;
use App\Casts\FloatConvertCasting;

/**
 * Extended ProductUnitQuantity model for manufacturing functionality
 * 
 * @property bool $is_manufactured
 * @property bool $is_raw_material
 */
class ProductUnitQuantity extends CoreProductUnitQuantity
{
    /**
     * Initialize the model and safely merge module-specific fields.
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        
        $this->mergeFillable(['is_manufactured', 'is_raw_material']);
        $this->mergeCasts([
            'is_manufactured' => 'boolean',
            'is_raw_material' => 'boolean',
        ]);
    }

    /**
     * Scope a query to only include manufactured products.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeManufactured($query)
    {
        return $query->where('is_manufactured', true);
    }

    /**
     * Scope a query to only include raw materials.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeRawMaterial($query)
    {
        return $query->where('is_raw_material', true);
    }

    /**
     * Scope a query to only include products that can be used in manufacturing.
     * This includes both manufactured products and raw materials.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForManufacturing($query)
    {
        return $query->where(function ($q) {
            $q->where('is_manufactured', true)
              ->orWhere('is_raw_material', true);
        });
    }

    /**
     * Scope a query to only include products that can be used as components.
     * This includes raw materials only.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForComponents($query)
    {
        return $query->where('is_raw_material', true);
    }

    /**
     * Scope a query to only include products that can be used for production.
     * This includes manufactured products.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForProduction($query)
    {
        return $query->where('is_manufactured', true);
    }
}
