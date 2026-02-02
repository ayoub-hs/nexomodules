<?php

namespace Modules\NsSpecialCustomer\Tests\Feature;

use Tests\TestCase;
use App\Models\Customer;
use App\Models\CustomerGroup;
use Modules\NsSpecialCustomer\Services\SpecialCustomerService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class FrontendIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private SpecialCustomerService $specialCustomerService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->specialCustomerService = app(SpecialCustomerService::class);
    }

    /** @test */
    public function api_config_endpoint_returns_valid_data()
    {
        // Create special customer group
        $specialGroup = CustomerGroup::factory()->create(['name' => 'Special']);
        $this->specialCustomerService->setSpecialGroupId($specialGroup->id);
        $this->specialCustomerService->setDiscountPercentage(7.5);
        $this->specialCustomerService->setCashbackPercentage(3.0);

        $response = $this->getJson('/api/special-customer/config');

        $response->assertStatus(200)
                ->assertJson([
                    'status' => 'success',
                    'data' => [
                        'groupId' => $specialGroup->id,
                        'discountPercentage' => 7.5,
                        'cashbackPercentage' => 3.0,
                        'applyDiscountStackable' => false
                    ]
                ]);
    }

    /** @test */
    public function api_check_endpoint_returns_special_status()
    {
        // Create special customer group and customer
        $specialGroup = CustomerGroup::factory()->create(['name' => 'Special']);
        $this->specialCustomerService->setSpecialGroupId($specialGroup->id);
        
        $specialCustomer = Customer::factory()->create(['group_id' => $specialGroup->id]);
        $regularCustomer = Customer::factory()->create(['group_id' => null]);

        // Test special customer
        $response = $this->getJson("/api/special-customer/check/{$specialCustomer->id}");
        $response->assertStatus(200)
                ->assertJson([
                    'status' => 'success',
                    'data' => [
                        'isSpecial' => true
                    ]
                ]);

        // Test regular customer
        $response = $this->getJson("/api/special-customer/check/{$regularCustomer->id}");
        $response->assertStatus(200)
                ->assertJson([
                    'status' => 'success',
                    'data' => [
                        'isSpecial' => false
                    ]
                ]);
    }

    /** @test */
    public function api_balance_endpoint_returns_customer_financial_data()
    {
        $customer = Customer::factory()->create(['account_amount' => 150.50]);

        $response = $this->getJson("/api/special-customer/balance/{$customer->id}");

        $response->assertStatus(200)
                ->assertJson([
                    'status' => 'success',
                    'data' => [
                        'current_balance' => 150.50
                    ]
                ]);

        // Check structure
        $data = $response->json('data');
        $this->assertArrayHasKey('customer', $data);
        $this->assertArrayHasKey('total_credited', $data);
        $this->assertArrayHasKey('total_debited', $data);
        $this->assertArrayHasKey('account_history', $data);
        $this->assertArrayHasKey('orders_paid_via_wallet', $data);
    }

    /** @test */
    public function api_settings_update_works_correctly()
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

    /** @test */
    public function dashboard_page_loads_with_correct_data()
    {
        // Create special customer group
        $specialGroup = CustomerGroup::factory()->create(['name' => 'Special']);
        $this->specialCustomerService->setSpecialGroupId($specialGroup->id);

        $response = $this->get('/dashboard/special-customer');

        $response->assertStatus(200)
                ->assertSee('Special Customer Dashboard')
                ->assertSee('Special Customer Configuration')
                ->assertSee('Quick Actions')
                ->assertSee('Customer Lookup');
    }

    /** @test */
    public function pos_hooks_return_correct_data()
    {
        // Create special customer group and customer
        $specialGroup = CustomerGroup::factory()->create(['name' => 'Special']);
        $this->specialCustomerService->setSpecialGroupId($specialGroup->id);
        
        $specialCustomer = Customer::factory()->create([
            'group_id' => $specialGroup->id,
            'account_amount' => 100.00
        ]);

        // Test POS options hook
        $options = apply_filters('ns-pos-options', []);
        $this->assertArrayHasKey('specialCustomer', $options);
        $this->assertEquals($specialGroup->id, $options['specialCustomer']['groupId']);

        // Test customer selected hook
        $customerData = apply_filters('ns-pos-customer-selected', [], $specialCustomer);
        $this->assertTrue($customerData['is_special']);
        $this->assertEquals('Special Customer', $customerData['special_badge']);
        $this->assertEquals(100.00, $customerData['wallet_balance']);
    }

    /** @test */
    public function product_price_hook_applies_wholesale_pricing()
    {
        // Create special customer group and customer
        $specialGroup = CustomerGroup::factory()->create(['name' => 'Special']);
        $this->specialCustomerService->setSpecialGroupId($specialGroup->id);
        
        $specialCustomer = Customer::factory()->create(['group_id' => $specialGroup->id]);
        $regularCustomer = Customer::factory()->create(['group_id' => null]);

        $product = new \stdClass();
        $product->id = 1;
        $product->sale_price = 100.00;
        $product->wholesale_price = 80.00;

        // Test special customer gets wholesale pricing
        $price = apply_filters('ns-pos-product-price', 100.00, $product, $specialCustomer);
        $this->assertIsArray($price);
        $this->assertTrue($price['wholesale_applied']);
        $this->assertEquals(80.00, $price['price']);
        $this->assertEquals(100.00, $price['original_price']);
        $this->assertEquals(20.00, $price['savings']);

        // Test regular customer gets normal pricing
        $price = apply_filters('ns-pos-product-price', 100.00, $product, $regularCustomer);
        $this->assertEquals(100.00, $price);
    }

    /** @test */
    public function order_attributes_hook_adds_special_data()
    {
        // Create special customer group and customer
        $specialGroup = CustomerGroup::factory()->create(['name' => 'Special']);
        $this->specialCustomerService->setSpecialGroupId($specialGroup->id);
        
        $specialCustomer = Customer::factory()->create(['group_id' => $specialGroup->id]);

        $order = new \stdClass();
        $order->customer = $specialCustomer;
        $order->created_at = now();

        $order = apply_filters('ns-order-attributes', $order);
        
        $this->assertObjectHasProperty('special_customer_data', $order);
        $this->assertTrue($order->special_customer_data['is_special']);
        $this->assertEquals($specialGroup->id, $order->special_customer_data['group_id']);
    }

    /** @test */
    public function customer_account_history_hook_labels_special_transactions()
    {
        $transaction = new \stdClass();
        $transaction->reference = 'ns_special_topup';

        $label = apply_filters('ns-customer-account-history-label', 'Test', $transaction);
        $this->assertEquals('Special Customer Top-up', $label);

        $transaction->reference = 'ns_special_cashback';
        $label = apply_filters('ns-customer-account-history-label', 'Test', $transaction);
        $this->assertEquals('Special Customer Cashback', $label);
    }
}
