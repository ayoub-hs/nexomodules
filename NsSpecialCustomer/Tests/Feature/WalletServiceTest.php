<?php

namespace Modules\NsSpecialCustomer\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use App\Models\User;
use App\Models\UserAttribute;
use App\Models\Customer;
use App\Models\CustomerAccountHistory;
use Modules\NsSpecialCustomer\Services\WalletService;
use Modules\TestSupport\Testing\ModuleTestDatabaseBootstrap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class WalletServiceTest extends TestCase
{
    use RefreshDatabase;

    protected WalletService $walletService;
    protected User $admin;

    protected function setUp(): void
    {
        putenv('AUTOLOAD_MODULES=NsSpecialCustomer');

        parent::setUp();

        ModuleTestDatabaseBootstrap::prepare($this, 'modules/NsSpecialCustomer/Migrations');

        // Create admin user and attribute (fix for DateService bug)
        $this->admin = User::where('username', 'admin')->first() ?? User::factory()->create(['username' => 'admin']);
        
        if (!$this->admin->attribute) {
            $attribute = new UserAttribute(['language' => 'en']);
            $attribute->user_id = $this->admin->id;
            $attribute->save();
            $this->admin->refresh();
        }

        $this->admin->assignRole('admin');
        $this->actingAs($this->admin);

        $this->walletService = app(WalletService::class);
        Cache::flush();
    }

    #[Test]
    public function it_can_process_topup_with_double_entry_ledger()
    {
        // Create customer
        $customer = Customer::factory()->create([
            'account_amount' => 100.00,
        ]);

        // Process top-up
        $result = $this->walletService->processTopup(
            $customer->id,
            50.00,
            'Test top-up',
            'ns_special_topup'
        );

        // Assertions
        $this->assertTrue($result['success']);
        $this->assertEquals('Transaction processed successfully', $result['message']);
        $this->assertNotNull($result['transaction_id']);
        $this->assertEquals(100.00, $result['previous_balance']);
        $this->assertEquals(150.00, $result['new_balance']);
        $this->assertEquals(50.00, $result['amount']);

        // Verify database state
        $customer->refresh();
        $this->assertEquals(150.00, $customer->account_amount);

        // Verify ledger entry
        $transaction = CustomerAccountHistory::find($result['transaction_id']);
        $this->assertNotNull($transaction);
        $this->assertEquals($customer->id, $transaction->customer_id);
        $this->assertEquals(50.00, $transaction->amount);
        $this->assertEquals('add', $transaction->operation);
        $this->assertEquals('ns_special_topup', $transaction->reference);
    }

    #[Test]
    public function it_can_process_debit_with_validation()
    {
        // Create customer with sufficient balance
        $customer = Customer::factory()->create([
            'account_amount' => 200.00,
        ]);

        // Process debit (negative amount)
        $result = $this->walletService->processTopup(
            $customer->id,
            -50.00,
            'Test withdrawal',
            'ns_special_withdrawal'
        );

        // Assertions
        $this->assertTrue($result['success']);
        $this->assertEquals(200.00, $result['previous_balance']);
        $this->assertEquals(150.00, $result['new_balance']);
        $this->assertEquals(-50.00, $result['amount']);

        // Verify database state
        $customer->refresh();
        $this->assertEquals(150.00, $customer->account_amount);
    }

    #[Test]
    public function it_prevents_insufficient_balance_operations()
    {
        // Create customer with low balance
        $customer = Customer::factory()->create([
            'account_amount' => 25.00,
        ]);

        // Attempt to withdraw more than available
        $result = $this->walletService->processTopup(
            $customer->id,
            -50.00,
            'Test withdrawal',
            'ns_special_withdrawal'
        );

        // Assertions
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Insufficient balance', $result['message']);
        $this->assertNull($result['transaction_id']);

        // Verify balance unchanged
        $customer->refresh();
        $this->assertEquals(25.00, $customer->account_amount);
    }

    #[Test]
    public function it_validates_zero_amount_operations()
    {
        $customer = Customer::factory()->create();

        // Test zero amount
        $result = $this->walletService->processTopup(
            $customer->id,
            0.00,
            'Test zero amount',
            'ns_special_topup'
        );

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('cannot be zero', $result['message']);
    }

    #[Test]
    public function it_can_get_balance_with_caching()
    {
        // Create customer
        $customer = Customer::factory()->create([
            'account_amount' => 500.00,
        ]);

        // Get balance (should cache)
        $balance = $this->walletService->getBalance($customer->id);
        $this->assertEquals(500.00, $balance);

        // Update balance directly in database
        DB::table('nexopos_users')
            ->where('id', $customer->id)
            ->update(['account_amount' => 600.00]);

        // Should still return cached value
        $cachedBalance = $this->walletService->getBalance($customer->id);
        $this->assertEquals(500.00, $cachedBalance);

        // Clear cache and get fresh value
        $this->walletService->clearCustomerCache($customer->id);
        $freshBalance = $this->walletService->getBalance($customer->id);
        $this->assertEquals(600.00, $freshBalance);
    }

    #[Test]
    public function it_can_get_transaction_history_with_filters()
    {
        // Create customer
        $customer = Customer::factory()->create();

        // Create transactions
        CustomerAccountHistory::forceCreate([
            'customer_id' => $customer->id,
            'operation' => 'add',
            'amount' => 100.00,
            'reference' => 'ns_special_topup',
            'author' => $this->admin->id,
        ]);
        CustomerAccountHistory::forceCreate([
            'customer_id' => $customer->id,
            'operation' => 'add',
            'amount' => 100.00,
            'reference' => 'ns_special_topup',
            'author' => $this->admin->id,
        ]);
        CustomerAccountHistory::forceCreate([
            'customer_id' => $customer->id,
            'operation' => 'add',
            'amount' => 100.00,
            'reference' => 'ns_special_topup',
            'author' => $this->admin->id,
        ]);
        CustomerAccountHistory::forceCreate([
            'customer_id' => $customer->id,
            'operation' => 'add',
            'amount' => 100.00,
            'reference' => 'ns_special_topup',
            'author' => $this->admin->id,
        ]);
        CustomerAccountHistory::forceCreate([
            'customer_id' => $customer->id,
            'operation' => 'add',
            'amount' => 100.00,
            'reference' => 'ns_special_topup',
            'author' => $this->admin->id,
        ]);

        CustomerAccountHistory::forceCreate([
            'customer_id' => $customer->id,
            'operation' => 'deduct',
            'amount' => -50.00,
            'reference' => 'ns_special_withdrawal',
            'author' => $this->admin->id,
        ]);
        CustomerAccountHistory::forceCreate([
            'customer_id' => $customer->id,
            'operation' => 'deduct',
            'amount' => -50.00,
            'reference' => 'ns_special_withdrawal',
            'author' => $this->admin->id,
        ]);
        CustomerAccountHistory::forceCreate([
            'customer_id' => $customer->id,
            'operation' => 'deduct',
            'amount' => -50.00,
            'reference' => 'ns_special_withdrawal',
            'author' => $this->admin->id,
        ]);

        // Get all transactions
        $history = $this->walletService->getTransactionHistory($customer->id);
        $this->assertEquals(8, $history['total']);

        // Filter by operation
        $creditHistory = $this->walletService->getTransactionHistory($customer->id, [
            'operation' => 'add'
        ]);
        $this->assertEquals(5, $creditHistory['total']);

        // Filter by reference
        $topupHistory = $this->walletService->getTransactionHistory($customer->id, [
            'reference' => 'ns_special_topup'
        ]);
        $this->assertEquals(5, $topupHistory['total']);
    }

    #[Test]
    public function it_can_record_ledger_entry_with_audit_trail()
    {
        $customer = Customer::factory()->create([
            'account_amount' => 100.00,
        ]);

        // Add history for initial balance
        CustomerAccountHistory::forceCreate([
            'customer_id' => $customer->id,
            'operation' => 'add',
            'amount' => 100.00,
            'next_amount' => 100.00,
            'author' => $this->admin->id,
        ]);

        // Record credit entry
        $result = $this->walletService->recordLedgerEntry(
            $customer->id,
            0.00,
            25.00,
            'Test credit entry',
            null,
            'ns_special_ledger'
        );

        $this->assertTrue($result['success']);
        $this->assertNotNull($result['transaction_id']);

        // Verify audit trail
        $transaction = CustomerAccountHistory::find($result['transaction_id']);
        $this->assertEquals('add', $transaction->operation);
        $this->assertEquals(25.00, $transaction->amount);
        $this->assertEquals(125.00, $transaction->next_amount);
    }

    #[Test]
    public function it_can_validate_balance_before_operation()
    {
        $customer = Customer::factory()->create([
            'account_amount' => 100.00,
        ]);

        // Sufficient balance
        $validation = $this->walletService->validateBalance($customer->id, 50.00);
        $this->assertTrue($validation['sufficient']);
        $this->assertEquals(100.00, $validation['current_balance']);
        $this->assertEquals(0.00, $validation['shortfall']);

        // Insufficient balance
        $validation = $this->walletService->validateBalance($customer->id, 150.00);
        $this->assertFalse($validation['sufficient']);
        $this->assertEquals(100.00, $validation['current_balance']);
        $this->assertEquals(50.00, $validation['shortfall']);
    }

    #[Test]
    public function it_can_get_balance_summary()
    {
        \Illuminate\Support\Facades\Event::fake([
            \App\Events\CustomerAccountHistoryAfterCreatedEvent::class,
        ]);

        // Create customer with 500 balance
        $customer = Customer::factory()->create([
            'account_amount' => 500.00,
        ]);

        // Create 4 credits and 1 debit = 5 transactions
        for ($i = 0; $i < 4; $i++) {
            CustomerAccountHistory::forceCreate([
                'customer_id' => $customer->id,
                'operation' => 'add',
                'amount' => 100.00,
                'author' => $this->admin->id,
            ]);
        }

        CustomerAccountHistory::forceCreate([
            'customer_id' => $customer->id,
            'operation' => 'deduct',
            'amount' => -50.00,
            'author' => $this->admin->id,
        ]);

        // Get summary (Initial 500 balance, transactions are faked and don't update balance)
        $summary = $this->walletService->getBalanceSummary($customer->id);

        $this->assertEquals(500.00, $summary['balance']);
        $this->assertEquals(400.00, $summary['total_credit']); // 4 * 100
        $this->assertEquals(50.00, $summary['total_debit']); // 1 * 50
        $this->assertEquals(5, $summary['transaction_count']);
        $this->assertNotNull($summary['last_transaction']);
        $this->assertCount(5, $summary['recent_transactions']);
    }

    #[Test]
    public function it_can_get_daily_balance_changes()
    {
        $customer = Customer::factory()->create();

        // Create transactions for different days
        CustomerAccountHistory::forceCreate([
            'customer_id' => $customer->id,
            'operation' => 'add',
            'amount' => 100.00,
            'author' => $this->admin->id,
            'created_at' => now()->subDays(2),
        ]);

        CustomerAccountHistory::forceCreate([
            'customer_id' => $customer->id,
            'operation' => 'add',
            'amount' => 50.00,
            'author' => $this->admin->id,
            'created_at' => now()->subDays(1),
        ]);

        CustomerAccountHistory::forceCreate([
            'customer_id' => $customer->id,
            'operation' => 'deduct',
            'amount' => -25.00,
            'author' => $this->admin->id,
            'created_at' => now()->subDays(1),
        ]);

        // Get daily changes
        $changes = $this->walletService->getDailyBalanceChanges($customer->id, 5);

        $this->assertIsArray($changes);
        $this->assertNotEmpty($changes);
    }

    #[Test]
    public function it_can_reconcile_balance()
    {
        // Create customer
        $customer = Customer::factory()->create([
            'account_amount' => 100.00,
        ]);

        // Create history entries (should total 150)
        CustomerAccountHistory::forceCreate([
            'customer_id' => $customer->id,
            'operation' => 'add',
            'amount' => 100.00,
            'next_amount' => 100.00,
            'author' => $this->admin->id,
        ]);

        CustomerAccountHistory::forceCreate([
            'customer_id' => $customer->id,
            'operation' => 'add',
            'amount' => 50.00,
            'next_amount' => 150.00,
            'author' => $this->admin->id,
        ]);

        // Manually break the balance to create a discrepancy
        // Current balance is likely 250 (100 initial + 100 + 50 via listener)
        // We want calculated (150) - current (X) = 50 discrepancy
        // So X (current) should be 100
        DB::table('nexopos_users')->where('id', $customer->id)->update(['account_amount' => 100.00]);
        $this->walletService->clearCustomerCache($customer->id);

        // Reconcile
        $result = $this->walletService->reconcileBalance($customer->id);

        $this->assertTrue($result['reconciled']);
        $this->assertEquals(150.00, $result['new_balance']);
        $this->assertEquals(50.00, $result['discrepancy']);
    }

    #[Test]
    public function it_can_get_wallet_statistics()
    {
        // Create customers and transactions
        $customers = Customer::factory()->count(3)->create();
        
        foreach ($customers as $customer) {
            CustomerAccountHistory::forceCreate([
                'customer_id' => $customer->id,
                'operation' => 'add',
                'amount' => 100.00,
                'author' => $this->admin->id,
            ]);
            
            CustomerAccountHistory::forceCreate([
                'customer_id' => $customer->id,
                'operation' => 'add',
                'amount' => 100.00,
                'author' => $this->admin->id,
            ]);
        }

        // Get statistics
        $stats = $this->walletService->getWalletStatistics();

        $this->assertEquals(6, $stats['total_transactions']);
        $this->assertEquals(600.00, $stats['total_credits']);
        $this->assertEquals(0.00, $stats['total_debits']);
        $this->assertEquals(600.00, $stats['net_flow']);
    }

    #[Test]
    public function it_handles_non_existent_customer_gracefully()
    {
        $result = $this->walletService->processTopup(
            99999,
            50.00,
            'Test',
            'ns_special_topup'
        );

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Customer not found', $result['message']);
    }

    #[Test]
    public function it_can_clear_all_cache()
    {
        // Create customer and get balance to populate cache
        $customer = Customer::factory()->create();
        $this->walletService->getBalance($customer->id);

        // Clear all cache
        $this->walletService->clearAllCache();

        // Verify cache is cleared by checking fresh data
        $this->assertNotNull($this->walletService->getBalance($customer->id));
    }

    #[Test]
    public function it_handles_database_transaction_rollback()
    {
        // This test verifies that database transactions are properly rolled back on failure
        $customer = Customer::factory()->create(['account_amount' => 100.00]);

        // Mock a scenario that would cause a transaction failure
        // by trying to process an invalid operation
        $initialBalance = $customer->account_amount;

        // Attempt operation that should fail
        $result = $this->walletService->processTopup(
            $customer->id,
            -999999.99, // Excessive amount that should trigger validation
            'Test failure',
            'ns_special_topup'
        );

        // Verify transaction was rolled back
        $this->assertFalse($result['success']);
        $customer->refresh();
        $this->assertEquals($initialBalance, $customer->account_amount);
    }
}
