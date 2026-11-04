@extends('layouts.meetings')


@section('title')My profile{{ '' }}@endsection

@section('content')

<div class="page-head"><div><h1>My profile</h1></div></div>
<div class="grid-2">
  <section class="card">
    <div class="profile-head">
      {{ MUi::avatar(auth()->user()->name) }}
      <div><h2>{{ auth()->user()->name }}</h2><div class="muted">{{ (auth()->user()->job_title ?: '') }}</div></div>
    </div>
    <dl class="facts">
      <dt>Email</dt><dd>{{ auth()->user()->email }}</dd>
      <dt>Role</dt><dd>{{ MUi::ROLES[auth()->user()->meetingRole()] }}</dd>
      <dt>Department</dt><dd>{{ (auth()->user()->departmentName() ?: '—') }}</dd>
      <dt>Meetings invited to</dt><dd>{{ $stats->meetings }}</dd>
      <dt>Meetings attended</dt><dd>{{ $stats->attended }}</dd>
      <dt>Open action items</dt><dd>{{ $stats->open_tasks }}</dd>
      <dt>Groups</dt><dd>@forelse ($my_groups as $grp)<span class="aud-chip" style="--c: {{ $grp->color }}">{{ $grp->name }}</span> @empty —@endforelse</dd>
    </dl>
  </section>
  <section class="card"><h2>Password</h2><p class="muted">You sign in with your TFC Portal account.</p><div class="form-actions"><a class="btn btn-primary" href="/password">Change password</a></div></section>
</div>
@endsection
