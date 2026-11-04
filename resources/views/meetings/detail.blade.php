@extends('layouts.meetings')


@section('title'){{ $meeting->title }}@endsection

@section('content')

<a class="back-link" href="{{ route('meetings.meetings_list', []) }}">{{ MUi::icon('left', 16) }} All meetings</a>

<section class="card meeting-header" style="--c: {{ MUi::typeColor($meeting->meeting_type) }}">
  <div class="mh-top">
    <div class="mh-main">
      <div class="mh-badges">
        {{ MUi::typeChip($meeting->meeting_type) }}
        {{ MUi::statusBadge($meeting->status) }}
        @if ($series)<span class="badge">{{ MUi::icon('repeat', 12) }} Meeting {{ ($series_index + 1) }} of {{ count($series) }}</span>@endif
      </div>
      <h1>{{ $meeting->title }}</h1>
      <div class="mh-meta">
        <span>{{ MUi::icon('calendar', 16) }} {{ MUi::longdate($meeting->start_time) }}</span>
        <span>{{ MUi::icon('clock', 16) }} {{ MUi::time($meeting->start_time) }} – {{ MUi::time($meeting->end_time) }}</span>
        @if ($meeting->room_name)<span>{{ MUi::icon('pin', 16) }} {{ $meeting->room_name }}@if ($meeting->room_location), {{ $meeting->room_location }}@endif</span>@endif
        @if ($meeting->online_link)<span>{{ MUi::icon('video', 16) }} <a href="{{ $meeting->online_link }}" target="_blank" rel="noopener">Join online</a></span>@endif
        <span>{{ MUi::icon('user', 16) }} {{ $meeting->organizer_name }}</span>
      </div>
      @if ($audience)
      <div class="audience">
        <span class="muted small">Invited:</span>
        @foreach ($audience as $a)
          <span class="aud-chip" @if ($a->color)style="--c: {{ $a->color }}"@endif>{{ MUi::icon((($a->kind == 'group') ? 'layers' : 'briefcase'), 13) }} {{ $a->name }}</span>
        @endforeach
      </div>
      @endif
    </div>
    <div class="mh-side">
      {{ MUi::avatarStack(array_column($participants, 'name'), 6) }}
      <div class="mh-actions">
        @if (($meeting->online_link && (in_array($meeting->status, ['scheduled', 'in_progress']))))<a class="btn btn-sm btn-primary" href="{{ $meeting->online_link }}" target="_blank" rel="noopener">{{ MUi::icon('video', 15) }} Join</a>@endif
        @if ($manage)<a class="btn btn-sm" href="{{ route('meetings.meeting_edit', ['meeting_id' => $meeting->id]) }}">{{ MUi::icon('edit', 15) }} Edit</a>@endif
        <a class="btn btn-sm" href="{{ route('meetings.meeting_print', ['meeting_id' => $meeting->id]) }}" target="_blank">{{ MUi::icon('printer', 15) }} Print / PDF</a>
        <details class="dropdown">
          <summary class="btn btn-sm">{{ MUi::icon('download', 15) }} Add to calendar</summary>
          <div class="menu">
            <a href="{{ route('meetings.meeting_ics', ['meeting_id' => $meeting->id]) }}">This meeting (.ics)</a>
            @if ($series)<a href="{{ route('meetings.meeting_ics', ['meeting_id' => $meeting->id, 'series' => 1]) }}">Whole series ({{ count($series) }} meetings)</a>@endif
          </div>
        </details>
        @if (($record && ($meeting->status == 'scheduled')))
        <form method="post" action="{{ route('meetings.meeting_start', ['meeting_id' => $meeting->id]) }}" class="inline-form">@csrf<button class="btn btn-sm btn-success">{{ MUi::icon('play', 15) }} Start meeting</button></form>
        @elseif (($record && ($meeting->status == 'in_progress')))
        <form method="post" action="{{ route('meetings.meeting_end', ['meeting_id' => $meeting->id]) }}" class="inline-form" data-confirm="End this meeting now?">@csrf<button class="btn btn-sm btn-danger">{{ MUi::icon('stop', 15) }} End meeting</button></form>
        @endif
        @if ($record)
        <form method="post" action="{{ route('meetings.meeting_status', ['meeting_id' => $meeting->id]) }}" class="inline-form">
          @csrf
          <select name="status" aria-label="Change status" onchange="this.form.submit()" class="select-sm">
            @foreach (MUi::MEETING_STATUSES as $k => $v)
              @if ((($k != 'cancelled') ?: $manage))<option value="{{ $k }}" {{ (($meeting->status == $k) ? 'selected' : '') }}>{{ $v }}</option>@endif
            @endforeach
          </select>
        </form>
        @endif
      </div>
    </div>
  </div>

  <ol class="stepper">
    @foreach ($steps as [$label, $done])
    <li class="{{ ($done ? 'done' : '') }}"><span class="st-dot">@if ($done){{ MUi::icon('tick', 12) }}@else {{ $loop->iteration }}@endif</span>{{ $label }}</li>
    @endforeach
  </ol>
