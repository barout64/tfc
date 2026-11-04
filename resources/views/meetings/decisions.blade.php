@extends('layouts.meetings')


@section('title')Decisions{{ '' }}@endsection

@section('content')

<div class="page-head">
  <div><h1>Decision register</h1><p class="muted">Every decision and resolution recorded in your meetings, with voting results.</p></div>
</div>
<div class="toolbar-row">
  <div class="seg seg-lg">
    <a href="{{ route('meetings.decisions_list', ['q' => $q]) }}" class="{{ (! $outcome ? 'active' : '') }}">All</a>
    @foreach (MUi::DECISION_OUTCOMES as $k => $v)<a href="{{ route('meetings.decisions_list', ['q' => $q, 'outcome' => $k]) }}" class="{{ (($outcome == $k) ? 'active' : '') }}">{{ $v }}</a>@endforeach
  </div>
  <form class="filters" method="get">
    <input type="hidden" name="outcome" value="{{ $outcome }}">
    <div class="input-icon">{{ MUi::icon('search', 15) }}<input type="search" name="q" value="{{ $q }}" placeholder="Search decisions or meetings"></div>
    @if ($q)<a class="link" href="{{ route('meetings.decisions_list', ['outcome' => $outcome]) }}">Clear</a>@endif
  </form>
</div>
@if ($decisions)
<div class="card table-wrap flush">
  <table class="table">
    <thead><tr><th>Decision</th><th>Outcome</th><th class="hide-sm num">Votes (for / against / abstain)</th><th>Meeting</th><th>Date</th></tr></thead>
    <tbody>
    @foreach ($decisions as $d)
      <tr>
        <td>{{ $d->text }}</td>
        <td>@if ($d->outcome){{ MUi::outcomeBadge($d->outcome) }}@else <span class="muted">—</span>@endif</td>
        <td class="hide-sm num nowrap">@if (($d->votes_for !== null ?: $d->votes_against !== null)){{ ($d->votes_for ?: 0) }} / {{ ($d->votes_against ?: 0) }} / {{ ($d->votes_abstain ?: 0) }}@else <span class="muted">—</span>@endif</td>
        <td><a href="{{ route('meetings.meeting_detail', ['meeting_id' => $d->meeting_id]) }}#decisions">{{ $d->meeting_title }}</a><div class="muted small">{{ $d->meeting_type }}</div></td>
        <td class="nowrap">{{ MUi::date($d->start_time) }}</td>
      </tr>
    @endforeach
    </tbody>
  </table>
</div>
@else 
<div class="card empty">{{ MUi::icon('gavel', 30) }}<p>No decisions found.</p></div>
@endif
@endsection
