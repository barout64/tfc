<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Minutes – {{ $meeting->title }}</title>
  <link rel="stylesheet" href="{{ asset('meeting-app/print.css') }}">
</head>
<body>
<div class="toolbar">
  <button onclick="window.print()">Print / Save as PDF</button>
  <a href="{{ route('meetings.meeting_detail', ['meeting_id' => $meeting->id]) }}">Back to meeting</a>
</div>

<article class="doc">
  <header>
    <div class="org">{{ MUi::org() }}</div>
    <h1>{{ ($minutes ? 'Minutes of the ' : 'Notice and Agenda: ') }}{{ $meeting->title }}</h1>
    <table class="meta">
      <tr><th>Type</th><td>{{ $meeting->meeting_type }}</td></tr>
      <tr><th>Date</th><td>{{ MUi::date($meeting->start_time) }}</td></tr>
      <tr><th>Time</th><td>{{ MUi::time($meeting->start_time) }} – {{ MUi::time($meeting->end_time) }}</td></tr>
      <tr><th>Venue</th><td>{{ ($meeting->room_name ?: '') }}@if ($meeting->room_location), {{ $meeting->room_location }}@endif{{ '' }}@if ($meeting->online_link){{ ($meeting->room_name ? ' / ' : '') }}{{ $meeting->online_link }}@endif</td></tr>
      <tr><th>Chairperson / Organizer</th><td>{{ $meeting->organizer_name }}</td></tr>
      @if ($minutes)<tr><th>Minutes status</th><td>{{ ucfirst($minutes->status) }}@if (($minutes->status != 'approved')) — DRAFT, not yet approved{{ '' }}@endif</td></tr>@endif
    </table>
  </header>

  @php($present_list = array_filter($participants, fn ($r) => $r->attendance == 'present'))
  @php($excused_list = array_filter($participants, fn ($r) => $r->attendance == 'excused'))
  @php($absent_list = array_filter($participants, fn ($r) => $r->attendance == 'absent'))
  <section>
    <h2>Attendance</h2>
    @if ((($present_list ?: $excused_list) ?: $absent_list))
      <p><strong>Present ({{ count($present_list) }}):</strong> @foreach ($present_list as $p){{ $p->name }}@if ($p->job_title) ({{ $p->job_title }})@endif{{ (! $loop->last ? ', ' : '') }}@endforeach</p>
      @if ($excused_list)<p><strong>Apologies ({{ count($excused_list) }}):</strong> @foreach ($excused_list as $p){{ $p->name }}{{ (! $loop->last ? ', ' : '') }}@endforeach</p>@endif
      @if ($absent_list)<p><strong>Absent ({{ count($absent_list) }}):</strong> @foreach ($absent_list as $p){{ $p->name }}{{ (! $loop->last ? ', ' : '') }}@endforeach</p>@endif
    @else 
      <p><strong>Invited ({{ count($participants) }}):</strong> @foreach ($participants as $p){{ $p->name }}{{ (! $loop->last ? ', ' : '') }}@endforeach</p>
    @endif
  </section>

  <section>
    <h2>Agenda</h2>
    @if ($agenda)
    <ol>@foreach ($agenda as $a)<li>{{ $a->title }}@if ($a->presenter_name) — <em>{{ $a->presenter_name }}</em>@endif{{ '' }}@if ($a->duration) ({{ $a->duration }} min)@endif</li>@endforeach</ol>
    @else <p>No agenda items.</p>@endif
  </section>

  @if (($minutes && $minutes->content))
  <section>
    <h2>Proceedings</h2>
    <div class="prewrap">{{ $minutes->content }}</div>
  </section>
  @endif

  @if ($decisions)
  <section>
    <h2>Decisions / Resolutions</h2>
    <ol>@foreach ($decisions as $d)<li>{{ $d->text }}
      @if (($d->proposer_name ?: $d->seconder_name))<br><small>@if ($d->proposer_name)Proposed: {{ $d->proposer_name }}@endif{{ '' }}@if ($d->seconder_name){{ ($d->proposer_name ? '; ' : '') }}Seconded: {{ $d->seconder_name }}@endif</small>@endif
      @if ((($d->votes_for !== null ?: $d->votes_against !== null) ?: $d->votes_abstain !== null))<br><small>Votes — For: {{ ($d->votes_for ?: 0) }}, Against: {{ ($d->votes_against ?: 0) }}, Abstained: {{ ($d->votes_abstain ?: 0) }}</small>@endif
      @if ($d->outcome) <strong>[{{ MUi::DECISION_OUTCOMES[$d->outcome] }}]</strong>@endif</li>@endforeach</ol>
  </section>
  @endif

  @if ($actions)
  <section>
    <h2>Action Items</h2>
    <table class="grid">
      <thead><tr><th>#</th><th>Action</th><th>Responsible</th><th>Due date</th><th>Status</th></tr></thead>
      <tbody>@foreach ($actions as $a)<tr><td>{{ $loop->iteration }}</td><td>{{ $a->title }}</td><td>{{ ($a->assignee_name ?: '—') }}</td><td>{{ ($a->due_date ? MUi::date($a->due_date) : '—') }}</td><td>{{ MUi::ACTION_STATUSES[$a->status] }}</td></tr>@endforeach</tbody>
    </table>
  </section>
  @endif

  @if ($minutes)
  <section class="signatures">
    <div><div class="line"></div>Secretary{{ '' }}@if ($minutes->updated_by_name): {{ $minutes->updated_by_name }}@endif</div>
    <div><div class="line"></div>Chairperson{{ '' }}@if ($minutes->approved_by_name): {{ $minutes->approved_by_name }}@endif
      @if ($minutes->approved_at)<br><small>Approved {{ MUi::date($minutes->approved_at) }}</small>@endif</div>
  </section>
  @endif
</article>
</body>
</html>