<?php

namespace Modules\NsSpecialCustomer\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

/**
 * Update Settings Request Validation
 * 
 * Validates special customer settings updates with proper security checks,
 * value validation, and business rule enforcement.
 */
class UpdateSettingsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::check() && Auth::user()->can('special.customer.settings');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'ns_special_customer_group_id' => [
                'required',
                'integer',
                'exists:nexopos_customers_groups,id',
            ],
            'ns_special_discount_percentage' => [
                'required',
                'numeric',
                'min:0',
                'max:100',
                'regex:/^\d+(\.\d{1,2})?$/',
            ],
            'ns_special_cashback_percentage' => [
                'required',
                'numeric',
                'min:0',
                'max:50',
                'regex:/^\d+(\.\d{1,2})?$/',
            ],
            'ns_special_apply_discount_stackable' => [
                'boolean',
            ],
            'ns_special_min_order_amount' => [
                'nullable',
                'numeric',
                'min:0',
                'max:999999.99',
                'regex:/^\d+(\.\d{1,2})?$/',
            ],
            'ns_special_max_topup_amount' => [
                'nullable',
                'numeric',
                'min:1',
                'max:999999.99',
                'regex:/^\d+(\.\d{1,2})?$/',
            ],
            'ns_special_min_topup_amount' => [
                'nullable',
                'numeric',
                'min:0.01',
                'max:999999.99',
                'regex:/^\d+(\.\d{1,2})?$/',
            ],
            'ns_special_enable_auto_cashback' => [
                'boolean',
            ],
            'ns_special_cashback_processing_month' => [
                'nullable',
                'integer',
                'min:1',
                'max:12',
            ],
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'ns_special_customer_group_id.required' => 'Please select a customer group.',
            'ns_special_customer_group_id.exists' => 'The selected customer group does not exist.',
            'ns_special_discount_percentage.required' => 'Discount percentage is required.',
            'ns_special_discount_percentage.min' => 'Discount percentage cannot be negative.',
            'ns_special_discount_percentage.max' => 'Discount percentage cannot exceed 100%.',
            'ns_special_cashback_percentage.required' => 'Cashback percentage is required.',
            'ns_special_cashback_percentage.min' => 'Cashback percentage cannot be negative.',
            'ns_special_cashback_percentage.max' => 'Cashback percentage cannot exceed 50%.',
            'ns_special_min_order_amount.min' => 'Minimum order amount cannot be negative.',
            'ns_special_max_topup_amount.min' => 'Maximum top-up amount must be at least 1.',
            'ns_special_min_topup_amount.min' => 'Minimum top-up amount must be at least 0.01.',
            'ns_special_cashback_processing_month.min' => 'Processing month must be between 1 and 12.',
            'ns_special_cashback_processing_month.max' => 'Processing month must be between 1 and 12.',
        ];
    }

    /**
     * Get custom attributes for validation errors.
     */
    public function attributes(): array
    {
        return [
            'ns_special_customer_group_id' => 'customer group',
            'ns_special_discount_percentage' => 'discount percentage',
            'ns_special_cashback_percentage' => 'cashback percentage',
            'ns_special_apply_discount_stackable' => 'stackable discount',
            'ns_special_min_order_amount' => 'minimum order amount',
            'ns_special_max_topup_amount' => 'maximum top-up amount',
            'ns_special_min_topup_amount' => 'minimum top-up amount',
            'ns_special_enable_auto_cashback' => 'auto cashback',
            'ns_special_cashback_processing_month' => 'cashback processing month',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isEmpty()) {
                $this->validateBusinessRules($validator);
            }
        });
    }

    /**
     * Validate business-specific rules.
     */
    protected function validateBusinessRules($validator): void
    {
        // Validate min/max top-up amount relationship
        $minTopup = $this->ns_special_min_topup_amount;
        $maxTopup = $this->ns_special_max_topup_amount;

        if ($minTopup && $maxTopup && $minTopup >= $maxTopup) {
            $validator->errors()->add('ns_special_min_topup_amount', 
                'Minimum top-up amount must be less than maximum top-up amount.');
        }

        // Validate percentage combinations
        $discountPercentage = (float) $this->ns_special_discount_percentage;
        $cashbackPercentage = (float) $this->ns_special_cashback_percentage;

        if ($discountPercentage + $cashbackPercentage > 100) {
            $validator->errors()->add('ns_special_cashback_percentage', 
                'Combined discount and cashback percentages should not exceed 100% for business sustainability.');
        }

        // Check if customer group has customers assigned
        $groupId = (int) $this->ns_special_customer_group_id;
        $customerCount = \App\Models\Customer::where('group_id', $groupId)->count();

        if ($customerCount === 0) {
            $validator->errors()->add('ns_special_customer_group_id', 
                'Warning: No customers are currently assigned to this group.');
        }

        // Validate auto cashback settings
        if ($this->ns_special_enable_auto_cashback && !$this->ns_special_cashback_processing_month) {
            $validator->errors()->add('ns_special_cashback_processing_month', 
                'Processing month is required when auto cashback is enabled.');
        }
    }

    /**
     * Get validated data with proper type casting.
     */
    public function getValidatedData(): array
    {
        $data = parent::validated();
        
        // Type casting
        $data['ns_special_customer_group_id'] = (int) $data['ns_special_customer_group_id'];
        $data['ns_special_discount_percentage'] = (float) $data['ns_special_discount_percentage'];
        $data['ns_special_cashback_percentage'] = (float) $data['ns_special_cashback_percentage'];
        $data['ns_special_apply_discount_stackable'] = (bool) $data['ns_special_apply_discount_stackable'];
        $data['ns_special_enable_auto_cashback'] = (bool) $data['ns_special_enable_auto_cashback'];
        
        // Optional numeric fields
        if (isset($data['ns_special_min_order_amount'])) {
            $data['ns_special_min_order_amount'] = (float) $data['ns_special_min_order_amount'];
        }
        if (isset($data['ns_special_max_topup_amount'])) {
            $data['ns_special_max_topup_amount'] = (float) $data['ns_special_max_topup_amount'];
        }
        if (isset($data['ns_special_min_topup_amount'])) {
            $data['ns_special_min_topup_amount'] = (float) $data['ns_special_min_topup_amount'];
        }
        if (isset($data['ns_special_cashback_processing_month'])) {
            $data['ns_special_cashback_processing_month'] = (int) $data['ns_special_cashback_processing_month'];
        }
        
        return $data;
    }

    /**
     * Get settings summary for audit trail.
     */
    public function getSettingsSummary(): array
    {
        return [
            'updated_by' => Auth::id(),
            'updated_at' => now(),
            'changes' => $this->getValidatedData(),
            'ip_address' => $this->ip(),
            'user_agent' => $this->userAgent(),
        ];
    }
}
