<template>
    <div id="outstanding-ticket-payment-popup" class="ns-box shadow-lg w-[95vw] md:w-[500px]">
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
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Order Code') }}</label>
                <div class="text-lg font-semibold">{{ order?.code }}</div>
            </div>
            
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Customer') }}</label>
                <div class="text-md">{{ order?.customer_name }}</div>
            </div>
            
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Due Amount') }}</label>
                <div class="text-xl font-bold text-error-primary">{{ formattedDueAmount }}</div>
            </div>

            <div class="mb-4">
                <ns-field 
                    v-for="(field, index) in fields" 
                    :key="index" 
                    :field="field"
                    @change="handleFieldChange($event, field)">
                </ns-field>
            </div>

            <div v-if="paymentMethod === 'wallet' && customerBalance !== null" class="mb-4 p-3 bg-info-secondary rounded">
                <div class="flex justify-between items-center">
                    <span class="text-sm">{{ __('Customer Wallet Balance') }}</span>
                    <span class="font-bold" :class="hasEnoughBalance ? 'text-success-primary' : 'text-error-primary'">
                        {{ formatCurrency(customerBalance) }}
                    </span>
                </div>
                <div v-if="!hasEnoughBalance" class="text-xs text-error-primary mt-1">
                    {{ __('Insufficient balance. Please choose another payment method.') }}
                </div>
            </div>
            </template>
        </div>
        <div class="popup-footer ns-box-footer flex justify-end gap-2 p-3 border-t">
            <ns-button type="error" @click="closePopup()">{{ __('Cancel') }}</ns-button>
            <ns-button 
                type="success" 
                :disabled="isSubmitting || !canSubmit"
                @click="submitPayment()">
                <span v-if="isSubmitting">{{ __('Processing...') }}</span>
                <span v-else>{{ __('Pay') }}</span>
            </ns-button>
        </div>
    </div>
</template>

<script>
import { __ } from '~/libraries/lang';
import popupResolver from '~/libraries/popup-resolver';
import popupCloser from '~/libraries/popup-closer';
import { nsHttpClient, nsSnackBar } from '~/bootstrap';
import { nsCurrency } from '~/filters/currency';
import FormValidation from '~/libraries/form-validation';

export default {
    name: 'NsOutstandingTicketPaymentPopup',
    props: ['popup'],
    data() {
        return {
            order: null,
            fields: [],
            isSubmitting: false,
            isLoading: false,
            customerBalance: null,
            formValidation: new FormValidation,
            paymentMethod: 'cash',
        };
    },
    computed: {
        formattedDueAmount() {
            return nsCurrency(this.order?.due_amount || 0);
        },
        hasEnoughBalance() {
            return this.customerBalance >= (this.order?.due_amount || 0);
        },
        canSubmit() {
            if (this.paymentMethod === 'wallet') {
                return this.hasEnoughBalance;
            }
            return true;
        }
    },
    mounted() {
        this.popupCloser();
        
        // Support both direct row data and ticketId for fetching
        if (this.popup.params.row) {
            // Row data provided directly
            this.order = this.popup.params.row;
            this.initializeFields();
            
            if (this.order?.customer_id) {
                this.loadCustomerBalance();
            }
        } else if (this.popup.params.ticketId) {
            // Only ticketId provided, fetch order from API
            this.loadOrderById(this.popup.params.ticketId);
        }
    },
    methods: {
        __,
        popupResolver,
        popupCloser,
        nsCurrency,
        formatCurrency(value) {
            return nsCurrency(value);
        },
        
        initializeFields() {
            this.fields = [
                {
                    type: 'number',
                    name: 'amount',
                    label: __('Amount'),
                    value: this.order?.due_amount || 0,
                    disabled: true,
                    description: __('The amount due for this order.'),
                },
                {
                    type: 'select',
                    name: 'payment_method',
                    label: __('Payment Method'),
                    value: 'cash',
                    options: [
                        { label: __('Cash'), value: 'cash' },
                        { label: __('Credit Card'), value: 'credit_card' },
                        { label: __('Bank Transfer'), value: 'bank_transfer' },
                        { label: __('Wallet'), value: 'wallet' },
                    ],
                    description: __('Select the payment method.'),
                },
                {
                    type: 'text',
                    name: 'reference',
                    label: __('Reference / Notes'),
                    value: '',
                    description: __('Optional reference number or notes.'),
                },
            ];
        },
        
        handleFieldChange(event, field) {
            field.value = event;
            
            if (field.name === 'payment_method') {
                this.paymentMethod = event;
            }
        },
        
        loadOrderById(ticketId) {
            this.isLoading = true;
            nsHttpClient.get(`/api/crud/ns.outstanding-tickets/${ticketId}`)
                .subscribe({
                    next: (response) => {
                        this.isLoading = false;
                        this.order = response.data;
                        this.initializeFields();
                        
                        if (this.order?.customer_id) {
                            this.loadCustomerBalance();
                        }
                    },
                    error: (error) => {
                        this.isLoading = false;
                        console.error('Failed to load order:', error);
                        nsSnackBar.error(__('Failed to load order details.')).subscribe();
                        this.closePopup();
                    }
                });
        },
        
        loadCustomerBalance() {
            nsHttpClient.get(`/api/special-customer/balance/${this.order.customer_id}`)
                .subscribe({
                    next: (response) => {
                        this.customerBalance = response.data?.balance || 0;
                    },
                    error: (error) => {
                        console.error('Failed to load customer balance:', error);
                    }
                });
        },
        
        submitPayment() {
            if (!this.canSubmit) {
                nsSnackBar.error(__('Cannot proceed with this payment method.')).subscribe();
                return;
            }
            
            this.isSubmitting = true;
            
            const formData = this.formValidation.extractFields(this.fields);
            
            const payload = {
                order_id: this.order.id,
                customer_id: this.order.customer_id,
                amount: parseFloat(formData.amount),
                payment_method: formData.payment_method,
                reference: formData.reference,
            };
            
            nsHttpClient.post('/api/special-customer/outstanding-tickets/pay-with-method', payload)
                .subscribe({
                    next: (response) => {
                        this.isSubmitting = false;
                        nsSnackBar.success(response.message || __('Payment processed successfully.')).subscribe();
                        this.popupResolver(true);
                        this.popup.close();
                    },
                    error: (error) => {
                        this.isSubmitting = false;
                        const message = error.message || __('Failed to process payment.');
                        nsSnackBar.error(message).subscribe();
                    }
                });
        },
        
        closePopup() {
            this.popupResolver(false);
            this.popup.close();
        }
    }
};
</script>
