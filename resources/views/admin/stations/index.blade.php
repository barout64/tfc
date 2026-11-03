@extends('layouts.app', ['title' => 'Stations'])

@section('content')
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Stations (vituo)</h1>
            <p class="mt-1 text-sm text-slate-500">The stations that submit a Daily Report. Tie a person to one of these in People &amp; access so they can report for it.</p>
        </div>
        <a href="{{ route('admin.stations.create') }}" class="btn-primary">+ Add station</a>
    </div>

    <div class="card overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-50 text-xs font-semibold tracking-wide text-slate-500 uppercase">
                <tr>
                    <th class="px-4 py-3">Station</th>
                    <th class="px-4 py-3">Location</th>
                    <th class="px-4 py-3">People</th>
                    <th class="px-4 py-3"></th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($stations as $st)
                    <tr class="{{ $st->is_active ? '' : 'bg-slate-50 text-slate-400' }}">
                        <td class="px-4 py-3 font-semibold {{ $st->is_active ? 'text-slate-900' : '' }}">{{ $st->name }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ $st->location ?: '—' }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ $st->users_count }}</td>
                        <td class="px-4 py-3">@unless ($st->is_active)<span class="rounded bg-slate-200 px-1.5 py-0.5 text-[11px] font-semibold text-slate-600">Off</span>@endunless</td>
                        <td class="px-4 py-3 text-right"><a href="{{ route('admin.stations.edit', $st) }}" class="font-semibold text-brand-700 hover:underline">Edit</a></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-10 text-center text-slate-500">No stations yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
