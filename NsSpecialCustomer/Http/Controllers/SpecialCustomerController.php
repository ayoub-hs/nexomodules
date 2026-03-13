<?php

namespace Modules\NsSpecialCustomer\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Modules\NsSpecialCustomer\Services\SpecialCustomerService;
use Modules\NsSpecialCustomer\Services\WalletService;
use App\Services\CustomerService;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;
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
                        'total_due' => 0,
                        'pending_cashback' => 0
                    ]
                ]);
            }
            
            // Count special customers and their total wallet balance
            $customers = Customer::where('group_id', $groupId)->get();
            $totalCustomers = $customers->count();
            $totalBalance = $customers->sum('account_amount');
            
            // Calculate total due from unpaid/partially paid orders for special customers
            // Using the same logic as SpecialCustomerCrud: SUM(total) - SUM(payments.value)
            $customerIds = $customers->pluck('id');
            
            $totalDue = 0;
            if ($customerIds->isNotEmpty()) {
                // Get orders that are not fully paid (unpaid or partially_paid)
                $unpaidOrders = \App\Models\Order::whereIn('customer_id', $customerIds)
                    ->whereIn('payment_status', ['unpaid', 'partially_paid'])
                    ->with('payments')
                    ->get();
                
                // Calculate due for each order: total - payments sum
                foreach ($unpaidOrders as $order) {
                    $paidAmount = $order->payments->sum('value');
                    $totalDue += ($order->total - $paidAmount);
                }
            }
            
            return response()->json([
                'status' => 'success',
                'data' => [
                    'total_customers' => $totalCustomers,
                    'total_balance' => $totalBalance,
                    'total_due' => $totalDue,
                    'pending_cashback' => 0
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('Unable to load special customer statistics.')
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
                'message' => __('Customer not found.')
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

        // Read top-up metadata directly from validated payload
        $description = $payload['description'] ?? null;
        $receivedDate = $payload['received_date'] ?? now()->toDateString();

        try {
            $walletService = app(WalletService::class);

            $result = $walletService->processTopup(
                $payload['customer_id'],
                $payload['amount'],
                $description,
                $payload['reference'],
                $receivedDate
            );

            if ($result['success']) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Account topped up successfully',
                    'data' => [
                        'success' => true,
                        'transaction_id' => $result['transaction_id'] ?? null,
                        'customer_id' => $payload['customer_id'],
                        'amount' => $payload['amount'],
                        'new_balance' => $result['new_balance'] ?? 0,
                        'received_date' => $receivedDate,
                        'created_at' => now()->toISOString(),
                    ]
                ]);
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => $result['message']
                ], 400);
            }
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('Unable to process the top-up request.')
            ], 500);
        }
    }

    /**
     * Get customer balance information
     */
    public function getCustomerBalance(int $customerId): JsonResponse
    {
        try {
            if (! Customer::query()->whereKey($customerId)->exists()) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('Customer not found.'),
                ], 404);
            }

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

            // Get last topup date and total topups count
            $lastTopup = $customer->account_history()
                ->where('operation', 'add')
                ->orderBy('created_at', 'desc')
                ->first();
            
            $totalTopups = $customer->account_history()
                ->where('operation', 'add')
                ->count();

            // Build customer name
            $customerName = trim($customer->first_name . ' ' . $customer->last_name);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'customer_id' => (int) $customer->id,
                    'customer_name' => $customerName,
                    'balance' => (float) $customer->account_amount,
                    'last_topup_at' => $lastTopup?->created_at?->format('Y-m-d H:i:s'),
                    'total_topups' => (int) $totalTopups,
                    // Extended data for backward compatibility
                    'customer' => $customer,
                    'current_balance' => (float) $customer->account_amount,
                    'total_credited' => (float) $totalCredited,
                    'total_debited' => (float) abs($totalDebited),
                    'account_history' => $accountHistory,
                    'orders_paid_via_wallet' => $ordersPaidViaWallet
                ]
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('Unable to retrieve customer balance.')
            ], 500);
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
                'message' => __('Unable to update special customer settings.')
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

            $groupId = $config['groupId'] ?? null;
            $query = Customer::query()
                ->select([
                    'id', 'first_name', 'last_name', 'email', 'phone',
                    'account_amount', 'group_id', 'created_at'
                ])
                ->with(['group:id,name']);

            if ($groupId) {
                $query->where('group_id', $groupId);
            } else {
                $query->whereRaw('1 = 0');
            }

            // Search functionality
            if ($request->has('search')) {
                $search = str_replace(['%', '_'], ['\%', '\_'], (string) $request->search);
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
                if ($statusFilter === 'special' && $groupId) {
                    $query->where('group_id', $groupId);
                } elseif ($statusFilter === 'regular') {
                    $query->where(function($q) use ($groupId) {
                        $q->whereNull('group_id')
                          ->orWhere('group_id', '!=', $groupId);
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
                $customer->purchases_amount = $this->calculateCustomerPurchases($customer->id);

                return $customer;
            });

            return response()->json([
                'status' => 'success',
                'data' => $customers->toArray()
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('Unable to load special customers.')
            ], 500);
        }
    }

    /**
     * Calculate customer total purchases
     */
    private function calculateCustomerPurchases(int $customerId): float
    {
        try {
            $totalPurchases = DB::table('nexopos_orders')
                ->where('customer_id', $customerId)
                ->where('payment_status', '!=', 'order_void')
                ->sum('total');

            $totalRefunds = DB::table('nexopos_orders_refunds')
                ->join('nexopos_orders', 'nexopos_orders.id', '=', 'nexopos_orders_refunds.order_id')
                ->where('nexopos_orders.customer_id', $customerId)
                ->sum('nexopos_orders_refunds.total');

            return max(0, (float) $totalPurchases - (float) $totalRefunds);
        } catch (\Throwable $e) {
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

    /**
     * Get wallet topups list for mobile app
     */
    public function getWalletTopups(Request $request): JsonResponse
    {
        try {
            $customerId = $request->query('customer_id');
            $limit = $request->query('limit', 50);
            $offset = $request->query('offset', 0);

            // Use CustomerAccountHistory model - topups are records with operation 'add'
            $query = \App\Models\CustomerAccountHistory::query()
                ->where('operation', 'add')
                ->with(['customer:id,first_name,last_name,email'])
                ->orderBy('created_at', 'desc');

            if ($customerId) {
                $query->where('customer_id', $customerId);
            }

            $total = $query->count();
            $topups = $query->skip($offset)->take($limit)->get();

            return response()->json([
                'status' => 'success',
                'data' => [
                    'topups' => $topups,
                    'total' => $total,
                    'limit' => (int) $limit,
                    'offset' => (int) $offset
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('Unable to retrieve wallet top-ups.')
            ], 500);
        }
    }

    /**
     * Get single wallet topup details for mobile app
     */
    public function getWalletTopup(int $id): JsonResponse
    {
        try {
            // Use CustomerAccountHistory model - topups are records with operation 'add'
            $topup = \App\Models\CustomerAccountHistory::with(['customer:id,first_name,last_name,email'])
                ->where('operation', 'add')
                ->find($id);

            if (!$topup) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Topup not found'
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'data' => $topup
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('Unable to retrieve the wallet top-up.')
            ], 500);
        }
    }
}
