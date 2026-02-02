<?php

namespace Modules\NsManufacturing\Models;

use App\Models\NsModel;
use App\Models\Product;
use App\Models\Unit;

class ManufacturingBomItem extends NsModel
{
    protected $table = 'ns_manufacturing_bom_items';

    protected $fillable = [
        'bom_id',
        'product_id',
        'unit_id',
        'quantity',
        'waste_percent',
        'cost_allocation'
    ];

    protected $casts = [
        'quantity' => \App\Casts\FloatConvertCasting::class,
        'waste_percent' => \App\Casts\FloatConvertCasting::class,
        'cost_allocation' => \App\Casts\FloatConvertCasting::class
    ];

    public function bom()
    {
        return $this->belongsTo(ManufacturingBom::class, 'bom_id');
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
