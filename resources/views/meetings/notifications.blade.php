@extends('layouts.meetings')


@section('title')Notifications{{ '' }}@endsection

@section('content')

<div class="page-head">
  <div><h1>Notifications</h1></div>
  @if (MUi::unread())
  <form method="post" action="{{ route('meetings.notifications_read_all', []) }}">@csrf<button class="btn btn-sm">Mark all as read</button></form>
  @endif
</div>
<section class="card">
  @if ($items)
  <ul class="notif-list">
    @foreach ($items as $n)
    <li class="{{ (! $n->is_read ? 'unread' : '') }}">
      <a href="{{ route('meetings.notification_open', ['item_id' => $n->id]) }}">
        {{ MUi::icon('bell', 16) }}
        <span class="notif-msg">{{ $n->message }}</span>
        <span class="muted small nowrap">{{ MUi::datetime($n->created_at) }}</span>
      </a>
    </li>
    @endforeach
  </ul>
  @else 
  <div class="empty">You have no notifications.</div>
  @endif
</section>
@endsection
