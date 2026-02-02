<?php

namespace Modules\NsContainerManagement\Models;

use App\Models\NsModel;
use App\Models\Product;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductContainer extends NsModel
{
    protected $table = 'ns_product_containers';

    protected $fillable = [
        'product_id',
        'unit_id',
        'container_type_id',
        'is_enabled',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function containerType(): BelongsTo
    {
        return $this->belongsTo(ContainerType::class, 'container_type_id');
    }
}
