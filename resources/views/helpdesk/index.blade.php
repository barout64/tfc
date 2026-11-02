@extends('layouts.app', ['title' => 'Helpdesk'])

@section('content')
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="text-sm font-semibold tracking-wide text-brand-600 uppercase">TFC Helpdesk</p>
            <h1 class="mt-1 text-2xl font-bold text-slate-900">{{ $support ? 'Requests for help' : 'My requests for help' }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ $support ? 'Take a request, answer the person, and mark it fixed.' : 'Printer, computer, internet, email or anything else not working? Ask here and follow the answer.' }}</p>
        </div>
        <a href="{{ route('helpdesk.create') }}" class="btn-primary">+ Ask for help</a>
    </div>

    @if ($support)
        <div class="mb-5 grid grid-cols-2 gap-3 md:grid-cols-4">
            @foreach (['open' => 'Open', 'new' => 'New, nobody yet', 'urgent' => 'Urgent', 'mine' => 'Mine'] as $key => $label)
                <div class="card p-4"><p class="text-xs text-slate-500">{{ $label }}</p><p class="mt-1 text-2xl font-bold {{ $key === 'urgent' && $counts[$key] ? 'text-red-600' : 'text-slate-900' }}">{{ $counts[$key] }}</p></div>
            @endforeach
        </div>
        <nav class="mb-4 flex flex-wrap gap-1 text-sm">
            @foreach (['open' => 'Open', 'mine' => 'Mine', 'done' => 'Fixed & closed', 'all' => 'All'] as $key => $label)
                <a href="{{ route('helpdesk.index', ['show' => $key]) }}" class="rounded-lg px-3 py-1.5 {{ $show === $key ? 'bg-brand-600 text-white' : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50' }}">{{ $label }}</a>
            @endforeach
        </nav>
    @endif

    <div class="card overflow-hidden">
        @if ($tickets->isEmpty())
            <div class="p-10 text-center">
                <p class="font-medium text-slate-700">{{ $support ? 'Nothing here.' : 'You have not asked for help yet.' }}</p>
                @unless ($support)<a href="{{ route('helpdesk.create') }}" class="btn-primary mt-4">Ask for help</a>@endunless
            </div>
        @else
            <ul class="divide-y divide-slate-100">
                @foreach ($tickets as $ticket)
                    <li>
                        <a href="{{ route('helpdesk.show', $ticket) }}" class="flex flex-wrap items-center gap-x-4 gap-y-1 px-5 py-4 hover:bg-slate-50">
                            <span class="w-16 text-xs font-semibold text-slate-400">{{ $ticket->number() }}</span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate font-medium text-slate-900">{{ $ticket->subject }}</span>
                                <span class="block text-xs text-slate-500">{{ \App\Models\HelpdeskTicket::CATEGORIES[$ticket->category] ?? $ticket->category }}@if ($support) · {{ $ticket->requester?->name }}@endif @if ($ticket->location) · {{ $ticket->location }}@endif · {{ $ticket->created_at->diffForHumans() }}</span>
                            </span>
                            <span class="text-xs text-slate-500">{{ $ticket->assignee ? 'With '.$ticket->assignee->name : 'Nobody yet' }}</span>
                            <span>@include('helpdesk._status')</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
    <div class="mt-4">{{ $tickets->links() }}</div>
@endsection
