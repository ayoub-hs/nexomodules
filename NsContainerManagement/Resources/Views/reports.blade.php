<?php use App\Classes\Hook; ?>

@extends('layout.dashboard')

@section('layout.dashboard.body')
<div class="h-full flex flex-col">

    @include(Hook::filter('ns-dashboard-header-file', '../common/dashboard-header'))

    <div class="flex-auto overflow-y-auto ns-scrollbar">
        <div class="ns-container px-6 py-6">

            {{-- Page Header --}}
            <div class="mb-8">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900 mb-2">Container Management Reports</h1>
                    
                    </div>
                    <div class="flex gap-3">
                        <button class="ns-button secondary" id="refresh-data">
                            <i class="las la-sync-alt mr-2"></i>Refresh
                        </button>
                        <button class="ns-button primary" id="export-csv">
                            <i class="las la-download mr-2"></i>Export CSV
                        </button>
                    </div>
                </div>
            </div>

            {{-- Filters Section --}}
            <div class="ns-box p-6 mb-8">
                
                <div class="flex flex-wrap gap-4 items-end">
                    <div class="flex-1 min-w-[200px]">
                        <label class="block text-sm font-medium text-gray-700 mb-2">From Date</label>
                        <input type="date" id="from-date" class="ns-input w-full">
                    </div>
                    <div class="flex-1 min-w-[200px]">
                        <label class="block text-sm font-medium text-gray-700 mb-2">To Date</label>
                        <input type="date" id="to-date" class="ns-input w-full">
                    </div>
                    <div class="flex-1 min-w-[200px]">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Customer</label>
                        <select id="customer-filter" class="ns-input w-full">
                            <option value="">All Customers</option>
                        </select>
                    </div>
                    <div class="flex-1 min-w-[200px]">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Container Type</label>
                        <select id="type-filter" class="ns-input w-full">
                            <option value="">All Types</option>
                        </select>
                    </div>
                    <div class="flex gap-2">
                        <button class="ns-button primary" id="apply-filters">
                            <i class="las la-search mr-2"></i>Apply
                        </button>
                        <button class="ns-button secondary" id="reset-filters">
                            <i class="las la-undo mr-2"></i>Reset
                        </button>
                    </div>
                </div>
            </div>

            {{-- Summary Cards --}}
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="ns-box p-6 border-l-4 border-red-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600 mb-1">Total Containers Out</p>
                            <h3 class="text-3xl font-bold text-gray-900" id="total-out">
                                <span class="loading-dots">...</span>
                            </h3>
                            <p class="text-xs text-gray-500 mt-2">This period</p>
                        </div>
                        <div class="rounded-full bg-red-100 p-3">
                            <i class="las la-box-open text-2xl text-red-600"></i>
                        </div>
                    </div>
                </div>

                <div class="ns-box p-6 border-l-4 border-green-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600 mb-1">Total Returned</p>
                            <h3 class="text-3xl font-bold text-gray-900" id="total-in">
                                <span class="loading-dots">...</span>
                            </h3>
                            <p class="text-xs text-gray-500 mt-2">This period</p>
                        </div>
                        <div class="rounded-full bg-green-100 p-3">
                            <i class="las la-box text-2xl text-green-600"></i>
                        </div>
                    </div>
                </div>

                <div class="ns-box p-6 border-l-4 border-yellow-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600 mb-1">Outstanding Balance</p>
                            <h3 class="text-3xl font-bold text-gray-900" id="total-balance">
                                <span class="loading-dots">...</span>
                            </h3>
                            <p class="text-xs text-gray-500 mt-2">Active containers</p>
                        </div>
                        <div class="rounded-full bg-yellow-100 p-3">
                            <i class="las la-exclamation-triangle text-2xl text-yellow-600"></i>
                        </div>
                    </div>
                </div>

                <div class="ns-box p-6 border-l-4 border-blue-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600 mb-1">Active Customers</p>
                            <h3 class="text-3xl font-bold text-gray-900" id="active-customers">
                                <span class="loading-dots">...</span>
                            </h3>
                            <p class="text-xs text-gray-500 mt-2">With balances</p>
                        </div>
                        <div class="rounded-full bg-blue-100 p-3">
                            <i class="las la-users text-2xl text-blue-600"></i>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Tabs Navigation --}}
            <div class="ns-box mb-6">
                <div class="border-b border-gray-200">
                    <nav class="flex space-x-8" aria-label="Tabs">
                        <button class="tab-button active" data-tab="movements">
                            <i class="las la-exchange-alt mr-2"></i>
                            Container Movements
                            <span class="tab-count" id="movements-count">0</span>
                        </button>
                        <button class="tab-button" data-tab="balances">
                            <i class="las fa-balance-scale mr-2"></i>
                            Customer Balances
                            <span class="tab-count" id="balances-count">0</span>
                        </button>
                        <button class="tab-button" data-tab="charges">
                            <i class="las la-credit-card mr-2"></i>
                            Charges History
                            <span class="tab-count" id="charges-count">0</span>
                        </button>
                    </nav>
                </div>
            </div>

            {{-- Movements Table --}}
            <div class="tab-content active" id="movements-tab">
                <div class="ns-box">
                    <div class="p-6 border-b border-gray-200 bg-gray-50">
                        <div class="flex items-center justify-between">
                            <h3 class="text-lg font-semibold text-gray-900">
                                <i class="las la-history mr-2"></i>Movement History
                            </h3>
                            <div class="flex items-center gap-4">
                                <span class="text-sm text-gray-500">
                                    Showing <span id="movements-shown">0</span> of <span id="movements-total">0</span>
                                </span>
                                <button class="ns-button secondary text-sm" id="export-movements">
                                    <i class="las la-download mr-1"></i>Export
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50 border-b border-gray-200">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date & Time</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Container Type</th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Direction</th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Quantity</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Source</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Notes</th>
                                </tr>
                            </thead>
                            <tbody id="movements-table" class="bg-white divide-y divide-gray-200">
                                <tr>
                                    <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                                        <i class="las la-spinner la-spin text-2xl mb-2"></i>
                                        <p>Loading movement data...</p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="p-4 border-t border-gray-200 bg-gray-50">
                        <div class="flex items-center justify-between">
                            <div class="text-sm text-gray-700">
                                Page <span id="movements-current-page">1</span> of <span id="movements-last-page">1</span>
                            </div>
                            <div class="flex gap-2">
                                <button class="ns-button secondary" id="prev-movements" disabled>
                                    <i class="las la-chevron-left"></i>
                                </button>
                                <button class="ns-button secondary" id="next-movements" disabled>
                                    <i class="las la-chevron-right"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Balances Table --}}
            <div class="tab-content" id="balances-tab">
                <div class="ns-box">
                    <div class="p-6 border-b border-gray-200 bg-gray-50">
                        <div class="flex items-center justify-between">
                            <h3 class="text-lg font-semibold text-gray-900">
                                <i class="las fa-balance-scale mr-2"></i>Outstanding Customer Balances
                            </h3>
                            <div class="flex items-center gap-4">
                                <span class="text-sm text-gray-500">
                                    Showing <span id="balances-shown">0</span> of <span id="balances-total">0</span>
                                </span>
                                <button class="ns-button secondary text-sm" id="export-balances">
                                    <i class="las la-download mr-1"></i>Export
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50 border-b border-gray-200">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Container Type</th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Balance</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Updated</th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="balances-table" class="bg-white divide-y divide-gray-200">
                                <tr>
                                    <td colspan="5" class="px-6 py-12 text-center text-gray-500">
                                        <i class="las la-spinner la-spin text-2xl mb-2"></i>
                                        <p>Loading balance data...</p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="p-4 border-t border-gray-200 bg-gray-50">
                        <div class="flex items-center justify-between">
                            <div class="text-sm text-gray-700">
                                Page <span id="balances-current-page">1</span> of <span id="balances-last-page">1</span>
                            </div>
                            <div class="flex gap-2">
                                <button class="ns-button secondary" id="prev-balances" disabled>
                                    <i class="las la-chevron-left"></i>
                                </button>
                                <button class="ns-button secondary" id="next-balances" disabled>
                                    <i class="las la-chevron-right"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Charges History Tab --}}
            <div class="tab-content" id="charges-tab">
                <div class="ns-box">
                    <div class="p-6 border-b border-gray-200 bg-gray-50">
                        <div class="flex items-center justify-between">
                            <h3 class="text-lg font-semibold text-gray-900">
                                <i class="las la-credit-card mr-2"></i>Charges History
                            </h3>
                            <div class="flex items-center gap-4">
                                <span class="text-sm text-gray-500">
                                    Showing <span id="charges-shown">0</span> of <span id="charges-total">0</span>
                                </span>
                                <button class="ns-button secondary text-sm" id="export-charges">
                                    <i class="las la-download mr-1"></i>Export
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50 border-b border-gray-200">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date & Time</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Container Type</th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Quantity</th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Notes</th>
                                </tr>
                            </thead>
                            <tbody id="charges-table" class="bg-white divide-y divide-gray-200">
                                <tr>
                                    <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                                        <i class="las la-spinner la-spin text-2xl mb-2"></i>
                                        <p>Loading charge data...</p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="p-4 border-t border-gray-200 bg-gray-50">
                        <div class="flex items-center justify-between">
                            <div class="text-sm text-gray-700">
                                Page <span id="charges-current-page">1</span> of <span id="charges-last-page">1</span>
                            </div>
                            <div class="flex gap-2">
                                <button class="ns-button secondary" id="prev-charges" disabled>
                                    <i class="las la-chevron-left"></i>
                                </button>
                                <button class="ns-button secondary" id="next-charges" disabled>
                                    <i class="las la-chevron-right"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<style>
