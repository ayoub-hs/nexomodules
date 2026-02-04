<?php

namespace Modules\NsSpecialCustomer\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Modules\NsSpecialCustomer\Services\SpecialCustomerService;
use Modules\NsSpecialCustomer\Services\WalletService;
use App\Services\CustomerService;
use App\Models\Customer;
use Modules\NsSpecialCustomer\Crud\CustomerTopupCrud;
use Modules\NsSpecialCustomer\Http\Requests\ProcessTopupRequest;

class SpecialCustomerController extends Controller
{
    private SpecialCustomerService $specialCustomerService;
    private CustomerService $customerService;

    public function __construct(
        SpecialCustomerService $specialCustomerService,
        CustomerService $customerService
    ) {
        $this->specialCustomerService = $specialCustomerService;
        $this->customerService = $customerService;
    }

    /**
     * Get special customer configuration
     */
    public function getConfig(): JsonResponse
    {
        $isTesting = app()->runningUnitTests() || app()->environment('testing') || strtolower((string) env('APP_ENV')) === 'testing';

        if (auth()->check()) {
            if (!ns()->allowedTo('special.customer.settings')) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('You don\'t have permission to access this resource.')
                ], 403);
            }
        } else {
            return response()->json([
                'status' => 'error',
                'message' => __('Authentication required.')
            ], 401);
        }
        
        $config = $this->specialCustomerService->getConfig();
        
        return response()->json([
            'status' => 'success',
            'data' => $config
        ]);
    }

    /**
     * Get dashboard statistics
     */
    public function getStats(): JsonResponse
    {
        try {
            $config = $this->specialCustomerService->getConfig();
            
            // Get special customer group ID
            $groupId = $config['groupId'] ?? null;
            
            if (!$groupId) {
                return response()->json([
                    'status' => 'success',
                    'data' => [
                        'total_customers' => 0,
                        'total_balance' => 0,
                        'pending_cashback' => 0
                    ]
                ]);
            }
            
            // Count special customers and their total balance
            $customers = Customer::where('group_id', $groupId)->get();
            $totalCustomers = $customers->count();
            $totalBalance = $customers->sum('account_amount');
            
            return response()->json([
                'status' => 'success',
                'data' => [
                    'total_customers' => $totalCustomers,
                    'total_balance' => $totalBalance,
                    'pending_cashback' => 0
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check if customer is special customer
     */
    public function checkCustomerSpecialStatus(int $customerId): JsonResponse
    {
        try {
            $customer = $this->customerService->get($customerId);
            $isSpecial = $this->specialCustomerService->isSpecialCustomer($customer);
            $config = $this->specialCustomerService->getConfig();

            return response()->json([
                'status' => 'success',
                'data' => [
                    'isSpecial' => $isSpecial,
                    'config' => $config
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Legacy method for backward compatibility
     */
    public function checkCustomer(int $customerId): JsonResponse
    {
        return $this->checkCustomerSpecialStatus($customerId);
    }

    /**
     * Top-up customer account
     */
    public function topUpAccount(ProcessTopupRequest $request): JsonResponse
    {
        $payload = $request->getValidatedData();

        try {
            $walletService = app(WalletService::class);
            
            $result = $walletService->processTopup(
                $payload['customer_id'],
                $payload['amount'],
                $payload['description'],
                $payload['reference']
            );

            if ($result['success']) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Account topped up successfully',
                    'data' => $result
                ]);
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => $result['message']
                ]);
            }
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Get customer balance information
     */
    public function getCustomerBalance(int $customerId): JsonResponse
    {
        try {
            $customer = $this->customerService->get($customerId);
            
            // Get account history
            $accountHistory = $customer->account_history()
                ->orderBy('created_at', 'desc')
                ->limit(50)
                ->get();

            // Use the correct operation constants from CustomerAccountHistory
            // Credit operations: add, refund
            // Debit operations: deduct, payment
            $creditOperations = ['add', 'refund'];
            $debitOperations = ['deduct', 'payment'];

            $totalCredited = $customer->account_history()
                ->whereIn('operation', $creditOperations)
                ->sum('amount');

            $totalDebited = $customer->account_history()
                ->whereIn('operation', $debitOperations)
                ->sum('amount');

            // Get orders paid via account wallet
            $ordersPaidViaWallet = [];
            try {
                $ordersPaidViaWallet = $customer->orders()
                    ->where('payment_status', 'paid')
                    ->whereHas('payments', function ($query) {
                        $query->where('identifier', 'account-payment');
                    })
                    ->with(['payments' => function ($query) {
                        $query->where('identifier', 'account-payment');
                    }])
                    ->orderBy('created_at', 'desc')
                    ->limit(20)
                    ->get();
            } catch (\Exception $e) {
                // Silently fail if orders relationship doesn't exist
            }

            return response()->json([
                'status' => 'success',
                'data' => [
                    'customer' => $customer,
                    'current_balance' => (float) $customer->account_amount,
                    'total_credited' => (float) $totalCredited,
                    'total_debited' => (float) abs($totalDebited),
                    'account_history' => $accountHistory,
                    'orders_paid_via_wallet' => $ordersPaidViaWallet
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Update special customer settings
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $request->validate([
            'discount_percentage' => 'nullable|numeric|min:0|max:100',
            'cashback_percentage' => 'nullable|numeric|min:0|max:100',
            'apply_discount_stackable' => 'nullable|boolean'
        ]);

        try {
            if ($request->has('discount_percentage')) {
                $this->specialCustomerService->setDiscountPercentage($request->discount_percentage);
            }

            if ($request->has('cashback_percentage')) {
                $this->specialCustomerService->setCashbackPercentage($request->cashback_percentage);
            }

            if ($request->has('apply_discount_stackable')) {
                $this->specialCustomerService->setDiscountStackable($request->apply_discount_stackable);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Settings updated successfully',
                'data' => $this->specialCustomerService->getConfig()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Show customers list page
     */
    public function customersPage()
    {
        if (!ns()->allowedTo('special.customer.manage')) {
            return redirect()->route('ns.dashboard.home')->with('error', __('You don\'t have permission to access this page.'));
        }
        
        return view('NsSpecialCustomer::customers');
    }

    /**
     * Show settings page
     */
    public function settingsPage()
    {
        if (!ns()->allowedTo('special.customer.settings')) {
            return redirect()->route('ns.dashboard.home')->with('error', __('You don\'t have permission to access this page.'));
        }
        
        return view('NsSpecialCustomer::settings');
    }

    /**
     * Get customers list with special customer status
     */
    public function getCustomersList(Request $request): JsonResponse
    {
        try {
            $specialCustomerService = app(SpecialCustomerService::class);
            $config = $specialCustomerService->getConfig();
            
            $query = Customer::query()
                ->select([
                    'id', 'first_name', 'last_name', 'email', 'phone',
                    'account_amount', 'group_id', 'created_at'
                ])
                ->with(['group:id,name,code']);

            // Search functionality
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                      ->orWhere('last_name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%");
                });
            }

            // Balance filter
            if ($request->has('balance_filter')) {
                $balanceFilter = $request->balance_filter;
                switch ($balanceFilter) {
                    case 'positive':
                        $query->where('account_amount', '>', 0);
                        break;
                    case 'zero':
                        $query->where('account_amount', '=', 0);
                        break;
                    case 'negative':
                        $query->where('account_amount', '<', 0);
                        break;
                }
            }

            // Status filter
            if ($request->has('status_filter')) {
                $statusFilter = $request->status_filter;
                if ($statusFilter === 'special') {
                    $query->where('group_id', $config['groupId']);
                } elseif ($statusFilter === 'regular') {
                    $query->where(function($q) use ($config) {
                        $q->whereNull('group_id')
                          ->orWhere('group_id', '!=', $config['groupId']);
                    });
                }
            }

            // Order by
            $query->orderBy('created_at', 'desc');

            // Pagination
            $perPage = $request->get('per_page', 25);
            $page = $request->get('page', 1);
            $customers = $query->paginate($perPage, ['*'], 'page', $page);

            // Add special customer status and calculate purchases
            $customers->getCollection()->transform(function ($customer) use ($specialCustomerService) {
                $customer->is_special = $specialCustomerService->isSpecialCustomer($customer);
                
                // Calculate total purchases (simplified - in real implementation you'd join with orders)
                $customer->purchases_amount = $this->calculateCustomerPurchases($customer->id);
                
                return $customer;
            });

            return response()->json([
                'status' => 'success',
                'data' => $customers
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calculate customer total purchases
     */
    private function calculateCustomerPurchases(int $customerId): float
    {
        // Simplified calculation - in real implementation you'd query orders
        try {
            // This is a placeholder - you would typically join with orders table
            // For now, return 0 as we don't have the orders relationship set up
            return 0.00;
        } catch (\Exception $e) {
            return 0.00;
        }
    }

    /**
     * Show topup page
     */
    public function topupPage()
    {
        if (!ns()->allowedTo('special.customer.manage')) {
            return redirect()->route('ns.dashboard.home')->with('error', __('You don\'t have permission to access this page.'));
        }
        
        return CustomerTopupCrud::table();
    }

    /**
     * Create topup page
     */
    public function createTopup()
    {
        if (!ns()->allowedTo('special.customer.manage')) {
            return redirect()->route('ns.dashboard.home')->with('error', __('You don\'t have permission to access this page.'));
        }
        
        return CustomerTopupCrud::form();
    }

    /**
     * Show balance page
     */
    public function balancePage($customerId)
    {
        if (!ns()->allowedTo('special.customer.manage')) {
            return redirect()->route('ns.dashboard.home')->with('error', __('You don\'t have permission to access this page.'));
        }
        
        try {
            $customer = $this->customerService->get($customerId);
            $isSpecialCustomer = $this->specialCustomerService->isSpecialCustomer($customer);
            return view('NsSpecialCustomer::balance', compact('customer', 'isSpecialCustomer'));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * Show statistics page
     */
    public function statisticsPage()
    {
        if (!ns()->allowedTo('special.customer.manage')) {
            return redirect()->route('ns.dashboard.home')->with('error', __('You don\'t have permission to access this page.'));
        }
        
        return view('NsSpecialCustomer::statistics');
    }
}
