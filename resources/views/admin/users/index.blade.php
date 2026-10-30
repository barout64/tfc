@extends('layouts.app', ['title' => 'People & access'])

@section('content')
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">People &amp; access</h1>
            <p class="mt-1 text-sm text-slate-500">Everyone who signs in to the TFC Portal, and the systems each person may open.</p>
        </div>
        <div class="flex gap-2">
            <form method="GET" class="flex gap-2">
                <input name="q" value="{{ $q }}" placeholder="Search name or email" class="field w-56">
                <button class="btn-light">Search</button>
            </form>
            <a href="{{ route('admin.users.create') }}" class="btn-primary">+ Add person</a>
        </div>
    </div>

    <div class="card overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-50 text-xs font-semibold tracking-wide text-slate-500 uppercase">
                <tr>
                    <th class="px-4 py-3">Person</th>
                    @foreach ($systems as $s)<th class="px-4 py-3">{{ $s['name'] }}</th>@endforeach
                    <th class="px-4 py-3">Last sign-in</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($users as $u)
                    <tr class="{{ $u->is_active ? '' : 'bg-slate-50 text-slate-400' }}">
                        <td class="px-4 py-3">
                            <p class="font-semibold {{ $u->is_active ? 'text-slate-900' : '' }}">{{ $u->name }}
                                @if ($u->is_admin)<span class="ml-1 rounded bg-harvest-300/40 px-1.5 py-0.5 text-[11px] font-semibold text-amber-800">Admin</span>@endif
                                @unless ($u->is_active)<span class="ml-1 rounded bg-slate-200 px-1.5 py-0.5 text-[11px] font-semibold text-slate-600">Off</span>@endunless
                            </p>
                            <p class="text-xs text-slate-500">{{ $u->email }}</p>
                        </td>
                        @foreach ($systems as $key => $s)
                            @php($link = $u->linkTo($key))
                            <td class="px-4 py-3">
                                @if ($link)<span class="inline-flex items-center gap-1.5 text-brand-700"><span class="size-1.5 rounded-full bg-brand-500"></span>{{ $link->remote_name }}</span>
                                @else<span class="text-slate-300">—</span>@endif
                            </td>
                        @endforeach
                        <td class="px-4 py-3 text-xs text-slate-500">{{ $u->last_login_at?->timezone('Africa/Dar_es_Salaam')->format('d M Y H:i') ?? 'Never' }}</td>
                        <td class="px-4 py-3 text-right"><a href="{{ route('admin.users.edit', $u) }}" class="font-semibold text-brand-700 hover:underline">Edit</a></td>
                    </tr>
                @empty
                    <tr><td colspan="{{ count($systems) + 3 }}" class="px-4 py-10 text-center text-slate-500">No one found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $users->links() }}</div>
@endsection
