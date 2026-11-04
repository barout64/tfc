@extends('layouts.meetings')


@section('title'){{ ($editing ? 'Edit group' : 'New group') }}@endsection

@section('content')

<a class="back-link" href="{{ route('meetings.groups', []) }}">{{ MUi::icon('left', 16) }} Groups &amp; committees</a>
<div class="page-head"><div><h1>{{ ($editing ? 'Edit group' : 'Create a group or committee') }}</h1></div></div>

<form method="post" class="form form-grid">
  @csrf
  <div class="form-col">
    <section class="card">
      <label>Group name *<input name="name" required value="{{ ($form->name ?: '') }}" placeholder="e.g. Board of Directors"></label>
      <label>Description<textarea name="description" rows="3" placeholder="What is this group for?">{{ ($form->description ?: '') }}</textarea></label>
      <div class="field">
        <span class="field-label">Colour</span>
        <div class="swatches">
          @foreach ($colors as $c)
          <label class="swatch" style="--c: {{ $c }}"><input type="radio" name="color" value="{{ $c }}" {{ (($form->color == $c) ? 'checked' : '') }}><span></span></label>
          @endforeach
        </div>
      </div>
    </section>
    @if ($editing)
    <section class="danger-zone">
      <div><strong>Delete group</strong><div class="muted small">Meetings already scheduled keep their participants.</div></div>
      <button class="btn btn-sm btn-ghost-danger" type="submit" formaction="{{ route('meetings.group_delete', ['group_id' => $group_id]) }}" data-confirm="Delete this group?">{{ MUi::icon('trash', 15) }} Delete group</button>
    </section>
    @endif
  </div>
  <div class="form-col">
    <section class="card">
      <h2>Members</h2>
      <p class="muted small">Pick people, or tick a department to add all its members.</p>
      @include('meetings._picker', ['data' => $people_data, 'selected' => $selected, 'field' => 'members', 'show_groups' => false])
    </section>
  </div>
  <div class="form-actions sticky-actions">
    <button class="btn btn-primary btn-lg" type="submit">{{ MUi::icon('tick', 16) }} {{ ($editing ? 'Save group' : 'Create group') }}</button>
    <a class="btn btn-lg" href="{{ route('meetings.groups', []) }}">Cancel</a>
  </div>
</form>
@endsection