</section>

@if (($meeting->status == 'in_progress'))
@php($live = (object) ['cur' => null, 'num' => 0, 'nxt' => null])
@foreach ($agenda as $a)
  @if (($live->cur !== null && $live->nxt === null))@php($live->nxt = $a)@endif
  @if (($a->id == $meeting->current_agenda_id))@php($live->cur = $a)@php($live->num = $loop->iteration)@endif
@endforeach
<section class="card live-bar" data-live-url="{{ route('meetings.meeting_live', ['meeting_id' => $meeting->id]) }}" data-current="{{ ($meeting->current_agenda_id ?: '') }}">
  <span class="live-dot"></span>
  <div class="live-main">
    <div class="live-label">Live{{ '' }}@if ($meeting->started_at) · started {{ MUi::time($meeting->started_at) }}@endif</div>
    <div class="live-now" id="liveNow">
      @if ($live->cur)Now discussing: <a href="#agenda" data-tab-link="agenda"><strong>{{ $live->num }}. {{ $live->cur->title }}</strong></a> <span class="muted small">since {{ MUi::time($live->cur->started_at) }}</span>
      @elseif ($agenda)No agenda item picked yet{{ '' }}@if ($record) — press “Discuss now” on an item{{ '' }}@endif
      @else No agenda items{{ '' }}@endif
    </div>
  </div>
  @if (($record && $live->nxt))
  <form method="post" action="{{ route('meetings.agenda_now', ['item_id' => $live->nxt->id]) }}">@csrf<button class="btn btn-sm btn-primary">Next item {{ MUi::icon('arrow-right', 15) }}</button></form>
  @elseif ((($record && $agenda) && ! $live->cur))
  <form method="post" action="{{ route('meetings.agenda_now', ['item_id' => $agenda[0]->id]) }}">@csrf<button class="btn btn-sm btn-primary">Start item 1 {{ MUi::icon('arrow-right', 15) }}</button></form>
  @endif
</section>
@endif

@if (($me && ($meeting->status == 'scheduled')))
<section class="card rsvp-bar">
  <div><strong>Will you attend?</strong> <span class="muted">Your reply:</span> {{ MUi::rsvpBadge($me->rsvp) }}</div>
  <form method="post" action="{{ route('meetings.meeting_rsvp', ['meeting_id' => $meeting->id]) }}" class="btn-group">
    @csrf
    <button class="btn btn-sm {{ (($me->rsvp == 'accepted') ? 'btn-success' : '') }}" name="rsvp" value="accepted">{{ MUi::icon('tick', 14) }} Accept</button>
    <button class="btn btn-sm {{ (($me->rsvp == 'tentative') ? 'btn-warning' : '') }}" name="rsvp" value="tentative">Maybe</button>
    <button class="btn btn-sm {{ (($me->rsvp == 'declined') ? 'btn-danger' : 'btn-ghost-danger') }}" name="rsvp" value="declined">Decline</button>
  </form>
</section>
@endif

<nav class="tabs" role="tablist">
  <a href="#overview" data-tab="overview">Overview</a>
  <a href="#participants" data-tab="participants">Participants <span class="count">{{ $total }}</span></a>
  <a href="#agenda" data-tab="agenda">Agenda <span class="count">{{ count($agenda) }}</span></a>
  <a href="#minutes" data-tab="minutes">Minutes{{ '' }}@if ($minutes) <span class="count count-{{ $minutes->status }}">{{ $minutes->status }}</span>@endif</a>
  <a href="#decisions" data-tab="decisions">Decisions <span class="count">{{ count($decisions) }}</span></a>
  <a href="#actions" data-tab="actions">Action items <span class="count">{{ count($actions) }}</span></a>
  <a href="#files" data-tab="files">Files <span class="count">{{ count($attachments) }}</span></a>
</nav>


