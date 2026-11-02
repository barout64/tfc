@extends('layouts.app', ['title' => 'Ask for help'])

@section('content')
    <div class="mx-auto max-w-2xl">
        <a href="{{ route('helpdesk.index') }}" class="text-sm text-slate-500 hover:text-slate-700">← Helpdesk</a>
        <h1 class="mt-2 text-2xl font-bold text-slate-900">Ask for help</h1>
        <p class="mt-1 text-sm text-slate-500">Tell support what is not working. They answer here, and you can follow your request until it is fixed.</p>

        <form method="POST" action="{{ route('helpdesk.store') }}" enctype="multipart/form-data" class="card mt-6 space-y-5 p-6">
            @csrf
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="label" for="category">What is it about?</label>
                    <select class="field" id="category" name="category" required>
                        <option value="">Choose…</option>
                        @foreach (\App\Models\HelpdeskTicket::CATEGORIES as $key => $label)<option value="{{ $key }}" @selected(old('category') === $key)>{{ $label }}</option>@endforeach
                    </select>
                    @error('category')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="label" for="priority">How urgent?</label>
                    <select class="field" id="priority" name="priority" required>
                        @foreach (\App\Models\HelpdeskTicket::PRIORITIES as $key => $label)<option value="{{ $key }}" @selected(old('priority', 'normal') === $key)>{{ $label }}</option>@endforeach
                    </select>
                    @error('priority')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>
            <div>
                <label class="label" for="subject">In a few words</label>
                <input class="field" id="subject" name="subject" value="{{ old('subject') }}" maxlength="150" placeholder="e.g. The printer on the 2nd floor does not print" required>
                @error('subject')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="label" for="description">What happens?</label>
                <textarea class="field" id="description" name="description" rows="5" maxlength="5000" placeholder="What were you doing, what do you see (an error message?), since when…" required>{{ old('description') }}</textarea>
                @error('description')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="label" for="location">Where? (office, room)</label>
                    <input class="field" id="location" name="location" value="{{ old('location') }}" maxlength="120" placeholder="e.g. Finance, room 12">
                    @error('location')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="label" for="phone">Phone or extension (optional)</label>
                    <input class="field" id="phone" name="phone" value="{{ old('phone') }}" maxlength="30">
                    @error('phone')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>
            <div>
                <label class="label" for="file">Photo or screenshot (optional)</label>
                <input class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-medium" id="file" name="file" type="file" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt">
                <p class="mt-1 text-xs text-slate-400">A picture of the error helps. Up to 5 MB.</p>
                @error('file')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div class="flex justify-end gap-2">
                <a href="{{ route('helpdesk.index') }}" class="btn-light">Cancel</a>
                <button class="btn-primary">Send to helpdesk</button>
            </div>
        </form>
    </div>
@endsection
