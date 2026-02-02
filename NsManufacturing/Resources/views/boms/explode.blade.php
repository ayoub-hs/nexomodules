@extends('layout.dashboard')
@section('layout.dashboard.body')
    <div class="h-full flex flex-col">
        <div class="flex-auto overflow-y-auto">
             <div class="ns-container px-4 py-4">
                <div class="mb-4 flex items-center justify-between">
                     <h1 class="text-2xl font-bold">{{ $bom->name }} - {{ __('Structure') }}</h1>
                     <a href="{{ ns()->route('ns.dashboard.manufacturing-boms') }}" class="px-4 py-2 bg-gray-200 rounded text-gray-700">{{ __('Back') }}</a>
                </div>
                
                <div class="bg-white shadow rounded p-4">
                    <h2 class="text-lg font-semibold mb-2">
                        {{ __('Output') }}: {{ $bom->product->name ?? 'N/A' }} 
                        ({{ number_format($bom->quantity, ns()->option->get('ns_currency_precision', 2), ns()->option->get('ns_currency_decimal_separator', '.'), ns()->option->get('ns_currency_thousand_separator', ',')) }} {{ $bom->unit->name ?? '' }})
                    </h2>
                    <hr class="my-4">
                    <h3 class="font-medium mb-2">{{ __('Components') }}</h3>
                    <ul class="list-disc pl-5">
                    @foreach($bom->items as $item)
                        <li class="mb-1">
                            <strong>{{ $item->product->name ?? 'N/A' }}</strong>: 
                            {{ number_format($item->quantity, ns()->option->get('ns_currency_precision', 2), ns()->option->get('ns_currency_decimal_separator', '.'), ns()->option->get('ns_currency_thousand_separator', ',')) }} {{ $item->unit->name ?? '' }} 
                            (Waste: {{ number_format($item->waste_percent, ns()->option->get('ns_currency_precision', 2), ns()->option->get('ns_currency_decimal_separator', '.'), ns()->option->get('ns_currency_thousand_separator', ',')) }}%)
                        </li>
                    @endforeach
                    </ul>
                </div>
             </div>
        </div>
    </div>
@endsection
