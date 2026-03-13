<?php

namespace Modules\NsSpecialCustomer\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\PaymentType;
use App\Services\OrdersService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\NsSpecialCustomer\Services\OutstandingTicketPaymentService;
use Modules\NsSpecialCustomer\Services\SpecialCustomerService;
use Modules\NsSpecialCustomer\Services\WalletService;

class OutstandingTicketsController extends Controller
{
    public function __construct(
        private readonly SpecialCustomerService $specialCustomerService,
        private readonly OutstandingTicketPaymentService $paymentService,
        private readonly OrdersService $ordersService,
        private readonly WalletService $walletService
    ) {
    }

    public function index(Request $request): View|RedirectResponse
    {
        if (!ns()->allowedTo('special.customer.manage') && !ns()->allowedTo('special.customer.pay-outstanding-tickets')) {
            return redirect()->route('ns.dashboard.home')
                ->with('error', __('You do not have permission to access this page.'));
        }

        $groupId = $this->specialCustomerService->getSpecialGroupId();
        $customers = Customer::query()
            ->when($groupId, function ($query) use ($groupId) {
                $query->where('group_id', $groupId);
            })
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();

        $selectedCustomer = null;
        $orders = collect();

        if ($request->filled('customer_id')) {
            $selectedCustomer = $customers->firstWhere('id', (int) $request->customer_id);

            if ($selectedCustomer) {
                $orders = Order::query()
                    ->with('payments')
                    ->where('customer_id', $selectedCustomer->id)
                    ->whereIn('payment_status', [
                        Order::PAYMENT_UNPAID,
                        Order::PAYMENT_PARTIALLY,
                    ])
                    ->orderByDesc('created_at')
                    ->get()
                    ->map(function (Order $order) {
                        $paidAmount = $order->payments->sum('value');
                        $order->paid_amount = $paidAmount;
                        $order->due_amount = max(0, (float) $order->total - $paidAmount);

                        return $order;
                    })
                    ->filter(fn (Order $order) => $order->due_amount > 0)
                    ->values();
            }
        }

        return view('NsSpecialCustomer::outstanding-tickets', [
            'customers' => $customers,
            'selectedCustomer' => $selectedCustomer,
            'orders' => $orders,
        ]);
    }

    public function pay(Request $request): RedirectResponse
    {
        if (!ns()->allowedTo('special.customer.pay-outstanding-tickets')) {
            return redirect()->route('ns.dashboard.home')
                ->with('error', __('You do not have permission to access this page.'));
        }

        $validated = $request->validate([
            'customer_id' => 'required|integer|exists:nexopos_users,id',
            'order_id' => 'required|integer|exists:nexopos_orders,id',
        ]);

        try {
            $this->paymentService->payOutstanding(
                customerId: (int) $validated['customer_id'],
                orderId: (int) $validated['order_id'],
                authorId: (int) auth()->id()
            );

            return redirect()
                ->route('ns.dashboard.special-customer-outstanding', [
                    'customer_id' => $validated['customer_id'],
                ])
                ->with('success', __('Outstanding ticket paid successfully.'));
        } catch (\Throwable $exception) {
            return redirect()
                ->route('ns.dashboard.special-customer-outstanding', [
                    'customer_id' => $validated['customer_id'],
                ])
                ->with('error', $exception->getMessage());
        }
    }

