@extends('layouts.meetings')


@section('title')Dashboard{{ '' }}@endsection

@section('content')

<section class="hero">
  <div>
    <p class="eyebrow">{{ MUi::longdate(MUi::today()) }}</p>
    <h1>{{ $greeting }}, {{ explode(' ', auth()->user()->name)[0] }}</h1>
    <p class="hero-sub">
      @if ($stats->today)You have <strong>{{ $stats->today }}</strong> meeting{{ (($stats->today != 1) ? 's' : '') }} today{{ '' }}@else No meetings today{{ '' }}@endif{{ '' }}@if ($stats->overdue) · <strong class="text-danger">{{ $stats->overdue }}</strong> overdue task{{ (($stats->overdue != 1) ? 's' : '') }}@endif{{ '' }}@if ($stats->year_left) · {{ $stats->year_left }} more planned this year{{ '' }}@endif
    </p>
  </div>
  <div class="hero-actions">
    @if (MUi::canCreate())
    <a class="btn btn-light" href="{{ route('meetings.meeting_new', ['plan' => 'year']) }}">{{ MUi::icon('repeat', 16) }} Plan the year</a>
    <a class="btn btn-white" href="{{ route('meetings.meeting_new', []) }}">{{ MUi::icon('plus', 16) }} New meeting</a>
    @else 
    <a class="btn btn-white" href="{{ route('meetings.calendar_view', []) }}">{{ MUi::icon('calendar', 16) }} Open calendar</a>
    @endif
  </div>
</section>

<div class="stats">
  <a class="stat" href="{{ route('meetings.calendar_view', []) }}">
    <span class="stat-icon si-blue">{{ MUi::icon('calendar', 20) }}</span>
    <span class="stat-body"><span class="stat-value">{{ $stats->today }}</span><span class="stat-label">Meetings today</span></span>
  </a>
  <a class="stat" href="{{ route('meetings.meetings_list', []) }}">
    <span class="stat-icon si-violet">{{ MUi::icon('clock', 20) }}</span>
    <span class="stat-body"><span class="stat-value">{{ $stats->week }}</span><span class="stat-label">Next 7 days</span></span>
  </a>
  <a class="stat" href="{{ route('meetings.planner', []) }}">
    <span class="stat-icon si-teal">{{ MUi::icon('year', 20) }}</span>
    <span class="stat-body"><span class="stat-value">{{ $stats->year_left }}</span><span class="stat-label">Still planned this year</span></span>
  </a>
  <a class="stat" href="{{ route('meetings.tasks', ['status' => ($stats->overdue ? 'overdue' : 'pending')]) }}">
    <span class="stat-icon {{ ($stats->overdue ? 'si-red' : 'si-amber') }}">{{ MUi::icon('tasks', 20) }}</span>
    <span class="stat-body"><span class="stat-value">{{ $stats->open_tasks }}</span>
      <span class="stat-label">Open tasks{{ '' }}@if ($stats->overdue) · <span class="text-danger">{{ $stats->overdue }} overdue</span>@endif</span></span>
  </a>
</div>

@if (($pending_rsvp ?: $minutes_to_approve))
<section class="card attention">
  <div class="card-head"><h2>{{ MUi::icon('flag', 17) }} Needs your attention</h2></div>
  <ul class="plain-list">
    @foreach ($pending_rsvp as $m)
    <li class="attention-row">
      <div><a class="strong-link" href="{{ route('meetings.meeting_detail', ['meeting_id' => $m->id]) }}">{{ $m->title }}</a>
        <div class="muted small">{{ MUi::when($m) }} — will you attend?</div></div>
      <form method="post" action="{{ route('meetings.meeting_rsvp', ['meeting_id' => $m->id]) }}" class="btn-group">
        @csrf<input type="hidden" name="back" value="{{ route('meetings.dashboard', []) }}">
        <button class="btn btn-sm btn-success" name="rsvp" value="accepted">Accept</button>
        <button class="btn btn-sm" name="rsvp" value="tentative">Maybe</button>
        <button class="btn btn-sm btn-ghost-danger" name="rsvp" value="declined">Decline</button>
      </form>
    </li>
    @endforeach
    @foreach ($minutes_to_approve as $m)
    <li class="attention-row">
      <div><a class="strong-link" href="{{ route('meetings.meeting_detail', ['meeting_id' => $m->id]) }}#minutes">{{ $m->title }}</a>
        <div class="muted small">Minutes are waiting for your approval</div></div>
      <a class="btn btn-sm btn-primary" href="{{ route('meetings.meeting_detail', ['meeting_id' => $m->id]) }}#minutes">Review minutes</a>
    </li>
    @endforeach
  </ul>
</section>
@endif

