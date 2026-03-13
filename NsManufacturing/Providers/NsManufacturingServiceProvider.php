<?php

namespace Modules\NsManufacturing\Providers;

use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Event;
use App\Services\Helper;
use App\Events\ProductUnitQuantityAfterCreatedEvent;
use App\Events\ProductUnitQuantityAfterUpdatedEvent;
use App\Models\ProductUnitQuantity;
use Illuminate\Support\ServiceProvider;
use Modules\NsManufacturing\Crud\BomCrud;
use Modules\NsManufacturing\Crud\BomItemCrud;
use Modules\NsManufacturing\Crud\ProductionOrderCrud;
use Modules\NsManufacturing\Console\Commands\SyncManufacturingFlagsCommand;
use Modules\NsManufacturing\Services\ManufacturingProductFlagSyncService;
use Modules\NsManufacturing\Services\ProductFormHook;
use Modules\NsManufacturing\Services\ProductUnitFormHook;
use TorMorten\Eventy\Facades\Events as Hook;

class NsManufacturingServiceProvider extends ServiceProvider
{
    public function register()
    {
        // Register the ProductUnitFormHook as a singleton
        $this->app->singleton( ProductUnitFormHook::class );
        // Register the ProductFormHook as a singleton
        $this->app->singleton( ProductFormHook::class );
    }

