<script>
/**
 * Special Customer Module - Component Registration
 * This script registers the Outstanding Ticket Payment Popup component
 */
(function() {
    'use strict';

    console.log('[NsSpecialCustomer] Initializing component registration...');

    // Define the Outstanding Ticket Payment Popup Component
    const OutstandingTicketPopup = {
        template: `
            <div class="ns-box shadow-lg w-[95vw] md:w-[500px] bg-white">
                <div class="popup-heading ns-box-header flex justify-between items-center p-3 border-b">
                    <h3 class="font-bold text-lg">{{ __('Pay Outstanding Ticket') }}</h3>
                    <div>
                        <ns-close-button @click="closePopup()"></ns-close-button>
                    </div>
                </div>
                <div class="popup-body p-4">
                    <div v-if="isLoading" class="flex justify-center items-center py-8">
                        <ns-spinner />
                    </div>
                    <template v-else>
                        <div class="mb-4 bg-gray-50 p-3 rounded border">
                            <div class="flex justify-between mb-2">
                                <span class="text-sm font-medium text-gray-500">{{ __('Order Code') }}</span>
                                <span class="font-bold">@{{ order?.code }}</span>
                            </div>
                            <div class="flex justify-between mb-2">
                                <span class="text-sm font-medium text-gray-500">{{ __('Customer') }}</span>
                                <span class="text-sm">@{{ customerName }}</span>
                            </div>
                            <div class="flex justify-between items-center border-t pt-2 mt-2">
                                <span class="font-bold text-gray-700">{{ __('Due Amount') }}</span>
                                <span class="text-xl font-bold text-red-600">@{{ formattedDueAmount }}</span>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('Payment Amount') }}</label>
                            <input 
                                type="number" 
                                v-model="paymentAmount"
                                :max="order?.due_amount"
                                min="0.01"
                                step="0.01"
                                class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500"
                                :class="{'border-red-500': paymentAmount > customerBalance}"
                            />
                            <div class="flex justify-between mt-1 text-xs text-gray-500">
                                <span>{{ __('Min: 0.01') }}</span>
                                <span>{{ __('Max:') }} @{{ formatCurrency(order?.due_amount || 0) }}</span>
                            </div>
                        </div>

                        <div class="mb-4 p-3 bg-blue-50 border border-blue-200 rounded">
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-sm text-blue-800">{{ __('Wallet Balance') }}</span>
                                <span class="font-bold text-lg" :class="hasEnoughBalance ? 'text-green-600' : 'text-orange-500'">
                                    @{{ formatCurrency(customerBalance) }}
                                </span>
                            </div>
                            <div v-if="!hasEnoughBalance && paymentAmount > customerBalance" class="text-xs text-orange-600 mt-1 font-medium">
                                <i class="las la-exclamation-circle"></i> {{ __('Insufficient balance for full payment. You can make a partial payment.') }}
                            </div>
                            <div v-if="hasEnoughBalance" class="text-xs text-green-600 mt-1 font-medium">
                                <i class="las la-check-circle"></i> {{ __('Sufficient balance available.') }}
                            </div>
                        </div>
                    </template>
                </div>
                <div class="popup-footer ns-box-footer flex justify-end gap-2 p-3 border-t bg-gray-50">
                    <ns-button type="error" @click="closePopup()">{{ __('Cancel') }}</ns-button>
                    <ns-button
                        type="success"
                        :disabled="isSubmitting || !canSubmit"
                        @click="submitPayment()">
                        <span v-if="isSubmitting"><i class="las la-spinner la-spin"></i> {{ __('Processing...') }}</span>
                        <span v-else>{{ __('Pay with Wallet') }}</span>
                    </ns-button>
                </div>
            </div>
        `,
        // IMPORTANT: 'row' is passed automatically by the CRUD Action
        props: ['row', 'popup'],
        data() {
            return {
                order: null,
                isSubmitting: false,
                isLoading: false,
                customerBalance: 0,
                paymentAmount: 0,
            };
        },
        computed: {
            formattedDueAmount() {
                return this.formatCurrency(this.order?.due_amount || 0);
            },
            customerName() {
                if (!this.order?.customer) return '{{ __("Unknown") }}';
                return `${this.order.customer.first_name || ''} ${this.order.customer.last_name || ''}`.trim();
            },
            hasEnoughBalance() {
                const balance = parseFloat(this.customerBalance || 0);
                const amount = parseFloat(this.paymentAmount || 0);
                return balance >= amount;
            },
            canSubmit() {
                if (this.isLoading) return false;
                const amount = parseFloat(this.paymentAmount || 0);
                const balance = parseFloat(this.customerBalance || 0);
                const due = parseFloat(this.order?.due_amount || 0);
                
                // Must have valid amount
                if (amount <= 0) return false;
                
                // Amount cannot exceed due amount
                if (amount > due) return false;
                
                // Amount cannot exceed balance
                if (amount > balance) return false;
                
                return true;
            }
        },
        mounted() {
            console.log('[OutstandingTicketPopup] Component mounted', {
                row: this.row,
                popup: this.popup
            });
            this.loadTicketData();
        },
        methods: {
            closePopup() {
                console.log('[OutstandingTicketPopup] Closing popup');
                this.$emit('close');
                if (this.popup && typeof this.popup.close === 'function') {
                    this.popup.close();
                }
            },
            formatCurrency(amount) {
                return window.nsCurrency ? window.nsCurrency(amount) : amount;
            },
            loadTicketData() {
                this.isLoading = true;
                // Support both CRUD Action (row.id) and Manual JS (popup.params.ticketId)
                const ticketId = this.row?.id || this.popup?.params?.ticketId;
                
                console.log('[OutstandingTicketPopup] Loading ticket data', { ticketId });
                
                if (!ticketId) {
                    console.error('[OutstandingTicketPopup] Ticket ID is missing');
                    window.nsSnackBar.error('{{ __("Ticket ID is missing") }}').subscribe();
                    this.closePopup();
                    return;
                }

                // Fetch fresh order data from API
                window.nsHttpClient.get('/api/crud/ns.outstanding-tickets/' + ticketId)
                    .subscribe({
                        next: (response) => {
                            console.log('[OutstandingTicketPopup] Order data loaded', response);
                            this.order = response;
                            
                            // Calculate due amount if not present
                            if (!this.order.due_amount) {
                                const total = parseFloat(this.order.total || 0);
                                const tendered = parseFloat(this.order.tendered || 0);
                                this.order.due_amount = Math.max(0, total - tendered);
                            }

                            // Set default payment amount to due amount
                            this.paymentAmount = this.order.due_amount;

                            this.loadCustomerBalance();
                            this.isLoading = false;
                        },
                        error: (error) => {
                            console.error('[OutstandingTicketPopup] Failed to load order', error);
                            window.nsSnackBar.error('{{ __("Failed to load ticket details") }}').subscribe();
                            this.isLoading = false;
                            this.closePopup();
                        }
                    });
            },
            loadCustomerBalance() {
                if (!this.order?.customer_id) {
                    console.warn('[OutstandingTicketPopup] No customer ID found');
                    return;
                }
                
                console.log('[OutstandingTicketPopup] Loading customer balance', { customerId: this.order.customer_id });
                
                window.nsHttpClient.get('/api/special-customer/balance/' + this.order.customer_id)
                    .subscribe({
                        next: (response) => {
                            console.log('[OutstandingTicketPopup] Balance loaded', response);
                            // Handle nested response structure: response.data.current_balance
                            if (response.data && response.data.current_balance !== undefined) {
                                this.customerBalance = parseFloat(response.data.current_balance);
                            } else if (response.balance !== undefined) {
                                this.customerBalance = parseFloat(response.balance);
                            } else if (response.current_balance !== undefined) {
                                this.customerBalance = parseFloat(response.current_balance);
                            } else {
                                this.customerBalance = 0;
                            }
                            console.log('[OutstandingTicketPopup] Customer balance set to:', this.customerBalance);
                        },
                        error: (error) => {
                            console.error('[OutstandingTicketPopup] Failed to load balance', error);
                            this.customerBalance = 0;
                        }
                    });
            },
            submitPayment() {
                if (!this.canSubmit) {
                    console.warn('[OutstandingTicketPopup] Cannot submit - validation failed');
                    return;
                }
                
                console.log('[OutstandingTicketPopup] Submitting payment', {
                    orderId: this.order.id,
                    customerId: this.order.customer_id
                });
                
                this.isSubmitting = true;
                
                // Use the new pay-with-method endpoint that supports partial payments
                window.nsHttpClient.post('/api/special-customer/outstanding-tickets/pay-with-method', {
                    order_id: this.order.id,
                    customer_id: this.order.customer_id,
                    amount: parseFloat(this.paymentAmount),
                    payment_method: 'wallet'
                }).subscribe({
                    next: (response) => {
                        console.log('[OutstandingTicketPopup] Payment successful', response);
                        this.isSubmitting = false;
                        window.nsSnackBar.success(response.message || '{{ __("Payment processed successfully") }}').subscribe();
                        this.closePopup();
                        
                        // Refresh CRUD Table
                        if (window.nsCrud) {
                            console.log('[OutstandingTicketPopup] Refreshing CRUD table');
                            window.nsCrud.refresh();
                        } else {
                            console.log('[OutstandingTicketPopup] Reloading page');
                            window.location.reload();
                        }
                    },
                    error: (error) => {
                        console.error('[OutstandingTicketPopup] Payment failed', error);
                        this.isSubmitting = false;
                        const msg = error.response?.data?.message || error.message || '{{ __("Payment failed") }}';
                        window.nsSnackBar.error(msg).subscribe();
                    }
                });
            }
        }
    };

    // Ensure the global component registry exists
    if (typeof window.nsExtraComponents === 'undefined') {
        console.log('[NsSpecialCustomer] Creating nsExtraComponents registry');
        window.nsExtraComponents = {};
    }

    // Register the component with the exact name expected by OutstandingTicketCrud.php
    // The CRUD uses: ->action(..., component: 'nsOutstandingTicketPayment')
    // NexoPOS expects components to be plain objects, not async components
    window.nsExtraComponents['nsOutstandingTicketPayment'] = OutstandingTicketPopup;

    console.log('[NsSpecialCustomer] Component "nsOutstandingTicketPayment" registered successfully');
    console.log('[NsSpecialCustomer] Available components:', Object.keys(window.nsExtraComponents));
})();
</script>
