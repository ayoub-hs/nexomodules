<?php use App\Classes\Hook; ?>

@extends('layout.dashboard')

@section('layout.dashboard.body')
<div class="h-full flex flex-col">

    {{-- Dashboard header (required) --}}
    @include(Hook::filter('ns-dashboard-header-file', '../common/dashboard-header'))

    <div class="flex-auto overflow-y-auto ns-scrollbar">
        <div class="ns-container px-6 py-6">

            {{-- Page Header --}}
            <div class="mb-8">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900 mb-2">Charge Customer</h1>
                        <p class="text-gray-600">Charge customer for container deposits or fees</p>
                    </div>
                </div>
            </div>

            {{-- Charge Form --}}
            <div class="ns-box">
                <div class="p-6 border-b border-gray-200 bg-gray-50">
                    <h3 class="text-lg font-semibold text-gray-900">
                        <i class="las la-credit-card mr-2"></i>Container Charge Details
                    </h3>
                </div>
                <div class="p-6">
                    <form id="charge-form" class="space-y-6">
                        @csrf
                        {{-- Customer Selection --}}
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Customer</label>
                                <select id="customer-select" class="ns-input w-full" required>
                                    <option value="">Select Customer</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Container Type</label>
                                <select id="container-type-select" class="ns-input w-full" required>
                                    <option value="">Select Container Type</option>
                                </select>
                            </div>
                        </div>

                        {{-- Charge Details --}}
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Quantity</label>
                                <input type="number" id="quantity" class="ns-input w-full" min="1" value="1" required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Unit Price</label>
                                <input type="number" id="unit-price" class="ns-input w-full" min="0" step="0.01" placeholder="0.00" required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Total Amount</label>
                                <input type="number" id="total-amount" class="ns-input w-full" min="0" step="0.01" readonly>
                            </div>
                        </div>

                        {{-- Charge Type --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Notes</label>
                            <textarea id="charge-notes" class="ns-input w-full" rows="3" placeholder="Enter charge details or reason..."></textarea>
                        </div>

                        {{-- Action Buttons --}}
                        <div class="flex gap-3">
                            <button type="submit" class="ns-button primary">
                                <i class="las la-credit-card mr-2"></i>Process Charge
                            </button>
                            <button type="button" class="ns-button secondary" onclick="history.back()">
                                <i class="las la-times mr-2"></i>Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>
</div>

<style>
.charge-summary {
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: 0.5rem;
    padding: 1rem;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    
    // Set initial values from URL parameters first
    const urlParams = new URLSearchParams(window.location.search);
    const customerId = urlParams.get('customer_id');
    const containerTypeId = urlParams.get('container_type_id');
    
    // Load filter options
    loadFilterOptions(customerId, containerTypeId);
    
    // Calculate total when quantity or unit price changes
    document.getElementById('quantity').addEventListener('input', calculateTotal);
    document.getElementById('unit-price').addEventListener('input', calculateTotal);

    // Handle form submission
    document.getElementById('charge-form').addEventListener('submit', handleChargeSubmit);

    function loadFilterOptions(preselectedCustomerId = null, preselectedTypeId = null) {
        fetch('/dashboard/container-management/reports/filters')
            .then(r => r.json())
            .then(data => {
                const customerSelect = document.getElementById('customer-select');
                const typeSelect = document.getElementById('container-type-select');

                // Check if elements exist before trying to use them
                if (customerSelect) {
                    // Clear existing options except "All"
                    customerSelect.innerHTML = '<option value="">All Customers</option>';
                }
                if (typeSelect) {
                    typeSelect.innerHTML = '<option value="">All Types</option>';
                }

                // Add customer options
                if (data.customers && data.customers.length > 0) {
                    data.customers.forEach(c => {
                        if (c.name && c.name !== 'null' && c.name.trim() !== '') {
                            const option = `<option value="${c.id}">${c.name}</option>`;
                            customerSelect.insertAdjacentHTML('beforeend', option);
                        }
                    });
                }

                // Add type options
                if (data.types && data.types.length > 0) {
                    data.types.forEach(t => {
                        if (t.name && t.name !== 'null' && t.name.trim() !== '') {
                            const option = `<option value="${t.id}">${t.name}</option>`;
                            typeSelect.insertAdjacentHTML('beforeend', option);
                        }
                    });
                }

                // Set pre-selected values AFTER options are loaded
                if (preselectedCustomerId) {
                    customerSelect.value = preselectedCustomerId;
                }
                if (preselectedTypeId) {
                    typeSelect.value = preselectedTypeId;
                    // Load container details for pre-selected type
                    loadContainerDetails();
                }

                // Load container type details when type is selected
                if (typeSelect) typeSelect.addEventListener('change', loadContainerDetails);
            })
            .catch(error => {
                console.error('Error loading filter options:', error);
            });
    }

    function loadContainerDetails() {
        const typeId = document.getElementById('container-type-select').value;
        if (!typeId) return;

        // Use the existing container types data instead of making another API call
        fetch('/dashboard/container-management/reports/filters')
            .then(r => r.json())
            .then(data => {
                const containerType = data.types.find(t => t.id == typeId);
                if (containerType) {
                    // Set unit price from deposit fee if available
                    if (containerType.deposit_fee) {
                        document.getElementById('unit-price').value = containerType.deposit_fee;
                        calculateTotal();
                    }
                }
            })
            .catch(error => {
                console.error('Error loading container details:', error);
            });
    }

    function calculateTotal() {
        const quantity = parseFloat(document.getElementById('quantity').value) || 0;
        const unitPrice = parseFloat(document.getElementById('unit-price').value) || 0;
        const total = quantity * unitPrice;
        document.getElementById('total-amount').value = total.toFixed(2);
    }

    function handleChargeSubmit(e) {
        e.preventDefault();
        
        const formData = {
            customer_id: document.getElementById('customer-select').value,
            container_type_id: document.getElementById('container-type-select').value,
            quantity: document.getElementById('quantity').value,
            unit_price: document.getElementById('unit-price').value,
            total_amount: document.getElementById('total-amount').value,
            charge_type: 'charge', // Always use 'charge' since we removed the dropdown
            notes: document.getElementById('charge-notes').value
        };

        // Debug: Log what's being sent
        console.log('Sending charge data:', formData);
        console.log('Customer ID:', formData.customer_id);
        console.log('Container Type ID:', formData.container_type_id);
        console.log('Quantity:', formData.quantity);
        console.log('Unit Price:', formData.unit_price);
        console.log('Total Amount:', formData.total_amount);
        console.log('Charge Type:', formData.charge_type);
        console.log('Notes:', formData.notes);

        // Get CSRF token from the form
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || 
                          document.querySelector('input[name="_token"]')?.value;

        if (!csrfToken) {
            alert('Security token not found. Please refresh the page.');
            return;
        }

        // Submit charge
        fetch('/dashboard/container-management/charge/process', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify(formData)
        })
        .then(response => {
            // Check if response is actually JSON
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                return response.text().then(text => {
                    console.error('Server returned non-JSON response:', text);
                    alert('Server error: Invalid response format. Please check logs.');
                });
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                alert('Charge processed successfully!');
            } else {
                alert('Error: ' + (data.message || 'Failed to process charge'));
            }
        })
        .catch(error => {
            console.error('Error processing charge:', error);
            alert('Error processing charge. Please try again.');
        });
    }
});
</script>
@endsection
