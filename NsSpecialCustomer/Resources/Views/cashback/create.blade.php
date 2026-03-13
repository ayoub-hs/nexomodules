@extends('layout.dashboard')

@section('layout.dashboard.body')
<div class="h-full flex flex-col flex-auto">
    @include(Hook::filter('ns-dashboard-header-file', '../common/dashboard-header'))
    <div class="px-4 flex-auto flex flex-col" id="dashboard-content">
        @include('common.dashboard.title', [
            'title' => __('Process Cashback'),
            'description' => __('Create a new cashback entry for a special customer.'),
        ])
        <ns-crud-form 
            return-url="{{ url('/dashboard/special-customer/cashback') }}"
            submit-url="{{ url('/api/crud/ns.special-customer-cashback') }}"
            src="{{ url('/api/crud/ns.special-customer-cashback/form-config') }}">
            <template v-slot:title>Process Cashback</template>
            <template v-slot:save>Process Cashback</template>
        </ns-crud-form>
    </div>
</div>
@endsection

@section('layout.dashboard.footer.inject')
<script>
(function () {
    const HOOK_ID = 'ns-special-customer-cashback-create-autofill';

    function getField(form, name) {
        if (!form || !form.tabs) {
            return null;
        }

        for (const tabKey of Object.keys(form.tabs)) {
            const fields = form.tabs[tabKey]?.fields || [];
            const match = fields.find(field => field.name === name);
            if (match) {
                return match;
            }
        }

        if (form.main && form.main.name === name) {
            return form.main;
        }

        return null;
    }

    function attachAutoFill(crudForm) {
        if (!crudForm || crudForm.__nsSpecialCashbackAutoFillAttached) {
            return;
        }

        crudForm.__nsSpecialCashbackAutoFillAttached = true;

        let lastKey = null;
        let pendingRequestKey = null;
        let debounceTimer = null;

        const run = () => {
            const customerField = getField(crudForm.form, 'customer_id');
            const yearField = getField(crudForm.form, 'year');
            const totalPurchasesField = getField(crudForm.form, 'total_purchases');
            const totalRefundsField = getField(crudForm.form, 'total_refunds');
            const cashbackPercentageField = getField(crudForm.form, 'cashback_percentage');

            if (!customerField || !yearField || !totalPurchasesField || !totalRefundsField) {
                return;
            }

            const customerId = parseInt(customerField.value, 10);
            const year = parseInt(yearField.value, 10);

            if (!customerId || !year) {
                lastKey = null;
                pendingRequestKey = null;
                return;
            }

            const requestKey = `${customerId}:${year}`;
            if (requestKey === lastKey) {
                return;
            }

            lastKey = requestKey;
            pendingRequestKey = requestKey;

            window.nsHttpClient.get(`/api/special-customer/cashback/calculate?customer_id=${encodeURIComponent(customerId)}&year=${encodeURIComponent(year)}`)
                .subscribe({
                    next: (response) => {
                        if (pendingRequestKey !== requestKey) {
                            return;
                        }

                        const data = response?.data || {};

                        totalPurchasesField.value = data.total_purchases ?? 0;
                        totalRefundsField.value = data.total_refunds ?? 0;

                        if (cashbackPercentageField && (cashbackPercentageField.value === '' || cashbackPercentageField.value === null || cashbackPercentageField.value === undefined)) {
                            cashbackPercentageField.value = data.cashback_percentage ?? cashbackPercentageField.value;
                        }

                        const excludedAmount = parseFloat(data?.already_cashed_back?.cashback_amount || 0);
                        const excludedPurchases = parseFloat(data?.already_cashed_back?.total_purchases || 0);
                        const shouldNotify = data?.eligible === false || excludedAmount > 0 || excludedPurchases > 0;

                        if (data.reason && shouldNotify) {
                            window.nsSnackBar.info(data.reason, 'OK', { duration: 2500 });
                        }
                    },
                    error: (error) => {
                        if (pendingRequestKey !== requestKey) {
                            return;
                        }

                        lastKey = null;
                        window.nsSnackBar.error(error.message || 'Failed to calculate cashback totals');
                    }
                });
        };

        // Polling is used here because ns-crud-form does not propagate change events for all field types.
        const intervalId = setInterval(() => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(run, 150);
        }, 500);

        // Initial run after fields are mounted
        setTimeout(run, 400);

        window.addEventListener('beforeunload', () => clearInterval(intervalId), { once: true });
    }

    function tryAttachFromDom() {
        const root = document.getElementById('crud-form');
        const vueProxy = root && root.__vueParentComponent && root.__vueParentComponent.proxy
            ? root.__vueParentComponent.proxy
            : null;

        if (vueProxy) {
            attachAutoFill(vueProxy);
            return true;
        }

        return false;
    }

    const subscribe = () => {
        if (typeof window.nsHooks === 'undefined') {
            return;
        }

        window.nsHooks.addAction('ns-crud-form-loaded', HOOK_ID, (crudForm) => {
            attachAutoFill(crudForm);
        });
    };

    let attachAttempts = 0;
    const domAttachTimer = setInterval(() => {
        attachAttempts++;

        if (tryAttachFromDom() || attachAttempts >= 40) {
            clearInterval(domAttachTimer);
        }
    }, 250);

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', subscribe);
    } else {
        subscribe();
    }
})();
</script>
@endsection
