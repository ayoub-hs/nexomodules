<?php

namespace Modules\NsManufacturing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\NsManufacturing\Services\BomService;

class CreateBomItemRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return ns()->allowedTo('nexopos.create.manufacturing-recipes');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'bom_id' => 'required|exists:ns_manufacturing_boms,id',
            'product_id' => 'required|exists:nexopos_products,id',
            'unit_id' => 'required|exists:nexopos_units,id',
            'quantity' => 'required|numeric|min:0.0001',
            'waste_percent' => 'nullable|numeric|min:0|max:100',
            'cost_allocation' => 'nullable|numeric|min:0',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'bom_id.required' => __('BOM is required'),
            'bom_id.exists' => __('Selected BOM does not exist'),
            'product_id.required' => __('Component product is required'),
            'product_id.exists' => __('Selected product does not exist'),
            'unit_id.required' => __('Unit is required'),
            'unit_id.exists' => __('Selected unit does not exist'),
            'quantity.required' => __('Quantity is required'),
            'quantity.numeric' => __('Quantity must be a number'),
            'quantity.min' => __('Quantity must be greater than 0'),
            'waste_percent.numeric' => __('Waste percentage must be a number'),
            'waste_percent.min' => __('Waste percentage cannot be negative'),
            'waste_percent.max' => __('Waste percentage cannot exceed 100'),
            'cost_allocation.numeric' => __('Cost allocation must be a number'),
            'cost_allocation.min' => __('Cost allocation cannot be negative'),
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Check for circular dependency
            $bomService = app(BomService::class);
            
            if (!$bomService->validateCircularDependency(
                $this->input('bom_id'),
                $this->input('product_id')
            )) {
                $validator->errors()->add(
                    'product_id',
                    __('Circular dependency detected. This product cannot be added as a component to this BOM.')
                );
            }
        });
    }
}