    public function boot()
    {
        // Register the ProductUnitFormHook
        $this->app->make( ProductUnitFormHook::class );
        // Register the ProductFormHook
        $this->app->make( ProductFormHook::class );

        $this->loadMigrationsFrom( __DIR__ . '/../Migrations' );
        $this->loadRoutesFrom( __DIR__ . '/../Routes/api.php' );
        $this->loadRoutesFrom( __DIR__ . '/../Routes/web.php' );
        $this->loadViewsFrom( __DIR__ . '/../Resources/views', 'ns-manufacturing' );

        View::composer( 'ns-manufacturing::*', function ( $view ) {
            $view->with( 'manufacturingConfig', config( 'ns-manufacturing' ) );
        } );

        // Register CRUDs
        Hook::addFilter( 'ns-crud-resource', function ( $resource ) {
            if ( $resource === 'ns.manufacturing-boms' ) {
                return BomCrud::class;
            }
            if ( $resource === 'ns.manufacturing-bom-items' ) {
                return BomItemCrud::class;
            }
            if ( $resource === 'ns.manufacturing-orders' ) {
                return ProductionOrderCrud::class;
            }

            return $resource;
        } );

        // Dashboard Menu - Add Manufacturing after Inventory
        Hook::addFilter( 'ns-dashboard-menus', function ( $menus ) {
            // Insert Manufacturing menu right after Inventory
            $menus = array_insert_after( $menus, 'inventory', [
                'manufacturing' => [
                    'label' => __( 'Manufacturing' ),
                    'icon' => 'la-industry',
                    'childrens' => [
                        'boms' => [
                            'label' => __( 'Bill of Materials' ),
                            'href' => ns()->route( 'ns.dashboard.manufacturing-boms' ),
                        ],
                        'orders' => [
                            'label' => __( 'Production Orders' ),
                            'href' => ns()->route( 'ns.dashboard.manufacturing-orders' ),
                        ],
                        'reports' => [
                            'label' => __( 'Reports' ),
                            'href' => ns()->route( 'ns.dashboard.manufacturing-reports' ),
                        ],
                    ],
                ],
            ] );

            return $menus;
        } );

        // Register Stock Hooks
        Hook::addFilter( 'ns-products-decrease-actions', function ( $actions ) {
            $actions[] = 'manufacturing_consume';

            return $actions;
        } );

        Hook::addFilter( 'ns-products-increase-actions', function ( $actions ) {
            $actions[] = 'manufacturing_produce';

            return $actions;
        } );

        Hook::addFilter( 'ns-products-history-label', function ( $label, $action ) {
            if ( $action === 'manufacturing_consume' ) {
                return __( 'Manufacturing Consumption' );
            }
            if ( $action === 'manufacturing_produce' ) {
                return __( 'Manufacturing Output' );
            }

            return $label;
        }, 10, 2 );

        /**
         * For documented compatibility with ns-product-history-operation (singular)
         * and ns-products-history-operation (plural)
         */
        $historyOperationFilter = function ( $label, $action ) {
            if ( $action === 'manufacturing_consume' ) {
                return __( 'Manufacturing Consumption' );
            }
            if ( $action === 'manufacturing_produce' ) {
                return __( 'Manufacturing Output' );
            }

            return $label;
        };

        Hook::addFilter( 'ns-product-history-operation', $historyOperationFilter, 10, 2 );
        Hook::addFilter( 'ns-products-history-operation', $historyOperationFilter, 10, 2 );

        // Hook into product unit CRUD form, columns, and validation
        $productUnitFormHook = $this->app->make( ProductUnitFormHook::class );

        // Register product unit form hook
        Hook::addFilter( 'ns.products-units-crud-form', [$productUnitFormHook, 'addManufacturingFieldsToProductUnitForm'] );

        // Register product unit columns hook
        Hook::addFilter( 'ns.products-units-crud-columns', [$productUnitFormHook, 'addManufacturingColumnsToProductUnitTable'] );

        // Register product unit validation hooks
        Hook::addFilter( 'ns.products-units-crud-validate-post', function ( $inputs, $entry ) use ( $productUnitFormHook ) {
            return $productUnitFormHook->validateManufacturingFlags( $inputs, $entry );
        } );

        Hook::addFilter( 'ns.products-units-crud-validate-put', function ( $inputs, $entry ) use ( $productUnitFormHook ) {
            return $productUnitFormHook->validateManufacturingFlags( $inputs, $entry );
        } );

        // Hook into product CRUD form, columns, and validation
        $productFormHook = $this->app->make( ProductFormHook::class );

        // Register product form hook
        Hook::addFilter( 'ns-products-crud-form', [$productFormHook, 'addManufacturingFieldsToProductForm'] );

        // Register product columns hook
        Hook::addFilter( 'ns-products-crud-columns', [$productFormHook, 'addManufacturingColumnsToProductTable'] );

        // Register product validation hooks
        Hook::addFilter( 'ns-products-crud-validate-post', function ( $inputs, $entry ) use ( $productFormHook ) {
            return $productFormHook->validateManufacturingFlags( $inputs, $entry );
        } );

        Hook::addFilter( 'ns-products-crud-validate-put', function ( $inputs, $entry ) use ( $productFormHook ) {
            return $productFormHook->validateManufacturingFlags( $inputs, $entry );
        } );

        Hook::addFilter('ns-products-units-quantities-fields-names', function ($fields) {
            $fields[] = 'is_manufactured';
            $fields[] = 'is_raw_material';

            return array_values(array_unique($fields));
        });

        Hook::addFilter('ns-products-units-quantities-fields', function ($fields) {
            $fields[] = [
                'type' => 'switch',
                'name' => 'is_manufactured',
                'label' => __( 'Is Manufactured' ),
                'description' => __( 'Check if this unit can be manufactured (used for production)' ),
                'options' => Helper::boolToOptions(
                    true: __( 'Yes' ),
                    false: __( 'No' ),
                ),
                'value' => 0,
            ];

            $fields[] = [
                'type' => 'switch',
                'name' => 'is_raw_material',
                'label' => __( 'Is Raw Material' ),
                'description' => __( 'Check if this unit is a raw material (can be used as component)' ),
                'options' => Helper::boolToOptions(
                    true: __( 'Yes' ),
                    false: __( 'No' ),
                ),
                'value' => 0,
            ];

            return $fields;
        });

        Hook::addFilter('ns-update-products-inputs', function ($inputs) {
            unset($inputs['is_manufactured']);
            unset($inputs['is_raw_material']);
            return $inputs;
        });

        Hook::addFilter('ns-create-products-inputs', function ($inputs) {
            unset($inputs['is_manufactured']);
            unset($inputs['is_raw_material']);
            return $inputs;
        });

        $syncService = $this->app->make(ManufacturingProductFlagSyncService::class);

        Event::listen(ProductUnitQuantityAfterCreatedEvent::class, function ($event) use ($syncService) {
            $syncService->syncProductFlags($event->productUnitQuantity->product_id);
        });

        Event::listen(ProductUnitQuantityAfterUpdatedEvent::class, function ($event) use ($syncService) {
            $syncService->syncProductFlags($event->productUnitQuantity->product_id);
        });

        ProductUnitQuantity::deleted(function ($unitQuantity) use ($syncService) {
            $syncService->syncProductFlags($unitQuantity->product_id);
        });

        if ($this->app->runningInConsole()) {
            $this->commands([
                SyncManufacturingFlagsCommand::class,
            ]);
        }
    }

}
