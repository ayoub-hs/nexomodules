<?php

namespace Modules\NsManufacturing\Services;

use App\Services\Helper;
use TorMorten\Eventy\Facades\Events as Hook;

class ProductFormHook
{
    public function __construct()
    {
        // Register hooks for product form modifications
        $this->registerHooks();
    }

    /**
     * Register all hooks for product form modifications
     */
    private function registerHooks(): void
    {
        // Hook into product CRUD form (passes form and entry)
        Hook::addFilter( 'ns-products-crud-form', [$this, 'addManufacturingFieldsToProductForm'], 10, 2 );

        // Hook into product CRUD columns
        Hook::addFilter( 'ns-products-crud-columns', [$this, 'addManufacturingColumnsToProductTable'] );

        // Hook into product CRUD validation (passes validator and request)
        Hook::addFilter( 'ns-products-crud-validate-post', [$this, 'validateManufacturingFlags'], 10, 2 );
        Hook::addFilter( 'ns-products-crud-validate-put', [$this, 'validateManufacturingFlags'], 10, 2 );

        // Hook into product CRUD input filtering to normalize boolean values
        // This ensures unchecked switches are properly set to 0
        Hook::addFilter( 'App\Crud\ProductCrud@filterPostInputs', [$this, 'filterManufacturingInputs'], 10, 2 );
        Hook::addFilter( 'App\Crud\ProductCrud@filterPutInputs', [$this, 'filterManufacturingInputs'], 10, 2 );
    }

    /**
     * Add manufacturing fields to the product form
     *
     * @param mixed $entry
     */
    public function addManufacturingFieldsToProductForm( array $form, $entry = null ): array
    {
        // Add manufacturing tab if it doesn't exist
        $hasManufacturingTab = false;
        foreach ( $form['variations'][0]['tabs'] as $tab ) {
            if ( isset( $tab['label'] ) && $tab['label'] === __( 'Manufacturing' ) ) {
                $hasManufacturingTab = true;
                break;
            }
        }

        if ( ! $hasManufacturingTab ) {
            // Insert manufacturing tab before the last tab (usually images)
            // Use array_slice to preserve associative keys instead of array_pop
            $tabs = $form['variations'][0]['tabs'];
            $tabKeys = array_keys( $tabs );
            $lastKey = end( $tabKeys );
            $lastTab = $tabs[$lastKey];

            // Remove the last tab
            unset( $tabs[$lastKey] );

            // Add manufacturing tab
            $tabs['manufacturing'] = [
                'label' => __( 'Manufacturing' ),
                'fields' => [
                    [
                        'type' => 'switch',
                        'name' => 'is_manufactured',
                        'label' => __( 'Is Manufactured' ),
                        'description' => __( 'Check if this product can be manufactured (used for production)' ),
                        'value' => (int) ($entry->is_manufactured ?? 0),
                        'options' => [
                            ['label' => __('Yes'), 'value' => 1],
                            ['label' => __('No'), 'value' => 0],
                        ],
                    ],
                    [
                        'type' => 'switch',
                        'name' => 'is_raw_material',
                        'label' => __( 'Is Raw Material' ),
                        'description' => __( 'Check if this product is a raw material (can be used as component)' ),
                        'value' => (int) ($entry->is_raw_material ?? 0),
                        'options' => [
                            ['label' => __('No'), 'value' => 0],
                            ['label' => __('Yes'), 'value' => 1],
                        ],
                    ],
                    [
                        'type' => 'help',
                        'content' => __( 'Note: At least one manufacturing flag must be selected. Manufactured products can be used for production, while both manufactured products and raw materials can be used as components.' ),
                    ],
                ],
            ];

            // Add back the last tab
            $tabs[$lastKey] = $lastTab;
            $form['variations'][0]['tabs'] = $tabs;
        }

        return $form;
    }

    /**
     * Add manufacturing columns to the product table
     */
    public function addManufacturingColumnsToProductTable( array $columns ): array
    {
        // Add manufacturing columns after the type column
        $newColumns = [];
        foreach ( $columns as $key => $column ) {
            $newColumns[$key] = $column;

            // Insert manufacturing columns after type
            if ( $key === 'type' ) {
                $newColumns['is_manufactured'] = [
                    'label' => __( 'Manufactured' ),
                    '$direction' => '',
                    '$sort' => false,
                    'width' => '120px',
                ];
                $newColumns['is_raw_material'] = [
                    'label' => __( 'Raw Material' ),
                    '$direction' => '',
                    '$sort' => false,
                    'width' => '120px',
                ];
            }
        }

        return $newColumns;
    }

    /**
     * Validate manufacturing flags before saving
     *
     * @param  \Illuminate\Validation\Validator $validation
     * @param  mixed                            $request
     * @return \Illuminate\Validation\Validator
     */
    public function validateManufacturingFlags( $validation, $request )
    {
        // Get the input data from the request
        $inputs = $request->all();

        // Check if at least one manufacturing flag is selected
        if ( empty( $inputs['is_manufactured'] ) && empty( $inputs['is_raw_material'] ) ) {
            $validation->errors()->add( 'is_manufactured', __( 'At least one manufacturing flag must be selected (Is Manufactured or Is Raw Material).' ) );
        }

        // Add validation rules for manufacturing fields
        $validation->addRules( [
            'is_manufactured' => 'sometimes|boolean',
            'is_raw_material' => 'sometimes|boolean',
        ] );

        return $validation;
    }

    /**
     * Filter and normalize manufacturing inputs
     * This ensures unchecked switches are properly set to 0 in the database
     *
     * @param  array       $inputs
     * @param  mixed|null  $entry
     * @return array
     */
    public function filterManufacturingInputs( $inputs, $entry = null )
    {
        // Explicitly set boolean fields to ensure unchecked values are saved as 0
        // When a switch is unchecked, the browser doesn't send the field in the request
        // so we need to normalize these values before they reach the database
        $inputs['is_manufactured'] = !empty($inputs['is_manufactured']) ? 1 : 0;
        $inputs['is_raw_material'] = !empty($inputs['is_raw_material']) ? 1 : 0;

        return $inputs;
    }
}
