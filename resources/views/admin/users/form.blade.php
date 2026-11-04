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

                <div class="mt-5 max-w-sm">
                    <label class="label" for="station_id">Daily Report: station (kituo)</label>
                    <select class="field" id="station_id" name="station_id">
                        <option value="">Not tied to a station</option>
                        @foreach ($stations as $st)
                            <option value="{{ $st->id }}" @selected((string) old('station_id', $user->station_id) === (string) $st->id)>{{ $st->name }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-slate-400">Lets this person submit the daily sales report for that station. Administrators can submit for any station regardless.</p>
                    @error('station_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
            </section>

            <section class="card p-6">
                <h2 class="font-semibold text-slate-900">Department and roles</h2>
                <p class="mt-1 text-sm text-slate-500">Everyone who can sign in uses every part of the TFC system; these say what they do there. An administrator is administrator everywhere.</p>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="label" for="department_id">Department</label>
                        <select class="field" id="department_id" name="department_id">
                            <option value="">No department</option>
                            @foreach ($departments as $d)
                                <option value="{{ $d->id }}" @selected((string) old('department_id', $user->department_id) === (string) $d->id)>{{ $d->name }}</option>
                            @endforeach
                        </select>
                        @error('department_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="label" for="job_title">Job title</label>
                        <input class="field" id="job_title" name="job_title" value="{{ old('job_title', $user->job_title) }}">
                    </div>
                    <div>
                        <label class="label" for="meeting_role">Meetings role</label>
                        <select class="field" id="meeting_role" name="meeting_role">
                            @foreach (\App\Support\MeetingUi::ROLES as $key => $label)
                                <option value="{{ $key }}" @selected(old('meeting_role', $user->meeting_role ?? 'member') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="label" for="protocol_role">Protocol role</label>
                        <select class="field" id="protocol_role" name="protocol_role">
                            @foreach (['employee' => 'Employee', 'secretary' => 'Department secretary', 'head' => 'Department head', 'protocol' => 'Protocol officer', 'admin' => 'System administrator'] as $key => $label)
                                <option value="{{ $key }}" @selected(old('protocol_role', $user->protocol_role ?? 'employee') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </section>

            <div class="flex justify-end gap-2">
                <a href="{{ route('admin.users.index') }}" class="btn-light">Cancel</a>
                <button class="btn-primary">Save</button>
            </div>
        </form>
    </div>
@endsection
