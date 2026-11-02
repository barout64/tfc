@extends('layouts.app', ['title' => $user->exists ? $user->name : 'Add person'])

@section('content')
    <div class="mx-auto max-w-3xl">
        <a href="{{ route('admin.users.index') }}" class="text-sm text-slate-500 hover:text-slate-700">← People &amp; access</a>
        <h1 class="mt-2 text-2xl font-bold text-slate-900">{{ $user->exists ? 'Edit '.$user->name : 'Add a person' }}</h1>

        <form method="POST" action="{{ $user->exists ? route('admin.users.update', $user) : route('admin.users.store') }}" class="mt-6 space-y-6">
            @csrf
            @if ($user->exists) @method('PUT') @endif

            <section class="card p-6">
                <h2 class="font-semibold text-slate-900">TFC Portal sign-in</h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="label" for="name">Full name</label>
                        <input class="field" id="name" name="name" value="{{ old('name', $user->name) }}" required>
                        @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="label" for="email">Email (used to sign in)</label>
                        <input class="field" id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required>
                        @error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="label" for="password">{{ $user->exists ? 'New password (leave empty to keep)' : 'Password' }}</label>
                        <input class="field" id="password" name="password" type="password" autocomplete="new-password" {{ $user->exists ? '' : 'required' }}>
                        @error('password')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="label" for="password_confirmation">Password again</label>
                        <input class="field" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password">
                    </div>
                </div>
                <div class="mt-5 flex flex-wrap gap-6 text-sm">
                    <label class="flex items-center gap-2"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $user->is_active ?? true)) class="rounded border-slate-300 text-brand-600"> Can sign in</label>
                    <label class="flex items-center gap-2"><input type="hidden" name="is_admin" value="0"><input type="checkbox" name="is_admin" value="1" @checked(old('is_admin', $user->is_admin)) class="rounded border-slate-300 text-brand-600"> TFC administrator (adds people and gives access)</label>
                    <label class="flex items-center gap-2"><input type="hidden" name="is_support" value="0"><input type="checkbox" name="is_support" value="1" @checked(old('is_support', $user->is_support)) class="rounded border-slate-300 text-brand-600"> Helpdesk support (receives and fixes staff requests for help)</label>
                </div>
                @error('is_admin')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
            </section>

            <section class="card p-6">
                <h2 class="font-semibold text-slate-900">Systems this person may open</h2>
                <p class="mt-1 text-sm text-slate-500">Given to everyone automatically, or chosen for them - never both.</p>

                @php($autoSystems = collect($systems)->diffKeys($assignedSystems))
                @if ($autoSystems->isNotEmpty())
                    <div class="mt-5 rounded-lg bg-brand-50 px-4 py-3 text-sm text-brand-900">
                        <p class="font-medium">Everyone who can sign in already has:</p>
                        <p class="mt-1 text-brand-800">{{ $autoSystems->pluck('name')->implode(', ') }}</p>
                        <p class="mt-1 text-xs text-brand-700">An account is created there for them automatically (no password of their own - they always arrive signed in from here).</p>
                    </div>
                @endif

                <div class="mt-5 space-y-5">
                    @foreach ($assignedSystems as $key => $s)
                        @php([$people, $error] = $lists[$key])
                        @php($current = old("links.$key", $user->exists ? $user->linkTo($key)?->remote_id : ''))
                        <div class="grid gap-2 sm:grid-cols-3 sm:items-center">
                            <div>
                                <p class="font-medium text-slate-800">{{ $s['name'] }}</p>
                                <p class="text-xs text-slate-400">{{ parse_url($s['url'], PHP_URL_HOST) }} · assigned, not automatic</p>
                            </div>
                            <div class="sm:col-span-2">
                                <select name="links[{{ $key }}]" class="field">
                                    <option value="">No access</option>
                                    @if ($error && $current)
                                        <option value="{{ $current }}" selected>{{ $user->linkTo($key)?->remote_name ?? $current }} (current)</option>
                                    @endif
                                    @foreach ($people as $p)
                                        <option value="{{ $p['id'] }}" @selected((string) $current === $p['id'])>{{ $p['name'] }} — {{ $p['login'] }}{{ $p['role'] ? ' · '.$p['role'] : '' }}{{ $p['active'] ? '' : ' (switched off there)' }}</option>
                                    @endforeach
                                </select>
                                @if ($error)<p class="mt-1 text-xs text-amber-700">{{ $error }}</p>@endif
                                @error("links.$key")<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                        </div>
                    @endforeach
                </div>
                @if ($assignedSystems->isNotEmpty())
                    <p class="mt-5 rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-500">Someone without an account in {{ $assignedSystems->pluck('name')->implode(' or ') }} yet: create it there first (as that system's administrator), then choose it here.</p>
                @endif
            </section>

            <div class="flex justify-end gap-2">
                <a href="{{ route('admin.users.index') }}" class="btn-light">Cancel</a>
                <button class="btn-primary">Save</button>
            </div>
        </form>
    </div>
@endsection
