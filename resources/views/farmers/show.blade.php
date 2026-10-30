<x-layouts.app :title="$farmer->fullName()">
    <x-page-header :title="$farmer->fullName()" :subtitle="$farmer->farmer_number.' · '.$farmer->phone.' · '.$farmer->region->name.($farmer->village ? ', '.$farmer->village : '')">
        <x-slot:actions>
            @if (auth()->user()->hasAbility('farmers.manage'))
                <form method="POST" action="{{ route('farmers.entitlements.refresh', $farmer) }}">@csrf<button class="btn-secondary">{{ __('Recalculate allowance') }}</button></form>
                <a href="{{ route('farmers.edit', $farmer) }}" class="btn-secondary"><x-icon name="pencil" class="size-4" />{{ __('Edit') }}</a>
            @endif
            @if (auth()->user()->hasAbility('sales.manage'))<a href="{{ route('sales.create') }}" class="btn-primary"><x-icon name="cart" class="size-4" />{{ __('Sell') }}</a>@endif
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            <div class="card">
                <div class="card-header"><h2 class="card-title">{{ __('Subsidy allowance — season :season', ['season' => \App\Models\Season::current()->name]) }}</h2></div>
                <div class="grid gap-4 p-5 sm:grid-cols-3">
                    @forelse ($farmer->entitlements as $e)
                        <div class="rounded-lg border border-slate-200 p-4">
                            <p class="text-sm font-semibold">{{ $e->product->code }}</p>
                            <p class="mt-1 text-2xl font-semibold tabular-nums">{{ $remaining[$e->product_id] }} <span class="text-sm font-normal text-slate-500">/ {{ $e->bags_allowed }} {{ __('bags') }}</span></p>
                            <x-progress class="mt-2" :percent="$e->bags_allowed ? $e->bags_used / $e->bags_allowed * 100 : 0" color="bg-harvest-500" />
                            <p class="mt-1 text-xs text-slate-500">{{ $e->bags_used }} {{ __('used') }}</p>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500 sm:col-span-3">{{ __('No allowance. Check that the main crop has fertilizer recommendations.') }}</p>
                    @endforelse
                </div>
                @if (auth()->user()->hasAbility('farmers.manage') && $farmer->entitlements->isNotEmpty())
                    <form method="POST" action="{{ route('farmers.vouchers.store', $farmer) }}" class="flex flex-wrap items-end gap-3 border-t border-slate-100 p-5">
                        @csrf
                        <div><label class="label">{{ __('Issue e-voucher for') }}</label><select name="product_id" class="input">@foreach ($farmer->entitlements as $e)<option value="{{ $e->product_id }}">{{ $e->product->code }}</option>@endforeach</select></div>
                        <div><label class="label">{{ __('Bags') }}</label><input type="number" name="bags" min="1" class="input w-28" required></div>
                        <button class="btn-primary"><x-icon name="ticket" class="size-4" />{{ __('Issue & SMS') }}</button>
                    </form>
                @endif
            </div>

            <div class="card">
                <div class="card-header"><h2 class="card-title">{{ __('Purchases') }}</h2></div>
                <table class="table"><thead><tr><th>{{ __('Sale') }}</th><th>{{ __('Date') }}</th><th>{{ __('Where') }}</th><th>{{ __('Products') }}</th><th class="num">{{ __('Bags') }}</th><th class="num">TZS</th></tr></thead><tbody>
                @forelse ($sales as $s)
                    <tr><td><a href="{{ route('sales.show', $s) }}" class="link">{{ $s->number }}</a></td><td class="text-xs">{{ $s->sold_at->format('d M Y') }}</td><td>{{ $s->warehouse->name }}</td><td class="text-xs">{{ $s->items->pluck('product.code')->unique()->implode(', ') }}</td><td class="num">{{ $s->total_bags }}</td><td class="num">{{ number_format($s->total_amount) }}</td></tr>
                @empty<tr><td colspan="6" class="text-center text-slate-500">{{ __('No purchases yet') }}</td></tr>@endforelse
                </tbody></table>
            </div>

            <div class="card">
                <div class="card-header"><h2 class="card-title">{{ __('E-vouchers') }}</h2></div>
                <table class="table"><thead><tr><th>{{ __('Code') }}</th><th>{{ __('Product') }}</th><th class="num">{{ __('Bags') }}</th><th>{{ __('Expires') }}</th><th>{{ __('Status') }}</th></tr></thead><tbody>
                @forelse ($vouchers as $v)
                    <tr><td class="font-mono font-semibold">{{ $v->code }}</td><td>{{ $v->product->code }}</td><td class="num">{{ $v->bags }}</td><td class="text-xs">{{ $v->expires_at?->format('d M Y') }}</td><td><x-badge :color="$v->status->color()">{{ $v->status->label() }}</x-badge></td></tr>
                @empty<tr><td colspan="5" class="text-center text-slate-500">{{ __('No vouchers') }}</td></tr>@endforelse
                </tbody></table>
            </div>
        </div>

        <div class="space-y-6">
            <div class="card p-5 text-sm">
                <dl class="space-y-2.5">
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">{{ __('Status') }}</dt><dd><x-badge :color="$farmer->status === 'active' ? 'green' : 'red'">{{ __(ucfirst($farmer->status)) }}</x-badge></dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">NIDA</dt><dd>{{ $farmer->nida_number ?: '—' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">{{ __('Gender') }}</dt><dd>{{ __(ucfirst($farmer->gender ?? '—')) }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">{{ __('Main crop') }}</dt><dd>{{ $farmer->mainCrop?->name }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">{{ __('Farm size') }}</dt><dd>{{ $farmer->farm_size_acres + 0 }} {{ __('acres') }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">{{ __('District') }}</dt><dd>{{ $farmer->district?->name ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">{{ __('Cooperative') }}</dt><dd class="text-right">{{ $farmer->cooperative?->name ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">{{ __('Registered') }}</dt><dd>{{ $farmer->created_at->format('d M Y') }} · {{ strtoupper($farmer->registered_via) }}</dd></div>
                </dl>
            </div>
            <div class="card">
                <div class="card-header"><h2 class="card-title">{{ __('Reservations') }}</h2></div>
                <ul class="divide-y divide-slate-100 text-sm">
                    @forelse ($reservations as $r)
                        <li class="px-5 py-3"><span class="font-mono font-semibold">{{ $r->code }}</span> · {{ $r->bags }} {{ $r->product->code }} @ {{ $r->warehouse->name }} <x-badge class="ml-1">{{ $r->status }}</x-badge></li>
                    @empty<li class="px-5 py-4 text-slate-500">{{ __('None') }}</li>@endforelse
                </ul>
            </div>
            <div class="card">
                <div class="card-header"><h2 class="card-title">{{ __('SMS sent') }}</h2></div>
                <ul class="divide-y divide-slate-100 text-sm">
                    @forelse ($messages as $m)
                        <li class="px-5 py-3"><p class="text-slate-700">{{ $m->message }}</p><p class="text-xs text-slate-400">{{ $m->created_at->format('d M H:i') }} · {{ $m->category }}</p></li>
                    @empty<li class="px-5 py-4 text-slate-500">{{ __('None') }}</li>@endforelse
                </ul>
            </div>
            @if ($complaints->isNotEmpty())
                <div class="card">
                    <div class="card-header"><h2 class="card-title">{{ __('Complaints') }}</h2></div>
                    <ul class="divide-y divide-slate-100 text-sm">@foreach ($complaints as $c)<li class="px-5 py-3"><a class="link" href="{{ route('complaints.show', $c) }}">{{ $c->ticket_number }}</a> · {{ $c->category->label() }} <x-badge :color="$c->status->color()">{{ $c->status->label() }}</x-badge></li>@endforeach</ul>
                </div>
            @endif
        </div>
    </div>
</x-layouts.app>
