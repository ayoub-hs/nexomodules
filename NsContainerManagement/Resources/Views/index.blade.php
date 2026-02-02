<?php
use App\Classes\Hook;
?>
@extends('layout.dashboard')

@section('layout.dashboard.body')
    <div class="h-full flex flex-col">
        @include(Hook::filter('ns-dashboard-header-file', '../common/dashboard-header'))
        <div class="flex-auto overflow-y-auto">
            <div class="ns-container px-4 py-4">
                <div class="bg-white shadow rounded p-6">
                    <h1 class="text-2xl font-bold mb-4">{{ $title }}</h1>
                    <p class="mb-4">{{ $description ?? 'Manage your container operations here.' }}</p>
                    <div class="p-4 bg-blue-50 text-blue-700 rounded border border-blue-200">
                        <i class="las la-info-circle mr-2"></i>
                        Front-end implementation pending. (Vue Component Placeholder)
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
