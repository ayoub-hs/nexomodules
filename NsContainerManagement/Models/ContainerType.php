<?php

namespace Modules\NsContainerManagement\Models;

use App\Models\NsModel;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ContainerType extends NsModel
{
    protected $table = 'ns_container_types';

    protected $fillable = [
        'name',
        'capacity',
        'capacity_unit',
        'deposit_fee',
        'description',
        'is_active',
        'author',
    ];

    protected $casts = [
        'capacity' => 'decimal:3',
        'deposit_fee' => 'decimal:5',
        'is_active' => 'boolean',
    ];

    public function inventory(): HasOne
    {
        return $this->hasOne(ContainerInventory::class, 'container_type_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(ContainerMovement::class, 'container_type_id');
    }

    public function customerBalances(): HasMany
    {
        return $this->hasMany(CustomerContainerBalance::class, 'container_type_id');
    }

    public function productContainers(): HasMany
    {
        return $this->hasMany(ProductContainer::class, 'container_type_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
