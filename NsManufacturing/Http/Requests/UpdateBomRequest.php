<?php

namespace Modules\NsManufacturing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBomRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return ns()->allowedTo('nexopos.update.manufacturing-recipes');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => 'sometimes|required|string|max:255',
            'product_id' => 'sometimes|required|exists:nexopos_products,id',
            'unit_id' => 'sometimes|required|exists:nexopos_units,id',
            'quantity' => 'sometimes|required|numeric|min:0.0001',
            'is_active' => 'sometimes|boolean',
            'description' => 'nullable|string|max:1000',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => __('BOM name is required'),
            'name.max' => __('BOM name cannot exceed 255 characters'),
            'product_id.required' => __('Output product is required'),
            'product_id.exists' => __('Selected product does not exist'),
            'unit_id.required' => __('Unit is required'),
            'unit_id.exists' => __('Selected unit does not exist'),
            'quantity.required' => __('Output quantity is required'),
            'quantity.numeric' => __('Output quantity must be a number'),
            'quantity.min' => __('Output quantity must be greater than 0'),
            'description.max' => __('Description cannot exceed 1000 characters'),
        ];
    }
}
