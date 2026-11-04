@extends('layouts.meetings')


@section('title')Calendar{{ '' }}@endsection

@section('content')

<div class="page-head">
  <div class="cal-nav">
    <a class="icon-btn" href="{{ route('meetings.calendar_view', ['month' => $prev_month]) }}" aria-label="Previous month">{{ MUi::icon('left') }}</a>
    <h1>{{ $first->format('F Y') }}</h1>
    <a class="icon-btn" href="{{ route('meetings.calendar_view', ['month' => $next_month]) }}" aria-label="Next month">{{ MUi::icon('right') }}</a>
    <a class="btn btn-sm" href="{{ route('meetings.calendar_view', []) }}">Today</a>
  </div>
  <div class="btn-group">
    <div class="seg">
      <a class="active" href="{{ route('meetings.calendar_view', ['month' => $first->format('Y-m')]) }}">Month</a>
      <a href="{{ route('meetings.planner', ['year' => $first->year]) }}">Year</a>
    </div>
    @if (MUi::canCreate())<a class="btn btn-primary" href="{{ route('meetings.meeting_new', []) }}">{{ MUi::icon('plus', 16) }} New meeting</a>@endif
  </div>
</div>

<div class="card calendar flush">
  <div class="cal-head">@foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $d)<div>{{ $d }}</div>@endforeach</div>
  @foreach ($weeks as $week)
  <div class="cal-week">
    @foreach ($week as $day)
    @php($iso = $day->toDateString())
    <div class="cal-day {{ (($day->month != $first->month) ? 'other' : '') }} {{ (($iso == MUi::today()) ? 'today' : '') }} {{ ((($day->dayOfWeekIso - 1) >= 5) ? 'wkend' : '') }}">
      <div class="cal-day-head">
        <span class="cal-num">{{ $day->day }}</span>
        @if ((MUi::canCreate() && ($iso >= MUi::today())))<a class="cal-add" href="{{ route('meetings.meeting_new', ['date' => $iso]) }}" title="New meeting on this day" aria-label="New meeting on {{ $iso }}">{{ MUi::icon('plus', 14) }}</a>@endif
      </div>
      @foreach (($by_day[$iso] ?? []) as $m)
      <a class="cal-event st-{{ $m->status }}" style="--c: {{ MUi::typeColor($m->meeting_type) }}" href="{{ route('meetings.meeting_detail', ['meeting_id' => $m->id]) }}" title="{{ $m->title }} ({{ MUi::time($m->start_time) }}–{{ MUi::time($m->end_time) }})">
        <span class="ev-time">{{ MUi::time($m->start_time) }}</span> {{ $m->title }}
      </a>
      @endforeach
    </div>
    @endforeach
  </div>
  @endforeach
</div>
<div class="legend small">
  @foreach (MUi::TYPE_COLORS as $t => $c)<span class="legend-item plain" style="--c: {{ $c }}"><span class="tdot"></span>{{ $t }}</span>@endforeach
</div>
@endsection
