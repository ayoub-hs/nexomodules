@extends('layout.dashboard')

@section('layout.dashboard.body')
<div class="h-full flex-auto flex flex-col">
    <div class="px-4">
        <h3>Cashback History</h3>
    </div>
    
    <div class="px-4 mt-6">
        <!-- Filters -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Filters</h6>
            </div>
            <div class="card-body">
                <form id="filter-form" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Customer</label>
                        <select name="customer_id" class="form-select">
                            <option value="">All Customers</option>
                            @if(isset($customers))
                                @foreach($customers as $customer)
                                    <option value="{{ $customer->id }}">{{ $customer->name }}</option>
                                @endforeach
                            @endif
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Start Date</label>
                        <input type="date" name="start_date" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">End Date</label>
                        <input type="date" name="end_date" class="form-control">
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter"></i> Filter
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Statistics -->
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
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Actions</div>
                        <a href="{{ route('special.customer.cashback.create') }}" class="btn btn-success btn-sm">
                            <i class="fas fa-plus"></i> Process Cashback
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Cashback History Table -->
        <div class="card shadow">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Cashback Records</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered" id="cashback-table">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Customer</th>
                                <th>Amount</th>
                                <th>Percentage</th>
                                <th>Period</th>
                                <th>Initiator</th>
                                <th>Description</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="cashback-table-body">
                            <tr>
                                <td colspan="9" class="text-center">Loading...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <div id="pagination" class="mt-3">
                    <!-- Pagination will be rendered here -->
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('layout.dashboard.footer.js')
<script>
let currentPage = 1;
const perPage = 25;

document.addEventListener('DOMContentLoaded', function() {
    loadCashbackHistory();
    loadStatistics();

    // Handle filter form submission
    document.getElementById('filter-form').addEventListener('submit', function(e) {
        e.preventDefault();
        currentPage = 1;
        loadCashbackHistory();
        loadStatistics();
    });
});

async function loadCashbackHistory() {
    const formData = new FormData(document.getElementById('filter-form'));
    const params = new URLSearchParams({
        per_page: perPage,
        page: currentPage
    });

    // Add filter parameters
    if (formData.get('customer_id')) {
        params.append('customer_id', formData.get('customer_id'));
    }
    if (formData.get('start_date')) {
        params.append('start_date', formData.get('start_date'));
    }
    if (formData.get('end_date')) {
        params.append('end_date', formData.get('end_date'));
    }

    try {
        const response = await fetch(`/api/special-customer/cashback?${params}`);
        const result = await response.json();
        
        if (result.status === 'success') {
            renderCashbackTable(result.data.data);
            renderPagination(result.data);
        } else {
            document.getElementById('cashback-table-body').innerHTML = `
                <tr>
                    <td colspan="9" class="text-center text-danger">
                        Failed to load cashback history
                    </td>
                </tr>
            `;
        }
    } catch (error) {
        console.error('Failed to load cashback history:', error);
        document.getElementById('cashback-table-body').innerHTML = `
            <tr>
                <td colspan="9" class="text-center text-danger">
                    Failed to load cashback history
                </td>
            </tr>
        `;
    }
}

async function loadStatistics() {
    const formData = new FormData(document.getElementById('filter-form'));
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
            document.getElementById('total-cashback').textContent = formatCurrency(stats.total_amount);
            document.getElementById('total-records').textContent = stats.total_records;
            document.getElementById('unique-customers').textContent = stats.unique_customers;
        }
    } catch (error) {
        console.error('Failed to load statistics:', error);
    }
}

function renderCashbackHistory(cashbacks) {
    const tbody = document.getElementById('cashback-table-body');
    
    if (cashbacks.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="9" class="text-center">No cashback records found</td>
            </tr>
        `;
        return;
    }

    tbody.innerHTML = cashbacks.map(cashback => `
        <tr>
            <td>${cashback.id}</td>
            <td>
                ${cashback.customer ? cashback.customer.name : 'N/A'}
                ${cashback.customer ? `<br><small class="text-muted">${cashback.customer.email}</small>` : ''}
            </td>
            <td class="text-success fw-bold">${formatCurrency(cashback.amount)}</td>
            <td>${cashback.percentage}%</td>
            <td>${cashback.period_start} to ${cashback.period_end}</td>
            <td>
                <span class="badge bg-info">${cashback.initiator}</span>
            </td>
            <td style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">${cashback.description || '-'}</td>
            <td>${new Date(cashback.created_at).toLocaleDateString()}</td>
            <td>
                <button onclick="deleteCashback(${cashback.id})" class="btn btn-sm btn-danger">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        </tr>
    `).join('');
}

function renderPagination(paginationData) {
    const pagination = document.getElementById('pagination');
    
    if (paginationData.last_page <= 1) {
        pagination.innerHTML = '';
        return;
    }

    let paginationHtml = '<nav><ul class="pagination justify-content-center">';
    
    // Previous button
    if (paginationData.current_page > 1) {
        paginationHtml += `
            <li class="page-item">
                <a class="page-link" href="#" onclick="goToPage(${paginationData.current_page - 1}); return false;">Previous</a>
            </li>
        `;
    }

    // Page numbers
    for (let i = 1; i <= paginationData.last_page; i++) {
        const isActive = i === paginationData.current_page;
        paginationHtml += `
            <li class="page-item ${isActive ? 'active' : ''}">
                <a class="page-link" href="#" onclick="goToPage(${i}); return false;">${i}</a>
            </li>
        `;
    }

    // Next button
    if (paginationData.current_page < paginationData.last_page) {
        paginationHtml += `
            <li class="page-item">
                <a class="page-link" href="#" onclick="goToPage(${paginationData.current_page + 1}); return false;">Next</a>
            </li>
        `;
    }

    paginationHtml += '</ul></nav>';
    pagination.innerHTML = paginationHtml;
}

function goToPage(page) {
    currentPage = page;
    loadCashbackHistory();
}

async function deleteCashback(id) {
    if (!confirm('Are you sure you want to delete this cashback record? This will also reverse the transaction.')) {
        return;
    }

    try {
        const response = await fetch(`/api/special-customer/cashback/${id}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        });

        const result = await response.json();
        
        if (result.status === 'success') {
            showAlert('Cashback record deleted successfully', 'success');
            loadCashbackHistory();
            loadStatistics();
        } else {
            showAlert(result.message || 'Failed to delete cashback record', 'danger');
        }
    } catch (error) {
        console.error('Failed to delete cashback record:', error);
        showAlert('Failed to delete cashback record', 'danger');
    }
}

function formatCurrency(amount) {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD'
    }).format(amount);
}

function showAlert(message, type) {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    document.querySelector('.px-4').prepend(alertDiv);
}
</script>
@endsection
