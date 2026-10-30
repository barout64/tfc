<x-layouts.public :title="__('Farming advice')">
    <div class="mx-auto max-w-5xl px-4 py-10 sm:px-6">
        <h1 class="text-3xl font-semibold text-slate-900">{{ __('Farming advice') }}</h1>
        <div class="mt-8 grid gap-6 md:grid-cols-2">
            @forelse ($advisories as $a)
                <article class="rounded-2xl border border-slate-200 p-6">
                    <div class="flex flex-wrap gap-2">@if ($a->crop)<x-badge color="green">{{ $a->crop->name }}</x-badge>@endif @if ($a->region)<x-badge color="blue">{{ $a->region->name }}</x-badge>@endif</div>
                    <h2 class="mt-3 text-lg font-semibold">{{ $a->title }}</h2>
                    <p class="mt-2 text-sm whitespace-pre-line text-slate-600">{{ $a->body }}</p>
                    <p class="mt-4 text-xs text-slate-400">{{ $a->published_at->format('d M Y') }}</p>
                </article>
            @empty
                <p class="text-slate-500">{{ __('No advice published yet.') }}</p>
            @endforelse
        </div>
        <div class="mt-6">{{ $advisories->links() }}</div>
    </div>
</x-layouts.public>
