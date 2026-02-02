<?php

namespace Modules\NsSpecialCustomer\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Modules\NsSpecialCustomer\Services\SpecialCustomerService;
use Modules\NsSpecialCustomer\Models\SpecialCashbackHistory;

/**
 * Process Cashback Request Validation
 * 
 * Validates cashback processing requests with proper security checks,
 * year validation, and business rule enforcement.
 */
class ProcessCashbackRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::check() && Auth::user()->can('special.customer.cashback');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'year' => [
                'required',
                'integer',
                'min:2020',
                'max:' . (now()->year + 1),
                function ($attribute, $value, $fail) {
                    if ($value > now()->year) {
                        $fail('Cannot process cashback for future years.');
                    }
                },
            ],
            'customer_ids' => [
                'nullable',
                'array',
                'max:100', // Limit batch size
            ],
            'customer_ids.*' => [
                'integer',
                'exists:nexopos_users,id',
                function ($attribute, $value, $fail) {
                    $customer = \App\Models\Customer::find($value);
                    if (!$customer) {
                        return;
                    }
                    
                    $specialCustomerService = app(SpecialCustomerService::class);
                    if (!$specialCustomerService->isSpecialCustomer($customer)) {
                        $fail("Customer ID {$value} is not a special customer.");
                    }
                },
            ],
            'force_reprocess' => [
                'boolean',
            ],
            'dry_run' => [
                'boolean',
            ],
            'description' => [
                'nullable',
                'string',
                'max:255',
            ],
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'year.required' => 'Please specify a year for cashback processing.',
            'year.integer' => 'Year must be a valid integer.',
            'year.min' => 'Year must be 2020 or later.',
            'year.max' => 'Year cannot be more than one year in the future.',
            'customer_ids.array' => 'Customer IDs must be provided as an array.',
            'customer_ids.max' => 'Cannot process more than 100 customers at once.',
            'customer_ids.*.integer' => 'Invalid customer ID format.',
            'customer_ids.*.exists' => 'Customer ID :input does not exist.',
            'force_reprocess.boolean' => 'Force reprocess must be a boolean value.',
            'dry_run.boolean' => 'Dry run must be a boolean value.',
            'description.max' => 'Description must not exceed 255 characters.',
        ];
    }

    /**
     * Get custom attributes for validation errors.
     */
    public function attributes(): array
    {
        return [
            'year' => 'cashback year',
            'customer_ids' => 'customers',
            'force_reprocess' => 'force reprocess',
            'dry_run' => 'dry run',
            'description' => 'description',
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
        $year = (int) $this->year;
        $customerIds = $this->customer_ids ?? [];

        // Check if cashback has already been processed for the year
        if (!$this->force_reprocess && empty($customerIds)) {
            $processedCount = SpecialCashbackHistory::where('year', $year)
                ->where('status', SpecialCashbackHistory::STATUS_PROCESSED)
                ->count();

            if ($processedCount > 0) {
                $validator->errors()->add('year', 
                    "Cashback for {$year} has already been processed for {$processedCount} customers. " .
                    "Use force reprocess to override."
                );
            }
        }

        // Validate specific customers if provided
        if (!empty($customerIds)) {
            foreach ($customerIds as $customerId) {
                if (!$this->force_reprocess) {
                    $alreadyProcessed = SpecialCashbackHistory::isProcessedForCustomerYear($customerId, $year);
                    if ($alreadyProcessed) {
                        $validator->errors()->add('customer_ids', 
                            "Cashback for customer ID {$customerId} in {$year} has already been processed."
                        );
                    }
                }
            }
        }

        // Check if there's sufficient purchase data for the year
        if (!$this->dry_run) {
            $hasPurchaseData = \App\Models\CustomerAccountHistory::whereYear('created_at', $year)
                ->where('operation', 'ORDER_PAYMENT')
                ->exists();

            if (!$hasPurchaseData) {
                $validator->errors()->add('year', 
                    "No purchase data found for {$year}. Cannot process cashback."
                );
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
        $data['year'] = (int) $data['year'];
        $data['force_reprocess'] = (bool) ($data['force_reprocess'] ?? false);
        $data['dry_run'] = (bool) ($data['dry_run'] ?? false);
        
        // Set defaults
        $data['customer_ids'] = $data['customer_ids'] ?? [];
        $data['description'] = $data['description'] ?? "Special Customer Cashback for {$data['year']}";
        
        return $data;
    }

    /**
     * Get processing options as an array.
     */
    public function getProcessingOptions(): array
    {
        return [
            'year' => $this->year,
            'customer_ids' => $this->customer_ids,
            'force_reprocess' => $this->force_reprocess,
            'dry_run' => $this->dry_run,
            'description' => $this->description,
            'author_id' => Auth::id(),
        ];
    }
}
