<?php

namespace Modules\NsSpecialCustomer\Crud;

use App\Classes\CrudForm;
use App\Classes\FormInput;
use App\Models\Customer;
use App\Services\CrudEntry;
use App\Services\CrudService;
use App\Services\Helper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\NsSpecialCustomer\Models\SpecialCashbackHistory;
use Modules\NsSpecialCustomer\Services\SpecialCustomerService;
use Modules\NsSpecialCustomer\Services\WalletService;

/**
 * Customer Topup CRUD Class
 * 
 * Provides CRUD interface for managing special customer top-ups with
 * proper financial tracking, audit trail, and security controls.
 */
class CustomerTopupCrud extends CrudService
{
    const IDENTIFIER = 'ns.special-customer-topup';
    const AUTOLOAD = true;
    
    protected $table = 'nexopos_customers_account_history';
    protected $model = \App\Models\CustomerAccountHistory::class;
    protected $namespace = 'ns.special-customer-topup';
    
    /**
     * Define fillable fields for the model
     */
    public $fillable = [
        'customer_id',
        'order_id',
        'previous_amount',
        'amount',
        'next_amount',
        'operation',
        'description',
        'reference',
        'author',
    ];
    
    /**
     * Define table configuration
     */
    public function __construct()
    {
        parent::__construct();
        
        $this->mainRoute = 'dashboard/special-customer/topup';
        $this->permissions = [
            'create' => 'special.customer.topup',
            'read' => 'special.customer.view',
            'update' => 'special.customer.manage',
            'delete' => 'special.customer.manage',
        ];
    }

    /**
     * Get table columns configuration
     */
    public function getColumns(): array
    {
        return [
            'id' => [
                'label' => __('ID'),
                'width' => '80px',
                '$direction' => 'asc'
            ],
            'customer_name' => [
                'label' => __('Customer'),
                'width' => '200px',
                'filter' => 'like'
            ],
            'amount' => [
                'label' => __('Amount'),
                'width' => '120px',
                '$direction' => 'desc'
            ],
            'previous_amount' => [
                'label' => __('Previous Balance'),
                'width' => '120px'
            ],
            'next_amount' => [
                'label' => __('New Balance'),
                'width' => '120px',
                '$direction' => 'desc'
            ],
            'description' => [
                'label' => __('Description'),
                'width' => '200px'
            ],
            'author_name' => [
                'label' => __('Processed By'),
                'width' => '150px'
            ],
            'created_at' => [
                'label' => __('Date'),
                'width' => '150px'
            ]
        ];
    }

    /**
     * Get table entries
     */
    public function getEntries($config = []): array
    {
        $this->allowedTo('read');

        // Use Eloquent model to get top-up history
        $query = \App\Models\CustomerAccountHistory::with(['customer'])
            ->where('reference', 'ns_special_topup');

        // Apply filters
        if (isset($config['filter'])) {
            foreach ($config['filter'] as $key => $value) {
                if ($key === 'customer_name') {
                    $query->whereHas('customer', function($q) use ($value) {
                        $q->where('first_name', 'like', "%{$value}%")
                          ->orWhere('last_name', 'like', "%{$value}%");
                    });
                } elseif ($key === 'amount') {
                    $query->where('amount', 'like', "%{$value}%");
                }
            }
        }

        // Apply ordering
        $query->orderBy(
            $config['order_by'] ?? 'created_at',
            $config['direction'] ?? 'desc'
        );

        // Handle pagination
        $perPage = $config['per_page'] ?? 25;
        $page = $config['page'] ?? 1;

        if ($perPage > 0) {
            $entries = $query->paginate($perPage, ['*'], 'page', $page);
        } else {
            $entries = $query->get();
        }

        // Use parent method to handle proper CrudEntry creation and actions
        $result = parent::getEntries($config);

        // Override the data with our filtered results
        $data = $entries instanceof \Illuminate\Pagination\LengthAwarePaginator 
            ? $entries->getCollection() 
            : $entries;

        $result['data'] = $data->map(function ($entry) {
            // Ensure we have a model object, not just an ID
            if (is_numeric($entry)) {
                $entry = \App\Models\CustomerAccountHistory::find($entry);
            }
            
            if (!$entry) {
                return null;
            }
            
            // Convert to array first to ensure all fields are available
            $entryArray = $entry->toArray();
            
            // Ensure required fields exist
            if (!isset($entryArray['id'])) {
                $entryArray['id'] = $entry->id;
            }
            
            // Add customer name
            $entryArray['customer_name'] = $entry->customer ? $entry->customer->first_name . ' ' . $entry->customer->last_name : 'Unknown';
            
            // Add author name
            $authorId = $entry->author ?? 0;
            if ($authorId > 0) {
                $author = \App\Models\User::find($authorId);
                $entryArray['author_name'] = $author ? $author->username : __('Unknown');
            } else {
                $entryArray['author_name'] = __('System');
            }
            
            // Format currency fields
            if (isset($entryArray['amount'])) {
                $entryArray['formatted_amount'] = ns()->currency->define($entryArray['amount']);
            }
            
            if (isset($entryArray['previous_balance'])) {
                $entryArray['formatted_previous_balance'] = ns()->currency->define($entryArray['previous_balance']);
            }
            
            if (isset($entryArray['new_balance'])) {
                $entryArray['formatted_new_balance'] = ns()->currency->define($entryArray['new_balance']);
            }
            
            $crudEntry = new CrudEntry($entryArray);
            
            return $crudEntry;
        })->filter()->values()->toArray();

        // Update pagination info
        $result['total'] = $entries instanceof \Illuminate\Pagination\LengthAwarePaginator ? $entries->total() : count($entries);
        $result['per_page'] = $perPage;
        $result['current_page'] = $page;
        $result['last_page'] = $entries instanceof \Illuminate\Pagination\LengthAwarePaginator ? $entries->lastPage() : 1;

        return $result;
    }

