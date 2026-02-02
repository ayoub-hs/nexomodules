@if(isset($options['specialCustomer']) && $options['specialCustomer']['enabled'])
<div class="special-customer-indicator" id="special-customer-indicator" style="display: none;">
    <div class="alert alert-warning alert-sm mb-2">
        <div class="d-flex align-items-center">
            <i class="fas fa-star me-2"></i>
            <div class="flex-grow-1">
                <strong>Special Customer</strong>
                <div class="small text-muted">
                    Wholesale pricing and {{ $options['specialCustomer']['discountPercentage'] }}% discount applied
                </div>
            </div>
            <div class="text-end">
                <div class="small text-muted">Wallet Balance</div>
                <strong>{{ nsCurrency(order.customer.wallet_balance ?? 0) }}</strong>
            </div>
        </div>
    </div>
</div>

<script>
// Initialize special customer indicator when POS is ready
document.addEventListener('DOMContentLoaded', function() {
    // Watch for customer changes
    if (typeof POS !== 'undefined') {
        // Hook into customer selection
        POS.onCustomerSelected = function(customer) {
            setTimeout(function() {
                updateSpecialCustomerIndicator();
            }, 100);
        };
        
        // Hook into product updates
        POS.onProductAdded = function(product) {
            setTimeout(function() {
                updateSpecialCustomerIndicator();
            }, 100);
        };
    }
});

function updateSpecialCustomerIndicator() {
    const indicator = document.getElementById('special-customer-indicator');
    if (!indicator) return;
    
    const order = typeof POS !== 'undefined' ? (POS.order?.getValue ? POS.order.getValue() : POS.order) : null;
    const specialGroupId = {{ $options['specialCustomer']['groupId'] ?? 'null' }};
    
    if (order && order.customer && specialGroupId) {
        const customerGroupId = order.customer.group_id;
        if (parseInt(customerGroupId) === parseInt(specialGroupId)) {
            indicator.style.display = 'block';
            
            // Update wallet balance
            const walletDisplay = indicator.querySelector('.text-end strong');
            if (walletDisplay) {
                const balance = order.customer.wallet_balance || order.customer.account_amount || 0;
                walletDisplay.textContent = typeof nsCurrency !== 'undefined' ? nsCurrency(balance) : '$' + parseFloat(balance).toFixed(2);
            }
            return;
        }
    }
    indicator.style.display = 'none';
}

// Auto-update indicator every 2 seconds
setInterval(updateSpecialCustomerIndicator, 2000);
</script>
@endif
