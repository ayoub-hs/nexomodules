<?php

namespace Modules\NsSpecialCustomer\Crud;

use App\Services\CrudService;
use App\Services\CrudEntry;
use App\Services\CrudForm;
use App\Classes\FormInput;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Order;
use Modules\NsSpecialCustomer\Services\SpecialCustomerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SpecialCustomerCrud extends CrudService
{
    const IDENTIFIER = 'ns.special-customers';
    const AUTOLOAD = true;
    
    protected $table = 'nexopos_users';
    protected $model = Customer::class;
    protected $namespace = 'ns.special-customers';
    
    /**
     * Define table configuration
     */
    public function __construct()
    {
        parent::__construct();
        
        $this->mainRoute = 'dashboard/special-customer/customers';
        $this->permissions = [
            'create' => 'special.customer.manage',
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
            'name' => [
                'label' => __('Customer'),
                'width' => '200px',
                'filter' => 'like',
                'component' => 'ns-crud-column-customer-name'
            ],
            'email' => [
                'label' => __('Email'),
                'width' => '200px',
                'filter' => 'like'
            ],
            'group_name' => [
                'label' => __('Group'),
                'width' => '100px',
                'filter' => 'exact'
            ],
            'account_amount' => [
                'label' => __('Balance'),
                'width' => '120px',
                '$direction' => 'desc'
            ],
            'total_unpaid' => [
                'label' => __('Total Unpaid'),
                'width' => '130px',
                '$direction' => 'desc'
            ],
            'created_at' => [
                'label' => __('Created At'),
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

        $specialCustomerService = app(SpecialCustomerService::class);
        $specialConfig = $specialCustomerService->getConfig();

        // Use Eloquent model instead of raw SQL
        $query = Customer::with(['group'])
            ->where('group_id', $specialConfig['groupId']);

        // Apply filters
        if (isset($config['filter'])) {
            foreach ($config['filter'] as $key => $value) {
                if ($key === 'name') {
                    $query->where(function($query) use ($value) {
                        $query->where('first_name', 'like', "%{$value}%")
                              ->orWhere('last_name', 'like', "%{$value}%");
                    });
                } elseif ($key === 'email') {
                    $query->where('email', 'like', "%{$value}%");
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

        // Get all customer IDs to fetch unpaid amounts in a single query
        $customerIds = $data->pluck('id')->toArray();
        $unpaidAmounts = $this->getUnpaidAmountsForCustomers($customerIds);

        $result['data'] = $data->map(function ($entry) use ($unpaidAmounts) {
            // Ensure we have a model object, not just an ID
            if (is_numeric($entry)) {
                $entry = Customer::find($entry);
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
            
            // Create formatted customer name
            $entryArray['name'] = $entry->first_name . ' ' . $entry->last_name;
            
            $crudEntry = new CrudEntry($entryArray);
            
            // Add special customer status
            $crudEntry->is_special = true;
            
            // Add group name
            $crudEntry->group_name = $entry->group ? $entry->group->name : 'None';
            
            // Format currency fields
            if (isset($crudEntry->account_amount)) {
                $crudEntry->formatted_amount = ns()->currency->define($crudEntry->account_amount);
            }
            
            // Add total unpaid amount
            $unpaidAmount = $unpaidAmounts[$entry->id] ?? 0;
            $crudEntry->total_unpaid = ns()->currency->define($unpaidAmount)->format();
            $crudEntry->total_unpaid_raw = $unpaidAmount;
            
            // Apply actions using our setActions method
            $this->setActions($crudEntry);
            
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
     */
    public function getForm($entry = null): array
    {
        $specialCustomerService = app(SpecialCustomerService::class);
        $config = $specialCustomerService->getConfig();

        return [
            'main' => [
                'label' => __('Customer Information'),
                'name' => 'main',
                'fields' => [
                    [
                        'type' => 'text',
                        'name' => 'first_name',
                        'label' => __('First Name'),
                        'description' => __('Customer first name'),
                        'value' => $entry?->first_name,
                        'disabled' => true,
                    ],
                    [
                        'type' => 'text',
                        'name' => 'last_name',
                        'label' => __('Last Name'),
                        'description' => __('Customer last name'),
                        'value' => $entry?->last_name,
                        'disabled' => true,
                    ],
                    [
                        'type' => 'text',
                        'name' => 'email',
                        'label' => __('Email'),
                        'description' => __('Customer email address'),
                        'value' => $entry?->email,
                        'disabled' => true,
                    ],
                    [
                        'type' => 'select',
                        'name' => 'group_id',
                        'label' => __('Customer Group'),
                        'description' => __('Assign customer to a group. Special customers should be assigned to the special group.'),
                        'options' => $this->getCustomerGroupsOptions(),
                        'value' => $entry?->group_id,
                    ],
                ],
            ],
        ];
    }

    /**
     * Create new entry
     */
    public function createEntry(Request $request): array
    {
        $this->allowedTo('create');

        throw new \Exception('Customer creation should be done through the main customer management system.');
    }

    /**
     * Get single entry
     */
    public function getEntry(int $id): array
    {
        $this->allowedTo('read');

        $specialCustomerService = app(SpecialCustomerService::class);
        
        $entry = Customer::with(['group'])->findOrFail($id);
        
        $data = $entry->toArray();
        $data['is_special'] = $specialCustomerService->isSpecialCustomer($entry);
        $data['group_name'] = $entry->group ? $entry->group->name : 'None';

        return [
            'status' => 'success',
            'data' => $data
        ];
    }

    /**
     * Update entry
     */
    public function updateEntry(int $id, Request $request): array
    {
        $this->allowedTo('update');

        $customer = Customer::findOrFail($id);

        $request->validate([
            'group_id' => 'nullable|integer|exists:nexopos_customers_groups,id'
        ]);

        $customer->update([
            'group_id' => $request->group_id
        ]);

        return [
            'status' => 'success',
            'message' => __('Customer updated successfully'),
            'data' => $customer->fresh()->load(['group'])
        ];
    }

    /**
     * Delete entry
     */
    public function deleteEntry(int $id): array
    {
        $this->allowedTo('delete');

        throw new \Exception('Customer deletion should be done through the main customer management system.');
    }

    /**
     * Set actions for entries
     */
    protected function setActions(CrudEntry $entry): CrudEntry
    {
        // View Balance action
        $entry->action(
            identifier: 'view_balance',
            label: __('View Balance'),
            url: ns()->url("/dashboard/special-customer/balance/{$entry->id}")
        );

        // Top-up action
        $entry->action(
            identifier: 'topup',
            label: __('Top-up'),
            url: ns()->url("/dashboard/special-customer/topup?customer_id={$entry->id}")
        );

        // Statistics action
        $entry->action(
            identifier: 'statistics',
            label: __('Statistics'),
            url: ns()->url("/dashboard/special-customer/statistics?customer_id={$entry->id}")
        );

        // Edit group action
        $entry->action(
            identifier: 'edit_group',
            label: __('Edit Group'),
            url: ns()->url("/dashboard/customers/{$entry->id}/edit")
        );

        return $entry;
    }

    /**
     * Get customer groups options
     */
    private function getCustomerGroupsOptions(): array
    {
        return CustomerGroup::all()->map(function ($group) {
            return [
                'label' => $group->name,
                'value' => $group->id
            ];
        })->toArray();
    }

    /**
     * Get unpaid amounts for multiple customers in a single query
     * 
     * @param array $customerIds Array of customer IDs
     * @return array Associative array of customer_id => unpaid_amount
     */
    private function getUnpaidAmountsForCustomers(array $customerIds): array
    {
        if (empty($customerIds)) {
            return [];
        }

        // Query orders that are unpaid or partially paid
        // Payment statuses: 'unpaid', 'partially_paid', 'hold'
        $unpaidOrders = DB::table('nexopos_orders')
            ->select('customer_id', DB::raw('SUM(total - tendered) as unpaid_amount'))
            ->whereIn('customer_id', $customerIds)
            ->whereIn('payment_status', ['unpaid', 'partially_paid', 'hold'])
            ->groupBy('customer_id')
            ->get();

        $result = [];
        foreach ($unpaidOrders as $order) {
            $result[$order->customer_id] = (float) $order->unpaid_amount;
        }

        return $result;
    }

    /**
     * Hook called before creating entry
     */
    public function beforePost(array $data): array
    {
        return $data;
    }

    /**
     * Hook called after creating entry
     */
    public function afterPost(array $data, CrudEntry $entry): array
    {
        return $data;
    }

    /**
     * Hook called before updating entry
     */
    public function beforePut(array $data, CrudEntry $entry): array
    {
        return $data;
    }

    /**
     * Hook called after updating entry
     */
    public function afterPut(array $data, CrudEntry $entry): array
    {
        return $data;
    }

    /**
     * Hook called before deleting entry
     */
    public function beforeDelete(CrudEntry $entry): array
    {
        return [];
    }

    /**
     * Hook called after deleting entry
     */
    public function afterDelete(CrudEntry $entry): array
    {
        return [];
    }

    /**
     * Check if user is allowed to perform an action
     * Properly implements permission checking using ns()->restrict()
     */
    public function allowedTo(string $permission): void
    {
        $permissions = [
            'create' => 'special.customer.manage',
            'read' => 'special.customer.view',
            'update' => 'special.customer.manage',
            'delete' => 'special.customer.manage',
        ];

        if (isset($permissions[$permission])) {
            ns()->restrict($permissions[$permission]);
        }
    }

    /**
     * Get bulk actions for mass operations
     */
    public function getBulkActions(): array
    {
        return [
            [
                'label' => __('Export Selected'),
                'identifier' => 'export_selected',
                'url' => ns()->route('ns.api.crud-bulk-actions', [
                    'namespace' => $this->namespace,
                ]),
            ],
            [
                'label' => __('Assign to Special Group'),
                'identifier' => 'assign_special_group',
                'url' => ns()->route('ns.api.crud-bulk-actions', [
                    'namespace' => $this->namespace,
                ]),
            ],
        ];
    }
}
