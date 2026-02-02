@extends('layout.dashboard')

@section('layout.dashboard.body')
<div class="h-full flex-auto flex flex-col">
    <div class="px-4">
        <h3>Cashback Statistics</h3>
    </div>
    
    <div class="px-4 mt-6">
        <!-- Date Range Filter -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Date Range Filter</h6>
            </div>
            <div class="card-body">
                <form id="stats-filter-form" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Start Date</label>
                        <input type="date" name="start_date" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">End Date</label>
                        <input type="date" name="end_date" class="form-control">
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter"></i> Update Statistics
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Statistics Overview -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card border-left-success shadow h-100 py-2">
                    <div class="card-body">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Total Cashback</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800" id="total-cashback">Loading...</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-left-info shadow h-100 py-2">
                    <div class="card-body">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Total Records</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800" id="total-records">Loading...</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-left-warning shadow h-100 py-2">
                    <div class="card-body">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Unique Customers</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800" id="unique-customers">Loading...</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-left-primary shadow h-100 py-2">
                    <div class="card-body">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Average Cashback</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800" id="average-cashback">Loading...</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top Customers -->
        <div class="row">
            <div class="col-md-12">
                <div class="card shadow">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Top Customers by Cashback Amount</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead class="table-light">
                                    <tr>
                                        <th>Customer</th>
                                        <th>Total Cashback</th>
                                        <th>Records Count</th>
                                    </tr>
                                </thead>
                                <tbody id="top-customers-body">
                                    <tr>
                                        <td colspan="3" class="text-center">Loading...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('layout.dashboard.footer.js')
<script>
document.addEventListener('DOMContentLoaded', function() {
    loadStatistics();

    // Handle filter form submission
    document.getElementById('stats-filter-form').addEventListener('submit', function(e) {
        e.preventDefault();
        loadStatistics();
    });
});

async function loadStatistics() {
    const formData = new FormData(document.getElementById('stats-filter-form'));
    const params = new URLSearchParams();

    if (formData.get('start_date')) {
        params.append('start_date', formData.get('start_date'));
    }
    if (formData.get('end_date')) {
        params.append('end_date', formData.get('end_date'));
    }

    try {
        const response = await fetch(`/api/special-customer/cashback/statistics?${params}`);
        const result = await response.json();
        
        if (result.status === 'success') {
            const stats = result.data;
            
            // Update overview cards
            document.getElementById('total-cashback').textContent = formatCurrency(stats.total_amount);
            document.getElementById('total-records').textContent = stats.total_records;
            document.getElementById('unique-customers').textContent = stats.unique_customers;
            
            // Calculate average
            const average = stats.total_records > 0 ? stats.total_amount / stats.total_records : 0;
            document.getElementById('average-cashback').textContent = formatCurrency(average);
            
            // Update top customers table
            renderTopCustomers(stats.top_customers);
        } else {
            showError('Failed to load statistics');
        }
    } catch (error) {
        console.error('Failed to load statistics:', error);
        showError('Failed to load statistics');
    }
}

function renderTopCustomers(customers) {
    const tbody = document.getElementById('top-customers-body');
    
    if (!customers || customers.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="3" class="text-center">No data available</td>
            </tr>
        `;
        return;
    }

    tbody.innerHTML = customers.map((customer, index) => `
        <tr>
            <td>
                ${customer.customer ? customer.customer.name : 'N/A'}
                ${customer.customer ? `<br><small class="text-muted">${customer.customer.email}</small>` : ''}
            </td>
            <td class="text-success fw-bold">${formatCurrency(customer.total_cashback)}</td>
            <td>
                <span class="badge bg-info">${customer.records_count || 'N/A'}</span>
            </td>
        </tr>
    `).join('');
}

function formatCurrency(amount) {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD'
    }).format(amount);
}

function showError(message) {
    const alertDiv = document.createElement('div');
    alertDiv.className = 'alert alert-danger alert-dismissible fade show';
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    document.querySelector('.px-4').prepend(alertDiv);
}
</script>
@endsection
