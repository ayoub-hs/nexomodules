<?php

namespace Modules\NsSpecialCustomer\Tests\Feature;

use Tests\TestCase;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\CustomerAccountHistory;
use Modules\NsSpecialCustomer\Services\SpecialCustomerService;
use Modules\NsSpecialCustomer\Models\SpecialCashbackHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CashbackTest extends TestCase
{
    use RefreshDatabase;

    private SpecialCustomerService $specialCustomerService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->specialCustomerService = app(SpecialCustomerService::class);
    }

    /** @test */
    public function it_processes_cashback_successfully()
    {
        // Create special customer group and customer
        $specialGroup = CustomerGroup::factory()->create(['code' => 'special']);
        $this->specialCustomerService->setSpecialGroupId($specialGroup->id);
        
        $customer = Customer::factory()->create([
            'group_id' => $specialGroup->id,
            'account_amount' => 100.00
        ]);

        $cashbackData = [
            'customer_id' => $customer->id,
            'amount' => 25.00,
            'percentage' => 5.0,
            'period_start' => '2024-01-01',
            'period_end' => '2024-01-31',
            'description' => 'Test cashback',
            'initiator' => 'admin'
        ];

        $response = $this->postJson('/api/special-customer/cashback', $cashbackData);

        $response->assertStatus(200)
                ->assertJson([
                    'status' => 'success',
                    'message' => 'Cashback processed successfully'
                ]);

        // Verify cashback history was created
        $this->assertDatabaseHas('ns_special_cashback_history', [
            'customer_id' => $customer->id,
            'amount' => 25.00,
            'percentage' => 5.0
        ]);

        // Verify customer account was credited
        $customer->refresh();
        $this->assertEquals(125.00, $customer->account_amount);
    }

    /** @test */
    public function it_prevents_cashback_for_non_special_customers()
    {
        // Create regular customer (not in special group)
        $customer = Customer::factory()->create(['group_id' => null]);

        $cashbackData = [
            'customer_id' => $customer->id,
            'amount' => 25.00,
            'percentage' => 5.0,
            'period_start' => '2024-01-01',
            'period_end' => '2024-01-31',
        ];

        $response = $this->postJson('/api/special-customer/cashback', $cashbackData);

        $response->assertStatus(400)
                ->assertJson([
                    'status' => 'error',
                    'message' => 'Customer is not a special customer'
                ]);
    }

    /** @test */
    public function it_prevents_overlapping_cashback_periods()
    {
        // Create special customer group and customer
        $specialGroup = CustomerGroup::factory()->create(['code' => 'special']);
        $this->specialCustomerService->setSpecialGroupId($specialGroup->id);
        
        $customer = Customer::factory()->create(['group_id' => $specialGroup->id]);

        // Create initial cashback record
        SpecialCashbackHistory::create([
            'customer_id' => $customer->id,
            'amount' => 50.00,
            'percentage' => 5.0,
            'period_start' => '2024-01-01',
            'period_end' => '2024-01-31',
            'initiator' => 'admin'
        ]);

        // Try to create overlapping cashback
        $cashbackData = [
            'customer_id' => $customer->id,
            'amount' => 30.00,
            'percentage' => 3.0,
            'period_start' => '2024-01-15',
            'period_end' => '2024-02-15',
        ];

        $response = $this->postJson('/api/special-customer/cashback', $cashbackData);

        $response->assertStatus(400)
                ->assertJson([
                    'status' => 'error',
                    'message' => 'Cashback period overlaps with existing period for this customer'
                ]);
    }

    /** @test */
    public function it_returns_cashback_history()
    {
        // Create special customer group and customer
        $specialGroup = CustomerGroup::factory()->create(['code' => 'special']);
        $this->specialCustomerService->setSpecialGroupId($specialGroup->id);
        
        $customer = Customer::factory()->create(['group_id' => $specialGroup->id]);

        // Create cashback history records
        SpecialCashbackHistory::factory()->count(3)->create([
            'customer_id' => $customer->id
        ]);

        $response = $this->getJson('/api/special-customer/cashback');

        $response->assertStatus(200)
                ->assertJson([
                    'status' => 'success'
                ])
                ->assertJsonCount(3, 'data.data');
    }

    /** @test */
    public function it_returns_customer_cashback_summary()
    {
        // Create special customer group and customer
        $specialGroup = CustomerGroup::factory()->create(['code' => 'special']);
        $this->specialCustomerService->setSpecialGroupId($specialGroup->id);
        
        $customer = Customer::factory()->create(['group_id' => $specialGroup->id]);

        // Create cashback history records
        SpecialCashbackHistory::factory()->create([
            'customer_id' => $customer->id,
            'amount' => 25.00
        ]);

        SpecialCashbackHistory::factory()->create([
            'customer_id' => $customer->id,
            'amount' => 15.00
        ]);

        $response = $this->getJson("/api/special-customer/cashback/customer/{$customer->id}");

        $response->assertStatus(200)
                ->assertJson([
                    'status' => 'success',
                    'data' => [
                        'total_cashback' => 40.00,
                        'is_special_customer' => true
                    ]
                ]);
    }

    /** @test */
    public function it_deletes_cashback_and_reverses_transaction()
    {
        // Create special customer group and customer
        $specialGroup = CustomerGroup::factory()->create(['code' => 'special']);
        $this->specialCustomerService->setSpecialGroupId($specialGroup->id);
        
        $customer = Customer::factory()->create([
            'group_id' => $specialGroup->id,
            'account_amount' => 150.00
        ]);

        // Create cashback history with transaction
        $transaction = CustomerAccountHistory::factory()->create([
            'customer_id' => $customer->id,
            'amount' => 50.00,
            'operation' => 'credit'
        ]);

        $cashback = SpecialCashbackHistory::create([
            'customer_id' => $customer->id,
            'amount' => 50.00,
            'percentage' => 5.0,
            'period_start' => '2024-01-01',
            'period_end' => '2024-01-31',
            'transaction_id' => $transaction->id,
            'initiator' => 'admin'
        ]);

        $response = $this->deleteJson("/api/special-customer/cashback/{$cashback->id}");

        $response->assertStatus(200)
                ->assertJson([
                    'status' => 'success',
                    'message' => 'Cashback record deleted and reversed successfully'
                ]);

        // Verify cashback record was deleted
        $this->assertDatabaseMissing('ns_special_cashback_history', [
            'id' => $cashback->id
        ]);

        // Verify reversal transaction was created
        $this->assertDatabaseHas('ns_customer_account_history', [
            'customer_id' => $customer->id,
            'amount' => 50.00,
            'operation' => 'debit',
            'reference' => 'ns_special_cashback_reversal'
        ]);
    }

    /** @test */
    public function it_returns_cashback_statistics()
    {
        // Create special customer group and customers
        $specialGroup = CustomerGroup::factory()->create(['code' => 'special']);
        $this->specialCustomerService->setSpecialGroupId($specialGroup->id);
        
        $customer1 = Customer::factory()->create(['group_id' => $specialGroup->id]);
        $customer2 = Customer::factory()->create(['group_id' => $specialGroup->id]);

        // Create cashback history records
        SpecialCashbackHistory::factory()->create([
            'customer_id' => $customer1->id,
            'amount' => 25.00
        ]);

        SpecialCashbackHistory::factory()->create([
            'customer_id' => $customer2->id,
            'amount' => 35.00
        ]);

        $response = $this->getJson('/api/special-customer/cashback/statistics');

        $response->assertStatus(200)
                ->assertJson([
                    'status' => 'success',
                    'data' => [
                        'total_amount' => 60.00,
                        'total_records' => 2,
                        'unique_customers' => 2
                    ]
                ]);
    }
}
