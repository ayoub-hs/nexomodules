<div class="dashboard-widget special-customer-widget">
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="fas fa-star text-warning"></i>
                {{ __('Special Customers') }}
            </h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-6">
                    <div class="stat-item">
                        <span class="stat-value" id="sc-customers-count">-</span>
                        <span class="stat-label">{{ __('Customers') }}</span>
                    </div>
                </div>
                <div class="col-6">
                    <div class="stat-item">
                        <span class="stat-value" id="sc-balance-total">-</span>
                        <span class="stat-label">{{ __('Total Balance') }}</span>
                    </div>
                </div>
            </div>
            <div class="mt-3">
                <a href="{{ url('/dashboard/special-customer/customers') }}" class="btn btn-sm btn-primary w-100">
                    {{ __('Manage Customers') }}
                </a>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Load special customer stats
    fetch('/api/special-customer/stats')
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                document.getElementById('sc-customers-count').textContent = data.data.total_customers || 0;
                document.getElementById('sc-balance-total').textContent = ns()->currency->define(data.data.total_balance || 0);
            }
        })
        .catch(error => console.error('Error loading special customer stats:', error));
});
</script>
@endpush

