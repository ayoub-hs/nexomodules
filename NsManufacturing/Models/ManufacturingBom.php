<?php

namespace Modules\NsManufacturing\Models;

use App\Models\NsModel;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\SoftDeletes;

class ManufacturingBom extends NsModel
{
    use SoftDeletes;

    protected $table = 'ns_manufacturing_boms';

    protected $fillable = [
        'uuid',
        'name',
        'product_id',
        'unit_id',
        'quantity',
        'is_active',
        'description',
        'author'
    ];

    protected $casts = [
        'quantity' => \App\Casts\FloatConvertCasting::class,
        'is_active' => 'boolean'
    ];

    public function items()
    {
        return $this->hasMany(ManufacturingBomItem::class, 'bom_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function authorUser()
    {
        return $this->belongsTo(User::class, 'author');
    }
}