    /**
     * Pay outstanding ticket with a specific payment method
     * Supports: cash, credit_card, bank_transfer, wallet
     */
    public function payWithMethod(Request $request): JsonResponse
    {
        if (! $this->hasOutstandingTicketAccess()) {
            return $this->forbiddenResponse();
        }

        $validated = $request->validate([
            'order_id' => 'required|integer|exists:nexopos_orders,id',
            'customer_id' => 'required|integer|exists:nexopos_users,id',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|string|in:cash,credit_card,bank_transfer,wallet',
            'reference' => 'nullable|string|max:255',
        ]);

        try {
            $order = Order::findOrFail($validated['order_id']);
            $customer = Customer::findOrFail($validated['customer_id']);

            if (! $this->specialCustomerService->isSpecialCustomer($customer)) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('Outstanding ticket not found.'),
                ], 404);
            }

            // Verify order belongs to customer
            if ($order->customer_id !== $customer->id) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('Order does not belong to this customer.'),
                ], 400);
            }

            // Check if order is eligible for payment
            if (!in_array($order->payment_status, [Order::PAYMENT_UNPAID, Order::PAYMENT_PARTIALLY], true)) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('This order is not eligible for payment.'),
                ], 400);
            }

            // Calculate due amount
            $order->load('payments');
            $paidAmount = $order->payments->sum('value');
            $dueAmount = max(0, (float) $order->total - $paidAmount);

            if ($dueAmount <= 0) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('This order has no outstanding balance.'),
                ], 400);
            }

            $paymentAmount = min((float) $validated['amount'], $dueAmount);
            $paymentMethod = $validated['payment_method'];

            // Handle wallet payment differently
            if ($paymentMethod === 'wallet') {
                if (!$this->specialCustomerService->isSpecialCustomer($customer)) {
                    return response()->json([
                        'status' => 'error',
                        'message' => __('Customer is not eligible for wallet payments.'),
                    ], 400);
                }

                // Get wallet balance before payment
                $walletBalanceBefore = (float) $customer->account_amount;

                $this->paymentService->payOutstanding(
                    customerId: $customer->id,
                    orderId: $order->id,
                    authorId: (int) auth()->id(),
                    amount: $paymentAmount
                );

                // Refresh customer to get new balance
                $customer->refresh();
                $walletBalanceAfter = (float) $customer->account_amount;

                return response()->json([
                    'status' => 'success',
                    'message' => __('Payment processed successfully.'),
                    'data' => [
                        'order_id' => $order->id,
                        'amount_paid' => $paymentAmount,
                        'payment_method' => $paymentMethod,
                        'wallet_balance_before' => $walletBalanceBefore,
                        'wallet_balance_after' => $walletBalanceAfter,
                    ],
                ]);
            } else {
                // Map payment method to identifier
                $paymentIdentifier = match ($paymentMethod) {
                    'cash' => OrderPayment::PAYMENT_CASH,
                    'credit_card' => OrderPayment::PAYMENT_CREDIT_CARD,
                    'bank_transfer' => OrderPayment::PAYMENT_BANK_TRANSFER,
                    default => OrderPayment::PAYMENT_CASH,
                };

                // Create payment using OrdersService
                $payment = [
                    'identifier' => $paymentIdentifier,
                    'value' => $paymentAmount,
                ];

                // Add reference if provided
                if (!empty($validated['reference'])) {
                    $payment['reference'] = $validated['reference'];
                }

                $this->ordersService->makeOrderSinglePayment($payment, $order);

                return response()->json([
                    'status' => 'success',
                    'message' => __('Payment processed successfully.'),
                    'data' => [
                        'order_id' => $order->id,
                        'amount_paid' => $paymentAmount,
                        'payment_method' => $paymentMethod,
                    ],
                ]);
            }
        } catch (\Throwable $exception) {
            return response()->json([
                'status' => 'error',
                'message' => __('Unable to process the outstanding ticket payment.'),
            ], 500);
        }
    }

    /**
     * Show payment page for an outstanding order
     */
    public function payment($order): View|RedirectResponse
    {
        if (!ns()->allowedTo('special.customer.pay-outstanding-tickets')) {
            return redirect()->route('ns.dashboard.home')
                ->with('error', __('You do not have permission to access this page.'));
        }

        // Resolve order from route parameter
        $order = Order::find($order);

        if (! $order instanceof Order) {
            return redirect()->route('ns.dashboard.special-customer-outstanding')
                ->with('error', __('Order not found.'));
        }

        $order->load('customer');
        $order->load('payments');
        $order->load('products');

        // Calculate due amount
        $paidAmount = $order->payments->sum('value');
        $dueAmount = max(0, (float) $order->total - $paidAmount);

        // If order is already paid, redirect to receipt
        if ($dueAmount <= 0) {
            return redirect( ns()->url('/dashboard/orders/receipt/' . $order->id) )
 ->with('info', __('This order is already fully paid.'));
        }

        // Get active payment types
        $paymentTypes = PaymentType::orderBy('priority', 'asc')
            ->active()
            ->get()
            ->map(function ($payment) {
                return [
                    'identifier' => $payment->identifier,
                    'label' => $payment->label,
                    'priority' => $payment->priority,
                ];
            });

        return view('NsSpecialCustomer::payment', [
            'order' => $order,
            'dueAmount' => $dueAmount,
            'paidAmount' => $paidAmount,
            'paymentTypes' => $paymentTypes,
            'title' => sprintf(__('Order Payment — %s'), $order->code),
        ]);
    }

    // ==================== Mobile API Endpoints ====================

    /**
     * GET /api/mobile/special-customer/tickets
     * List all outstanding tickets for mobile
     */
    public function indexMobile(Request $request): JsonResponse
    {
        if (! $this->hasOutstandingTicketAccess()) {
            return $this->forbiddenResponse();
        }

        try {
            $customerId = $request->input('customer_id');
            $status = $request->input('status'); // 'unpaid' or 'partially_paid'
            $limit = min((int) $request->input('limit', 50), 100);

            $groupId = $this->specialCustomerService->getSpecialGroupId();

            // Handle case where special customer group is not configured
            if (!$groupId) {
                return response()->json([
                    'status' => 'success',
                    'data' => [],
                    'meta' => [
                        'count' => 0,
                        'message' => 'Special customer group not configured',
                    ],
                ]);
            }

            $query = Order::with(['customer:id,first_name,last_name,email,phone', 'payments'])
                ->select([
                    'id',
                    'code',
                    'customer_id',
                    'total',
                    'subtotal',
                    'payment_status',
                    'created_at',
                    'updated_at',
                ])
                ->whereIn('payment_status', [Order::PAYMENT_UNPAID, Order::PAYMENT_PARTIALLY])
                ->orderByDesc('created_at')
                ->limit($limit);

            // Filter by customer
            if ($customerId) {
                $query->where('customer_id', (int) $customerId);
            }

            // Filter by special customer group
            $query->whereHas('customer', function ($q) use ($groupId) {
                $q->where('group_id', $groupId);
            });

            $orders = $query->get();

            $tickets = $orders->map(function ($order) {
                // Ensure payments are loaded
                if (!$order->relationLoaded('payments')) {
                    $order->load('payments');
                }
                
                $paidAmount = $order->payments->sum('value');
                $dueAmount = max(0, (float) $order->total - $paidAmount);

                // Handle created_at - it might be a string due to the select() in the query
                $createdAt = $order->created_at;
                if (is_string($createdAt)) {
                    $createdAt = \Carbon\Carbon::parse($createdAt)->toIso8601String();
                } elseif ($createdAt instanceof \Carbon\Carbon) {
                    $createdAt = $createdAt->toIso8601String();
                }

                return [
                    'id' => $order->id,
                    'code' => $order->code,
                    'customer_id' => $order->customer_id,
                    'customer' => $order->customer ? [
                        'id' => $order->customer->id,
                        'name' => $order->customer->first_name . ' ' . $order->customer->last_name,
                        'email' => $order->customer->email,
                        'phone' => $order->customer->phone,
                    ] : null,
                    'total' => (float) $order->total,
                    'paid_amount' => (float) $paidAmount,
                    'due_amount' => (float) $dueAmount,
                    'payment_status' => $order->payment_status,
                    'created_at' => $createdAt,
                ];
            })->filter(fn ($ticket) => $ticket['due_amount'] > 0)->values();

            // Filter by status if specified
            if ($status) {
                $tickets = $tickets->filter(fn ($ticket) => $ticket['payment_status'] === $status)->values();
            }

            return response()->json([
                'status' => 'success',
                'data' => $tickets,
                'meta' => [
                    'count' => $tickets->count(),
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('Unable to retrieve outstanding tickets.'),
                'data' => [],
                'meta' => [
                    'count' => 0,
                ],
            ], 500);
        }
    }

    /**
     * GET /api/mobile/special-customer/tickets/{id}
     * Show single outstanding ticket for mobile
     */
    public function showMobile(Request $request, int $id): JsonResponse
    {
        if (! $this->hasOutstandingTicketAccess()) {
            return $this->forbiddenResponse();
        }

        try {
            $order = Order::with([
                'customer:id,first_name,last_name,email,phone',
                'payments',
                'products.product.unit',
            ])->findOrFail($id);
            $customer = $order->customer instanceof Customer
                ? $order->customer
                : Customer::find($order->customer_id);

            if (! $customer instanceof Customer || ! $this->specialCustomerService->isSpecialCustomer($customer)) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('Ticket not found'),
                ], 404);
            }

            $paidAmount = $order->payments->sum('value');
            $dueAmount = max(0, (float) $order->total - $paidAmount);

            $data = [
                'id' => $order->id,
                'code' => $order->code,
                'customer_id' => $order->customer_id,
                'customer' => $order->customer ? [
                    'id' => $order->customer->id,
                    'name' => $order->customer->first_name . ' ' . $order->customer->last_name,
                    'email' => $order->customer->email,
                    'phone' => $order->customer->phone,
                ] : null,
                'total' => (float) $order->total,
                'paid_amount' => (float) $paidAmount,
                'due_amount' => (float) $dueAmount,
                'payment_status' => $order->payment_status,
                'created_at' => $order->created_at->toIso8601String(),
                'payments' => $order->payments->map(fn ($payment) => [
                    'id' => $payment->id,
                    'identifier' => $payment->identifier,
                    'value' => (float) $payment->value,
                    'created_at' => $payment->created_at->toIso8601String(),
                ])->toArray(),
                'products' => $order->products->map(fn ($product) => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'quantity' => (float) $product->quantity,
                    'unit_price' => (float) $product->unit_price,
                    'total_price' => (float) $product->total_price,
                ])->toArray(),
            ];

            return response()->json([
                'status' => 'success',
                'data' => $data,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Ticket not found',
            ], 404);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('Failed to retrieve ticket details'),
            ], 500);
        }
    }

    /**
     * POST /api/mobile/special-customer/tickets/{id}/pay
     * Pay outstanding ticket for mobile
     */
    public function payMobile(Request $request, int $id): JsonResponse
    {
        if (! $this->hasOutstandingTicketAccess()) {
            return $this->forbiddenResponse();
        }

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|string|in:cash,credit_card,bank_transfer,wallet',
            'reference' => 'nullable|string|max:255',
        ]);

        try {
            $order = Order::with('payments')->findOrFail($id);
            $customer = Customer::findOrFail($order->customer_id);

            if (! $this->specialCustomerService->isSpecialCustomer($customer)) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('Outstanding ticket not found.'),
                ], 404);
            }

            // Check if order is eligible for payment
            if (!in_array($order->payment_status, [Order::PAYMENT_UNPAID, Order::PAYMENT_PARTIALLY], true)) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('This order is not eligible for payment.'),
                ], 400);
            }

            // Calculate due amount
            $paidAmount = $order->payments->sum('value');
            $dueAmount = max(0, (float) $order->total - $paidAmount);

            if ($dueAmount <= 0) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('This order has no outstanding balance.'),
                ], 400);
            }

            $paymentAmount = min((float) $validated['amount'], $dueAmount);
            $paymentMethod = $validated['payment_method'];

            // Handle wallet payment differently
            if ($paymentMethod === 'wallet') {
                if (!$this->specialCustomerService->isSpecialCustomer($customer)) {
                    return response()->json([
                        'status' => 'error',
                        'message' => __('Customer is not eligible for wallet payments.'),
                    ], 400);
                }

                $this->paymentService->payOutstanding(
                    customerId: $customer->id,
                    orderId: $order->id,
                    authorId: (int) auth()->id(),
                    amount: $paymentAmount
                );
            } else {
                // Map payment method to identifier
                $paymentIdentifier = match ($paymentMethod) {
                    'cash' => OrderPayment::PAYMENT_CASH,
                    'credit_card' => OrderPayment::PAYMENT_CREDIT_CARD,
                    'bank_transfer' => OrderPayment::PAYMENT_BANK_TRANSFER,
                    default => OrderPayment::PAYMENT_CASH,
                };

                // Create payment using OrdersService
                // For remote payments, omit register_id to bypass register check
                $payment = [
                    'identifier' => $paymentIdentifier,
                    'value' => $paymentAmount,
                ];

                // Add reference if provided
                if (!empty($validated['reference'])) {
                    $payment['reference'] = $validated['reference'];
                }

                $this->ordersService->makeOrderSinglePayment($payment, $order);
            }

            return response()->json([
                'status' => 'success',
                'message' => __('Payment processed successfully.'),
                'data' => [
                    'order_id' => $order->id,
                    'amount_paid' => $paymentAmount,
                    'payment_method' => $paymentMethod,
                ],
            ]);
        } catch (\Throwable $exception) {
            return response()->json([
                'status' => 'error',
                'message' => __('Unable to process the outstanding ticket payment.'),
            ], 500);
        }
    }

    /**
     * POST /api/mobile/special-customer/tickets/{id}/pay-from-wallet
     * Pay outstanding ticket from customer's wallet balance for mobile
     */
    public function payFromWalletMobile(Request $request, int $id): JsonResponse
    {
        if (! $this->hasOutstandingTicketAccess()) {
            return $this->forbiddenResponse();
        }

        $validated = $request->validate([
            'amount' => 'nullable|numeric|min:0.01',
        ]);

        try {
            $order = Order::with('payments')->findOrFail($id);
            $customer = Customer::findOrFail($order->customer_id);

            // Check if customer is special customer
            if (!$this->specialCustomerService->isSpecialCustomer($customer)) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('Customer is not a special customer.'),
                ], 400);
            }

            // Check if order is eligible for payment
            if (!in_array($order->payment_status, [Order::PAYMENT_UNPAID, Order::PAYMENT_PARTIALLY], true)) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('This order is not eligible for payment.'),
                ], 400);
            }

            // Calculate due amount
            $paidAmount = $order->payments->sum('value');
            $dueAmount = max(0, (float) $order->total - $paidAmount);

            if ($dueAmount <= 0) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('This order has no outstanding balance.'),
                ], 400);
            }

            // Use full due amount if not specified
            $paymentAmount = isset($validated['amount']) 
                ? min((float) $validated['amount'], $dueAmount) 
                : $dueAmount;

            // Check wallet balance
            $walletBalance = (float) $customer->account_amount;
            if ($walletBalance < $paymentAmount) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('Insufficient wallet balance. Available: :balance', ['balance' => $walletBalance]),
                ], 400);
            }

            // Process payment from wallet
            $this->paymentService->payOutstanding(
                customerId: $customer->id,
                orderId: $order->id,
                authorId: $this->getAuthorId(),
                amount: $paymentAmount
            );

            return response()->json([
                'status' => 'success',
                'message' => __('Payment processed successfully from wallet.'),
                'data' => [
                    'order_id' => $order->id,
                    'amount_paid' => $paymentAmount,
                    'payment_method' => 'wallet',
                    'remaining_wallet_balance' => $walletBalance - $paymentAmount,
                ],
            ]);
        } catch (\Throwable $exception) {
            return response()->json([
                'status' => 'error',
                'message' => __('Unable to process the wallet payment.'),
            ], 500);
        }
    }

    /**
     * Get author ID for mobile auth
     */
    private function getAuthorId(): int
    {
        // For mobile auth, use auth()->id() like the existing payMobile method
        return (int) auth()->id();
    }

    private function hasOutstandingTicketAccess(): bool
    {
        return ns()->allowedTo('special.customer.manage')
            || ns()->allowedTo('special.customer.pay-outstanding-tickets');
    }

    private function forbiddenResponse(): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => __('You do not have permission to perform this action.'),
        ], 403);
    }
}
