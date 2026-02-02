@extends('layout.dashboard')
@section('layout.dashboard.body')
    <div class="h-full flex flex-col">
        <div class="flex-auto overflow-y-auto">
            <div class="ns-container px-4 py-4">
                <h1 class="text-2xl font-bold mb-6">{{ __('Manufacturing Analytics') }}</h1>
                
                {{-- Key Metrics Grid --}}
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                    <div class="bg-white p-6 rounded shadow border-l-4 border-gray-400">
                        <h3 class="text-gray-500 text-sm font-medium uppercase tracking-wider">{{ __('Total Orders') }}</h3>
                        <p class="text-3xl font-bold text-gray-900 mt-2">{{ $summary['total_orders'] }}</p>
                    </div>
                    <div class="bg-white p-6 rounded shadow border-l-4 border-green-500">
                         <h3 class="text-gray-500 text-sm font-medium uppercase tracking-wider">{{ __('Completed') }}</h3>
                         <p class="text-3xl font-bold text-green-600 mt-2">{{ $summary['completed'] }}</p>
                    </div>
                     <div class="bg-white p-6 rounded shadow border-l-4 border-blue-400">
                         <h3 class="text-gray-500 text-sm font-medium uppercase tracking-wider">{{ __('In Progress / Pending') }}</h3>
                         <p class="text-3xl font-bold text-blue-600 mt-2">{{ $summary['pending'] }}</p>
                    </div>
                    <div class="bg-white p-6 rounded shadow border-l-4 border-indigo-500">
                         <h3 class="text-gray-500 text-sm font-medium uppercase tracking-wider">{{ __('Total Production Value') }}</h3>
                         <p class="text-3xl font-bold text-indigo-700 mt-2">{{ $summary['total_production_value'] }}</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    {{-- Top Products --}}
                    <div class="bg-white border rounded shadow">
                        <div class="px-6 py-4 border-b">
                            <h3 class="font-bold text-gray-700 uppercase tracking-wider text-sm">{{ __('Top Produced Products') }}</h3>
                        </div>
                        <div class="p-6">
                            @if(count($summary['top_products']) > 0)
                                <ul class="divide-y divide-gray-100">
                                    @foreach($summary['top_products'] as $product)
                                        <li class="py-3 flex justify-between items-center">
                                            <span class="text-gray-800 font-medium">{{ $product['name'] }}</span>
                                            <span class="bg-gray-100 px-3 py-1 rounded-full text-sm font-bold text-gray-600">{{ number_format($product['quantity']) }} {{ __('Units') }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            @else
                                <p class="text-gray-400 text-center py-4 italic">{{ __('No production data available yet.') }}</p>
                            @endif
                        </div>
                    </div>

                    {{-- Recent Activity --}}
                    <div class="bg-white border rounded shadow">
                        <div class="px-6 py-4 border-b">
                            <h3 class="font-bold text-gray-700 uppercase tracking-wider text-sm">{{ __('Recent Production Orders') }}</h3>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="bg-gray-50 text-gray-500 text-xs font-semibold">
                                        <th class="px-6 py-3 border-b">{{ __('Code') }}</th>
                                        <th class="px-6 py-3 border-b">{{ __('Product') }}</th>
                                        <th class="px-6 py-3 border-b text-right">{{ __('Value') }}</th>
                                        <th class="px-6 py-3 border-b text-center">{{ __('Status') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($summary['recent_orders'] as $order)
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-6 py-4 border-b text-sm font-medium text-blue-600">{{ $order['code'] }}</td>
                                            <td class="px-6 py-4 border-b text-sm">
                                                <div class="text-gray-900">{{ $order['product_name'] }}</div>
                                                <div class="text-gray-400 text-xs">{{ $order['quantity'] }} {{ $order['unit_name'] }}</div>
                                            </td>
                                            <td class="px-6 py-4 border-b text-sm text-right font-mono">{{ $order['value'] }}</td>
                                            <td class="px-6 py-4 border-b text-center">
                                                <span class="px-2 py-1 rounded text-xs {{ $order['status'] === 'Completed' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800' }}">
                                                    {{ $order['status'] }}
                                                </span>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="px-6 py-10 text-center text-gray-400 italic">
                                                {{ __('No orders found.') }}
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
