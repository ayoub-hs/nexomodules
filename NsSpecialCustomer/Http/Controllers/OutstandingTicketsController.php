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
        if (!ns()->allowedTo('special.customer.pay-outstanding-tickets')) {
            return response()->json([
                'status' => 'error',
                'message' => __('You do not have permission to perform this action.'),
            ], 403);
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
                $payment = [
                    'identifier' => $paymentIdentifier,
                    'value' => $paymentAmount,
                    'register_id' => $order->register_id,
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
                'message' => $exception->getMessage(),
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
}
