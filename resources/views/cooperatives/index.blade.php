<x-layouts.app :title="__('Cooperatives')">
    <x-page-header :title="__('Cooperatives (AMCOS)')" :subtitle="__('Bulk buyers such as tobacco, cotton and sugarcane cooperatives.')">
        <x-slot:actions>@if (auth()->user()->hasAbility('farmers.manage'))<a href="{{ route('cooperatives.create') }}" class="btn-primary"><x-icon name="plus" class="size-4" />{{ __('Add cooperative') }}</a>@endif</x-slot:actions>
    </x-page-header>
    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>{{ __('Cooperative') }}</th><th>{{ __('Region') }}</th><th>{{ __('Crop') }}</th><th>{{ __('Chairperson') }}</th><th class="num">{{ __('Members') }}</th><th class="num">{{ __('Registered farmers') }}</th><th class="num">{{ __('Bags bought') }}</th></tr></thead>
            <tbody>
            @forelse ($cooperatives as $c)
                <tr>
                    <td><a href="{{ route('cooperatives.show', $c) }}" class="link">{{ $c->name }}</a><p class="text-xs text-slate-500">{{ $c->registration_no }}</p></td>
                    <td>{{ $c->region->name }}</td><td>{{ $c->crop?->name }}</td><td>{{ $c->chairperson }}<p class="text-xs text-slate-500">{{ $c->phone }}</p></td>
                    <td class="num">{{ number_format($c->members_count) }}</td><td class="num">{{ number_format($c->farmers_count) }}</td><td class="num">{{ number_format($c->sales_sum_total_bags ?? 0) }}</td>
                </tr>
            @empty
                <tr><td colspan="7"><x-empty icon="group" :title="__('No cooperatives')" /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $cooperatives->links() }}</div>
</x-layouts.app>