<div class="tab-panel" id="tab-overview">
  <div class="grid-main">
    <div>
      <section class="card">
        <h2>About this meeting</h2>
        @if ($meeting->description)<p class="prewrap">{{ $meeting->description }}</p>@else <p class="muted">No description.</p>@endif
        <dl class="facts">
          <dt>When</dt><dd>{{ MUi::longdate($meeting->start_time) }}, {{ MUi::time($meeting->start_time) }} – {{ MUi::time($meeting->end_time) }}@if ($agenda_minutes) · agenda planned for {{ $agenda_minutes }} min{{ '' }}@endif</dd>
          @if ($meeting->started_at)<dt>Held</dt><dd>started {{ MUi::time($meeting->started_at) }}@if ($meeting->ended_at), ended {{ MUi::time($meeting->ended_at) }}@if ((MUi::slice($meeting->ended_at, null, 10) != MUi::slice($meeting->started_at, null, 10))) ({{ MUi::date($meeting->ended_at) }})@endif{{ '' }}@else  · still running{{ '' }}@endif</dd>@endif
          @if ($meeting->room_name)<dt>Room</dt><dd>{{ $meeting->room_name }} · {{ $meeting->room_capacity }} seats @if (($total > $meeting->room_capacity))<span class="badge badge-overdue">over capacity</span>@endif</dd>@endif
          <dt>Quorum</dt><dd>{{ $meeting->quorum }}% — at least {{ $quorum_needed }} of {{ $total }} people</dd>
          <dt>Organizer</dt><dd>{{ $meeting->organizer_name }}</dd>
        </dl>
      </section>

      @if ($series)
      <section class="card">
        <div class="card-head"><h2>{{ MUi::icon('repeat', 17) }} Meeting series</h2><span class="muted small">{{ count($series) }} meetings</span></div>
        <div class="series-strip">
          @foreach ($series as $s)
          <a href="{{ route('meetings.meeting_detail', ['meeting_id' => $s->id]) }}" class="series-item {{ (($s->id == $meeting->id) ? 'current' : '') }} st-{{ $s->status }} {{ (($s->end_time < MUi::now()) ? 'past' : '') }}" title="{{ MUi::datetime($s->start_time) }} · {{ MUi::MEETING_STATUSES[$s->status] }}">
            <span class="si-mon">{{ MUi::mon($s->start_time) }}</span><span class="si-day">{{ MUi::slice($s->start_time, 8, 10) }}</span>
          </a>
          @endforeach
        </div>
      </section>
      @endif
    </div>

    <div>
      <section class="card">
        <h2>Responses</h2>
        <div class="mini-stats">
          <div class="ms-ok"><span>{{ $rsvp_counts->accepted }}</span>Accepted</div>
          <div class="ms-warn"><span>{{ $rsvp_counts->tentative }}</span>Maybe</div>
          <div class="ms-bad"><span>{{ $rsvp_counts->declined }}</span>Declined</div>
          <div><span>{{ $rsvp_counts->pending }}</span>No reply</div>
        </div>
        @if ($total)
        <div class="stack-bar" aria-hidden="true">
          <span class="sb-ok" style="width: {{ (($rsvp_counts->accepted / $total) * 100) }}%"></span>
          <span class="sb-warn" style="width: {{ (($rsvp_counts->tentative / $total) * 100) }}%"></span>
          <span class="sb-bad" style="width: {{ (($rsvp_counts->declined / $total) * 100) }}%"></span>
        </div>
        @endif
        @if ((($attendance_counts->present ?: $attendance_counts->absent) ?: $attendance_counts->excused))
        <div class="quorum {{ ($quorum_met ? 'ok' : 'no') }}">{{ MUi::icon(($quorum_met ? 'tick' : 'alert'), 15) }} {{ $present }} of {{ $total }} present — quorum {{ ($quorum_met ? 'reached' : 'not reached') }}</div>
        @endif
      </section>
      <section class="card">
        <h2>At a glance</h2>
        <ul class="glance">
          <li><a href="#agenda" data-tab-link="agenda">{{ MUi::icon('list', 16) }} {{ count($agenda) }} agenda item{{ ((count($agenda) != 1) ? 's' : '') }}</a></li>
          <li><a href="#decisions" data-tab-link="decisions">{{ MUi::icon('gavel', 16) }} {{ count($decisions) }} decision{{ ((count($decisions) != 1) ? 's' : '') }}</a></li>
          <li><a href="#actions" data-tab-link="actions">{{ MUi::icon('tasks', 16) }} {{ count(array_filter($actions, fn ($r) => $r->status != 'done')) }} open action item(s)</a></li>
          @if ($arising)<li><a href="#agenda" data-tab-link="agenda">{{ MUi::icon('flag', 16) }} {{ count($arising) }} matter(s) arising from earlier meetings</a></li>@endif
          <li><a href="#minutes" data-tab-link="minutes">{{ MUi::icon('file', 16) }} Minutes: {{ ($minutes ? $minutes->status : 'not started') }}</a></li>
        </ul>
      </section>
    </div>
  </div>
