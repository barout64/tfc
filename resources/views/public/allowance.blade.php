<x-layouts.public :title="__('My allowance')">
    <div class="mx-auto max-w-4xl px-4 py-10 sm:px-6">
        <h1 class="text-3xl font-semibold text-slate-900">{{ __('My subsidy allowance') }}</h1>
        <p class="mt-2 text-slate-600">{{ __('Enter your phone number and farmer number to see how many subsidised bags you can still buy.') }}</p>
        <form method="POST" action="{{ route('public.allowance') }}" class="mt-6 grid gap-3 rounded-2xl border border-slate-200 p-5 sm:grid-cols-3">
            @csrf
            <input name="phone" value="{{ old('phone', isset($farmer) ? '0'.substr($farmer->phone, 3) : '') }}" placeholder="0712345678" class="input" required>
            <input name="farmer_number" value="{{ old('farmer_number', $farmer->farmer_number ?? '') }}" placeholder="TFC-F-2026-000001" class="input" required>
            <button class="btn-primary">{{ __('Check') }}</button>
        </form>
        <p class="mt-2 text-sm text-slate-500">{{ __('Not registered?') }} <a class="link" href="{{ route('public.register') }}">{{ __('Register here') }}</a></p>

        @isset($farmer)
            <div class="mt-8 rounded-2xl border border-brand-200 bg-brand-50/40 p-6">
                <p class="text-lg font-semibold">{{ $farmer->fullName() }}</p>
                <p class="text-sm text-slate-600">{{ $farmer->farmer_number }} · {{ $farmer->region->name }} · {{ $farmer->mainCrop?->name }} · {{ $farmer->farm_size_acres + 0 }} {{ __('acres') }}</p>
                <div class="mt-5 grid gap-4 sm:grid-cols-3">
                    @forelse ($farmer->entitlements->where('season_id', $season->id) as $e)
                        <div class="rounded-xl bg-white p-4 ring-1 ring-slate-200"><p class="text-sm font-medium">{{ $e->product->name }}</p><p class="mt-1 text-3xl font-semibold text-brand-700">{{ $remaining[$e->product_id] }}</p><p class="text-xs text-slate-500">{{ __('of :total bags left', ['total' => $e->bags_allowed]) }}</p></div>
                    @empty
                        <p class="text-sm text-slate-600">{{ __('No allowance yet for this season.') }}</p>
                    @endforelse
                </div>
                @php $active = $farmer->vouchers->filter->isUsable(); @endphp
                @if ($active->isNotEmpty())
                    <p class="mt-5 text-sm font-semibold">{{ __('Your active vouchers') }}</p>
                    <div class="mt-2 flex flex-wrap gap-2">@foreach ($active as $v)<span class="rounded-lg bg-white px-3 py-2 text-sm ring-1 ring-slate-200"><span class="font-mono font-semibold">{{ $v->code }}</span> · {{ $v->bags }} {{ $v->product->code }} · {{ __('until') }} {{ $v->expires_at->format('d M') }}</span>@endforeach</div>
                @endif
            </div>

            @if ($nearby->isNotEmpty() && $remaining->sum() > 0)
                <form method="POST" action="{{ route('public.reserve') }}" class="mt-6 rounded-2xl border border-slate-200 p-6">
                    @csrf
                    <input type="hidden" name="phone" value="{{ $farmer->phone }}"><input type="hidden" name="farmer_number" value="{{ $farmer->farmer_number }}">
                    <h2 class="text-lg font-semibold">{{ __('Reserve bags at a sale point') }}</h2>
                    <p class="text-sm text-slate-600">{{ __('We hold them for :hours hours so you don\'t travel for nothing.', ['hours' => config('tfc.reservation_hours')]) }}</p>
                    <div class="mt-4 grid gap-3 sm:grid-cols-4">
                        <select name="warehouse_id" class="input sm:col-span-2" required>@foreach ($nearby as $w)<option value="{{ $w->id }}">{{ $w->name }}</option>@endforeach</select>
                        <select name="product_id" class="input" required>@foreach ($farmer->entitlements->where('season_id', $season->id) as $e)<option value="{{ $e->product_id }}">{{ $e->product->code }}</option>@endforeach</select>
                        <input type="number" name="bags" min="1" placeholder="{{ __('Bags') }}" class="input" required>
                    </div>
                    <button class="btn-primary mt-4">{{ __('Reserve') }}</button>
                </form>
            @endif
        @endisset
    </div>
</x-layouts.public>
