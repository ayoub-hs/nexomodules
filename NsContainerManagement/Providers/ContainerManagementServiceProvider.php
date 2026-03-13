<?php

namespace Modules\NsContainerManagement\Providers;

use Illuminate\Support\ServiceProvider;
use TorMorten\Eventy\Facades\Events as Hook;
use Modules\NsContainerManagement\Services\ContainerService;
use Modules\NsContainerManagement\Services\ContainerLedgerService;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductUnitQuantity;
use App\Events\OrderAfterCreatedEvent;
use Illuminate\Support\Facades\Event;
use App\Events\RenderFooterEvent;

class ContainerManagementServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(ContainerService::class, function ($app) {
            return new ContainerService();
        });

        $this->app->singleton(ContainerLedgerService::class, function ($app) {
            return new ContainerLedgerService($app->make(\App\Services\OrdersService::class));
        });
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        $this->loadMigrationsFrom(__DIR__ . '/../Migrations');
        $this->loadRoutesFrom(__DIR__ . '/../Routes/api.php');
        $this->loadRoutesFrom(__DIR__ . '/../Routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/../Resources/Views', 'nscontainermanagement');

        // Listen for order creation to record container movements
        Event::listen(OrderAfterCreatedEvent::class, \Modules\NsContainerManagement\Listeners\OrderAfterCreatedListener::class);

        /**
         * Handle Product Saving to Link Containers
         * Uses Laravel Events instead of hooks since ns-after-save-product doesn't exist
         */
        $handleProductSave = function($product) {
            $containerService = app(ContainerService::class);
            $request = request();

            // Helper to resolve unit_id to unit_quantity_id
            $resolveUnitQuantityId = function(int $productId, int $unitId): ?int {
                $unitQuantity = \App\Models\ProductUnitQuantity::where('product_id', $productId)
                    ->where('unit_id', $unitId)
                    ->first();
                return $unitQuantity ? $unitQuantity->id : null;
            };

            if ($request->has('variations')) {
                $variations = $request->input('variations');

                foreach ($variations as $variationIndex => $variationData) {
                    if (!is_array($variationData)) {
                        continue;
                    }

                    // The selling_group is an array under units
                    $sellingGroupArray = $variationData['units']['selling_group'] ?? [];

                    if (!is_array($sellingGroupArray)) {
                        continue;
                    }

                    // Each selling_group entry is an associative array with field names as keys
                    foreach ($sellingGroupArray as $sgIndex => $fields) {
                        if (!is_array($fields)) {
                            continue;
                        }

                        // Structure is: ['unit_id' => 1, 'container_type_id' => '', ...]
                        $unitId = $fields['unit_id'] ?? null;
                        $typeId = $fields['container_type_id'] ?? null;
                        
                        // Resolve unit_id to unit_quantity_id
                        $unitQuantityId = null;
                        if ($unitId) {
                            $unitQuantityId = $resolveUnitQuantityId($product->id, $unitId);
                        }

                        if ($unitQuantityId || $unitId) {
                            if (empty($typeId)) {
                                $containerService->unlinkProductFromContainer($product->id, $unitQuantityId);
                            } else {
                                $containerService->linkProductToContainer($product->id, (int) $typeId, $unitQuantityId, $unitId);
                            }
                        }
                    }
                }
            } else {
                // Handle simple product - selling_group directly under units
                $units = $request->input('units');

                if (is_array($units) && isset($units['selling_group'])) {
                    $sellingGroupArray = $units['selling_group'];

                    if (is_array($sellingGroupArray)) {
                        foreach ($sellingGroupArray as $sgIndex => $fields) {
                            if (!is_array($fields)) {
                                continue;
                            }

                            // Structure is: ['unit_id' => 1, 'container_type_id' => '', ...]
                            $unitId = $fields['unit_id'] ?? null;
                            $typeId = $fields['container_type_id'] ?? null;
                            
                            // Resolve unit_id to unit_quantity_id
                            $unitQuantityId = null;
                            if ($unitId) {
                                $unitQuantityId = $resolveUnitQuantityId($product->id, $unitId);
                            }

                            if ($unitQuantityId || $unitId) {
                                if (empty($typeId)) {
                                    $containerService->unlinkProductFromContainer($product->id, $unitQuantityId);
                                } else {
                                    $containerService->linkProductToContainer($product->id, (int) $typeId, $unitQuantityId, $unitId);
                                }
                            }
                        }
                    }
                }
            }
        };
        
        // Listen to product events
        Event::listen(\App\Events\ProductAfterCreatedEvent::class, function($event) use ($handleProductSave) {
            $handleProductSave($event->product);
        });
        
        Event::listen(\App\Events\ProductAfterUpdatedEvent::class, function($event) use ($handleProductSave) {
            $handleProductSave($event->product);
        });

        // Register CRUD resources
        Hook::addFilter('ns-crud-resource', function ($resource) {
            if ($resource === 'ns.container-types') {
                return \Modules\NsContainerManagement\Crud\ContainerTypeCrud::class;
            }
            if ($resource === 'ns.container-inventory') {
                return \Modules\NsContainerManagement\Crud\ContainerInventoryCrud::class;
            }
            if ($resource === 'ns.container-receive') {
                return \Modules\NsContainerManagement\Crud\ReceiveContainerCrud::class;
            }
            if ($resource === 'ns.container-customers') {
                return \Modules\NsContainerManagement\Crud\CustomerBalanceCrud::class;
            }
            if ($resource === 'ns.container-adjustment') {
                return \Modules\NsContainerManagement\Crud\ContainerAdjustmentCrud::class;
            }
            return $resource;
        });

        /**
         * Add Container field to each Unit in Product CRUD
         */
        Hook::addFilter('ns-products-units-quantities-fields', function($fields) {
            $containerService = app(ContainerService::class);
            $types = $containerService->getContainerTypesDropdown();

            $fields[] = [
                'type' => 'select',
                'name' => 'container_type_id',
                'label' => __('Container'),
                'options' => array_merge([['value' => '', 'label' => __('None')]], $types),
                'description' => __('Link a container to this specific unit.'),
                'validation' => '',
                'value' => '',
            ];

            return $fields;
        });

        /**
         * We need a way to populate the values for existing units.
         */
        Hook::addFilter('ns-products-crud-form', function($form, $entry) {
            if (!$entry || !$entry->id) {
                return $form;
            }

            $containerService = app(ContainerService::class);

            // Helper to resolve unit_id to unit_quantity_id
            $resolveUnitQuantityId = function(int $productId, int $unitId): ?int {
                $unitQuantity = \App\Models\ProductUnitQuantity::where('product_id', $productId)
                    ->where('unit_id', $unitId)
                    ->first();
                return $unitQuantity ? $unitQuantity->id : null;
            };

            /**
             * Helper function to process selling_group fields and populate container_type_id.
             * Uses array index access to avoid reference issues with Collections.
             */
            $processSellingGroup = function(&$fields, $productId) use ($containerService, $resolveUnitQuantityId) {
                $updatedCount = 0;
                foreach ($fields as $fieldIndex => &$field) {
                    if (($field['name'] ?? '') === 'selling_group' && isset($field['groups'])) {
                        // Convert groups to array if it's a Collection
                        $groups = $field['groups'];
                        $isCollection = $groups instanceof \Illuminate\Support\Collection;
                        if ($isCollection) {
                            $groups = $groups->toArray();
                        }
                        
                        foreach ($groups as $groupIndex => &$group) {
                            $unitId = null;
                            $unitQuantityId = null;
                            $fieldNames = [];
                            
                            // Convert group fields to array if needed
                            $groupFields = $group['fields'] ?? [];
                            if ($groupFields instanceof \Illuminate\Support\Collection) {
                                $groupFields = $groupFields->toArray();
                            }
                            
                            if (is_array($groupFields)) {
                                foreach ($groupFields as $idx => $f) {
                                    if (($f['name'] ?? '') === 'unit_id') {
                                        $unitId = $f['value'] ?? null;
                                        // Resolve to unit_quantity_id
                                        if ($unitId) {
                                            $unitQuantityId = $resolveUnitQuantityId($productId, $unitId);
                                        }
                                    }
                                }
                            }

                            if ($unitQuantityId || $unitId) {
                                // Try to get link by unit_quantity_id first, then fallback to unit_id
                                $link = null;
                                if ($unitQuantityId) {
                                    $link = $containerService->getProductContainer($productId, $unitQuantityId);
                                }
                                if (!$link && $unitId) {
                                    // Fallback: try to get by unit_id for backward compatibility
                                    $link = \Modules\NsContainerManagement\Models\ProductContainer::where('product_id', $productId)
                                        ->where('unit_id', $unitId)
                                        ->first();
                                }
                                $typeId = $link ? (string) $link->container_type_id : '';

                                // Find and update the container_type_id field
                                for ($i = 0; $i < count($groupFields); $i++) {
                                    if (($groupFields[$i]['name'] ?? '') === 'container_type_id') {
                                        $groupFields[$i]['value'] = $typeId;
                                        $updatedCount++;
                                        break;
                                    }
                                }
                                
                                // Assign updated fields back to group
                                $group['fields'] = $groupFields;
                            }
                        }
                        
                        // Assign updated groups back to field
                        $field['groups'] = $groups;
                    }
                }
                return $updatedCount;
            };

            // Handle Simple Product units tab
            if (isset($form['tabs']['units']['fields'])) {
                $processSellingGroup($form['tabs']['units']['fields'], $entry->id);
            }

            // Handle variations for variable products
            // Convert to array if it's a Collection to allow modifications
            if (isset($form['variations'])) {
                $variations = $form['variations'];
                if ($variations instanceof \Illuminate\Support\Collection) {
                    $variations = $variations->toArray();
                }

                if (is_array($variations)) {
                    foreach ($variations as $variationIndex => $variation) {
                        if (isset($variation['tabs']['units']['fields'])) {
                            $processSellingGroup($variations[$variationIndex]['tabs']['units']['fields'], $entry->id);
                        }
                    }
                    $form['variations'] = $variations;
                }
            }

            return $form;
        }, 20, 2);

        /**
         * Sanitize product inputs to prevent SQL errors
         * for columns that don't exist in nexopos_products
         */
        Hook::addFilter('ns-update-products-inputs', function($inputs) {
            unset($inputs['container_type_id']);
            return $inputs;
        });

        // Register dashboard menu
        Hook::addFilter('ns-dashboard-menus', function ($menus) {
            $containerMenu = [
                'label' => __('Container Management'),
                'icon' => 'la-boxes',
                'childrens' => [
                    'container-types' => [
                        'label' => __('Container Types'),
                        'href' => ns()->route('ns.dashboard.container-types'),
                    ],
                    'container-inventory' => [
                        'label' => __('Inventory'),
                        'href' => ns()->route('ns.dashboard.container-inventory'),
                    ],
                    'adjust-stock' => [
                        'label' => __('Adjust Stock'),
                        'href' => ns()->route('ns.dashboard.container-adjust'),
                    ],
                    'receive-containers' => [
                        'label' => __('Receive Containers'),
                        'href' => ns()->route('ns.dashboard.container-receive'),
                    ],
                    'customer-balances' => [
                        'label' => __('Customer Balances'),
                        'href' => ns()->route('ns.dashboard.container-customers'),
                    ],
                    'container-reports' => [
                        'label' => __('Reports'),
                        'href' => ns()->route('ns.dashboard.container-reports'),
                    ],
                ],
            ];

            if (isset($menus['orders'])) {
                $newMenus = [];
                foreach ($menus as $key => $value) {
                    $newMenus[$key] = $value;
                    if ($key === 'orders') {
                        $newMenus['container-management'] = $containerMenu;
                    }
                }
                return $newMenus;
            }

            $menus['container-management'] = $containerMenu;
            return $menus;
        });

        // Inject POS options and footer
        Hook::addFilter('ns-pos-options', function($options) {
            $containerService = app(ContainerService::class);
            $options['container_management'] = [
                'enabled' => true,
                'types' => $containerService->getContainerTypesDropdown(),
                'links' => $containerService->getAllProductContainerLinks(),
            ];
            return $options;
        });

        // Use RenderFooterEvent to inject the pos-footer view
        Event::listen(RenderFooterEvent::class, function (RenderFooterEvent $event) {
            // Only inject on the POS route (ns.dashboard.pos)
            if ($event->routeName === 'ns.dashboard.pos') {
                $event->output->addView('nscontainermanagement::pos-footer');
            }
        });
    }

}

