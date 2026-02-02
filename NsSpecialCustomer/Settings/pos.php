<?php

use App\Classes\FormInput;
use App\Models\CustomerGroup;
use App\Services\Helper;

$booleanValue = static function ($value): bool {
    if (is_bool($value)) {
        return $value;
    }

    if (is_numeric($value)) {
        return (bool) (int) $value;
    }

    return in_array(strtolower((string) $value), ['yes', 'true', '1'], true);
};

$stackableValue = $booleanValue(ns()->option->get('ns_special_apply_discount_stackable', false));
$autoCashbackValue = $booleanValue(ns()->option->get('ns_special_enable_auto_cashback', false));

return [
    'label' => __( 'Special Customer' ),
    'show' => function () {
        return ns()->allowedTo('special.customer.settings');
    },
    'fields' => [
        FormInput::select(
            label: __( 'Special Customer Group' ),
            name: 'ns_special_customer_group_id',
            value: ns()->option->get( 'ns_special_customer_group_id' ),
            description: __( 'Customer group that receives special pricing and cashback.' ),
            options: Helper::toJsOptions( CustomerGroup::get(), [ 'id', 'name' ] ),
            validation: 'required|integer'
        ),
        FormInput::number(
            label: __( 'Discount Percentage' ),
            name: 'ns_special_discount_percentage',
            value: ns()->option->get( 'ns_special_discount_percentage', 7.0 ),
            description: __( 'Default discount percentage for special customers.' ),
            validation: 'required|numeric|min:0|max:100'
        ),
        FormInput::number(
            label: __( 'Cashback Percentage' ),
            name: 'ns_special_cashback_percentage',
            value: ns()->option->get( 'ns_special_cashback_percentage', 2.0 ),
            description: __( 'Yearly cashback percentage for special customers.' ),
            validation: 'required|numeric|min:0|max:50'
        ),
        FormInput::switch(
            label: __( 'Allow Discount Stacking' ),
            name: 'ns_special_apply_discount_stackable',
            options: Helper::kvToJsOptions( [
                'yes' => __( 'Yes' ),
                'no' => __( 'No' ),
            ] ),
            value: $stackableValue ? 'yes' : 'no',
            description: __( 'Allow special discount to stack with other discounts.' )
        ),
        FormInput::number(
            label: __( 'Minimum Order Amount' ),
            name: 'ns_special_min_order_amount',
            value: ns()->option->get( 'ns_special_min_order_amount', 0 ),
            description: __( 'Minimum order total required to apply special discount.' ),
            validation: 'nullable|numeric|min:0'
        ),
        FormInput::number(
            label: __( 'Minimum Top-up Amount' ),
            name: 'ns_special_min_topup_amount',
            value: ns()->option->get( 'ns_special_min_topup_amount', 1 ),
            description: __( 'Minimum amount allowed for special customer top-up.' ),
            validation: 'nullable|numeric|min:0.01'
        ),
        FormInput::number(
            label: __( 'Maximum Top-up Amount' ),
            name: 'ns_special_max_topup_amount',
            value: ns()->option->get( 'ns_special_max_topup_amount', 10000 ),
            description: __( 'Maximum amount allowed for special customer top-up.' ),
            validation: 'nullable|numeric|min:1'
        ),
        FormInput::switch(
            label: __( 'Enable Auto Cashback' ),
            name: 'ns_special_enable_auto_cashback',
            options: Helper::kvToJsOptions( [
                'yes' => __( 'Yes' ),
                'no' => __( 'No' ),
            ] ),
            value: $autoCashbackValue ? 'yes' : 'no',
            description: __( 'Enable automatic cashback processing.' )
        ),
        FormInput::select(
            label: __( 'Cashback Processing Month' ),
            name: 'ns_special_cashback_processing_month',
            value: ns()->option->get( 'ns_special_cashback_processing_month', 1 ),
            description: __( 'Month when auto cashback is processed.' ),
            options: Helper::kvToJsOptions( [
                1 => __( 'January' ),
                2 => __( 'February' ),
                3 => __( 'March' ),
                4 => __( 'April' ),
                5 => __( 'May' ),
                6 => __( 'June' ),
                7 => __( 'July' ),
                8 => __( 'August' ),
                9 => __( 'September' ),
                10 => __( 'October' ),
                11 => __( 'November' ),
                12 => __( 'December' ),
            ] )
        ),
    ],
];
