<x-layouts.app :title="__('Users')">
    <x-page-header :title="__('Users & roles')" :subtitle="__('Staff, agro-dealer and transporter accounts. Each role sees only what it needs.')">
        <x-slot:actions><a href="{{ route('users.create') }}" class="btn-primary"><x-icon name="plus" class="size-4" />{{ __('Add user') }}</a></x-slot:actions>
    </x-page-header>
    <form class="card mb-4 grid gap-3 p-4 sm:grid-cols-3">
        <input name="q" value="{{ request('q') }}" placeholder="{{ __('Name or email') }}" class="input">
        <select name="role" class="input"><option value="">{{ __('All roles') }}</option>@foreach ($roles as $v => $l)<option value="{{ $v }}" @selected(request('role') === $v)>{{ $l }}</option>@endforeach</select>
        <button class="btn-secondary"><x-icon name="search" class="size-4" />{{ __('Filter') }}</button>
    </form>
    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Role') }}</th><th>{{ __('Assigned to') }}</th><th>{{ __('Last login') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
            <tbody>
            @foreach ($users as $u)
                <tr>
                    <td>{{ $u->name }}<p class="text-xs text-slate-500">{{ $u->email }}</p></td>
                    <td><x-badge color="violet">{{ $u->role->label() }}</x-badge></td>
                    <td class="text-xs">{{ $u->warehouse?->name ?? $u->agroDealer?->business_name ?? $u->transporter?->name ?? __('All') }}</td>
                    <td class="text-xs">{{ $u->last_login_at?->diffForHumans() ?? __('Never') }}</td>
                    <td><x-badge :color="$u->is_active ? 'green' : 'red'">{{ $u->is_active ? __('Active') : __('Disabled') }}</x-badge></td>
                    <td class="text-right"><a href="{{ route('users.edit', $u) }}" class="btn-ghost btn-sm"><x-icon name="pencil" class="size-4" /></a></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $users->links() }}</div>
</x-layouts.app>
