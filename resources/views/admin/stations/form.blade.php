@extends('layouts.app', ['title' => $station->exists ? $station->name : 'Add station'])

@section('content')
    <div class="mx-auto max-w-lg">
        <a href="{{ route('admin.stations.index') }}" class="text-sm text-slate-500 hover:text-slate-700">← Stations</a>
        <h1 class="mt-2 text-2xl font-bold text-slate-900">{{ $station->exists ? 'Edit '.$station->name : 'Add a station' }}</h1>

        <form method="POST" action="{{ $station->exists ? route('admin.stations.update', $station) : route('admin.stations.store') }}" class="card mt-6 space-y-5 p-6">
            @csrf
            @if ($station->exists) @method('PUT') @endif

            <div>
                <label class="label" for="name">Station name</label>
                <input class="field" id="name" name="name" value="{{ old('name', $station->name) }}" required>
                @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="label" for="location">Location (optional)</label>
                <input class="field" id="location" name="location" value="{{ old('location', $station->location) }}">
                @error('location')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <label class="flex items-center gap-2 text-sm">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $station->is_active ?? true)) class="rounded border-slate-300 text-brand-600">
                Active (can submit reports)
            </label>

            <div class="flex justify-end gap-2">
                <a href="{{ route('admin.stations.index') }}" class="btn-light">Cancel</a>
                <button class="btn-primary">Save</button>
            </div>
        </form>
    </div>
@endsection
