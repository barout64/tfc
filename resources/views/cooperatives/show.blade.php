<x-layouts.app :title="$cooperative->name">
    <x-page-header :title="$cooperative->name" :subtitle="$cooperative->region->name.' · '.($cooperative->crop?->name ?? '').' · '.number_format($cooperative->members_count).' '.__('members')" />
    <div class="grid gap-6 xl:grid-cols-2">
        <div class="card">
            <div class="card-header"><h2 class="card-title">{{ __('Bulk purchases') }}</h2></div>
            <table class="table"><thead><tr><th>{{ __('Sale') }}</th><th>{{ __('Date') }}</th><th>{{ __('From') }}</th><th class="num">{{ __('Bags') }}</th><th class="num">TZS</th></tr></thead><tbody>
            @forelse ($sales as $s)<tr><td><a class="link" href="{{ route('sales.show', $s) }}">{{ $s->number }}</a></td><td class="text-xs">{{ $s->sold_at->format('d M Y') }}</td><td>{{ $s->warehouse->name }}</td><td class="num">{{ number_format($s->total_bags) }}</td><td class="num">{{ number_format($s->total_amount) }}</td></tr>
            @empty<tr><td colspan="5" class="text-center text-slate-500">{{ __('No purchases') }}</td></tr>@endforelse
            </tbody></table>
        </div>
        <div class="card">
            <div class="card-header"><h2 class="card-title">{{ __('Member farmers in the system') }}</h2></div>
            <table class="table"><thead><tr><th>{{ __('Farmer') }}</th><th>{{ __('Village') }}</th><th class="num">{{ __('Acres') }}</th></tr></thead><tbody>
            @forelse ($farmers as $f)<tr><td><a class="link" href="{{ route('farmers.show', $f) }}">{{ $f->fullName() }}</a></td><td>{{ $f->village }}</td><td class="num">{{ $f->farm_size_acres + 0 }}</td></tr>
            @empty<tr><td colspan="3" class="text-center text-slate-500">{{ __('No farmers linked') }}</td></tr>@endforelse
            </tbody></table>
        </div>
    </div>
</x-layouts.app>
