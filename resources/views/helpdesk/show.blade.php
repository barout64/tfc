@extends('layouts.app', ['title' => $ticket->number()])

@section('content')
    <div class="mx-auto max-w-5xl">
        <a href="{{ route('helpdesk.index') }}" class="text-sm text-slate-500 hover:text-slate-700">← Helpdesk</a>
        <div class="mt-2 flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-sm font-semibold text-slate-400">{{ $ticket->number() }} · {{ \App\Models\HelpdeskTicket::CATEGORIES[$ticket->category] ?? $ticket->category }}</p>
                <h1 class="mt-1 text-2xl font-bold text-slate-900">{{ $ticket->subject }}</h1>
            </div>
            <div class="pt-1">@include('helpdesk._status')</div>
        </div>

        @if ($mine && $ticket->status === 'resolved')
            <div class="mt-5 flex flex-wrap items-center justify-between gap-3 rounded-xl bg-brand-50 px-5 py-4 ring-1 ring-brand-200">
                <p class="text-sm text-brand-900"><span class="font-semibold">Support says this is fixed.</span> Does it work now?</p>
                <div class="flex gap-2">
                    <form method="POST" action="{{ route('helpdesk.confirm', $ticket) }}">@csrf<input type="hidden" name="works" value="1"><button class="btn-primary">Yes, it works</button></form>
                    <form method="POST" action="{{ route('helpdesk.confirm', $ticket) }}">@csrf<input type="hidden" name="works" value="0"><button class="btn-light">No, still not working</button></form>
                </div>
            </div>
        @elseif ($mine && $ticket->status === 'waiting')
            <p class="mt-5 rounded-xl bg-violet-50 px-5 py-4 text-sm text-violet-900 ring-1 ring-violet-200"><span class="font-semibold">Support is waiting for your answer.</span> Reply below.</p>
        @endif

        <div class="mt-6 grid gap-6 lg:grid-cols-3">
            <div class="space-y-4 lg:col-span-2">
                <article class="card p-5">
                    <p class="text-xs text-slate-400"><span class="font-medium text-slate-600">{{ $ticket->requester?->name }}</span> asked {{ $ticket->created_at->timezone('Africa/Dar_es_Salaam')->format('d M Y, H:i') }}</p>
                    <p class="mt-2 whitespace-pre-line text-sm text-slate-800">{{ $ticket->description }}</p>
                </article>

                @foreach ($ticket->messages as $m)
                    @if ($m->kind === 'event')
                        <p class="px-2 text-center text-xs text-slate-400">{{ $m->body }} · {{ $m->created_at->timezone('Africa/Dar_es_Salaam')->format('d M H:i') }}</p>
                    @else
                        @php($fromSupport = $m->user_id !== $ticket->user_id)
                        <article class="card p-5 {{ $fromSupport ? 'ring-brand-200' : '' }}">
                            <p class="text-xs text-slate-400"><span class="font-medium {{ $fromSupport ? 'text-brand-700' : 'text-slate-600' }}">{{ $m->user?->name ?? 'Someone' }}</span>{{ $fromSupport ? ' (support)' : '' }} · {{ $m->created_at->timezone('Africa/Dar_es_Salaam')->format('d M Y, H:i') }}</p>
                            @if ($m->body !== '')<p class="mt-2 whitespace-pre-line text-sm text-slate-800">{{ $m->body }}</p>@endif
                            @if ($m->attachment_path)
                                @php($image = preg_match('/\.(jpe?g|png|webp)$/i', $m->attachment_name))
                                <a href="{{ route('helpdesk.file', $m) }}" target="_blank" class="mt-3 inline-flex items-center gap-2 text-sm font-medium text-brand-700">
                                    @if ($image)<img src="{{ route('helpdesk.file', $m) }}" alt="{{ $m->attachment_name }}" class="max-h-60 rounded-lg ring-1 ring-slate-200">@else📎 {{ $m->attachment_name }}@endif
                                </a>
                            @endif
                        </article>
                    @endif
                @endforeach

                <form method="POST" action="{{ route('helpdesk.reply', $ticket) }}" enctype="multipart/form-data" class="card space-y-3 p-5">
                    @csrf
                    <label class="label" for="body">{{ $support && ! $mine ? 'Answer '.str($ticket->requester?->name)->before(' ') : ($ticket->isOpen() ? 'Add something' : 'Still a problem? Write here and it opens again') }}</label>
                    <textarea class="field" id="body" name="body" rows="3" maxlength="5000">{{ old('body') }}</textarea>
                    @error('body')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <input class="text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:text-sm" name="file" type="file" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt" aria-label="Photo or file">
                        <button class="btn-primary">Send</button>
                    </div>
                    @error('file')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                </form>
            </div>

            <aside class="space-y-4">
                <div class="card space-y-3 p-5 text-sm">
                    <div><p class="text-xs text-slate-400">Asked by</p><p class="font-medium text-slate-800">{{ $ticket->requester?->name }}</p><p class="text-xs text-slate-500">{{ $ticket->requester?->email }}</p></div>
                    @if ($ticket->location)<div><p class="text-xs text-slate-400">Where</p><p class="text-slate-800">{{ $ticket->location }}</p></div>@endif
                    @if ($ticket->phone)<div><p class="text-xs text-slate-400">Phone</p><p class="text-slate-800">{{ $ticket->phone }}</p></div>@endif
                    <div><p class="text-xs text-slate-400">How urgent</p><p class="text-slate-800">{{ \App\Models\HelpdeskTicket::PRIORITIES[$ticket->priority] ?? $ticket->priority }}</p></div>
                    <div><p class="text-xs text-slate-400">Support person</p><p class="text-slate-800">{{ $ticket->assignee?->name ?? 'Nobody yet' }}</p></div>
                    @if ($ticket->resolved_at)<div><p class="text-xs text-slate-400">Fixed</p><p class="text-slate-800">{{ $ticket->resolved_at->timezone('Africa/Dar_es_Salaam')->format('d M Y, H:i') }}</p></div>@endif
                </div>

                @if ($support && $ticket->status !== 'closed')
                    <div class="card space-y-4 p-5">
                        <p class="font-semibold text-slate-900">Support</p>
                        @if ($ticket->assigned_to !== auth()->id())
                            <form method="POST" action="{{ route('helpdesk.assign', $ticket) }}">@csrf<button class="btn-primary w-full">Take it — I will fix this</button></form>
                        @endif
                        <form method="POST" action="{{ route('helpdesk.assign', $ticket) }}" class="flex gap-2">@csrf
                            <select name="assigned_to" class="field" aria-label="Give to">
                                @foreach ($supportPeople as $id => $name)<option value="{{ $id }}" @selected($ticket->assigned_to === $id)>{{ $name }}</option>@endforeach
                            </select>
                            <button class="btn-light">Give</button>
                        </form>
                        <form method="POST" action="{{ route('helpdesk.status', $ticket) }}" class="space-y-2">@csrf
                            <label class="label" for="status">Status</label>
                            <select id="status" name="status" class="field">
                                <option value="in_progress" @selected($ticket->status === 'in_progress')>Being fixed</option>
                                <option value="waiting" @selected($ticket->status === 'waiting')>Waiting for {{ str($ticket->requester?->name)->before(' ') }}</option>
                                <option value="resolved" @selected($ticket->status === 'resolved')>Fixed</option>
                            </select>
                            <textarea name="note" rows="2" class="field" placeholder="What was done (optional, {{ str($ticket->requester?->name)->before(' ') }} sees it)"></textarea>
                            <button class="btn-primary w-full">Save</button>
                        </form>
                    </div>
                @endif
            </aside>
        </div>
    </div>
@endsection
