@extends( 'layout.dashboard' )

@section( 'layout.dashboard.body' )
<div class="flex-auto flex flex-col">
    @include( Hook::filter( 'ns-dashboard-header-file', '../common/dashboard-header' ) )
    <div class="flex-auto flex flex-col" id="dashboard-content">
        <div class="px-4">
            <div class="page-inner-header mb-4">
                <h3 class="text-3xl text-primary-800 font-bold">{{ __( 'Customer Balance' ) }}</h3>
                <p class="text-secondary-600">{{ __( 'View and manage customer account balance.' ) }}</p>
            </div>
        </div>
        <div class="px-4 flex-auto flex flex-col">
            <div class="flex flex-col flex-auto" id="balance-container">
                <!-- Customer Info Card -->
                <div class="ns-box rounded shadow mb-4">
                    <div class="ns-box-header p-2 border-b">
                        <h3 class="font-semibold">{{ __( 'Customer Information' ) }}</h3>
                    </div>
                    <div class="ns-box-body p-4">
                        <div class="flex flex-wrap -mx-2">
                            <div class="px-2 w-full md:w-1/2">
                                <div class="mb-2">
                                    <span class="font-semibold">{{ __( 'Name' ) }}:</span>
                                    <span>{{ $customer->first_name }} {{ $customer->last_name }}</span>
                                </div>
                                <div class="mb-2">
                                    <span class="font-semibold">{{ __( 'Email' ) }}:</span>
                                    <span>{{ $customer->email ?? __( 'N/A' ) }}</span>
                                </div>
                                <div class="mb-2">
                                    <span class="font-semibold">{{ __( 'Phone' ) }}:</span>
                                    <span>{{ $customer->phone ?? __( 'N/A' ) }}</span>
                                </div>
                            </div>
                            <div class="px-2 w-full md:w-1/2">
                                <div class="mb-2">
                                    <span class="font-semibold">{{ __( 'Current Balance' ) }}:</span>
                                    <span class="text-success-600 font-bold text-xl">{{ ns()->currency->define( $customer->account_amount )->format() }}</span>
                                </div>
                                <div class="mb-2">
                                    <span class="font-semibold">{{ __( 'Customer Group' ) }}:</span>
                                    <span>{{ $customer->group ? $customer->group->name : __( 'N/A' ) }}</span>
                                </div>
                                <div class="mb-2">
                                    <span class="font-semibold">{{ __( 'Special Customer' ) }}:</span>
                                    @if( $isSpecialCustomer ?? false )
                                        <span class="ns-label success">{{ __( 'Yes' ) }}</span>
                                    @else
                                        <span class="ns-label">{{ __( 'No' ) }}</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Balance Statistics Cards -->
                <div class="flex flex-wrap -mx-2 mb-4">
                    <div class="px-2 w-full md:w-1/3 mb-4">
                        <div class="ns-box rounded shadow h-full">
                            <div class="ns-box-body p-4">
                                <div class="text-xs font-semibold text-success-600 uppercase mb-1">{{ __( 'Current Balance' ) }}</div>
                                <div class="text-2xl font-bold" id="balance-current">{{ ns()->currency->define( $customer->account_amount )->format() }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="px-2 w-full md:w-1/3 mb-4">
                        <div class="ns-box rounded shadow h-full">
                            <div class="ns-box-body p-4">
                                <div class="text-xs font-semibold text-info-600 uppercase mb-1">{{ __( 'Total Credited' ) }}</div>
                                <div class="text-2xl font-bold" id="balance-credit">{{ __( 'Loading...' ) }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="px-2 w-full md:w-1/3 mb-4">
                        <div class="ns-box rounded shadow h-full">
                            <div class="ns-box-body p-4">
                                <div class="text-xs font-semibold text-warning-600 uppercase mb-1">{{ __( 'Total Debited' ) }}</div>
                                <div class="text-2xl font-bold" id="balance-debit">{{ __( 'Loading...' ) }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="ns-box rounded shadow mb-4">
                    <div class="ns-box-header p-2 border-b">
                        <h3 class="font-semibold">{{ __( 'Quick Actions' ) }}</h3>
                    </div>
                    <div class="ns-box-body p-4">
                        <div class="flex flex-wrap gap-2">
                            <a href="{{ ns()->url( '/dashboard/special-customer/topup' ) }}?customer_id={{ $customer->id }}" class="ns-button info">
                                <i class="las la-plus-circle mr-2"></i>
                                {{ __( 'Top-up Account' ) }}
                            </a>
                            <a href="{{ ns()->url( '/dashboard/special-customer/cashback/create' ) }}?customer_id={{ $customer->id }}" class="ns-button success">
                                <i class="las la-gift mr-2"></i>
                                {{ __( 'Process Cashback' ) }}
                            </a>
                            <a href="{{ ns()->url( '/dashboard/crud/ns.special-customer-cashback' ) }}?customer_id={{ $customer->id }}" class="ns-button default">
                                <i class="las la-history mr-2"></i>
                                {{ __( 'View Cashback History' ) }}
                            </a>
                            <button onclick="loadBalanceInfo()" class="ns-button info">
                                <i class="las la-sync mr-2"></i>
                                {{ __( 'Refresh' ) }}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Recent Transactions -->
                <div class="ns-box rounded shadow flex-auto">
                    <div class="ns-box-header p-2 border-b">
                        <h3 class="font-semibold">{{ __( 'Recent Account History' ) }}</h3>
                    </div>
                    <div class="ns-box-body">
                        <div class="table-responsive">
                            <table class="table ns-table w-full">
                                <thead>
                                    <tr>
                                        <th class="p-2 text-left border-b">{{ __( 'Date' ) }}</th>
                                        <th class="p-2 text-left border-b">{{ __( 'Type' ) }}</th>
                                        <th class="p-2 text-left border-b">{{ __( 'Amount' ) }}</th>
                                        <th class="p-2 text-left border-b">{{ __( 'Description' ) }}</th>
                                    </tr>
                                </thead>
                                <tbody id="transactions-body">
                                    <tr>
                                        <td colspan="4" class="p-4 text-center text-secondary-600">
                                            {{ __( 'Loading...' ) }}
                                        </td>
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

@section( 'layout.dashboard.footer' )
    @parent
    <script>
    const customerId = {{ $customer->id }};
    const initialBalance = {{ $customer->account_amount ?? 0 }};

    function formatCurrency( value ) {
        if ( typeof window.nsCurrency === 'function' ) {
            return window.nsCurrency( value || 0 );
        }
        return ( value || 0 ).toFixed( 2 );
    }

    function formatDate( dateString ) {
        if ( !dateString ) return '-';
        return new Date( dateString ).toLocaleDateString();
    }

    function getOperationClass( operation ) {
        const classes = {
            'add': 'success',
            'refund': 'success',
            'deduct': 'error',
            'payment': 'warning'
        };
        return classes[ operation ] || '';
    }

    function renderTransactions( transactions ) {
        const tbody = document.getElementById( 'transactions-body' );
        
        if ( !transactions || transactions.length === 0 ) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="4" class="p-4 text-center text-secondary-600">
                        {{ __( 'No transactions found' ) }}
                    </td>
                </tr>
            `;
            return;
        }

        tbody.innerHTML = transactions.slice( 0, 10 ).map( function( transaction ) {
            const isCredit = [ 'add', 'refund' ].includes( transaction.operation );
            const prefix = isCredit ? '+' : '-';
            const amountClass = isCredit ? 'text-success-600 font-bold' : 'text-error-600 font-bold';
            const labelClass = getOperationClass( transaction.operation );
            
            return `
                <tr>
                    <td class="p-2 border-b">${formatDate( transaction.created_at )}</td>
                    <td class="p-2 border-b">
                        <span class="ns-label ${labelClass}">${transaction.operation || '-'}</span>
                    </td>
                    <td class="p-2 border-b ${amountClass}">
                        ${prefix}${formatCurrency( Math.abs( transaction.amount || 0 ) )}
                    </td>
                    <td class="p-2 border-b">${transaction.description || '-'}</td>
                </tr>
            `;
        }).join( '' );
    }

    async function loadBalanceInfo() {
        // Set loading state
        document.getElementById( 'balance-credit' ).textContent = '{{ __( "Loading..." ) }}';
        document.getElementById( 'balance-debit' ).textContent = '{{ __( "Loading..." ) }}';
        document.getElementById( 'transactions-body' ).innerHTML = `
            <tr>
                <td colspan="4" class="p-4 text-center text-secondary-600">
                    {{ __( 'Loading...' ) }}
                </td>
            </tr>
        `;
        
        try {
            const response = await nsHttpClient.get( `/api/special-customer/balance/${customerId}` ).toPromise();
            
            if ( response && response.status === 'success' ) {
                const data = response.data || {};
                document.getElementById( 'balance-current' ).textContent = formatCurrency( data.current_balance || initialBalance );
                document.getElementById( 'balance-credit' ).textContent = formatCurrency( data.total_credited || 0 );
                document.getElementById( 'balance-debit' ).textContent = formatCurrency( data.total_debited || 0 );
                renderTransactions( data.account_history || [] );
            } else {
                nsSnackBar.error( response?.message || '{{ __( "Failed to load balance information" ) }}' ).subscribe();
            }
        } catch ( error ) {
            console.error( 'Failed to load balance information:', error );
            // Set default values on error
            document.getElementById( 'balance-current' ).textContent = formatCurrency( initialBalance );
            document.getElementById( 'balance-credit' ).textContent = formatCurrency( 0 );
            document.getElementById( 'balance-debit' ).textContent = formatCurrency( 0 );
            renderTransactions( [] );
        }
    }

    // Load balance info on page load
    document.addEventListener( 'DOMContentLoaded', function() {
        loadBalanceInfo();
    });
    </script>
@endsection
