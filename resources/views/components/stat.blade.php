@props(['label', 'value', 'sub' => null, 'icon' => 'cube', 'tone' => 'brand', 'href' => null])
@php
    $tones = [
        'brand' => 'bg-brand-50 text-brand-700',
        'blue' => 'bg-sky-50 text-sky-700',
        'amber' => 'bg-amber-50 text-amber-700',
        'violet' => 'bg-violet-50 text-violet-700',
        'red' => 'bg-red-50 text-red-700',
    ];
@endphp
<{{ $href ? 'a' : 'div' }} @if($href) href="{{ $href }}" @endif {{ $attributes->merge(['class' => 'card flex items-start gap-4 p-5'.($href ? ' transition hover:border-brand-300 hover:shadow' : '')]) }}>
    <span class="flex size-11 shrink-0 items-center justify-center rounded-lg {{ $tones[$tone] ?? $tones['brand'] }}">
        <x-icon :name="$icon" class="size-6" />
    </span>
    <div class="min-w-0">
        <p class="text-sm text-slate-500">{{ $label }}</p>
        <p class="mt-0.5 text-2xl font-semibold tracking-tight text-slate-900 tabular-nums">{{ $value }}</p>
        @if ($sub)
            <p class="mt-0.5 truncate text-xs text-slate-500">{{ $sub }}</p>
        @endif
        {{ $slot }}
    </div>
</{{ $href ? 'a' : 'div' }}>
