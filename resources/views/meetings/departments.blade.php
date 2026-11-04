@extends('layouts.meetings')


@section('title')Departments{{ '' }}@endsection

@section('content')

<div class="page-head"><div><h1>Departments</h1></div></div>
<section class="card narrow">
  <ul class="dept-list">
    @forelse ($items as $d)
    <li>
      <form method="post" class="dept-row">
        @csrf<input type="hidden" name="id" value="{{ $d->id }}">
        <input name="name" value="{{ $d->name }}" aria-label="Department name" required>
        <span class="muted small nowrap">{{ $d->members }} member{{ (($d->members != 1) ? 's' : '') }}</span>
        <button class="btn btn-sm" name="action" value="rename">Rename</button>
        <button class="icon-btn danger" name="action" value="delete" title="Delete" data-confirm="Delete {{ $d->name }}? Members will have no department.">{{ MUi::icon('trash', 16) }}</button>
      </form>
    </li>
    @empty <li class="empty">No departments yet.</li>@endforelse
  </ul>
  <form method="post" class="dept-row add">
    @csrf<input name="name" placeholder="New department name" required>
    <button class="btn btn-primary btn-sm" name="action" value="add">{{ MUi::icon('plus', 15) }} Add</button>
  </form>
</section>
@endsection
