<?php

namespace Modules\NsContainerManagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateContainerTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ns()->allowedTo('nexopos.create.container-types') || ns()->allowedTo('nexopos.update.container-types');
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:100',
            'capacity' => 'required|numeric|min:0.001',
            'capacity_unit' => 'required|string|max:10',
            'deposit_fee' => 'required|numeric|min:0',
            'is_active' => 'sometimes|boolean',
            'description' => 'nullable|string|max:255',
            'initial_stock' => 'sometimes|integer|min:0',
        ];
    }
}
