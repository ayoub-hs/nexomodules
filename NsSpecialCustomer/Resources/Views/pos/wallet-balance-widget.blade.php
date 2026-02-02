@if(isset($options['specialCustomer']) && $options['specialCustomer']['enabled'])
<div class="wallet-balance-widget" id="wallet-balance-widget" style="display: none;">
    <div class="card border-info">
        <div class="card-header bg-info text-white py-2">
            <i class="fas fa-wallet me-2"></i>
            <strong>Customer Wallet</strong>
        </div>
        <div class="card-body p-3">
            <div class="row align-items-center">
                <div class="col">
                    <div class="small text-muted">Available Balance</div>
                    <div class="h4 mb-0 text-primary" id="wallet-balance-amount">
                        {{ nsCurrency(0) }}
                    </div>
                </div>
                <div class="col-auto">
                    <button class="btn btn-sm btn-outline-primary" @click="refreshWalletBalance()" title="Refresh Balance">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                </div>
            </div>
            @if($options['specialCustomer']['cashbackPercentage'] > 0)
            <div class="mt-2 pt-2 border-top">
                <div class="small text-muted">
                    <i class="fas fa-percentage me-1"></i>
                    {{ $options['specialCustomer']['cashbackPercentage'] }}% cashback on purchases
                </div>
            </div>
            @endif
        </div>
    </div>
</div>

<script>
function refreshWalletBalance() {
    const order = typeof POS !== 'undefined' ? POS.getOrder() : null;
    if (!order || !order.customer || !order.customer.id) return;
    
    // Fetch latest balance from API
    if (typeof nsHttpClient !== 'undefined') {
        nsHttpClient.get(`/api/special-customer/balance/${order.customer.id}`)
            .then(response => {
                const balance = response.data.data.current_balance || 0;
                const balanceElement = document.getElementById('wallet-balance-amount');
                if (balanceElement) {
                    balanceElement.textContent = typeof nsCurrency !== 'undefined' ? nsCurrency(balance) : '$' + parseFloat(balance).toFixed(2);
                }
            })
            .catch(error => {
                console.error('Error fetching wallet balance:', error);
            });
    }
}

// Update wallet balance display
function updateWalletBalanceDisplay() {
    const widget = document.getElementById('wallet-balance-widget');
    if (!widget) return;
    
    const order = typeof POS !== 'undefined' ? (POS.order?.getValue ? POS.order.getValue() : POS.order) : null;
    const specialGroupId = {{ $options['specialCustomer']['groupId'] ?? 'null' }};
    
    if (order && order.customer && specialGroupId) {
        const customerGroupId = order.customer.group_id;
        if (parseInt(customerGroupId) === parseInt(specialGroupId)) {
            widget.style.display = 'block';
            
            const balance = order.customer.wallet_balance || order.customer.account_amount || 0;
            const balanceElement = document.getElementById('wallet-balance-amount');
            if (balanceElement) {
                balanceElement.textContent = typeof nsCurrency !== 'undefined' ? nsCurrency(balance) : '$' + parseFloat(balance).toFixed(2);
            }
            return;
        }
    }
    widget.style.display = 'none';
}

// Initialize wallet balance widget
document.addEventListener('DOMContentLoaded', function() {
    // Update wallet balance when customer changes
    if (typeof POS !== 'undefined') {
        const originalSetCustomer = POS.setCustomer;
        POS.setCustomer = function(customer) {
            const result = originalSetCustomer.apply(this, arguments);
            setTimeout(updateWalletBalanceDisplay, 100);
            return result;
        };
    }
    
    // Initial update
    setTimeout(updateWalletBalanceDisplay, 500);
    
    // Auto-refresh every 30 seconds
    setInterval(updateWalletBalanceDisplay, 30000);
});

// Make refresh function globally available
window.refreshWalletBalance = refreshWalletBalance;
</script>
@endif
