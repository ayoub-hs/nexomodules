@extends( 'layout.dashboard' )

@section( 'layout.dashboard.body' )
    <div>
        @include( Hook::filter( 'ns-dashboard-header-file', '../common/dashboard-header' ) )
        <div id="dashboard-content" class="px-4">
            <div class="page-inner-header mb-4">
                <h3 class="text-3xl text-fontcolor font-bold">{!! sprintf( __( 'Order Payment — %s' ), $order->code ) !!}</h3>
                <p class="text-fontcolor-soft">{{ __( 'Process payment for this order' ) }}</p>
            </div>

            <div class="max-w-4xl mx-auto">
                {{-- Order Summary --}}
                <div class="bg-white shadow-lg rounded-lg p-6 mb-6">
                    <h4 class="text-xl font-bold mb-4 border-b pb-2">{{ __( 'Order Summary' ) }}</h4>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-gray-600">{{ __( 'Customer' ) }}</p>
                            <p class="font-semibold">{{ $order->customer->first_name ?? '' }} {{ $order->customer->last_name ?? __( 'Guest' ) }}</p>
                        </div>
                        <div>
                            <p class="text-gray-600">{{ __( 'Order Code' ) }}</p>
                            <p class="font-semibold">{{ $order->code }}</p>
                        </div>
                        <div>
                            <p class="text-gray-600">{{ __( 'Total Amount' ) }}</p>
                            <p class="font-semibold text-lg">{{ ns()->currency->define( $order->total ) }}</p>
                        </div>
                        <div>
                            <p class="text-gray-600">{{ __( 'Already Paid' ) }}</p>
                            <p class="font-semibold">{{ ns()->currency->define( $paidAmount ) }}</p>
                        </div>
                        <div class="col-span-2">
                            <p class="text-gray-600">{{ __( 'Amount Due' ) }}</p>
                            <p class="font-bold text-2xl text-red-600">{{ ns()->currency->define( $dueAmount ) }}</p>
                        </div>
                    </div>
                </div>

                {{-- Payment Options --}}
                <div class="bg-white shadow-lg rounded-lg p-6">
                    <h4 class="text-xl font-bold mb-4 border-b pb-2">{{ __( 'Payment Options' ) }}</h4>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        {{-- Pay from Wallet (for special customers) --}}
                        @if( $order->customer && ns()->allowedTo( 'special.customer.pay-outstanding-tickets' ) )
                            <form action="{{ route( 'ns.dashboard.special-customer-outstanding.pay' ) }}" method="POST" class="payment-option">
                                @csrf
                                <input type="hidden" name="customer_id" value="{{ $order->customer_id }}">
                                <input type="hidden" name="order_id" value="{{ $order->id }}">
                                <button type="submit"
                                    class="w-full p-4 border-2 border-blue-500 rounded-lg hover:bg-blue-50 transition text-left"
                                    onclick="return confirm('{{ __( 'Pay this order using the customer wallet?' ) }}')">
                                    <div class="font-bold text-blue-600">{{ __( 'Pay from Wallet' ) }}</div>
                                    <div class="text-sm text-gray-600">{{ __( 'Use customer wallet balance' ) }}</div>
                                </button>
                            </form>
                        @endif

                        {{-- Standard Payment Methods --}}
                        @foreach( $paymentTypes as $paymentType )
                            @if( $paymentType['identifier'] !== 'wallet' )
                                <form action="{{ ns()->url( '/api/orders/' . $order->id . '/payments' ) }}" method="POST" class="payment-option">
                                    @csrf
                                    <input type="hidden" name="identifier" value="{{ $paymentType['identifier'] }}">
                                    <input type="hidden" name="value" value="{{ $dueAmount }}">
                                    <button type="submit"
                                        class="w-full p-4 border-2 border-gray-300 rounded-lg hover:bg-gray-50 transition text-left">
                                        <div class="font-bold text-gray-800">{{ $paymentType['label'] }}</div>
                                        <div class="text-sm text-gray-600">{{ __( 'Pay' ) }} {{ ns()->currency->define( $dueAmount ) }}</div>
                                    </button>
                                </form>
                            @endif
                        @endforeach
                    </div>

                    <div class="mt-6 text-center">
                        <a href="{{ ns()->url( '/dashboard/orders/receipt/' . $order->id ) }}"
                           class="text-blue-500 hover:text-blue-700 underline">
                            {{ __( 'View Order Receipt' ) }}
                        </a>
                        <span class="mx-2">|</span>
                        <a href="{{ ns()->url( '/dashboard/special-customer/outstanding-tickets' ) }}"
                           class="text-blue-500 hover:text-blue-700 underline">
                            {{ __( 'Back to Outstanding Tickets' ) }}
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection