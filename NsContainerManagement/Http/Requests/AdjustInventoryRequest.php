<?php

namespace Modules\NsContainerManagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdjustInventoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ns()->allowedTo('nexopos.adjust.container-stock');
    }

    public function rules(): array
    {
        return [
            'container_type_id' => 'required|exists:ns_container_types,id',
            'adjustment' => 'required|numeric',
            'reason' => 'nullable|string|max:255',
        ];
    }
}
