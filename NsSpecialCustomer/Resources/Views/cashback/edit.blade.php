@extends('layout.dashboard')

@section('layout.dashboard.body')
<div class="h-full flex flex-col flex-auto">
    @include(Hook::filter('ns-dashboard-header-file', '../common/dashboard-header'))
    <div class="px-4 flex-auto flex flex-col" id="dashboard-content">
        @include('common.dashboard.title')
        <ns-crud-form 
            return-url="{{ url('/dashboard/special-customer/cashback') }}"
            submit-url="{{ url('/api/crud/ns.special-customer-cashback/' . $id) }}"
            src="{{ url('/api/crud/ns.special-customer-cashback/form-config') }}"
            :data='{ "id": {{ $id }} }'>
            <template v-slot:title>Edit Cashback</template>
            <template v-slot:save>Update Cashback</template>
        </ns-crud-form>
    </div>
</div>
@endsection

