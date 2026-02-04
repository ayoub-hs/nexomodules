<?php

namespace Modules\NsSpecialCustomer\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\NsSpecialCustomer\Database\Factories\SpecialCashbackHistoryFactory;
use App\Models\Customer;
use App\Models\CustomerAccountHistory;
use Carbon\Carbon;

/**
 * Special Cashback History Model
 * 
 * Tracks yearly cashback calculations and processing for special customers.
 * Maintains audit trail and financial integrity for all cashback operations.
 */
class SpecialCashbackHistory extends Model
{
    use HasFactory;

    protected $table = 'special_cashback_history';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'customer_id',
        'year',
        'total_purchases',
        'total_refunds',
        'cashback_percentage',
        'cashback_amount',
        // Manual cashback fields
        'amount',
        'percentage',
        'period_start',
        'period_end',
        'initiator',
        'transaction_id',
        'status',
        'processed_at',
        'reversed_at',
        'reversal_reason',
        'reversal_transaction_id',
        'reversal_author',
        'author',
        'description',
    ];

    protected static function newFactory(): SpecialCashbackHistoryFactory
    {
        return SpecialCashbackHistoryFactory::new();
    }

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'total_purchases' => 'decimal:5',
        'total_refunds' => 'decimal:5',
        'cashback_percentage' => 'decimal:2',
        'cashback_amount' => 'decimal:5',
        'amount' => 'decimal:5',
        'percentage' => 'decimal:2',
        'period_start' => 'datetime',
        'period_end' => 'datetime',
        'processed_at' => 'datetime',
        'reversed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Cashback status constants
     */
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_PROCESSED = 'processed';
    const STATUS_REVERSED = 'reversed';
    const STATUS_FAILED = 'failed';

    /**
     * Get the customer that owns the cashback history.
     */
    public function customer(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /**
     * Get the transaction that recorded the cashback.
     */
    public function transaction(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(CustomerAccountHistory::class, 'transaction_id');
    }

    /**
     * Get the reversal transaction if applicable.
     */
    public function reversalTransaction(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(CustomerAccountHistory::class, 'reversal_transaction_id');
    }

    /**
     * Get the author user who processed the cashback.
     */
    public function authorUser(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo('App\Models\User', 'author');
    }

    /**
     * Get the author user who reversed the cashback.
     */
    public function reversalAuthorUser(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo('App\Models\User', 'reversal_author');
    }

    /**
     * Scope to get processed cashback records.
     */
    public function scopeProcessed(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PROCESSED);
    }

    /**
     * Scope to get reversed cashback records.
     */
    public function scopeReversed(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_REVERSED);
    }

    /**
     * Scope to get cashback for a specific year.
     */
    public function scopeForYear(Builder $query, int $year): Builder
    {
        return $query->where('year', $year);
    }

    /**
     * Scope to get cashback for a specific customer.
     */
    public function scopeForCustomer(Builder $query, int $customerId): Builder
    {
        return $query->where('customer_id', $customerId);
    }

    /**
     * Scope to get cashback within a date range.
     */
    public function scopeBetweenDates(Builder $query, Carbon $startDate, Carbon $endDate): Builder
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Check if cashback has already been processed for a customer in a specific year.
     */
    public static function isProcessedForCustomerYear(int $customerId, int $year): bool
    {
        return static::where('customer_id', $customerId)
            ->where('year', $year)
            ->where('status', self::STATUS_PROCESSED)
            ->exists();
    }

    /**
     * Get cashback record for customer and year.
     */
    public static function getForCustomerYear(int $customerId, int $year): ?self
    {
        return static::where('customer_id', $customerId)
            ->where('year', $year)
            ->first();
    }

    /**
     * Get total cashback amount for a year.
     */
    public static function getTotalForYear(int $year): float
    {
        return static::where('year', $year)
            ->processed()
            ->sum('cashback_amount');
    }

    /**
     * Get cashback statistics for a year.
     */
    public static function getYearStatistics(int $year): array
    {
        $records = static::where('year', $year)->get();
        
        return [
            'year' => $year,
            'total_customers' => $records->count(),
            'processed_count' => $records->where('status', self::STATUS_PROCESSED)->count(),
            'reversed_count' => $records->where('status', self::STATUS_REVERSED)->count(),
            'total_purchases' => $records->sum('total_purchases'),
            'total_refunds' => $records->sum('total_refunds'),
            'total_cashback_processed' => $records->where('status', self::STATUS_PROCESSED)->sum('cashback_amount'),
            'total_cashback_reversed' => $records->where('status', self::STATUS_REVERSED)->sum('cashback_amount'),
            'net_cashback' => $records->sum(function($record) {
                return $record->status === self::STATUS_PROCESSED ? $record->cashback_amount : 
                      ($record->status === self::STATUS_REVERSED ? -$record->cashback_amount : 0);
            }),
            'average_cashback' => $records->where('status', self::STATUS_PROCESSED)->avg('cashback_amount'),
        ];
    }

    /**
     * Mark cashback as processed.
     */
    public function markAsProcessed(?int $transactionId = null): bool
    {
        return $this->update([
            'status' => self::STATUS_PROCESSED,
            'processed_at' => now(),
            'transaction_id' => $transactionId,
        ]);
    }

    /**
     * Mark cashback as reversed.
     */
    public function markAsReversed(string $reason, ?int $reversalTransactionId = null, ?int $reversalAuthorId = null): bool
    {
        return $this->update([
            'status' => self::STATUS_REVERSED,
            'reversed_at' => now(),
            'reversal_reason' => $reason,
            'reversal_transaction_id' => $reversalTransactionId,
            'reversal_author' => $reversalAuthorId,
        ]);
    }

    /**
     * Check if the cashback can be reversed.
     */
    public function canBeReversed(): bool
    {
        return $this->status === self::STATUS_PROCESSED && 
               $this->transaction_id !== null;
    }

    /**
     * Get the net cashback amount (processed minus reversed).
     */
    public function getNetAmountAttribute(): float
    {
        return $this->status === self::STATUS_PROCESSED ? $this->cashback_amount : 
               ($this->status === self::STATUS_REVERSED ? -$this->cashback_amount : 0);
    }

    /**
     * Get human-readable status label.
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'Pending',
            self::STATUS_PROCESSING => 'Processing',
            self::STATUS_PROCESSED => 'Processed',
            self::STATUS_REVERSED => 'Reversed',
            self::STATUS_FAILED => 'Failed',
            default => 'Unknown',
        };
    }

    /**
     * Get formatted cashback amount.
     */
    public function getFormattedCashbackAmountAttribute(): string
    {
        return number_format($this->cashback_amount, 2);
    }

    /**
     * Get formatted total purchases.
     */
    public function getFormattedTotalPurchasesAttribute(): string
    {
        return number_format($this->total_purchases, 2);
    }

    /**
     * Get formatted total refunds.
     */
    public function getFormattedTotalRefundsAttribute(): string
    {
        return number_format($this->total_refunds, 2);
    }

    /**
     * Boot the model and add event listeners.
     */
    protected static function booted(): void
    {
        static::creating(function ($cashback) {
            if (empty($cashback->status)) {
                $cashback->status = self::STATUS_PENDING;
            }
            if (empty($cashback->author)) {
                $cashback->author = auth()->id();
            }
            // Ensure year is set based on period_start when creating manual entries
            if (empty($cashback->year)) {
                if (! empty($cashback->period_start)) {
                    try {
                        $cashback->year = Carbon::parse($cashback->period_start)->year;
                    } catch (\Throwable $e) {
                        $cashback->year = now()->year;
                    }
                } else {
                    $cashback->year = now()->year;
                }
            }
        });

        static::updating(function ($cashback) {
            // Prevent modification of processed cashback unless reversing
            if ($cashback->isDirty('status') && $cashback->getOriginal('status') === self::STATUS_PROCESSED) {
                if ($cashback->status !== self::STATUS_REVERSED) {
                    throw new \Exception('Processed cashback can only be reversed, not modified');
                }
            }
        });
    }
}
