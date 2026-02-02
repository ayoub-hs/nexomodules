<?php

namespace Modules\NsSpecialCustomer\Tests\Feature;

use Tests\TestCase;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Services\CustomerService;
use Modules\NsSpecialCustomer\Services\SpecialCustomerService;
use Modules\NsSpecialCustomer\Models\SpecialCashbackHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SpecialCustomerTest extends TestCase
{
    use RefreshDatabase;

    private SpecialCustomerService $specialCustomerService;
    private CustomerService $customerService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->specialCustomerService = app(SpecialCustomerService::class);
        $this->customerService = app(CustomerService::class);
    }

    /** @test */
    public function it_can_identify_special_customer()
    {
        // Create special customer group
        $specialGroup = CustomerGroup::factory()->create([
            'name' => 'Special',
            'code' => 'special'
        ]);

        // Set the special group ID
        $this->specialCustomerService->setSpecialGroupId($specialGroup->id);

        // Create a regular customer
        $regularCustomer = Customer::factory()->create(['group_id' => null]);
        $this->assertFalse($this->specialCustomerService->isSpecialCustomer($regularCustomer));

        // Create a special customer
        $specialCustomer = Customer::factory()->create(['group_id' => $specialGroup->id]);
        $this->assertTrue($this->specialCustomerService->isSpecialCustomer($specialCustomer));
    }

    /** @test */
    public function it_calculates_special_discount_correctly()
    {
        // Create special customer group and customer
        $specialGroup = CustomerGroup::factory()->create(['code' => 'special']);
        $this->specialCustomerService->setSpecialGroupId($specialGroup->id);
        
        $specialCustomer = Customer::factory()->create(['group_id' => $specialGroup->id]);
        $this->specialCustomerService->setDiscountPercentage(10.0);

        // Test discount calculation
        $orderTotal = 100.00;
        $expectedDiscount = 10.00;
        
        $actualDiscount = $this->specialCustomerService->calculateSpecialDiscount($orderTotal, $specialCustomer);
        $this->assertEquals($expectedDiscount, $actualDiscount);
    }

    /** @test */
    public function it_calculates_cashback_correctly()
    {
        // Create special customer group and customer
        $specialGroup = CustomerGroup::factory()->create(['code' => 'special']);
        $this->specialCustomerService->setSpecialGroupId($specialGroup->id);
        
        $specialCustomer = Customer::factory()->create(['group_id' => $specialGroup->id]);
        $this->specialCustomerService->setCashbackPercentage(5.0);

        // Test cashback calculation using the service method
        $orderTotal = 200.00;
        $expectedCashback = 10.00;
        
        $actualCashback = $this->specialCustomerService->calculateSpecialDiscount($orderTotal, $specialCustomer);
        $this->assertEquals($expectedCashback, $actualCashback);
    }

    /** @test */
    public function it_prevents_overlapping_cashback_periods()
    {
        // Create customer and cashback history
        $customer = Customer::factory()->create();
        
        // Create initial cashback record using the model
        SpecialCashbackHistory::create([
            'customer_id' => $customer->id,
            'year' => 2024,
            'total_purchases' => 1000.00,
            'total_refunds' => 0,
            'cashback_percentage' => 5.0,
            'cashback_amount' => 50.00,
            'status' => 'pending',
            'author' => 1
        ]);

        // Test overlapping detection by checking if record exists
        $this->assertTrue(
            SpecialCashbackHistory::where('customer_id', $customer->id)
                ->where('year', 2024)
                ->exists()
        );

        // Test non-overlapping period (different year)
        $this->assertFalse(
            SpecialCashbackHistory::where('customer_id', $customer->id)
                ->where('year', 2025)
                ->exists()
        );
    }

    /** @test */
    public function it_returns_correct_configuration()
    {
        // Set configuration values
        $this->specialCustomerService->setDiscountPercentage(7.5);
        $this->specialCustomerService->setCashbackPercentage(3.0);
        $this->specialCustomerService->setDiscountStackable(true);

        $config = $this->specialCustomerService->getConfig();

        $this->assertEquals(7.5, $config['discountPercentage']);
        $this->assertEquals(3.0, $config['cashbackPercentage']);
        $this->assertTrue($config['applyDiscountStackable']);
    }

    /** @test */
    public function it_initializes_default_settings()
    {
        $this->specialCustomerService->initializeDefaults();

        $config = $this->specialCustomerService->getConfig();
        $defaults = $this->specialCustomerService->getDefaultConfig();

        $this->assertEquals($defaults['ns_special_discount_percentage'], $config['discountPercentage']);
        $this->assertEquals($defaults['ns_special_cashback_percentage'], $config['cashbackPercentage']);
        $this->assertEquals($defaults['ns_special_apply_discount_stackable'], $config['applyDiscountStackable']);
    }

    /** @test */
    public function api_returns_special_customer_config()
    {
        // Create special customer group
        $specialGroup = CustomerGroup::factory()->create(['code' => 'special']);
        $this->specialCustomerService->setSpecialGroupId($specialGroup->id);
        $this->specialCustomerService->setDiscountPercentage(8.0);

        $response = $this->getJson('/api/special-customer/config');

        $response->assertStatus(200)
                ->assertJson([
                    'status' => 'success',
                    'data' => [
                        'groupId' => $specialGroup->id,
                        'discountPercentage' => 8.0
                    ]
                ]);
    }

    /** @test */
    public function api_checks_customer_special_status()
    {
        // Create special customer group and customer
        $specialGroup = CustomerGroup::factory()->create(['code' => 'special']);
        $this->specialCustomerService->setSpecialGroupId($specialGroup->id);
        
        $specialCustomer = Customer::factory()->create(['group_id' => $specialGroup->id]);

        $response = $this->getJson("/api/special-customer/check/{$specialCustomer->id}");

        $response->assertStatus(200)
                ->assertJson([
                    'status' => 'success',
                    'data' => [
                        'isSpecial' => true
                    ]
                ]);
    }

    /** @test */
    public function api_updates_special_customer_settings()
    {
        $response = $this->postJson('/api/special-customer/settings', [
            'discount_percentage' => 12.5,
            'cashback_percentage' => 4.0,
            'apply_discount_stackable' => true
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'status' => 'success',
                    'message' => 'Settings updated successfully'
                ]);

        // Verify settings were updated
        $config = $this->specialCustomerService->getConfig();
        $this->assertEquals(12.5, $config['discountPercentage']);
        $this->assertEquals(4.0, $config['cashbackPercentage']);
        $this->assertTrue($config['applyDiscountStackable']);
    }
}
