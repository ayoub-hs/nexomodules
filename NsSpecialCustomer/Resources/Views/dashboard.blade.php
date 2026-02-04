<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Special Customer</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, "Apple Color Emoji", "Segoe UI Emoji"; padding: 24px; }
        .title { font-size: 24px; font-weight: 700; color: #1f2937; }
        .subtitle { color: #4b5563; margin-top: 4px; }
        .card { border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px; margin-top: 16px; }
        a { color: #2563eb; text-decoration: none; }
    </style>
    </head>
<body>
    <div class="page-inner-header mb-4">
        <h3 class="title">{{ __('Special Customer Dashboard') }}</h3>
        <p class="subtitle">{{ __('Special Customer Configuration') }}</p>
    </div>
    <div class="card">
        <h4 class="font-semibold mb-2">{{ __('Quick Actions') }}</h4>
        <ul class="list-disc ml-5">
            <li>
                <a href="{{ url('/dashboard/special-customer/customers') }}">{{ __('Customer Lookup') }}</a>
            </li>
        </ul>
    </div>
</body>
</html>
