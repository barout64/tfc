@extends('layouts.app', ['title' => 'Change password'])

@section('content')
    <div class="mx-auto max-w-md">
        <h1 class="text-2xl font-bold text-slate-900">Change your password</h1>
        <p class="mt-1 text-sm text-slate-500">This is your TFC Portal password, used for all TFC systems.</p>
        <form method="POST" action="{{ route('password.update') }}" class="card mt-6 space-y-5 p-6">
            @csrf @method('PUT')
            <div>
                <label class="label" for="current_password">Current password</label>
                <input class="field" id="current_password" name="current_password" type="password" required autocomplete="current-password">
                @error('current_password')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="label" for="password">New password <span class="font-normal text-slate-400">(at least 8 characters)</span></label>
                <input class="field" id="password" name="password" type="password" required autocomplete="new-password">
                @error('password')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="label" for="password_confirmation">New password again</label>
                <input class="field" id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password">
            </div>
            <button class="btn-primary w-full">Change password</button>
        </form>
    </div>
@endsection