</div>


<div class="tab-panel" id="tab-participants">
  <section class="card">
    <div class="card-head">
      <h2>Participants &amp; attendance</h2>
      @if ((($attendance_counts->present ?: $attendance_counts->absent) ?: $attendance_counts->excused))
      <span class="quorum {{ ($quorum_met ? 'ok' : 'no') }}">{{ $present }}/{{ $total }} present · quorum {{ ($quorum_met ? 'reached' : 'not reached') }}</span>
      @endif
    </div>
    <form method="post" action="{{ route('meetings.meeting_attendance', ['meeting_id' => $meeting->id]) }}" id="attForm">@csrf</form>
    <div class="table-wrap">
    <table class="table">
      <thead><tr><th>Name</th><th class="hide-sm">Department</th><th>Reply</th><th>Attendance</th>@if ($manage)<th></th>@endif</tr></thead>
      <tbody>
      @foreach ($participants as $p)
        <tr>
          <td><div class="person-cell">{{ MUi::avatar($p->name) }}<div><strong>{{ $p->name }}</strong>@if (($p->user_id == $meeting->organizer_id)) <span class="badge badge-type">Organizer</span>@endif<div class="muted small">{{ ($p->job_title ?: '') }}</div></div></div></td>
          <td class="hide-sm">{{ ($p->department ?: '—') }}</td>
          <td>{{ MUi::rsvpBadge($p->rsvp) }}</td>
          <td>
            @if ($record)
            <select name="att_{{ $p->user_id }}" form="attForm" class="att-select att-{{ $p->attendance }}" aria-label="Attendance for {{ $p->name }}">
              @foreach (MUi::ATTENDANCE_STATUSES as $k => $v)<option value="{{ $k }}" {{ (($p->attendance == $k) ? 'selected' : '') }}>{{ $v }}</option>@endforeach
            </select>
            @else <span class="badge att-{{ $p->attendance }}">{{ MUi::ATTENDANCE_STATUSES[$p->attendance] }}</span>@endif
          </td>
          @if ($manage)
          <td class="right">
            @if (($p->user_id != $meeting->organizer_id))
            <form method="post" action="{{ route('meetings.participant_remove', ['meeting_id' => $meeting->id, 'user_id' => $p->user_id]) }}" data-confirm="Remove {{ $p->name }} from this meeting?">
              @csrf<button class="icon-btn danger" title="Remove">{{ MUi::icon('x', 16) }}</button>
            </form>
            @endif
          </td>
          @endif
        </tr>
      @endforeach
      </tbody>
    </table>
    </div>
    @if ($record)
    <div class="form-actions">
      <button class="btn btn-primary" form="attForm" type="submit">Save attendance</button>
      <button class="btn" type="button" id="markAllPresent">Mark everyone present</button>
    </div>
    @endif
  </section>

  @if ($manage)
  <section class="card">
    <h2>Invite more people</h2>
    <p class="muted small">Add individuals, whole departments or groups. People already invited are shown as “Invited”.</p>
    <form method="post" action="{{ route('meetings.participant_add', ['meeting_id' => $meeting->id]) }}" class="form">
      @csrf
      @include('meetings._picker', ['data' => $people_data, 'selected' => [], 'locked' => array_column($participants, 'user_id')])
      <div class="form-actions">
        @if ((count($series) > 1))
        <select name="scope" class="select-sm" aria-label="Apply to">
          <option value="this">This meeting only</option>
          <option value="following">This and following meetings in the series</option>
        </select>
        @endif
        <button class="btn btn-primary" type="submit">Send invitations</button>
      </div>
    </form>
  </section>
  @endif
</div>


