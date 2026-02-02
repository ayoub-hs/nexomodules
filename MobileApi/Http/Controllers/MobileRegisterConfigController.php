<?php

namespace Modules\MobileApi\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Register;
use App\Models\Tax;

class MobileRegisterConfigController extends Controller
{
    public function show()
    {
        $register = Register::query()->first();
        $defaultTax = Tax::orderBy('rate')->first();

        return response()->json([
            'register_id'       => $register?->id,
            'store_name'        => ns()->option->get('store_name'),
            'store_address'     => ns()->option->get('store_address'),
            'store_phone'       => ns()->option->get('store_phone'),
            'currency_symbol'   => ns()->option->get('currency_symbol', '$'),
            'currency_position' => ns()->option->get('currency_position', 'before'),
            'tax_enabled'       => (bool) ns()->option->get('enable_taxes', false),
            'default_tax_rate'  => $defaultTax?->rate,
            'receipt_header'    => ns()->option->get('receipt_header'),
            'receipt_footer'    => ns()->option->get('receipt_footer'),
        ]);
    }
}
