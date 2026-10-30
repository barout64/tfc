<x-layouts.app :title="__('Complaints')">
    <x-page-header :title="__('Farmer complaints')" :subtitle="__('Received from the web portal, USSD, SMS and the call centre.')" />
    <div class="mb-4 flex flex-wrap gap-2">
        <a href="{{ route('complaints.index') }}" @class(['btn btn-sm', 'bg-slate-900 text-white' => ! request('status'), 'btn-secondary' => request('status')])>{{ __('All') }} <span class="opacity-60">{{ $counts->sum() }}</span></a>
        @foreach ($statuses as $v => $l)
            <a href="{{ route('complaints.index', ['status' => $v]) }}" @class(['btn btn-sm', 'bg-slate-900 text-white' => request('status') === $v, 'btn-secondary' => request('status') !== $v])>{{ $l }} <span class="opacity-60">{{ $counts[$v] ?? 0 }}</span></a>
        @endforeach
    </div>
    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>{{ __('Ticket') }}</th><th>{{ __('From') }}</th><th>{{ __('Category') }}</th><th>{{ __('Description') }}</th><th>{{ __('Channel') }}</th><th>{{ __('Assigned') }}</th><th>{{ __('Age') }}</th><th>{{ __('Status') }}</th></tr></thead>
            <tbody>
            @forelse ($complaints as $c)
                <tr>
                    <td><a href="{{ route('complaints.show', $c) }}" class="link">{{ $c->ticket_number }}</a></td>
                    <td>{{ $c->name ?? $c->farmer?->fullName() ?? '—' }}<p class="text-xs text-slate-500">{{ $c->phone }} · {{ $c->region?->name }}</p></td>
                    <td>{{ $c->category->label() }}</td>
                    <td class="max-w-72 truncate text-xs">{{ $c->description }}</td>
                    <td><x-badge>{{ strtoupper($c->channel) }}</x-badge></td>
                    <td class="text-xs">{{ $c->assignee?->name ?? '—' }}</td>
                    <td class="text-xs">{{ $c->created_at->diffForHumans(short: true) }}</td>
                    <td><x-badge :color="$c->status->color()">{{ $c->status->label() }}</x-badge></td>
                </tr>
            @empty
                <tr><td colspan="8"><x-empty icon="chat" :title="__('No complaints')" /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $complaints->links() }}</div>
</x-layouts.app>
