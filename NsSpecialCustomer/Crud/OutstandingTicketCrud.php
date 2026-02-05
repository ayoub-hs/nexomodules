<?php

namespace Modules\NsSpecialCustomer\Crud;

use App\Classes\CrudForm;
use App\Classes\FormInput;
use App\Models\Customer;
use App\Models\Order;
use App\Services\CrudEntry;
use App\Services\CrudService;
use Illuminate\Http\Request;
use Modules\NsSpecialCustomer\Services\OutstandingTicketPaymentService;
use Modules\NsSpecialCustomer\Services\SpecialCustomerService;

/**
 * Outstanding Tickets CRUD Class
 *
 * Provides CRUD interface for managing outstanding (unpaid/partially paid)
 * tickets for special customers with the ability to pay from wallet.
 */
class OutstandingTicketCrud extends CrudService
{
    const IDENTIFIER = 'ns.outstanding-tickets';

    const AUTOLOAD = true;

    protected $table = 'nexopos_orders';

    protected $model = Order::class;

    protected $namespace = 'ns.outstanding-tickets';

    /**
     * Define table configuration
     */
    public function __construct()
    {
        parent::__construct();

        $this->mainRoute = 'dashboard/special-customer/outstanding-tickets';
        $this->permissions = [
            'create' => false, // Cannot create outstanding tickets manually
            'read' => 'special.customer.view',
            'update' => false, // Cannot edit outstanding tickets
            'delete' => false, // Cannot delete outstanding tickets
        ];

        // Disable features not applicable to outstanding tickets
        $this->features = [
            'bulk-actions' => false,
            'single-action' => true,
            'checkboxes' => false,
        ];
    }

    /**
     * Get table columns configuration
     */
    public function getColumns(): array
    {
        return [
            'id' => [
                'label' => __( 'ID' ),
                'width' => '80px',
                '$direction' => 'asc',
            ],
            'code' => [
                'label' => __( 'Order Code' ),
                'width' => '120px',
                'filter' => 'like',
            ],
            'customer_name' => [
                'label' => __( 'Customer' ),
                'width' => '200px',
                'filter' => 'like',
            ],
            'created_at' => [
                'label' => __( 'Date' ),
                'width' => '150px',
            ],
            'total' => [
                'label' => __( 'Total' ),
                'width' => '120px',
                '$direction' => 'desc',
            ],
            'paid_amount' => [
                'label' => __( 'Paid' ),
                'width' => '120px',
            ],
            'due_amount' => [
                'label' => __( 'Due' ),
                'width' => '120px',
                '$direction' => 'desc',
            ],
            'payment_status' => [
                'label' => __( 'Status' ),
                'width' => '120px',
                'filter' => 'exact',
            ],
        ];
    }

