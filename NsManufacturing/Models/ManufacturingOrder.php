<?php

namespace Modules\NsManufacturing\Models;

use App\Models\NsModel;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\SoftDeletes;

class ManufacturingOrder extends NsModel
{
    use SoftDeletes;

    protected $table = 'ns_manufacturing_orders';

    protected $fillable = [
        'code',
        'bom_id',
        'product_id',
        'unit_id',
        'quantity',
        'status',
        'started_at',
        'completed_at',
        'author'
    ];

    protected $casts = [
        'quantity' => \App\Casts\FloatConvertCasting::class,
        'started_at' => 'datetime',
        'completed_at' => 'datetime'
    ];

    const STATUS_DRAFT = 'draft';
    const STATUS_PLANNED = 'planned';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_ON_HOLD = 'on_hold';

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

    public function movements()
    {
        return $this->hasMany(ManufacturingStockMovement::class, 'order_id');
    }
    
    public function authorUser()
    {
        return $this->belongsTo(User::class, 'author');
    }
}
