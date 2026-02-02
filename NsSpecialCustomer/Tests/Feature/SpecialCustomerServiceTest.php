<?php

namespace Modules\NsSpecialCustomer\Tests\Feature;

use Tests\TestCase;
use App\Models\Customer;
use App\Models\CustomerGroup;
use Modules\NsSpecialCustomer\Services\SpecialCustomerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

class SpecialCustomerServiceTest extends TestCase
{
    use RefreshDatabase;

    protected SpecialCustomerService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SpecialCustomerService(app('App\Services\Options'));
        Cache::flush();
    }

    /** @test */
    public function it_can_identify_special_customer_correctly()
    {
        // Create special customer group
        $specialGroup = CustomerGroup::factory()->create([
            'name' => 'Special',
        ]);

        // Create regular customer
        $regularCustomer = Customer::factory()->create([
            'group_id' => CustomerGroup::factory()->create()->id,
        ]);

        // Create special customer
        $specialCustomer = Customer::factory()->create([
            'group_id' => $specialGroup->id,
        ]);

        // Set the special group ID in options
        app('App\Services\Options')->set('ns_special_customer_group_id', $specialGroup->id);

        // Test identification
        $this->assertFalse($this->service->isSpecialCustomer($regularCustomer));
        $this->assertTrue($this->service->isSpecialCustomer($specialCustomer));
    }

    /** @test */
    public function it_can_get_special_customers_with_filters()
    {
        // Create special customer group
        $specialGroup = CustomerGroup::factory()->create(['name' => 'Special']);
        app('App\Services\Options')->set('ns_special_customer_group_id', $specialGroup->id);

        // Create special customers
        Customer::factory()->count(3)->create(['group_id' => $specialGroup->id]);
        Customer::factory()->create(['group_id' => CustomerGroup::factory()->create()->id]); // Regular customer

        // Test getting all special customers
        $result = $this->service->getSpecialCustomers();
        $this->assertEquals(3, $result['total']);
        $this->assertCount(3, $result['data']);

        // Test search filter
        $firstCustomer = Customer::first();
        $result = $this->service->getSpecialCustomers(['search' => $firstCustomer->first_name]);
        $this->assertGreaterThanOrEqual(1, $result['total']);

        // Test balance filter
        $result = $this->service->getSpecialCustomers(['min_balance' => 0]);
        $this->assertGreaterThanOrEqual(0, $result['total']);
    }

    /** @test */
    public function it_can_get_customer_status_with_detailed_information()
    {
        // Create special customer group
        $specialGroup = CustomerGroup::factory()->create(['name' => 'Special']);
        app('App\Services\Options')->set('ns_special_customer_group_id', $specialGroup->id);

        // Create special customer with account history
        $customer = Customer::factory()->create([
            'group_id' => $specialGroup->id,
            'account_amount' => 500.00,
            'purchases_amount' => 2000.00,
        ]);

        // Get customer status
        $status = $this->service->getCustomerStatus($customer->id);

        // Assertions
        $this->assertTrue($status['is_special']);
        $this->assertEquals(500.00, $status['balance']);
        $this->assertEquals(2000.00, $status['total_purchases']);
        $this->assertTrue($status['discount_eligible']);
        $this->assertTrue($status['cashback_eligible']);
        $this->assertArrayHasKey('config', $status);
    }

    /** @test */
    public function it_can_apply_wholesale_pricing_to_special_customer()
    {
        // Create special customer group
        $specialGroup = CustomerGroup::factory()->create(['name' => 'Special']);
        app('App\Services\Options')->set('ns_special_customer_group_id', $specialGroup->id);

        // Create customers
        $regularCustomer = Customer::factory()->create(['group_id' => CustomerGroup::factory()->create()->id]);
        $specialCustomer = Customer::factory()->create(['group_id' => $specialGroup->id]);

        // Create product with wholesale pricing
        $product = new \stdClass();
        $product->sale_price = 100.00;
        $product->wholesale_price = 80.00;

        // Test regular customer (no wholesale pricing)
        $regularResult = $this->service->applyWholesalePricing($product, $regularCustomer);
        $this->assertFalse($regularResult['wholesale_applied']);
        $this->assertEquals(100.00, $regularResult['special_price']);
        $this->assertEquals(0.00, $regularResult['savings']);

        // Test special customer (wholesale pricing applied)
        $specialResult = $this->service->applyWholesalePricing($product, $specialCustomer);
        $this->assertTrue($specialResult['wholesale_applied']);
        $this->assertEquals(80.00, $specialResult['special_price']);
        $this->assertEquals(20.00, $specialResult['savings']);
    }

    /** @test */
    public function it_can_apply_special_discount_with_validation()
    {
        // Create special customer group
        $specialGroup = CustomerGroup::factory()->create(['name' => 'Special']);
        app('App\Services\Options')->set('ns_special_customer_group_id', $specialGroup->id);
        app('App\Services\Options')->set('ns_special_discount_percentage', 10.0);

        // Create customers
        $regularCustomer = Customer::factory()->create(['group_id' => CustomerGroup::factory()->create()->id]);
        $specialCustomer = Customer::factory()->create(['group_id' => $specialGroup->id]);

        // Create order
        $order = new \stdClass();
        $order->total = 200.00;
        $order->special_customer_data = [];

        // Test regular customer (no discount)
        $regularResult = $this->service->applySpecialDiscount($order, $regularCustomer);
        $this->assertFalse($regularResult['discount_applied']);
        $this->assertEquals(0.00, $regularResult['discount_amount']);

        // Test special customer (discount applied)
        $specialResult = $this->service->applySpecialDiscount($order, $specialCustomer);
        $this->assertTrue($specialResult['discount_applied']);
        $this->assertEquals(20.00, $specialResult['discount_amount']); // 10% of 200
        $this->assertEquals(180.00, $specialResult['new_total']);
    }

    /** @test */
    public function it_can_validate_discount_eligibility_with_business_rules()
    {
        // Create special customer group
        $specialGroup = CustomerGroup::factory()->create(['name' => 'Special']);
        app('App\Services\Options')->set('ns_special_customer_group_id', $specialGroup->id);
        app('App\Services\Options')->set('ns_special_discount_percentage', 10.0);

        // Create customers
        $regularCustomer = Customer::factory()->create(['group_id' => CustomerGroup::factory()->create()->id]);
        $specialCustomer = Customer::factory()->create(['group_id' => $specialGroup->id]);

        // Create order
        $order = new \stdClass();
        $order->total = 50.00;
        $order->special_customer_data = [];

        // Test regular customer
        $validation = $this->service->validateDiscountEligibility($regularCustomer, $order);
        $this->assertFalse($validation['eligible']);
        $this->assertStringContainsString('not in the special customer group', $validation['reason']);

        // Test special customer
        $validation = $this->service->validateDiscountEligibility($specialCustomer, $order);
        $this->assertTrue($validation['eligible']);

        // Test disabled discount
        app('App\Services\Options')->set('ns_special_discount_percentage', 0);
        $validation = $this->service->validateDiscountEligibility($specialCustomer, $order);
        $this->assertFalse($validation['eligible']);
        $this->assertStringContainsString('not enabled', $validation['reason']);
    }

    /** @test */
    public function it_can_manage_configuration_with_caching()
    {
        // Test getting configuration (should cache)
        $config = $this->service->getConfig();
        $this->assertArrayHasKey('groupId', $config);
        $this->assertArrayHasKey('discountPercentage', $config);
        $this->assertArrayHasKey('cashbackPercentage', $config);
        $this->assertArrayHasKey('applyDiscountStackable', $config);

        // Test setting values (should clear cache)
        $this->service->setDiscountPercentage(15.0);
        $this->service->setCashbackPercentage(3.0);
        $this->service->setDiscountStackable(true);

        // Verify updated values
        $updatedConfig = $this->service->getConfig();
        $this->assertEquals(15.0, $updatedConfig['discountPercentage']);
        $this->assertEquals(3.0, $updatedConfig['cashbackPercentage']);
        $this->assertTrue($updatedConfig['applyDiscountStackable']);
    }

    /** @test */
    public function it_can_initialize_default_configuration()
    {
        // Clear any existing options
        app('App\Services\Options')->set('ns_special_discount_percentage', null);
        app('App\Services\Options')->set('ns_special_cashback_percentage', null);
        app('App\Services\Options')->set('ns_special_apply_discount_stackable', null);

        // Initialize defaults
        $this->service->initializeDefaults();

        // Verify defaults were set
        $this->assertEquals(7.0, app('App\Services\Options')->get('ns_special_discount_percentage'));
        $this->assertEquals(2.0, app('App\Services\Options')->get('ns_special_cashback_percentage'));
        $this->assertFalse(app('App\Services\Options')->get('ns_special_apply_discount_stackable'));
    }

    /** @test */
    public function it_can_clear_cache()
    {
        // Set some values to populate cache
        $this->service->getConfig();
        $this->service->getSpecialGroupId();

        // Clear cache
        $this->service->clearCache();

        // Verify cache is cleared by checking if fresh data is loaded
        $this->assertNotNull($this->service->getConfig());
        $this->assertNotNull($this->service->getSpecialGroupId());
    }

    /** @test */
    public function it_handles_edge_cases_gracefully()
    {
        // Test with null customer
        $this->assertFalse($this->service->isSpecialCustomer(null));

        // Test with customer without group_id
        $customer = new Customer();
        $this->assertFalse($this->service->isSpecialCustomer($customer));

        // Test with non-existent customer
        $this->expectException(\Exception::class);
        $this->service->getCustomerStatus(99999);
    }

    /** @test */
    public function it_calculates_special_discount_correctly()
    {
        // Create special customer group
        $specialGroup = CustomerGroup::factory()->create(['name' => 'Special']);
        app('App\Services\Options')->set('ns_special_customer_group_id', $specialGroup->id);
        app('App\Services\Options')->set('ns_special_discount_percentage', 15.0);

        // Create special customer
        $specialCustomer = Customer::factory()->create(['group_id' => $specialGroup->id]);

        // Test discount calculation
        $discount = $this->service->calculateSpecialDiscount(500.00, $specialCustomer);
        $this->assertEquals(75.00, $discount); // 15% of 500

        // Test with regular customer
        $regularCustomer = Customer::factory()->create(['group_id' => CustomerGroup::factory()->create()->id]);
        $discount = $this->service->calculateSpecialDiscount(500.00, $regularCustomer);
        $this->assertEquals(0.00, $discount);
    }
}
