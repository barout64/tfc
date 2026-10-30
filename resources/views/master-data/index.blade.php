<x-layouts.app :title="__('Master data')">
    <x-page-header :title="__('Master data')" :subtitle="__('Products, seasons, crop fertilizer recommendations and suppliers.')" />
    <div class="grid gap-6 xl:grid-cols-2">
        <div class="card">
            <div class="card-header"><h2 class="card-title">{{ __('Products') }}</h2></div>
            <table class="table"><thead><tr><th>{{ __('Code') }}</th><th>{{ __('Name') }}</th><th>{{ __('Category') }}</th><th class="num">kg</th><th class="num">{{ __('Low-stock alert') }}</th></tr></thead><tbody>
            @foreach ($products as $p)<tr><td><span class="inline-flex items-center gap-2"><span class="size-2.5 rounded-full" style="background: {{ $p->color }}"></span>{{ $p->code }}</span></td><td>{{ $p->name }}<p class="text-xs text-slate-500">{{ $p->nutrients }}</p></td><td>{{ str_replace('_', ' ', $p->category) }}</td><td class="num">{{ $p->package_kg }}</td><td class="num">{{ number_format($p->low_stock_bags) }}</td></tr>@endforeach
            </tbody></table>
            <form method="POST" action="{{ route('master-data.products.store') }}" class="grid grid-cols-2 gap-2 border-t border-slate-100 p-4 sm:grid-cols-4">
                @csrf
                <input name="code" placeholder="{{ __('Code') }}" class="input" required><input name="name" placeholder="{{ __('Name') }}" class="input" required>
                <select name="category" class="input">@foreach (['planting', 'top_dressing', 'compound', 'lime', 'other'] as $c)<option value="{{ $c }}">{{ str_replace('_', ' ', $c) }}</option>@endforeach</select>
                <input name="nutrients" placeholder="N-P-K" class="input">
                <input name="package_kg" type="number" value="50" class="input" required><input name="low_stock_bags" type="number" value="1000" class="input" required>
                <input name="color" type="color" value="#16a34a" class="input h-10 p-1"><button class="btn-secondary">{{ __('Add product') }}</button>
            </form>
        </div>
        <div class="card">
            <div class="card-header"><h2 class="card-title">{{ __('Seasons') }}</h2></div>
            <table class="table"><thead><tr><th>{{ __('Season') }}</th><th>{{ __('Period') }}</th><th></th></tr></thead><tbody>
            @foreach ($seasons as $s)<tr><td class="font-medium">{{ $s->name }}</td><td class="text-xs">{{ $s->starts_on->format('d M Y') }} – {{ $s->ends_on->format('d M Y') }}</td><td class="text-right">@if ($s->is_active)<x-badge color="green">{{ __('Active') }}</x-badge>@else<form method="POST" action="{{ route('master-data.seasons.activate', $s) }}">@csrf<button class="btn-secondary btn-sm">{{ __('Activate') }}</button></form>@endif</td></tr>@endforeach
            </tbody></table>
            <form method="POST" action="{{ route('master-data.seasons.store') }}" class="grid grid-cols-4 gap-2 border-t border-slate-100 p-4">
                @csrf<input name="name" placeholder="2027/28" class="input" required><input type="date" name="starts_on" class="input" required><input type="date" name="ends_on" class="input" required><button class="btn-secondary">{{ __('Add') }}</button>
            </form>
        </div>
        <div class="card">
            <div class="card-header"><h2 class="card-title">{{ __('Crop recommendations (bags per acre)') }}</h2></div>
            <table class="table"><thead><tr><th>{{ __('Crop') }}</th><th>{{ __('Recommendations') }}</th></tr></thead><tbody>
            @foreach ($crops as $c)<tr><td class="font-medium">{{ $c->name }} <span class="text-xs text-slate-500">{{ $c->name_sw }}</span></td><td class="text-xs">@foreach ($c->recommendations as $r)<x-badge class="mr-1">{{ $r->product->code }} {{ $r->bags_per_acre + 0 }}/ac · {{ str_replace('_', ' ', $r->stage) }}</x-badge>@endforeach</td></tr>@endforeach
            </tbody></table>
            <form method="POST" action="{{ route('master-data.recommendations.store') }}" class="grid grid-cols-2 gap-2 border-t border-slate-100 p-4 sm:grid-cols-5">
                @csrf
                <input name="crop_name" placeholder="{{ __('Crop') }}" class="input" required>
                <select name="product_id" class="input">@foreach ($products as $p)<option value="{{ $p->id }}">{{ $p->code }}</option>@endforeach</select>
                <select name="stage" class="input"><option value="planting">{{ __('Planting') }}</option><option value="top_dressing">{{ __('Top dressing') }}</option></select>
                <input name="bags_per_acre" type="number" step="0.1" placeholder="{{ __('Bags/acre') }}" class="input" required>
                <button class="btn-secondary">{{ __('Save') }}</button>
            </form>
        </div>
        <div class="card">
            <div class="card-header"><h2 class="card-title">{{ __('Suppliers') }}</h2></div>
            <table class="table"><thead><tr><th>{{ __('Name') }}</th><th>{{ __('Country') }}</th><th>{{ __('Contact') }}</th></tr></thead><tbody>
            @foreach ($suppliers as $s)<tr><td>{{ $s->name }}</td><td>{{ $s->country }}</td><td class="text-xs">{{ $s->contact_person }} {{ $s->email }}</td></tr>@endforeach
            </tbody></table>
            <form method="POST" action="{{ route('master-data.suppliers.store') }}" class="grid grid-cols-2 gap-2 border-t border-slate-100 p-4 sm:grid-cols-4">
                @csrf<input name="name" placeholder="{{ __('Name') }}" class="input" required><input name="country" placeholder="{{ __('Country') }}" class="input"><input name="email" type="email" placeholder="Email" class="input"><button class="btn-secondary">{{ __('Add') }}</button>
            </form>
        </div>
    </div>
</x-layouts.app>
