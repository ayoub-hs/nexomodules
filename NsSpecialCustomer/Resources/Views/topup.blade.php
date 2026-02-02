@extends('layout.dashboard')

@section('layout.dashboard.body')
<div>
    @include(Hook::filter('ns-dashboard-header-file', '../common/dashboard-header'))
    <div id="dashboard-content" class="px-4">
        <div class="page-inner-header mb-4">
            <h3 class="text-3xl text-gray-800 font-bold">{{ __('Customer Top-up History') }}</h3>
            <p class="text-gray-600">{{ __('View and manage special customer top-up transactions') }}</p>
        </div>
        <ns-crud 
            src="{{ url('api/crud/ns.special-customer-topup') }}" 
            create-url="{{ url('dashboard/special-customer/topup/create') }}">
        </ns-crud>
    </div>
</div>
@endsection