<div class="tab-panel" id="tab-agenda">
  @if ($arising)
  <section class="card arising">
    <div class="card-head"><h2>{{ MUi::icon('flag', 17) }} Matters arising</h2><span class="muted small">Open action items from earlier meetings</span></div>
    <ul class="task-list">
      @foreach ($arising as $a)
      <li>
        <span class="task-dot {{ (($a->due_date && ($a->due_date < MUi::today())) ? 'overdue' : $a->status) }}"></span>
        <div class="task-main"><div class="task-title">{{ $a->title }}</div>
          <div class="muted small">{{ ($a->assignee_name ?: 'Unassigned') }} · from <a href="{{ route('meetings.meeting_detail', ['meeting_id' => $a->from_id]) }}#actions">{{ MUi::date($a->from_date) }}</a>@if ($a->due_date) · due {{ MUi::date($a->due_date) }}@endif</div></div>
        {{ MUi::actionBadge($a) }}
      </li>
      @endforeach
    </ul>
  </section>
  @endif

  <section class="card">
    <div class="card-head">
      <h2>Agenda</h2>
      <span class="muted small">@if ($agenda_minutes)Total planned time: {{ $agenda_minutes }} min{{ '' }}@endif</span>
    </div>
    @if ($agenda)
    <ol class="agenda">
      @foreach ($agenda as $a)
      <li data-agenda-id="{{ $a->id }}" class="{{ ((($meeting->status == 'in_progress') && ($a->id == $meeting->current_agenda_id)) ? 'now' : '') }} {{ ($a->ended_at ? 'discussed' : '') }}">
        <div class="agenda-num">{{ $loop->iteration }}</div>
        <div class="agenda-body">
          <strong>{{ $a->title }}</strong> <span class="badge now-badge"><span class="bdot"></span> Now discussing</span>
          @if ($a->description)<p class="prewrap muted">{{ $a->description }}</p>@endif
          <div class="agenda-meta">
            @if ($a->presenter_name)<span>{{ MUi::icon('user', 13) }} {{ $a->presenter_name }}</span>@endif
            @if ($a->duration)<span>{{ MUi::icon('clock', 13) }} {{ $a->duration }} min</span>@endif
            @if ($a->spent !== null)<span>{{ MUi::icon('tick', 13) }} discussed {{ MUi::time($a->started_at) }}–{{ MUi::time($a->ended_at) }} ({{ $a->spent }} min)</span>@endif
          </div>
          @if ($a->reports)
          <ul class="agenda-reports">
            @foreach ($a->reports as $f)
            <li>{{ MUi::icon('file', 14) }} <a href="{{ route('meetings.attachment_download', ['item_id' => $f->id]) }}">{{ $f->filename }}</a>
              <span class="muted small">{{ MUi::filesize($f->size) }} · {{ ($f->uploader ?: 'Unknown') }}</span>
              @if (($record ?: ($f->uploaded_by == auth()->user()->id)))
              <form method="post" action="{{ route('meetings.attachment_delete', ['item_id' => $f->id]) }}" data-confirm="Delete {{ $f->filename }}?">@csrf<input type="hidden" name="back" value="agenda"><button class="icon-btn danger" title="Delete">{{ MUi::icon('trash', 14) }}</button></form>
              @endif
            </li>
            @endforeach
          </ul>
          @endif
          @if ((($record ?: $me) && ($meeting->status != 'cancelled')))
          <form method="post" action="{{ route('meetings.attachment_upload', ['meeting_id' => $meeting->id]) }}" enctype="multipart/form-data" class="agenda-upload">
            @csrf<input type="hidden" name="agenda_item_id" value="{{ $a->id }}"><input type="hidden" name="back" value="agenda">
            <label class="btn btn-sm">{{ MUi::icon('file', 14) }} Attach report<input type="file" name="file" required hidden onchange="this.form.submit()"></label>
          </form>
          @endif
        </div>
        @if (($record && ($meeting->status == 'in_progress')))
        <form method="post" action="{{ route('meetings.agenda_now', ['item_id' => $a->id]) }}" class="discuss-btn">@csrf<button class="btn btn-sm">{{ MUi::icon('play', 14) }} Discuss now</button></form>
        @endif
        @if ($record)
        <div class="agenda-tools">
          @if (! $loop->first)<form method="post" action="{{ route('meetings.agenda_move', ['item_id' => $a->id, 'direction' => 'up']) }}">@csrf<button class="icon-btn" title="Move up">{{ MUi::icon('up', 16) }}</button></form>@endif
          @if (! $loop->last)<form method="post" action="{{ route('meetings.agenda_move', ['item_id' => $a->id, 'direction' => 'down']) }}">@csrf<button class="icon-btn" title="Move down">{{ MUi::icon('down', 16) }}</button></form>@endif
          <form method="post" action="{{ route('meetings.agenda_delete', ['item_id' => $a->id]) }}" data-confirm="Delete this agenda item?">@csrf<button class="icon-btn danger" title="Delete">{{ MUi::icon('trash', 16) }}</button></form>
        </div>
        @endif
      </li>
      @endforeach
    </ol>
    @else 
    <div class="empty compact">{{ MUi::icon('list', 26) }}<p>No agenda items yet.</p>
      @if (($record && ($series ?: $arising)))
      <form method="post" action="{{ route('meetings.agenda_copy', ['meeting_id' => $meeting->id]) }}">@csrf<button class="btn btn-sm">{{ MUi::icon('repeat', 14) }} Copy agenda from previous meeting</button></form>
      @endif
    </div>
    @endif
  </section>
  @if ($record)
  <section class="card">
    <h2>Add agenda item</h2>
    <form method="post" action="{{ route('meetings.agenda_add', ['meeting_id' => $meeting->id]) }}" class="form">
      @csrf
      <label>Topic *<input name="title" required placeholder="e.g. Financial report"></label>
      <div class="row-2">
        <label>Presenter
          <select name="presenter_id"><option value="">—</option>
            @foreach ($participants as $p)<option value="{{ $p->user_id }}">{{ $p->name }}</option>@endforeach
          </select>
        </label>
        <label>Time (minutes)<input type="number" name="duration" min="1" max="600" placeholder="15"></label>
      </div>
      <label>Notes<textarea name="description" rows="2" placeholder="Optional details"></textarea></label>
      <div class="form-actions"><button class="btn btn-primary" type="submit">{{ MUi::icon('plus', 16) }} Add item</button></div>
    </form>
  </section>
  @endif
