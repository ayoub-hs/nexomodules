@extends('layout.dashboard')
@section('layout.dashboard.body')
<div class="h-full flex flex-col pt-8">

    <div class="flex-auto overflow-y-auto ns-scrollbar">
        <div class="ns-container px-6 py-6">

            {{-- Page Header --}}
            <div class="mb-8">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900 mb-2">{{ __('Manufacturing Reports') }}</h1>
                        <p class="text-gray-500">{{ __('Detailed insights into your production performance and material consumption.') }}</p>
                    </div>
                    <div class="flex gap-3">
                        <button class="ns-button secondary" id="refresh-data">
                            <i class="las la-sync-alt mr-2"></i>{{ __('Refresh') }}
                        </button>
                    </div>
                </div>
            </div>

            {{-- Quick Stats Grid --}}
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="bg-white p-6 rounded shadow border-l-4 border-indigo-500">
                    <p class="text-gray-500 text-sm font-medium uppercase tracking-wider">{{ __('Total Produced Value') }}</p>
                    <h3 class="text-3xl font-bold text-gray-900 mt-2" id="stat-total-value">...</h3>
                </div>
                <div class="bg-white p-6 rounded shadow border-l-4 border-green-500">
                     <p class="text-gray-500 text-sm font-medium uppercase tracking-wider">{{ __('Completed Orders') }}</p>
                     <h3 class="text-3xl font-bold text-green-600 mt-2" id="stat-completed">...</h3>
                </div>
                 <div class="bg-white p-6 rounded shadow border-l-4 border-blue-400">
                     <p class="text-gray-500 text-sm font-medium uppercase tracking-wider">{{ __('Ongoing / Pending') }}</p>
                     <h3 class="text-3xl font-bold text-blue-600 mt-2" id="stat-pending">...</h3>
                </div>
                <div class="bg-white p-6 rounded shadow border-l-4 border-gray-400">
                     <p class="text-gray-500 text-sm font-medium uppercase tracking-wider">{{ __('Total Efficiency') }}</p>
                     <h3 class="text-3xl font-bold text-gray-700 mt-2">100%</h3>
                </div>
            </div>

            {{-- Global Filters --}}
            <div class="bg-white p-6 rounded shadow mb-8">
                <div class="flex flex-wrap gap-4 items-end">
                    <div class="flex-1 min-w-[200px]">
                        <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('From Date') }}</label>
                        <input type="date" id="filter-from" class="ns-input w-full">
                    </div>
                    <div class="flex-1 min-w-[200px]">
                        <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('To Date') }}</label>
                        <input type="date" id="filter-to" class="ns-input w-full">
                    </div>
                    <div class="flex-1 min-w-[200px]">
                        <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('Output Product') }}</label>
                        <select id="filter-product" class="ns-input w-full">
                            <option value="">{{ __('All Products') }}</option>
                        </select>
                    </div>
                    <div class="flex gap-2">
                        <button class="ns-button primary" id="btn-apply-filters">
                            <i class="las la-search mr-2"></i>{{ __('Apply') }}
                        </button>
                    </div>
                </div>
            </div>

            {{-- Tabs --}}
            <div class="mb-6">
                <div class="border-b border-gray-200">
                    <nav class="flex space-x-8" aria-label="Tabs">
                        <button class="tab-btn active px-4 py-3 border-b-2 border-indigo-500 text-indigo-600 font-medium text-sm" data-tab="history">
                            <i class="las la-history mr-2"></i>{{ __('Production History') }}
                        </button>
                        <button class="tab-btn px-4 py-3 border-b-2 border-transparent text-gray-500 hover:text-gray-700 font-medium text-sm" data-tab="consumption">
                            <i class="las la-flask mr-2"></i>{{ __('Ingredient Consumption') }}
                        </button>
                    </nav>
                </div>
            </div>

            {{-- Tab Content: History --}}
            <div id="tab-history" class="tab-content block">
                <div class="bg-white rounded shadow overflow-hidden">
                    <table class="w-full text-left">
                        <thead class="bg-gray-50 text-gray-500 text-xs font-semibold uppercase">
                            <tr>
                                <th class="px-6 py-4">{{ __('Code') }}</th>
                                <th class="px-6 py-4">{{ __('Date') }}</th>
                                <th class="px-6 py-4">{{ __('Product') }}</th>
                                <th class="px-6 py-4 text-center">{{ __('Qty') }}</th>
                                <th class="px-6 py-4 text-right">{{ __('Value') }}</th>
                                <th class="px-6 py-4 text-center">{{ __('Status') }}</th>
                            </tr>
                        </thead>
                        <tbody id="history-table-body" class="divide-y divide-gray-100">
                            {{-- Content loaded by JS --}}
                        </tbody>
                    </table>
                    <div class="px-6 py-4 bg-gray-50 flex justify-between items-center text-sm" id="history-pagination">
                        {{-- Pagination loaded by JS --}}
                    </div>
                </div>
            </div>

            {{-- Tab Content: Consumption --}}
            <div id="tab-consumption" class="tab-content hidden">
                <div class="bg-white rounded shadow overflow-hidden">
                    <table class="w-full text-left">
                        <thead class="bg-gray-50 text-gray-500 text-xs font-semibold uppercase">
                            <tr>
                                <th class="px-6 py-4">{{ __('Ingredient') }}</th>
                                <th class="px-6 py-4 text-center">{{ __('Unit') }}</th>
                                <th class="px-6 py-4 text-right">{{ __('Total Quantity') }}</th>
                                <th class="px-6 py-4 text-right">{{ __('Total Cost') }}</th>
                            </tr>
                        </thead>
                        <tbody id="consumption-table-body" class="divide-y divide-gray-100">
                            {{-- Content loaded by JS --}}
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</div>

