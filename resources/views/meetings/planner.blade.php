@extends('layouts.meetings')


@section('title')Annual planner {{ $year }}@endsection

@section('content')

<div class="page-head">
  <div class="cal-nav">
    <a class="icon-btn" href="{{ route('meetings.planner', ['year' => ($year - 1), 'type' => $mtype]) }}" aria-label="Previous year">{{ MUi::icon('left') }}</a>
    <div><p class="eyebrow">Annual planner</p><h1>{{ $year }}</h1></div>
    <a class="icon-btn" href="{{ route('meetings.planner', ['year' => ($year + 1), 'type' => $mtype]) }}" aria-label="Next year">{{ MUi::icon('right') }}</a>
  </div>
  <div class="btn-group">
    <a class="btn" href="{{ route('meetings.planner', ['year' => $year, 'type' => $mtype, 'format' => 'ics']) }}">{{ MUi::icon('download', 16) }} Export year (.ics)</a>
    @if (MUi::canCreate())<a class="btn btn-primary" href="{{ route('meetings.meeting_new', ['plan' => 'year', 'date' => (($year > (int) MUi::slice(MUi::today(), null, 4)) ? ($year . '-01-01') : MUi::today())]) }}">{{ MUi::icon('repeat', 16) }} Plan a recurring series</a>@endif
  </div>
</div>

<section class="card legend-card">
  <div class="legend">
    <a class="legend-item {{ (! $mtype ? 'active' : '') }}" href="{{ route('meetings.planner', ['year' => $year]) }}"><strong>{{ $total }}</strong> All meetings</a>
    @foreach (MUi::MEETING_TYPES as $t)@if (($type_counts[$t] ?? null))
    <a class="legend-item {{ (($mtype == $t) ? 'active' : '') }}" href="{{ route('meetings.planner', ['year' => $year, 'type' => $t]) }}" style="--c: {{ MUi::typeColor($t) }}"><span class="tdot"></span>{{ $t }} <strong>{{ $type_counts[$t] }}</strong></a>
    @endif{{ '' }}@endforeach
  </div>
</section>

<div class="year-grid">
  @foreach ($months as $mo)
  <section class="card month-card">
    <div class="month-head">
      <a href="{{ route('meetings.calendar_view', ['month' => sprintf('%d-%02d', $year, $mo->num)]) }}"><h2>{{ $mo->name }}</h2></a>
      <span class="muted small">{{ count(array_filter($mo->meetings, fn ($r) => $r->status != 'cancelled')) }} meeting(s)</span>
    </div>
    <div class="mini-cal">
      @foreach (['M', 'T', 'W', 'T', 'F', 'S', 'S'] as $d)<span class="mc-h">{{ $d }}</span>@endforeach
      @foreach ($mo->weeks as $week)@foreach ($week as $day)
        @php($iso = $day->toDateString())
        @php($evs = (($day->month == $mo->num) ? ($by_day[$iso] ?? []) : []))
        @if (($day->month != $mo->num))<span class="mc-d out"></span>
        @elseif ($evs)
          <a class="mc-d has {{ (($iso == MUi::today()) ? 'today' : '') }}" href="{{ ((count($evs) == 1) ? route('meetings.meeting_detail', ['meeting_id' => $evs[0]->id]) : route('meetings.calendar_view', ['month' => MUi::slice($iso, null, 7)])) }}"
             title="@foreach ($evs as $e){{ MUi::time($e->start_time) }} {{ $e->title }}{{ (! $loop->last ? ' · ' : '') }}@endforeach"
             style="--c: {{ MUi::typeColor($evs[0]->meeting_type) }}">{{ $day->day }}@if ((count($evs) > 1))<i>{{ count($evs) }}</i>@endif</a>
        @else <span class="mc-d {{ (($iso == MUi::today()) ? 'today' : '') }} {{ ((($day->dayOfWeekIso - 1) >= 5) ? 'wkend' : '') }}">{{ $day->day }}</span>@endif
      @endforeach{{ '' }}@endforeach
    </div>
    @if ($mo->meetings)
    <ul class="month-list">
      @foreach (MUi::slice($mo->meetings, null, 4) as $m)
      <li class="{{ (($m->status == 'cancelled') ? 'cancelled' : '') }}"><a href="{{ route('meetings.meeting_detail', ['meeting_id' => $m->id]) }}" style="--c: {{ MUi::typeColor($m->meeting_type) }}">
        <span class="ml-day">{{ MUi::slice($m->start_time, 8, 10) }}</span><span class="ml-title">{{ $m->title }}</span>@if ($m->series_id){{ MUi::icon('repeat', 12) }}@endif</a></li>
      @endforeach
      @if ((count($mo->meetings) > 4))<li class="more"><a href="{{ route('meetings.calendar_view', ['month' => sprintf('%d-%02d', $year, $mo->num)]) }}">+{{ (count($mo->meetings) - 4) }} more</a></li>@endif
    </ul>
    @endif
  </section>
  @endforeach
</div>
@endsection
