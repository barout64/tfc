@extends('layouts.meetings')


@section('title')Groups & committees{{ '' }}@endsection

@section('content')

<div class="page-head">
  <div><h1>Groups &amp; committees</h1><p class="muted">Standing groups such as the Board or Management Team. Invite a whole group to a meeting in one click.</p></div>
  @if (MUi::canManageGroups())<a class="btn btn-primary" href="{{ route('meetings.group_form', []) }}">{{ MUi::icon('plus', 16) }} New group</a>@endif
</div>

@if ($items)
<div class="group-grid">
  @foreach ($items as $it)
  @php($grp = $it->group)
  <section class="card group-card" style="--c: {{ $grp->color }}">
    <div class="group-top">
      <span class="group-icon">{{ MUi::icon('layers', 20) }}</span>
      <div class="group-title">
        <h2>{{ $grp->name }}</h2>
        <div class="muted small">{{ count($it->members) }} member{{ ((count($it->members) != 1) ? 's' : '') }}@if ($it->is_member) · <span class="text-accent">You are a member</span>@endif</div>
      </div>
    </div>
    @if ($grp->description)<p class="muted">{{ $grp->description }}</p>@endif
    <div class="group-members">
      {{ MUi::avatarStack(array_column($it->members, 'name'), 7) }}
    </div>
    <div class="group-names small muted">{{ (implode(', ', array_column($it->members, 'name')) ?: 'No members yet') }}</div>
    <div class="group-foot">
      <span class="small muted">{{ MUi::icon('calendar', 13) }} {{ $it->upcoming }} upcoming meeting{{ (($it->upcoming != 1) ? 's' : '') }}</span>
      <span class="btn-group">
        @if (MUi::canCreate())<a class="btn btn-sm" href="{{ route('meetings.meeting_new', ['group' => $grp->id]) }}">{{ MUi::icon('plus', 14) }} Meeting</a>@endif
        @if (MUi::canManageGroups())<a class="btn btn-sm" href="{{ route('meetings.group_form', ['group_id' => $grp->id]) }}">{{ MUi::icon('edit', 14) }} Edit</a>@endif
      </span>
    </div>
  </section>
  @endforeach
</div>
@else 
<div class="card empty">{{ MUi::icon('layers', 30) }}<p>No groups yet. Create groups such as “Board of Directors” or “Finance Committee” to invite them quickly.</p>
  @if (MUi::canManageGroups())<a class="btn btn-primary" href="{{ route('meetings.group_form', []) }}">Create the first group</a>@endif</div>
@endif
@endsection
