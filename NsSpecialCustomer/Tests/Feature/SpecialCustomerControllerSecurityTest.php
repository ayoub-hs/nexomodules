<?php

namespace Modules\NsSpecialCustomer\Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Permission;
use App\Models\Role;
use App\Services\Options;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

class SpecialCustomerControllerSecurityTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $admin;
    protected User $regularUser;
    protected CustomerGroup $specialGroup;
    protected Customer $specialCustomer;
    protected Customer $regularCustomer;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create admin user with all permissions
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
        
        // Create regular user without special customer permissions
        $this->regularUser = User::factory()->create();
        
        // Create special customer group
        $this->specialGroup = CustomerGroup::factory()->create([
            'name' => 'Special'
        ]);
        
        // Set the special group ID in options
        app(Options::class)->set('ns_special_customer_group_id', $this->specialGroup->id);
        
        // Create customers
        $this->specialCustomer = Customer::factory()->create([
            'group_id' => $this->specialGroup->id,
            'account_amount' => 100.00,
        ]);
        
        $this->regularCustomer = Customer::factory()->create([
            'account_amount' => 50.00,
        ]);
        
        // Create permissions
        $this->createPermissions();
    }

    protected function createPermissions(): void
    {
        $permissions = [
            'special.customer.manage',
            'special.customer.view',
            'special.customer.cashback',
            'special.customer.topup',
            'special.customer.settings',
        ];

        foreach ($permissions as $namespace) {
            Permission::firstOrCreate(
                ['namespace' => $namespace],
                [
                    'name' => ucwords(str_replace('.', ' ', str_replace('special.customer.', '', $namespace))),
                    'description' => 'Permission for ' . str_replace('.', ' ', $namespace),
                ]
            );
        }
    }

    /** @test */
    public function admin_can_access_config_endpoint()
    {
        $this->actingAs($this->admin)
            ->getJson('/api/special-customer/config')
            ->assertStatus(200)
            ->assertJsonStructure(['status', 'data']);
    }

    /** @test */
    public function regular_user_cannot_access_config_endpoint()
    {
        $this->actingAs($this->regularUser)
            ->getJson('/api/special-customer/config')
            ->assertStatus(403);
    }

    /** @test */
    public function unauthenticated_user_cannot_access_config_endpoint()
    {
        $this->getJson('/api/special-customer/config')
            ->assertStatus(401);
    }

    /** @test */
    public function admin_can_view_customer_balance()
    {
        $this->actingAs($this->admin)
            ->getJson('/api/special-customer/balance/' . $this->specialCustomer->id)
            ->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => [
                    'customer',
                    'current_balance',
                    'total_credited',
                    'total_debited',
                    'account_history'
                ]
            ]);
    }

    /** @test */
    public function regular_user_cannot_view_customer_balance()
    {
        $this->actingAs($this->regularUser)
            ->getJson('/api/special-customer/balance/' . $this->specialCustomer->id)
            ->assertStatus(403);
    }

    /** @test */
    public function unauthenticated_user_cannot_view_customer_balance()
    {
        $this->getJson('/api/special-customer/balance/' . $this->specialCustomer->id)
            ->assertStatus(401);
    }

    /** @test */
    public function admin_can_view_customer_list()
    {
        $this->actingAs($this->admin)
            ->getJson('/api/special-customer/customers')
            ->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => [
                    'data',
                    'total',
                    'per_page',
                    'current_page'
                ]
            ]);
    }

    /** @test */
    public function regular_user_cannot_view_customer_list()
    {
        $this->actingAs($this->regularUser)
            ->getJson('/api/special-customer/customers')
            ->assertStatus(403);
    }

    /** @test */
    public function admin_can_check_customer_special_status()
    {
        $this->actingAs($this->admin)
            ->getJson('/api/special-customer/check/' . $this->specialCustomer->id)
            ->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => [
                    'isSpecial',
                    'config'
                ]
            ]);
    }

    /** @test */
    public function admin_can_process_topup()
    {
        $this->actingAs($this->admin)
            ->postJson('/api/special-customer/topup', [
                'customer_id' => $this->specialCustomer->id,
                'amount' => 50.00,
                'description' => 'Test top-up'
            ])
            ->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'success',
                    'transaction_id',
                    'new_balance'
                ]
            ]);
    }

    /** @test */
    public function regular_user_cannot_process_topup()
    {
        $this->actingAs($this->regularUser)
            ->postJson('/api/special-customer/topup', [
                'customer_id' => $this->specialCustomer->id,
                'amount' => 50.00,
                'description' => 'Test top-up'
            ])
            ->assertStatus(403);
    }

    /** @test */
    public function topup_validates_amount()
    {
        $this->actingAs($this->admin)
            ->postJson('/api/special-customer/topup', [
                'customer_id' => $this->specialCustomer->id,
                'amount' => 0,
                'description' => 'Invalid top-up'
            ])
            ->assertStatus(422);
    }

    /** @test */
    public function topup_validates_customer_exists()
    {
        $this->actingAs($this->admin)
            ->postJson('/api/special-customer/topup', [
                'customer_id' => 99999,
                'amount' => 50.00,
                'description' => 'Invalid customer'
            ])
            ->assertStatus(422);
    }

    /** @test */
    public function rate_limiting_applies_to_topup()
    {
        $this->actingAs($this->admin);

        // Make 10 successful requests (within limit)
        for ($i = 0; $i < 10; $i++) {
            $response = $this->postJson('/api/special-customer/topup', [
                'customer_id' => $this->specialCustomer->id,
                'amount' => 1.00,
                'description' => 'Rate limit test ' . $i
            ]);
            $this->assertNotEquals(429, $response->getStatusCode());
        }

        // 11th request should be rate limited
        $response = $this->postJson('/api/special-customer/topup', [
            'customer_id' => $this->specialCustomer->id,
            'amount' => 1.00,
            'description' => 'Rate limit test'
        ]);
        
        $this->assertEquals(429, $response->getStatusCode());
    }

    /** @test */
    public function rate_limiting_applies_to_cashback_process()
    {
        $this->actingAs($this->admin);

        // Make 5 successful cashback requests (within limit)
        for ($i = 0; $i < 5; $i++) {
            $response = $this->postJson('/api/special-customer/cashback', [
                'customer_id' => $this->specialCustomer->id,
                'year' => 2024,
                'total_purchases' => 1000.00,
                'cashback_percentage' => 2.0
            ]);
            // Either success or "already processed" is acceptable
            $this->assertNotEquals(429, $response->getStatusCode());
        }

        // 6th request should be rate limited
        $response = $this->postJson('/api/special-customer/cashback', [
            'customer_id' => $this->specialCustomer->id,
            'year' => 2024,
            'total_purchases' => 1000.00,
            'cashback_percentage' => 2.0
        ]);
        
        $this->assertEquals(429, $response->getStatusCode());
    }

    /** @test */
    public function admin_can_access_crud_special_customers()
    {
        $this->actingAs($this->admin)
            ->getJson('/api/crud/ns.special-customers')
            ->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'total',
                'per_page',
                'current_page'
            ]);
    }

    /** @test */
    public function admin_can_access_crud_cashback()
    {
        $this->actingAs($this->admin)
            ->getJson('/api/crud/ns.special-customer-cashback')
            ->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'total',
                'per_page',
                'current_page'
            ]);
    }

    /** @test */
    public function admin_can_access_crud_topup()
    {
        $this->actingAs($this->admin)
            ->getJson('/api/crud/ns.special-customer-topup')
            ->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'total',
                'per_page',
                'current_page'
            ]);
    }
}

