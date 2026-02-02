@extends( 'layout.dashboard' )

@section( 'layout.dashboard.body' )
<div class="flex-auto flex flex-col">
    @include( Hook::filter( 'ns-dashboard-header-file', '../common/dashboard-header' ) )
    <div class="flex-auto flex flex-col" id="dashboard-content">
        <div class="px-4">
            <div class="page-inner-header mb-4">
                <h3 class="text-3xl text-primary-800 font-bold">{{ __( 'Special Customer Statistics' ) }}</h3>
                <p class="text-secondary-600">{{ __( 'Track cashback activity and customer performance.' ) }}</p>
            </div>
        </div>
        <div class="px-4 flex-auto flex flex-col">
            <div class="flex flex-col flex-auto" id="statistics-container">
                <!-- Date Range Filter -->
                <div class="ns-box rounded shadow mb-4">
                    <div class="ns-box-header p-2 border-b">
                        <h3 class="font-semibold">{{ __( 'Date Range Filter' ) }}</h3>
                    </div>
                    <div class="ns-box-body p-2">
                        <div class="flex flex-wrap -mx-2">
                            <div class="px-2 w-full md:w-1/4">
                                <label class="block text-sm font-medium mb-1">{{ __( 'Year' ) }}</label>
                                <select id="year-select" class="form-input w-full border rounded p-2">
                                    @for( $year = date('Y'); $year >= date('Y') - 5; $year-- )
                                        <option value="{{ $year }}" {{ $year == date('Y') ? 'selected' : '' }}>{{ $year }}</option>
                                    @endfor
                                </select>
                            </div>
                            <div class="px-2 w-full md:w-1/4 flex items-end">
                                <button onclick="loadStatistics()" class="ns-button info">
                                    <i class="las la-sync mr-2"></i>
                                    {{ __( 'Update Statistics' ) }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Statistics Overview Cards -->
                <div class="flex flex-wrap -mx-2 mb-4">
                    <div class="px-2 w-full md:w-1/4 mb-4">
                        <div class="ns-box rounded shadow h-full">
                            <div class="ns-box-body p-4">
                                <div class="text-xs font-semibold text-success-600 uppercase mb-1">{{ __( 'Total Cashback' ) }}</div>
                                <div class="text-2xl font-bold" id="stat-total-amount">{{ __( 'Loading...' ) }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="px-2 w-full md:w-1/4 mb-4">
                        <div class="ns-box rounded shadow h-full">
                            <div class="ns-box-body p-4">
                                <div class="text-xs font-semibold text-info-600 uppercase mb-1">{{ __( 'Total Records' ) }}</div>
                                <div class="text-2xl font-bold" id="stat-total-records">{{ __( 'Loading...' ) }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="px-2 w-full md:w-1/4 mb-4">
                        <div class="ns-box rounded shadow h-full">
                            <div class="ns-box-body p-4">
                                <div class="text-xs font-semibold text-warning-600 uppercase mb-1">{{ __( 'Processed' ) }}</div>
                                <div class="text-2xl font-bold" id="stat-total-processed">{{ __( 'Loading...' ) }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="px-2 w-full md:w-1/4 mb-4">
                        <div class="ns-box rounded shadow h-full">
                            <div class="ns-box-body p-4">
                                <div class="text-xs font-semibold text-primary-600 uppercase mb-1">{{ __( 'Average Cashback' ) }}</div>
                                <div class="text-2xl font-bold" id="stat-average-amount">{{ __( 'Loading...' ) }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Additional Stats -->
                <div class="flex flex-wrap -mx-2 mb-4">
                    <div class="px-2 w-full md:w-1/3 mb-4">
                        <div class="ns-box rounded shadow h-full">
                            <div class="ns-box-body p-4">
                                <div class="text-xs font-semibold text-error-600 uppercase mb-1">{{ __( 'Reversed' ) }}</div>
                                <div class="text-2xl font-bold" id="stat-total-reversed">{{ __( 'Loading...' ) }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="px-2 w-full md:w-1/3 mb-4">
                        <div class="ns-box rounded shadow h-full">
                            <div class="ns-box-body p-4">
                                <div class="text-xs font-semibold text-success-600 uppercase mb-1">{{ __( 'Total Purchases' ) }}</div>
                                <div class="text-2xl font-bold" id="stat-total-purchases">{{ __( 'Loading...' ) }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="px-2 w-full md:w-1/3 mb-4">
                        <div class="ns-box rounded shadow h-full">
                            <div class="ns-box-body p-4">
                                <div class="text-xs font-semibold text-warning-600 uppercase mb-1">{{ __( 'Total Refunds' ) }}</div>
                                <div class="text-2xl font-bold" id="stat-total-refunds">{{ __( 'Loading...' ) }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Cashback History Table -->
                <div class="ns-box rounded shadow flex-auto">
                    <div class="ns-box-header p-2 border-b flex justify-between items-center">
                        <h3 class="font-semibold">{{ __( 'Cashback History' ) }}</h3>
                        <a href="{{ ns()->url( '/dashboard/crud/ns.special-customer-cashback' ) }}" class="ns-button info">
                            <i class="las la-list mr-2"></i>
                            {{ __( 'View All' ) }}
                        </a>
                    </div>
                    <div class="ns-box-body">
                        <ns-crud src="{{ url( 'api/crud/ns.special-customer-cashback' ) }}"></ns-crud>
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
    function formatCurrency( value ) {
        if ( typeof window.nsCurrency === 'function' ) {
            return window.nsCurrency( value || 0 );
        }
        return ( value || 0 ).toFixed( 2 );
    }

    async function loadStatistics() {
        const year = document.getElementById( 'year-select' ).value;
        
        // Set loading state
        document.getElementById( 'stat-total-amount' ).textContent = '{{ __( "Loading..." ) }}';
        document.getElementById( 'stat-total-records' ).textContent = '{{ __( "Loading..." ) }}';
        document.getElementById( 'stat-total-processed' ).textContent = '{{ __( "Loading..." ) }}';
        document.getElementById( 'stat-average-amount' ).textContent = '{{ __( "Loading..." ) }}';
        document.getElementById( 'stat-total-reversed' ).textContent = '{{ __( "Loading..." ) }}';
        document.getElementById( 'stat-total-purchases' ).textContent = '{{ __( "Loading..." ) }}';
        document.getElementById( 'stat-total-refunds' ).textContent = '{{ __( "Loading..." ) }}';
        
        try {
            const response = await nsHttpClient.get( `/api/special-customer/cashback/statistics?year=${year}` ).toPromise();
            
            if ( response && response.status === 'success' ) {
                // API returns data.statistics for the stats object
                const stats = response.data?.statistics || {};
                const yearlyBreakdown = response.data?.yearly_breakdown || {};
                
                // Use yearly breakdown if available for the selected year
                const yearData = yearlyBreakdown[year] || stats;
                
                document.getElementById( 'stat-total-amount' ).textContent = formatCurrency( yearData.total_amount_processed || yearData.total_cashback_processed || 0 );
                document.getElementById( 'stat-total-records' ).textContent = yearData.total_customers || stats.total_processed || 0;
                document.getElementById( 'stat-total-processed' ).textContent = yearData.processed_count || stats.total_processed || 0;
                document.getElementById( 'stat-average-amount' ).textContent = formatCurrency( yearData.average_cashback || stats.average_cashback || 0 );
                document.getElementById( 'stat-total-reversed' ).textContent = yearData.reversed_count || stats.total_reversed || 0;
                document.getElementById( 'stat-total-purchases' ).textContent = formatCurrency( yearData.total_purchases || 0 );
                document.getElementById( 'stat-total-refunds' ).textContent = formatCurrency( yearData.total_refunds || 0 );
            } else {
                if ( typeof nsSnackBar !== 'undefined' ) {
                    nsSnackBar.error( response?.message || '{{ __( "Failed to load statistics" ) }}' ).subscribe();
                }
                setDefaultValues();
            }
        } catch ( error ) {
            console.error( 'Failed to load statistics:', error );
            setDefaultValues();
        }
    }
    
    function setDefaultValues() {
        document.getElementById( 'stat-total-amount' ).textContent = formatCurrency( 0 );
        document.getElementById( 'stat-total-records' ).textContent = '0';
        document.getElementById( 'stat-total-processed' ).textContent = '0';
        document.getElementById( 'stat-average-amount' ).textContent = formatCurrency( 0 );
        document.getElementById( 'stat-total-reversed' ).textContent = '0';
        document.getElementById( 'stat-total-purchases' ).textContent = formatCurrency( 0 );
        document.getElementById( 'stat-total-refunds' ).textContent = formatCurrency( 0 );
    }

    // Load statistics on page load
    document.addEventListener( 'DOMContentLoaded', function() {
        loadStatistics();
    });
    </script>
@endsection
