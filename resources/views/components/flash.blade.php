@if (session('success'))
    <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 8000)" class="mb-5 flex items-start gap-3 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
        <x-icon name="check" class="mt-0.5 size-5 shrink-0" />
        <p class="flex-1">{{ session('success') }}</p>
        <button type="button" @click="show = false" class="text-emerald-600 hover:text-emerald-800"><x-icon name="x" class="size-4" /></button>
    </div>
@endif
@if (session('error'))
    <div class="mb-5 flex items-start gap-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
        <x-icon name="alert" class="mt-0.5 size-5 shrink-0" />
        <p class="flex-1">{{ session('error') }}</p>
    </div>
@endif
@if ($errors->any() && ! session('error'))
    <div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
        <p class="font-medium">{{ __('Please fix the highlighted fields:') }}</p>
        <ul class="mt-1 list-inside list-disc">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
