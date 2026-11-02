{{-- The deadline, coloured: late (red), within a day (amber) --}}
@php($soon = $task->isToDo() && ! $task->isOverdue() && $task->due_at->lte(now()->addDay()))
<span class="{{ $task->isOverdue() ? 'font-semibold text-red-600' : ($soon ? 'font-semibold text-amber-700' : 'text-slate-500') }}">{{ $task->dueLabel() }}@if ($task->isToDo()) · {{ $task->isOverdue() ? 'late by '.$task->due_at->diffForHumans(null, true) : 'in '.$task->due_at->diffForHumans(null, true) }}@endif</span>