    /**
     * Get form configuration
     * Amount is the main field, customer select is next to description in the tab
     */
    public function getForm( $entry = null )
    {
        return CrudForm::form(
            main: FormInput::number(
                name: 'amount',
                label: __( 'Amount' ),
                value: $entry->amount ?? '',
                validation: 'required|numeric|min:0.01',
                description: __( 'Enter the top-up amount.' )
            ),
            tabs: CrudForm::tabs(
                CrudForm::tab(
                    label: __( 'Top-up Details' ),
                    identifier: 'details',
                    fields: CrudForm::fields(
                        FormInput::searchSelect(
                            label: __( 'Customer' ),
                            name: 'customer_id',
                            value: $entry->customer_id ?? '',
                            options: $this->getSpecialCustomersOptions(),
                            validation: 'required|numeric|exists:nexopos_users,id',
                            description: __( 'Select the special customer to top-up.' )
                        ),
                        FormInput::textarea(
                            name: 'description',
                            label: __( 'Description' ),
                            value: $entry->description ?? '',
                            description: __( 'Optional description for this top-up.' )
                        )
                    )
                )
            )
        );
    }

    /**
     * Create new entry - completely override to use WalletService
     */
    public function createEntry(Request $request): array
    {
        $this->allowedTo('create');

        // Validate input - no auto-validation by parent
        $validated = $request->validate([
            'customer_id' => 'required|integer|exists:nexopos_users,id',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'nullable|string|max:255'
        ]);

        $walletService = app(\Modules\NsSpecialCustomer\Services\WalletService::class);
        
        try {
            $result = $walletService->processTopup(
                $validated['customer_id'],
                $validated['amount'],
                $validated['description'] ?? 'Special customer top-up',
                'ns_special_topup'
            );

            if ($result['success']) {
                return [
                    'status' => 'success',
                    'message' => __('Top-up processed successfully.'),
                    'data' => $result,
                ];
            } else {
                return [
                    'status' => 'error',
                    'message' => $result['message'],
                ];
            }
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => __('Failed to process top-up: ') . $e->getMessage(),
            ];
        }
    }

    /**
     * Define actions
     */
    public function setActions(CrudEntry $entry): CrudEntry
    {
        // Get author name from author_id if available (author is just an integer, not a relationship)
        $authorId = $entry->author ?? 0;
        if ($authorId > 0) {
            $author = \App\Models\User::find($authorId);
            $entry->author_name = $author ? $author->username : __('Unknown');
        } else {
            $entry->author_name = __('System');
        }
        
        $entry->customer_name = $entry->customer ? $entry->customer->first_name . ' ' . $entry->customer->last_name : __('Unknown');
        $entry->formatted_amount = ns()->currency->define($entry->amount);
        $entry->formatted_previous_balance = ns()->currency->define($entry->previous_amount ?? 0);
        $entry->formatted_next_balance = ns()->currency->define($entry->next_amount ?? 0);

        // Only show view action for top-up entries
        if ($entry->reference === 'ns_special_topup') {
            $entry->action(
                identifier: 'view_balance',
                label: __('View Balance'),
                url: ns()->url("/dashboard/special-customer/balance/{$entry->customer_id}")
            );
        }

        return $entry;
    }

    /**
     * Get special customers options
     * 
     * @return array of options with label and value
     */
    private function getSpecialCustomersOptions(): array
    {
        $specialCustomerService = app(SpecialCustomerService::class);
        $config = $specialCustomerService->getConfig();
        
        $query = Customer::query();
        
        // If special group is configured, filter by that group
        if (!empty($config['groupId'])) {
            $query->where('group_id', $config['groupId']);
        }
        
        // Get all customers and format as options
        $customers = $query->get(['id', 'first_name', 'last_name', 'email']);
        
        // If no customers found, return empty array with a placeholder
        if ($customers->isEmpty()) {
            return [
                [
                    'label' => __('No customers found. Please configure the special customer group.'),
                    'value' => ''
                ]
            ];
        }
        
        return $customers->map(function ($customer) use ($config) {
            $prefix = empty($config['groupId']) ? '[All] ' : '';
            return [
                'label' => $prefix . $customer->first_name . ' ' . $customer->last_name . ' (' . $customer->email . ')',
                'value' => $customer->id
            ];
        })->toArray();
    }

    /**
     * Hook into query for filtering
     */
    public function hook($query): void
    {
        // Only show special customer top-ups
        $query->where('reference', 'ns_special_topup');
        
        if ($query instanceof \Illuminate\Database\Eloquent\Builder) {
            $query->with(['customer']);
        }
    }

    /**
     * Get single entry
     */
    public function getEntry(int $id): array
    {
        $this->allowedTo('read');

        $entry = \App\Models\CustomerAccountHistory::with(['customer'])
            ->where('reference', 'ns_special_topup')
            ->findOrFail($id);

        return [
            'status' => 'success',
            'data' => $entry
        ];
    }

    /**
     * Update entry (not allowed for financial records)
     */
    public function updateEntry(int $id, Request $request): array
    {
        $this->allowedTo('update');

        throw new \Exception('Top-up transactions cannot be modified for audit purposes.');
    }

    /**
     * Delete entry (not allowed for financial records)
     */
    public function deleteEntry(int $id): array
    {
        $this->allowedTo('delete');

        throw new \Exception('Top-up transactions cannot be deleted for audit purposes.');
    }

    /**
     * Get labels for CRUD operations
     */
    public function getLabels()
    {
        return [
            'list_title' => __('Customer Top-ups'),
            'list_description' => __('Manage special customer top-up transactions.'),
            'no_entry' => __('No top-up transactions found.'),
            'create_new' => __('Add Top-up'),
            'create_title' => __('New Customer Top-up'),
            'create_description' => __('Add funds to a special customer account.'),
            'edit_title' => __('Edit Top-up'),
            'edit_description' => __('Modify top-up transaction details.'),
            'back_to_list' => __('Back to Top-ups'),
        ];
    }

    /**
     * Get links
     */
    public function getLinks(): array
    {
        return [
            'list' => ns()->url('dashboard/special-customer/topup'),
            'create' => ns()->url('dashboard/special-customer/topup/create'),
            'edit' => '#',  // Disabled - top-up transactions cannot be modified
            'post' => ns()->url('api/crud/' . 'ns.special-customer-topup'),
        ];
    }

    /**
     * Get bulk actions
     */
    public function getBulkActions(): array
    {
        return [];
    }

    /**
     * Check if a feature is enabled
     */
    public function isEnabled($feature): bool
    {
        return match ($feature) {
            'bulk-actions' => false,
            'single-action' => true,
            'checkboxes' => false,
            default => false,
        };
    }

    /**
     * Filter GET input fields
     */
    public function filterGetInputs($inputs, $entry): array
    {
        return $inputs;
    }

    /**
     * Filter POST input fields - only allow valid fields
     * This prevents "undefined" column errors when using WalletService
     */
    public function filterPostInputs($inputs, $entry): array
    {
        // Get current customer balance for previous_amount
        $customerId = $inputs['customer_id'] ?? 0;
        $amount = floatval($inputs['amount'] ?? 0);
        
        $customer = \App\Models\Customer::find($customerId);
        $previousBalance = $customer ? floatval($customer->account_amount) : 0;
        $newBalance = $previousBalance + $amount;
        
        // Only return the fields we actually need for top-up processing
        return [
            'customer_id' => $customerId,
            'amount' => $amount,
            'description' => $inputs['description'] ?? null,
            'operation' => \App\Models\CustomerAccountHistory::OPERATION_ADD,
            'reference' => 'ns_special_topup',
            'previous_amount' => $previousBalance,
            'next_amount' => $newBalance,
            'author' => auth()->id() ?? 0,
        ];
    }

    /**
     * After CRUD POST - Update customer balance
     */
    public function afterPost($inputs, $entry, $filteredInputs): array
    {
        // Update customer account balance
        if ($entry && $entry->customer_id) {
            $customer = \App\Models\Customer::find($entry->customer_id);
            if ($customer && isset($entry->next_amount)) {
                $customer->account_amount = $entry->next_amount;
                $customer->save();
            }
        }
        
        return $inputs;
    }

    /**
     * After CRUD PUT
     */
    public function afterPut($inputs): array
    {
        return $inputs;
    }

    /**
     * Before Delete
     */
    public function beforeDelete($namespace, $id): void
    {
        // Prevent deletion of financial records
        throw new \Exception('Top-up transactions cannot be deleted for audit purposes.');
    }

    /**
     * Before Post
     */
    public function beforePost($request): void
    {
        $this->allowedTo('create');
    }

    /**
     * Before Put
     */
    public function beforePut($request, $id): void
    {
        $this->allowedTo('update');
    }

    /**
     * Get
     */
    public function get($param): mixed
    {
        return match ($param) {
            'model' => $this->model,
            default => null,
        };
    }

    /**
     * Check if user is allowed to perform an action
     * Properly implements permission checking using ns()->restrict()
     */
    public function allowedTo(string $permission): void
    {
        $permissions = [
            'create' => 'special.customer.topup',
            'read' => 'special.customer.view',
            'update' => 'special.customer.manage',
            'delete' => 'special.customer.manage',
        ];

        if (isset($permissions[$permission])) {
            ns()->restrict($permissions[$permission]);
        }
    }
}
