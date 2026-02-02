<?php

namespace Modules\NsManufacturing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateProductionOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return ns()->allowedTo('nexopos.create.manufacturing-orders');
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Auto-generate code if not provided
        if (empty($this->code)) {
            $this->merge([
                'code' => 'MO-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6))
            ]);
        }
        
        // Set default status if not provided
        if (empty($this->status)) {
            $this->merge([
                'status' => 'planned'
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'code' => 'nullable|string|max:255|unique:ns_manufacturing_orders,code',
            'bom_id' => 'required|exists:ns_manufacturing_boms,id',
            'product_id' => 'required|exists:nexopos_products,id',
            'unit_id' => 'required|exists:nexopos_units,id',
            'quantity' => 'required|numeric|min:0.0001',
            'status' => 'sometimes|in:draft,planned',
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
            'status.in' => __('Status must be either draft or planned'),
        ];
    }
}
