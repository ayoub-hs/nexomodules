<?php

namespace Modules\NsContainerManagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReceiveContainerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ns()->allowedTo('nexopos.receive.containers');
    }

    public function rules(): array
    {
        return [
            'container_type_id' => 'required|exists:ns_container_types,id',
            'quantity' => 'required|numeric|min:0.001',
            'provider' => 'nullable|string|max:255',
            'reference' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:255',
        ];
    }
}
