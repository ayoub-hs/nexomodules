<?php

namespace Modules\NsManufacturing\Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use App\Models\User;
use App\Models\UserAttribute;
use App\Models\Product;
use App\Models\Unit;
use App\Models\UnitGroup;
use App\Models\ProductUnitQuantity;
use Modules\NsManufacturing\Models\ManufacturingBom;
use Modules\NsManufacturing\Models\ManufacturingBomItem;
use Modules\NsManufacturing\Models\ManufacturingOrder;
use Modules\NsManufacturing\Models\ManufacturingStockMovement;
use Modules\TestSupport\Testing\ModuleTestDatabaseBootstrap;

class ProductionFlowTest extends TestCase
{
    use DatabaseTransactions; // Use transactions to rollback changes

    protected $user;
    protected $unit;
    protected $materialA;
    protected $materialB;
    protected $finishedProduct;
    protected $bom;

    public function setUp(): void
    {
        putenv('AUTOLOAD_MODULES=NsManufacturing');

        parent::setUp();

        ModuleTestDatabaseBootstrap::prepare($this, 'modules/NsManufacturing/Migrations');
        
        // Log in as admin
        $this->user = User::where('username', 'admin')->first() ?? User::factory()->create(['username' => 'admin']);
        
        // Fix for DateService core bug: ensure user has an attribute
        if (!$this->user->attribute) {
            $attribute = new UserAttribute([
                'language' => 'en'
            ]);
            $attribute->user_id = $this->user->id;
            $attribute->save();
            $this->user->refresh();
        }
        
        $this->user->assignRole('admin');
        $this->actingAs($this->user);
        \App\Models\Role::namespace(\App\Models\Role::ADMIN)?->addPermissions([
            'nexopos.create.manufacturing-recipes',
            'nexopos.read.manufacturing-recipes',
            'nexopos.update.manufacturing-recipes',
            'nexopos.delete.manufacturing-recipes',
            'nexopos.create.manufacturing-orders',
            'nexopos.read.manufacturing-orders',
            'nexopos.update.manufacturing-orders',
            'nexopos.delete.manufacturing-orders',
            'nexopos.start.manufacturing-orders',
            'nexopos.complete.manufacturing-orders',
            'nexopos.cancel.manufacturing-orders',
            'nexopos.view.manufacturing-costs',
            'nexopos.export.manufacturing-reports',
        ]);
        \App\Classes\Hook::addFilter('ns-products-decrease-actions', function ($actions) {
            $actions[] = 'manufacturing_consume';
            return $actions;
        });
        \App\Classes\Hook::addFilter('ns-products-increase-actions', function ($actions) {
            $actions[] = 'manufacturing_produce';
            return $actions;
        });

        // Setup Units
        $unitGroup = UnitGroup::first() ?? UnitGroup::forceCreate(['name' => 'General', 'author' => $this->user->id]);
        $this->unit = Unit::first() ?? Unit::forceCreate([
            'name' => 'Piece',
            'identifier' => 'piece',
            'value' => 1,
            'base_unit' => true,
            'group_id' => $unitGroup->id,
            'author' => $this->user->id
        ]);

        // Setup Products
        $this->materialA = $this->createProduct('Material A', 10); // Cost 10
        $this->materialB = $this->createProduct('Material B', 20); // Cost 20
        $this->finishedProduct = $this->createProduct('Cake', 0); // Cost derived

        // Setup Stock for Materials
        // Using low-level insert or service if available, simply using DB insert for speed/reliability in test setup
        $this->setStock($this->materialA->id, 100);
        $this->setStock($this->materialB->id, 100);

        // Setup BOM for 1 Cake = 2 MatA + 1 MatB
        $this->bom = ManufacturingBom::create([
            'uuid' => \Illuminate\Support\Str::uuid(),
            'name' => 'Cake Recipe',
            'product_id' => $this->finishedProduct->id,
            'unit_id' => $this->unit->id,
            'quantity' => 1,
            'author' => $this->user->id
        ]);

        ManufacturingBomItem::create([
            'bom_id' => $this->bom->id,
            'product_id' => $this->materialA->id,
            'unit_id' => $this->unit->id,
            'quantity' => 2,
        ]);

        ManufacturingBomItem::create([
            'bom_id' => $this->bom->id,
            'product_id' => $this->materialB->id,
            'unit_id' => $this->unit->id,
            'quantity' => 1,
        ]);
    }

    private function createProduct($name, $cost)
    {
        // NexoPOS Base Product does not have price columns.
        $product = Product::forceCreate([
            'name' => $name,
            'sku' => \Illuminate\Support\Str::random(8),
            'barcode' => \Illuminate\Support\Str::random(8),
            'barcode_type' => 'code_128',
            'author' => $this->user->id,
            'stock_management' => 'enabled',
            'status' => 'available',
            'type' => 'product', // or material
            'unit_group' => $this->unit->group_id
        ]);

        // Price is attached to Unit Quantity
        ProductUnitQuantity::forceCreate([
            'product_id' => $product->id,
            'unit_id' => $this->unit->id,
            'cogs' => $cost, // Cost of Goods Sold
            'sale_price' => $cost * 1.5,
            'quantity' => 0,
            'barcode' => $product->sku
        ]);

        return $product;
    }

    private function setStock($productId, $qty)
    {
        $entry = \App\Models\ProductUnitQuantity::where('product_id', $productId)
            ->where('unit_id', $this->unit->id)
            ->first();
            
        if ($entry) {
            $entry->quantity = $qty;
            $entry->save();
        } else {
            \App\Models\ProductUnitQuantity::forceCreate([
                'product_id' => $productId,
                'unit_id' => $this->unit->id,
                'quantity' => $qty,
                // Add defaults to satisfy DB constraints if needed, though they should be nullable or default
            ]);
        }
    }

    public function test_production_order_lifecycle()
    {
        // 1. Create Order
        $order = ManufacturingOrder::create([
            'code' => 'TEST-PO-001',
            'bom_id' => $this->bom->id,
            'product_id' => $this->finishedProduct->id,
            'unit_id' => $this->unit->id,
            'quantity' => 10,
            'status' => 'planned',
            'author' => $this->user->id
        ]);

        $this->assertEquals('planned', $order->status);

        // 2. Start Order (Deduct Stock)
        $productionService = app(\Modules\NsManufacturing\Services\ProductionService::class);
        $productionService->startOrder($order);

        $order->refresh();
        $this->assertEquals('in_progress', $order->status);

        // Verify Stock Deduction
        $this->assertDatabaseHas('nexopos_products_unit_quantities', [
            'product_id' => $this->materialA->id,
            'quantity' => 100 - 20 // 80
        ]);

        // 3. Complete Order (Add Stock)
        $productionService->completeOrder($order);

        $order->refresh();
        $this->assertEquals('completed', $order->status);

        // Verify Finished Goods Stock
        $this->assertDatabaseHas('nexopos_products_unit_quantities', [
            'product_id' => $this->finishedProduct->id,
            'quantity' => 10 // 0 + 10
        ]);
        
        // Verify Audit Trail
        $this->assertDatabaseHas('ns_manufacturing_stock_movements', [
            'order_id' => $order->id,
            'type' => 'production',
            'quantity' => 10
        ]);
    }
}
