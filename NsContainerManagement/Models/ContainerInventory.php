<?php

namespace Modules\NsContainerManagement\Models;

use App\Models\NsModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContainerInventory extends NsModel
{
    protected $table = 'ns_container_inventory';

    protected $fillable = [
        'container_type_id',
        'quantity_on_hand',
        'quantity_reserved',
        'last_adjustment_date',
        'last_adjustment_by',
        'last_adjustment_reason',
    ];

    protected $casts = [
        'last_adjustment_date' => 'datetime',
    ];

    public function containerType(): BelongsTo
    {
        return $this->belongsTo(ContainerType::class, 'container_type_id');
    }

    public function getAvailableQuantityAttribute(): int
    {
        return $this->quantity_on_hand - $this->quantity_reserved;
    }
}
