@props(['icon' => 'cube', 'title' => __('Nothing here yet'), 'text' => null])
<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center px-6 py-14 text-center']) }}>
    <span class="flex size-12 items-center justify-center rounded-full bg-slate-100 text-slate-400"><x-icon :name="$icon" class="size-6" /></span>
    <p class="mt-3 text-sm font-semibold text-slate-700">{{ $title }}</p>
    @if ($text)
        <p class="mt-1 max-w-sm text-sm text-slate-500">{{ $text }}</p>
    @endif
    {{ $slot }}
</div>
