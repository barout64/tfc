@extends('layouts.meetings')


@section('title')Meetings{{ '' }}@endsection

@section('content')

<div class="page-head">
  <div><h1>Meetings</h1><p class="muted">{{ count($meetings) }} meeting{{ ((count($meetings) == 1) ? '' : 's') }} found</p></div>
  @if (MUi::canCreate())<a class="btn btn-primary" href="{{ route('meetings.meeting_new', []) }}">{{ MUi::icon('plus', 16) }} New meeting</a>@endif
</div>

<div class="toolbar-row">
  <div class="seg seg-lg">
    @foreach ([['upcoming', 'Upcoming'], ['past', 'Past'], ['mine', 'Organized by me'], ['all', 'All']] as [$key, $label])
    <a href="{{ route('meetings.meetings_list', ['scope' => $key, 'q' => $q, 'status' => $status, 'type' => $mtype]) }}" class="{{ (($scope == $key) ? 'active' : '') }}">{{ $label }}</a>
    @endforeach
  </div>
  <form class="filters" method="get">
    <input type="hidden" name="scope" value="{{ $scope }}">
    <div class="input-icon">{{ MUi::icon('search', 15) }}<input type="search" name="q" value="{{ $q }}" placeholder="Search title or description"></div>
    <select name="status" onchange="this.form.submit()" aria-label="Status">
      <option value="">Any status</option>
      @foreach (MUi::MEETING_STATUSES as $k => $v)<option value="{{ $k }}" {{ (($status == $k) ? 'selected' : '') }}>{{ $v }}</option>@endforeach
    </select>
    <select name="type" onchange="this.form.submit()" aria-label="Type">
      <option value="">Any type</option>
      @foreach (MUi::MEETING_TYPES as $t)<option {{ (($mtype == $t) ? 'selected' : '') }}>{{ $t }}</option>@endforeach
    </select>
    @if ((($q ?: $status) ?: $mtype))<a class="link" href="{{ route('meetings.meetings_list', ['scope' => $scope]) }}">Clear</a>@endif
  </form>
</div>

@if ($meetings)
<div class="card table-wrap flush">
  <table class="table">
    <thead><tr><th>Meeting</th><th>Date &amp; time</th><th class="hide-sm">Location</th><th class="hide-sm">Organizer</th><th class="hide-sm num">People</th><th>Status</th></tr></thead>
    <tbody>
      @foreach ($meetings as $m)
      <tr class="row-link" data-href="{{ route('meetings.meeting_detail', ['meeting_id' => $m->id]) }}">
        <td><div class="title-cell" style="--c: {{ MUi::typeColor($m->meeting_type) }}"><span class="tbar"></span><div>
          <a href="{{ route('meetings.meeting_detail', ['meeting_id' => $m->id]) }}" class="strong-link">{{ $m->title }}</a>
          <div class="muted small">{{ $m->meeting_type }}@if ($m->series_id) · {{ MUi::icon('repeat', 11) }} series{{ '' }}@endif</div></div></div></td>
        <td class="nowrap">{{ MUi::date($m->start_time) }}<div class="muted small">{{ MUi::time($m->start_time) }}–{{ MUi::time($m->end_time) }}@php($rel = MUi::relativeDay($m->start_time))@if ($rel) · {{ $rel }}@endif</div></td>
        <td class="hide-sm">{{ ($m->room_name ?: '') }}@if ($m->online_link){{ ($m->room_name ? ' + ' : '') }}Online{{ '' }}@endif{{ '' }}@if ((! $m->room_name && ! $m->online_link))<span class="muted">—</span>@endif</td>
        <td class="hide-sm">{{ $m->organizer_name }}</td>
        <td class="hide-sm num">{{ $m->participant_count }}</td>
        <td>{{ MUi::statusBadge($m->status) }}</td>
      </tr>
      @endforeach
    </tbody>
  </table>
</div>
@else 
<div class="card empty">{{ MUi::icon('calendar', 30) }}<p>No meetings match these filters.</p></div>
@endif
@endsection
