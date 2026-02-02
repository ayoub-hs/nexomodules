/**
 * Special Customer Module - Component Registration
 *
 * Registers Vue components for the Special Customer module.
 */
import { defineAsyncComponent } from 'vue';

declare const nsExtraComponents: any;

// Register popup components
nsExtraComponents.nsOutstandingTicketPayment = defineAsyncComponent(
    () => import('./components/NsOutstandingTicketPaymentPopup.vue')
);

// Register hook-based popup component
nsExtraComponents['ns-outstanding-ticket-payment-popup'] = defineAsyncComponent(
    () => import('./components/NsOutstandingTicketPaymentPopup.vue')
);
