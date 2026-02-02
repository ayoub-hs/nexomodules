<?php

namespace Modules\NsManufacturing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductionOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return ns()->allowedTo('nexopos.update.manufacturing-orders');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $orderId = $this->route('id');
        
        return [
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('ns_manufacturing_orders', 'code')->ignore($orderId)
            ],
            'bom_id' => 'sometimes|required|exists:ns_manufacturing_boms,id',
            'product_id' => 'sometimes|required|exists:nexopos_products,id',
            'unit_id' => 'sometimes|required|exists:nexopos_units,id',
            'quantity' => 'sometimes|required|numeric|min:0.0001',
            'status' => 'sometimes|in:draft,planned,in_progress,completed,cancelled,on_hold',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'code.unique' => __('This production order code already exists'),
            'code.max' => __('Order code cannot exceed 255 characters'),
            'bom_id.required' => __('BOM is required'),
            'bom_id.exists' => __('Selected BOM does not exist'),
            'product_id.required' => __('Output product is required'),
            'product_id.exists' => __('Selected product does not exist'),
            'unit_id.required' => __('Unit is required'),
            'unit_id.exists' => __('Selected unit does not exist'),
            'quantity.required' => __('Production quantity is required'),
            'quantity.numeric' => __('Production quantity must be a number'),
            'quantity.min' => __('Production quantity must be greater than 0'),
            'status.in' => __('Invalid status value'),
        ];
    }
}
