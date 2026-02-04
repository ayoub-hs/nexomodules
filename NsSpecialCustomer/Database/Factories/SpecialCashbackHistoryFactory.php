<?php

namespace Modules\NsSpecialCustomer\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\NsSpecialCustomer\Models\SpecialCashbackHistory;
use App\Models\Customer;

class SpecialCashbackHistoryFactory extends Factory
{
    protected $model = SpecialCashbackHistory::class;

    public function definition(): array
    {
        $year = (int) now()->year;
        $totalPurchases = $this->faker->randomFloat(2, 100, 5000);
        $totalRefunds = $this->faker->randomFloat(2, 0, $totalPurchases * 0.3);
        $percentage = $this->faker->randomFloat(2, 1, 10);
        $cashback = round(max(0, $totalPurchases - $totalRefunds) * ($percentage / 100), 2);

        return [
            'customer_id' => Customer::factory(),
            'year' => $year,
            'total_purchases' => $totalPurchases,
            'total_refunds' => $totalRefunds,
            'cashback_percentage' => $percentage,
            'cashback_amount' => $cashback,
            'status' => SpecialCashbackHistory::STATUS_PENDING,
            'processed_at' => null,
            'reversed_at' => null,
            'reversal_reason' => null,
            'reversal_transaction_id' => null,
            'reversal_author' => null,
            'author' => null,
            'description' => $this->faker->sentence(),
        ];
    }
}