    /**
     * Get table entries
     */
    public function getEntries( $config = [] ): array
    {
        $this->allowedTo( 'read' );

        $specialCustomerService = app( SpecialCustomerService::class );
        $specialConfig = $specialCustomerService->getConfig();

        // Query orders with outstanding payments for special customers
        $query = Order::with( ['customer', 'payments'] )
            ->whereIn( 'payment_status', [
                Order::PAYMENT_UNPAID,
                Order::PAYMENT_PARTIALLY,
            ] );

        // Filter by special customer group if configured
        if ( ! empty( $specialConfig['groupId'] ) ) {
            $query->whereHas( 'customer', function ( $q ) use ( $specialConfig ) {
                $q->where( 'group_id', $specialConfig['groupId'] );
            } );
        }

        // Apply filters
        if ( isset( $config['filter'] ) ) {
            foreach ( $config['filter'] as $key => $value ) {
                if ( $key === 'customer_name' ) {
                    $query->whereHas( 'customer', function ( $q ) use ( $value ) {
                        $q->where( 'first_name', 'like', "%{$value}%" )
                            ->orWhere( 'last_name', 'like', "%{$value}%" );
                    } );
                } elseif ( $key === 'code' ) {
                    $query->where( 'code', 'like', "%{$value}%" );
                } elseif ( $key === 'payment_status' ) {
                    $query->where( 'payment_status', $value );
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

        if ( $perPage > 0 ) {
            $entries = $query->paginate( $perPage, ['*'], 'page', $page );
        } else {
            $entries = $query->get();
        }

        // Use parent method to handle proper CrudEntry creation and actions
        $result = parent::getEntries( $config );

        // Override the data with our filtered results
        $data = $entries instanceof \Illuminate\Pagination\LengthAwarePaginator
            ? $entries->getCollection()
            : $entries;

        $result['data'] = $data->map( function ( $entry ) {
            // Ensure we have a model object, not just an ID
            if ( is_numeric( $entry ) ) {
                $entry = Order::find( $entry );
            }

            if ( ! $entry ) {
                return null;
            }

            // Calculate paid amount from payments
            $paidAmount = $entry->payments->sum( 'value' );
            $dueAmount = max( 0, (float) $entry->total - $paidAmount );

            // Convert to array first to ensure all fields are available
            $entryArray = $entry->toArray();

            // Ensure required fields exist
            if ( ! isset( $entryArray['id'] ) ) {
                $entryArray['id'] = $entry->id;
            }

            // Add customer name
            $entryArray['customer_name'] = $entry->customer
                ? $entry->customer->first_name . ' ' . $entry->customer->last_name
                : __( 'Unknown' );

            // Add calculated fields
            $entryArray['paid_amount'] = $paidAmount;
            $entryArray['due_amount'] = $dueAmount;

            $crudEntry = new CrudEntry( $entryArray );

            // Format currency fields
            $crudEntry->formatted_total = ns()->currency->define( $entry->total );
            $crudEntry->formatted_paid_amount = ns()->currency->define( $paidAmount );
            $crudEntry->formatted_due_amount = ns()->currency->define( $dueAmount );

            // Format payment status
            $crudEntry->payment_status_label = $this->getPaymentStatusLabel( $entry->payment_status );

            // Apply actions
            $this->setActions( $crudEntry );

            return $crudEntry;
        } )->filter()->values()->toArray();

        // Update pagination info
        $result['total'] = $entries instanceof \Illuminate\Pagination\LengthAwarePaginator ? $entries->total() : count( $entries );
        $result['per_page'] = $perPage;
        $result['current_page'] = $page;
        $result['last_page'] = $entries instanceof \Illuminate\Pagination\LengthAwarePaginator ? $entries->lastPage() : 1;

        return $result;
    }

    /**
     * Get a single entry by ID
     */
    public function getEntry( $id )
    {
        $this->allowedTo( 'read' );

        $order = Order::with( ['customer', 'payments'] )->findOrFail( $id );

        // Calculate paid and due amounts
        $paidAmount = $order->payments->sum( 'value' );
        $dueAmount = max( 0, (float) $order->total - $paidAmount );

        // Convert to array
        $entry = $order->toArray();

        // Add customer name
        $entry['customer_name'] = $order->customer
            ? $order->customer->first_name . ' ' . $order->customer->last_name
            : __( 'Unknown' );

        // Add calculated fields
        $entry['paid_amount'] = $paidAmount;
        $entry['due_amount'] = $dueAmount;

        // Format currency fields
        $entry['formatted_total'] = ns()->currency->define( $order->total );
        $entry['formatted_paid_amount'] = ns()->currency->define( $paidAmount );
        $entry['formatted_due_amount'] = ns()->currency->define( $dueAmount );

        // Format payment status
        $entry['payment_status_label'] = $this->getPaymentStatusLabel( $order->payment_status );

        return $entry;
    }

    /**
     * Get payment status label
     */
    private function getPaymentStatusLabel( string $status ): string
    {
        return match ( $status ) {
            Order::PAYMENT_PAID => __( 'Paid' ),
            Order::PAYMENT_PARTIALLY => __( 'Partially Paid' ),
            Order::PAYMENT_UNPAID => __( 'Unpaid' ),
            Order::PAYMENT_HOLD => __( 'On Hold' ),
            Order::PAYMENT_VOID => __( 'Void' ),
            Order::PAYMENT_REFUNDED => __( 'Refunded' ),
            Order::PAYMENT_PARTIALLY_REFUNDED => __( 'Partially Refunded' ),
            default => $status,
        };
    }

    /**
     * Define actions for each entry
     */
    public function setActions( CrudEntry $entry ): CrudEntry
    {
        // Format currency fields for display
        $entry->formatted_total = ns()->currency->define( $entry->total );
        $entry->formatted_paid_amount = ns()->currency->define( $entry->paid_amount ?? 0 );
        $entry->formatted_due_amount = ns()->currency->define( $entry->due_amount ?? 0 );

        $dueAmount = $entry->due_amount ?? 0;
        $hasPermission = ns()->allowedTo( 'special.customer.pay-outstanding-tickets' );

        // Add "Pay From Wallet" action if customer is special and has due amount
        if ( $dueAmount > 0 && $hasPermission && ! empty( $entry->customer_id ) ) {
            $customer = Customer::find( $entry->customer_id );
            $specialCustomerService = app( SpecialCustomerService::class );
            if ( $customer && $specialCustomerService->isSpecialCustomer( $customer ) ) {
                $entry->action(
                    label: __( 'Pay From Wallet' ),
                    identifier: 'pay_from_wallet',
                    url: ns()->url( '/dashboard/special-customer/outstanding-tickets/payment/' . $entry->id ),
                    type: 'POPUP'
                );
                $entry->values['$actions']['pay_from_wallet']['component'] = 'nsOutstandingTicketPayment';
            }
        }


        // Add view order action
        $entry->action(
            identifier: 'view_order',
            label: __( 'View Order' ),
            url: ns()->url( "/dashboard/orders/receipt/{$entry->id}" )
        );

        return $entry;
    }

    /**
     * Get form configuration
     * Outstanding tickets are read-only, so this returns minimal form
     */
    public function getForm( $entry = null ): array
    {
        return CrudForm::form(
            main: FormInput::text(
                name: 'code',
                label: __( 'Order Code' ),
                value: $entry?->code ?? '',
                disabled: true,
                description: __( 'Order code cannot be edited.' )
            ),
            tabs: CrudForm::tabs(
                CrudForm::tab(
                    label: __( 'Order Information' ),
                    identifier: 'info',
                    fields: CrudForm::fields(
                        FormInput::text(
                            name: 'customer_name',
                            label: __( 'Customer' ),
                            value: $entry?->customer_name ?? '',
                            disabled: true
                        ),
                        FormInput::text(
                            name: 'total',
                            label: __( 'Total' ),
                            value: $entry?->total ?? '',
                            disabled: true
                        ),
                        FormInput::text(
                            name: 'paid_amount',
                            label: __( 'Paid Amount' ),
                            value: $entry?->paid_amount ?? '',
                            disabled: true
                        ),
                        FormInput::text(
                            name: 'due_amount',
                            label: __( 'Due Amount' ),
                            value: $entry?->due_amount ?? '',
                            disabled: true
                        ),
                        FormInput::text(
                            name: 'payment_status',
                            label: __( 'Payment Status' ),
                            value: $this->getPaymentStatusLabel( $entry?->payment_status ?? '' ),
                            disabled: true
                        )
                    )
                )
            )
        );
    }

    /**
     * Pay outstanding ticket from customer wallet
     */
    public function payTicket( Request $request ): array
    {
        $this->allowedTo( 'read' ); // Uses read permission as base, but checks pay permission in controller

        $validated = $request->validate( [
            'customer_id' => 'required|integer|exists:nexopos_users,id',
            'order_id' => 'required|integer|exists:nexopos_orders,id',
        ] );

        $paymentService = app( OutstandingTicketPaymentService::class );

        try {
            $paymentService->payOutstanding(
                customerId: (int) $validated['customer_id'],
                orderId: (int) $validated['order_id'],
                authorId: (int) auth()->id()
            );

            return [
                'status' => 'success',
                'message' => __( 'Outstanding ticket paid successfully.' ),
            ];
        } catch ( \Throwable $exception ) {
            return [
                'status' => 'error',
                'message' => $exception->getMessage(),
            ];
        }
    }
}
