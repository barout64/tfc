<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('Trip') }} {{ $dispatch->number }}</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-100 font-sans text-slate-800">
<div class="mx-auto max-w-md p-4" x-data="tripPage()">
    <div class="rounded-2xl bg-brand-900 p-5 text-white">
        <p class="text-xs text-brand-300">TFC · {{ __('Driver trip page') }}</p>
        <p class="mt-1 text-xl font-semibold">{{ $dispatch->number }}</p>
        <p class="mt-2 text-sm">{{ $dispatch->fromWarehouse->name }} → <strong>{{ $dispatch->toWarehouse->name }}</strong></p>
        <p class="text-xs text-brand-200">{{ $dispatch->vehicle?->plate_number }} · {{ $dispatch->driver?->name }} · {{ number_format($dispatch->totalBags()) }} {{ __('bags') }}</p>
        <p class="mt-3 inline-block rounded-full bg-white/15 px-3 py-1 text-xs">{{ $dispatch->status->label() }} @if ($dispatch->expected_arrival_at) · ETA {{ $dispatch->expected_arrival_at->format('d M H:i') }} @endif</p>
    </div>

    @if (session('success'))<div class="mt-4 rounded-xl bg-emerald-100 p-3 text-sm text-emerald-800">{{ session('success') }}</div>@endif

    @if ($dispatch->status->value === 'in_transit')
        <div class="mt-4 rounded-2xl bg-white p-5 shadow-sm">
            <p class="font-semibold">{{ __('Send an update') }}</p>
            <p class="text-xs text-slate-500">{{ __('Your GPS location is attached automatically.') }}</p>
            <div class="mt-4 grid grid-cols-2 gap-3">
                @foreach (['checkpoint' => [__('Checkpoint'), 'bg-brand-600'], 'rest' => [__('Rest stop'), 'bg-sky-600'], 'delay' => [__('Delay'), 'bg-amber-500'], 'breakdown' => [__('Breakdown'), 'bg-red-600']] as $event => [$label, $color])
                    <button type="button" @click="send('{{ $event }}')" :disabled="busy" class="{{ $color }} rounded-xl py-4 text-sm font-semibold text-white disabled:opacity-50">{{ $label }}</button>
                @endforeach
                <button type="button" @click="send('arrived')" :disabled="busy" class="col-span-2 rounded-xl bg-slate-900 py-4 text-sm font-semibold text-white disabled:opacity-50">{{ __('Arrived at destination') }}</button>
            </div>
            <input x-model="note" class="input mt-3" placeholder="{{ __('Note (optional)') }}">
            <label class="mt-3 flex items-center gap-2 text-sm"><input type="checkbox" x-model="auto" @change="toggleAuto()" class="rounded"> {{ __('Share location automatically every 10 min') }}</label>
            <p class="mt-3 text-sm" :class="error ? 'text-red-600' : 'text-emerald-700'" x-text="message"></p>
        </div>
    @endif

    <div class="mt-4 rounded-2xl bg-white p-5 shadow-sm">
        <p class="font-semibold">{{ __('Trip history') }}</p>
        <ol class="mt-3 space-y-3 text-sm">
            @foreach ($dispatch->events as $e)
                <li><span class="font-medium">{{ __(ucfirst($e->event)) }}</span> <span class="text-xs text-slate-500">{{ $e->recorded_at->format('d M H:i') }}</span>@if ($e->note)<br><span class="text-slate-600">{{ $e->note }}</span>@endif</li>
            @endforeach
        </ol>
    </div>
    <p class="mt-4 text-center text-xs text-slate-500">{{ __('Problem? Call') }} {{ config('tfc.hotline') }}</p>
</div>
<script>
    function tripPage() {
        return {
            busy: false, note: '', message: '', error: false, auto: false, timer: null,
            position() {
                return new Promise(resolve => {
                    if (!navigator.geolocation) return resolve({});
                    navigator.geolocation.getCurrentPosition(p => resolve({ lat: p.coords.latitude, lng: p.coords.longitude }), () => resolve({}), { enableHighAccuracy: true, timeout: 15000 });
                });
            },
            async send(event) {
                this.busy = true; this.error = false; this.message = '{{ __('Sending…') }}';
                const pos = await this.position();
                try {
                    const res = await fetch('{{ route('trip.update', $dispatch->tracking_token) }}', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                        body: JSON.stringify({ event, note: this.note, ...pos }),
                    });
                    if (!res.ok) throw new Error();
                    this.message = '{{ __('Update sent. Thank you!') }}'; this.note = '';
                } catch (e) { this.error = true; this.message = '{{ __('Could not send. Check your network and try again.') }}'; }
                this.busy = false;
            },
            toggleAuto() {
                clearInterval(this.timer);
                if (this.auto) { this.send('checkpoint'); this.timer = setInterval(() => this.send('checkpoint'), 600000); }
            },
        };
    }
</script>
</body>
</html>
