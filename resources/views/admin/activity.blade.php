@extends('layouts.app', ['title' => 'Activity'])

@section('content')
    @php($labels = ['signed_in' => 'Signed in to the portal', 'opened' => 'Opened', 'changed_password' => 'Changed their password', 'added_person' => 'Added', 'changed_person' => 'Changed access of',
        'helpdesk_asked' => 'Asked the helpdesk:', 'helpdesk_taken' => 'Helpdesk: took', 'helpdesk_in_progress' => 'Helpdesk: working on', 'helpdesk_waiting' => 'Helpdesk: waiting for an answer on',
        'helpdesk_resolved' => 'Helpdesk: fixed', 'helpdesk_closed' => 'Helpdesk: confirmed fixed', 'helpdesk_reopened' => 'Helpdesk: opened again',
        'duty_assigned' => 'Uwajibikaji: gave', 'duty_on_it' => 'Uwajibikaji: on it —', 'duty_stuck' => 'Uwajibikaji: needs help with', 'duty_submitted' => 'Uwajibikaji: handed in',
        'duty_approved' => 'Uwajibikaji: approved', 'duty_returned' => 'Uwajibikaji: sent back', 'duty_deadline' => 'Uwajibikaji: new deadline', 'duty_cancelled' => 'Uwajibikaji: cancelled',
        'duty_explained' => 'Uwajibikaji: explained a missed deadline —', 'duty_decided' => 'Uwajibikaji: decided on an explanation —', 'duty_cleared' => 'Uwajibikaji: cleared'])
    <h1 class="text-2xl font-bold text-slate-900">Activity</h1>
    <p class="mt-1 text-sm text-slate-500">Who signed in, which systems they opened, and changes to people's access.</p>

    <div class="card mt-6 overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-50 text-xs font-semibold tracking-wide text-slate-500 uppercase">
                <tr><th class="px-4 py-3">When</th><th class="px-4 py-3">Who</th><th class="px-4 py-3">What</th><th class="px-4 py-3">From</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($activities as $a)
                    <tr>
                        <td class="px-4 py-3 whitespace-nowrap text-slate-500">{{ $a->created_at->timezone('Africa/Dar_es_Salaam')->format('d M Y H:i') }}</td>
                        <td class="px-4 py-3 font-medium text-slate-800">{{ $a->user?->name ?? '—' }}</td>
                        <td class="px-4 py-3">
                            {{ $labels[$a->action] ?? $a->action }}
                            @if ($a->system)<span class="font-semibold text-brand-700">{{ $systems->name($a->system) }}</span>@endif
                            @if ($a->detail)<span class="text-slate-500">{{ $a->system ? '(as '.$a->detail.')' : $a->detail }}</span>@endif
                        </td>
                        <td class="px-4 py-3 text-xs text-slate-400">{{ $a->ip_address }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-10 text-center text-slate-500">Nothing yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $activities->links() }}</div>
@endsection
