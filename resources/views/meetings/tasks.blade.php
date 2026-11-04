@extends('layouts.meetings')


@section('title')Action items{{ '' }}@endsection

@section('content')

<div class="page-head">
  <div><h1>Action items</h1><p class="muted">Tasks agreed in meetings and who is responsible for them.</p></div>
</div>

<div class="tabs-inline">
  <a href="{{ route('meetings.tasks', ['scope' => 'mine', 'status' => $status]) }}" class="{{ (($scope == 'mine') ? 'active' : '') }}">Assigned to me</a>
  @if ((in_array(auth()->user()->meetingRole(), ['admin', 'organizer', 'secretary'])))
  <a href="{{ route('meetings.tasks', ['scope' => 'all', 'status' => $status]) }}" class="{{ (($scope == 'all') ? 'active' : '') }}">All tasks</a>
  @endif
</div>
<div class="chips">
  @foreach ([['pending', 'Not done'], ['overdue', 'Overdue'], ['open', 'Open'], ['in_progress', 'In progress'], ['done', 'Done'], ['all', 'All']] as [$k, $v])
  <a class="chip {{ (($status == $k) ? 'active' : '') }}" href="{{ route('meetings.tasks', ['scope' => $scope, 'status' => $k]) }}">{{ $v }}</a>
  @endforeach
</div>

@if ($items)
<div class="card table-wrap">
  <table class="table">
    <thead><tr><th>Task</th><th>Meeting</th>@if (($scope == 'all'))<th>Assigned to</th>@endif<th>Due</th><th>Status</th></tr></thead>
    <tbody>
    @foreach ($items as $a)
      <tr class="{{ (($a->status == 'done') ? 'done-row' : '') }}">
        <td><strong>{{ $a->title }}</strong>@if ($a->description)<div class="muted small">{{ $a->description }}</div>@endif</td>
        <td><a href="{{ route('meetings.meeting_detail', ['meeting_id' => $a->meeting_id]) }}#actions">{{ $a->meeting_title }}</a></td>
        @if (($scope == 'all'))<td>{{ ($a->assignee_name ?: '—') }}</td>@endif
        <td class="nowrap">{{ ($a->due_date ? MUi::date($a->due_date) : '—') }}</td>
        <td>
          @if ($a->editable)
          <form method="post" action="{{ route('meetings.action_status', ['item_id' => $a->id]) }}">
            @csrf<input type="hidden" name="back" value="{{ request()->getRequestUri() }}">
            <select name="status" onchange="this.form.submit()" class="status-select">
              @foreach (MUi::ACTION_STATUSES as $k => $v)<option value="{{ $k }}" {{ (($a->status == $k) ? 'selected' : '') }}>{{ $v }}</option>@endforeach
            </select>
            @if (((($a->status != 'done') && $a->due_date) && ($a->due_date < MUi::today())))<span class="badge badge-overdue">Overdue</span>@endif
          </form>
          @else {{ MUi::actionBadge($a) }}@endif
        </td>
      </tr>
    @endforeach
    </tbody>
  </table>
</div>
@else 
<div class="card empty">No action items here. 🎉</div>
@endif
@endsection
