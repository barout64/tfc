<x-layouts.app :title="$complaint->ticket_number">
    <x-page-header :title="__('Complaint :ticket', ['ticket' => $complaint->ticket_number])" :subtitle="$complaint->category->label().' · '.__('via').' '.strtoupper($complaint->channel).' · '.$complaint->created_at->format('d M Y H:i')" />
    <div class="grid gap-6 xl:grid-cols-3">
        <div class="card p-6 xl:col-span-2">
            <p class="text-xs font-semibold text-slate-500 uppercase">{{ __('Description') }}</p>
            <p class="mt-2 whitespace-pre-line text-slate-800">{{ $complaint->description }}</p>
            <div class="mt-6 grid gap-4 border-t border-slate-100 pt-4 text-sm sm:grid-cols-3">
                <div><p class="text-xs text-slate-500">{{ __('Name') }}</p><p>@if ($complaint->farmer)<a class="link" href="{{ route('farmers.show', $complaint->farmer) }}">{{ $complaint->farmer->fullName() }}</a>@else{{ $complaint->name ?? '—' }}@endif</p></div>
                <div><p class="text-xs text-slate-500">{{ __('Phone') }}</p><p>{{ $complaint->phone }}</p></div>
                <div><p class="text-xs text-slate-500">{{ __('Region') }}</p><p>{{ $complaint->region?->name ?? '—' }}</p></div>
            </div>
            @if ($complaint->resolution)
                <div class="mt-6 rounded-lg bg-emerald-50 p-4 text-sm"><p class="font-medium text-emerald-800">{{ __('Resolution') }}</p><p class="mt-1 text-emerald-900">{{ $complaint->resolution }}</p></div>
            @endif
        </div>
        <form method="POST" action="{{ route('complaints.update', $complaint) }}" class="card space-y-4 p-6">
            @csrf @method('PUT')
            <x-field :label="__('Status')" name="status" :value="$complaint->status" :options="$statuses" required />
            <x-field :label="__('Assign to')" name="assigned_to" :value="$complaint->assigned_to" :options="$staff->pluck('name', 'id')" />
            <x-field :label="__('Resolution / action taken')" name="resolution" type="textarea" :value="$complaint->resolution" />
            <label class="flex items-center gap-2 text-sm"><input type="hidden" name="notify" value="0"><input type="checkbox" name="notify" value="1" checked class="rounded border-slate-300 text-brand-600"> {{ __('SMS the farmer when resolved') }}</label>
            <button class="btn-primary w-full">{{ __('Update complaint') }}</button>
        </form>
    </div>
</x-layouts.app>
