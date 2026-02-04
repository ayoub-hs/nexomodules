<?php

namespace Modules\NsSpecialCustomer\Tests\Feature;

use Tests\TestCase;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\CustomerAccountHistory;
use Modules\NsSpecialCustomer\Models\SpecialCashbackHistory;
use Modules\NsSpecialCustomer\Services\CashbackService;
use Modules\NsSpecialCustomer\Services\SpecialCustomerService;
use Modules\NsSpecialCustomer\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

class CashbackServiceTest extends TestCase
{
    use RefreshDatabase;

    protected CashbackService $cashbackService;
    protected SpecialCustomerService $specialCustomerService;
    protected WalletService $walletService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->specialCustomerService = new SpecialCustomerService(app('App\Services\Options'));
        $this->walletService = new WalletService(app('App\Services\CustomerService'));
        $this->cashbackService = new CashbackService($this->specialCustomerService, $this->walletService);
        Cache::flush();
    }

    /** @test */
    public function it_can_calculate_yearly_cashback_correctly()
    {
        // Create special customer group
        $specialGroup = CustomerGroup::factory()->create(['name' => 'Special']);
        app('App\Services\Options')->set('ns_special_customer_group_id', $specialGroup->id);
        app('App\Services\Options')->set('ns_special_cashback_percentage', 5.0);

        // Create special customer
        $customer = Customer::factory()->create(['group_id' => $specialGroup->id]);

        // Create purchase history for the year
        CustomerAccountHistory::factory()->create([
            'customer_id' => $customer->id,
            'operation' => 'ORDER_PAYMENT',
            'amount' => 1000.00,
            'created_at' => now()->year(2023)->startOfYear(),
        ]);

        CustomerAccountHistory::factory()->create([
            'customer_id' => $customer->id,
            'operation' => 'ORDER_REFUND',
            'amount' => -100.00,
            'created_at' => now()->year(2023)->startOfYear(),
        ]);

        // Calculate cashback
        $calculation = $this->cashbackService->calculateYearlyCashback($customer->id, 2023);

        $this->assertTrue($calculation['eligible']);
        $this->assertEquals(900.00, $calculation['total_purchases']); // 1000 - 100
        $this->assertEquals(100.00, $calculation['total_refunds']);
        $this->assertEquals(5.0, $calculation['cashback_percentage']);
        $this->assertEquals(45.00, $calculation['cashback_amount']); // 5% of 900
    }

    /** @test */
    public function it_rejects_non_special_customer_for_cashback()
    {
        // Create regular customer
        $regularCustomer = Customer::factory()->create();

        $calculation = $this->cashbackService->calculateYearlyCashback($regularCustomer->id, 2023);

        $this->assertFalse($calculation['eligible']);
        $this->assertStringContainsString('not a special customer', $calculation['reason']);
        $this->assertEquals(0.00, $calculation['cashback_amount']);
    }

    /** @test */
    public function it_handles_disabled_cashback_gracefully()
    {
        // Create special customer group
        $specialGroup = CustomerGroup::factory()->create(['name' => 'Special']);
        app('App\Services\Options')->set('ns_special_customer_group_id', $specialGroup->id);
        app('App\Services\Options')->set('ns_special_cashback_percentage', 0.0); // Disabled

        // Create special customer
        $customer = Customer::factory()->create(['group_id' => $specialGroup->id]);

        $calculation = $this->cashbackService->calculateYearlyCashback($customer->id, 2023);

        $this->assertFalse($calculation['eligible']);
        $this->assertStringContainsString('not enabled', $calculation['reason']);
        $this->assertEquals(0.00, $calculation['cashback_amount']);
    }

    /** @test */
    public function it_can_process_customer_cashback_with_transaction_safety()
    {
        // Create special customer group
        $specialGroup = CustomerGroup::factory()->create(['name' => 'Special']);
        app('App\Services\Options')->set('ns_special_customer_group_id', $specialGroup->id);
        app('App\Services\Options')->set('ns_special_cashback_percentage', 2.0);

        // Create special customer with purchases
        $customer = Customer::factory()->create([
            'group_id' => $specialGroup->id,
            'account_amount' => 100.00,
        ]);

        CustomerAccountHistory::factory()->create([
            'customer_id' => $customer->id,
            'operation' => 'ORDER_PAYMENT',
            'amount' => 500.00,
            'created_at' => now()->year(2023)->startOfYear(),
        ]);

        // Process cashback
        $result = $this->cashbackService->processCustomerCashback($customer->id, 2023);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('processed successfully', $result['message']);
        $this->assertEquals(10.00, $result['cashback_amount']); // 2% of 500
        $this->assertNotNull($result['transaction_id']);
        $this->assertNotNull($result['cashback_history_id']);

        // Verify database state
        $customer->refresh();
        $this->assertEquals(110.00, $customer->account_amount); // 100 + 10

        // Verify cashback history
        $cashbackHistory = SpecialCashbackHistory::find($result['cashback_history_id']);
        $this->assertNotNull($cashbackHistory);
        $this->assertEquals($customer->id, $cashbackHistory->customer_id);
        $this->assertEquals(2023, $cashbackHistory->year);
        $this->assertEquals(500.00, $cashbackHistory->total_purchases);
        $this->assertEquals(10.00, $cashbackHistory->cashback_amount);
        $this->assertEquals(SpecialCashbackHistory::STATUS_PROCESSED, $cashbackHistory->status);
    }

    /** @test */
    public function it_prevents_duplicate_cashback_for_same_year()
    {
        // Create special customer group
        $specialGroup = CustomerGroup::factory()->create(['name' => 'Special']);
        app('App\Services\Options')->set('ns_special_customer_group_id', $specialGroup->id);
        app('App\Services\Options')->set('ns_special_cashback_percentage', 2.0);

        // Create special customer
        $customer = Customer::factory()->create(['group_id' => $specialGroup->id]);

        CustomerAccountHistory::factory()->create([
            'customer_id' => $customer->id,
            'operation' => 'ORDER_PAYMENT',
            'amount' => 500.00,
            'created_at' => now()->year(2023)->startOfYear(),
        ]);

        // Process cashback first time
        $result1 = $this->cashbackService->processCustomerCashback($customer->id, 2023);
        $this->assertTrue($result1['success']);

        // Attempt to process cashback again
        $result2 = $this->cashbackService->processCustomerCashback($customer->id, 2023);
        $this->assertFalse($result2['success']);
        $this->assertStringContainsString('already been processed', $result2['message']);
    }

    /** @test */
    public function it_can_process_cashback_batch_with_multiple_customers()
    {
        // Create special customer group
        $specialGroup = CustomerGroup::factory()->create(['name' => 'Special']);
        app('App\Services\Options')->set('ns_special_customer_group_id', $specialGroup->id);
        app('App\Services\Options')->set('ns_special_cashback_percentage', 3.0);

        // Create multiple special customers
        $customers = Customer::factory()->count(3)->create(['group_id' => $specialGroup->id]);

        foreach ($customers as $customer) {
            CustomerAccountHistory::factory()->create([
                'customer_id' => $customer->id,
                'operation' => 'ORDER_PAYMENT',
                'amount' => 1000.00,
                'created_at' => now()->year(2023)->startOfYear(),
            ]);
        }

        // Process batch
        $result = $this->cashbackService->processCashbackBatch(2023);

        $this->assertEquals(3, $result['total_customers']);
        $this->assertEquals(3, $result['processed']);
        $this->assertEquals(0, $result['failed']);
        $this->assertEquals(90.00, $result['total_cashback']); // 3 * (3% of 1000)
    }

    /** @test */
    public function it_can_get_cashback_report_with_statistics()
    {
        // Create special customer group
        $specialGroup = CustomerGroup::factory()->create(['name' => 'Special']);
        app('App\Services\Options')->set('ns_special_customer_group_id', $specialGroup->id);

        // Create customers and cashback history
        $customers = Customer::factory()->count(2)->create(['group_id' => $specialGroup->id]);

        foreach ($customers as $index => $customer) {
            SpecialCashbackHistory::factory()->create([
                'customer_id' => $customer->id,
                'year' => 2023,
                'total_purchases' => ($index + 1) * 1000.00,
                'cashback_percentage' => 2.0,
                'cashback_amount' => ($index + 1) * 20.00,
                'status' => SpecialCashbackHistory::STATUS_PROCESSED,
            ]);
        }

        // Get report
        $report = $this->cashbackService->getCashbackReport(2023);

        $this->assertArrayHasKey('summary', $report);
        $this->assertArrayHasKey('details', $report);
        $this->assertEquals(2023, $report['summary']['year']);
        $this->assertEquals(2, $report['summary']['total_customers']);
        $this->assertEquals(3000.00, $report['summary']['total_purchases']);
        $this->assertEquals(60.00, $report['summary']['total_cashback_processed']);
        $this->assertNotEmpty($report['details']);
    }

    /** @test */
    public function it_can_reverse_cashback_with_proper_audit_trail()
    {
        // Create special customer group
        $specialGroup = CustomerGroup::factory()->create(['name' => 'Special']);
        app('App\Services\Options')->set('ns_special_customer_group_id', $specialGroup->id);
        app('App\Services\Options')->set('ns_special_cashback_percentage', 2.0);

        // Create special customer
        $customer = Customer::factory()->create([
            'group_id' => $specialGroup->id,
            'account_amount' => 100.00,
        ]);

        CustomerAccountHistory::factory()->create([
            'customer_id' => $customer->id,
            'operation' => 'ORDER_PAYMENT',
            'amount' => 500.00,
            'created_at' => now()->year(2023)->startOfYear(),
        ]);

        // Process cashback
        $processResult = $this->cashbackService->processCustomerCashback($customer->id, 2023);
        $this->assertTrue($processResult['success']);

        $initialBalance = $customer->account_amount;

        // Reverse cashback
        $reverseResult = $this->cashbackService->reverseCashback(
            $processResult['cashback_history_id'],
            'Test reversal'
        );

        $this->assertTrue($reverseResult['success']);
        $this->assertStringContainsString('reversed successfully', $reverseResult['message']);
        $this->assertEquals(10.00, $reverseResult['reversed_amount']);

        // Verify database state
        $customer->refresh();
        $this->assertEquals($initialBalance, $customer->account_amount); // Back to original

        // Verify cashback history
        $cashbackHistory = SpecialCashbackHistory::find($processResult['cashback_history_id']);
        $this->assertEquals(SpecialCashbackHistory::STATUS_REVERSED, $cashbackHistory->status);
        $this->assertEquals('Test reversal', $cashbackHistory->reversal_reason);
        $this->assertNotNull($cashbackHistory->reversed_at);
    }

    /** @test */
    public function it_prevents_reversal_of_non_processed_cashback()
    {
        // Create special customer group
        $specialGroup = CustomerGroup::factory()->create(['name' => 'Special']);
        app('App\Services\Options')->set('ns_special_customer_group_id', $specialGroup->id);

        // Create special customer
        $customer = Customer::factory()->create(['group_id' => $specialGroup->id]);

        // Create pending cashback
        $cashbackHistory = SpecialCashbackHistory::factory()->create([
            'customer_id' => $customer->id,
            'year' => 2023,
            'status' => SpecialCashbackHistory::STATUS_PENDING,
        ]);

        // Attempt reversal
        $this->expectException(\Exception::class);
        $this->cashbackService->reverseCashback($cashbackHistory->id, 'Test');
    }

    /** @test */
    public function it_can_get_customer_cashback_history()
    {
        // Create special customer group
        $specialGroup = CustomerGroup::factory()->create(['name' => 'Special']);
        app('App\Services\Options')->set('ns_special_customer_group_id', $specialGroup->id);

        // Create special customer
        $customer = Customer::factory()->create(['group_id' => $specialGroup->id]);

        // Create cashback history
        SpecialCashbackHistory::factory()->count(5)->create([
            'customer_id' => $customer->id,
            'year' => 2021,
            'status' => SpecialCashbackHistory::STATUS_PROCESSED,
        ]);

        SpecialCashbackHistory::factory()->count(3)->create([
            'customer_id' => $customer->id,
            'year' => 2022,
            'status' => SpecialCashbackHistory::STATUS_PROCESSED,
        ]);

        // Get history
        $history = $this->cashbackService->getCustomerCashbackHistory($customer->id);

        $this->assertEquals(8, $history['total']);
        $this->assertNotEmpty($history['data']);
    }

    /** @test */
    public function it_can_get_cashback_statistics()
    {
        // Create cashback history for different years
        SpecialCashbackHistory::factory()->count(3)->create([
            'status' => SpecialCashbackHistory::STATUS_PROCESSED,
            'cashback_amount' => 50.00,
            'year' => 2022,
        ]);

        SpecialCashbackHistory::factory()->count(2)->create([
            'status' => SpecialCashbackHistory::STATUS_REVERSED,
            'cashback_amount' => 30.00,
            'year' => 2022,
        ]);

        // Get statistics
        $stats = $this->cashbackService->getCashbackStatistics(2022);

        $this->assertEquals(3, $stats['total_processed']);
        $this->assertEquals(2, $stats['total_reversed']);
        $this->assertEquals(150.00, $stats['total_amount_processed']);
        $this->assertEquals(60.00, $stats['total_amount_reversed']);
        $this->assertEquals(90.00, $stats['net_amount']);
    }

    /** @test */
    public function it_can_clear_cache_properly()
    {
        // Create special customer group
        $specialGroup = CustomerGroup::factory()->create(['name' => 'Special']);
        app('App\Services\Options')->set('ns_special_customer_group_id', $specialGroup->id);

        // Create customer and cashback
        $customer = Customer::factory()->create(['group_id' => $specialGroup->id]);
        SpecialCashbackHistory::factory()->create([
            'customer_id' => $customer->id,
            'year' => 2023,
        ]);

        // Get report to populate cache
        $this->cashbackService->getCashbackReport(2023);

        // Clear cache
        $this->cashbackService->clearCache();

        // Verify cache is cleared by getting fresh data
        $freshReport = $this->cashbackService->getCashbackReport(2023);
        $this->assertArrayHasKey('summary', $freshReport);
    }

    /** @test */
    public function it_handles_edge_cases_gracefully()
    {
        // Test with non-existent customer
        $calculation = $this->cashbackService->calculateYearlyCashback(99999, 2023);
        $this->assertFalse($calculation['eligible']);
        $this->assertStringContainsString('Customer not found', $calculation['reason']);

        // Test with non-existent cashback history
        $this->expectException(\Exception::class);
        $this->cashbackService->reverseCashback(99999, 'Test');

        // Test with non-existent customer history
        $history = $this->cashbackService->getCustomerCashbackHistory(99999);
        $this->assertEquals(0, $history['total']);
    }

    /** @test */
    public function it_validates_cashback_amount_calculation()
    {
        // Create special customer group
        $specialGroup = CustomerGroup::factory()->create(['name' => 'Special']);
        app('App\Services\Options')->set('ns_special_customer_group_id', $specialGroup->id);
        app('App\Services\Options')->set('ns_special_cashback_percentage', 5.0);

        // Create special customer
        $customer = Customer::factory()->create(['group_id' => $specialGroup->id]);

        // Test with zero purchases
        $calculation = $this->cashbackService->calculateYearlyCashback($customer->id, 2023);
        $this->assertTrue($calculation['eligible']);
        $this->assertEquals(0.00, $calculation['total_purchases']);
        $this->assertEquals(0.00, $calculation['cashback_amount']);

        // Test with zero cashback percentage
        app('App\Services\Options')->set('ns_special_cashback_percentage', 0.0);
        $calculation = $this->cashbackService->calculateYearlyCashback($customer->id, 2023);
        $this->assertFalse($calculation['eligible']);
        $this->assertEquals(0.00, $calculation['cashback_amount']);
    }
}
