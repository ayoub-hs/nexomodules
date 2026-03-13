<?php

namespace Modules\NsContainerManagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ChargeCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ns()->allowedTo('nexopos.charge.containers');
    }

    public function rules(): array
    {
        return [
            'customer_id' => 'required|exists:nexopos_users,id',
            'container_type_id' => 'required|exists:ns_container_types,id',
            'quantity' => 'required|numeric|min:0.001',
            'note' => 'nullable|string|max:255',
        ];
    }
}
