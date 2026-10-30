<x-layouts.app :title="__('Advisories')">
    <x-page-header :title="__('Farming advisories')" :subtitle="__('Publish fertilizer advice on the portal and push it by SMS to the farmers it concerns.')" />
    <div class="grid gap-6 xl:grid-cols-3">
        <form method="POST" action="{{ route('advisories.store') }}" class="card space-y-4 p-6" x-data="{ sms: '' }">
            @csrf
            <h2 class="card-title">{{ __('New advisory') }}</h2>
            <x-field :label="__('Title')" name="title" required />
            <x-field :label="__('Full advice (web)')" name="body" type="textarea" required />
            <div><label class="label">{{ __('SMS version') }}</label><textarea name="sms_text" x-model="sms" maxlength="320" rows="3" class="input"></textarea><p class="mt-1 text-xs text-slate-500"><span x-text="sms.length"></span>/320 · <span x-text="Math.max(1, Math.ceil(sms.length / 160))"></span> SMS</p></div>
            <x-field :label="__('Only farmers growing')" name="crop_id" :options="$crops->pluck('name', 'id')" :placeholder="__('All crops')" />
            <x-field :label="__('Only farmers in')" name="region_id" :options="$regions->pluck('name', 'id')" :placeholder="__('All regions')" />
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="send_sms" value="1" class="rounded border-slate-300 text-brand-600"> {{ __('Send SMS now') }}</label>
            <button class="btn-primary w-full"><x-icon name="megaphone" class="size-4" />{{ __('Publish') }}</button>
        </form>
        <div class="space-y-4 xl:col-span-2">
            @forelse ($advisories as $a)
                <div class="card p-5">
                    <div class="flex items-start justify-between gap-3">
                        <h3 class="font-semibold text-slate-900">{{ $a->title }}</h3>
                        <span class="text-xs whitespace-nowrap text-slate-500">{{ $a->published_at?->format('d M Y') }}</span>
                    </div>
                    <p class="mt-2 text-sm text-slate-600">{{ str($a->body)->limit(300) }}</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        @if ($a->crop)<x-badge color="green">{{ $a->crop->name }}</x-badge>@endif
                        @if ($a->region)<x-badge color="blue">{{ $a->region->name }}</x-badge>@endif
                        @if ($a->sms_recipients)<x-badge color="violet">{{ number_format($a->sms_recipients) }} SMS</x-badge>@endif
                    </div>
                </div>
            @empty
                <div class="card"><x-empty icon="megaphone" :title="__('No advisories yet')" /></div>
            @endforelse
            {{ $advisories->links() }}
        </div>
    </div>
</x-layouts.app>
