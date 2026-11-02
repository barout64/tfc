@extends('layouts.app', ['title' => 'Give a task'])

@section('content')
    <div class="mx-auto max-w-2xl">
        <a href="{{ route('duty.index') }}" class="text-sm text-slate-500 hover:text-slate-700">← Uwajibikaji</a>
        <h1 class="mt-2 text-2xl font-bold text-slate-900">Give a task</h1>
        <p class="mt-1 text-sm text-slate-500">The person sees it straight away. If the deadline passes without the work, they get a warning and must explain why.</p>

        <form method="POST" action="{{ route('duty.store') }}" enctype="multipart/form-data" class="card mt-6 space-y-5 p-6">
            @csrf
            <div>
                <label class="label" for="assigned_to">To whom?</label>
                <select class="field" id="assigned_to" name="assigned_to" required>
                    <option value="">Choose…</option>
                    @foreach ($people as $p)<option value="{{ $p->id }}" @selected((int) old('assigned_to') === $p->id)>{{ $p->name }} — {{ $p->email }}</option>@endforeach
                </select>
                @error('assigned_to')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="label" for="title">The task, in a few words</label>
                <input class="field" id="title" name="title" value="{{ old('title') }}" maxlength="150" placeholder="e.g. Sales report for last week" required>
                @error('title')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="label" for="details">Details (what exactly, in what form)</label>
                <textarea class="field" id="details" name="details" rows="5" maxlength="5000" placeholder="e.g. Sales of 27 Oct – 2 Nov by region, in Excel">{{ old('details') }}</textarea>
                @error('details')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="label" for="due_at">Deadline (Tanzania time)</label>
                    <input class="field" id="due_at" name="due_at" type="datetime-local" value="{{ old('due_at') }}" min="{{ now(\App\Models\DutyTask::TZ)->format('Y-m-d\TH:i') }}" required>
                    @error('due_at')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="label" for="file">A file to go with it (optional)</label>
                    <input class="text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:text-sm" id="file" name="file" type="file">
                    @error('file')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>
            <button class="btn-primary w-full py-2.5">Give the task</button>
        </form>
    </div>
@endsection
