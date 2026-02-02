<?php

namespace Modules\NsContainerManagement\Models;

use App\Models\Customer;
use App\Models\NsModel;
use App\Models\Order;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\NsContainerManagement\Services\ContainerLedgerService;

class ContainerMovement extends NsModel
{
    protected $table = 'ns_container_movements';

    public $timestamps = true;

    const DIRECTION_OUT = 'out';
    const DIRECTION_IN = 'in';
    const DIRECTION_CHARGE = 'charge';
    const DIRECTION_ADJUSTMENT = 'adjustment';

    const SOURCE_POS_SALE = 'pos_sale';
    const SOURCE_MANUAL_GIVE = 'manual_give';
    const SOURCE_MANUAL_RETURN = 'manual_return';
    const SOURCE_CHARGE_TRANSACTION = 'charge_transaction';
    const SOURCE_INVENTORY_ADJUSTMENT = 'inventory_adjustment';
    const SOURCE_PROCUREMENT = 'procurement';

    protected $fillable = [
        'container_type_id',
        'customer_id',
        'order_id',
        'direction',
        'quantity',
        'unit_deposit_fee',
        'total_deposit_value',
        'source_type',
        'reference_id',
        'note',
        'author',
    ];

    protected $casts = [
        'unit_deposit_fee' => 'decimal:5',
        'total_deposit_value' => 'decimal:5',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::created(function ($movement) {
            $ledgerService = app(ContainerLedgerService::class);
            $ledgerService->handleMovementEffect($movement);
        });
    }

    public function containerType(): BelongsTo
    {
        return $this->belongsTo(ContainerType::class, 'container_type_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function scopeForCustomer($query, int $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeForContainerType($query, int $containerTypeId)
    {
        return $query->where('container_type_id', $containerTypeId);
    }

    public function scopeDirection($query, string $direction)
    {
        return $query->where('direction', $direction);
    }
}
