@extends('layouts.meetings')


@section('title')Rooms{{ '' }}@endsection

@section('content')

<div class="page-head">
  <div><h1>Meeting rooms</h1><p class="muted">Bookings for {{ MUi::longdate($day) }}.</p></div>
  <form method="get" class="cal-nav">
    <a class="icon-btn" href="{{ route('meetings.rooms', ['day' => $prev_day]) }}" aria-label="Previous day">{{ MUi::icon('left') }}</a>
    <input type="date" name="day" value="{{ $day }}" onchange="this.form.submit()" aria-label="Day" class="date-input">
    <a class="icon-btn" href="{{ route('meetings.rooms', ['day' => $next_day]) }}" aria-label="Next day">{{ MUi::icon('right') }}</a>
    <a class="btn btn-sm" href="{{ route('meetings.rooms', []) }}">Today</a>
  </form>
</div>

<div class="room-grid">
  @forelse ($rooms as $r)
  <section class="card room">
    <div class="card-head">
      <h2>{{ $r->name }}</h2>
      <span class="badge">{{ $r->capacity }} people</span>
    </div>
    <div class="muted small">@if ($r->location){{ MUi::icon('pin', 13) }} {{ $r->location }}@endif{{ '' }}@if ($r->equipment) · {{ $r->equipment }}@endif</div>
    <ul class="booking-list">
      @forelse (($by_room[$r->id] ?? []) as $b)
        <li style="--c: {{ MUi::typeColor($b->meeting_type) }}"><span class="nowrap"><strong>{{ MUi::time($b->start_time) }}–{{ MUi::time($b->end_time) }}</strong></span>
          <span><a href="{{ route('meetings.meeting_detail', ['meeting_id' => $b->id]) }}">{{ $b->title }}</a><small class="muted"> · {{ $b->organizer_name }}</small></span></li>
      @empty 
        <li class="free">Free all day</li>
      @endforelse
    </ul>
    @if (MUi::isAdmin())
    <details class="room-edit">
      <summary>Edit room</summary>
      <form method="post" class="form">
        @csrf<input type="hidden" name="room_id" value="{{ $r->id }}">
        <label>Name<input name="name" value="{{ $r->name }}" required></label>
        <div class="row-2">
          <label>Capacity<input type="number" name="capacity" min="1" value="{{ $r->capacity }}"></label>
          <label>Location<input name="location" value="{{ ($r->location ?: '') }}"></label>
        </div>
        <label>Equipment<input name="equipment" value="{{ ($r->equipment ?: '') }}"></label>
        <div class="form-actions">
          <button class="btn btn-primary btn-sm" type="submit">Save</button>
          <button class="btn btn-sm btn-danger-outline" type="submit" formaction="{{ route('meetings.room_delete', ['room_id' => $r->id]) }}" data-confirm="Delete {{ $r->name }}?">Delete room</button>
        </div>
      </form>
    </details>
    @endif
  </section>
  @empty 
  <div class="card empty">No rooms have been added yet.</div>
  @endforelse
</div>

@if (MUi::isAdmin())
<section class="card narrow">
  <h2>Add a room</h2>
  <form method="post" class="form">
    @csrf
    <label>Name *<input name="name" required placeholder="e.g. Conference Room B"></label>
    <div class="row-2">
      <label>Capacity<input type="number" name="capacity" min="1" value="10"></label>
      <label>Location<input name="location" placeholder="e.g. 2nd Floor"></label>
    </div>
    <label>Equipment<input name="equipment" placeholder="e.g. Projector, whiteboard"></label>
    <div class="form-actions"><button class="btn btn-primary" type="submit">{{ MUi::icon('plus', 16) }} Add room</button></div>
  </form>
</section>
@endif
@endsection
