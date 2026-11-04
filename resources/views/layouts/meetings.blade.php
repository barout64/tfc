<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title>@yield('title') · TFC Meetings</title>
  <link rel="icon" href="/images/coat-of-arms-sm.png" type="image/png">
  <script>
    try { var t = localStorage.getItem('theme'); if (t) document.documentElement.dataset.theme = t; } catch (e) {}
  </script>
  <link rel="stylesheet" href="{{ asset('meeting-app/style.css') }}?v=20261104">
</head>
<body>
@php
    $me = auth()->user();
    $on = fn (string ...$names) => request()->routeIs(...array_map(fn ($n) => 'meetings.'.$n, $names)) ? 'active' : '';
@endphp
<div class="layout">
  <aside class="sidebar" id="sidebar">
    <a class="brand" href="{{ route('meetings.dashboard') }}">
      <div class="brand-mark">{{ MUi::icon('calendar', 19) }}</div>
      <div class="brand-text">
        <div class="brand-name">TFC Meetings</div>
        <div class="brand-sub">{{ MUi::org() }}</div>
      </div>
    </a>

    @if (MUi::canCreate())
    <a class="btn btn-primary btn-block sidebar-cta" href="{{ route('meetings.meeting_new') }}">{{ MUi::icon('plus', 16) }} New meeting</a>
    @endif

    <nav class="nav">
      <a href="{{ route('home') }}">{{ MUi::icon('left') }}<span>TFC Portal</span></a>
      <div class="nav-section">Workspace</div>
      <a href="{{ route('meetings.dashboard') }}" class="{{ $on('dashboard') }}">{{ MUi::icon('home') }}<span>Dashboard</span></a>
      <a href="{{ route('meetings.meetings_list') }}" class="{{ $on('meeting*') }}">{{ MUi::icon('list') }}<span>Meetings</span></a>
      <a href="{{ route('meetings.tasks') }}" class="{{ $on('tasks') }}">{{ MUi::icon('tasks') }}<span>Action items</span></a>
      <a href="{{ route('meetings.decisions_list') }}" class="{{ $on('decisions_list') }}">{{ MUi::icon('gavel') }}<span>Decisions</span></a>

      <div class="nav-section">Planning</div>
      <a href="{{ route('meetings.calendar_view') }}" class="{{ $on('calendar_view') }}">{{ MUi::icon('calendar') }}<span>Calendar</span></a>
      <a href="{{ route('meetings.planner') }}" class="{{ $on('planner') }}">{{ MUi::icon('year') }}<span>Annual planner</span></a>
      <a href="{{ route('meetings.groups') }}" class="{{ $on('group*') }}">{{ MUi::icon('layers') }}<span>Groups &amp; committees</span></a>
      <a href="{{ route('meetings.rooms') }}" class="{{ $on('rooms') }}">{{ MUi::icon('pin') }}<span>Rooms</span></a>
      @if (MUi::canCreate())
      <a href="{{ route('meetings.reports') }}" class="{{ $on('reports') }}">{{ MUi::icon('chart') }}<span>Reports</span></a>
      @endif

      @if (MUi::isAdmin())
      <div class="nav-section">Administration</div>
      @can('admin')
      <a href="{{ route('admin.users.index') }}">{{ MUi::icon('users') }}<span>People &amp; access</span></a>
      @endcan
      <a href="{{ route('meetings.departments') }}" class="{{ $on('departments') }}">{{ MUi::icon('briefcase') }}<span>Departments</span></a>
      @endif
    </nav>

    <a class="sidebar-user" href="{{ route('meetings.profile') }}">
      {{ MUi::avatar($me->name) }}
      <span class="su-text"><strong>{{ $me->name }}</strong><small>{{ MUi::ROLES[$me->meetingRole()] }}</small></span>
    </a>
  </aside>
  <div class="sidebar-backdrop" id="backdrop"></div>

  <div class="main">
    <header class="topbar">
      <button class="icon-btn menu-btn" id="menuBtn" aria-label="Open menu">{{ MUi::icon('menu') }}</button>
      <form class="search" action="{{ route('meetings.meetings_list') }}" method="get" role="search">
        {{ MUi::icon('search', 16) }}
        <input type="search" name="q" placeholder="Search meetings…" value="{{ request()->routeIs('meetings.meetings_list') ? request('q') : '' }}" aria-label="Search meetings">
        <input type="hidden" name="scope" value="all">
      </form>
      <div class="topbar-right">
        <span class="today-label hide-sm">{{ MUi::longdate(MUi::today()) }}</span>
        <button class="icon-btn" id="themeBtn" type="button" aria-label="Toggle dark mode" title="Toggle dark mode">
          <span class="theme-moon">{{ MUi::icon('moon') }}</span><span class="theme-sun">{{ MUi::icon('sun') }}</span>
        </button>
        <a class="icon-btn bell" href="{{ route('meetings.notifications') }}" aria-label="Notifications">
          {{ MUi::icon('bell') }}@if (MUi::unread())<span class="dot">{{ MUi::unread() < 100 ? MUi::unread() : '99+' }}</span>@endif
        </a>
        <details class="user-menu">
          <summary aria-label="Account menu">{{ MUi::avatar($me->name) }}{{ MUi::icon('down', 14) }}</summary>
          <div class="menu">
            <div class="menu-head">
              <strong>{{ $me->name }}</strong>
              <span>{{ $me->email }}</span>
              <span>{{ MUi::ROLES[$me->meetingRole()] }}@if ($me->departmentName()) · {{ $me->departmentName() }}@endif</span>
            </div>
            <a href="{{ route('home') }}">{{ MUi::icon('home', 16) }} TFC Portal</a>
            <a href="{{ route('meetings.profile') }}">{{ MUi::icon('user', 16) }} My profile</a>
            <a href="{{ route('meetings.notifications') }}">{{ MUi::icon('bell', 16) }} Notifications</a>
            <form method="post" action="{{ route('logout') }}">@csrf
              <button type="submit">{{ MUi::icon('logout', 16) }} Sign out</button>
            </form>
          </div>
        </details>
      </div>
    </header>

    <main class="content">
      @if (session('meeting_flash'))
        <div class="flashes">
        @foreach (session('meeting_flash') as [$cat, $msg])
          <div class="flash flash-{{ $cat }}" role="status">
            <span class="flash-icon">{{ MUi::icon($cat === 'success' ? 'tick' : (in_array($cat, ['error', 'warning'], true) ? 'alert' : 'bell'), 16) }}</span>
            <span class="flash-msg">{{ $msg }}</span>
            <button class="flash-close" aria-label="Dismiss">{{ MUi::icon('x', 14) }}</button>
          </div>
        @endforeach
        </div>
      @endif
      @yield('content')
    </main>
  </div>
</div>
<script src="{{ asset('meeting-app/app.js') }}?v=20261104"></script>
</body>
</html>
