<x-layouts.app :title="__('Audit log')">
    <x-page-header :title="__('Audit log')" :subtitle="__('Who did what and when — for internal audit and the CAG.')" />
    <form class="card mb-4 grid gap-3 p-4 sm:grid-cols-3">
        <input name="action" value="{{ request('action') }}" placeholder="{{ __('Action, e.g. dispatch, sale, dealer') }}" class="input">
        <button class="btn-secondary"><x-icon name="search" class="size-4" />{{ __('Filter') }}</button>
    </form>
    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>{{ __('Time') }}</th><th>{{ __('User') }}</th><th>{{ __('Action') }}</th><th>{{ __('Description') }}</th><th>IP</th></tr></thead>
            <tbody>
            @forelse ($logs as $log)
                <tr><td class="text-xs whitespace-nowrap">{{ $log->created_at->format('d M Y H:i:s') }}</td><td>{{ $log->user?->name ?? __('System / public') }}</td><td><x-badge>{{ $log->action }}</x-badge></td><td class="text-sm">{{ $log->description }}</td><td class="text-xs text-slate-500">{{ $log->ip_address }}</td></tr>
            @empty
                <tr><td colspan="5"><x-empty icon="clipboard" :title="__('No activity')" /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $logs->links() }}</div>
</x-layouts.app>
