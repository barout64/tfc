<x-layouts.app :title="__('Reports')">
    <x-page-header :title="__('Reports')" :subtitle="__('Every report can be filtered, printed or exported to Excel (CSV).')" />
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @foreach ($reports as $key => $r)
            <a href="{{ route('reports.show', $key) }}" class="card group flex gap-4 p-5 transition hover:border-brand-300 hover:shadow">
                <span class="flex size-11 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-brand-700 group-hover:bg-brand-600 group-hover:text-white"><x-icon :name="['warehouse' => 'warehouse', 'cart' => 'cart', 'map' => 'map', 'target' => 'target', 'coins' => 'coins', 'truck' => 'truck', 'star' => 'star', 'users' => 'users', 'ticket' => 'ticket', 'chat' => 'chat'][$r['icon']]" class="size-6" /></span>
                <span><span class="block font-semibold text-slate-900">{{ __($r['title']) }}</span><span class="mt-0.5 block text-sm text-slate-500">{{ __($r['description']) }}</span></span>
            </a>
        @endforeach
    </div>
</x-layouts.app>
