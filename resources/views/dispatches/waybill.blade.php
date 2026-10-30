<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ __('Waybill') }} {{ $dispatch->number }}</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-100 p-6 font-sans text-sm text-slate-800 print:bg-white print:p-0">
<div class="mx-auto max-w-3xl bg-white p-8 shadow print:shadow-none">
    <div class="flex items-start justify-between border-b-2 border-brand-700 pb-4">
        <img src="{{ asset('images/coat-of-arms-sm.png') }}" alt="" class="mr-4 h-20 w-auto">
        <div class="flex-1">
            <p class="text-lg font-bold text-brand-800">TANZANIA FERTILIZER COMPANY</p>
            <p class="text-xs text-slate-500">Sukari House, Sokoine/Ohio, Dar es Salaam · {{ config('tfc.hotline') }}</p>
            <p class="mt-3 text-2xl font-semibold">{{ __('WAYBILL / DELIVERY NOTE') }}</p>
            <p class="font-mono text-base">{{ $dispatch->number }}</p>
        </div>
        <div class="text-center"><canvas id="qr"></canvas><p class="text-[10px] text-slate-500">{{ __('Scan to track / confirm') }}</p></div>
    </div>
    <div class="mt-5 grid grid-cols-2 gap-6">
        <div><p class="text-xs font-semibold text-slate-500 uppercase">{{ __('From') }}</p><p class="font-medium">{{ $dispatch->fromWarehouse->name }}</p><p>{{ $dispatch->fromWarehouse->region->name }}</p></div>
        <div><p class="text-xs font-semibold text-slate-500 uppercase">{{ __('To') }}</p><p class="font-medium">{{ $dispatch->toWarehouse->name }}</p><p>{{ $dispatch->toWarehouse->region->name }} · {{ $dispatch->toWarehouse->contact_phone }}</p></div>
        <div><p class="text-xs font-semibold text-slate-500 uppercase">{{ __('Transporter') }}</p><p>{{ $dispatch->transporter?->name }}</p><p>{{ __('Vehicle') }}: {{ $dispatch->vehicle?->plate_number }}</p></div>
        <div><p class="text-xs font-semibold text-slate-500 uppercase">{{ __('Driver') }}</p><p>{{ $dispatch->driver?->name }} · {{ $dispatch->driver?->phone }}</p><p>{{ __('Licence') }}: {{ $dispatch->driver?->license_number }}</p></div>
    </div>
    <table class="mt-6 w-full border border-slate-300 text-left">
        <thead class="bg-slate-100"><tr><th class="border p-2">#</th><th class="border p-2">{{ __('Product') }}</th><th class="border p-2">{{ __('Batch') }}</th><th class="border p-2 text-right">{{ __('Bags') }}</th><th class="border p-2 text-right">{{ __('Tonnes') }}</th><th class="border p-2">{{ __('Received') }}</th></tr></thead>
        <tbody>
        @foreach ($dispatch->items as $item)
            <tr><td class="border p-2">{{ $loop->iteration }}</td><td class="border p-2">{{ $item->batch->product->name }} ({{ $item->batch->product->package_kg }}kg)</td><td class="border p-2 font-mono text-xs">{{ $item->batch->batch_number }}</td><td class="border p-2 text-right">{{ number_format($item->bags_sent) }}</td><td class="border p-2 text-right">{{ number_format($item->batch->product->bagsToTonnes($item->bags_sent), 2) }}</td><td class="border p-2"></td></tr>
        @endforeach
        <tr class="font-semibold"><td colspan="3" class="border p-2 text-right">{{ __('Total') }}</td><td class="border p-2 text-right">{{ number_format($dispatch->totalBags()) }}</td><td class="border p-2 text-right">{{ number_format($dispatch->totalTonnes(), 2) }}</td><td class="border p-2"></td></tr>
        </tbody>
    </table>
    <div class="mt-10 grid grid-cols-3 gap-6 text-xs">
        @foreach ([__('Dispatched by (warehouse)'), __('Driver'), __('Received by')] as $sig)
            <div><div class="h-12 border-b border-slate-400"></div><p class="mt-1">{{ $sig }}</p><p class="text-slate-500">{{ __('Name, signature & date') }}</p></div>
        @endforeach
    </div>
    <p class="mt-8 text-[10px] text-slate-400">{{ __('Generated') }} {{ now()->format('d M Y H:i') }} · {{ __('Planned') }} {{ $dispatch->planned_date?->format('d M Y') }} · {{ $dispatch->distance_km }} km</p>
    <div class="no-print mt-6 text-right"><button onclick="window.print()" class="btn-primary">{{ __('Print') }}</button></div>
</div>
<script type="module">
    QRCode.toCanvas(document.getElementById('qr'), {{ \Illuminate\Support\Js::from(route('trip.show', $dispatch->tracking_token)) }}, { width: 110, margin: 1 });
</script>
</body>
</html>
