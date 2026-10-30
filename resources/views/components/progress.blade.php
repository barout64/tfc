@props(['percent', 'color' => null])
@php
    $percent = max(0, min(100, (int) $percent));
    $color ??= $percent >= 80 ? 'bg-brand-500' : ($percent >= 50 ? 'bg-harvest-500' : 'bg-red-500');
@endphp
<div {{ $attributes->merge(['class' => 'h-2 w-full overflow-hidden rounded-full bg-slate-100']) }}>
    <div class="h-full rounded-full {{ $color }}" style="width: {{ $percent }}%"></div>
</div>