</div>


<div class="tab-panel" id="tab-minutes">
  <section class="card">
    <div class="card-head">
      <h2>Minutes</h2>
      @if ($minutes)<span class="badge minutes-{{ $minutes->status }}">{{ ucfirst($minutes->status) }}</span>@endif
    </div>
    @if ($minutes)
    <p class="muted small">
      @if ($minutes->updated_by_name)Last edited by {{ $minutes->updated_by_name }} on {{ MUi::datetime($minutes->updated_at) }}.@endif
      @if (($minutes->status == 'approved')) Approved by {{ $minutes->approved_by_name }} on {{ MUi::datetime($minutes->approved_at) }}.@endif
    </p>
    @endif

    @if (($record && (! $minutes ?: ($minutes->status != 'approved'))))
    <form method="post" action="{{ route('meetings.minutes_save', ['meeting_id' => $meeting->id]) }}" class="form">
      @csrf
      <textarea name="content" rows="16" class="minutes-editor" placeholder="Record what was discussed under each agenda item…">@if ($minutes){{ $minutes->content }}@else @foreach ($agenda as $a){{ $loop->iteration }}. {{ $a->title }}
-

@endforeach{{ '' }}@endif</textarea>
      <div class="form-actions">
        <button class="btn" name="action" value="save">Save draft</button>
        @if ($manage)
          @if (($minutes && ($minutes->status == 'submitted')))<button class="btn btn-success" name="action" value="approve" data-confirm="Approve these minutes? They will be locked and shared with all participants.">Approve minutes</button>@endif
          @if ((! $minutes ?: ($minutes->status == 'draft')))<button class="btn btn-primary" name="action" value="submit">Save &amp; submit</button>@endif
        @else 
          <button class="btn btn-primary" name="action" value="submit">Submit for approval</button>
        @endif
      </div>
      <p class="muted small">“Approve” approves the minutes as last saved — save your changes first.</p>
    </form>
    @elseif (($minutes && $minutes->content))
      <div class="minutes-view prewrap">{{ $minutes->content }}</div>
      @if (($manage && ($minutes->status == 'approved')))
      <form method="post" action="{{ route('meetings.minutes_save', ['meeting_id' => $meeting->id]) }}" class="form-actions">
        @csrf<button class="btn btn-sm" name="action" value="reopen" data-confirm="Reopen approved minutes for editing?">Reopen for editing</button>
      </form>
      @endif
    @else 
      <div class="empty compact">{{ MUi::icon('file', 26) }}<p>Minutes have not been recorded yet.</p></div>
    @endif
  </section>
</div>


