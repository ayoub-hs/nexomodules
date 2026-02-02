<?php

namespace Modules\NsSpecialCustomer\Services;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Services\OrdersService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\NsSpecialCustomer\Services\SpecialCustomerService;

class OutstandingTicketPaymentService
{
    public function __construct(
        private readonly OrdersService $ordersService,
        private readonly SpecialCustomerService $specialCustomerService,
        private readonly AuditService $auditService
    ) {
    }

    public function payOutstanding(int $customerId, int $orderId, int $authorId, ?float $amount = null): void
    {
        DB::transaction(function () use ($customerId, $orderId, $authorId, $amount) {
            $customer = Customer::query()
                ->where('id', $customerId)
                ->lockForUpdate()
                ->firstOrFail();

            if (!$this->specialCustomerService->isSpecialCustomer($customer)) {
                throw new \RuntimeException(__('Customer is not eligible for special customer payments.'));
            }

            $order = Order::query()
                ->where('id', $orderId)
                ->where('customer_id', $customer->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (!in_array($order->payment_status, [Order::PAYMENT_UNPAID, Order::PAYMENT_PARTIALLY], true)) {
                throw new \RuntimeException(__('This order is not eligible for wallet payment.'));
            }

            $order->load('payments');
            $paidAmount = $order->payments->sum('value');
            $dueAmount = max(0, (float) $order->total - $paidAmount);

            if ($dueAmount <= 0) {
                throw new \RuntimeException(__('This order has no outstanding balance.'));
            }

            // Use provided amount or full due amount
            $paymentAmount = $amount !== null ? min($amount, $dueAmount) : $dueAmount;

            // Validate customer has enough balance
            $customerBalance = (float) $customer->account_amount;
            if ($customerBalance < $paymentAmount) {
                throw new \RuntimeException(
                    sprintf(
                        __('Insufficient wallet balance. Required: %s, Available: %s'),
                        ns()->currency->define($paymentAmount)->format(),
                        ns()->currency->define($customerBalance)->format()
                    )
                );
            }

            $payment = [
                'identifier' => OrderPayment::PAYMENT_ACCOUNT,
                'value' => $paymentAmount,
                'register_id' => $order->register_id,
            ];

            $this->ordersService->makeOrderSinglePayment($payment, $order);

            $this->auditService->logDataAccess('order', $order->id, 'wallet_payment');
            Log::channel('audit')->info('Special customer outstanding ticket paid', [
                'author_id' => $authorId,
                'customer_id' => $customer->id,
                'order_id' => $order->id,
                'amount' => $paymentAmount,
                'payment_identifier' => OrderPayment::PAYMENT_ACCOUNT,
                'timestamp' => now()->toISOString(),
            ]);
        });
    }
}
