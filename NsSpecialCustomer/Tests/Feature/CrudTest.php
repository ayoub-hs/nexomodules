<?php

namespace Modules\NsSpecialCustomer\Tests\Feature;

use Tests\TestCase;
use App\Models\Customer;
use App\Models\CustomerGroup;
use Modules\NsSpecialCustomer\Services\SpecialCustomerService;
use Modules\NsSpecialCustomer\Models\SpecialCashbackHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

class CrudTest extends TestCase
{
    use RefreshDatabase;

    private SpecialCustomerService $specialCustomerService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->specialCustomerService = app(SpecialCustomerService::class);
    }

    /** @test */
    public function special_cashback_crud_can_get_entries()
    {
        // Create test data
        $customer = Customer::factory()->create();
        $cashback = SpecialCashbackHistory::factory()->create([
            'customer_id' => $customer->id,
            'year' => 2024,
            'total_purchases' => 1000.00,
            'cashback_amount' => 50.00
        ]);

        // Test CRUD get entries
        $crudClass = \Modules\NsSpecialCustomer\Crud\SpecialCashbackCrud::class;
        $resource = new $crudClass;
        
        $entries = $resource->getEntries();
        
        $this->assertIsArray($entries);
        $this->assertArrayHasKey('data', $entries);
    }

    /** @test */
    public function special_cashback_crud_can_create_entry()
    {
        // Create test data
        $customer = Customer::factory()->create();
        
        $request = new Request([
            'customer_id' => $customer->id,
            'year' => 2024,
            'total_purchases' => 1000.00,
            'total_refunds' => 0,
            'cashback_percentage' => 5.0,
            'description' => 'Test cashback'
        ]);

        // Test CRUD create entry
        $crudClass = \Modules\NsSpecialCustomer\Crud\SpecialCashbackCrud::class;
        $resource = new $crudClass;
        
        $result = $resource->createEntry($request);
        
        $this->assertEquals('success', $result['status']);
        $this->assertArrayHasKey('data', $result);
        
        // Verify record was created
        $this->assertDatabaseHas('ns_special_cashback_history', [
            'customer_id' => $customer->id,
            'year' => 2024,
            'total_purchases' => 1000.00
        ]);
    }

    /** @test */
    public function special_cashback_crud_can_get_single_entry()
    {
        // Create test data
        $customer = Customer::factory()->create();
        $cashback = SpecialCashbackHistory::factory()->create([
            'customer_id' => $customer->id,
            'year' => 2024
        ]);

        // Test CRUD get entry
        $crudClass = \Modules\NsSpecialCustomer\Crud\SpecialCashbackCrud::class;
        $resource = new $crudClass;
        
        $result = $resource->getEntry($cashback->id);
        
        $this->assertEquals('success', $result['status']);
        $this->assertEquals($cashback->id, $result['data']['id']);
    }

    /** @test */
    public function special_cashback_crud_can_update_entry()
    {
        // Create test data
        $customer = Customer::factory()->create();
        $cashback = SpecialCashbackHistory::factory()->create([
            'customer_id' => $customer->id,
            'year' => 2024,
            'total_purchases' => 1000.00,
            'status' => 'pending'
        ]);

        $request = new Request([
            'total_purchases' => 1500.00,
            'description' => 'Updated description'
        ]);

        // Test CRUD update entry
        $crudClass = \Modules\NsSpecialCustomer\Crud\SpecialCashbackCrud::class;
        $resource = new $crudClass;
        
        $result = $resource->updateEntry($cashback->id, $request);
        
        $this->assertEquals('success', $result['status']);
        
        // Verify record was updated
        $this->assertDatabaseHas('ns_special_cashback_history', [
            'id' => $cashback->id,
            'total_purchases' => 1500.00,
            'description' => 'Updated description'
        ]);
    }

    /** @test */
    public function special_cashback_crud_can_delete_entry()
    {
        // Create test data
        $customer = Customer::factory()->create();
        $cashback = SpecialCashbackHistory::factory()->create([
            'customer_id' => $customer->id,
            'year' => 2024,
            'status' => 'pending'
        ]);

        // Test CRUD delete entry
        $crudClass = \Modules\NsSpecialCustomer\Crud\SpecialCashbackCrud::class;
        $resource = new $crudClass;
        
        $result = $resource->deleteEntry($cashback->id);
        
        $this->assertEquals('success', $result['status']);
        
        // Verify record was deleted
        $this->assertDatabaseMissing('ns_special_cashback_history', [
            'id' => $cashback->id
        ]);
    }

    /** @test */
    public function special_cashback_crud_prevents_updating_processed_entry()
    {
        // Create test data
        $customer = Customer::factory()->create();
        $cashback = SpecialCashbackHistory::factory()->create([
            'customer_id' => $customer->id,
            'year' => 2024,
            'status' => 'processed'
        ]);

        $request = new Request([
            'total_purchases' => 1500.00
        ]);

        // Test CRUD update entry should fail
        $crudClass = \Modules\NsSpecialCustomer\Crud\SpecialCashbackCrud::class;
        $resource = new $crudClass;
        
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Cannot update processed cashback entry');
        
        $resource->updateEntry($cashback->id, $request);
    }

    /** @test */
    public function special_cashback_crud_prevents_deleting_processed_entry()
    {
        // Create test data
        $customer = Customer::factory()->create();
        $cashback = SpecialCashbackHistory::factory()->create([
            'customer_id' => $customer->id,
            'year' => 2024,
            'status' => 'processed'
        ]);

        // Test CRUD delete entry should fail
        $crudClass = \Modules\NsSpecialCustomer\Crud\SpecialCashbackCrud::class;
        $resource = new $crudClass;
        
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Cannot delete processed cashback entry');
        
        $resource->deleteEntry($cashback->id);
    }

    /** @test */
    public function special_cashback_crud_prevents_duplicate_entries()
    {
        // Create test data
        $customer = Customer::factory()->create();
        SpecialCashbackHistory::factory()->create([
            'customer_id' => $customer->id,
            'year' => 2024
        ]);

        $request = new Request([
            'customer_id' => $customer->id,
            'year' => 2024,
            'total_purchases' => 1000.00,
            'cashback_percentage' => 5.0
        ]);

        // Test CRUD create entry should fail for duplicate
        $crudClass = \Modules\NsSpecialCustomer\Crud\SpecialCashbackCrud::class;
        $resource = new $crudClass;
        
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Cashback already exists for this customer and year');
        
        $resource->createEntry($request);
    }
}