<div class="tab-panel" id="tab-decisions">
  <section class="card">
    <h2>Decisions &amp; resolutions</h2>
    @if ($decisions)
    <ol class="decision-list">
      @foreach ($decisions as $d)
      <li>
        <div class="dec-num">{{ $loop->iteration }}</div>
        <div class="dec-body">
          <div class="dec-text">{{ $d->text }}</div>
          <div class="dec-meta">
            {{ MUi::outcomeBadge($d->outcome) }}
            @if ($d->agenda_title)<span>{{ MUi::icon('list', 13) }} {{ $d->agenda_title }}</span>@endif
            @if ($d->proposer_name)<span>Proposed by {{ $d->proposer_name }}</span>@endif
            @if ($d->seconder_name)<span>Seconded by {{ $d->seconder_name }}</span>@endif
          </div>
          @php([$vf, $va, $vb] = [($d->votes_for ?: 0), ($d->votes_against ?: 0), ($d->votes_abstain ?: 0)])
          @if (((($vf + $va) + $vb) > 0))
          <div class="votes">
            <div class="vote-bar">
              <span class="v-for" style="flex: {{ $vf }}"></span><span class="v-against" style="flex: {{ $va }}"></span><span class="v-abstain" style="flex: {{ $vb }}"></span>
            </div>
            <div class="vote-legend"><span class="lg-for">For {{ $vf }}</span><span class="lg-against">Against {{ $va }}</span><span class="lg-abstain">Abstain {{ $vb }}</span></div>
          </div>
          @endif
        </div>
        @if ($record)<form method="post" action="{{ route('meetings.decision_delete', ['item_id' => $d->id]) }}" data-confirm="Delete this decision?">@csrf<button class="icon-btn danger" title="Delete">{{ MUi::icon('trash', 16) }}</button></form>@endif
      </li>
      @endforeach
    </ol>
    @else <div class="empty compact">{{ MUi::icon('gavel', 26) }}<p>No decisions recorded.</p></div>@endif
  </section>
  @if ($record)
  <section class="card">
    <h2>Record a decision or resolution</h2>
    <form method="post" action="{{ route('meetings.decision_add', ['meeting_id' => $meeting->id]) }}" class="form">
      @csrf
      <label>Resolution / decision *<textarea name="text" rows="2" required placeholder="e.g. RESOLVED that the 2027 budget be approved."></textarea></label>
      <div class="row-3">
        <label>Agenda item
          <select name="agenda_item_id"><option value="">—</option>@foreach ($agenda as $a)<option value="{{ $a->id }}">{{ $loop->iteration }}. {{ $a->title }}</option>@endforeach</select>
        </label>
        <label>Proposed by
          <select name="proposer_id"><option value="">—</option>@foreach ($participants as $p)<option value="{{ $p->user_id }}">{{ $p->name }}</option>@endforeach</select>
        </label>
        <label>Seconded by
          <select name="seconder_id"><option value="">—</option>@foreach ($participants as $p)<option value="{{ $p->user_id }}">{{ $p->name }}</option>@endforeach</select>
        </label>
      </div>
      <div class="row-4">
        <label>Votes for<input type="number" name="votes_for" min="0" placeholder="0"></label>
        <label>Against<input type="number" name="votes_against" min="0" placeholder="0"></label>
        <label>Abstained<input type="number" name="votes_abstain" min="0" placeholder="0"></label>
        <label>Outcome
          <select name="outcome"><option value="">—</option>@foreach (MUi::DECISION_OUTCOMES as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select>
        </label>
      </div>
      <div class="form-actions"><button class="btn btn-primary" type="submit">{{ MUi::icon('gavel', 15) }} Record decision</button></div>
    </form>
  </section>
  @endif
</div>


<div class="tab-panel" id="tab-actions">
  <section class="card">
    <h2>Action items</h2>
    @if ($actions)
    <div class="table-wrap">
    <table class="table">
      <thead><tr><th>Task</th><th>Assigned to</th><th>Due</th><th>Status</th>@if ($record)<th></th>@endif</tr></thead>
      <tbody>
      @foreach ($actions as $a)
      <tr class="{{ (($a->status == 'done') ? 'done-row' : '') }}">
        <td><strong>{{ $a->title }}</strong>@if ($a->description)<div class="muted small">{{ $a->description }}</div>@endif</td>
        <td>@if ($a->assignee_name)<div class="person-cell">{{ MUi::avatar($a->assignee_name, 'sm') }}{{ $a->assignee_name }}</div>@else —@endif</td>
        <td class="nowrap">{{ ($a->due_date ? MUi::date($a->due_date) : '—') }}</td>
        <td>
          @if (($record ?: ($a->assignee_id == auth()->user()->id)))
          <form method="post" action="{{ route('meetings.action_status', ['item_id' => $a->id]) }}" class="inline-form">
            @csrf<input type="hidden" name="back" value="{{ route('meetings.meeting_detail', ['meeting_id' => $meeting->id]) }}#actions">
            <select name="status" onchange="this.form.submit()" class="select-sm" aria-label="Status">
              @foreach (MUi::ACTION_STATUSES as $k => $v)<option value="{{ $k }}" {{ (($a->status == $k) ? 'selected' : '') }}>{{ $v }}</option>@endforeach
            </select>
            @if (((($a->status != 'done') && $a->due_date) && ($a->due_date < MUi::today())))<span class="badge badge-overdue">Overdue</span>@endif
          </form>
          @else {{ MUi::actionBadge($a) }}@endif
        </td>
        @if ($record)<td class="right"><form method="post" action="{{ route('meetings.action_delete', ['item_id' => $a->id]) }}" data-confirm="Delete this action item?">@csrf<button class="icon-btn danger" title="Delete">{{ MUi::icon('trash', 16) }}</button></form></td>@endif
      </tr>
      @endforeach
      </tbody>
    </table>
    </div>
    @else <div class="empty compact">{{ MUi::icon('tasks', 26) }}<p>No action items.</p></div>@endif
  </section>
  @if ($record)
  <section class="card">
    <h2>Assign a new action item</h2>
    <form method="post" action="{{ route('meetings.action_add', ['meeting_id' => $meeting->id]) }}" class="form">
      @csrf
      <label>Task *<input name="title" required placeholder="e.g. Prepare the budget proposal"></label>
      <div class="row-2">
        <label>Assign to
          <select name="assignee_id"><option value="">—</option>
            @foreach ($people_data->users as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach
          </select>
        </label>
        <label>Due date<input type="date" name="due_date"></label>
      </div>
      <label>Details<textarea name="description" rows="2"></textarea></label>
      <div class="form-actions"><button class="btn btn-primary" type="submit">{{ MUi::icon('plus', 16) }} Add action item</button></div>
    </form>
  </section>
  @endif
</div>


<div class="tab-panel" id="tab-files">
  <section class="card">
    <h2>Documents &amp; files</h2>
    @if ($attachments)
    <ul class="file-list">
      @foreach ($attachments as $f)
      <li>
        <span class="file-icon">{{ mb_strtoupper(MUi::ext($f->filename)) }}</span>
        <div class="file-info"><a href="{{ route('meetings.attachment_download', ['item_id' => $f->id]) }}">{{ $f->filename }}</a>
          <div class="muted small">{{ MUi::filesize($f->size) }} · {{ ($f->uploader ?: 'Unknown') }} · {{ MUi::datetime($f->uploaded_at) }}@if ($f->agenda_title) · for “{{ $f->agenda_title }}”@endif</div></div>
        @if (($record ?: ($f->uploaded_by == auth()->user()->id)))
        <form method="post" action="{{ route('meetings.attachment_delete', ['item_id' => $f->id]) }}" data-confirm="Delete {{ $f->filename }}?">@csrf<button class="icon-btn danger" title="Delete">{{ MUi::icon('trash', 16) }}</button></form>
        @endif
      </li>
      @endforeach
    </ul>
    @else <div class="empty compact">{{ MUi::icon('file', 26) }}<p>No files uploaded.</p></div>@endif
    @if (($record ?: $me))
    <form method="post" action="{{ route('meetings.attachment_upload', ['meeting_id' => $meeting->id]) }}" enctype="multipart/form-data" class="upload-form">
      @csrf
      <label class="dropzone">{{ MUi::icon('download', 20) }}<span>Choose a file to upload <small class="muted">PDF, Word, Excel, PowerPoint, images, text or zip · max 20 MB</small></span>
        <input type="file" name="file" required></label>
      @if ($agenda)
      <select name="agenda_item_id" aria-label="Agenda item" class="select-sm">
        <option value="">Whole meeting</option>
        @foreach ($agenda as $a)<option value="{{ $a->id }}">Report for: {{ $loop->iteration }}. {{ $a->title }}</option>@endforeach
      </select>
      @endif
      <button class="btn btn-primary" type="submit">Upload</button>
    </form>
    @endif
  </section>
</div>

@if ($manage)
<section class="danger-zone">
  <div><strong>Danger zone</strong><div class="muted small">Cancelling notifies participants. Deleting removes the agenda, minutes and files permanently.</div></div>
  <div class="btn-group">
    @if (((count($series) > 1) && ($meeting->status != 'cancelled')))
    <form method="post" action="{{ route('meetings.meeting_status', ['meeting_id' => $meeting->id]) }}" data-confirm="Cancel this meeting and all following meetings in the series?">
      @csrf<input type="hidden" name="status" value="cancelled"><input type="hidden" name="scope" value="following">
      <button class="btn btn-sm btn-ghost-danger">Cancel this &amp; following</button>
    </form>
    @endif
    <form method="post" action="{{ route('meetings.meeting_delete', ['meeting_id' => $meeting->id]) }}" data-confirm="Permanently delete this meeting and all its records? This cannot be undone.">
      @csrf<button class="btn btn-sm btn-ghost-danger">{{ MUi::icon('trash', 15) }} Delete meeting</button>
    </form>
    @if ((count($series) > 1))
    <form method="post" action="{{ route('meetings.meeting_delete', ['meeting_id' => $meeting->id]) }}" data-confirm="Permanently delete this meeting AND all following meetings in the series? This cannot be undone.">
      @csrf<input type="hidden" name="scope" value="following"><button class="btn btn-sm btn-ghost-danger">Delete this &amp; following</button>
    </form>
    @endif
  </div>
</section>
@endif
@endsection
