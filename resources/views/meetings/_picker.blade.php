@php
    $sel_departments ??= [];
    $sel_groups ??= [];
    $field ??= 'participants';
    $locked ??= [];
    $show_groups ??= true;
@endphp
<div class="picker" data-picker>
  <div class="seg" role="tablist">
    <button type="button" class="active" data-ptab="people">{{ MUi::icon('user', 15) }} People</button>
    <button type="button" data-ptab="departments">{{ MUi::icon('briefcase', 15) }} Departments</button>
    @if ($show_groups)<button type="button" data-ptab="groups">{{ MUi::icon('layers', 15) }} Groups</button>@endif
  </div>

  <div class="picker-summary">
    <span><strong data-count>0</strong> people selected</span>
    <span class="picker-links">
      <button type="button" class="link-btn" data-all>Select everyone</button>
      <button type="button" class="link-btn" data-clear>Clear</button>
    </span>
  </div>
  <div class="chip-tray" data-tray></div>

  <div class="picker-pane active" data-pane="people">
    <div class="input-icon">{{ MUi::icon('search', 15) }}<input type="search" data-filter placeholder="Search by name, title or department"></div>
    <div class="people-list">
      @foreach ($data->users as $u)
      @php($is_locked = (in_array($u->id, $locked)))
      <label class="person {{ ($is_locked ? 'locked' : '') }}" data-search="{{ mb_strtolower(($u->name . ' ' . ($u->department ?: '') . ' ' . ($u->job_title ?: ''))) }}">
        <input type="checkbox" name="{{ $field }}[]" value="{{ $u->id }}" data-name="{{ $u->name }}" {{ (((in_array($u->id, $selected)) ?: $is_locked) ? 'checked' : '') }} {{ ($is_locked ? 'disabled' : '') }}>
        {{ MUi::avatar($u->name, 'sm') }}
        <span class="person-text"><strong>{{ $u->name }}</strong><small>{{ ($u->job_title ?: '') }}@if ($u->department) · {{ $u->department }}@endif</small></span>
        @if ($is_locked)<span class="badge">Invited</span>@endif
      </label>
      @endforeach
    </div>
  </div>

  <div class="picker-pane" data-pane="departments">
    <p class="hint">Tick a department to invite all its members. You can still untick individuals afterwards.</p>
    <div class="unit-list">
      @forelse ($data->departments as $d)
      <label class="unit {{ (! $d->members ? 'empty-unit' : '') }}">
        <input type="checkbox" name="departments[]" value="{{ $d->id }}" data-members="{{ implode(',', $d->members) }}" {{ ((in_array($d->id, $sel_departments)) ? 'checked' : '') }} {{ (! $d->members ? 'disabled' : '') }}>
        <span class="unit-icon">{{ MUi::icon('briefcase', 16) }}</span>
        <span class="unit-text"><strong>{{ $d->name }}</strong><small>{{ count($d->members) }} member{{ ((count($d->members) != 1) ? 's' : '') }}</small></span>
        <span class="unit-check">{{ MUi::icon('tick', 14) }}</span>
      </label>
      @empty <p class="muted">No departments yet.</p>@endforelse
    </div>
  </div>

  @if ($show_groups)
  <div class="picker-pane" data-pane="groups">
    <p class="hint">Groups and committees, e.g. the Board of Directors. @if (MUi::canManageGroups())<a href="{{ route('meetings.groups', []) }}" target="_blank">Manage groups</a>@endif</p>
    <div class="unit-list">
      @forelse ($data->groups as $grp)
      <label class="unit {{ (! $grp->members ? 'empty-unit' : '') }}" style="--c: {{ $grp->color }}">
        <input type="checkbox" name="groups[]" value="{{ $grp->id }}" data-members="{{ implode(',', $grp->members) }}" {{ ((in_array($grp->id, $sel_groups)) ? 'checked' : '') }} {{ (! $grp->members ? 'disabled' : '') }}>
        <span class="unit-icon colored">{{ MUi::icon('layers', 16) }}</span>
        <span class="unit-text"><strong>{{ $grp->name }}</strong><small>{{ count($grp->members) }} member{{ ((count($grp->members) != 1) ? 's' : '') }}@if ($grp->description) · {{ $grp->description }}@endif</small></span>
        <span class="unit-check">{{ MUi::icon('tick', 14) }}</span>
      </label>
      @empty <p class="muted">No groups yet.@if (MUi::canManageGroups()) <a href="{{ route('meetings.group_form', []) }}">Create one</a>.@endif</p>@endforelse
    </div>
  </div>
  @endif
</div>