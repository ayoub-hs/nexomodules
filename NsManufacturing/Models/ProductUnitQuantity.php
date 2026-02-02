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
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'product_id',
        'type',
        'preview_url',
        'expiration_date',
        'unit_id',
        'barcode',
        'quantity',
        'low_quantity',
        'stock_alert_enabled',
        'sale_price',
        'sale_price_edit',
        'sale_price_without_tax',
        'sale_price_with_tax',
        'sale_price_tax',
        'wholesale_price',
        'wholesale_price_edit',
        'wholesale_price_with_tax',
        'wholesale_price_without_tax',
        'wholesale_price_tax',
        'custom_price',
        'custom_price_edit',
        'custom_price_with_tax',
        'custom_price_without_tax',
        'cogs',
        'visible',
        'convert_unit_id',
        'is_manufactured',
        'is_raw_material',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'sale_price' => FloatConvertCasting::class,
        'sale_price_edit' => FloatConvertCasting::class,
        'sale_price_without_tax' => FloatConvertCasting::class,
        'sale_price_with_tax' => FloatConvertCasting::class,
        'sale_price_tax' => FloatConvertCasting::class,
        'wholesale_price' => FloatConvertCasting::class,
        'wholesale_price_edit' => FloatConvertCasting::class,
        'wholesale_price_with_tax' => FloatConvertCasting::class,
        'wholesale_price_without_tax' => FloatConvertCasting::class,
        'wholesale_price_tax' => FloatConvertCasting::class,
        'custom_price' => FloatConvertCasting::class,
        'custom_price_edit' => FloatConvertCasting::class,
        'custom_price_with_tax' => FloatConvertCasting::class,
        'custom_price_without_tax' => FloatConvertCasting::class,
        'quantity' => 'float',
        'low_quantity' => 'float',
        'is_manufactured' => 'boolean',
        'is_raw_material' => 'boolean',
    ];

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
     * This includes both raw materials and manufactured products.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForComponents($query)
    {
        return $query->where(function ($q) {
            $q->where('is_raw_material', true)
              ->orWhere('is_manufactured', true);
        });
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