<div class="grid-main">
  <div>
    <section class="card">
      <div class="card-head"><h2>Today's schedule</h2><a href="{{ route('meetings.calendar_view', []) }}" class="link">Calendar {{ MUi::icon('arrow-right', 14) }}</a></div>
      @if ($today_meetings)
      <ol class="timeline">
        @foreach ($today_meetings as $m)
        @php($live = (($m->start_time <= MUi::now()) && ($m->end_time >= MUi::now())))
        <li class="{{ ($live ? 'live' : '') }} {{ (($m->end_time < MUi::now()) ? 'past' : '') }}">
          <div class="tl-time"><strong>{{ MUi::time($m->start_time) }}</strong><span>{{ MUi::time($m->end_time) }}</span></div>
          <a class="tl-card" href="{{ route('meetings.meeting_detail', ['meeting_id' => $m->id]) }}" style="--c: {{ MUi::typeColor($m->meeting_type) }}">
            <div class="tl-title">{{ $m->title }} @if ($live)<span class="live-pill">Live now</span>@endif</div>
            <div class="tl-meta">
              @if ($m->room_name)<span>{{ MUi::icon('pin', 13) }} {{ $m->room_name }}</span>@endif
              @if ($m->online_link)<span>{{ MUi::icon('video', 13) }} Online</span>@endif
              <span>{{ MUi::icon('users', 13) }} {{ $m->participant_count }}</span>
            </div>
          </a>
        </li>
        @endforeach
      </ol>
      @else 
      <div class="empty compact">{{ MUi::icon('calendar', 26) }}<p>Nothing scheduled for today.</p></div>
      @endif
    </section>

    <section class="card">
      <div class="card-head"><h2>Coming up</h2><a href="{{ route('meetings.meetings_list', []) }}" class="link">All meetings {{ MUi::icon('arrow-right', 14) }}</a></div>
      @if ($upcoming)
      <ul class="meeting-list">
        @foreach ($upcoming as $m)
        <li>
          <a class="meeting-item" href="{{ route('meetings.meeting_detail', ['meeting_id' => $m->id]) }}">
            <div class="date-chip" style="--c: {{ MUi::typeColor($m->meeting_type) }}">
              <span class="dc-mon">{{ MUi::mon($m->start_time) }}</span>
              <span class="dc-day">{{ MUi::slice($m->start_time, 8, 10) }}</span>
            </div>
            <div class="mi-body">
              <div class="mi-title">{{ $m->title }}</div>
              <div class="mi-meta">
                <span>{{ MUi::icon('clock', 13) }} {{ MUi::time($m->start_time) }}–{{ MUi::time($m->end_time) }}</span>
                @if ($m->room_name)<span>{{ MUi::icon('pin', 13) }} {{ $m->room_name }}</span>@endif
                @if ($m->series_id)<span>{{ MUi::icon('repeat', 13) }} Series</span>@endif
                @php($rel = MUi::relativeDay($m->start_time))@if ($rel)<span class="rel">{{ $rel }}</span>@endif
              </div>
            </div>
            <div class="mi-side">@if ($m->my_rsvp){{ MUi::rsvpBadge($m->my_rsvp) }}@endif</div>
          </a>
        </li>
        @endforeach
      </ul>
      @else 
      <div class="empty compact">{{ MUi::icon('clock', 26) }}<p>No upcoming meetings.
        @if (MUi::canCreate())<a href="{{ route('meetings.meeting_new', []) }}">Schedule one</a>.@endif</p></div>
      @endif
    </section>
  </div>

  <div>
    <section class="card">
      <div class="card-head"><h2>My action items</h2><a href="{{ route('meetings.tasks', []) }}" class="link">View all {{ MUi::icon('arrow-right', 14) }}</a></div>
      @if ($my_tasks)
      <ul class="task-list">
        @foreach ($my_tasks as $t)
        <li>
          <span class="task-dot {{ (($t->due_date && ($t->due_date < MUi::today())) ? 'overdue' : $t->status) }}"></span>
          <div class="task-main">
            <div class="task-title">{{ $t->title }}</div>
            <div class="muted small"><a href="{{ route('meetings.meeting_detail', ['meeting_id' => $t->meeting_id]) }}#actions">{{ $t->meeting_title }}</a>
              @if ($t->due_date) · Due {{ MUi::date($t->due_date) }}@endif</div>
          </div>
          {{ MUi::actionBadge($t) }}
        </li>
        @endforeach
      </ul>
      @else 
      <div class="empty compact">{{ MUi::icon('tick', 26) }}<p>You're all caught up.</p></div>
      @endif
    </section>

    <section class="card quick-links">
      <h2>Quick links</h2>
      <a href="{{ route('meetings.planner', []) }}">{{ MUi::icon('year', 18) }}<span><strong>Annual planner</strong><small>See the whole year at a glance</small></span>{{ MUi::icon('right', 16) }}</a>
      <a href="{{ route('meetings.groups', []) }}">{{ MUi::icon('layers', 18) }}<span><strong>Groups &amp; committees</strong><small>Board, management, committees</small></span>{{ MUi::icon('right', 16) }}</a>
      <a href="{{ route('meetings.decisions_list', []) }}">{{ MUi::icon('gavel', 18) }}<span><strong>Decision register</strong><small>All resolutions and votes</small></span>{{ MUi::icon('right', 16) }}</a>
    </section>
  </div>
</div>
@endsection
