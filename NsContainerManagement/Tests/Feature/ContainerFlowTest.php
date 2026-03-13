<?php

namespace Modules\NsContainerManagement\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use App\Models\User;
use App\Models\UserAttribute;
use App\Models\Unit;
use App\Models\UnitGroup;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Services\CrudService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\NsContainerManagement\Models\ContainerType;
use Modules\NsContainerManagement\Models\ContainerInventory;
use Modules\NsContainerManagement\Models\ContainerMovement;
use Modules\NsContainerManagement\Models\CustomerContainerBalance;
use Modules\NsContainerManagement\Crud\ReceiveContainerCrud;
use Modules\TestSupport\Testing\ModuleTestDatabaseBootstrap;

class ContainerFlowTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;
    protected $customer;
    protected $containerType;
    protected $unit;
    protected $unitGroup;

    protected function setUp(): void
    {
        putenv('AUTOLOAD_MODULES=NsContainerManagement');

        parent::setUp();

        ModuleTestDatabaseBootstrap::prepare($this, 'modules/NsContainerManagement/Migrations');

        $this->admin = User::where('username', 'admin')->first() ?? User::factory()->create(['username' => 'admin']);
        
        // Fix for DateService core bug: ensure user has an attribute
        if (!$this->admin->attribute) {
            $attribute = new UserAttribute([
                'language' => 'en'
            ]);
            $attribute->user_id = $this->admin->id;
            $attribute->save();
            $this->admin->refresh();
        }

        $this->admin->assignRole('admin');
        $this->actingAs($this->admin);
        \App\Classes\Hook::addFilter('ns-crud-resource', function ($resource) {
            return $resource === ReceiveContainerCrud::IDENTIFIER ? ReceiveContainerCrud::class : $resource;
        });
        \App\Models\Role::namespace(\App\Models\Role::ADMIN)?->addPermissions([
            'nexopos.create.container-types',
            'nexopos.read.container-types',
            'nexopos.update.container-types',
            'nexopos.adjust.container-stock',
            'nexopos.receive.containers',
            'nexopos.charge.containers',
        ]);

        // Create a unit group
        $this->unitGroup = UnitGroup::create([
            'name' => 'Default Group',
            'author' => $this->admin->id,
        ]);

        // Create a default unit
        $this->unit = Unit::create([
            'name' => 'Unit',
            'identifier' => 'unit',
            'group_id' => $this->unitGroup->id,
            'value' => 1,
            'base_unit' => true,
            'author' => $this->admin->id,
        ]);

        // Create a customer
        $this->customer = Customer::factory()->create();

        // Create a container type
        $this->containerType = ContainerType::create([
            'name' => '20L Gallon',
            'capacity' => 20,
            'capacity_unit' => 'L',
            'deposit_fee' => 5.00,
            'is_active' => true,
            'author' => $this->admin->id,
        ]);

        // Initialize inventory
        ContainerInventory::create([
            'container_type_id' => $this->containerType->id,
            'quantity_on_hand' => 100,
            'quantity_reserved' => 0,
        ]);
    }

    #[Test]
    public function it_can_list_container_types()
    {
        $response = $this->getJson('/api/container-management/types');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => [
                    '*' => ['id', 'name', 'capacity', 'capacity_unit', 'deposit_fee']
                ]
            ]);
    }

    #[Test]
    public function it_can_create_container_type()
    {
        $containerType = app(\Modules\NsContainerManagement\Services\ContainerService::class)
            ->createContainerType([
            'name' => '10L Gallon',
            'capacity' => 10,
            'capacity_unit' => 'L',
            'deposit_fee' => 3.00,
            'is_active' => true,
            'initial_stock' => 50,
        ]);

        $this->assertDatabaseHas('ns_container_types', ['name' => '10L Gallon']);

        $type = $containerType ?? ContainerType::where('name', '10L Gallon')->first();
        $this->assertDatabaseHas('ns_container_inventory', [
            'container_type_id' => $type->id,
            'quantity_on_hand' => 50
        ]);
    }

    #[Test]
    public function it_can_adjust_inventory()
    {
        ContainerMovement::create([
            'container_type_id' => $this->containerType->id,
            'customer_id' => null,
            'order_id' => null,
            'direction' => ContainerMovement::DIRECTION_ADJUSTMENT,
            'quantity' => 10,
            'unit_deposit_fee' => 0,
            'total_deposit_value' => 0,
            'source_type' => ContainerMovement::SOURCE_INVENTORY_ADJUSTMENT,
            'note' => 'Restock',
            'author' => $this->admin->id,
        ]);

        $this->assertDatabaseHas('ns_container_inventory', [
            'container_type_id' => $this->containerType->id,
            'quantity_on_hand' => 110
        ]);

        $this->assertDatabaseHas('ns_container_movements', [
            'container_type_id' => $this->containerType->id,
            'direction' => 'adjustment',
            'quantity' => 10,
            'note' => 'Restock',
        ]);
    }

    #[Test]
    public function it_can_charge_customer_for_containers()
    {
        // First, manually set a balance for the customer
        CustomerContainerBalance::create([
            'customer_id' => $this->customer->id,
            'container_type_id' => $this->containerType->id,
            'balance' => 5,
            'total_out' => 5,
            'total_in' => 0,
            'total_charged' => 0,
            'last_movement_at' => now(),
        ]);

        app(\Modules\NsContainerManagement\Services\ContainerLedgerService::class)
            ->updateCustomerBalance($this->customer->id, $this->containerType->id, charged: 2);

        ContainerMovement::create([
            'customer_id' => $this->customer->id,
            'container_type_id' => $this->containerType->id,
            'order_id' => null,
            'quantity' => 2,
            'direction' => ContainerMovement::DIRECTION_CHARGE,
            'source_type' => ContainerMovement::SOURCE_CHARGE_TRANSACTION,
            'unit_deposit_fee' => $this->containerType->deposit_fee,
            'total_deposit_value' => 2 * $this->containerType->deposit_fee,
            'author' => $this->admin->id,
            'note' => 'Unreturned containers',
        ]);
        
        $this->assertDatabaseHas('ns_customer_container_balances', [
            'customer_id' => $this->customer->id,
            'container_type_id' => $this->containerType->id,
            'balance' => 3 // 5 - 2
        ]);

        $this->assertDatabaseHas('ns_container_movements', [
            'customer_id' => $this->customer->id,
            'container_type_id' => $this->containerType->id,
            'direction' => 'charge',
            'quantity' => 2,
            'source_type' => 'charge_transaction',
        ]);
    }

    #[Test]
    public function dashboard_receive_crud_updates_balance_inventory_and_reports_data()
    {
        CustomerContainerBalance::create([
            'customer_id' => $this->customer->id,
            'container_type_id' => $this->containerType->id,
            'balance' => 3,
            'total_out' => 3,
            'total_in' => 0,
            'total_charged' => 0,
            'last_movement_at' => now(),
        ]);

        $result = app(CrudService::class)->submitRequest('ns.container-receive', [
            'customer_id' => $this->customer->id,
            'container_type_id' => $this->containerType->id,
            'quantity' => 2,
            'note' => 'Returned empties',
        ]);

        $this->assertSame('success', $result['status']);

        $this->assertDatabaseHas('ns_container_movements', [
            'customer_id' => $this->customer->id,
            'container_type_id' => $this->containerType->id,
            'direction' => ContainerMovement::DIRECTION_IN,
            'quantity' => 2,
            'source_type' => ContainerMovement::SOURCE_MANUAL_RETURN,
            'note' => 'Returned empties',
        ]);

        $this->assertDatabaseHas('ns_customer_container_balances', [
            'customer_id' => $this->customer->id,
            'container_type_id' => $this->containerType->id,
            'balance' => 1,
            'total_out' => 3,
            'total_in' => 2,
            'total_charged' => 0,
        ]);

        $this->assertDatabaseHas('ns_container_inventory', [
            'container_type_id' => $this->containerType->id,
            'quantity_on_hand' => 102,
        ]);
    }

    #[Test]
    public function reports_page_and_endpoints_load_successfully()
    {
        $this->withoutMiddleware();

        ContainerMovement::create([
            'container_type_id' => $this->containerType->id,
            'customer_id' => $this->customer->id,
            'order_id' => null,
            'direction' => ContainerMovement::DIRECTION_OUT,
            'quantity' => 1,
            'unit_deposit_fee' => $this->containerType->deposit_fee,
            'total_deposit_value' => $this->containerType->deposit_fee,
            'source_type' => ContainerMovement::SOURCE_MANUAL_GIVE,
            'note' => 'Report seed',
            'author' => $this->admin->id,
        ]);

        CustomerContainerBalance::updateOrCreate(
            [
                'customer_id' => $this->customer->id,
                'container_type_id' => $this->containerType->id,
            ],
            [
                'balance' => 1,
                'total_out' => 1,
                'total_in' => 0,
                'total_charged' => 0,
                'last_movement_at' => now(),
            ]
        );

        $this->get('/dashboard/container-management/reports')->assertStatus(200);
        $this->getJson('/dashboard/container-management/reports/summary')->assertStatus(200);
        $this->getJson('/dashboard/container-management/reports/movements')->assertStatus(200);
        $this->getJson('/dashboard/container-management/reports/balances')->assertStatus(200);
        $this->getJson('/dashboard/container-management/reports/filters')->assertStatus(200);
    }

    #[Test]
    public function charges_report_handles_missing_customer_record()
    {
        $this->withoutMiddleware();

        ContainerMovement::create([
            'container_type_id' => $this->containerType->id,
            'customer_id' => null,
            'order_id' => null,
            'direction' => ContainerMovement::DIRECTION_CHARGE,
            'quantity' => 2,
            'unit_deposit_fee' => $this->containerType->deposit_fee,
            'total_deposit_value' => 2 * $this->containerType->deposit_fee,
            'source_type' => ContainerMovement::SOURCE_CHARGE_TRANSACTION,
            'note' => 'Legacy orphaned charge',
            'author' => $this->admin->id,
        ]);

        $response = $this->getJson('/dashboard/container-management/reports/charges');
        $response->assertStatus(200);
        $response->assertJsonPath('data.0.customer', 'N/A');
    }
}
