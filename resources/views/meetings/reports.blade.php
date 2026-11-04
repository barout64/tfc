@extends('layouts.meetings')

@section('title')Reports{{ '' }}@endsection

@section('content')

<div class="page-head">
  <div><h1>Reports {{ $year }}</h1><p class="muted">{{ (MUi::isAdmin() ? 'All meetings in the organization' : 'Meetings you organize or attend') }}</p></div>
  <form method="get" class="inline-form">
    <select name="year" onchange="this.form.submit()" aria-label="Year">
      @foreach (MUi::pyRange(((int) MUi::slice(MUi::today(), null, 4) + 1), ((int) MUi::slice(MUi::today(), null, 4) - 5), -1) as $y)<option {{ (((string) $y == $year) ? 'selected' : '') }}>{{ $y }}</option>@endforeach
    </select>
  </form>
</div>

<div class="stats">
  <div class="stat"><span class="stat-label">Meetings</span><span class="stat-value">{{ $totals->meetings }}</span></div>
  <div class="stat"><span class="stat-label">Completed</span><span class="stat-value">{{ $totals->completed }}</span></div>
  <div class="stat"><span class="stat-label">Attendance rate</span><span class="stat-value">{{ ($totals->recorded ? ((int) round((($totals->present / $totals->recorded) * 100)) . '%') : '—') }}</span></div>
  <div class="stat"><span class="stat-label">Tasks completed</span><span class="stat-value">{{ $totals->actions_done }}/{{ $totals->actions }}</span></div>
</div>

<div class="grid-2">
  <section class="card">
    <h2>Meetings per month</h2>
    <div class="bar-chart" role="img" aria-label="Meetings per month">
      @foreach ($months as [$label, $n])
      <div class="bar-col" title="{{ $label }}: {{ $n }} meeting(s)">
        <span class="bar-val">{{ ($n ? $n : '') }}</span>
        <div class="bar" style="height: {{ (int) round((($n / $max_month) * 100)) }}%"></div>
        <span class="bar-label">{{ $label }}</span>
      </div>
      @endforeach
    </div>
  </section>

  <section class="card">
    <h2>Meetings by type</h2>
    @if ($by_type)
    @php($top = $by_type[0]->n)
    <div class="hbars">
      @foreach ($by_type as $t)
      <div class="hbar-row"><span class="hbar-label">{{ $t->label }}</span>
        <div class="hbar-track"><div class="hbar" style="width: {{ (int) round((($t->n / $top) * 100)) }}%"></div></div>
        <span class="hbar-val">{{ $t->n }}</span></div>
      @endforeach
    </div>
    @else <div class="empty">No meetings this year.</div>@endif
  </section>

  <section class="card">
    <h2>Attendance by department</h2>
    @if ($attendance)
    <div class="table-wrap">
    <table class="table">
      <thead><tr><th>Department</th><th class="num">Present</th><th class="num">Apologies</th><th class="num">Absent</th><th class="num">Rate</th></tr></thead>
      <tbody>
      @foreach ($attendance as $a)
        @php($rate = (int) round((($a->present / $a->recorded) * 100)))
        <tr><td>{{ $a->label }}</td><td class="num">{{ $a->present }}</td><td class="num">{{ $a->excused }}</td><td class="num">{{ $a->absent }}</td>
          <td class="num"><span class="rate {{ (($rate >= 75) ? 'good' : (($rate >= 50) ? 'mid' : 'bad')) }}">{{ $rate }}%</span></td></tr>
      @endforeach
      </tbody>
    </table>
    </div>
    @else <div class="empty">No attendance recorded yet.</div>@endif
  </section>

  <section class="card">
    <h2>Action items by person</h2>
    @if ($actions)
    <div class="table-wrap">
    <table class="table">
      <thead><tr><th>Person</th><th class="num">Total</th><th class="num">Done</th><th class="num">Overdue</th></tr></thead>
      <tbody>
      @foreach ($actions as $a)
        <tr><td>{{ $a->label }}</td><td class="num">{{ $a->total }}</td><td class="num">{{ ($a->done ?: 0) }}</td>
          <td class="num">@if ($a->overdue)<span class="badge badge-overdue">{{ $a->overdue }}</span>@else 0{{ '' }}@endif</td></tr>
      @endforeach
      </tbody>
    </table>
    </div>
    @else <div class="empty">No action items this year.</div>@endif
  </section>

  <section class="card">
    <h2>Room usage</h2>
    @if ($room_usage)
    <div class="table-wrap">
    <table class="table">
      <thead><tr><th>Room</th><th class="num">Meetings</th><th class="num">Hours booked</th></tr></thead>
      <tbody>@foreach ($room_usage as $r)<tr><td>{{ $r->label }}</td><td class="num">{{ $r->n }}</td><td class="num">{{ sprintf('%.1f', ($r->hours ?: 0)) }}</td></tr>@endforeach</tbody>
    </table>
    </div>
    @else <div class="empty">No rooms booked this year.</div>@endif
  </section>
</div>
@endsection