.tab-button {
    padding: 0.75rem 1rem;
    font-size: 0.875rem;
    font-weight: 500;
    border-bottom: 2px solid transparent;
    color: #6b7280;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    cursor: pointer;
    background: none;
    border-left: none;
    border-right: none;
    border-top: none;
    transition: all 0.2s ease;
}

.tab-button:hover {
    color: #374151;
    border-color: #d1d5db;
}

.tab-button.active {
    color: var(--primary);
    border-color: var(--primary);
}

.tab-count {
    background: #e5e7eb;
    color: #374151;
    padding: 0.125rem 0.5rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 600;
}

.tab-button.active .tab-count {
    background: var(--primary);
    color: #fff;
}

.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
}

.loading-dots::after {
    content: '';
    animation: dots 1.5s steps(4, end) infinite;
}

@keyframes dots {
    0%, 20% { content: ''; }
    40% { content: '.'; }
    60% { content: '..'; }
    80%, 100% { content: '...'; }
}

.direction-badge {
    padding: 0.25rem 0.5rem;
    font-size: 0.75rem;
    font-weight: 600;
    border-radius: 9999px;
}

.direction-out { background: #fee2e2; color: #991b1b; }
.direction-in { background: #dcfce7; color: #166534; }
.direction-charge { background: #fef3c7; color: #92400e; }
.direction-adjustment { background: #e0e7ff; color: #3730a3; }

.balance-positive { color: #dc2626; font-weight: 600; }
.balance-zero { color: #16a34a; font-weight: 600; }



</style>

{{-- Enhanced JavaScript --}}
<script>
let movementsPage = 1;
let balancesPage = 1;
let currentTab = 'movements';

document.addEventListener('DOMContentLoaded', function () {

    // Initialize
    initializeTabs();
    initializeFilters();
    loadFilterOptions();
    wireUpEventListeners();
    loadInitialData();

    let chargesPage = 1;

    function loadFilterOptions() {
        fetch('/dashboard/container-management/reports/filters')
            .then(r => r.json())
            .then(data => {
                const customerSelect = document.getElementById('customer-filter');
                const typeSelect = document.getElementById('type-filter');

                // Clear existing options except "All"
                customerSelect.innerHTML = '<option value="">All Customers</option>';
                typeSelect.innerHTML = '<option value="">All Types</option>';

                // Add customer options
                if (data.customers && data.customers.length > 0) {
                    data.customers.forEach(c => {
                        if (c.name && c.name !== 'null' && c.name.trim() !== '') {
                            customerSelect.insertAdjacentHTML(
                                'beforeend',
                                `<option value="${c.id}">${c.name}</option>` 
                            );
                        }
                    });
                }

                // Add type options
                if (data.types && data.types.length > 0) {
                    data.types.forEach(t => {
                        if (t.name && t.name !== 'null' && t.name.trim() !== '') {
                            typeSelect.insertAdjacentHTML(
                                'beforeend',
                                `<option value="${t.id}">${t.name}</option>` 
                            );
                        }
                    });
                }
            })
            .catch(error => {
                console.error('Error loading filter options:', error);
            });
    }

    function initializeTabs() {
        document.querySelectorAll('.tab-button').forEach(button => {
            button.addEventListener('click', function() {
                const tab = this.dataset.tab;
                switchTab(tab);
            });
        });
    }

    function switchTab(tabName) {
        // Update button states
        document.querySelectorAll('.tab-button').forEach(btn => {
            btn.classList.remove('active');
        });
        document.querySelector(`[data-tab="${tabName}"]`).classList.add('active');

        // Update content visibility
        document.querySelectorAll('.tab-content').forEach(content => {
            content.classList.remove('active');
        });
        document.getElementById(`${tabName}-tab`).classList.add('active');

        currentTab = tabName;

        // Load data for the active tab
        if (tabName === 'charges') {
            loadCharges();
        }
    }

    function initializeFilters() {
        // Set default date range (last 30 days)
        const today = new Date();
        const thirtyDaysAgo = new Date(today.getTime() - (30 * 24 * 60 * 60 * 1000));
        
        document.getElementById('to-date').value = today.toISOString().split('T')[0];
    }

    function wireUpEventListeners() {
        // Filter controls
        const applyBtn = document.getElementById('apply-filters');
        if (applyBtn) applyBtn.onclick = () => {
            movementsPage = 1;
            balancesPage = 1;
            chargesPage = 1;
            loadSummary();
            loadMovements();
            loadBalances();
            loadCharges();
        };

        const resetBtn = document.getElementById('reset-filters');
        if (resetBtn) resetBtn.onclick = () => {
            initializeFilters();
            movementsPage = 1;
            balancesPage = 1;
            chargesPage = 1;
            loadSummary();
            loadMovements();
            loadBalances();
            loadCharges();
        };

        const clearBtn = document.getElementById('clear-filters');
        if (clearBtn) clearBtn.onclick = () => {
            const resetBtn = document.getElementById('reset-filters');
            if (resetBtn) resetBtn.click();
        };

        // Export controls
        const exportCsvBtn = document.getElementById('export-csv');
        if (exportCsvBtn) exportCsvBtn.onclick = () => {
            window.location = `/dashboard/container-management/reports/export?${query()}`;
        };

        const exportMovementsBtn = document.getElementById('export-movements');
        if (exportMovementsBtn) exportMovementsBtn.onclick = () => {
            window.location = `/dashboard/container-management/reports/export?type=movements&${query()}`;
        };

        const exportBalancesBtn = document.getElementById('export-balances');
        if (exportBalancesBtn) exportBalancesBtn.onclick = () => {
            window.location = `/dashboard/container-management/reports/export?type=balances&${query()}`;
        };

        const exportChargesBtn = document.getElementById('export-charges');
        if (exportChargesBtn) exportChargesBtn.onclick = () => {
            window.location = `/dashboard/container-management/reports/export?type=charges&${query()}`;
        };

        // Refresh control
        const refreshBtn = document.getElementById('refresh-data');
        if (refreshBtn) refreshBtn.onclick = () => {
            loadSummary();
            loadMovements();
            loadBalances();
            loadCharges();
        };

        // Pagination controls
        const prevMovementsBtn = document.getElementById('prev-movements');
        if (prevMovementsBtn) prevMovementsBtn.onclick = () => {
            if (movementsPage > 1) {
                movementsPage--;
                loadMovements();
            }
        };

        const nextMovementsBtn = document.getElementById('next-movements');
        if (nextMovementsBtn) nextMovementsBtn.onclick = () => {
            movementsPage++;
            loadMovements();
        };

        const prevBalancesBtn = document.getElementById('prev-balances');
        if (prevBalancesBtn) prevBalancesBtn.onclick = () => {
            if (balancesPage > 1) {
                balancesPage--;
                loadBalances();
            }
        };

        const nextBalancesBtn = document.getElementById('next-balances');
        if (nextBalancesBtn) nextBalancesBtn.onclick = () => {
            balancesPage++;
            loadBalances();
        };

        const prevChargesBtn = document.getElementById('prev-charges');
        if (prevChargesBtn) prevChargesBtn.onclick = () => {
            if (chargesPage > 1) {
                chargesPage--;
                loadCharges();
            }
        };

        const nextChargesBtn = document.getElementById('next-charges');
        if (nextChargesBtn) nextChargesBtn.onclick = () => {
            chargesPage++;
            loadCharges();
        };
    }

    function loadInitialData() {
        loadSummary();
        loadMovements();
        loadBalances();
        // Load charges if that's the active tab
        if (currentTab === 'charges') {
            loadCharges();
        }
    }

    function loadCharges() {
        fetch(`/dashboard/container-management/reports/charges?${query({ page: chargesPage })}`)
            .then(r => r.json())
            .then(res => {
                const tbody = document.getElementById('charges-table');
                tbody.innerHTML = '';

                if (!res.data || !res.data.length) {
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                                <i class="las la-inbox text-4xl mb-2"></i>
                                <p>No charge data found for the selected period</p>
                            </td>
                        </tr>
                    `;
                    updateChargesPagination(0, 0, 1);
                    return;
                }

                res.data.forEach(charge => {
                    
                    tbody.insertAdjacentHTML('beforeend', `
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                ${formatDateTime(charge.date)}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <div class="font-medium">${charge.customer}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                ${charge.container}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-semibold text-gray-900">
                                ${charge.quantity}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-semibold text-gray-900">
                                $${parseFloat(charge.amount).toFixed(2)}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                ${charge.notes || '-'}
                            </td>
                        </tr>
                    `);
                });

                updateChargesPagination(res.data.length, res.total, res.last_page);
            })
            .catch(error => {
                console.error('Error loading charges:', error);
                document.getElementById('charges-table').innerHTML = `
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center text-red-500">
                            <i class="las la-exclamation-triangle text-4xl mb-2"></i>
                            <p>Error loading charge data</p>
                        </td>
                    </tr>
                `;
            });
    }

    function updateChargesPagination(shown, total, lastPage) {
        document.getElementById('charges-shown').textContent = shown;
        document.getElementById('charges-total').textContent = total;
        document.getElementById('charges-current-page').textContent = chargesPage;
        document.getElementById('charges-last-page').textContent = lastPage;
        document.getElementById('charges-count').textContent = total;

        document.getElementById('prev-charges').disabled = chargesPage === 1;
        document.getElementById('next-charges').disabled = chargesPage >= lastPage;
    }

    

    function loadSummary() {
        fetch(`/dashboard/container-management/reports/summary?${query()}`)
            .then(r => r.json())
            .then(data => {
                document.getElementById('total-out').innerHTML = data.total_out.toLocaleString();
                document.getElementById('total-in').innerHTML = data.total_in.toLocaleString();
                document.getElementById('total-balance').innerHTML = data.outstanding.toLocaleString();
                document.getElementById('active-customers').innerHTML = data.active_customers.toLocaleString();
            })
            .catch(error => {
                console.error('Error loading summary:', error);
                document.getElementById('total-out').innerHTML = '—';
                document.getElementById('total-in').innerHTML = '—';
                document.getElementById('total-balance').innerHTML = '—';
                document.getElementById('active-customers').innerHTML = '—';
            });
    }

    function loadMovements() {
        fetch(`/dashboard/container-management/reports/movements?${query({ page: movementsPage })}`)
            .then(r => r.json())
            .then(res => {
                const tbody = document.getElementById('movements-table');
                tbody.innerHTML = '';

                if (!res.data || !res.data.length) {
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                                <i class="las la-inbox text-4xl mb-2"></i>
                                <p>No movement data found for the selected period</p>
                            </td>
                        </tr>
                    `;
                    updateMovementsPagination(0, 0, 1);
                    return;
                }

                res.data.forEach(row => {
                    const directionClass = getDirectionClass(row.direction);
                    tbody.insertAdjacentHTML('beforeend', `
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                ${formatDateTime(row.date)}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <div class="font-medium">${row.customer}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                ${row.container}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="direction-badge ${directionClass}">
                                    ${row.direction.toUpperCase()}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-semibold text-gray-900">
                                ${row.quantity}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                ${row.source_type}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                ${row.note || '-'}
                            </td>
                        </tr>
                    `);
                });

                updateMovementsPagination(res.data.length, res.total, res.last_page);
            })
            .catch(error => {
                console.error('Error loading movements:', error);
                document.getElementById('movements-table').innerHTML = `
                    <tr>
                        <td colspan="7" class="px-6 py-12 text-center text-red-500">
                            <i class="las la-exclamation-triangle text-4xl mb-2"></i>
                            <p>Error loading movement data</p>
                        </td>
                    </tr>
                `;
            });
    }

    function loadBalances() {
        fetch(`/dashboard/container-management/reports/balances?${query({ page: balancesPage })}`)
            .then(r => r.json())
            .then(res => {
                const tbody = document.getElementById('balances-table');
                tbody.innerHTML = '';

                if (!res.data || !res.data.length) {
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center text-gray-500">
                                <i class="las la-inbox text-4xl mb-2"></i>
                                <p>No outstanding balances found</p>
                            </td>
                        </tr>
                    `;
                    updateBalancesPagination(0, 0, 1);
                    return;
                }

                res.data.forEach(row => {
                    const balanceClass = row.balance > 0 ? 'balance-positive' : 'balance-zero';
                    tbody.insertAdjacentHTML('beforeend', `
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <div class="font-medium">${row.customer}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                ${row.container}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <span class="${balanceClass}">${row.balance}</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                ${formatDateTime(row.updated_at)}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <button class="ns-button secondary text-sm">View Details</button>
                            </td>
                        </tr>
                    `);
                });

                updateBalancesPagination(res.data.length, res.total, res.last_page);
            })
            .catch(error => {
                console.error('Error loading balances:', error);
                document.getElementById('balances-table').innerHTML = `
                    <tr>
                        <td colspan="5" class="px-6 py-12 text-center text-red-500">
                            <i class="las la-exclamation-triangle text-4xl mb-2"></i>
                            <p>Error loading balance data</p>
                        </td>
                    </tr>
                `;
            });
    }

    function updateMovementsPagination(shown, total, lastPage) {
        document.getElementById('movements-shown').textContent = shown;
        document.getElementById('movements-total').textContent = total;
        document.getElementById('movements-current-page').textContent = movementsPage;
        document.getElementById('movements-last-page').textContent = lastPage;
        document.getElementById('movements-count').textContent = total;

        document.getElementById('prev-movements').disabled = movementsPage === 1;
        document.getElementById('next-movements').disabled = movementsPage >= lastPage;
    }

    function updateBalancesPagination(shown, total, lastPage) {
        document.getElementById('balances-shown').textContent = shown;
        document.getElementById('balances-total').textContent = total;
        document.getElementById('balances-current-page').textContent = balancesPage;
        document.getElementById('balances-last-page').textContent = lastPage;
        document.getElementById('balances-count').textContent = total;

        document.getElementById('prev-balances').disabled = balancesPage === 1;
        document.getElementById('next-balances').disabled = balancesPage >= lastPage;
    }

    function query(extra = {}) {
        return new URLSearchParams({
            from: document.getElementById('from-date').value,
            to: document.getElementById('to-date').value,
            customer_id: document.getElementById('customer-filter').value,
            type_id: document.getElementById('type-filter').value,
            ...extra
        }).toString();
    }

    function formatDateTime(dateString) {
        const date = new Date(dateString);
        return date.toLocaleString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    function getDirectionClass(direction) {
        switch(direction) {
            case 'out': return 'direction-out';
            case 'in': return 'direction-in';
            case 'charge': return 'direction-charge';
            case 'adjustment': return 'direction-adjustment';
            default: return 'direction-out';
        }
    }

});
</script>
@endsection
