<x-layouts.app :title="__('SMS log')">
    <x-page-header :title="__('SMS log')" :subtitle="__('Gateway: :driver', ['driver' => strtoupper($driver)]).($driver === 'log' ? ' — '.__('messages are stored but not sent. Set SMS_DRIVER=beem in .env to go live.') : '')" />
    <form class="card mb-4 grid gap-3 p-4 sm:grid-cols-3">
        <input name="phone" value="{{ request('phone') }}" placeholder="{{ __('Phone') }}" class="input">
        <select name="category" class="input"><option value="">{{ __('All categories') }}</option>@foreach ($categories as $c)<option value="{{ $c }}" @selected(request('category') === $c)>{{ ucfirst($c) }}</option>@endforeach</select>
        <button class="btn-secondary"><x-icon name="search" class="size-4" />{{ __('Filter') }}</button>
    </form>
    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>{{ __('Time') }}</th><th>{{ __('Phone') }}</th><th>{{ __('Category') }}</th><th>{{ __('Message') }}</th><th>{{ __('Status') }}</th></tr></thead>
            <tbody>
            @forelse ($messages as $m)
                <tr><td class="text-xs whitespace-nowrap">{{ $m->created_at->format('d M H:i') }}</td><td class="text-xs">{{ $m->phone }}</td><td><x-badge>{{ $m->category }}</x-badge></td><td class="max-w-xl text-xs">{{ $m->message }}</td><td><x-badge :color="['sent' => 'green', 'logged' => 'blue', 'failed' => 'red'][$m->status] ?? 'gray'">{{ $m->status }}</x-badge></td></tr>
            @empty
                <tr><td colspan="5"><x-empty icon="phone" :title="__('No messages')" /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $messages->links() }}</div>
</x-layouts.app>