<style>
    .tab-btn.active { border-color: #6366f1; color: #4f46e5; }
    .tab-content.hidden { display: none; }
</style>

<script>
let currentHistoryPage = 1;

document.addEventListener('DOMContentLoaded', function() {
    loadFilters();
    loadAllReports();

    // Tab Switching
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.onclick = function() {
            document.querySelectorAll('.tab-btn').forEach(b => {
                b.classList.remove('active', 'border-indigo-500', 'text-indigo-600');
                b.classList.add('border-transparent', 'text-gray-500');
            });
            this.classList.add('active', 'border-indigo-500', 'text-indigo-600');
            this.classList.remove('border-transparent', 'text-gray-500');

            document.querySelectorAll('.tab-content').forEach(c => c.classList.add('hidden'));
            document.getElementById('tab-' + this.dataset.tab).classList.remove('hidden');
        }
    });

    document.getElementById('btn-apply-filters').onclick = () => {
        currentHistoryPage = 1;
        loadAllReports();
    };

    document.getElementById('refresh-data').onclick = loadAllReports;
});

function loadFilters() {
    fetch('{{ ns()->url("/dashboard/manufacturing/reports/filters") }}')
        .then(r => r.json())
        .then(data => {
            const select = document.getElementById('filter-product');
            data.products.forEach(p => {
                const opt = document.createElement('option');
                opt.value = p.id;
                opt.textContent = p.name;
                select.appendChild(opt);
            });
        });
}

function loadAllReports() {
    loadSummary();
    loadHistory();
    loadConsumption();
}

function getQuery(params = {}) {
    const from = document.getElementById('filter-from').value;
    const to = document.getElementById('filter-to').value;
    const product = document.getElementById('filter-product').value;
    
    let url = `?from=${from}&to=${to}&product_id=${product}`;
    for (let key in params) {
        url += `&${key}=${params[key]}`;
    }
    return url;
}

function loadSummary() {
    fetch('{{ ns()->url("/dashboard/manufacturing/reports/summary") }}' + getQuery())
        .then(r => r.json())
        .then(data => {
            document.getElementById('stat-total-value').textContent = data.total_value_formatted;
            document.getElementById('stat-completed').textContent = data.completed_orders;
            document.getElementById('stat-pending').textContent = data.pending_orders;
        });
}

