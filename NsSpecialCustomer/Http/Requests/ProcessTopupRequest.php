<?php

namespace Modules\NsSpecialCustomer\Http\Requests;

use App\Models\Customer;
use App\Models\CustomerAccountHistory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

/**
 * Process Topup Request Validation
 *
 * Validates customer top-up requests with proper security checks,
 * amount validation, and business rule enforcement.
 */
class ProcessTopupRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::check() && ns()->allowedTo('special.customer.topup');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $minAmount = (float) ns()->option->get('ns_special_min_topup_amount', 1);
        $maxAmount = (float) ns()->option->get('ns_special_max_topup_amount', 10000);

        return [
            'customer_id' => [
                'required',
                'integer',
                'exists:nexopos_users,id',
            ],
            'amount' => [
                'required',
                'numeric',
                'min:' . $minAmount,
                'max:' . $maxAmount,
            ],
            'description' => [
                'nullable',
                'string',
                'max:255',
            ],
            'reference' => [
                'nullable',
                'string',
                'max:100',
            ],
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        $minAmount = (float) ns()->option->get('ns_special_min_topup_amount', 1);
        $maxAmount = (float) ns()->option->get('ns_special_max_topup_amount', 10000);

        return [
            'customer_id.required' => __('Please select a customer.'),
            'customer_id.exists' => __('The selected customer does not exist.'),
            'customer_id.integer' => __('Invalid customer ID format.'),
            'amount.required' => __('Please enter an amount.'),
            'amount.numeric' => __('The amount must be a valid number.'),
            'amount.min' => __('The minimum top-up amount is :min.', ['min' => ns()->currency->define($minAmount)->format()]),
            'amount.max' => __('The maximum top-up amount is :max.', ['max' => ns()->currency->define($maxAmount)->format()]),
            'description.max' => __('The description must not exceed 255 characters.'),
            'reference.max' => __('The reference must not exceed 100 characters.'),
        ];
    }

    /**
     * Get custom attributes for validation errors.
     */
    public function attributes(): array
    {
        return [
            'customer_id' => __('customer'),
            'amount' => __('top-up amount'),
            'description' => __('description'),
            'reference' => __('reference'),
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
        $customer = Customer::find($this->customer_id);
        $amount = (float) $this->amount;

        if (! $customer) {
            return;
        }

        // Check for suspicious activity (multiple large top-ups in short time)
        $largeTopupThreshold = 1000;
        if ($amount >= $largeTopupThreshold) {
            $recentLargeTopups = CustomerAccountHistory::where('customer_id', $this->customer_id)
                ->where('operation', CustomerAccountHistory::OPERATION_ADD)
                ->where('amount', '>=', $largeTopupThreshold)
                ->where('created_at', '>=', now()->subHours(24))
                ->count();

            if ($recentLargeTopups >= 5) {
                $validator->errors()->add('amount', __('Multiple large top-ups detected in the last 24 hours. Please contact support.'));
            }
        }

        // Validate daily top-up limits
        $dailyLimit = (float) ns()->option->get('ns_special_daily_topup_limit', 50000);
        if ($dailyLimit > 0) {
            $dailyTotal = CustomerAccountHistory::where('customer_id', $this->customer_id)
                ->where('operation', CustomerAccountHistory::OPERATION_ADD)
                ->whereDate('created_at', now()->toDateString())
                ->sum('amount');

            if (($dailyTotal + $amount) > $dailyLimit) {
                $validator->errors()->add('amount', __('Daily top-up limit of :limit exceeded.', [
                    'limit' => ns()->currency->define($dailyLimit)->format(),
                ]));
            }
        }
    }

    /**
     * Get validated data with proper type casting.
     */
    public function getValidatedData(): array
    {
        $data = parent::validated();

        // Type casting
        $data['customer_id'] = (int) $data['customer_id'];
        $data['amount'] = (float) $data['amount'];

        // Set defaults
        $data['description'] = $data['description'] ?? __('Special customer top-up');
        $data['reference'] = $data['reference'] ?? 'ns_special_topup';

        return $data;
    }
}
