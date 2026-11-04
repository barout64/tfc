@extends('layouts.meetings')


@section('title'){{ ($is_new ? 'New meeting' : 'Edit meeting') }}@endsection

@section('content')

<a class="back-link" href="{{ ($is_new ? route('meetings.meetings_list', []) : route('meetings.meeting_detail', ['meeting_id' => $meeting_id])) }}">{{ MUi::icon('left', 16) }} Back</a>
<div class="page-head">
  <div>
    <h1>{{ ($is_new ? 'Schedule a meeting' : 'Edit meeting') }}</h1>
    <p class="muted">{{ ($is_new ? 'Create a single meeting or plan a recurring series for the whole year.' : 'Update the details, time, room or participants.') }}</p>
  </div>
</div>

<form method="post" class="form form-grid" id="meetingForm" data-preview-url="{{ route('meetings.meeting_preview', []) }}">
  @csrf
  <div class="form-col">
    <section class="card">
      <div class="section-title"><span class="step">1</span><h2>Meeting details</h2></div>
      <label>Title *
        <input name="title" required maxlength="200" value="{{ ($meeting->title ?: '') }}" placeholder="e.g. Monthly Management Meeting">
      </label>
      <div class="row-2">
        <label>Type
          <select name="meeting_type">
            @foreach (MUi::MEETING_TYPES as $t)<option {{ (($meeting->meeting_type == $t) ? 'selected' : '') }}>{{ $t }}</option>@endforeach
          </select>
        </label>
        <label>Quorum <span class="label-hint">% of participants needed</span>
          <input type="number" name="quorum" min="0" max="100" value="{{ ($meeting->quorum ?: 50) }}">
        </label>
      </div>
      <label>Purpose / description
        <textarea name="description" rows="3" placeholder="What is this meeting about? What should people prepare?">{{ ($meeting->description ?: '') }}</textarea>
      </label>
    </section>

    <section class="card">
      <div class="section-title"><span class="step">2</span><h2>Date, time &amp; place</h2></div>
      <div class="row-3">
        <label>{{ ($is_new ? 'First date *' : 'Date *') }}<input type="date" name="date" id="fDate" required value="{{ ($meeting->date ?: '') }}"></label>
        <label>Start *<input type="time" name="start" id="fStart" required value="{{ ($meeting->start ?: '') }}"></label>
        <label>End *<input type="time" name="end" id="fEnd" required value="{{ ($meeting->end ?: '') }}"></label>
      </div>
      <div class="row-2">
        <label>Room
          <select name="room_id" id="fRoom">
            <option value="">No room (online only)</option>
            @foreach ($rooms as $r)
            <option value="{{ $r->id }}" {{ (((string) $meeting->room_id == (string) $r->id) ? 'selected' : '') }}>{{ $r->name }} · {{ $r->capacity }} seats{{ '' }}@if ($r->location) · {{ $r->location }}@endif</option>
            @endforeach
          </select>
        </label>
        <label>Online meeting link
          <input type="url" name="online_link" value="{{ ($meeting->online_link ?: '') }}" placeholder="https://zoom.us/j/… or a Teams / Meet link">
        </label>
      </div>

      @if ($is_new)
      <div class="recur" id="recur">
        <div class="recur-head">
          <span class="recur-icon">{{ MUi::icon('repeat', 18) }}</span>
          <div><strong>Repeat</strong><div class="muted small">Plan a series, e.g. a board meeting on the 2nd Tuesday of every month for the whole year.</div></div>
        </div>
        <div class="row-2">
          <label>Frequency
            <select name="repeat" id="fRepeat">
              @foreach (MUi::REPEAT_OPTIONS as $k => $v)<option value="{{ $k }}" {{ (($meeting->repeat == $k) ? 'selected' : '') }}>{{ $v }}</option>@endforeach
            </select>
          </label>
          <label data-recur-only>Ends
            <select name="end_mode" id="fEndMode">
              @foreach (MUi::END_OPTIONS as $k => $v)<option value="{{ $k }}" {{ (($meeting->end_mode == $k) ? 'selected' : '') }}>{{ $v }}</option>@endforeach
            </select>
          </label>
        </div>
        <div class="row-2" data-recur-only>
          <label id="wrapCount">Number of meetings
            <input type="number" name="occurrences" id="fCount" min="1" max="120" value="{{ ($meeting->occurrences ?: 12) }}">
          </label>
          <label id="wrapUntil">Last possible date
            <input type="date" name="until" id="fUntil" value="{{ ($meeting->until ?: '') }}">
          </label>
        </div>
        <div class="preset-row" data-recur-only>
          <span class="muted small">Quick presets:</span>
          <button type="button" class="chip" data-preset="monthly_weekday:year_end">Monthly · rest of year</button>
          <button type="button" class="chip" data-preset="quarterly:twelve_months">Quarterly · 12 months</button>
          <button type="button" class="chip" data-preset="weekly:year_end">Weekly · rest of year</button>
          <button type="button" class="chip" data-preset="biweekly:twelve_months">Every 2 weeks · 12 months</button>
        </div>
        <div class="preview" id="preview" data-recur-only aria-live="polite">
          <div class="preview-head"><strong id="pvSummary">—</strong><span class="muted small" id="pvCount"></span></div>
          <div class="preview-dates" id="pvDates"></div>
        </div>
      </div>
      @elseif (($series_count > 1))
      <div class="scope-box">
        <strong>{{ MUi::icon('repeat', 16) }} This meeting is part of a series</strong>
        <label class="radio"><input type="radio" name="scope" value="this" checked> Apply changes to <b>this meeting only</b></label>
        <label class="radio"><input type="radio" name="scope" value="following"> Apply to <b>this and the {{ ($series_count - 1) }} following meetings</b> <span class="muted small">(new time, room, details and participants; each keeps its own date)</span></label>
      </div>
      @endif
    </section>
  </div>

  <div class="form-col">
    <section class="card sticky-card">
      <div class="section-title"><span class="step">3</span><h2>Participants</h2></div>
      <p class="muted small">Invite people one by one, a whole department, or a group such as the Board. You are added automatically as the organizer.</p>
      @include('meetings._picker', ['data' => $people_data, 'selected' => $selected, 'sel_departments' => $sel_departments, 'sel_groups' => $sel_groups])
    </section>
  </div>

  <div class="form-actions sticky-actions">
    <button class="btn btn-primary btn-lg" type="submit" id="submitBtn">{{ MUi::icon('tick', 16) }} <span id="submitLabel">{{ ($is_new ? 'Create meeting' : 'Save changes') }}</span></button>
    <a class="btn btn-lg" href="{{ ($is_new ? route('meetings.meetings_list', []) : route('meetings.meeting_detail', ['meeting_id' => $meeting_id])) }}">Cancel</a>
  </div>
</form>
@endsection
