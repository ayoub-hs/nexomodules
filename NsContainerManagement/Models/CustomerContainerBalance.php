<?php

namespace Modules\NsContainerManagement\Models;

use App\Models\Customer;
use App\Models\NsModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerContainerBalance extends NsModel
{
    protected $table = 'ns_customer_container_balances';

    protected $fillable = [
        'customer_id',
        'container_type_id',
        'balance',
        'total_out',
        'total_in',
        'total_charged',
        'last_movement_at',
    ];

    protected $casts = [
        'balance' => 'integer',
        'total_out' => 'integer',
        'total_in' => 'integer',
        'total_charged' => 'integer',
        'last_movement_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function containerType(): BelongsTo
    {
        return $this->belongsTo(ContainerType::class, 'container_type_id');
    }

    public function scopeForCustomer($query, int $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeWithBalance($query)
    {
        return $query->where('balance', '>', 0);
    }

    public function getDepositValueAttribute(): float
    {
        return $this->balance * ($this->containerType->deposit_fee ?? 0);
    }
}