function loadHistory() {
    const tbody = document.getElementById('history-table-body');
    tbody.innerHTML = '<tr><td colspan="6" class="px-6 py-10 text-center text-gray-400 italic">{{ __("Loading...") }}</td></tr>';

    fetch('{{ ns()->url("/dashboard/manufacturing/reports/history") }}' + getQuery({ page: currentHistoryPage }))
        .then(r => {
            if (!r.ok) throw new Error('Network response was not ok');
            return r.json();
        })
        .then(res => {
            tbody.innerHTML = '';
            if (res.data.length === 0) {
                const tr = document.createElement('tr');
                const td = document.createElement('td');
                td.colSpan = 6;
                td.className = 'px-6 py-10 text-center text-gray-400 italic';
                td.textContent = '{{ __("No records found.") }}';
                tr.appendChild(td);
                tbody.appendChild(tr);
                return;
            }

            res.data.forEach(order => {
                const statusColor = order.status_raw === 'completed' ? 'text-green-600 bg-green-50' : 'text-blue-600 bg-blue-50';
                
                const tr = document.createElement('tr');
                
                const tdCode = document.createElement('td');
                tdCode.className = 'px-6 py-4 font-medium text-blue-600';
                tdCode.textContent = order.code;
                tr.appendChild(tdCode);
                
                const tdDate = document.createElement('td');
                tdDate.className = 'px-6 py-4 text-xs text-gray-500';
                tdDate.textContent = order.date;
                tr.appendChild(tdDate);
                
                const tdProduct = document.createElement('td');
                tdProduct.className = 'px-6 py-4 text-sm font-medium text-gray-800';
                tdProduct.textContent = order.product;
                tr.appendChild(tdProduct);
                
                const tdQuantity = document.createElement('td');
                tdQuantity.className = 'px-6 py-4 text-center text-sm font-bold';
                tdQuantity.textContent = order.quantity;
                tr.appendChild(tdQuantity);
                
                const tdValue = document.createElement('td');
                tdValue.className = 'px-6 py-4 text-right font-mono text-sm';
                tdValue.textContent = order.value;
                tr.appendChild(tdValue);
                
                const tdStatus = document.createElement('td');
                tdStatus.className = 'px-6 py-4 text-center';
                const spanStatus = document.createElement('span');
                spanStatus.className = 'px-2 py-1 rounded text-[10px] font-bold uppercase ' + statusColor;
                spanStatus.textContent = order.status;
                tdStatus.appendChild(spanStatus);
                tr.appendChild(tdStatus);
                
                tbody.appendChild(tr);
            });
            renderHistoryPagination(res);
        })
        .catch(error => {
            console.error('Error:', error);
            tbody.innerHTML = '';
            const tr = document.createElement('tr');
            const td = document.createElement('td');
            td.colSpan = 6;
            td.className = 'px-6 py-10 text-center text-red-500';
            td.textContent = '{{ __("Error loading data. Please try again.") }}';
            tr.appendChild(td);
            tbody.appendChild(tr);
        });
}

function renderHistoryPagination(res) {
    const pag = document.getElementById('history-pagination');
    pag.innerHTML = `
        <span>{{ __('Showing page') }} ${res.current_page} {{ __('of') }} ${res.last_page}</span>
        <div class="flex gap-2">
            <button class="ns-button secondary text-xs" ${res.current_page <= 1 ? 'disabled' : ''} onclick="changeHistoryPage(${currentHistoryPage - 1})">{{ __('Prev') }}</button>
            <button class="ns-button secondary text-xs" ${res.current_page >= res.last_page ? 'disabled' : ''} onclick="changeHistoryPage(${currentHistoryPage + 1})">{{ __('Next') }}</button>
        </div>
    `;
}

function changeHistoryPage(page) {
    currentHistoryPage = page;
    loadHistory();
}

function loadConsumption() {
    const tbody = document.getElementById('consumption-table-body');
    tbody.innerHTML = '<tr><td colspan="4" class="px-6 py-10 text-center text-gray-400 italic">{{ __("Loading...") }}</td></tr>';

    fetch('{{ ns()->url("/dashboard/manufacturing/reports/consumption") }}' + getQuery())
        .then(r => {
            if (!r.ok) throw new Error('Network response was not ok');
            return r.json();
        })
        .then(res => {
            tbody.innerHTML = '';
            if (res.data.length === 0) {
                const tr = document.createElement('tr');
                const td = document.createElement('td');
                td.colSpan = 4;
                td.className = 'px-6 py-10 text-center text-gray-400 italic';
                td.textContent = '{{ __("No materials consumed in this period.") }}';
                tr.appendChild(td);
                tbody.appendChild(tr);
                return;
            }

            res.data.forEach(item => {
                const tr = document.createElement('tr');
                
                const tdIngredient = document.createElement('td');
                tdIngredient.className = 'px-6 py-4 text-sm font-medium text-gray-800';
                tdIngredient.textContent = item.ingredient;
                tr.appendChild(tdIngredient);
                
                const tdUnit = document.createElement('td');
                tdUnit.className = 'px-6 py-4 text-center text-xs text-gray-500';
                tdUnit.textContent = item.unit;
                tr.appendChild(tdUnit);
                
                const tdQuantity = document.createElement('td');
                tdQuantity.className = 'px-6 py-4 text-right font-bold text-gray-700';
                tdQuantity.textContent = item.quantity;
                tr.appendChild(tdQuantity);
                
                const tdCost = document.createElement('td');
                tdCost.className = 'px-6 py-4 text-right font-mono text-sm text-indigo-600';
                tdCost.textContent = item.total_cost;
                tr.appendChild(tdCost);
                
                tbody.appendChild(tr);
            });
        })
        .catch(error => {
            console.error('Error:', error);
            tbody.innerHTML = '';
            const tr = document.createElement('tr');
            const td = document.createElement('td');
            td.colSpan = 4;
            td.className = 'px-6 py-10 text-center text-red-500';
            td.textContent = '{{ __("Error loading data. Please try again.") }}';
            tr.appendChild(td);
            tbody.appendChild(tr);
        });
}
</script>
@endsection
