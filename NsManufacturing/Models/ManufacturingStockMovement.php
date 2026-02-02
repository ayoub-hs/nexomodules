<?php

namespace Modules\NsManufacturing\Models;

use App\Models\NsModel;
use App\Models\Product;
use App\Models\Unit;

class ManufacturingStockMovement extends NsModel
{
    protected $table = 'ns_manufacturing_stock_movements';

    protected $fillable = [
        'order_id',
        'product_id',
        'unit_id',
        'quantity',
        'type',
        'cost_at_time'
    ];

    protected $casts = [
        'quantity' => \App\Casts\FloatConvertCasting::class,
        'cost_at_time' => \App\Casts\FloatConvertCasting::class
    ];

    const TYPE_CONSUMPTION = 'consumption';
    const TYPE_PRODUCTION = 'production';
    const TYPE_WASTE = 'waste';

    public function order()
    {
        return $this->belongsTo(ManufacturingOrder::class, 'order_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }
}
