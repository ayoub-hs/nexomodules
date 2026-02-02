@extends('layout.dashboard')

@section('layout.dashboard.body')
<div class="h-full flex flex-col flex-auto">
    @include(Hook::filter('ns-dashboard-header-file', '../common/dashboard-header'))
    <div class="px-4 flex-auto flex flex-col" id="dashboard-content">
        @include('common.dashboard.title')
        <ns-crud-form 
            return-url="{{ url('/dashboard/special-customer/topup') }}"
            submit-url="{{ url('/api/crud/ns.special-customer-topup') }}"
            src="{{ url('/api/crud/ns.special-customer-topup/form-config') }}">
            <template v-slot:title>Customer Top-up</template>
            <template v-slot:save>Process Top-up</template>
        </ns-crud-form>
    </div>
</div>
@endsection
