@php($colours = ['new' => 'bg-amber-100 text-amber-800', 'seen' => 'bg-sky-100 text-sky-800', 'on_it' => 'bg-indigo-100 text-indigo-800', 'stuck' => 'bg-orange-100 text-orange-800',
    'submitted' => 'bg-violet-100 text-violet-800', 'returned' => 'bg-rose-100 text-rose-800', 'approved' => 'bg-brand-100 text-brand-800', 'cancelled' => 'bg-slate-100 text-slate-600'])
<span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $colours[$task->status] ?? 'bg-slate-100 text-slate-600' }}">{{ \App\Models\DutyTask::STATUSES[$task->status] ?? $task->status }}</span>
@if ($task->isOverdue())<span class="ml-1 inline-flex rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-semibold text-red-700">Late</span>@endif
